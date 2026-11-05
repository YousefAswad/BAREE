<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Notifications\PaymentOverdueNotification;
use App\Services\Billing\BillingService;
use App\Services\Billing\InvoiceService;
use App\Services\Platform\TenantProvisioningService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/** Daily: renewals, monthly credits, overdue reminders, and suspension after the grace period. */
final class BillingCycleJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public function handle(TenantContext $context, BillingService $billing, InvoiceService $invoices, TenantProvisioningService $tenants): void
    {
        $subscriptions = $context->asPlatform(fn () => Subscription::query()->whereIn('status', ['trialing', 'active', 'past_due'])->get());

        foreach ($subscriptions as $subscription) {
            $context->runAs($subscription->tenant_id, function () use ($subscription, $billing) {
                if ($subscription->period_end->lte(now())) {
                    $billing->renew($subscription);
                }
                $billing->grantMonthlyCredits($subscription->fresh());
            });
        }

        $overdue = $context->asPlatform(fn () => Invoice::query()->where('status', 'open')->where('due_at', '<', now())->get());
        foreach ($overdue as $invoice) {
            $tenant = Tenant::query()->find($invoice->tenant_id);
            $context->runAs($invoice->tenant_id, fn () => Subscription::query()->whereKey($invoice->subscription_id)->where('status', 'active')->update(['status' => 'past_due']));

            if (Cache::add("dunning:{$invoice->id}:".now()->format('o-W'), true, now()->addWeek())) { // at most weekly
                Notification::send($invoices->owners($tenant), new PaymentOverdueNotification($invoice));
            }

            if ($invoice->due_at->lt(now()->subDays((int) config('billing.grace_days'))) && $tenant->status !== 'suspended') {
                $tenants->setStatus($tenant, 'suspended', 'Unpaid invoice '.$invoice->number);
                $context->asPlatform(fn () => $tenant->update(['settings' => array_merge($tenant->fresh()->settings ?? [], ['suspension_reason' => 'non_payment'])]));
            }
        }
    }
}
