<?php

namespace App\AI;

use App\Models\Agent;
use App\Models\AgentVersion;

/**
 * Gradual rollout: a version in "rolling_out" serves only the tenants its rollout targets
 * (pilot list or a stable percentage bucket). Everyone else keeps the published version.
 */
final class VersionResolver
{
    public function forTenant(Agent $agent, ?int $tenantId): ?AgentVersion
    {
        if ($tenantId !== null) {
            $candidate = AgentVersion::query()->where('agent_id', $agent->id)->where('status', 'rolling_out')
                ->orderByDesc('version')->first();

            if ($candidate && $this->inRollout($candidate->rollout ?? [], $agent->id, $tenantId)) {
                return $candidate;
            }
        }

        return $agent->publishedVersion;
    }

    public function inRollout(array $rollout, int $agentId, int $tenantId): bool
    {
        return match ($rollout['mode'] ?? null) {
            'all' => true,
            'pilot' => in_array($tenantId, $rollout['tenant_ids'] ?? [], true),
            // Stable bucket per (agent, tenant): the same institution stays in or out as the percentage grows.
            'percent' => (crc32("agent:{$agentId}:tenant:{$tenantId}") % 100) < (int) ($rollout['percent'] ?? 0),
            default => false,
        };
    }
}
