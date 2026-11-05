<?php

namespace App\Services\Platform;

use App\Models\AiRun;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

final class PlatformMetrics
{
    public function __construct(private TenantContext $context) {}

    public function overview(int $days = 30): array
    {
        return $this->context->asPlatform(function () use ($days) {
            $since = now()->subDays($days);
            $runs = AiRun::query()->where('created_at', '>=', $since);

            return [
                'tenants' => Tenant::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
                'active_users' => User::query()->whereNotNull('tenant_id')->where('last_active_at', '>=', $since)->count(),
                'runs' => (clone $runs)->count(),
                'runs_failed' => (clone $runs)->where('status', 'failed')->count(),
                'credits' => (int) (clone $runs)->sum('credits'),
                'tokens_by_provider' => (clone $runs)->whereNotNull('provider')
                    ->select('provider', DB::raw('sum(input_tokens) as input'), DB::raw('sum(output_tokens) as output'), DB::raw('count(*) as runs'))
                    ->groupBy('provider')->get(),
                'p95_latency_ms' => $this->p95((clone $runs)->whereNotNull('latency_ms')->pluck('latency_ms')->all()),
            ];
        });
    }

    private function p95(array $values): ?int
    {
        if ($values === []) {
            return null;
        }
        sort($values);

        return (int) $values[(int) floor(0.95 * (count($values) - 1))];
    }
}
