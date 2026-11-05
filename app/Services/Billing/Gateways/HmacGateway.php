<?php

namespace App\Services\Billing\Gateways;

use App\Models\Invoice;
use App\Services\Billing\PaymentGateway;
use Illuminate\Http\Request;

/**
 * Generic hosted-payment gateway for regional providers: we redirect with a signed query string and accept a
 * JSON webhook signed with HMAC-SHA256 in X-Signature. Adapt the field names to the provider contract.
 */
final class HmacGateway implements PaymentGateway
{
    public function checkoutUrl(Invoice $invoice, string $returnUrl): string
    {
        $query = ['invoice' => $invoice->public_id, 'amount' => $invoice->total_cents, 'currency' => $invoice->currency, 'return' => $returnUrl];
        $query['signature'] = hash_hmac('sha256', http_build_query($query), (string) config('billing.hmac.secret'));

        return rtrim((string) config('billing.hmac.checkout_url'), '?').'?'.http_build_query($query);
    }

    public function parseWebhook(Request $request): ?array
    {
        $expected = hash_hmac('sha256', $request->getContent(), (string) config('billing.hmac.secret'));
        abort_unless(hash_equals($expected, (string) $request->header('X-Signature')), 400, 'Invalid signature');

        $data = $request->json()->all();
        if (($data['status'] ?? null) !== 'paid') {
            return null;
        }

        return ['event_id' => (string) $data['event_id'], 'type' => 'paid', 'invoice' => (string) $data['invoice'], 'reference' => $data['reference'] ?? null];
    }
}
