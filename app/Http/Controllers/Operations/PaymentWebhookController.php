<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\PaymentEvent;
use App\Services\Billing\GatewayRegistry;
use App\Services\Billing\InvoiceService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/** Signed, idempotent payment webhooks. CSRF-exempt route; the signature is the authentication. */
final class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, GatewayRegistry $gateways, InvoiceService $invoices, TenantContext $context): Response
    {
        $event = $gateways->for($provider)->parseWebhook($request); // aborts on a bad signature
        if ($event === null) {
            return response('', 204);
        }

        try {
            // Savepoint when already inside a transaction: a duplicate must not poison the surrounding PostgreSQL transaction.
            DB::transaction(fn () => PaymentEvent::create(['provider' => $provider, 'event_id' => $event['event_id'], 'type' => $event['type'], 'processed_at' => now()]));
        } catch (UniqueConstraintViolationException) {
            return response('', 200); // already processed
        }

        $invoice = $context->asPlatform(fn () => Invoice::query()->where('public_id', $event['invoice'])->first());
        if ($invoice) {
            $invoices->markPaid($invoice, $provider, $event['reference']);
        }

        return response('', 200);
    }
}
