<?php

namespace App\Services\Billing;

use App\Services\Billing\Gateways\HmacGateway;
use App\Services\Billing\Gateways\ManualGateway;
use App\Services\Billing\Gateways\StripeGateway;

final class GatewayRegistry
{
    public function for(string $provider): PaymentGateway
    {
        return match ($provider) {
            'stripe' => new StripeGateway,
            'hmac' => new HmacGateway,
            'manual' => new ManualGateway,
            default => abort(404),
        };
    }
}
