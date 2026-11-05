<?php

return [
    'realtime_origin' => env('BAREE_REALTIME_ORIGIN', ''), // e.g. wss://ws.baree.app for Reverb in the CSP

    'privacy' => [
        // Default for new institutions: aggregated analytics only; individual results need employee consent.
        'default_mode' => env('BAREE_PRIVACY_MODE', 'consent'), // aggregate, consent, department_heads
        'min_group_size' => 5, // hide any analytics group smaller than this (k-anonymity)
    ],

    'credits' => [
        'trial_grant' => 5000,
    ],

    'uploads' => [
        'max_mb' => 50,
        'allowed_mimes' => ['pdf', 'docx', 'pptx', 'xlsx', 'csv', 'txt', 'png', 'jpg', 'jpeg', 'webp', 'mp3', 'm4a'],
        'scan_with' => env('BAREE_AV_SCANNER', 'clamav'), // clamav | none (local only). Files stay "pending" until scanned.
        'clamav_host' => env('CLAMAV_HOST', '127.0.0.1'),
        'clamav_port' => (int) env('CLAMAV_PORT', 3310),
    ],

    'version' => env('BAREE_VERSION', 'dev'),
    'health_token' => env('BAREE_HEALTH_TOKEN'),

    'retention' => [
        'ai_run_content_days' => (int) env('RETENTION_AI_RUN_DAYS', 90),
        'failed_media_days' => 30,
        'whatsapp_metadata_days' => 365,
    ],
];
