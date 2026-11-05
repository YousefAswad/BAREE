<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\RoleAssignment;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\InvoiceIssuedNotification;
use App\Services\Platform\TenantProvisioningService;
use App\Support\Audit;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class InvoiceService
{
    public function __construct(private TenantContext $context) {}

    /** @param  array<int, array{description:string, quantity:int|float, unit_cents:int}>  $lines */
    public function create(Tenant $tenant, ?Subscription $subscription, array $lines, string $currency): Invoice
    {
        $subtotal = (int) round(collect($lines)->sum(fn ($l) => $l['quantity'] * $l['unit_cents']));
        $tax = (int) round($subtotal * (float) config('billing.tax_percent') / 100);

        $invoice = Cache::lock('invoice-number', 10)->block(5, fn () => DB::transaction(fn () => $this->context->runAs($tenant->id, fn () => Invoice::create([
            'subscription_id' => $subscription?->id, 'number' => $this->nextNumber(), 'currency' => $currency,
            'subtotal_cents' => $subtotal, 'tax_cents' => $tax, 'total_cents' => $subtotal + $tax, 'status' => 'open',
            'lines' => $lines, 'due_at' => now()->addDays((int) config('billing.due_days')), 'provider' => $subscription?->provider ?? config('billing.provider'),
        ]))));

        Notification::send($this->owners($tenant), new InvoiceIssuedNotification($invoice));
        Audit::log('invoice.issued', $invoice, ['total' => $invoice->total_cents, 'currency' => $currency]);

        return $invoice;
    }

    public function markPaid(Invoice $invoice, string $provider, ?string $reference): void
    {
        if ($invoice->status === 'paid') {
            return; // idempotent
        }

        $this->context->runAs($invoice->tenant_id, function () use ($invoice, $provider, $reference) {
            $invoice->update(['status' => 'paid', 'paid_at' => now(), 'provider' => $provider, 'provider_ref' => $reference]);
            Subscription::query()->whereKey($invoice->subscription_id)->where('status', 'past_due')->update(['status' => 'active']);
        });

        $tenant = Tenant::query()->find($invoice->tenant_id);
        $stillOverdue = $this->context->runAs($tenant->id, fn () => Invoice::query()->where('status', 'open')->where('due_at', '<', now()->subDays((int) config('billing.grace_days')))->exists());
        if ($tenant->status === 'suspended' && data_get($tenant->settings, 'suspension_reason') === 'non_payment' && ! $stillOverdue) {
            app(TenantProvisioningService::class)->setStatus($tenant, 'active', 'Payment received');
            $settings = $tenant->fresh()->settings;
            unset($settings['suspension_reason']);
            $tenant->update(['settings' => $settings]);
        }
        Audit::log('invoice.paid', $invoice, ['provider' => $provider]);
    }

    public function owners(Tenant $tenant)
    {
        $ids = RoleAssignment::query()->where('tenant_id', $tenant->id)->whereHas('role', fn ($q) => $q->where('key', 'institution_owner'))->pluck('user_id');

        return User::query()->whereIn('id', $ids)->get();
    }

    private function nextNumber(): string
    {
        $prefix = config('billing.invoice_prefix').'-'.now()->year.'-';
        $last = $this->context->asPlatform(fn () => Invoice::query()->where('number', 'like', $prefix.'%')->orderByDesc('number')->value('number'));
        $sequence = $last ? (int) substr($last, strlen($prefix)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }
}
