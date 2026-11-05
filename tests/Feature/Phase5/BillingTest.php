<?php

require_once __DIR__.'/Phase5Helpers.php';

use App\Jobs\BillingCycleJob;
use App\Models\CreditLedgerEntry;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\BillingService;
use App\Services\Institution\InvitationService;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->ahmad = opsSetup();
    Notification::fake();
    config(['billing.tax_percent' => 16, 'billing.stripe.webhook_secret' => 'whsec_test', 'billing.hmac.secret' => 'hmac_test']);
    $this->plan = bareeTenantContext()->asPlatform(fn () => tap(Plan::where('key', 'professional')->first())->update(['price_per_seat_cents' => 1000, 'currency' => 'USD', 'interval' => 'year']));
    $this->tenant = $this->ahmad->tenant;
    $this->tenant->update(['status' => 'active']);
});

it('subscribes, invoices with tax and sequential numbers, and grants monthly credits once', function () {
    $sub = app(BillingService::class)->subscribe($this->tenant, $this->plan, 50);
    $invoice = Invoice::first();

    expect($invoice->number)->toBe('BAREE-'.now()->year.'-000001')
        ->and($invoice->subtotal_cents)->toBe(50000)->and($invoice->tax_cents)->toBe(8000)->and($invoice->total_cents)->toBe(58000);

    app(BillingService::class)->addSeats($sub, 10);
    expect(Invoice::orderByDesc('id')->first()->number)->toBe('BAREE-'.now()->year.'-000002')
        ->and(app(BillingService::class)->grantMonthlyCredits($sub))->toBeFalse() // already granted on subscribe
        ->and(CreditLedgerEntry::where('reason', 'monthly_grant')->where('reference_id', $sub->id)->count())->toBe(1); // the demo seed has its own unrelated grant
});

it('blocks invitations when every seat is used', function () {
    app(BillingService::class)->subscribe($this->tenant, $this->plan, 2); // 2 demo users already

    $admin = \App\Models\User::where('email', 'admin@future.test')->first();
    expect(fn () => app(InvitationService::class)->invite($admin, ['email' => 'x@future.test', 'role_key' => 'staff']))->toThrow(DomainException::class);
});

it('marks invoices paid from a signed Stripe webhook exactly once', function () {
    app(BillingService::class)->subscribe($this->tenant, $this->plan, 50);
    $invoice = Invoice::first();
    $payload = json_encode(['id' => 'evt_1', 'type' => 'checkout.session.completed', 'data' => ['object' => ['payment_status' => 'paid', 'payment_intent' => 'pi_1', 'metadata' => ['invoice' => $invoice->public_id]]]]);
    $sign = fn ($body, $secret = 'whsec_test') => 't='.time().',v1='.hash_hmac('sha256', time().'.'.$body, $secret);
    bareeTenantContext()->clear();

    $this->call('POST', '/webhooks/payments/stripe', [], [], [], ['HTTP_STRIPE_SIGNATURE' => $sign($payload, 'wrong'), 'CONTENT_TYPE' => 'application/json'], $payload)->assertStatus(400);
    $this->call('POST', '/webhooks/payments/stripe', [], [], [], ['HTTP_STRIPE_SIGNATURE' => $sign($payload), 'CONTENT_TYPE' => 'application/json'], $payload)->assertOk();
    $this->call('POST', '/webhooks/payments/stripe', [], [], [], ['HTTP_STRIPE_SIGNATURE' => $sign($payload), 'CONTENT_TYPE' => 'application/json'], $payload)->assertOk();

    $paid = bareeTenantContext()->asPlatform(fn () => Invoice::find($invoice->id));
    expect($paid->status)->toBe('paid')->and($paid->provider_ref)->toBe('pi_1');
});

it('suspends after the grace period and reactivates on payment', function () {
    app(BillingService::class)->subscribe($this->tenant, $this->plan, 50);
    $invoice = Invoice::first();
    $invoice->update(['due_at' => now()->subDays(20)]);

    (new BillingCycleJob)->handle(bareeTenantContext(), app(BillingService::class), app(\App\Services\Billing\InvoiceService::class), app(\App\Services\Platform\TenantProvisioningService::class));
    expect($this->tenant->fresh()->status)->toBe('suspended')->and(Subscription::first()->status)->toBe('past_due');

    $payload = json_encode(['event_id' => 'pay_1', 'invoice' => $invoice->public_id, 'status' => 'paid', 'reference' => 'local-1']);
    bareeTenantContext()->clear();
    $this->call('POST', '/webhooks/payments/hmac', [], [], [], ['HTTP_X_SIGNATURE' => hash_hmac('sha256', $payload, 'hmac_test'), 'CONTENT_TYPE' => 'application/json'], $payload)->assertOk();

    expect($this->tenant->fresh()->status)->toBe('active');
});
