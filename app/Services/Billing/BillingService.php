<?php

namespace App\Services\Billing;

use App\Models\CreditLedgerEntry;
use App\Models\Invitation;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Audit;
use App\Support\Tenancy\TenantContext;
use DomainException;

/**
 * Seat-based subscriptions. Plan changes apply at renewal; seat increases apply now with a prorated line.
 * Monthly credits are granted once per subscription per calendar month.
 */
final class BillingService
{
    public function __construct(private InvoiceService $invoices, private TenantContext $context) {}

    public function subscribe(Tenant $tenant, Plan $plan, int $seats, string $provider = 'manual'): Subscription
    {
        $subscription = $this->context->runAs($tenant->id, fn () => Subscription::query()->updateOrCreate(['tenant_id' => $tenant->id], [
            'plan_id' => $plan->id, 'status' => $tenant->status === 'trial' ? 'trialing' : 'active', 'seats' => $seats,
            'currency' => $plan->currency, 'price_per_seat_cents' => $plan->price_per_seat_cents,
            'period_start' => now(), 'period_end' => $plan->interval === 'month' ? now()->addMonth() : now()->addYear(), 'provider' => $provider,
        ]));
        $this->context->asPlatform(fn () => $tenant->update(['plan_id' => $plan->id]));

        if ($plan->price_per_seat_cents > 0 && $subscription->status === 'active') {
            $this->invoicePeriod($tenant, $subscription);
        }
        $this->grantMonthlyCredits($subscription);
        Audit::log('subscription.started', $tenant, ['plan' => $plan->key, 'seats' => $seats]);

        return $subscription;
    }

    public function scheduleChange(Subscription $subscription, Plan $plan, int $seats): void
    {
        $subscription->update(['next_plan_id' => $plan->id, 'next_seats' => $seats]);
        Audit::log('subscription.change_scheduled', $subscription, ['plan' => $plan->key, 'seats' => $seats]);
    }

    public function addSeats(Subscription $subscription, int $extra): void
    {
        if ($extra < 1) {
            throw new DomainException('Seats must increase.');
        }
        $remaining = max(0, now()->diffInDays($subscription->period_end, false));
        $total = max(1, $subscription->period_start->diffInDays($subscription->period_end));
        $subscription->update(['seats' => $subscription->seats + $extra]);

        if ($subscription->price_per_seat_cents > 0 && $remaining > 0) {
            $this->invoices->create(Tenant::query()->findOrFail($subscription->tenant_id), $subscription, [[
                'description' => "{$extra} مقعد إضافي (تناسبي {$remaining} يوم)", 'quantity' => $extra,
                'unit_cents' => (int) round($subscription->price_per_seat_cents * $remaining / $total),
            ]], $subscription->currency);
        }
    }

    public function renew(Subscription $subscription): void
    {
        if ($subscription->cancel_at && $subscription->cancel_at->lte(now())) {
            $subscription->update(['status' => 'canceled']);

            return;
        }
        $plan = Plan::query()->findOrFail($subscription->next_plan_id ?? $subscription->plan_id);
        $subscription->update([
            'plan_id' => $plan->id, 'seats' => $subscription->next_seats ?? $subscription->seats, 'price_per_seat_cents' => $plan->price_per_seat_cents,
            'currency' => $plan->currency, 'next_plan_id' => null, 'next_seats' => null, 'status' => $subscription->status === 'trialing' ? 'active' : $subscription->status,
            'period_start' => $subscription->period_end, 'period_end' => $plan->interval === 'month' ? $subscription->period_end->copy()->addMonth() : $subscription->period_end->copy()->addYear(),
        ]);

        if ($plan->price_per_seat_cents > 0) {
            $this->invoicePeriod(Tenant::query()->findOrFail($subscription->tenant_id), $subscription->fresh());
        }
    }

    public function grantMonthlyCredits(Subscription $subscription): bool
    {
        $amount = Plan::query()->find($subscription->plan_id)?->limits['monthly_credits'] ?? null;
        if (! $amount || $subscription->status === 'canceled') {
            return false;
        }
        $key = 'period:'.now()->format('Y-m');

        return $this->context->runAs($subscription->tenant_id, function () use ($subscription, $amount, $key) {
            if (CreditLedgerEntry::query()->where('reason', 'monthly_grant')->where('reference_type', $key)->where('reference_id', $subscription->id)->exists()) {
                return false;
            }
            CreditLedgerEntry::create(['delta' => (int) $amount, 'reason' => 'monthly_grant', 'reference_type' => $key, 'reference_id' => $subscription->id, 'created_at' => now()]);

            return true;
        });
    }

    /** Seats in use = active users + invitations still pending. */
    public function assertSeatAvailable(int $tenantId): void
    {
        $subscription = Subscription::query()->where('tenant_id', $tenantId)->first();
        if (! $subscription) {
            return; // no subscription yet (trial/demo): plan limits are not enforced
        }
        $used = User::query()->where('tenant_id', $tenantId)->where('status', '!=', 'disabled')->count()
            + Invitation::query()->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->count();

        if ($used >= $subscription->seats) {
            throw new DomainException(__('All seats in your plan are in use. Add seats or disable inactive accounts.'));
        }
    }

    private function invoicePeriod(Tenant $tenant, Subscription $subscription): void
    {
        $plan = Plan::query()->findOrFail($subscription->plan_id);
        $this->invoices->create($tenant, $subscription, [[
            'description' => ($plan->name['ar'] ?? $plan->key).' — '.$subscription->period_start->toDateString().' → '.$subscription->period_end->toDateString(),
            'quantity' => $subscription->seats, 'unit_cents' => $subscription->price_per_seat_cents,
        ]], $subscription->currency);
    }
}
