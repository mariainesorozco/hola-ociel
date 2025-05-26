<?php

// config/ollama.php - Configuración específica para modelos

return [
    /*
    |--------------------------------------------------------------------------
    | Configuración de Modelos Ollama para UAN
    |--------------------------------------------------------------------------
    |
    | Configuraciones optimizadas para reducir alucinaciones y mejorar
    | la precisión de las respuestas del asistente Ociel
    |
    */

    'models' => [
        'mistral' => [
            'name' => 'mistral:7b',
            'use_case' => 'primary',
            'parameters' => [
                'temperature' => 0.2,        // Muy baja para máxima precisión
                'top_p' => 0.8,             // Limitar diversidad de tokens
                'top_k' => 30,              // Reducir opciones de tokens
                'repeat_penalty' => 1.15,   // Evitar repeticiones
                'num_predict' => 800,       // Límite de tokens de respuesta
                'stop' => ["\n\n", "Usuario:", "USUARIO:"], // Tokens de parada
            ],
            'system_prompt_additions' => [
                'strict_mode' => true,
                'fact_checking' => true,
                'conservative_responses' => true
            ]
        ],

        'llama3.2' => [
            'name' => 'llama3.2:3b',
            'use_case' => 'secondary',
            'parameters' => [
                'temperature' => 0.15,       // Aún más conservador
                'top_p' => 0.75,
                'top_k' => 25,
                'repeat_penalty' => 1.2,
                'num_predict' => 500,
                'stop' => ["\n\n", "Usuario:", "USUARIO:", "Consulta:"]
            ]
        ],

        'deepseek-r1' => [
            'name' => 'deepseek-r1:14b',
            'use_case' => 'complex_queries',
            'parameters' => [
                'temperature' => 0.25,
                'top_p' => 0.85,
                'top_k' => 35,
                'repeat_penalty' => 1.1,
                'num_predict' => 1000,
            ],
            'activation_conditions' => [
                'query_length' => 100,       // Consultas largas
                'complexity_keywords' => ['proceso', 'procedimiento', 'explicar detalladamente'],
                'user_type' => ['employee']  // Para empleados administrativos
            ]
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración Anti-Alucinación
    |--------------------------------------------------------------------------
    */
    'anti_hallucination' => [
        'enabled' => true,
        'confidence_threshold' => 0.6,      // Umbral mínimo de confianza
        'fallback_responses' => true,       // Usar respuestas conservadoras
        'fact_validation' => [
            'validate_numbers' => true,     // Validar números y fechas
            'validate_names' => true,       // Validar nombres propios
            'validate_procedures' => true,  // Validar procedimientos
        ],
        'forbidden_patterns' => [
            // Patrones que indican posible alucinación
            '/exactamente a las \d+:\d+/',
            '/el día \d+ de \w+/',
            '/cuesta exactamente \$\d+/',
            '/son precisamente \d+ requisitos/',
            '/definitivamente es/',
            '/siempre ocurre/',
            '/nunca pasa que/',
        ],
        'safety_phrases' => [
            'Para información específica sobre',
            'Te recomiendo contactar directamente',
            'Los detalles exactos pueden variar',
            'Para confirmación oficial',
            'Verifica directamente con',
            'No tengo la información específica sobre'
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración de Contexto
    |--------------------------------------------------------------------------
    */
    'context' => [
        'max_context_length' => 3000,       // Caracteres máximos de contexto
        'context_sources' => 3,             // Máximo de fuentes de contexto
        'context_relevance_threshold' => 0.7, // Umbral de relevancia
        'prioritize_official_sources' => true,
        'context_validation' => [
            'check_freshness' => true,      // Verificar actualidad
            'verify_authority' => true,     // Verificar fuente oficial
            'cross_reference' => true,      // Validar con múltiples fuentes
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración por Tipo de Usuario
    |--------------------------------------------------------------------------
    */
    'user_specific' => [
        'student' => [
            'priority_topics' => ['admision', 'carreras', 'servicios', 'becas'],
            'response_style' => 'friendly_informative',
            'include_practical_tips' => true,
            'max_response_length' => 600,
        ],
        'employee' => [
            'priority_topics' => ['procedimientos', 'normativas', 'sistemas'],
            'response_style' => 'professional_detailed',
            'include_technical_details' => true,
            'max_response_length' => 800,
        ],
        'public' => [
            'priority_topics' => ['informacion_general', 'oferta_educativa'],
            'response_style' => 'welcoming_basic',
            'include_contact_info' => true,
            'max_response_length' => 400,
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración de Escalación
    |--------------------------------------------------------------------------
    */
    'escalation' => [
        'confidence_threshold' => 0.5,      // Escalar si confianza es menor
        'complex_query_indicators' => [
            'multiple_questions',
            'legal_terminology',
            'complaint_keywords',
            'urgent_language',
            'specific_procedures'
        ],
        'escalation_departments' => [
            'legal' => ['JURIDICO', 'SECRETARIA_GENERAL'],
            'academic' => ['DGSA'],
            'technical' => ['DGS'],
            'administrative' => ['SECRETARIA_GENERAL'],
            'financial' => ['ADMINISTRACION']
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Métricas y Monitoreo
    |--------------------------------------------------------------------------
    */
    'monitoring' => [
        'log_all_interactions' => true,
        'track_confidence_scores' => true,
        'monitor_response_times' => true,
        'detect_hallucination_patterns' => true,
        'quality_metrics' => [
            'response_relevance',
            'factual_accuracy',
            'user_satisfaction',
            'escalation_rate'
        ],
        'alerts' => [
            'low_confidence_threshold' => 0.4,
            'high_escalation_rate' => 0.3,
            'response_time_threshold' => 5000, // ms
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración de Recuperación de Fallos
    |--------------------------------------------------------------------------
    */
    'fallback' => [
        'enable_fallback_model' => true,
        'fallback_chain' => ['llama3.2:3b', 'mistral:7b'],
        'fallback_triggers' => [
            'model_unavailable',
            'timeout',
            'low_confidence',
            'error_response'
        ],
        'conservative_mode' => [
            'enabled' => true,
            'trigger_conditions' => [
                'no_context_available',
                'uncertain_query',
                'sensitive_topic'
            ],
            'default_response_template' => 'conservative_uan_response'
        ]
    ]
];
