<?php

namespace App\Domain\Assessment;

use App\Models\AssessmentAttempt;
use App\Models\AssessmentTemplate;
use App\Models\AttemptAnswer;
use App\Models\CompetencyScore;
use App\Models\QuestionOption;
use App\Models\User;
use App\Support\Audit;
use DomainException;
use Illuminate\Support\Facades\DB;

final class AssessmentService
{
    public function __construct(private ScoringEngine $engine, private PassportService $passports) {}

    public function start(User $user, AssessmentTemplate $template, ?int $campaignId = null): AssessmentAttempt
    {
        $existing = AssessmentAttempt::query()
            ->where('user_id', $user->id)->where('template_id', $template->id)->where('status', 'in_progress')->first();

        return $existing ?? AssessmentAttempt::create([
            'user_id' => $user->id,
            'template_id' => $template->id,
            'campaign_id' => $campaignId,
            'framework_version' => $template->framework->version,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
    }

    public function answer(AssessmentAttempt $attempt, int $questionId, ?int $optionId, ?int $confidence, ?int $timeMs): AttemptAnswer
    {
        $this->assertOpen($attempt);

        $item = $attempt->template->items()->where('question_id', $questionId)->with('question')->first()
            ?? throw new DomainException('Question is not part of this assessment.');

        $option = $optionId === null ? null : QuestionOption::query()
            ->where('question_id', $questionId)->whereKey($optionId)->first()
            ?? throw new DomainException('Option does not belong to this question.');

        return AttemptAnswer::updateOrCreate(
            ['attempt_id' => $attempt->id, 'question_id' => $questionId],
            [
                'option_id' => $option?->id,
                'confidence' => $confidence,
                'score' => $option?->score ?? 0,
                'max_score' => 3,
                'time_ms' => $timeMs,
            ],
        );
    }

    public function submit(AssessmentAttempt $attempt): ScoringResult
    {
        $this->assertOpen($attempt);

        $template = $attempt->template()->with(['framework.competencies', 'framework.levels', 'items.question'])->firstOrFail();
        $answers = $attempt->answers()->with('question')->get();

        if ($answers->count() < $template->items->count()) {
            throw new DomainException('Please answer every question before submitting.');
        }

        $result = $this->engine->score(
            $template->framework->competencies->mapWithKeys(fn ($c) => [$c->id => ['key' => $c->key, 'weight' => $c->weight]])->all(),
            $answers->map(fn ($a) => [
                'competency_id' => $a->question->competency_id,
                'score' => $a->score,
                'max_score' => $a->max_score,
                'weight' => $a->question->weight,
                'confidence' => $a->confidence,
            ])->all(),
            $template->framework->levels->map(fn ($l) => [
                'id' => $l->id, 'rank' => $l->rank, 'key' => $l->key, 'min_score' => $l->min_score, 'requirements' => $l->requirements,
            ])->all(),
        );

        DB::transaction(function () use ($attempt, $template, $result) {
            $attempt->update([
                'status' => 'scored',
                'submitted_at' => now(),
                'overall_score' => $result->overall,
                'level_id' => $result->levelId,
                'result' => $result->toArray(),
            ]);

            $ids = $template->framework->competencies->pluck('id', 'key');
            foreach ($result->competencies as $key => $score) {
                CompetencyScore::create([
                    'user_id' => $attempt->user_id, 'competency_id' => $ids[$key], 'score' => $score,
                    'source' => 'assessment', 'source_id' => $attempt->id, 'recorded_at' => now(),
                ]);
            }
        });

        $this->passports->refresh($attempt->user()->firstOrFail(), $template->framework);
        Audit::log('assessment.scored', $attempt, ['overall' => $result->overall, 'level' => $result->levelKey]);

        return $result;
    }

    private function assertOpen(AssessmentAttempt $attempt): void
    {
        if ($attempt->status !== 'in_progress') {
            throw new DomainException('This assessment has already been submitted.');
        }

        $limit = $attempt->template->time_limit_minutes;
        if ($limit && $attempt->started_at->addMinutes($limit + 5)->isPast()) {
            throw new DomainException('Time limit exceeded.');
        }
    }
}
