<?php

namespace App\Services\Learning;

use App\AI\Gateway\AiGateway;
use App\AI\Privacy\PiiRedactor;
use App\Domain\Assessment\PassportService;
use App\Models\CompetencyFramework;
use App\Models\CompetencyScore;
use App\Models\Mission;
use App\Models\MissionAttempt;
use App\Models\User;
use App\Services\Institution\ProtectedTermsRepository;
use App\Support\Audit;
use DomainException;

/**
 * AI Lab: practice with a coach. Each step is checked two ways — deterministic checks (e.g. did the
 * learner find the planted error?) and an AI coach scoring the rubric. The coach can't pass a step
 * the deterministic check failed. Practice data is synthetic; submissions are redacted before the coach.
 */
final class MissionService
{
    public const MAX_TRIES_PER_STEP = 3;

    public function __construct(
        private AiGateway $gateway,
        private CoachResponseParser $parser,
        private GamificationService $gamification,
        private PassportService $passports,
        private PiiRedactor $redactor,
        private ProtectedTermsRepository $terms,
    ) {}

    public function start(User $user, Mission $mission): MissionAttempt
    {
        return MissionAttempt::query()->where('user_id', $user->id)->where('mission_id', $mission->id)->where('status', 'in_progress')->first()
            ?? MissionAttempt::create(['mission_id' => $mission->id, 'user_id' => $user->id, 'status' => 'in_progress', 'step_index' => 0, 'transcript' => [], 'started_at' => now()]);
    }

    public function submit(User $user, MissionAttempt $attempt, string $submission): array
    {
        abort_unless($attempt->user_id === $user->id, 404);
        if ($attempt->status !== 'in_progress') {
            throw new DomainException(__('This mission is already finished.'));
        }

        $mission = $attempt->mission;
        $step = $mission->steps[$attempt->step_index] ?? throw new DomainException('No such step.');
        $criteria = array_column($mission->rubric, 'criterion');
        $clean = $this->redactor->redact(mb_substr($submission, 0, 6000), $this->terms->forTenant($user->tenant_id))['text'];

        $check = $this->deterministic($mission, $step, $clean);

        $run = $this->gateway->run($user, 'lab_coach', [
            'mission' => (string) ($mission->title['ar'] ?? $mission->key),
            'step' => (string) ($step['instruction']['ar'] ?? $step['key']),
            'criteria' => implode(', ', $criteria),
            'submission' => $clean,
            'check' => $check === null ? 'none' : ($check ? 'passed' : 'failed'),
        ]);
        $coach = $this->parser->parse($this->gateway->presentOutput($run, $user), $criteria);
        if ($check === false) {
            $coach['ready'] = false; // the model cannot override a failed objective check
        }

        $transcript = $attempt->transcript ?? [];
        $tries = collect($transcript)->where('step', $step['key'])->count() + 1;
        $transcript[] = ['step' => $step['key'], 'try' => $tries, 'submission' => $clean, 'check' => $check] + $coach;

        $advance = $coach['ready'] || $tries >= self::MAX_TRIES_PER_STEP;
        $attempt->update(['transcript' => $transcript, 'step_index' => $attempt->step_index + ($advance ? 1 : 0)]);

        if ($attempt->step_index >= count($mission->steps)) {
            $this->finish($user, $attempt->fresh());
        }

        return ['coach' => $coach, 'check' => $check, 'advanced' => $advance, 'attempt' => $attempt->fresh()];
    }

    public function finish(User $user, MissionAttempt $attempt): MissionAttempt
    {
        $mission = $attempt->mission;
        $byCriterion = [];
        foreach ($attempt->transcript ?? [] as $entry) {
            foreach ($entry['scores'] ?? [] as $criterion => $value) {
                $byCriterion[$criterion][] = $value;
            }
        }

        $weighted = 0;
        $weights = 0;
        foreach ($mission->rubric as $r) {
            $values = $byCriterion[$r['criterion']] ?? [0];
            $weighted += max($values) * $r['weight']; // best attempt per criterion counts
            $weights += $r['weight'];
        }
        $score = $weights ? round($weighted / $weights, 2) : 0;
        $xp = (int) round($mission->xp * ($score >= 60 ? 1 : 0.5));

        $attempt->update(['status' => 'completed', 'score' => $score, 'rubric_scores' => $byCriterion, 'completed_at' => now(), 'xp_awarded' => $xp]);

        $framework = CompetencyFramework::query()->where('is_default', true)->whereNull('tenant_id')->with('competencies')->first();
        if ($framework) {
            $ids = $framework->competencies->pluck('id', 'key');
            foreach (array_keys($mission->competency_impact ?? []) as $key) {
                if (isset($ids[$key])) {
                    CompetencyScore::create(['user_id' => $user->id, 'competency_id' => $ids[$key], 'score' => $score, 'source' => 'lab', 'source_id' => $attempt->id, 'recorded_at' => now()]);
                }
            }
            $this->passports->refresh($user, $framework);
        }

        // After the passport exists, so the XP lands on it too.
        $this->gamification->award($user, $xp, 'mission', "mission:{$attempt->id}");
        $this->gamification->evaluateBadges($user);
        Audit::log('mission.completed', $attempt, ['mission' => $mission->key, 'score' => $score]);

        return $attempt;
    }

    /** null = no objective check for this step; true/false = planted-answer check. */
    private function deterministic(Mission $mission, array $step, string $submission): ?bool
    {
        $accepted = $mission->dataset['answers'][$step['key']] ?? null;
        if (! $accepted) {
            return null;
        }
        $haystack = ArabicNormalizer::normalize($submission);

        return collect((array) $accepted)->contains(fn ($needle) => str_contains($haystack, ArabicNormalizer::normalize((string) $needle)));
    }
}
