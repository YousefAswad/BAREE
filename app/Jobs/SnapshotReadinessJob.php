<?php

namespace App\Jobs;

use App\Models\Passport;
use App\Models\ReadinessSnapshot;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/** Monthly readiness snapshot per institution and per department (for trends). */
final class SnapshotReadinessJob implements ShouldQueue
{
    use Queueable;

    public function handle(TenantContext $context): void
    {
        $month = now()->startOfMonth()->toDateString();

        foreach ($context->asPlatform(fn () => Tenant::query()->whereIn('status', ['trial', 'active'])->pluck('id')) as $tenantId) {
            $context->runAs($tenantId, function () use ($month) {
                $rows = Passport::query()->join('users', 'users.id', '=', 'passports.user_id')->leftJoin('levels', 'levels.id', '=', 'passports.level_id')
                    ->get(['users.department_id', 'passports.readiness', 'levels.rank']);

                foreach ([null => $rows] + $rows->groupBy('department_id')->all() as $departmentId => $group) {
                    if ($group->isEmpty()) {
                        continue;
                    }
                    ReadinessSnapshot::query()->updateOrCreate(['department_id' => $departmentId ?: null, 'month' => $month], [
                        'people' => $group->count(), 'readiness' => round($group->avg('readiness'), 2),
                        'levels' => $group->countBy('rank')->all(),
                    ]);
                }
            });
        }
    }
}
