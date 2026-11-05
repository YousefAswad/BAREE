<?php

namespace App\Services\Platform;

use App\Models\RoleAssignment;
use App\Models\SupportSession;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SupportAccessNotification;
use App\Support\Audit;
use DomainException;
use Illuminate\Support\Facades\Notification;

final class SupportSessionService
{
    public const MAX_MINUTES = 30;

    public function start(User $staff, Tenant $tenant, string $reason, int $minutes = self::MAX_MINUTES): SupportSession
    {
        if (! $staff->hasPermission('platform.support.impersonate')) {
            throw new DomainException('Not allowed.');
        }
        if (mb_strlen(trim($reason)) < 10) {
            throw new DomainException('A clear reason is required.');
        }

        $session = SupportSession::create([
            'tenant_id' => $tenant->id, 'staff_id' => $staff->id, 'reason' => trim($reason),
            'started_at' => now(), 'expires_at' => now()->addMinutes(min($minutes, self::MAX_MINUTES)),
        ]);

        $ownerIds = RoleAssignment::query()->where('tenant_id', $tenant->id)
            ->whereHas('role', fn ($q) => $q->where('key', 'institution_owner'))->pluck('user_id');
        Notification::send(User::query()->whereIn('id', $ownerIds)->get(), new SupportAccessNotification($session));

        Audit::log('support.session_started', $tenant, ['session' => $session->id, 'reason' => $session->reason]);

        return $session;
    }

    public function end(SupportSession $session): void
    {
        $session->update(['ended_at' => now()]);
        Audit::log('support.session_ended', null, ['session' => $session->id, 'tenant' => $session->tenant_id]);
    }
}
