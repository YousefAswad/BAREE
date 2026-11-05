<?php

namespace App\Services\Platform;

use App\Models\Agent;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantAgentSetting;
use App\Models\User;
use App\Services\CreditService;
use App\Services\Institution\InvitationService;
use App\Support\Audit;
use App\Support\Tenancy\TenantContext;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class TenantProvisioningService
{
    public function __construct(private TenantContext $context, private CreditService $credits, private InvitationService $invitations) {}

    /** Creates the institution, its defaults, trial credits, and invites the owner. */
    public function provision(User $staff, array $data): Tenant
    {
        $plan = Plan::query()->where('key', $data['plan_key'])->where('active', true)->firstOrFail();

        return DB::transaction(function () use ($staff, $data, $plan) {
            $tenant = $this->context->asPlatform(fn () => Tenant::create([
                'name' => $data['name'],
                'slug' => $data['slug'] ?? Str::slug($data['name']).'-'.Str::lower(Str::random(4)),
                'type' => $data['type'] ?? 'school',
                'status' => 'trial',
                'country' => $data['country'] ?? null,
                'default_locale' => $data['locale'] ?? 'ar',
                'plan_id' => $plan->id,
                'trial_ends_at' => now()->addDays(30),
                'settings' => ['privacy' => ['mode' => config('baree.privacy.default_mode')], 'allowed_email_domains' => $data['allowed_email_domains'] ?? []],
            ]));

            $agentIds = $this->context->asPlatform(fn () => Agent::query()->whereNull('tenant_id')->where('status', 'published')->pluck('id'));

            $this->context->runAs($tenant->id, function () use ($tenant, $agentIds, $data, $staff, $plan) {
                foreach ($agentIds as $agentId) {
                    TenantAgentSetting::firstOrCreate(['agent_id' => $agentId], ['enabled' => true]);
                }

                $this->credits->grant($tenant->id, (int) ($data['trial_credits'] ?? config('baree.credits.trial_grant')), 'trial_grant');

                // Staff act as the inviter; the owner gets the institution_owner role on acceptance.
                $this->invitations->invite($staff, ['email' => $data['owner_email'], 'name' => $data['owner_name'] ?? null, 'role_key' => 'institution_owner']);
            });

            Audit::log('tenant.provisioned', $tenant, ['plan' => $plan->key]);

            return $tenant;
        });
    }

    public function setStatus(Tenant $tenant, string $status, string $reason): void
    {
        if (! in_array($status, ['trial', 'active', 'suspended', 'cancelled'], true)) {
            throw new DomainException('Unknown status.');
        }

        $this->context->asPlatform(fn () => $tenant->update(['status' => $status]));
        Audit::log('tenant.status_changed', $tenant, ['status' => $status, 'reason' => $reason]);
    }
}
