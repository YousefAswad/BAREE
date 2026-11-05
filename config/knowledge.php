<?php

return [
    'embeddings' => [
        'driver' => env('EMBEDDINGS_DRIVER', 'openai'), // openai | fake
        'model' => env('EMBEDDINGS_MODEL'),            // set to an embedding model available on your account
    ],
    'dimensions' => (int) env('EMBEDDINGS_DIMENSIONS', 1536),
    'chunk_chars' => 1200,
    'chunk_overlap' => 150,
    'top_k' => 6,
    'min_score' => 0.22, // below this, the hub says it could not find an answer instead of guessing
];
