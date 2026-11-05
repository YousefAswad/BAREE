<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use Illuminate\Http\Request;

interface PaymentGateway
{
    public function checkoutUrl(Invoice $invoice, string $returnUrl): string;

    /**
     * Verifies the signature and returns a normalised paid event, or null for events we ignore.
     *
     * @return array{event_id:string, type:string, invoice:string, reference:?string}|null
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException on an invalid signature
     */
    public function parseWebhook(Request $request): ?array;
}
