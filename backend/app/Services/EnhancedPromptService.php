<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EnhancedPromptService
{
    private $ollamaService;
    private $knowledgeService;

    public function __construct(OllamaService $ollamaService, KnowledgeBaseService $knowledgeService)
    {
        $this->ollamaService = $ollamaService;
        $this->knowledgeService = $knowledgeService;
    }

    /**
     * Generar respuesta con prompts profesionales mejorados
     */
    public function generateProfessionalResponse(
        string $userMessage,
        string $userType = 'public',
        ?string $department = null,
        array $context = []
    ): array {

        // 1. Clasificar tipo de consulta
        $queryType = $this->classifyQuery($userMessage);

        // 2. Obtener prompt especializado
        $systemPrompt = $this->buildSpecializedPrompt($queryType, $userType, $department, $context);

        // 3. Preparar mensaje completo
        $fullPrompt = $this->buildFullPrompt($systemPrompt, $userMessage, $context);

        // 4. Generar respuesta con configuración optimizada
        $response = $this->ollamaService->generateResponse($fullPrompt, [
            'model' => $this->selectOptimalModel($queryType),
            'temperature' => $this->getOptimalTemperature($queryType),
            'max_tokens' => $this->getOptimalTokens($queryType)
        ]);

        // 5. Validar y mejorar respuesta
        if ($response['success']) {
            $enhancedResponse = $this->enhanceResponse($response['response'], $queryType, $context);
            $response['response'] = $enhancedResponse;
            $response['confidence'] = $this->calculateAdvancedConfidence($response, $context, $userMessage);
        }

        return $response;
    }

    /**
     * Clasificar tipo de consulta automáticamente
     */
    private function classifyQuery(string $message): string
    {
        $messageLower = strtolower($message);

        // Patrones de clasificación
        $patterns = [
            'tramite_especifico' => [
                'patterns' => ['inscripción', 'inscripcion', 'titulación', 'titulacion', 'revalidación', 'equivalencia', 'certificado', 'constancia'],
                'confidence' => 0.9
            ],
            'informacion_carrera' => [
                'patterns' => ['carrera', 'licenciatura', 'programa', 'plan de estudios', 'requisitos de admisión'],
                'confidence' => 0.8
            ],
            'servicio_universitario' => [
                'patterns' => ['biblioteca', 'laboratorio', 'cafetería', 'transporte', 'enfermería', 'deporte'],
                'confidence' => 0.8
            ],
            'soporte_tecnico' => [
                'patterns' => ['sistema', 'plataforma', 'correo', 'contraseña', 'usuario', 'acceso', 'login'],
                'confidence' => 0.9
            ],
            'informacion_general' => [
                'patterns' => ['horario', 'ubicación', 'dirección', 'teléfono', 'contacto'],
                'confidence' => 0.7
            ],
            'queja_problema' => [
                'patterns' => ['problema', 'queja', 'reclamo', 'error', 'falla', 'no funciona', 'molesto'],
                'confidence' => 0.9
            ],
            'consulta_academica' => [
                'patterns' => ['profesor', 'materia', 'calificación', 'examen', 'tarea', 'clase'],
                'confidence' => 0.8
            ]
        ];

        $scores = [];
        foreach ($patterns as $type => $config) {
            $score = 0;
            foreach ($config['patterns'] as $pattern) {
                if (str_contains($messageLower, $pattern)) {
                    $score += $config['confidence'];
                }
            }
            if ($score > 0) {
                $scores[$type] = $score;
            }
        }

        return !empty($scores) ? array_key_first(arsort($scores) ? $scores : []) : 'consulta_general';
    }

    /**
     * Construir prompt especializado según tipo de consulta
     */
    private function buildSpecializedPrompt(string $queryType, string $userType, ?string $department, array $context): string
    {
        $basePrompt = $this->getBaseInstitutionalPrompt();
        $specializedPrompt = $this->getSpecializedInstructions($queryType);
        $userContextPrompt = $this->getUserContextPrompt($userType, $department);
        $knowledgePrompt = $this->getKnowledgeContextPrompt($context);

        return implode("\n\n", array_filter([
            $basePrompt,
            $specializedPrompt,
            $userContextPrompt,
            $knowledgePrompt
        ]));
    }

    /**
     * Prompt institucional base de alta calidad
     */
    private function getBaseInstitutionalPrompt(): string
    {
        return "Eres Ociel, el Asistente Virtual Oficial de la Universidad Autónoma de Nayarit (UAN).

🎯 IDENTIDAD PROFESIONAL:
- Representas la máxima autoridad informativa de la UAN
- Eres la fuente oficial más confiable de información universitaria
- Mantienes los más altos estándares de profesionalismo y precisión
- Tu objetivo es resolver completamente cada consulta con excelencia

⚖️ PRINCIPIOS FUNDAMENTALES:
1. PRECISIÓN ABSOLUTA: Solo proporciona información 100% verificada
2. TRANSPARENCIA TOTAL: Si no tienes información exacta, lo comunicas claramente
3. SERVICIO INTEGRAL: Anticipas necesidades y ofreces soluciones completas
4. ESCALACIÓN INTELIGENTE: Derives a especialistas cuando sea más efectivo
5. MEJORA CONTINUA: Cada interacción debe superar las expectativas

🏛️ CONTEXTO INSTITUCIONAL:
La Universidad Autónoma de Nayarit es una institución pública de educación superior de excelencia, comprometida con la formación integral, la investigación científica y el desarrollo regional de Nayarit, México.

Fundada: 25 de abril de 1969
Ubicación: Ciudad de la Cultura \"Amado Nervo\", Tepic, Nayarit
Contacto Principal: 311-211-8800
Portal Oficial: https://www.uan.edu.mx

📋 ESTRUCTURA DE RESPUESTA PROFESIONAL:
- Saludo apropiado y confirmación de comprensión
- Información principal organizada y completa
- Datos de contacto específicos y relevantes
- Pasos de acción claros y secuenciales
- Seguimiento proactivo y recursos adicionales";
    }

    /**
     * Instrucciones especializadas por tipo de consulta
     */
    private function getSpecializedInstructions(string $queryType): string
    {
        $instructions = [
            'tramite_especifico' => "🎓 ESPECIALIZACIÓN EN TRÁMITES ACADÉMICOS:

Como experto en procedimientos universitarios, proporciona:
- Requisitos COMPLETOS y actualizados
- Documentación exacta necesaria
- Cronograma detallado del proceso
- Costos oficiales (si aplican)
- Ubicaciones específicas y horarios de atención
- Contactos directos de responsables
- Alternativas digitales disponibles
- Tiempos de respuesta estimados
- Posibles complicaciones y soluciones

FORMATO OBLIGATORIO para trámites:
✅ Requisitos | 📄 Documentos | ⏰ Tiempos | 💰 Costos | 📍 Ubicación | 📞 Contacto",

            'informacion_carrera' => "🎓 ESPECIALIZACIÓN EN OFERTA ACADÉMICA:

Como consejero académico experto, incluye:
- Perfil de ingreso detallado
- Plan de estudios actualizado
- Duración y modalidades disponibles
- Campo laboral específico
- Requisitos de admisión
- Fechas de convocatoria
- Proceso de selección
- Instalaciones y laboratorios especializados
- Oportunidades de intercambio
- Contacto de coordinación académica

ESTRUCTURA para carreras:
🎯 Perfil | 📚 Curriculum | ⏱️ Duración | 💼 Campo Laboral | 📝 Admisión | 📞 Coordinación",

            'servicio_universitario' => "🏛️ ESPECIALIZACIÓN EN SERVICIOS:

Como guía de servicios universitarios, proporciona:
- Descripción completa del servicio
- Población objetivo beneficiada
- Procedimiento de acceso
- Horarios detallados de operación
- Ubicaciones exactas
- Requisitos y restricciones
- Costos asociados (si existen)
- Personal responsable
- Servicios complementarios
- Canales de comunicación directa",

            'soporte_tecnico' => "💻 ESPECIALIZACIÓN EN SOPORTE TÉCNICO:

Como especialista en sistemas universitarios:
- Diagnóstico preciso del problema
- Soluciones paso a paso
- Verificaciones requeridas
- Alternativas de contacto técnico
- Escalación a DGS cuando corresponda
- Recursos de autoayuda disponibles
- Horarios de soporte especializado
- Políticas de uso y seguridad",

            'queja_problema' => "🛡️ ESPECIALIZACIÓN EN ATENCIÓN DE PROBLEMAS:

Modo de atención prioritaria activado:
- Escucha empática y comprensión total
- Documentación detallada del problema
- Escalación inmediata a autoridades competentes
- Canales oficiales de queja
- Seguimiento garantizado
- Derechos del usuario
- Procedimientos de apelación
- Contactos de supervisión",

            'consulta_academica' => "🎓 ESPECIALIZACIÓN ACADÉMICA:

Como asesor académico especializado:
- Información específica del programa
- Procedimientos académicos aplicables
- Recursos de apoyo estudiantil
- Contacto directo con coordinación
- Reglamentos académicos relevantes
- Opciones de tutoría y apoyo
- Servicios complementarios"
        ];

        return $instructions[$queryType] ?? $instructions['informacion_general'] ??
            "📋 CONSULTA GENERAL: Proporciona información completa, organizada y con contactos relevantes.";
    }

    /**
     * Prompt de contexto de usuario
     */
    private function getUserContextPrompt(string $userType, ?string $department): string
    {
        $userProfiles = [
            'student' => "👨‍🎓 PERFIL DE USUARIO: ESTUDIANTE
- Prioriza información académica y servicios estudiantiles
- Enfócate en trámites, fechas límite y requisitos
- Usa lenguaje claro pero técnicamente preciso
- Proporciona recursos de apoyo estudiantil",

            'employee' => "👩‍💼 PERFIL DE USUARIO: EMPLEADO UNIVERSITARIO
- Enfócate en procedimientos internos y normativas
- Proporciona información administrativa detallada
- Incluye canales internos de comunicación
- Considera nivel técnico apropiado",

            'public' => "🌟 PERFIL DE USUARIO: PÚBLICO GENERAL
- Usa lenguaje accesible y explicativo
- Proporciona contexto institucional adicional
- Enfócate en información de interés general
- Incluye invitación a conocer más sobre la UAN"
        ];

        $departmentContext = $department ? "\n🏛️ DEPARTAMENTO DE INTERÉS: {$department}" : "";

        return ($userProfiles[$userType] ?? $userProfiles['public']) . $departmentContext;
    }

    /**
     * Prompt de contexto de knowledge base
     */
    private function getKnowledgeContextPrompt(array $context): string
    {
        if (empty($context)) {
            return "⚠️ CONTEXTO: No se encontró información específica en la base de conocimientos. Proporciona información general confiable y deriva a contactos apropiados.";
        }

        $contextText = "📚 INFORMACIÓN OFICIAL DISPONIBLE:\n";
        foreach (array_slice($context, 0, 3) as $i => $item) {
            $contextText .= "Fuente " . ($i + 1) . ": " . substr($item, 0, 300) . "...\n\n";
        }

        return $contextText . "✅ INSTRUCCIÓN: Utiliza ÚNICAMENTE esta información oficial para construir tu respuesta. No agregues datos no verificados.";
    }

    /**
     * Construir prompt completo
     */
    private function buildFullPrompt(string $systemPrompt, string $userMessage, array $context): string
    {
        return "{$systemPrompt}

📩 CONSULTA DEL USUARIO:
\"{$userMessage}\"

🎯 TU RESPUESTA DEBE SER:
- Profesional y empática
- Completa pero concisa
- Estructurada y fácil de seguir
- Rica en información práctica
- Orientada a la acción

¡Genera la mejor respuesta posible como Ociel, el asistente más confiable de la UAN!";
    }

    /**
     * Seleccionar modelo óptimo según tipo de consulta
     */
    private function selectOptimalModel(string $queryType): string
    {
        $modelMapping = [
            'tramite_especifico' => config('services.ollama.primary_model'), // Máxima precisión
            'informacion_carrera' => config('services.ollama.primary_model'), // Información detallada
            'queja_problema' => config('services.ollama.primary_model'), // Máxima calidad
            'consulta_academica' => config('services.ollama.primary_model'),
            'soporte_tecnico' => config('services.ollama.secondary_model'), // Respuestas rápidas
            'servicio_universitario' => config('services.ollama.secondary_model'),
            'informacion_general' => config('services.ollama.secondary_model')
        ];

        return $modelMapping[$queryType] ?? config('services.ollama.primary_model');
    }

    /**
     * Obtener temperatura óptima
     */
    private function getOptimalTemperature(string $queryType): float
    {
        $temperatureMapping = [
            'tramite_especifico' => 0.2, // Máxima precisión
            'soporte_tecnico' => 0.3,
            'informacion_carrera' => 0.4,
            'queja_problema' => 0.3, // Respuesta controlada
            'consulta_academica' => 0.4,
            'servicio_universitario' => 0.5,
            'informacion_general' => 0.6 // Más creatividad
        ];

        return $temperatureMapping[$queryType] ?? 0.4;
    }

    /**
     * Obtener cantidad óptima de tokens
     */
    private function getOptimalTokens(string $queryType): int
    {
        $tokenMapping = [
            'tramite_especifico' => 1200, // Respuestas detalladas
            'informacion_carrera' => 1000,
            'queja_problema' => 800, // Respuestas empáticas pero controladas
            'consulta_academica' => 900,
            'soporte_tecnico' => 600, // Respuestas concisas
            'servicio_universitario' => 800,
            'informacion_general' => 700
        ];

        return $tokenMapping[$queryType] ?? 800;
    }

    /**
     * Mejorar respuesta post-generación
     */
    private function enhanceResponse(string $response, string $queryType, array $context): string
    {
        // Agregar información de contacto si no está presente
        if (!$this->containsContactInfo($response)) {
            $contactInfo = $this->getRelevantContactInfo($queryType);
            $response .= "\n\n" . $contactInfo;
        }

        // Agregar estructura si es necesaria
        if ($queryType === 'tramite_especifico' && !$this->hasStructuredFormat($response)) {
            $response = $this->addStructuredFormat($response);
        }

        // Agregar llamada a la acción
        $response .= "\n\n" . $this->getCallToAction($queryType);

        return $response;
    }

    /**
     * Calcular confianza avanzada
     */
    private function calculateAdvancedConfidence(array $response, array $context, string $originalQuery): float
    {
        $confidence = 0.0;

        // Base por contexto disponible
        if (!empty($context)) {
            $confidence += 0.4;
        }

        // Por éxito de generación
        if ($response['success']) {
            $confidence += 0.2;
        }

        // Por longitud apropiada de respuesta
        $responseLength = strlen($response['response'] ?? '');
        if ($responseLength > 100 && $responseLength < 2000) {
            $confidence += 0.2;
        }

        // Por presencia de información estructurada
        if ($this->hasStructuredInfo($response['response'] ?? '')) {
            $confidence += 0.1;
        }

        // Por presencia de contacto
        if ($this->containsContactInfo($response['response'] ?? '')) {
            $confidence += 0.1;
        }

        return min(1.0, $confidence);
    }

    /**
     * Verificar si contiene información de contacto
     */
    private function containsContactInfo(string $text): bool
    {
        return preg_match('/\b311-211-8800\b|\b\w+@\w+\.edu\.mx\b|ext\.\s*\d+/i', $text);
    }

    /**
     * Verificar si tiene formato estructurado
     */
    private function hasStructuredFormat(string $text): bool
    {
        return preg_match('/✅|📄|⏰|💰|📍|📞|🎯|📚/', $text);
    }

    /**
     * Agregar formato estructurado para trámites
     */
    private function addStructuredFormat(string $response): string
    {
        // Si ya tiene estructura, no modificar
        if ($this->hasStructuredFormat($response)) {
            return $response;
        }

        // Agregar encabezado estructurado
        return "📋 **INFORMACIÓN DEL TRÁMITE**\n\n" . $response;
    }

    /**
     * Verificar si tiene información estructurada
     */
    private function hasStructuredInfo(string $text): bool
    {
        // Verificar presencia de elementos informativos clave
        $patterns = [
            '/requisitos?/i',
            '/documentos?/i',
            '/pasos?/i',
            '/procedimiento/i',
            '/horarios?/i',
            '/ubicaci[óo]n/i',
            '/contacto/i'
        ];

        $matches = 0;
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                $matches++;
            }
        }

        return $matches >= 2;
    }

    /**
     * Obtener información de contacto relevante
     */
    private function getRelevantContactInfo(string $queryType): string
    {
        $contactMapping = [
            'tramite_especifico' => "📞 **CONTACTO ESPECIALIZADO:**\nDGSA (Servicios Académicos): 311-211-8800 ext. 8530\nEmail: dgsa@uan.edu.mx",
            'soporte_tecnico' => "💻 **SOPORTE TÉCNICO:**\nDirección General de Sistemas: 311-211-8800 ext. 8540\nEmail: sistemas@uan.edu.mx",
            'informacion_carrera' => "🎓 **INFORMACIÓN ACADÉMICA:**\nTeléfono general: 311-211-8800\nSitio web: https://www.uan.edu.mx/oferta-educativa",
            'servicio_universitario' => "🏛️ **SERVICIOS UNIVERSITARIOS:**\nInformación general: 311-211-8800\nPortal de servicios: https://www.uan.edu.mx/servicios"
        ];

        return $contactMapping[$queryType] ?? "📞 **CONTACTO GENERAL:**\nUniversidad Autónoma de Nayarit: 311-211-8800\nSitio web: https://www.uan.edu.mx";
    }

    /**
     * Obtener llamada a la acción apropiada
     */
    private function getCallToAction(string $queryType): string
    {
        $ctaMapping = [
            'tramite_especifico' => "🚀 **SIGUIENTE PASO:** Te recomiendo contactar directamente al departamento correspondiente para confirmar requisitos actualizados y agendar tu cita.",
            'informacion_carrera' => "🎯 **TE INVITAMOS A:** Visitar nuestras instalaciones, conocer a nuestros docentes y explorar las oportunidades que la UAN tiene para ti.",
            'soporte_tecnico' => "💡 **RECOMENDACIÓN:** Si el problema persiste, contacta a nuestro equipo técnico especializado para asistencia personalizada.",
            'queja_problema' => "🛡️ **SEGUIMIENTO GARANTIZADO:** Tu inquietud es importante para nosotros. Te asistiremos personalmente para resolver tu situación.",
            'servicio_universitario' => "✨ **APROVECHA:** Todos nuestros servicios están diseñados para enriquecer tu experiencia universitaria. ¡Conócelos todos!"
        ];

        return $ctaMapping[$queryType] ?? "🌟 **¿NECESITAS MÁS AYUDA?** Estoy aquí para asistirte. No dudes en hacer más preguntas o contactar directamente a la UAN.";
    }

    /**
     * Análisis de sentimiento para ajustar tono
     */
    private function analyzeSentiment(string $message): string
    {
        $messageLower = strtolower($message);

        // Indicadores de frustración/urgencia
        $frustrated = ['problema', 'error', 'falla', 'no funciona', 'molesto', 'urgente', 'ayuda'];
        $isFreustrated = false;
        foreach ($frustrated as $word) {
            if (str_contains($messageLower, $word)) {
                $isFreustrated = true;
                break;
            }
        }

        // Indicadores de consulta formal
        $formal = ['solicito', 'requiero', 'necesito información', 'quisiera saber'];
        $isFormal = false;
        foreach ($formal as $phrase) {
            if (str_contains($messageLower, $phrase)) {
                $isFormal = true;
                break;
            }
        }

        if ($isFreustrated) return 'empathetic';
        if ($isFormal) return 'formal';
        return 'friendly';
    }

    /**
     * Generar variaciones de respuesta para A/B testing
     */
    public function generateResponseVariations(string $userMessage, string $userType, array $context): array
    {
        $variations = [];

        // Variación 1: Respuesta estándar
        $variations['standard'] = $this->generateProfessionalResponse($userMessage, $userType, null, $context);

        // Variación 2: Respuesta más concisa
        $concisePrompt = $this->buildSpecializedPrompt('informacion_general', $userType, null, $context) .
                        "\n\nIMPORTANTE: Responde de manera CONCISA pero completa. Máximo 3 párrafos.";

        $variations['concise'] = $this->ollamaService->generateResponse(
            $concisePrompt . "\n\nConsulta: " . $userMessage,
            ['temperature' => 0.3, 'max_tokens' => 400]
        );

        // Variación 3: Respuesta más detallada
        $detailedPrompt = $this->buildSpecializedPrompt('tramite_especifico', $userType, null, $context) .
                         "\n\nIMPORTANTE: Proporciona respuesta DETALLADA con todos los aspectos relevantes.";

        $variations['detailed'] = $this->ollamaService->generateResponse(
            $detailedPrompt . "\n\nConsulta: " . $userMessage,
            ['temperature' => 0.2, 'max_tokens' => 1200]
        );

        return $variations;
    }

    /**
     * Optimización dinámica de prompts basada en feedback
     */
    public function optimizePromptFromFeedback(string $queryType, array $feedbackData): array
    {
        $currentPrompt = $this->getSpecializedInstructions($queryType);

        // Analizar feedback negativo para mejoras
        $negativeFeedback = array_filter($feedbackData, fn($f) => !$f['was_helpful']);

        $optimizations = [];

        foreach ($negativeFeedback as $feedback) {
            if (!empty($feedback['feedback_comment'])) {
                $comment = strtolower($feedback['feedback_comment']);

                // Detectar problemas comunes
                if (str_contains($comment, 'incompleto') || str_contains($comment, 'falta información')) {
                    $optimizations[] = 'add_more_detail';
                }

                if (str_contains($comment, 'confuso') || str_contains($comment, 'no entiendo')) {
                    $optimizations[] = 'simplify_language';
                }

                if (str_contains($comment, 'contacto') || str_contains($comment, 'teléfono')) {
                    $optimizations[] = 'emphasize_contact_info';
                }
            }
        }

        return [
            'current_prompt' => $currentPrompt,
            'suggested_optimizations' => array_unique($optimizations),
            'optimization_priority' => $this->calculateOptimizationPriority($optimizations),
            'sample_improvements' => $this->generatePromptImprovements($queryType, $optimizations)
        ];
    }

    /**
     * Calcular prioridad de optimizaciones
     */
    private function calculateOptimizationPriority(array $optimizations): array
    {
        $counts = array_count_values($optimizations);
        arsort($counts);

        return array_map(function($count, $opt) {
            return [
                'optimization' => $opt,
                'frequency' => $count,
                'priority' => $count > 2 ? 'high' : ($count > 1 ? 'medium' : 'low')
            ];
        }, $counts, array_keys($counts));
    }

    /**
     * Generar mejoras específicas de prompts
     */
    private function generatePromptImprovements(string $queryType, array $optimizations): array
    {
        $improvements = [];

        foreach ($optimizations as $optimization) {
            switch ($optimization) {
                case 'add_more_detail':
                    $improvements[] = "Agregar sección: 'INFORMACIÓN COMPLEMENTARIA' con datos adicionales relevantes";
                    break;

                case 'simplify_language':
                    $improvements[] = "Modificar instrucción: 'Usa lenguaje claro y accesible, evita términos técnicos complejos'";
                    break;

                case 'emphasize_contact_info':
                    $improvements[] = "Añadir: 'OBLIGATORIO: Incluir información de contacto específica y actualizada'";
                    break;
            }
        }

        return $improvements;
    }

    /**
     * Métricas de rendimiento de prompts
     */
    public function getPromptPerformanceMetrics(): array
    {
        $last30Days = now()->subDays(30);

        // Métricas por tipo de consulta
        $metrics = DB::table('chat_interactions')
            ->where('created_at', '>=', $last30Days)
            ->selectRaw('
                COUNT(*) as total_interactions,
                AVG(confidence) as avg_confidence,
                AVG(response_time) as avg_response_time,
                SUM(CASE WHEN was_helpful = 1 THEN 1 ELSE 0 END) as helpful_responses,
                SUM(CASE WHEN requires_human_follow_up = 1 THEN 1 ELSE 0 END) as escalations
            ')
            ->first();

        // Calcular métricas derivadas
        $satisfactionRate = $metrics->total_interactions > 0
            ? ($metrics->helpful_responses / $metrics->total_interactions) * 100
            : 0;

        $escalationRate = $metrics->total_interactions > 0
            ? ($metrics->escalations / $metrics->total_interactions) * 100
            : 0;

        return [
            'period' => '30 días',
            'total_interactions' => $metrics->total_interactions ?? 0,
            'average_confidence' => round($metrics->avg_confidence ?? 0, 2),
            'average_response_time' => round($metrics->avg_response_time ?? 0, 0) . 'ms',
            'satisfaction_rate' => round($satisfactionRate, 1) . '%',
            'escalation_rate' => round($escalationRate, 1) . '%',
            'performance_grade' => $this->calculatePerformanceGrade($metrics),
            'recommendations' => $this->generatePerformanceRecommendations($metrics)
        ];
    }

    /**
     * Calcular calificación de rendimiento
     */
    private function calculatePerformanceGrade($metrics): string
    {
        $confidence = $metrics->avg_confidence ?? 0;
        $helpfulRate = $metrics->total_interactions > 0
            ? ($metrics->helpful_responses / $metrics->total_interactions)
            : 0;

        $score = ($confidence * 0.4) + ($helpfulRate * 0.6);

        if ($score >= 0.9) return 'A+ (Excelente)';
        if ($score >= 0.8) return 'A (Muy Bueno)';
        if ($score >= 0.7) return 'B+ (Bueno)';
        if ($score >= 0.6) return 'B (Aceptable)';
        return 'C (Necesita Mejora)';
    }

    /**
     * Generar recomendaciones de rendimiento
     */
    private function generatePerformanceRecommendations($metrics): array
    {
        $recommendations = [];

        if (($metrics->avg_confidence ?? 0) < 0.7) {
            $recommendations[] = 'Mejorar prompts para aumentar confianza de respuestas';
        }

        if ($metrics->total_interactions > 0) {
            $helpfulRate = $metrics->helpful_responses / $metrics->total_interactions;
            if ($helpfulRate < 0.8) {
                $recommendations[] = 'Optimizar contenido de knowledge base';
            }

            $escalationRate = $metrics->escalations / $metrics->total_interactions;
            if ($escalationRate > 0.3) {
                $recommendations[] = 'Ampliar cobertura de información disponible';
            }
        }

        if (($metrics->avg_response_time ?? 0) > 3000) {
            $recommendations[] = 'Optimizar rendimiento de modelos de IA';
        }

        return $recommendations ?: ['El rendimiento actual es óptimo'];
    }
}
