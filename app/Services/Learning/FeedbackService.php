<?php

namespace App\Services\Learning;

use App\AI\Privacy\PiiRedactor;
use App\Models\AiRun;
use App\Models\AgentVersion;
use App\Models\OutputFeedback;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\Institution\ProtectedTermsRepository;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Explicit ratings and implicit signals (how much a user had to edit an AI output) feed BAREE's
 * feedback centre — only when the user agreed to help improve the agents, and always without names.
 */
final class FeedbackService
{
    public const REASONS = ['too_long', 'too_short', 'inaccurate', 'not_curriculum', 'not_local', 'language', 'format', 'great'];

    public function __construct(private PiiRedactor $redactor, private ProtectedTermsRepository $terms, private TenantContext $context) {}

    public function rate(User $user, AiRun $run, int $rating, array $reasons = [], ?string $comment = null): OutputFeedback
    {
        abort_unless($run->user_id === $user->id, 404);

        return OutputFeedback::create([
            'user_id' => $user->id, 'ai_run_id' => $run->id, 'agent_id' => AgentVersion::query()->whereKey($run->agent_version_id)->value('agent_id'),
            'rating' => max(1, min(5, $rating)), 'reasons' => array_values(array_intersect($reasons, self::REASONS)),
            'comment' => $comment ? $this->redactor->redact(mb_substr($comment, 0, 1000), $this->terms->forTenant($user->tenant_id))['text'] : null,
            'share_with_baree' => $this->consents($user), 'created_at' => now(),
        ]);
    }

    /** rating 0 = implicit signal: the user edited an AI output by hand. */
    public function recordEdit(User $user, ?int $aiRunId, string $before, string $after): void
    {
        if (! $aiRunId) {
            return;
        }
        $a = mb_substr($before, 0, 4000);
        $b = mb_substr($after, 0, 4000);
        similar_text($a, $b, $percent);
        $versionId = AiRun::query()->whereKey($aiRunId)->value('agent_version_id');

        OutputFeedback::create([
            'user_id' => $user->id, 'ai_run_id' => $aiRunId, 'agent_id' => AgentVersion::query()->whereKey($versionId)->value('agent_id'),
            'rating' => 0, 'edit_distance' => (int) round(100 - $percent), 'share_with_baree' => $this->consents($user), 'created_at' => now(),
        ]);
    }

    /** Cross-institution aggregates for BAREE staff: no user ids, no tenant ids, no comments from users who opted out. */
    public function platformSummary(int $days = 30): array
    {
        return $this->context->asPlatform(function () use ($days) {
            $base = OutputFeedback::query()->where('share_with_baree', true)->where('created_at', '>=', now()->subDays($days));

            $byAgent = (clone $base)->where('rating', '>', 0)->select('agent_id', DB::raw('count(*) as ratings'), DB::raw('avg(rating) as average'))
                ->groupBy('agent_id')->get()->keyBy('agent_id');
            $edits = (clone $base)->where('rating', 0)->select('agent_id', DB::raw('count(*) as edits'), DB::raw('avg(edit_distance) as change'))
                ->groupBy('agent_id')->get()->keyBy('agent_id');

            $reasons = [];
            foreach ((clone $base)->whereNotNull('reasons')->get(['agent_id', 'reasons']) as $row) {
                foreach ($row->reasons as $reason) {
                    $reasons[$row->agent_id][$reason] = ($reasons[$row->agent_id][$reason] ?? 0) + 1;
                }
            }

            $agents = \App\Models\Agent::query()->whereIn('id', $byAgent->keys()->merge($edits->keys())->unique())->get(['id', 'key', 'name'])->keyBy('id');

            return $agents->map(fn ($agent) => [
                'agent' => $agent->key, 'name' => $agent->name,
                'ratings' => (int) ($byAgent[$agent->id]->ratings ?? 0), 'average' => round((float) ($byAgent[$agent->id]->average ?? 0), 2),
                'edits' => (int) ($edits[$agent->id]->edits ?? 0), 'average_change' => round((float) ($edits[$agent->id]->change ?? 0), 1),
                'top_reasons' => collect($reasons[$agent->id] ?? [])->sortDesc()->take(3)->all(),
                'comments' => (clone $base)->where('agent_id', $agent->id)->whereNotNull('comment')->latest('created_at')->limit(5)->pluck('comment'),
            ])->values()->all();
        });
    }

    private function consents(User $user): bool
    {
        // Default on (stated during onboarding); the user can switch it off in profile settings.
        return UserConsent::query()->where('user_id', $user->id)->where('key', 'improve_agents')->value('granted') ?? true;
    }
}
