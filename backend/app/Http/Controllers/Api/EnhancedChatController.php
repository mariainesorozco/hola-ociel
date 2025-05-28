<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OllamaService;
use App\Services\KnowledgeBaseService;
use App\Services\EnhancedPromptService;
use App\Services\GhostIntegrationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class EnhancedChatController extends Controller
{
    private $ollamaService;
    private $knowledgeService;
    private $promptService;
    private $ghostService;

    public function __construct(
        OllamaService $ollamaService,
        KnowledgeBaseService $knowledgeService,
        EnhancedPromptService $promptService,
        GhostIntegrationService $ghostService
    ) {
        $this->ollamaService = $ollamaService;
        $this->knowledgeService = $knowledgeService;
        $this->promptService = $promptService;
        $this->ghostService = $ghostService;
    }

    /**
     * Endpoint principal mejorado con máxima confianza
     */
    public function chat(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        $requestId = Str::uuid();

        // Logging de request para monitoreo
        Log::info('Chat request initiated', [
            'request_id' => $requestId,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        // Validación estricta
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|min:3|max:1000',
            'user_type' => 'in:student,employee,public',
            'department' => 'nullable|string|max:100',
            'session_id' => 'nullable|string|max:100',
            'context_preference' => 'nullable|in:concise,detailed,standard'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Datos de entrada inválidos',
                'details' => $validator->errors(),
                'request_id' => $requestId
            ], 400);
        }

        $validated = $validator->validated();
        $message = trim($validated['message']);
        $userType = $validated['user_type'] ?? 'public';
        $department = $validated['department'] ?? null;
        $sessionId = $validated['session_id'] ?? Str::uuid();
        $contextPreference = $validated['context_preference'] ?? 'standard';

        try {
            // 1. VERIFICACIÓN DE RATE LIMITING
            if (!$this->checkRateLimit($request->ip(), $sessionId)) {
                return $this->rateLimitResponse($requestId);
            }

            // 2. BÚSQUEDA INTELIGENTE CON CACHE
            $context = $this->getIntelligentContext($message, $userType, $department);

            // 3. DETECCIÓN DE INTENCIÓN Y SENTIMIENTO
            $queryAnalysis = $this->analyzeQuery($message, $userType);

            // 4. GENERACIÓN DE RESPUESTA CON ALTA CONFIANZA
            $response = $this->generateHighConfidenceResponse(
                $message,
                $userType,
                $department,
                $context,
                $queryAnalysis,
                $contextPreference
            );

            // 5. VALIDACIÓN DE CALIDAD DE RESPUESTA
            $qualityCheck = $this->validateResponseQuality($response, $context, $message);

            // 6. ESCALACIÓN INTELIGENTE SI ES NECESARIO
            $escalationDecision = $this->intelligentEscalation($message, $response, $qualityCheck, $queryAnalysis);

            $responseTime = round((microtime(true) - $startTime) * 1000);

            // 7. REGISTRO COMPLETO DE INTERACCIÓN
            $interactionData = $this->logEnhancedInteraction([
                'request_id' => $requestId,
                'session_id' => $sessionId,
                'user_type' => $userType,
                'department' => $department,
                'message' => $message,
                'response' => $response['response'] ?? '',
                'context_used' => $context,
                'query_analysis' => $queryAnalysis,
                'quality_metrics' => $qualityCheck,
                'confidence' => $qualityCheck['overall_confidence'],
                'model_used' => $response['model'] ?? 'unknown',
                'response_time' => $responseTime,
                'ip_address' => $request->ip(),
                'channel' => 'web',
                'requires_human_follow_up' => $escalationDecision['escalate'],
                'escalation_reasons' => $escalationDecision['reasons']
            ]);

            // 8. RESPUESTA ENRIQUECIDA
            return response()->json([
                'success' => true,
                'data' => [
                    'response' => $response['response'] ?? '',
                    'session_id' => $sessionId,
                    'request_id' => $requestId,
                    'confidence' => $qualityCheck['overall_confidence'],
                    'quality_indicators' => [
                        'completeness' => $qualityCheck['completeness_score'],
                        'accuracy' => $qualityCheck['accuracy_score'],
                        'helpfulness' => $qualityCheck['helpfulness_score']
                    ],
                    'model_used' => $response['model'] ?? 'unknown',
                    'response_time' => $responseTime,
                    'requires_human_follow_up' => $escalationDecision['escalate'],
                    'escalation_priority' => $escalationDecision['priority'],
                    'contact_info' => $this->getSmartContactInfo($department, $queryAnalysis, $context),
                    'suggested_actions' => $this->getSmartSuggestedActions($message, $department, $context, $queryAnalysis),
                    'related_topics' => $this->getRelatedTopics($context, $queryAnalysis),
                    'feedback_options' => $this->getFeedbackOptions($queryAnalysis['query_type'])
                ],
                'metadata' => [
                    'query_type' => $queryAnalysis['query_type'],
                    'sentiment' => $queryAnalysis['sentiment'],
                    'context_sources' => count($context),
                    'processing_strategy' => $contextPreference,
                    'timestamp' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            return $this->handleChatError($e, $requestId, $message, $userType, $startTime);
        }
    }

    /**
     * Verificar rate limiting inteligente
     */
    private function checkRateLimit(string $ip, string $sessionId): bool
    {
        $ipKey = "rate_limit_ip_{$ip}";
        $sessionKey = "rate_limit_session_{$sessionId}";

        // Límites por IP: 60 requests por minuto
        $ipRequests = Cache::get($ipKey, 0);
        if ($ipRequests >= 60) {
            return false;
        }

        // Límites por sesión: 20 requests por minuto
        $sessionRequests = Cache::get($sessionKey, 0);
        if ($sessionRequests >= 20) {
            return false;
        }

        // Incrementar contadores
        Cache::put($ipKey, $ipRequests + 1, 60);
        Cache::put($sessionKey, $sessionRequests + 1, 60);

        return true;
    }

    /**
     * Respuesta para rate limiting
     */
    private function rateLimitResponse(string $requestId): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'Demasiadas consultas',
            'message' => 'Has alcanzado el límite de consultas por minuto. Intenta de nuevo en unos momentos.',
            'request_id' => $requestId,
            'retry_after' => 60
        ], 429);
    }

    /**
     * Obtener contexto inteligente con cache optimizado
     */
    private function getIntelligentContext(string $message, string $userType, ?string $department): array
    {
        // Cache basado en hash del mensaje para consultas similares
        $cacheKey = 'context_' . md5(strtolower($message) . $userType . ($department ?? ''));

        if (Cache::has($cacheKey)) {
            Log::debug('Using cached context', ['cache_key' => $cacheKey]);
            return Cache::get($cacheKey);
        }

        // Búsqueda multicapa
        try {
            // 1. Búsqueda semántica avanzada
            $semanticResults = $this->knowledgeService->searchRelevantContent($message, $userType, $department);

            // 2. Búsqueda por patrones específicos
            $patternResults = $this->searchByPatterns($message, $userType);

            // 3. Combinación inteligente de resultados
            $combinedContext = $this->combineContextResults($semanticResults, $patternResults);

            // Cache por 5 minutos para consultas similares
            Cache::put($cacheKey, $combinedContext, 300);

            return $combinedContext;

        } catch (\Exception $e) {
            Log::error('Error getting intelligent context: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Búsqueda por patrones específicos UAN
     */
    private function searchByPatterns(string $message, string $userType): array
    {
        $messageLower = strtolower($message);
        $results = [];

        // Patrones específicos de la UAN
        $patterns = [
            'inscripción|admision|inscribir' => 'tramites',
            'carrera|licenciatura|programa|estudiar' => 'oferta_educativa',
            'biblioteca|libros|acervo' => 'servicios',
            'sistema|plataforma|correo|password' => 'sistemas',
            'laboratorio|clinica|enfermeria' => 'servicios',
            'titulacion|titulo|egreso' => 'tramites',
            'maestria|doctorado|posgrado' => 'oferta_educativa'
        ];

        foreach ($patterns as $pattern => $category) {
            if (preg_match("/\b($pattern)\b/i", $messageLower)) {
                $categoryResults = $this->knowledgeService->getContentByCategory($category, $userType);
                $results = array_merge($results, $categoryResults->take(2)->pluck('content')->toArray());
            }
        }

        return array_unique($results);
    }

    /**
     * Combinar resultados de contexto
     */
    private function combineContextResults(array $semantic, array $pattern): array
    {
        $combined = array_merge($semantic, $pattern);

        // Eliminar duplicados y limitar a 5 elementos más relevantes
        $unique = array_unique($combined);

        return array_slice($unique, 0, 5);
    }

    /**
     * Analizar consulta para intención y sentimiento
     */
    private function analyzeQuery(string $message, string $userType): array
    {
        $messageLower = strtolower($message);

        // Análisis de sentimiento
        $sentiment = $this->analyzeSentiment($message);

        // Análisis de urgencia
        $urgency = $this->analyzeUrgency($message);

        // Tipo de consulta
        $queryType = $this->classifyQueryType($message);

        // Complejidad estimada
        $complexity = $this->estimateComplexity($message);

        return [
            'sentiment' => $sentiment,
            'urgency' => $urgency,
            'query_type' => $queryType,
            'complexity' => $complexity,
            'user_type' => $userType,
            'requires_empathy' => $sentiment === 'frustrated' || $urgency === 'high',
            'requires_detailed_response' => $complexity === 'high' || $queryType === 'tramite_especifico'
        ];
    }

    /**
     * Análisis de sentimiento mejorado
     */
    private function analyzeSentiment(string $message): string
    {
        $messageLower = strtolower($message);

        $frustratedWords = ['problema', 'error', 'falla', 'no funciona', 'molesto', 'enojado', 'urgente', 'ayuda por favor'];
        $formalWords = ['solicito', 'requiero', 'quisiera', 'podría', 'información sobre'];
        $casualWords = ['hola', 'qué tal', 'buenas', 'saludos'];

        $frustratedScore = 0;
        $formalScore = 0;
        $casualScore = 0;

        foreach ($frustratedWords as $word) {
            if (str_contains($messageLower, $word)) $frustratedScore++;
        }

        foreach ($formalWords as $word) {
            if (str_contains($messageLower, $word)) $formalScore++;
        }

        foreach ($casualWords as $word) {
            if (str_contains($messageLower, $word)) $casualScore++;
        }

        if ($frustratedScore > 0) return 'frustrated';
        if ($formalScore > 0) return 'formal';
        if ($casualScore > 0) return 'casual';

        return 'neutral';
    }

    /**
     * Análisis de urgencia
     */
    private function analyzeUrgency(string $message): string
    {
        $messageLower = strtolower($message);

        $highUrgency = ['urgente', 'inmediato', 'ya', 'ahora', 'rápido', 'emergency', 'emergencia'];
        $mediumUrgency = ['pronto', 'cuanto antes', 'necesito', 'importante'];

        foreach ($highUrgency as $word) {
            if (str_contains($messageLower, $word)) return 'high';
        }

        foreach ($mediumUrgency as $word) {
            if (str_contains($messageLower, $word)) return 'medium';
        }

        return 'low';
    }

    /**
     * Clasificar tipo de consulta
     */
    private function classifyQueryType(string $message): string
    {
        $messageLower = strtolower($message);

        $patterns = [
            'tramite_especifico' => ['inscripción', 'titulación', 'certificado', 'constancia', 'revalidación'],
            'informacion_carrera' => ['carrera', 'licenciatura', 'programa', 'estudios'],
            'soporte_tecnico' => ['sistema', 'plataforma', 'correo', 'contraseña', 'acceso'],
            'servicios' => ['biblioteca', 'laboratorio', 'cafetería', 'transporte'],
            'queja_problema' => ['problema', 'queja', 'reclamo', 'error', 'falla'],
            'saludo' => ['hola', 'buenos días', 'buenas tardes', 'qué tal']
        ];

        foreach ($patterns as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($messageLower, $keyword)) {
                    return $type;
                }
            }
        }

        return 'consulta_general';
    }

    /**
     * Estimar complejidad
     */
    private function estimateComplexity(string $message): string
    {
        $wordCount = str_word_count($message);
        $questionMarks = substr_count($message, '?');

        $complexWords = ['procedimiento', 'requisitos', 'documentación', 'proceso', 'normativa'];
        $complexWordCount = 0;

        foreach ($complexWords as $word) {
            if (str_contains(strtolower($message), $word)) {
                $complexWordCount++;
            }
        }

        if ($wordCount > 30 || $questionMarks > 2 || $complexWordCount > 1) {
            return 'high';
        } elseif ($wordCount > 15 || $questionMarks > 1 || $complexWordCount > 0) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Generar respuesta de alta confianza
     */
    private function generateHighConfidenceResponse(
        string $message,
        string $userType,
        ?string $department,
        array $context,
        array $queryAnalysis,
        string $contextPreference
    ): array {

        // Verificar disponibilidad de IA
        if (!$this->ollamaService->isHealthy()) {
            return $this->getFallbackResponse($message, $context, $queryAnalysis);
        }

        try {
            // Usar servicio de prompts mejorados
            $response = $this->promptService->generateProfessionalResponse(
                $message,
                $userType,
                $department,
                $context
            );

            // Si la confianza es baja, intentar con modelo alternativo
            if (($response['confidence'] ?? 0) < 0.6) {
                Log::info('Low confidence response, trying alternative approach', [
                    'original_confidence' => $response['confidence'] ?? 0,
                    'message' => substr($message, 0, 100)
                ]);

                $alternativeResponse = $this->tryAlternativeGeneration($message, $userType, $context, $queryAnalysis);

                if (($alternativeResponse['confidence'] ?? 0) > ($response['confidence'] ?? 0)) {
                    $response = $alternativeResponse;
                }
            }

            return $response;

        } catch (\Exception $e) {
            Log::error('Error generating high confidence response: ' . $e->getMessage());
            return $this->getFallbackResponse($message, $context, $queryAnalysis);
        }
    }

    /**
     * Intentar generación alternativa
     */
    private function tryAlternativeGeneration(string $message, string $userType, array $context, array $queryAnalysis): array
    {
        // Estrategia alternativa: usar contexto más específico
        $specificContext = $this->getSpecificContext($message, $queryAnalysis['query_type']);

        if (!empty($specificContext)) {
            return $this->promptService->generateProfessionalResponse($message, $userType, null, $specificContext);
        }

        // Si no hay contexto específico, usar respuesta template
        return $this->getTemplateResponse($message, $queryAnalysis['query_type'], $userType);
    }

    /**
     * Obtener contexto específico adicional
     */
    private function getSpecificContext(string $message, string $queryType): array
    {
        $specificQueries = [
            'tramite_especifico' => $this->knowledgeService->getContentByCategory('tramites', 'student'),
            'informacion_carrera' => $this->knowledgeService->getContentByCategory('oferta_educativa', 'public'),
            'soporte_tecnico' => $this->knowledgeService->getContentByDepartment('DGS', 'student'),
            'servicios' => $this->knowledgeService->getContentByCategory('servicios', 'student')
        ];

        if (isset($specificQueries[$queryType])) {
            return $specificQueries[$queryType]->take(3)->pluck('content')->toArray();
        }

        return [];
    }

    /**
     * Obtener respuesta template de alta calidad
     */
    private function getTemplateResponse(string $message, string $queryType, string $userType): array
    {
        $templates = [
            'tramite_especifico' => [
                'response' => "📋 **INFORMACIÓN DE TRÁMITES UAN**\n\nPara obtener información específica sobre el trámite que necesitas, te recomiendo contactar directamente a:\n\n🏛️ **SA (Secretaría Académica)**\n📞 Teléfono: 311-211-8803 ext. 8530\n📧 Email: academica@uan.edu.mx\n📍 Ubicación: Edificio PiiDA\n⏰ Horario: Lunes a Viernes de 8:00 a 20:00 hrs\n\n✅ **Te pueden ayudar con:**\n• Procesos de inscripción\n• Trámites de titulación\n• Certificados y constancias\n• Revalidación de estudios\n• Control escolar\n\n🚀 **Siguiente paso:** Contacta directamente para obtener información actualizada y específica para tu situación.",
                'confidence' => 0.8
            ],
            'informacion_carrera' => [
                'response' => "🎓 **OFERTA EDUCATIVA UAN**\n\nLa Universidad Autónoma de Nayarit ofrece más de 40 programas de licenciatura en diversas áreas del conocimiento:\n\n📚 **Áreas disponibles:**\n• Ciencias Básicas e Ingenierías\n• Ciencias Sociales y Humanidades\n• Ciencias de la Salud\n• Ciencias Biológico Agropecuarias y Pesqueras\n\n📞 **Para información detallada:**\n• Teléfono general: 311-211-8800\n• Portal web: https://www.uan.edu.mx/es/oferta\n• SA: 311-211-8800 ext. 8803\n\n🎯 **Te recomendamos:** Visitar nuestras instalaciones y conocer de cerca los programas que te interesan.",
                'confidence' => 0.85
            ],
            'soporte_tecnico' => [
                'response' => "💻 **SOPORTE TÉCNICO UAN**\n\nPara resolver problemas técnicos de las plataformas institucionales:\n\n🏛️ **Dirección General de Sistemas (DGS)**\n📞 Teléfono: 311-211-8800 ext. 8640\n📧 Email: dgs@uan.edu.mx\n📍 Ubicación: Edificio de Finanzas, 2do. piso\n⏰ Horario: Lunes a Viernes de 8:00 a 20:00 hrs\n\n🔧 **Servicios disponibles:**\n• Problemas de acceso a plataformas\n• Desarrollo de sistemas\n\n💡 **Recomendación:** Contacta directamente para asistencia especializada.",
                'confidence' => 0.9
            ]
        ];

        $template = $templates[$queryType] ?? [
            'response' => "👋 **¡Hola! Soy Ociel, tu asistente de la UAN**\n\nEstoy aquí para ayudarte con información sobre nuestra universidad.\n\n📞 **Contacto general:** 311-211-8800\n🌐 **Portal oficial:** https://www.uan.edu.mx\n📍 **Ubicación:** Ciudad de la Cultura \"Amado Nervo\", Tepic, Nayarit\n\n🎓 **Puedo ayudarte con:**\n• Información sobre carreras\n• Trámites y servicios\n• Contactos de departamentos\n• Servicios universitarios\n\n¿En qué más puedo asistirte?",
            'confidence' => 0.7
        ];

        return array_merge($template, ['model' => 'template_response', 'success' => true]);
    }

    /**
     * Respuesta de fallback robusta
     */
    private function getFallbackResponse(string $message, array $context, array $queryAnalysis): array
    {
        if (!empty($context)) {
            $response = "📋 **Información disponible:**\n\n" .
                       substr($context[0], 0, 400) . "...\n\n" .
                       "📞 **Para más información:** 311-211-8800\n" .
                       "🌐 **Portal oficial:** https://www.uan.edu.mx";

            return [
                'response' => $response,
                'confidence' => 0.6,
                'model' => 'fallback_with_context',
                'success' => true
            ];
        }

        return $this->getTemplateResponse($message, $queryAnalysis['query_type'], 'public');
    }

    /**
     * Validar calidad de respuesta
     */
    private function validateResponseQuality(array $response, array $context, string $originalMessage): array
    {
        $responseText = $response['response'] ?? '';

        // Métricas de calidad
        $completenessScore = $this->assessCompleteness($responseText, $originalMessage);
        $accuracyScore = $this->assessAccuracy($responseText, $context);
        $helpfulnessScore = $this->assessHelpfulness($responseText, $originalMessage);
        $structureScore = $this->assessStructure($responseText);

        // Puntuación general
        $overallConfidence = ($completenessScore + $accuracyScore + $helpfulnessScore + $structureScore) / 4;

        return [
            'completeness_score' => $completenessScore,
            'accuracy_score' => $accuracyScore,
            'helpfulness_score' => $helpfulnessScore,
            'structure_score' => $structureScore,
            'overall_confidence' => $overallConfidence,
            'quality_indicators' => $this->getQualityIndicators($responseText),
            'missing_elements' => $this->identifyMissingElements($responseText, $originalMessage)
        ];
    }

    /**
     * Evaluar completitud de respuesta
     */
    private function assessCompleteness(string $response, string $originalMessage): float
    {
        $score = 0.0;

        // Longitud apropiada (ni muy corta ni muy larga)
        $length = strlen($response);
        if ($length >= 100 && $length <= 2000) {
            $score += 0.3;
        } elseif ($length >= 50) {
            $score += 0.1;
        }

        // Presencia de información clave
        if ($this->containsContactInfo($response)) $score += 0.2;
        if ($this->containsActionableInfo($response)) $score += 0.2;
        if ($this->containsSpecificDetails($response)) $score += 0.2;
        if ($this->addressesMainQuery($response, $originalMessage)) $score += 0.1;

        return min(1.0, $score);
    }

    /**
     * Evaluar precisión de respuesta
     */
    private function assessAccuracy(string $response, array $context): float
    {
        $score = 0.5; // Base score

        // Si hay contexto y la respuesta lo utiliza
        if (!empty($context)) {
            $contextUsage = $this->calculateContextUsage($response, $context);
            $score += $contextUsage * 0.3;
        }

        // Verificar información institucional correcta
        if ($this->containsCorrectInstitutionalInfo($response)) {
            $score += 0.2;
        }

        return min(1.0, $score);
    }

    /**
     * Evaluar utilidad de respuesta
     */
    private function assessHelpfulness(string $response, string $originalMessage): float
    {
        $score = 0.0;

        // Respuesta directa a la pregunta
        if ($this->answersDirectly($response, $originalMessage)) $score += 0.3;

        // Información adicional útil
        if ($this->providesAdditionalValue($response)) $score += 0.2;

        // Tono apropiado y empático
        if ($this->hasAppropriateTone($response)) $score += 0.2;

        // Guía para próximos pasos
        if ($this->providesNextSteps($response)) $score += 0.3;

        return min(1.0, $score);
    }

    /**
     * Evaluar estructura de respuesta
     */
    private function assessStructure(string $response): float
    {
        $score = 0.0;

        // Uso de emojis y formato
        if (preg_match('/[📞📧📍🎓💻🏛️]/', $response)) $score += 0.2;

        // Organización en secciones
        if (preg_match('/\*\*.*\*\*/', $response)) $score += 0.2;

        // Uso de listas o bullets
        if (preg_match('/[•·]|^\s*[-*]\s/m', $response)) $score += 0.2;

        // Información de contacto bien formateada
        if (preg_match('/📞.*311-211-8800/', $response)) $score += 0.2;

        // Llamada a la acción clara
        if (preg_match('/🚀|💡|✅.*paso/i', $response)) $score += 0.2;

        return min(1.0, $score);
    }

    /**
     * Escalación inteligente
     */
    private function intelligentEscalation(string $message, array $response, array $qualityCheck, array $queryAnalysis): array
    {
        $escalate = false;
        $priority = 'low';
        $reasons = [];

        // Escalación por baja confianza
        if ($qualityCheck['overall_confidence'] < 0.6) {
            $escalate = true;
            $priority = 'medium';
            $reasons[] = 'baja_confianza';
        }

        // Escalación por sentimiento
        if ($queryAnalysis['sentiment'] === 'frustrated') {
            $escalate = true;
            $priority = 'high';
            $reasons[] = 'usuario_frustrado';
        }

        // Escalación por urgencia
        if ($queryAnalysis['urgency'] === 'high') {
            $escalate = true;
            $priority = 'high';
            $reasons[] = 'urgencia_alta';
        }

        // Escalación por tipo de consulta
        if ($queryAnalysis['query_type'] === 'queja_problema') {
            $escalate = true;
            $priority = 'high';
            $reasons[] = 'queja_o_problema';
        }

        // Escalación por complejidad
        if ($queryAnalysis['complexity'] === 'high' && $qualityCheck['overall_confidence'] < 0.8) {
            $escalate = true;
            $priority = $priority === 'high' ? 'high' : 'medium';
            $reasons[] = 'consulta_compleja';
        }

        return [
            'escalate' => $escalate,
            'priority' => $priority,
            'reasons' => $reasons,
            'recommended_department' => $this->getRecommendedDepartment($queryAnalysis['query_type']),
            'estimated_resolution_time' => $this->estimateResolutionTime($priority, $queryAnalysis['query_type'])
        ];
    }

    /**
     * Obtener información de contacto inteligente
     */
    private function getSmartContactInfo(?string $department, array $queryAnalysis, array $context): array
    {
        // Información específica por tipo de consulta
        $contactMapping = [
            'tramite_especifico' => [
                'primary' => ['name' => 'SA', 'phone' => '311-211-8800 ext. 8803', 'email' => 'academica@uan.edu.mx'],
                'secondary' => ['name' => 'Información Académica', 'phone' => '311-211-8800']
            ],
            'soporte_tecnico' => [
                'primary' => ['name' => 'DGS - Sistemas', 'phone' => '311-211-8800 ext. 8540', 'email' => 'sistemas@uan.edu.mx'],
                'secondary' => ['name' => 'Ayuda técnica plaformas institucionales', 'phone' => '311-211-8800 ext. 8640']
            ],
            'informacion_carrera' => [
                'primary' => ['name' => 'Información General', 'phone' => '311-211-8800'],
                'secondary' => ['name' => 'SA', 'phone' => '311-211-8800 ext. 8803']
            ]
        ];

        $contacts = $contactMapping[$queryAnalysis['query_type']] ?? [
            'primary' => ['name' => 'Universidad Autónoma de Nayarit', 'phone' => '311-211-8800'],
            'secondary' => ['name' => 'Portal Web', 'url' => 'https://www.uan.edu.mx']
        ];

        // Agregar información de horarios y ubicación
        $contacts['hours'] = 'Lunes a Viernes de 8:00 a 20:00 hrs';
        $contacts['location'] = 'Ciudad de la Cultura "Amado Nervo", Tepic, Nayarit';

        return $contacts;
    }

    /**
     * Obtener acciones sugeridas inteligentes
     */
    private function getSmartSuggestedActions(string $message, ?string $department, array $context, array $queryAnalysis): array
    {
        $actions = [];
        $queryType = $queryAnalysis['query_type'];

        // Acciones específicas por tipo de consulta
        switch ($queryType) {
            case 'tramite_especifico':
                $actions = [
                    ['type' => 'contact', 'text' => 'Contactar a SA para información específica', 'priority' => 'high'],
                    ['type' => 'document', 'text' => 'Preparar documentación requerida', 'priority' => 'medium'],
                    ['type' => 'visit', 'text' => 'Agendar cita presencial si es necesario', 'priority' => 'medium'],
                    ['type' => 'web', 'text' => 'Consultar portal de servicios estudiantiles', 'priority' => 'low']
                ];
                break;

            case 'informacion_carrera':
                $actions = [
                    ['type' => 'web', 'text' => 'Explorar oferta educativa completa en el sitio web', 'priority' => 'high'],
                    ['type' => 'visit', 'text' => 'Visitar las instalaciones de la carrera de interés', 'priority' => 'high'],
                    ['type' => 'contact', 'text' => 'Solicitar orientación vocacional', 'priority' => 'medium'],
                    ['type' => 'event', 'text' => 'Asistir a eventos de difusión académica', 'priority' => 'low']
                ];
                break;

            case 'soporte_tecnico':
                $actions = [
                    ['type' => 'contact', 'text' => 'Contactar a DGS para soporte especializado', 'priority' => 'high'],
                    ['type' => 'self_help', 'text' => 'Verificar credenciales de acceso', 'priority' => 'high'],
                    ['type' => 'document', 'text' => 'Consultar manuales de usuario disponibles', 'priority' => 'medium'],
                    ['type' => 'ticket', 'text' => 'Generar ticket de soporte si el problema persiste', 'priority' => 'low']
                ];
                break;

            case 'queja_problema':
                $actions = [
                    ['type' => 'escalation', 'text' => 'Escalación inmediata a autoridades competentes', 'priority' => 'high'],
                    ['type' => 'document', 'text' => 'Documentar detalladamente la situación', 'priority' => 'high'],
                    ['type' => 'contact', 'text' => 'Contactar a Secretaría General para seguimiento', 'priority' => 'medium'],
                    ['type' => 'rights', 'text' => 'Conocer derechos y procedimientos de apelación', 'priority' => 'medium']
                ];
                break;

            default:
                $actions = [
                    ['type' => 'contact', 'text' => 'Contactar al departamento correspondiente', 'priority' => 'medium'],
                    ['type' => 'web', 'text' => 'Consultar información en el portal oficial', 'priority' => 'medium'],
                    ['type' => 'chat', 'text' => 'Hacer una pregunta más específica', 'priority' => 'low']
                ];
        }

        // Personalizar según urgencia
        if ($queryAnalysis['urgency'] === 'high') {
            foreach ($actions as &$action) {
                if ($action['type'] === 'contact') {
                    $action['priority'] = 'high';
                    $action['text'] .= ' (URGENTE)';
                }
            }
        }

        return $actions;
    }

    /**
     * Obtener temas relacionados
     */
    private function getRelatedTopics(array $context, array $queryAnalysis): array
    {
        $relatedTopics = [];
        $queryType = $queryAnalysis['query_type'];

        // Temas relacionados por tipo de consulta
        $topicMapping = [
            'tramite_especifico' => [
                'Servicios Escolares',
                'Control Escolar',
                'Certificados y Constancias',
                'Revalidación de Estudios',
                'Titulación'
            ],
            'informacion_carrera' => [
                'Plan de Estudios',
                'Perfil de Egreso',
                'Campo Laboral',
                'Requisitos de Admisión',
                'Intercambio Académico'
            ],
            'soporte_tecnico' => [
                'Correo Institucional',
                'Plataformas Educativas',
                'WiFi Universitario',
                'Mesa de Ayuda',
                'Manuales de Usuario'
            ],
            'servicios' => [
                'Biblioteca',
                'Laboratorios',
                'Servicios Médicos',
                'Actividades Deportivas',
                'Servicios de Alimentación'
            ]
        ];

        $topics = $topicMapping[$queryType] ?? [
            'Información General',
            'Directorio de Contactos',
            'Horarios de Atención',
            'Servicios Universitarios'
        ];

        // Convertir a formato estructurado
        foreach ($topics as $topic) {
            $relatedTopics[] = [
                'title' => $topic,
                'query_suggestion' => "Información sobre {$topic}",
                'relevance' => 'medium'
            ];
        }

        return array_slice($relatedTopics, 0, 5);
    }

    /**
     * Obtener opciones de feedback
     */
    private function getFeedbackOptions(string $queryType): array
    {
        return [
            [
                'type' => 'rating',
                'question' => '¿Qué tan útil fue esta respuesta?',
                'options' => ['Muy útil', 'Útil', 'Poco útil', 'No útil']
            ],
            [
                'type' => 'boolean',
                'question' => '¿Resolvió tu consulta completamente?',
                'options' => ['Sí', 'No']
            ],
            [
                'type' => 'text',
                'question' => '¿Cómo podemos mejorar esta respuesta?',
                'placeholder' => 'Comparte tus comentarios (opcional)'
            ],
            [
                'type' => 'action',
                'question' => '¿Necesitas hablar con un humano?',
                'action' => 'request_human_agent'
            ]
        ];
    }

    /**
     * Registro mejorado de interacciones
     */
    private function logEnhancedInteraction(array $data): int
    {
        try {
            $interactionData = [
                'request_id' => $data['request_id'],
                'session_id' => $data['session_id'],
                'user_type' => $data['user_type'],
                'department' => $data['department'],
                'message' => $data['message'],
                'response' => $data['response'],
                'context_used' => json_encode($data['context_used']),
                'query_analysis' => json_encode($data['query_analysis']),
                'quality_metrics' => json_encode($data['quality_metrics']),
                'confidence' => $data['confidence'],
                'model_used' => $data['model_used'],
                'response_time' => $data['response_time'],
                'ip_address' => $data['ip_address'],
                'channel' => $data['channel'],
                'requires_human_follow_up' => $data['requires_human_follow_up'],
                'escalation_reasons' => json_encode($data['escalation_reasons']),
                'created_at' => now(),
                'updated_at' => now()
            ];

            return DB::table('chat_interactions')->insertGetId($interactionData);

        } catch (\Exception $e) {
            Log::error('Error logging enhanced interaction: ' . $e->getMessage(), $data);
            return 0;
        }
    }

    /**
     * Manejo de errores de chat
     */
    private function handleChatError(\Exception $e, string $requestId, string $message, string $userType, float $startTime): JsonResponse
    {
        $responseTime = round((microtime(true) - $startTime) * 1000);

        Log::error('Chat error occurred', [
            'request_id' => $requestId,
            'error' => $e->getMessage(),
            'message' => substr($message, 0, 100),
            'user_type' => $userType,
            'response_time' => $responseTime,
            'stack_trace' => $e->getTraceAsString()
        ]);

        // Respuesta de emergencia robusta
        $emergencyResponse = "🚨 **Disculpa las molestias**\n\n" .
                           "Estoy experimentando dificultades técnicas temporales. " .
                           "Para asistencia inmediata, contacta directamente:\n\n" .
                           "📞 **Universidad Autónoma de Nayarit**\n" .
                           "Teléfono: 311-211-8800\n" .
                           "🌐 Portal: https://www.uan.edu.mx\n\n" .
                           "🔧 **Soporte Técnico:** sistemas@uan.edu.mx\n\n" .
                           "Estaré disponible nuevamente en unos momentos.";

        return response()->json([
            'success' => false,
            'error' => 'Error temporal del sistema',
            'data' => [
                'response' => $emergencyResponse,
                'session_id' => Str::uuid(),
                'request_id' => $requestId,
                'confidence' => 0.3,
                'model_used' => 'emergency_response',
                'response_time' => $responseTime,
                'requires_human_follow_up' => true,
                'escalation_priority' => 'high',
                'contact_info' => [
                    'primary' => ['name' => 'UAN General', 'phone' => '311-211-8800'],
                    'technical' => ['name' => 'Soporte Técnico', 'email' => 'sistemas@uan.edu.mx']
                ]
            ],
            'metadata' => [
                'error_type' => 'system_error',
                'retry_suggested' => true,
                'timestamp' => now()->toISOString()
            ]
        ], 500);
    }

    // =================================================================
    // MÉTODOS AUXILIARES PARA VALIDACIÓN DE CALIDAD
    // =================================================================

    private function containsContactInfo(string $text): bool
    {
        return preg_match('/\b311-211-8800\b|\b\w+@\w+\.edu\.mx\b|ext\.\s*\d+/i', $text);
    }

    private function containsActionableInfo(string $text): bool
    {
        $actionWords = ['contacta', 'visita', 'solicita', 'consulta', 'agenda', 'presenta'];
        foreach ($actionWords as $word) {
            if (stripos($text, $word) !== false) return true;
        }
        return false;
    }

    private function containsSpecificDetails(string $text): bool
    {
        return preg_match('/\d{2}:\d{2}|\$\d+|ext\.\s*\d+|requisitos?|documentos?/i', $text);
    }

    private function addressesMainQuery(string $response, string $query): bool
    {
        $queryWords = explode(' ', strtolower($query));
        $responseWords = explode(' ', strtolower($response));

        $matches = 0;
        foreach ($queryWords as $word) {
            if (strlen($word) > 3 && in_array($word, $responseWords)) {
                $matches++;
            }
        }

        return $matches >= max(1, count($queryWords) * 0.3);
    }

    private function calculateContextUsage(string $response, array $context): float
    {
        if (empty($context)) return 0.0;

        $usage = 0.0;
        foreach ($context as $contextItem) {
            $contextWords = explode(' ', strtolower($contextItem));
            $responseWords = explode(' ', strtolower($response));

            $overlap = count(array_intersect($contextWords, $responseWords));
            $usage += $overlap / max(count($contextWords), 1);
        }

        return min(1.0, $usage / count($context));
    }

    private function containsCorrectInstitutionalInfo(string $response): bool
    {
        $correctInfo = [
            '311-211-8800' => true,
            'Universidad Autónoma de Nayarit' => true,
            'UAN' => true,
            'Tepic, Nayarit' => true,
            'uan.edu.mx' => true
        ];

        foreach ($correctInfo as $info => $expected) {
            if (stripos($response, $info) !== false) {
                return true;
            }
        }

        return false;
    }

    private function answersDirectly(string $response, string $query): bool
    {
        // Heurística simple: verificar si responde a preguntas directas
        if (str_contains($query, '?')) {
            return strlen($response) > 50 && !str_contains(strtolower($response), 'no tengo información');
        }

        return $this->addressesMainQuery($response, $query);
    }

    private function providesAdditionalValue(string $response): bool
    {
        $valueIndicators = ['también', 'además', 'adicionalmente', 'te recomiendo', 'puedes', 'opcionalmente'];
        foreach ($valueIndicators as $indicator) {
            if (stripos($response, $indicator) !== false) return true;
        }
        return false;
    }

    private function hasAppropriateTone(string $response): bool
    {
        // Verificar tono empático y profesional
        $positiveIndicators = ['ayudar', 'asistir', 'apoyar', 'gusto', 'disposición'];
        $count = 0;
        foreach ($positiveIndicators as $indicator) {
            if (stripos($response, $indicator) !== false) $count++;
        }
        return $count > 0;
    }

    private function providesNextSteps(string $response): bool
    {
        $stepIndicators = ['siguiente paso', 'próximo', 'luego', 'después', 'te recomiendo', 'contacta', 'visita'];
        foreach ($stepIndicators as $indicator) {
            if (stripos($response, $indicator) !== false) return true;
        }
        return false;
    }

    private function getQualityIndicators(string $response): array
    {
        return [
            'has_contact_info' => $this->containsContactInfo($response),
            'has_actionable_info' => $this->containsActionableInfo($response),
            'has_specific_details' => $this->containsSpecificDetails($response),
            'appropriate_length' => strlen($response) >= 100 && strlen($response) <= 2000,
            'well_structured' => preg_match('/[📞📧📍🎓💻]|\\*\\*/', $response),
            'empathetic_tone' => $this->hasAppropriateTone($response)
        ];
    }

    private function identifyMissingElements(string $response, string $query): array
    {
        $missing = [];

        if (!$this->containsContactInfo($response)) {
            $missing[] = 'información_de_contacto';
        }

        if (!$this->containsActionableInfo($response)) {
            $missing[] = 'pasos_accionables';
        }

        if (stripos($query, 'requisitos') !== false && stripos($response, 'requisitos') === false) {
            $missing[] = 'requisitos_específicos';
        }

        if (stripos($query, 'horario') !== false && !preg_match('/\d{1,2}:\d{2}/', $response)) {
            $missing[] = 'horarios_específicos';
        }

        return $missing;
    }

    private function getRecommendedDepartment(string $queryType): ?string
    {
        $departmentMapping = [
            'tramite_especifico' => 'SA',
            'soporte_tecnico' => 'DGS',
            'queja_problema' => 'SECRETARIA_GENERAL',
            'informacion_carrera' => 'SA',
            'servicios' => 'GENERAL'
        ];

        return $departmentMapping[$queryType] ?? null;
    }

    private function estimateResolutionTime(string $priority, string $queryType): string
    {
        $timeMapping = [
            'high' => '2-4 horas',
            'medium' => '1-2 días hábiles',
            'low' => '3-5 días hábiles'
        ];

        return $timeMapping[$priority] ?? '1-2 días hábiles';
    }

    // =================================================================
    // ENDPOINTS ADICIONALES
    // =================================================================

    /**
     * Endpoint para feedback mejorado
     */
    public function enhancedFeedback(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required|string',
            'request_id' => 'required|string',
            'rating' => 'required|integer|min:1|max:5',
            'was_helpful' => 'required|boolean',
            'feedback_type' => 'required|in:quality,accuracy,completeness,tone',
            'feedback_comment' => 'nullable|string|max:1000',
            'suggested_improvement' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Datos de feedback inválidos'
            ], 400);
        }

        $validated = $validator->validated();

        try {
            // Actualizar interacción original
            $updated = DB::table('chat_interactions')
                ->where('session_id', $validated['session_id'])
                ->where('request_id', $validated['request_id'])
                ->update([
                    'was_helpful' => $validated['was_helpful'],
                    'feedback_comment' => $validated['feedback_comment'],
                    'feedback_rating' => $validated['rating'],
                    'feedback_type' => $validated['feedback_type'],
                    'suggested_improvement' => $validated['suggested_improvement'],
                    'updated_at' => now()
                ]);

            // Procesar feedback para mejoras
            $this->processFeedbackForImprovements($validated);

            return response()->json([
                'success' => $updated > 0,
                'message' => '¡Gracias por tu feedback! Nos ayuda a mejorar continuamente.',
                'follow_up' => $validated['rating'] <= 2 ? 'Un agente humano revisará tu caso.' : null
            ]);

        } catch (\Exception $e) {
            Log::error('Error processing enhanced feedback: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error procesando feedback'
            ], 500);
        }
    }

    /**
     * Health check avanzado del sistema
     */
    public function healthAdvanced(): JsonResponse
    {
        $health = [
            'status' => 'ok',
            'service' => 'Enhanced Hola Ociel API',
            'version' => '2.0.0',
            'timestamp' => now()->toISOString(),
            'components' => [
                'database' => $this->checkDatabaseHealth(),
                'ollama' => $this->ollamaService->isHealthy(),
                'knowledge_base' => $this->knowledgeService->isHealthy(),
                'ghost_cms' => $this->ghostService->healthCheck()['status'] === 'ok',
                'cache' => $this->checkCacheHealth()
            ],
            'metrics' => [
                'total_interactions_today' => $this->getTodayInteractionsCount(),
                'average_confidence_24h' => $this->getAverageConfidence24h(),
                'escalation_rate_24h' => $this->getEscalationRate24h(),
                'average_response_time_24h' => $this->getAverageResponseTime24h()
            ]
        ];

        $allHealthy = collect($health['components'])->every(fn($status) => $status === true);
        $health['status'] = $allHealthy ? 'healthy' : 'degraded';

        return response()->json($health, $allHealthy ? 200 : 503);
    }

    /**
     * Endpoint para obtener métricas de rendimiento
     */
    public function performanceMetrics(): JsonResponse
    {
        try {
            // Generar métricas desde la base de datos directamente
            $metrics = $this->generateBasicPerformanceMetrics();

            return response()->json([
                'success' => true,
                'data' => $metrics,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting performance metrics: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Error obteniendo métricas'
            ], 500);
        }
    }

    /**
     * Generar métricas básicas de rendimiento
     */
    private function generateBasicPerformanceMetrics(): array
    {
        // Métricas de los últimos 7 días
        $startDate = now()->subDays(7);

        // Métricas básicas de chat_interactions
        $totalInteractions = DB::table('chat_interactions')
            ->where('created_at', '>=', $startDate)
            ->count();

        $averageConfidence = DB::table('chat_interactions')
            ->where('created_at', '>=', $startDate)
            ->avg('confidence') ?? 0;

        $averageResponseTime = DB::table('chat_interactions')
            ->where('created_at', '>=', $startDate)
            ->avg('response_time') ?? 0;

        $escalationRate = $totalInteractions > 0
            ? (DB::table('chat_interactions')
                ->where('created_at', '>=', $startDate)
                ->where('requires_human_follow_up', true)
                ->count() / $totalInteractions) * 100
            : 0;

        // Métricas por modelo usado
        $modelUsage = DB::table('chat_interactions')
            ->where('created_at', '>=', $startDate)
            ->select('model_used', DB::raw('count(*) as usage_count'), DB::raw('avg(confidence) as avg_confidence'))
            ->groupBy('model_used')
            ->get()
            ->toArray();

        // Métricas por tipo de usuario
        $userTypeMetrics = DB::table('chat_interactions')
            ->where('created_at', '>=', $startDate)
            ->select('user_type', DB::raw('count(*) as count'), DB::raw('avg(confidence) as avg_confidence'))
            ->groupBy('user_type')
            ->get()
            ->toArray();

        // Métricas diarias (últimos 7 días)
        $dailyMetrics = DB::table('chat_interactions')
            ->where('created_at', '>=', $startDate)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('count(*) as interactions'),
                DB::raw('avg(confidence) as avg_confidence'),
                DB::raw('avg(response_time) as avg_response_time')
            )
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->toArray();

        return [
            'period' => '7_days',
            'summary' => [
                'total_interactions' => $totalInteractions,
                'average_confidence' => round($averageConfidence, 3),
                'average_response_time_ms' => round($averageResponseTime, 0),
                'escalation_rate_percent' => round($escalationRate, 1),
                'success_rate_percent' => round((1 - ($escalationRate / 100)) * 100, 1)
            ],
            'model_performance' => $modelUsage,
            'user_type_breakdown' => $userTypeMetrics,
            'daily_trends' => $dailyMetrics,
            'quality_indicators' => [
                'high_confidence_rate' => $this->getHighConfidenceRate($startDate),
                'positive_feedback_rate' => $this->getPositiveFeedbackRate($startDate),
                'quick_response_rate' => $this->getQuickResponseRate($startDate)
            ],
            'system_health' => [
                'ollama_available' => $this->ollamaService->isHealthy(),
                'knowledge_base_healthy' => $this->knowledgeService->isHealthy(),
                'semantic_search_enabled' => method_exists($this->knowledgeService, 'isSemanticSearchAvailable')
                    ? $this->knowledgeService->isSemanticSearchAvailable()
                    : false
            ]
        ];
    }

    /**
     * Obtener tasa de alta confianza
     */
    private function getHighConfidenceRate(\Carbon\Carbon $startDate): float
    {
        $total = DB::table('chat_interactions')
            ->where('created_at', '>=', $startDate)
            ->count();

        if ($total === 0) return 0;

        $highConfidence = DB::table('chat_interactions')
            ->where('created_at', '>=', $startDate)
            ->where('confidence', '>=', 0.8)
            ->count();

        return round(($highConfidence / $total) * 100, 1);
    }

    /**
     * Obtener tasa de feedback positivo
     */
    private function getPositiveFeedbackRate(\Carbon\Carbon $startDate): float
    {
        $totalWithFeedback = DB::table('chat_interactions')
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('was_helpful')
            ->count();

        if ($totalWithFeedback === 0) return 0;

        $positive = DB::table('chat_interactions')
            ->where('created_at', '>=', $startDate)
            ->where('was_helpful', true)
            ->count();

        return round(($positive / $totalWithFeedback) * 100, 1);
    }

    /**
     * Obtener tasa de respuesta rápida
     */
    private function getQuickResponseRate(\Carbon\Carbon $startDate): float
    {
        $total = DB::table('chat_interactions')
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('response_time')
            ->count();

        if ($total === 0) return 0;

        $quick = DB::table('chat_interactions')
            ->where('created_at', '>=', $startDate)
            ->where('response_time', '<=', 2000) // Menos de 2 segundos
            ->count();

        return round(($quick / $total) * 100, 1);
    }
    /**
     * Endpoint para departamentos con información mejorada
     */
    public function enhancedDepartments(): JsonResponse
    {
        $departments = DB::table('departments')
            ->where('is_active', true)
            ->select(['code', 'name', 'short_name', 'type', 'contact_phone', 'contact_email', 'services', 'location', 'schedule'])
            ->get()
            ->map(function ($dept) {
                if ($dept->services) {
                    $dept->services = json_decode($dept->services);
                }
                return $dept;
            });

        return response()->json([
            'success' => true,
            'data' => $departments,
            'meta' => [
                'total_departments' => $departments->count(),
                'updated_at' => now()->toISOString()
            ]
        ]);
    }

    // =================================================================
    // MÉTODOS AUXILIARES PARA MÉTRICAS
    // =================================================================

    private function checkDatabaseHealth(): bool
    {
        try {
            DB::getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkCacheHealth(): bool
    {
        try {
            Cache::put('health_check', 'ok', 10);
            return Cache::get('health_check') === 'ok';
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getTodayInteractionsCount(): int
    {
        return DB::table('chat_interactions')
            ->whereDate('created_at', today())
            ->count();
    }

    private function getAverageConfidence24h(): float
    {
        $avg = DB::table('chat_interactions')
            ->where('created_at', '>=', now()->subHours(24))
            ->avg('confidence');

        return round($avg ?? 0, 2);
    }

    private function getEscalationRate24h(): float
    {
        $total = DB::table('chat_interactions')
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        if ($total === 0) return 0;

        $escalated = DB::table('chat_interactions')
            ->where('created_at', '>=', now()->subHours(24))
            ->where('requires_human_follow_up', true)
            ->count();

        return round(($escalated / $total) * 100, 1);
    }

    private function getAverageResponseTime24h(): float
    {
        $avg = DB::table('chat_interactions')
            ->where('created_at', '>=', now()->subHours(24))
            ->avg('response_time');

        return round($avg ?? 0, 0);
    }

    /**
     * Procesar feedback para mejoras continuas
     */
    private function processFeedbackForImprovements(array $feedback): void
    {
        try {
            // Guardar para análisis de tendencias
            DB::table('feedback_analytics')->insert([
                'feedback_type' => $feedback['feedback_type'],
                'rating' => $feedback['rating'],
                'was_helpful' => $feedback['was_helpful'],
                'comment_sentiment' => $this->analyzeSentiment($feedback['feedback_comment'] ?? ''),
                'improvement_category' => $this->categorizeFeedback($feedback['feedback_comment'] ?? ''),
                'created_at' => now()
            ]);

            // Alertas automáticas para feedback muy negativo
            if ($feedback['rating'] <= 2) {
                $this->triggerLowRatingAlert($feedback);
            }

        } catch (\Exception $e) {
            Log::error('Error processing feedback improvements: ' . $e->getMessage());
        }
    }

    /**
     * Categorizar feedback para mejoras
     */
    private function categorizeFeedback(string $comment): string
    {
        $comment = strtolower($comment);

        if (str_contains($comment, 'información incompleta') || str_contains($comment, 'falta')) {
            return 'completeness';
        }

        if (str_contains($comment, 'no entiendo') || str_contains($comment, 'confuso')) {
            return 'clarity';
        }

        if (str_contains($comment, 'lento') || str_contains($comment, 'tardó')) {
            return 'performance';
        }

        if (str_contains($comment, 'incorrecto') || str_contains($comment, 'error')) {
            return 'accuracy';
        }

        return 'general';
    }

    /**
     * Trigger para alertas de rating bajo
     */
    private function triggerLowRatingAlert(array $feedback): void
    {
        // En un entorno real, esto enviaría notificaciones
        Log::warning('Low rating feedback received', [
            'rating' => $feedback['rating'],
            'comment' => $feedback['feedback_comment'] ?? '',
            'session_id' => $feedback['session_id']
        ]);

        // Marcar para revisión humana
        Cache::put("low_rating_review_{$feedback['session_id']}", $feedback, 86400);
    }
}
