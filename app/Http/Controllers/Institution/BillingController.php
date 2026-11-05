<?php

namespace App\Http\Controllers\Institution;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\BillingService;
use App\Services\Billing\GatewayRegistry;
use App\Services\CreditService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class BillingController extends Controller
{
    public function index(Request $request, CreditService $credits): Response
    {
        $this->authorize('tenant.settings.manage');
        $tenantId = $request->user()->tenant_id;
        $subscription = Subscription::query()->with('plan')->first();

        return Inertia::render('Institution/Billing/Index', [
            'subscription' => $subscription?->only(['status', 'seats', 'currency', 'price_per_seat_cents', 'period_start', 'period_end', 'next_seats', 'cancel_at'])
                + ['plan' => $subscription?->plan?->only(['key', 'name', 'limits'])],
            'seatsUsed' => User::query()->where('tenant_id', $tenantId)->where('status', '!=', 'disabled')->count(),
            'credits' => $credits->balance($tenantId),
            'invoices' => Invoice::query()->latest()->limit(24)->get(['public_id', 'number', 'currency', 'total_cents', 'status', 'due_at', 'paid_at']),
            'plans' => Plan::query()->where('active', true)->get(['id', 'key', 'name', 'limits', 'price_per_seat_cents', 'currency', 'interval']),
            'bankInstructions' => config('billing.bank_instructions'),
        ]);
    }

    public function pay(Request $request, Invoice $invoice, GatewayRegistry $gateways): HttpResponse
    {
        $this->authorize('tenant.settings.manage');
        abort_unless($invoice->status === 'open', 404);

        return Inertia::location($gateways->for($invoice->provider ?? config('billing.provider'))->checkoutUrl($invoice, url('/admin/billing')));
    }

    public function seats(Request $request, BillingService $billing): RedirectResponse
    {
        $this->authorize('tenant.settings.manage');
        $extra = (int) $request->validate(['extra' => ['required', 'integer', 'between:1,5000']])['extra'];

        try {
            $billing->addSeats(Subscription::query()->firstOrFail(), $extra);
        } catch (DomainException $e) {
            return back()->withErrors(['seats' => $e->getMessage()]);
        }

        return back()->with('status', __('Seats added. An invoice for the remaining period was issued.'));
    }

    public function change(Request $request, BillingService $billing): RedirectResponse
    {
        $this->authorize('tenant.settings.manage');
        $data = $request->validate(['plan_id' => ['required', 'exists:plans,id'], 'seats' => ['required', 'integer', 'between:1,100000']]);
        $billing->scheduleChange(Subscription::query()->firstOrFail(), Plan::query()->findOrFail($data['plan_id']), $data['seats']);

        return back()->with('status', __('The change will apply at the next renewal.'));
    }
}
