<?php

namespace App\Services\Institution;

use App\Models\AssessmentAttempt;
use App\Models\AssessmentCampaign;
use App\Models\Department;
use App\Models\Passport;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserConsent;

/**
 * Institution analytics with privacy built in:
 *  - k-anonymity: any group smaller than baree.privacy.min_group_size is suppressed.
 *  - individual results depend on the tenant's privacy mode AND, in consent mode, the employee's choice.
 */
final class AnalyticsService
{
    public function __construct(private AudienceResolver $audiences) {}

    private function k(): int
    {
        return (int) config('baree.privacy.min_group_size', 5);
    }

    public function readinessByDepartment(): array
    {
        $rows = Passport::query()
            ->join('users', 'users.id', '=', 'passports.user_id')
            ->selectRaw('users.department_id as department_id, count(*) as n, avg(passports.readiness) as readiness')
            ->groupBy('users.department_id')->get();
        $names = Department::query()->pluck('name', 'id');

        return $rows->map(fn ($r) => [
            'department' => $names[$r->department_id] ?? null,
            'people' => $r->n >= $this->k() ? (int) $r->n : null,
            'readiness' => $r->n >= $this->k() ? round((float) $r->readiness, 1) : null,
            'suppressed' => $r->n < $this->k(),
        ])->values()->all();
    }

    public function levelDistribution(): array
    {
        $rows = Passport::query()->join('levels', 'levels.id', '=', 'passports.level_id')
            ->selectRaw('levels.rank as level_rank, levels.key as level_key, levels.name as level_name, levels.color as level_color, count(*) as n')
            ->groupBy('levels.id', 'levels.rank', 'levels.key')->orderBy('levels.rank')->get();
        $total = (int) $rows->sum('n');

        if ($total < $this->k()) {
            return ['suppressed' => true, 'levels' => []];
        }

        return ['suppressed' => false, 'total' => $total, 'levels' => $rows->map(fn ($r) => ['rank' => (int) $r->level_rank, 'key' => $r->level_key, 'name' => json_decode((string) $r->level_name, true) ?: null, 'color' => $r->level_color, 'count' => (int) $r->n])->all()];
    }

    public function campaignProgress(AssessmentCampaign $campaign): array
    {
        $audience = $this->audiences->query($campaign->audience ?? [], $campaign->tenant_id)->get(['id', 'department_id']);
        $done = AssessmentAttempt::query()->where('campaign_id', $campaign->id)->where('status', 'scored')->pluck('user_id')->flip();
        $names = Department::query()->pluck('name', 'id');

        return $audience->groupBy('department_id')->map(function ($users, $departmentId) use ($done, $names) {
            $n = $users->count();
            $completed = $users->filter(fn ($u) => isset($done[$u->id]))->count();

            return [
                'department' => $names[$departmentId] ?? null,
                'invited' => $n,
                // Completion of a tiny group would reveal who has not finished: suppress it.
                'completed' => $n >= $this->k() ? $completed : null,
                'rate' => $n >= $this->k() ? round(100 * $completed / $n, 1) : null,
            ];
        })->values()->all();
    }

    /** Returns the subject's level only when policy and consent allow it, otherwise null. */
    public function individualLevel(User $viewer, User $subject): ?array
    {
        if ($viewer->tenant_id !== $subject->tenant_id) {
            return null;
        }

        $mode = Tenant::query()->find($subject->tenant_id)?->setting('privacy.mode', config('baree.privacy.default_mode'));

        $allowed = match ($mode) {
            'consent' => $viewer->hasPermission('analytics.view_individual')
                && UserConsent::query()->where('user_id', $subject->id)->where('key', 'manager_visibility')->where('granted', true)->exists(),
            'department_heads' => $viewer->hasPermission('department.analytics.view', $subject),
            default => false, // 'aggregate'
        };

        if (! $allowed) {
            return null;
        }

        $passport = $subject->passport()->with('level')->first();

        return $passport ? ['level' => $passport->level?->key, 'readiness' => $passport->readiness] : null;
    }
}
