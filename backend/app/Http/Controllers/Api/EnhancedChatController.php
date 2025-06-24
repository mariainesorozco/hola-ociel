<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OllamaService;
use App\Services\KnowledgeBaseService;
use App\Services\EnhancedPromptService;
use App\Services\GhostIntegrationService;
use App\Services\EnhancedQdrantVectorService;
use App\Services\GeminiService;
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
    private $qdrantService;
    private $geminiService;

    public function __construct(
        OllamaService $ollamaService,
        KnowledgeBaseService $knowledgeService,
        EnhancedPromptService $promptService,
        GhostIntegrationService $ghostService,
        EnhancedQdrantVectorService $qdrantService,
        GeminiService $geminiService
    ) {
        $this->ollamaService = $ollamaService;
        $this->knowledgeService = $knowledgeService;
        $this->promptService = $promptService;
        $this->ghostService = $ghostService;
        $this->qdrantService = $qdrantService;
        $this->geminiService = $geminiService;
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

            // 8. RESPUESTA SIMPLIFICADA
            return response()->json([
                'success' => true,
                'response' => $response['response'] ?? '',
                'session_id' => $sessionId,
                'confidence' => $qualityCheck['overall_confidence']
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
        // Cache basado en hash del mensaje
        $cacheKey = 'context_' . md5(strtolower($message) . $userType . ($department ?? ''));

        if (Cache::has($cacheKey)) {
            Log::debug('Using cached context', ['cache_key' => $cacheKey]);
            return Cache::get($cacheKey);
        }

        Log::info('=== INICIANDO BÚSQUEDA DE CONTEXTO ===', [
            'message' => $message,
            'user_type' => $userType,
            'department' => $department
        ]);

        $context = [];

        try {
            // BÚSQUEDA EXCLUSIVAMENTE EN BASE DE DATOS VECTORIAL QDRANT
            try {
                // Determinar filtros basados en user type y department
                $filters = [];
                if ($userType) {
                    $filters['user_type'] = $userType;
                }
                if ($department) {
                    $filters['department'] = $department;
                }

                Log::info('🔍 Iniciando búsqueda en Qdrant (Notion)', [
                    'query' => $message,
                    'user_type' => $userType,
                    'department' => $department,
                    'filters' => $filters
                ]);

                // Usar búsqueda exclusiva de Notion con threshold adecuado
                $qdrantResults = $this->qdrantService->searchNotionServices($message, $filters, 5, 0.65);

                Log::info('Resultados búsqueda Qdrant (Notion):', [
                    'count' => count($qdrantResults),
                    'first_result_preview' => !empty($qdrantResults) ? (is_array($qdrantResults[0]) ? ($qdrantResults[0]['title'] ?? 'Sin título') : substr($qdrantResults[0], 0, 200)) : 'ninguno'
                ]);

                if (!empty($qdrantResults)) {
                    // PROCESAR RESULTADOS DE NOTION DESDE QDRANT
                    $cleanedResults = [];
                    foreach ($qdrantResults as $result) {
                        if (is_array($result)) {
                            // Resultado estructurado de Notion
                            $contextText = '';
                            if (!empty($result['title'])) {
                                $contextText .= $result['title'] . "\n\n";
                            }
                            if (!empty($result['content_preview'])) {
                                $contextText .= $result['content_preview'] . "\n";
                            }
                            if (!empty($result['modalidad'])) {
                                $contextText .= "Modalidad: " . $result['modalidad'] . "\n";
                            }
                            if (!empty($result['usuarios'])) {
                                $contextText .= "Para: " . $result['usuarios'] . "\n";
                            }
                            if (!empty($result['dependencia'])) {
                                $contextText .= "Dependencia: " . $result['dependencia'] . "\n";
                            }
                            
                            $contextText = trim($contextText);
                            if (!empty($contextText) && strlen($contextText) > 20) {
                                $cleanedResults[] = $contextText;
                            }
                        } else {
                            // Resultado de texto plano (fallback)
                            $clean = preg_replace('/📋\s*Información encontrada:\s*/i', '', $result);
                            $clean = preg_replace('/^#{1,6}\s*(.+)$/m', '$1', $clean);
                            $clean = preg_replace('/^\*\*([^*]+)\*\*:\s*/m', '$1: ', $clean);
                            $clean = preg_replace('/### (.+)/i', '$1', $clean);
                            $clean = trim($clean);
                            if (!empty($clean) && strlen($clean) > 20) {
                                $cleanedResults[] = $clean;
                            }
                        }
                    }
                    $context = array_merge($context, $cleanedResults);
                    
                    Log::info('✅ Contexto procesado desde Qdrant (Notion)', [
                        'cleaned_results_count' => count($cleanedResults),
                        'total_context_size' => array_sum(array_map('strlen', $cleanedResults))
                    ]);
                } else {
                    Log::warning('❌ NO SE ENCONTRARON RESULTADOS EN QDRANT (Notion)');
                    return $this->getEmergencyContext($message);
                }
            } catch (\Exception $e) {
                Log::error('Error en búsqueda Qdrant: ' . $e->getMessage());
                return $this->getEmergencyContext($message);
            }

            // 7. PROCESAR Y LIMITAR resultados
            $finalContext = $this->processContextResults($context);

            Log::info('=== CONTEXTO FINAL ===', [
                'total_items' => count($finalContext),
                'message' => $message,
                'preview' => !empty($finalContext) ? substr($finalContext[0], 0, 50) . '...' : 'vacío'
            ]);

            // Cache por 5 minutos
            Cache::put($cacheKey, $finalContext, 300);

            return $finalContext;

        } catch (\Exception $e) {
            Log::error('❌ ERROR CRÍTICO en getIntelligentContext: ' . $e->getMessage(), [
                'message' => $message,
                'stack_trace' => $e->getTraceAsString()
            ]);

            return $this->getEmergencyContext($message);
        }
    }




    /**
     * MÉTODO NUEVO: Contexto de emergencia cuando todo falla - SOLO NOTION
     */
    private function getEmergencyContext(string $message): array
    {
        Log::warning('🚨 NO SE ENCONTRÓ INFORMACIÓN ESPECÍFICA EN NOTION');

        return [
            "No pude encontrar información específica sobre tu consulta en mi base de conocimientos de Notion.

Para obtener información actualizada, te recomiendo revisar directamente los servicios disponibles en el sistema de gestión institucional."
        ];
    }

    /**
     * MÉTODO NUEVO: Contexto genérico de ayuda - SOLO NOTION
     */
    private function getGenericHelpContext(): array
    {
        return [
            "Estoy configurado para proporcionarte información específica de los servicios registrados en el sistema de gestión institucional.

Si no encuentro información específica sobre tu consulta, es posible que el servicio no esté registrado en el sistema o necesites contactar directamente con el departamento correspondiente."
        ];
    }

    /**
     * MÉTODO NUEVO: Procesar resultados del contexto
     */
    private function processContextResults(array $context): array
    {
        // Eliminar duplicados
        $unique = array_unique($context);

        // Filtrar contenido muy corto
        $filtered = array_filter($unique, fn($item) => strlen(trim($item)) > 50);

        // Limitar a máximo 5 elementos
        $limited = array_slice($filtered, 0, 5);

        // Si no hay suficiente contenido, agregar ayuda genérica
        if (count($limited) < 2) {
            $limited = array_merge($limited, $this->getGenericHelpContext());
        }

        return array_values($limited);
    }

    // Eliminado: searchByPatterns - ahora usamos solo Qdrant con contenido Notion

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

    // Eliminado: getSpecificContext - ahora usamos solo Qdrant con contenido Notion
    private function getSpecificContext(string $message, string $queryType): array
    {
        // Ya no usamos búsquedas específicas por categoría en MySQL
        // Todo el contexto viene de Qdrant con contenido Notion
        return [];
    }

    /**
     * Obtener respuesta template de alta calidad
     */
    private function getTemplateResponse(string $message, string $queryType, string $userType): array
    {
        // RESPUESTAS BASADAS ÚNICAMENTE EN INFORMACIÓN DE NOTION
        $template = [
            'response' => "👋 Hola, soy Ociel.\n\nEstoy configurado para proporcionarte información específica de los servicios registrados en nuestro sistema de gestión institucional.\n\n🔍 **Puedo ayudarte con:**\n• Información sobre servicios específicos registrados\n• Procedimientos detallados de trámites\n• Contactos oficiales de departamentos\n\nSi no encuentro información específica sobre tu consulta, es porque el servicio no está registrado en mi base de conocimientos.\n\n¿Sobre qué servicio específico necesitas información?",
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
            // Generar respuesta conversacional sin formato markdown
            $contextText = strip_tags($context[0]);
            $contextText = preg_replace('/📋\s*Información encontrada:\s*/i', '', $contextText);
            $contextText = preg_replace('/^#{1,6}\s*(.+)$/m', '$1', $contextText);
            $contextText = preg_replace('/^\*\*([^*]+)\*\*:\s*/m', '', $contextText);
            $contextText = preg_replace('/### (.+)/i', '', $contextText);
            
            $response = "¡Hola! 🐯 Encontré información sobre tu consulta. " . 
                       trim(substr($contextText, 0, 250)) . 
                       "... ¿Te gustaría que profundice en algún aspecto específico? Estoy aquí para apoyarte 🐾";

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

        // Información de contacto bien formateada (si viene de Notion)
        if (preg_match('/📞.*\d{3}-\d{3}-\d{4}/', $response)) $score += 0.2;

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
        // EXTRAER INFORMACIÓN DE CONTACTO COMPLETA DE NOTION
        foreach ($context as $content) {
            // Buscar la sección de contacto en el contenido markdown
            $contactInfo = $this->extractContactFromMarkdown($content);
            if (!empty($contactInfo)) {
                return [
                    'primary' => $contactInfo,
                    'source' => 'notion_content'
                ];
            }
        }

        // Si no hay información específica de contacto en Notion, no mostrar contactos genéricos
        return [
            'note' => 'Información de contacto específica no disponible en el registro del servicio'
        ];
    }

    /**
     * Extraer información de contacto completa de servicios de Notion
     */
    private function extractContactFromMarkdown(string $content): array
    {
        $contactInfo = [];
        
        // Primero extraer metadatos del header del servicio
        $serviceInfo = $this->extractServiceMetadata($content);
        
        // Buscar la sección ### Contacto
        if (preg_match('/###\s*Contacto\s*\n(.*?)(?=\n###|\n\n###|$)/is', $content, $contactSection)) {
            $contactText = $contactSection[1];
            
            // Extraer teléfono
            if (preg_match('/\*\*Teléfono:\*\*\s*(.+?)(?:\n|$)/i', $contactText, $phoneMatch)) {
                $contactInfo['phone'] = trim($phoneMatch[1]);
            }
            
            // Extraer correo
            if (preg_match('/\*\*Correo:\*\*\s*(.+?)(?:\n|$)/i', $contactText, $emailMatch)) {
                $contactInfo['email'] = trim($emailMatch[1]);
            }
            
            // Extraer ubicación
            if (preg_match('/\*\*Ubicación:\*\*\s*(.+?)(?:\n|$)/i', $contactText, $locationMatch)) {
                $contactInfo['location'] = trim($locationMatch[1]);
            }
            
            // Extraer horarios
            if (preg_match('/\*\*Horarios:\*\*\s*(.+?)(?:\n|$)/i', $contactText, $scheduleMatch)) {
                $contactInfo['schedule'] = trim($scheduleMatch[1]);
            }
        }
        
        // Si encontramos al menos teléfono o correo, devolver la información
        if (isset($contactInfo['phone']) || isset($contactInfo['email'])) {
            return [
                'name' => 'Contacto para ' . ($serviceInfo['categoria'] ?? 'servicio'),
                'service_info' => $serviceInfo,
                'details' => $contactInfo
            ];
        }
        
        return [];
    }

    /**
     * Extraer metadatos del servicio de Notion
     */
    private function extractServiceMetadata(string $content): array
    {
        $metadata = [];
        
        // Extraer líneas de metadatos después del título
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^(Categoria|Costo|Dependencia|Estado|ID_Servicio|Modalidad|Subcategoria|Usuarios):\s*(.+)$/i', $line, $matches)) {
                $key = strtolower($matches[1]);
                $value = trim($matches[2]);
                $metadata[$key] = $value;
            }
        }
        
        return $metadata;
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

        // Temas relacionados basados en servicios de Notion disponibles
        $serviceTopics = [
            'tramite_especifico' => [
                'Solicitud de Constancias Académicas',
                'Solicitud de Cuenta de Correo Institucional',
                'Registro al EXANI III',
                'Digitalización de Documentos'
            ],
            'informacion_carrera' => [
                'Creación de Programas Académicos',
                'Registro de Programa de Posgrado',
                'Asesoría para Evaluación de Programas',
                'Becas SECIHTI'
            ],
            'soporte_tecnico' => [
                'Activación de Correo Institucional',
                'Solicitud de Microsoft 365',
                'Solicitud de Licencia Canva Pro',
                'Orden de Servicio Técnico'
            ],
            'servicios_tecnologicos' => [
                'Cuenta Microsoft 365 for Educación',
                'Licencia Autodesk para Educación',
                'Licencia Affinity Educación',
                'Correo Electrónico Institucional'
            ]
        ];

        $topics = $serviceTopics[$queryType] ?? [
            'Servicios Académicos',
            'Servicios Tecnológicos', 
            'Servicios Administrativos',
            'Trámites Institucionales'
        ];

        // Convertir a formato estructurado con consultas específicas
        foreach ($topics as $topic) {
            $relatedTopics[] = [
                'title' => $topic,
                'query_suggestion' => strtolower($topic),
                'relevance' => 'high'
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
     * Manejo de errores de chat simplificado
     */
    private function handleChatError(\Exception $e, string $requestId, string $message, string $userType, float $startTime): JsonResponse
    {
        Log::error('Chat error occurred', [
            'request_id' => $requestId,
            'error' => $e->getMessage(),
            'message' => substr($message, 0, 100),
            'user_type' => $userType
        ]);

        $emergencyResponse = "Disculpa, estoy teniendo dificultades técnicas. Por favor intenta de nuevo en un momento.";

        return response()->json([
            'success' => false,
            'error' => 'Error temporal del sistema',
            'response' => $emergencyResponse
        ], 500);
    }

    // =================================================================
    // MÉTODOS AUXILIARES PARA VALIDACIÓN DE CALIDAD
    // =================================================================

    private function containsContactInfo(string $text): bool
    {
        return preg_match('/\b\d{3}-\d{3}-\d{4}\b|\b\w+@\w+\.edu\.mx\b|ext\.\s*\d+/i', $text);
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
        // Verificar que la información institucional venga de Notion, no hardcodeada
        $notionInfo = [
            'Universidad Autónoma de Nayarit' => true,
            'UAN' => true,
            'uan.edu.mx' => true
        ];

        foreach ($notionInfo as $info => $expected) {
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
                'gemini' => $this->geminiService->isHealthy(),
                'qdrant' => $this->qdrantService->isHealthy(),
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
                'qdrant_healthy' => $this->qdrantService->isHealthy(),
                'vector_search_enabled' => true
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
