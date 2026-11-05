<?php

/*
 | Media providers. Model IDs and voices change often: keep them in the environment.
 | Video and 3D use a configurable async HTTP driver, so any provider with a "submit job → poll → download"
 | API can be connected without code changes. Result URLs are only fetched from the allow-listed hosts (SSRF guard).
 */
return [
    'credits' => ['image' => 4, 'speech' => 3, 'video' => 25, 'model3d' => 15],

    'image' => [
        'driver' => env('MEDIA_IMAGE_DRIVER', 'openai'), // openai | fake
        'model' => env('OPENAI_IMAGE_MODEL'),
        'size' => env('OPENAI_IMAGE_SIZE', '1024x1024'),
    ],

    'speech' => [
        'driver' => env('MEDIA_SPEECH_DRIVER', 'openai'), // openai | elevenlabs | fake
        'openai' => ['model' => env('OPENAI_TTS_MODEL'), 'voice' => env('OPENAI_TTS_VOICE', 'alloy')],
        'elevenlabs' => ['key' => env('ELEVENLABS_API_KEY'), 'voice_id' => env('ELEVENLABS_VOICE_ID'), 'model' => env('ELEVENLABS_MODEL_ID')],
        'max_chars' => 4000,
    ],

    'video' => [
        'driver' => env('MEDIA_VIDEO_DRIVER', 'fake'), // async_http | fake
        'async_http' => [
            'submit_url' => env('VIDEO_SUBMIT_URL'),             // POST, JSON body: {"prompt": "...", ...options}
            'status_url' => env('VIDEO_STATUS_URL'),             // GET, "{id}" is replaced
            'api_key' => env('VIDEO_API_KEY'),
            'auth_header' => env('VIDEO_AUTH_HEADER', 'Authorization'),
            'auth_prefix' => env('VIDEO_AUTH_PREFIX', 'Bearer '),
            'id_path' => env('VIDEO_ID_PATH', 'id'),
            'state_path' => env('VIDEO_STATE_PATH', 'status'),
            'done_states' => ['succeeded', 'completed', 'SUCCEEDED'],
            'failed_states' => ['failed', 'error', 'FAILED', 'canceled'],
            'result_path' => env('VIDEO_RESULT_PATH', 'output.url'),
            'allowed_hosts' => array_filter(explode(',', (string) env('VIDEO_RESULT_HOSTS', ''))),
        ],
    ],

    'model3d' => [
        'driver' => env('MEDIA_3D_DRIVER', 'fake'),
        'async_http' => [
            'submit_url' => env('MODEL3D_SUBMIT_URL'),
            'status_url' => env('MODEL3D_STATUS_URL'),
            'api_key' => env('MODEL3D_API_KEY'),
            'auth_header' => 'Authorization',
            'auth_prefix' => 'Bearer ',
            'id_path' => env('MODEL3D_ID_PATH', 'result'),
            'state_path' => env('MODEL3D_STATE_PATH', 'status'),
            'done_states' => ['SUCCEEDED', 'succeeded', 'completed'],
            'failed_states' => ['FAILED', 'failed', 'EXPIRED'],
            'result_path' => env('MODEL3D_RESULT_PATH', 'model_urls.glb'),
            'allowed_hosts' => array_filter(explode(',', (string) env('MODEL3D_RESULT_HOSTS', ''))),
        ],
    ],

    'moderation' => [
        'driver' => env('MEDIA_MODERATION_DRIVER', 'openai'), // openai | local
        'model' => env('OPENAI_MODERATION_MODEL', 'omni-moderation-latest'),
    ],

    'max_polls' => 80, // with growing back-off ≈ 30 minutes
];
