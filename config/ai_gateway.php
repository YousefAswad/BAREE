<?php

/*
 | Provider defaults. Keys can also be stored encrypted per institution ("bring your own key")
 | from the Super Admin portal; the database always wins over these environment values.
 | Model IDs change often: keep them here or in the ai_models table, never hard-coded in agents.
 */
return [
    'context' => [
        'max_document_chars' => 120_000,     // total budget for documents in one run
        'max_chars_per_document' => 40_000,
    ],

    'providers' => [
        'anthropic' => [
            'key' => env('ANTHROPIC_API_KEY'),
            'api_version' => env('ANTHROPIC_API_VERSION', '2023-06-01'),
            'models' => [
                'light' => env('ANTHROPIC_MODEL_LIGHT', 'claude-haiku-5-5'),
                'standard' => env('ANTHROPIC_MODEL_STANDARD', 'claude-sonnet-5-5'),
                'advanced' => env('ANTHROPIC_MODEL_ADVANCED', 'claude-opus-5-5'),
            ],
        ],
        'openai' => [
            'key' => env('OPENAI_API_KEY'),
            'models' => [
                'light' => env('OPENAI_MODEL_LIGHT'),
                'standard' => env('OPENAI_MODEL_STANDARD'),
                'advanced' => env('OPENAI_MODEL_ADVANCED'),
            ],
        ],
        'openrouter' => [
            'key' => env('OPENROUTER_API_KEY'),
            'models' => ['standard' => env('OPENROUTER_MODEL_STANDARD')],
        ],
        'ollama' => [
            'key' => null,
            'models' => ['standard' => env('OLLAMA_MODEL', 'llama3.1')],
        ],
        'fake' => [
            'key' => null,
            'models' => ['light' => 'fake', 'standard' => 'fake', 'advanced' => 'fake'],
        ],
    ],
];
