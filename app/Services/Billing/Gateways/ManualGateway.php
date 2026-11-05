<?php

namespace App\Services\Billing\Gateways;

use App\Models\Invoice;
use App\Services\Billing\PaymentGateway;
use Illuminate\Http\Request;

/** Bank transfer / cheque: the invoice page shows instructions; BAREE finance marks it paid. */
final class ManualGateway implements PaymentGateway
{
    public function checkoutUrl(Invoice $invoice, string $returnUrl): string
    {
        return $returnUrl.'?instructions=1';
    }

    public function parseWebhook(Request $request): ?array
    {
        abort(404);
    }
}
