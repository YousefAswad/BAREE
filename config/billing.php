<?php

return [
    'provider' => env('BILLING_PROVIDER', 'manual'), // stripe | manual | hmac
    'invoice_prefix' => env('BILLING_INVOICE_PREFIX', 'BAREE'),
    'tax_percent' => (float) env('BILLING_TAX_PERCENT', 0),
    'due_days' => 14,
    'grace_days' => 14, // after the due date; then the institution is suspended until payment
    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],
    'hmac' => [ // generic hosted-payment-page gateway (for regional providers)
        'checkout_url' => env('HMAC_GATEWAY_CHECKOUT_URL'),
        'secret' => env('HMAC_GATEWAY_SECRET'),
    ],
    'bank_instructions' => env('BILLING_BANK_INSTRUCTIONS', ''),
];
