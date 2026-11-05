<?php

namespace App\Services\Institution;

use App\Models\Invitation;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\InvitationNotification;
use App\Support\Audit;
use App\Support\Tenancy\TenantContext;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

final class InvitationService
{
    public const TTL_DAYS = 7;

    public function __construct(private TenantContext $context) {}

    /** @return array{0: Invitation, 1: string} the raw token is returned once, for the e-mail only */
    public function invite(User $actor, array $data, ?int $importId = null): array
    {
        $tenant = Tenant::query()->findOrFail($this->context->id());
        $email = Str::lower(trim($data['email']));

        $this->assertAllowedDomain($tenant, $email);
        $role = $this->assertGrantableRole($actor, $data['role_key'] ?? 'staff');

        app(\App\Services\Billing\BillingService::class)->assertSeatAvailable($tenant->id);

        if (User::query()->where('email', $email)->exists()) {
            throw new DomainException(__('A user with this e-mail already exists.'));
        }

        Invitation::query()->where('email', $email)->whereNull('accepted_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);

        $raw = Str::random(64);
        $invitation = Invitation::create([
            'email' => $email,
            'name' => $data['name'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'job_title_id' => $data['job_title_id'] ?? null,
            'role_key' => $role->key,
            'token_hash' => hash('sha256', $raw),
            'expires_at' => now()->addDays(self::TTL_DAYS),
            'invited_by' => $actor->id,
            'import_id' => $importId,
        ]);

        Notification::route('mail', $email)->notify(new InvitationNotification($tenant->name, $raw, $tenant->default_locale ?? 'ar'));
        Audit::log('invitation.sent', $invitation, ['role' => $role->key]);

        return [$invitation, $raw];
    }

    public function findUsable(string $rawToken): ?Invitation
    {
        // No tenant is known before acceptance: look the token up in platform mode, then switch into its tenant.
        $invitation = $this->context->asPlatform(fn () => Invitation::query()->where('token_hash', hash('sha256', $rawToken))->first());

        return $invitation?->isUsable() ? $invitation : null;
    }

    public function accept(string $rawToken, string $name, string $password): User
    {
        $invitation = $this->findUsable($rawToken) ?? throw new DomainException(__('This invitation is invalid or has expired.'));

        return $this->context->runAs($invitation->tenant_id, fn () => DB::transaction(function () use ($invitation, $name, $password) {
            $fresh = Invitation::query()->lockForUpdate()->findOrFail($invitation->id); // single use, even under races
            if (! $fresh->isUsable()) {
                throw new DomainException(__('This invitation has already been used.'));
            }

            $user = new User;
            $user->forceFill([
                'name' => $name,
                'email' => $fresh->email,
                'password' => $password,
                'tenant_id' => $fresh->tenant_id,
                'department_id' => $fresh->department_id,
                'job_title_id' => $fresh->job_title_id,
                'status' => 'active',
                'email_verified_at' => now(), // the invitation link proves mailbox ownership
            ])->save();

            $role = Role::query()->where('key', $fresh->role_key)
                ->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $fresh->tenant_id))
                ->orderByDesc('tenant_id')->firstOrFail();
            RoleAssignment::create(['user_id' => $user->id, 'role_id' => $role->id, 'tenant_id' => $fresh->tenant_id, 'granted_by' => $fresh->invited_by]);

            $fresh->update(['accepted_at' => now()]);
            Audit::log('invitation.accepted', $user, ['invitation' => $fresh->id]);

            return $user;
        }));
    }

    public function revoke(Invitation $invitation): void
    {
        $invitation->update(['revoked_at' => now()]);
        Audit::log('invitation.revoked', $invitation);
    }

    private function assertAllowedDomain(Tenant $tenant, string $email): void
    {
        $domains = (array) $tenant->setting('allowed_email_domains', []);
        if ($domains !== [] && ! in_array(Str::after($email, '@'), $domains, true)) {
            throw new DomainException(__('E-mail domain is not allowed for this institution.'));
        }
    }

    private function assertGrantableRole(User $actor, string $key): Role
    {
        $role = Role::query()->where('key', $key)
            ->where(fn ($q) => $q->where(fn ($q) => $q->whereNull('tenant_id')->where('is_template', true))->orWhere('tenant_id', $actor->tenant_id))
            ->orderByDesc('tenant_id')->first() ?? throw new DomainException(__('Unknown role.'));

        if ($role->level === 'platform') {
            throw new DomainException(__('Platform roles cannot be granted by institutions.'));
        }
        if (in_array($role->key, ['institution_owner', 'institution_admin'], true) && ! $actor->is_platform_admin && ! $actor->hasPermission('roles.manage')) {
            throw new DomainException(__('You cannot invite administrators.'));
        }

        return $role;
    }
}
