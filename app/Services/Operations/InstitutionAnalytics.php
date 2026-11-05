<?php

namespace App\Services\Operations;

use App\Models\AiRun;
use App\Models\Certificate;
use App\Models\Department;
use App\Models\Output;
use App\Models\ReadinessSnapshot;
use App\Models\UserActivityDay;
use Illuminate\Support\Facades\DB;

/** Adoption and impact for the institution. Department rows below the k-anonymity threshold are suppressed. */
final class InstitutionAnalytics
{
    /** Minutes a typical task takes without AI, minus the review time with BAREE. Estimates, shown as such. */
    private const MINUTES_SAVED = ['lesson_plan' => 35, 'slides' => 40, 'activity' => 20, 'worksheet' => 30, 'quiz' => 25, 'homework' => 10, 'scenario' => 45, 'document' => 15];

    public function overview(int $tenantId, int $months = 6): array
    {
        $k = (int) config('baree.privacy.min_group_size');
        $from = now()->startOfMonth()->subMonths($months - 1);

        $monthly = UserActivityDay::query()->where('day', '>=', $from)->get(['user_id', 'day'])
            ->groupBy(fn ($r) => substr((string) $r->day, 0, 7))->map(fn ($rows) => $rows->pluck('user_id')->unique()->count())->sortKeys();

        $outputs = Output::query()->where('created_at', '>=', now()->subDays(90))->select('type', DB::raw('count(*) as n'))->groupBy('type')->pluck('n', 'type');
        $minutes = $outputs->reduce(fn ($sum, $n, $type) => $sum + $n * (self::MINUTES_SAVED[$type] ?? 10), 0);

        $names = Department::query()->pluck('name', 'id');
        $byDepartment = AiRun::query()->where('ai_runs.created_at', '>=', now()->subDays(30))->join('users', 'users.id', '=', 'ai_runs.user_id')
            ->select('users.department_id', DB::raw('count(*) as runs'), DB::raw('count(distinct ai_runs.user_id) as people'), DB::raw('sum(ai_runs.credits) as credits'))
            ->groupBy('users.department_id')->get()
            ->map(fn ($r) => ['department' => $names[$r->department_id] ?? null, 'runs' => $r->people >= $k ? (int) $r->runs : null,
                'people' => $r->people >= $k ? (int) $r->people : null, 'credits' => (int) $r->credits, 'suppressed' => $r->people < $k]);

        return [
            'active_by_month' => $monthly,
            'outputs_by_type' => $outputs,
            'hours_saved_estimate' => round($minutes / 60),
            'learning_hours' => (float) Certificate::query()->whereNull('revoked_at')->sum('hours'),
            'by_department' => $byDepartment->values(),
            'readiness_trend' => ReadinessSnapshot::query()->whereNull('department_id')->where('month', '>=', $from)->orderBy('month')->get(['month', 'readiness', 'people'])
                ->filter(fn ($s) => $s->people >= $k)->values(),
        ];
    }
}
