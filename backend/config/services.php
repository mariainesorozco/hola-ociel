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
        'ghost' => [
        'url' => env('GHOST_URL', 'https://blog.uan.edu.mx'),
        'api_key' => env('GHOST_API_KEY'),
        'webhook_secret' => env('GHOST_WEBHOOK_SECRET'),
        'sync_enabled' => env('GHOST_SYNC_ENABLED', true),
        'auto_sync_interval' => env('GHOST_AUTO_SYNC_INTERVAL', 360), // minutos
    ],
    'qdrant' => [
        'url' => env('QDRANT_URL', 'http://localhost:6333'),
        'collection' => env('QDRANT_COLLECTION', 'uan_piida_knowledge'),
        'vector_size' => env('QDRANT_VECTOR_SIZE', 768),
        'distance_metric' => env('QDRANT_DISTANCE', 'Cosine'),
        'timeout' => env('QDRANT_TIMEOUT', 30),
        'api_key' => env('QDRANT_API_KEY', null), // Para Qdrant Cloud
    ],
    'piida' => [
        'base_url' => env('PIIDA_BASE_URL', 'https://piida.uan.mx'),
        'api_timeout' => env('PIIDA_API_TIMEOUT', 30),
        'scraping_delay' => env('PIIDA_SCRAPING_DELAY', 2), // segundos entre requests
        'categories' => [
            'tramites_estudiantes' => 'Trámites para Estudiantes',
            'tramites_docentes' => 'Trámites para Docentes',
            'servicios_academicos' => 'Servicios Académicos',
            'normatividad' => 'Normatividad Universitaria',
            'directorio' => 'Directorio de Dependencias',
            'eventos' => 'Eventos y Convocatorias',
            'recursos_digitales' => 'Recursos Digitales',
        ],
        'user_types_mapping' => [
            'student' => ['tramites_estudiantes', 'servicios_academicos', 'recursos_digitales'],
            'employee' => ['tramites_docentes', 'servicios_academicos', 'normatividad', 'directorio'],
            'public' => ['servicios_academicos', 'directorio', 'eventos']
        ]
    ],
    'web_scraping' => [
        'user_agent' => env('SCRAPING_USER_AGENT', 'UAN-Ociel-Bot/2.0 (Contact: sistemas@uan.edu.mx; AI Assistant)'),
        'concurrent_requests' => env('SCRAPING_CONCURRENT', 3),
        'retry_attempts' => env('SCRAPING_RETRIES', 3),
        'cache_duration' => env('SCRAPING_CACHE_HOURS', 24), // horas
        'allowed_domains' => [
            'piida.uan.mx',
            'www.uan.edu.mx',
            'admision.uan.mx',
            'dgsa.uan.edu.mx',
            'sistemas.uan.edu.mx'
        ]
    ]
];
