<?php

/*
 | WhatsApp Business Platform (Cloud API). Business-initiated messages must use templates approved by Meta;
 | free text is only allowed inside the 24-hour window after the person writes to us.
 */
return [
    'enabled' => (bool) env('WHATSAPP_ENABLED', false),
    'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v22.0'),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
    'app_secret' => env('WHATSAPP_APP_SECRET'),       // verifies X-Hub-Signature-256 on webhooks
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),   // webhook subscription handshake
    'templates' => [
        'otp' => env('WHATSAPP_TEMPLATE_OTP', 'baree_otp'),
        'assignment' => env('WHATSAPP_TEMPLATE_ASSIGNMENT', 'baree_assignment'),
        'reminder' => env('WHATSAPP_TEMPLATE_REMINDER', 'baree_reminder'),
    ],
    'daily_inbound_limit' => 20,
    'stop_words' => ['stop', 'إيقاف', 'ايقاف', 'الغاء', 'إلغاء'],
];
