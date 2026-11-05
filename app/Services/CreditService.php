<?php

namespace App\Services;

use App\AI\Exceptions\AiPolicyViolation;
use App\Models\Agent;
use App\Models\CreditLedgerEntry;
use App\Models\TenantAgentSetting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Append-only credit ledger with per-agent monthly caps. */
final class CreditService
{
    public function balance(int $tenantId): int
    {
        return (int) CreditLedgerEntry::query()->where('tenant_id', $tenantId)->sum('delta');
    }

    public function ensureCanSpend(int $tenantId, int $cost, ?Agent $agent): void
    {
        if ($this->balance($tenantId) < $cost) {
            throw new AiPolicyViolation('Your institution has run out of AI credits.');
        }

        $cap = $agent ? TenantAgentSetting::query()->where('agent_id', $agent->id)->value('monthly_credit_cap') : null;
        if ($cap !== null && $agent) {
            $spent = -1 * (int) CreditLedgerEntry::query()->where('tenant_id', $tenantId)->where('agent_id', $agent->id)
                ->where('reason', 'ai_run')->where('created_at', '>=', now()->startOfMonth())->sum('delta');
            if ($spent + $cost > $cap) {
                throw new AiPolicyViolation('Monthly limit for this agent has been reached.');
            }
        }
    }

    public function debit(int $tenantId, int $amount, Model $reference, ?int $agentId, ?int $userId): void
    {
        // Serialise debits per tenant so concurrent runs cannot overspend.
        Cache::lock("credits:{$tenantId}", 10)->block(5, function () use ($tenantId, $amount, $reference, $agentId, $userId) {
            DB::transaction(fn () => CreditLedgerEntry::create([
                'tenant_id' => $tenantId, 'delta' => -$amount, 'reason' => 'ai_run',
                'reference_type' => $reference->getMorphClass(), 'reference_id' => $reference->getKey(),
                'agent_id' => $agentId, 'user_id' => $userId, 'created_at' => now(),
            ]));
        });
    }

    public function grant(int $tenantId, int $amount, string $reason = 'monthly_grant'): void
    {
        CreditLedgerEntry::create(['tenant_id' => $tenantId, 'delta' => $amount, 'reason' => $reason, 'created_at' => now()]);
    }
}
