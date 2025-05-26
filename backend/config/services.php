<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://localhost:11434'),
        'primary_model' => env('OLLAMA_PRIMARY_MODEL', 'mistral:7b'),
        'secondary_model' => env('OLLAMA_SECONDARY_MODEL', 'llama3.2:3b'),
        'embedding_model' => env('OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text'),
        'timeout' => env('OLLAMA_TIMEOUT', 60),
    ],
    'qdrant' => [
        'url' => env('QDRANT_URL', 'http://localhost:6333'),
        'collection' => env('QDRANT_COLLECTION', 'uan_knowledge'),
        'vector_size' => env('QDRANT_VECTOR_SIZE', 768), // Para nomic-embed-text
        'timeout' => env('QDRANT_TIMEOUT', 30),
        'retry_attempts' => env('QDRANT_RETRY_ATTEMPTS', 3),
    ],
];
