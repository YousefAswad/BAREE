<?php

namespace App\Services\Billing\Gateways;

use App\Models\Invoice;
use App\Services\Billing\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/** Stripe Checkout (hosted page) + signed webhooks. Use only where Stripe serves the institution's country. */
final class StripeGateway implements PaymentGateway
{
    public const TOLERANCE = 300;

    public function checkoutUrl(Invoice $invoice, string $returnUrl): string
    {
        $response = Http::asForm()->withBasicAuth((string) config('billing.stripe.secret'), '')->timeout(30)
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'payment',
                'client_reference_id' => $invoice->public_id,
                'metadata[invoice]' => $invoice->public_id,
                'line_items[0][quantity]' => 1,
                'line_items[0][price_data][currency]' => strtolower($invoice->currency),
                'line_items[0][price_data][unit_amount]' => $invoice->total_cents,
                'line_items[0][price_data][product_data][name]' => 'BAREE '.$invoice->number,
                'success_url' => $returnUrl.'?paid=1',
                'cancel_url' => $returnUrl,
            ])->throw();

        return (string) $response->json('url');
    }

    public function parseWebhook(Request $request): ?array
    {
        $payload = $request->getContent();
        $parts = collect(explode(',', (string) $request->header('Stripe-Signature')))
            ->mapWithKeys(fn ($p) => [trim(explode('=', $p, 2)[0]) => explode('=', $p, 2)[1] ?? null]);
        $timestamp = (int) ($parts['t'] ?? 0);
        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, (string) config('billing.stripe.webhook_secret'));

        abort_unless($timestamp && abs(time() - $timestamp) <= self::TOLERANCE && isset($parts['v1']) && hash_equals($expected, (string) $parts['v1']), 400, 'Invalid signature');

        $event = json_decode($payload, true);
        if (($event['type'] ?? null) !== 'checkout.session.completed' || data_get($event, 'data.object.payment_status') !== 'paid') {
            return null;
        }

        return ['event_id' => (string) $event['id'], 'type' => $event['type'], 'invoice' => (string) data_get($event, 'data.object.metadata.invoice'),
            'reference' => data_get($event, 'data.object.payment_intent')];
    }
}
