<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Billing\BillingService;
use App\Services\Billing\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class BillingController extends Controller
{
    public function index(): Response
    {
        $this->authorize('platform.plans.manage');
        $subscriptions = Subscription::query()->with('plan:id,key,interval')->get();
        $mrr = $subscriptions->whereIn('status', ['active', 'past_due'])->groupBy('currency')
            ->map(fn ($subs) => (int) round($subs->sum(fn ($s) => $s->seats * $s->price_per_seat_cents / ($s->plan?->interval === 'month' ? 1 : 12))));

        return Inertia::render('Platform/Billing/Index', [
            'mrr' => $mrr,
            'counts' => $subscriptions->countBy('status'),
            'openInvoices' => Invoice::query()->where('status', 'open')->orderBy('due_at')->limit(50)->get(['public_id', 'tenant_id', 'number', 'currency', 'total_cents', 'due_at', 'provider']),
            'tenants' => Tenant::query()->whereDoesntHave('subscription')->get(['id', 'public_id', 'name']),
            'plans' => Plan::query()->where('active', true)->get(['id', 'key', 'name', 'price_per_seat_cents', 'currency', 'interval']),
        ]);
    }

    public function subscribe(Request $request, BillingService $billing): RedirectResponse
    {
        $this->authorize('platform.plans.manage');
        $data = $request->validate(['tenant_id' => ['required', 'exists:tenants,id'], 'plan_id' => ['required', 'exists:plans,id'],
            'seats' => ['required', 'integer', 'between:1,100000'], 'provider' => ['required', 'in:manual,stripe,hmac']]);
        $billing->subscribe(Tenant::query()->findOrFail($data['tenant_id']), Plan::query()->findOrFail($data['plan_id']), $data['seats'], $data['provider']);

        return back();
    }

    /** Bank transfers and cheques are confirmed by BAREE finance. */
    public function markPaid(Request $request, string $publicId, InvoiceService $invoices): RedirectResponse
    {
        $this->authorize('platform.plans.manage');
        $reference = $request->validate(['reference' => ['required', 'string', 'max:120']])['reference'];
        $invoices->markPaid(Invoice::query()->where('public_id', $publicId)->firstOrFail(), 'manual', $reference);

        return back();
    }
}
