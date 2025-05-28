<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OllamaService;
use App\Services\KnowledgeBaseService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Exception;

class ChatController extends Controller
{
    private $ollamaService;
    private $knowledgeService;

    public function __construct(OllamaService $ollamaService, KnowledgeBaseService $knowledgeService)
    {
        $this->ollamaService = $ollamaService;
        $this->knowledgeService = $knowledgeService;
    }

    /**
     * Endpoint principal de chat con Ociel - VERSIÓN MEJORADA Y UNIFICADA
     */
    public function chat(Request $request): JsonResponse
    {
        $startTime = microtime(true);

        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:1000',
            'user_type' => 'in:student,employee,public',
            'department' => 'nullable|string|max:100',
            'session_id' => 'nullable|string|max:100',
            'user_identification' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Datos inválidos',
                'details' => $validator->errors()
            ], 400);
        }

        $validated = $validator->validated();
        $message = trim($validated['message']);
        $userType = $validated['user_type'] ?? 'public';
        $department = $validated['department'] ?? null;
        $sessionId = $validated['session_id'] ?? Str::uuid();
        $userIdentification = $validated['user_identification'] ?? null;

        try {
            // 1. VALIDAR ENTRADA Y DETECTAR SPAM
            if (!$this->isValidMessage($message)) {
                return $this->createErrorResponse('Mensaje no válido', $sessionId, $startTime);
            }

            // 2. BÚSQUEDA INTELIGENTE EN KNOWLEDGE BASE
            $context = $this->knowledgeService->searchRelevantContent($message, $userType, $department);

            // 3. ENRIQUECER CONTEXTO CON INFORMACIÓN RELACIONADA
            $enhancedContext = $this->enrichContext($context, $message, $userType, $department);

            // 4. GENERAR RESPUESTA CON IA USANDO CONTEXTO MEJORADO
            $aiResponse = $this->generateIntelligentResponse($message, $enhancedContext, $userType, $department);

            // 5. PROCESAR Y VALIDAR RESPUESTA
            $processedResponse = $this->processAIResponse($aiResponse, $message, $enhancedContext);

            // 6. CALCULAR MÉTRICAS DE CALIDAD
            $confidence = $this->calculateResponseConfidence($enhancedContext, $processedResponse, $message);
            $relevanceScore = $this->calculateRelevanceScore($message, $enhancedContext);

            // 7. DETERMINAR ACCIONES RECOMENDADAS
            $requiresHumanFollowUp = $this->shouldEscalateToHuman($message, $confidence, $enhancedContext);
            $suggestedActions = $this->getSuggestedActions($message, $department, $enhancedContext);
            $contactInfo = $this->getRelevantContactInfo($department, $enhancedContext, $message);

            $responseTime = round((microtime(true) - $startTime) * 1000);

            // 8. REGISTRAR INTERACCIÓN COMPLETA
            $this->logChatInteraction([
                'session_id' => $sessionId,
                'user_type' => $userType,
                'department' => $department,
                'user_identification' => $userIdentification,
                'message' => $message,
                'response' => $processedResponse['response'],
                'confidence' => $confidence,
                'model_used' => $processedResponse['model'],
                'response_time' => $responseTime,
                'ip_address' => $request->ip(),
                'channel' => 'web',
                'context_used' => json_encode(array_slice($enhancedContext, 0, 3)),
                'requires_human_follow_up' => $requiresHumanFollowUp
            ]);

            // 9. RESPUESTA ESTRUCTURADA
            return response()->json([
                'success' => true,
                'data' => [
                    'response' => $processedResponse['response'],
                    'session_id' => $sessionId,
                    'confidence' => $confidence,
                    'relevance_score' => $relevanceScore,
                    'model_used' => $processedResponse['model'],
                    'response_time' => $responseTime,
                    'requires_human_follow_up' => $requiresHumanFollowUp,
                    'contact_info' => $contactInfo,
                    'suggested_actions' => $suggestedActions,
                    'context_sources' => count($enhancedContext),
                    'follow_up_questions' => $this->generateFollowUpQuestions($message, $enhancedContext)
                ],
                'metadata' => [
                    'timestamp' => now()->toISOString(),
                    'version' => '1.2.0',
                    'processing_stats' => [
                        'context_search_ms' => round($responseTime * 0.2),
                        'ai_generation_ms' => round($responseTime * 0.6),
                        'post_processing_ms' => round($responseTime * 0.2)
                    ]
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Chat error: ' . $e->getMessage(), [
                'message' => $message,
                'user_type' => $userType,
                'session_id' => $sessionId,
                'stack' => $e->getTraceAsString()
            ]);

            // FALLBACK INTELIGENTE
            return $this->createFallbackResponse($message, $sessionId, $startTime, $e);
        }
    }

    /**
     * GENERAR RESPUESTA INTELIGENTE CON IA + CONTEXTO MEJORADO
     */
    private function generateIntelligentResponse(string $message, array $context, string $userType, ?string $department): array
    {
        // Verificar disponibilidad de Ollama
        if (!$this->ollamaService->isHealthy()) {
            throw new \Exception('Servicio de IA no disponible');
        }

        // Estrategia de respuesta basada en contexto
        if (empty($context)) {
            // Sin contexto específico - usar conocimiento general UAN
            $generalContext = $this->getGeneralUANContext($userType, $department);
            return $this->ollamaService->generateOcielResponse($message, $generalContext, $userType, $department);
        }

        // Con contexto específico - generar respuesta contextualizada
        $response = $this->ollamaService->generateOcielResponse($message, $context, $userType, $department);

        // Validar calidad de respuesta
        if (!$this->isResponseValid($response)) {
            // Reintentar con contexto simplificado
            $simplifiedContext = array_slice($context, 0, 2);
            return $this->ollamaService->generateOcielResponse($message, $simplifiedContext, $userType, $department);
        }

        return $response;
    }

    /**
     * ENRIQUECER CONTEXTO CON INFORMACIÓN RELACIONADA
     */
    private function enrichContext(array $baseContext, string $message, string $userType, ?string $department): array
    {
        $enrichedContext = $baseContext;

        // Agregar contexto departamental si es relevante
        if ($department && count($enrichedContext) < 3) {
            $departmentContext = $this->knowledgeService->getContentByDepartment($department, $userType);
            $enrichedContext = array_merge($enrichedContext, $departmentContext->take(2)->pluck('content')->toArray());
        }

        // Agregar preguntas frecuentes relacionadas
        if (count($enrichedContext) < 3) {
            $faqContext = $this->knowledgeService->searchFrequentQuestions($message);
            $enrichedContext = array_merge($enrichedContext, $faqContext->take(1)->pluck('content')->toArray());
        }

        // Filtrar y optimizar contexto
        return $this->optimizeContext($enrichedContext, $message);
    }

    /**
     * OPTIMIZAR CONTEXTO PARA MEJOR RENDIMIENTO
     */
    private function optimizeContext(array $context, string $message): array
    {
        // Remover duplicados
        $context = array_unique($context);

        // Ordenar por relevancia usando palabras clave
        $messageLower = strtolower($message);
        $scored = [];

        foreach ($context as $item) {
            $score = $this->calculateContextRelevance($messageLower, strtolower($item));
            $scored[] = ['content' => $item, 'score' => $score];
        }

        // Ordenar por score y tomar los mejores
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice(array_column($scored, 'content'), 0, 3);
    }

    /**
     * CALCULAR RELEVANCIA DE CONTEXTO
     */
    private function calculateContextRelevance(string $message, string $context): float
    {
        $messageWords = explode(' ', $message);
        $contextWords = explode(' ', $context);

        $intersection = array_intersect($messageWords, $contextWords);
        $union = array_unique(array_merge($messageWords, $contextWords));

        return count($intersection) / count($union); // Jaccard similarity
    }

    /**
     * PROCESAR RESPUESTA DE IA
     */
    private function processAIResponse(array $aiResponse, string $originalMessage, array $context): array
    {
        if (!isset($aiResponse['success']) || !$aiResponse['success']) {
            throw new \Exception('Error en generación de respuesta IA');
        }

        $response = $aiResponse['response'] ?? '';

        // Post-procesamiento de respuesta
        $response = $this->enhanceResponse($response, $originalMessage, $context);

        return [
            'response' => $response,
            'model' => $aiResponse['model'] ?? 'unknown',
            'tokens_used' => $aiResponse['tokens_generated'] ?? 0,
            'processing_time' => $aiResponse['response_time'] ?? 0
        ];
    }

    /**
     * MEJORAR RESPUESTA CON INFORMACIÓN ADICIONAL
     */
    private function enhanceResponse(string $response, string $originalMessage, array $context): string
    {
        // Agregar información de contacto si es relevante
        if ($this->needsContactInfo($originalMessage)) {
            $response .= "\n\n📞 Para más información, contacta al 311-211-8800 o visita https://www.uan.edu.mx";
        }

        // Agregar disclaimer si la confianza es baja
        if (empty($context)) {
            $response .= "\n\n💡 Te recomiendo verificar esta información contactando directamente a la dependencia correspondiente.";
        }

        return trim($response);
    }

    /**
     * CALCULAR CONFIANZA MEJORADA DE LA RESPUESTA
     */
    private function calculateResponseConfidence(array $context, array $processedResponse, string $originalMessage): float
    {
        $confidence = 0.3; // Base mínima

        // Factor 1: Cantidad y calidad del contexto (0-0.4)
        $contextScore = min(count($context) / 3, 1) * 0.4;
        $confidence += $contextScore;

        // Factor 2: Éxito de la IA (0-0.2)
        if (isset($processedResponse['response']) && strlen($processedResponse['response']) > 50) {
            $confidence += 0.2;
        }

        // Factor 3: Relevancia del mensaje (0-0.2)
        $relevanceBonus = $this->calculateMessageRelevance($originalMessage) * 0.2;
        $confidence += $relevanceBonus;

        // Factor 4: Tiempo de respuesta (0-0.1)
        $responseTime = $processedResponse['processing_time'] ?? 0;
        if ($responseTime < 2000) { // Menos de 2 segundos
            $confidence += 0.1;
        }

        // Factor 5: Penalizaciones
        if (strlen($processedResponse['response'] ?? '') < 30) {
            $confidence -= 0.2; // Respuesta muy corta
        }

        return max(0.0, min(1.0, $confidence));
    }

    /**
     * CALCULAR RELEVANCIA DEL MENSAJE
     */
    private function calculateMessageRelevance(string $message): float
    {
        $relevantKeywords = [
            'inscripción', 'carrera', 'licenciatura', 'maestría', 'doctorado',
            'trámite', 'servicio', 'biblioteca', 'sistema', 'admisión',
            'titulación', 'universidad', 'UAN', 'contacto', 'información'
        ];

        $messageLower = strtolower($message);
        $found = 0;

        foreach ($relevantKeywords as $keyword) {
            if (str_contains($messageLower, $keyword)) {
                $found++;
            }
        }

        return min($found / 5, 1.0); // Normalizar a 0-1
    }

    /**
     * CALCULAR SCORE DE RELEVANCIA
     */
    private function calculateRelevanceScore(string $message, array $context): float
    {
        if (empty($context)) {
            return 0.2;
        }

        $totalRelevance = 0;
        foreach ($context as $item) {
            $totalRelevance += $this->calculateContextRelevance(strtolower($message), strtolower($item));
        }

        return min($totalRelevance / count($context), 1.0);
    }

    /**
     * DETERMINAR SI REQUIERE ESCALACIÓN HUMANA - MEJORADO
     */
    private function shouldEscalateToHuman(string $message, float $confidence, array $context): bool
    {
        // Confianza muy baja
        if ($confidence < 0.4) {
            return true;
        }

        // Palabras clave de escalación
        $escalationKeywords = [
            'queja', 'problema', 'error', 'falla', 'reclamo', 'molesto', 'enojado',
            'director', 'rector', 'secretario', 'urgente', 'emergencia', 'ayuda',
            'demanda', 'legal', 'abogado', 'tribunal', 'denuncia', 'inconformidad'
        ];

        $messageLower = strtolower($message);
        foreach ($escalationKeywords as $keyword) {
            if (str_contains($messageLower, $keyword)) {
                return true;
            }
        }

        // Mensajes muy largos sin contexto relevante
        if (strlen($message) > 200 && empty($context)) {
            return true;
        }

        // Preguntas muy específicas que requieren conocimiento especializado
        $specializedKeywords = ['revalidación', 'equivalencia', 'transferencia', 'beca específica'];
        foreach ($specializedKeywords as $keyword) {
            if (str_contains($messageLower, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * OBTENER ACCIONES SUGERIDAS MEJORADAS
     */
    private function getSuggestedActions(string $message, ?string $department, array $context): array
    {
        $actions = [];
        $messageLower = strtolower($message);

        // Acciones específicas por categoría
        if (str_contains($messageLower, 'inscripción') || str_contains($messageLower, 'admisión')) {
            $actions = [
                'Revisar requisitos de admisión en el sitio web oficial',
                'Contactar a SA para información específica sobre tu situación',
                'Verificar fechas de convocatoria vigentes',
                'Preparar documentación requerida',
                'Consultar el proceso de examen de admisión'
            ];
        } elseif (str_contains($messageLower, 'carrera') || str_contains($messageLower, 'licenciatura')) {
            $actions = [
                'Explorar la oferta educativa completa de la UAN',
                'Solicitar orientación vocacional',
                'Visitar la unidad académica de tu interés',
                'Conocer el perfil de egreso de la carrera',
                'Consultar el plan de estudios actualizado'
            ];
        } elseif (str_contains($messageLower, 'sistema') || str_contains($messageLower, 'plataforma')) {
            $actions = [
                'Contactar a la Dirección General de Sistemas',
                'Verificar tus credenciales de acceso',
                'Consultar las guías de usuario disponibles',
                'Reportar el problema técnico específico',
                'Solicitar capacitación si es necesario'
            ];
        } elseif (str_contains($messageLower, 'biblioteca')) {
            $actions = [
                'Consultar el catálogo en línea',
                'Reservar espacios de estudio',
                'Solicitar apoyo para búsqueda bibliográfica',
                'Conocer los horarios de atención',
                'Activar tu cuenta de biblioteca digital'
            ];
        } else {
            // Acciones generales
            $actions = [
                'Contactar directamente a la dependencia correspondiente',
                'Visitar el sitio web oficial de la UAN',
                'Consultar el directorio institucional',
                'Programar una cita presencial si es necesario'
            ];
        }

        return array_slice($actions, 0, 3); // Máximo 3 acciones
    }

    /**
     * GENERAR PREGUNTAS DE SEGUIMIENTO
     */
    private function generateFollowUpQuestions(string $message, array $context): array
    {
        $messageLower = strtolower($message);
        $questions = [];

        if (str_contains($messageLower, 'carrera')) {
            $questions = [
                "¿Te interesa conocer los requisitos de ingreso?",
                "¿Quieres información sobre el perfil profesional?",
                "¿Necesitas detalles sobre el plan de estudios?"
            ];
        } elseif (str_contains($messageLower, 'inscripción')) {
            $questions = [
                "¿Ya presentaste el examen de admisión?",
                "¿Necesitas información sobre documentos requeridos?",
                "¿Quieres conocer las fechas importantes del proceso?"
            ];
        } elseif (str_contains($messageLower, 'trámite')) {
            $questions = [
                "¿Es tu primera vez realizando este trámite?",
                "¿Necesitas información sobre costos?",
                "¿Quieres conocer los tiempos de respuesta?"
            ];
        }

        return array_slice($questions, 0, 2); // Máximo 2 preguntas
    }

    /**
     * OBTENER INFORMACIÓN DE CONTACTO RELEVANTE MEJORADA
     */
    private function getRelevantContactInfo(?string $department, array $context, string $message): array
    {
        // Contacto específico por departamento
        if ($department) {
            $dept = DB::table('departments')
                ->where('code', $department)
                ->where('is_active', true)
                ->first(['contact_phone', 'contact_email', 'location', 'schedule']);

            if ($dept) {
                return [
                    'phone' => $dept->contact_phone,
                    'email' => $dept->contact_email,
                    'location' => $dept->location,
                    'schedule' => $dept->schedule,
                    'type' => 'departmental'
                ];
            }
        }

        // Contacto específico por tipo de consulta
        $messageLower = strtolower($message);

        if (str_contains($messageLower, 'inscripción') || str_contains($messageLower, 'admisión')) {
            return [
                'phone' => '311-211-8800 ext. 8803',
                'email' => 'academica@uan.edu.mx',
                'location' => 'Edificio PiiDA',
                'schedule' => 'Lunes a Viernes de 8:00 a 20:00 hrs',
                'department' => 'SA - Secretaría Académica',
                'type' => 'specific'
            ];
        }

        if (str_contains($messageLower, 'sistema') || str_contains($messageLower, 'técnico')) {
            return [
                'phone' => '311-211-8800 ext. 8640',
                'email' => 'dgs@uan.edu.mx',
                'location' => 'Edificio de Finanzas, 2do piso',
                'schedule' => 'Lunes a Viernes de 8:00 a 20:00 hrs',
                'department' => 'DGS - Dirección General de Sistemas',
                'type' => 'specific'
            ];
        }

        // Información general por defecto
        return [
            'phone' => '311-211-8800',
            'email' => 'contacto@uan.edu.mx',
            'website' => 'https://www.uan.edu.mx',
            'location' => 'Ciudad de la Cultura "Amado Nervo", Tepic, Nayarit',
            'type' => 'general'
        ];
    }

    /**
     * OBTENER CONTEXTO GENERAL DE LA UAN MEJORADO
     */
    private function getGeneralUANContext(string $userType, ?string $department): array
    {
        $baseContext = [
            "La Universidad Autónoma de Nayarit (UAN) es una institución pública de educación superior fundada el 25 de abril de 1969, ubicada en la Ciudad de la Cultura 'Amado Nervo' en Tepic, Nayarit, México.",
            "Ofrece más de 40 programas de licenciatura, 25 maestrías y 8 doctorados organizados en cuatro áreas del conocimiento: Artes, Ciencias Básicas e Ingenierías, Ciencias Sociales y Humanidades, Ciencias de la Salud, Ciencias Económicas y Administrativas y Ciencias Biológico Agropecuarias y Pesqueras.",
        ];

        // Contexto específico por tipo de usuario
        if ($userType === 'student') {
            $baseContext[] = "Como estudiante de la UAN tienes acceso a servicios de biblioteca, laboratorios, centro de cómputo, servicios médicos, actividades culturales y deportivas.";
            $baseContext[] = "Para trámites académicos contacta a la SA al 311-211-8800 ext. 8803.";
        } elseif ($userType === 'employee') {
            $baseContext[] = "Como empleado universitario tienes acceso a servicios institucionales, capacitación, desarrollo profesional y beneficios laborales.";
            $baseContext[] = "Para consultas administrativas contacta a la Secretaría de Administración al 311-211-8800 ext. 8900.";
        } else {
            $baseContext[] = "Ofrecemos información sobre admisión, oferta educativa, servicios públicos y eventos institucionales.";
            $baseContext[] = "Contacto principal: 311-211-8800. Sitio web: https://www.uan.edu.mx";
        }

        return $baseContext;
    }

    /**
     * CREAR RESPUESTA DE ERROR ESTRUCTURADA
     */
    private function createErrorResponse(string $error, string $sessionId, float $startTime): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => $error,
            'data' => [
                'session_id' => $sessionId,
                'response' => "Lo siento, hubo un problema procesando tu mensaje. Por favor, intenta reformular tu pregunta o contacta directamente al 311-211-8800.",
                'confidence' => 0.0,
                'requires_human_follow_up' => true,
                'response_time' => round((microtime(true) - $startTime) * 1000)
            ]
        ], 400);
    }

    /**
     * CREAR RESPUESTA FALLBACK INTELIGENTE
     */
    private function createFallbackResponse(string $message, string $sessionId, float $startTime, \Exception $e): JsonResponse
    {
        // Intentar búsqueda simple en base de conocimientos
        $fallbackContext = $this->searchKnowledgeSimple($message, 'public', null);
        $fallbackResponse = $this->generateSimpleResponse($message, $fallbackContext, 'public');

        // Log del error para debugging
        Log::warning('Fallback response used', [
            'original_error' => $e->getMessage(),
            'message' => $message,
            'session_id' => $sessionId
        ]);

        return response()->json([
            'success' => false,
            'error' => 'Servicio temporalmente limitado',
            'data' => [
                'response' => $fallbackResponse,
                'session_id' => $sessionId,
                'confidence' => 0.4,
                'model_used' => 'fallback',
                'response_time' => round((microtime(true) - $startTime) * 1000),
                'requires_human_follow_up' => true,
                'contact_info' => [
                    'phone' => '311-211-8800',
                    'email' => 'contacto@uan.edu.mx',
                    'website' => 'https://www.uan.edu.mx'
                ]
            ]
        ], 200);
    }

    /**
     * VALIDAR MENSAJE DE ENTRADA
     */
    private function isValidMessage(string $message): bool
    {
        // Filtros básicos
        if (strlen(trim($message)) < 3) return false;
        if (strlen($message) > 1000) return false;

        // Detectar spam básico
        $spamPatterns = [
            '/(.)\1{10,}/', // Caracteres repetidos
            '/^[0-9\s\-\+\(\)]+$/', // Solo números (posible spam)
            '/^[A-Z\s!]{50,}$/', // Solo mayúsculas largas
        ];

        foreach ($spamPatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return false;
            }
        }

        return true;
    }

    /**
     * VALIDAR RESPUESTA DE IA
     */
    private function isResponseValid(array $response): bool
    {
        if (!isset($response['success']) || !$response['success']) {
            return false;
        }

        $responseText = $response['response'] ?? '';

        // Respuesta muy corta o vacía
        if (strlen(trim($responseText)) < 20) {
            return false;
        }

        // Respuesta con errores obvios
        $errorPatterns = [
            'error', 'undefined', 'null', 'exception',
            'lo siento, no puedo', 'no entiendo'
        ];

        $responseLower = strtolower($responseText);
        foreach ($errorPatterns as $pattern) {
            if (str_contains($responseLower, $pattern)) {
                return false;
            }
        }

        return true;
    }

    /**
     * VERIFICAR SI NECESITA INFORMACIÓN DE CONTACTO
     */
    private function needsContactInfo(string $message): bool
    {
        $contactKeywords = [
            'contacto', 'teléfono', 'dirección', 'ubicación',
            'dónde', 'cómo llegar', 'horario', 'atención'
        ];

        $messageLower = strtolower($message);
        foreach ($contactKeywords as $keyword) {
            if (str_contains($messageLower, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * BÚSQUEDA SIMPLIFICADA PARA FALLBACK
     */
    private function searchKnowledgeSimple(string $query, string $userType, ?string $department): array
    {
        try {
            $results = DB::table('knowledge_base')
                ->where('is_active', true)
                ->whereRaw('JSON_CONTAINS(user_types, ?)', [json_encode($userType)])
                ->where(function($q) use ($query) {
                    $q->where('title', 'LIKE', "%{$query}%")
                      ->orWhere('content', 'LIKE', "%{$query}%");
                })
                ->orderBy('priority', 'desc')
                ->limit(2)
                ->get(['content']);

            return $results->pluck('content')->toArray();
        } catch (\Exception $e) {
            Log::error('Simple knowledge search error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * GENERAR RESPUESTA SIMPLE PARA FALLBACK
     */
    private function generateSimpleResponse(string $message, array $context, string $userType): string
    {
        $message = strtolower($message);

        // Respuestas específicas basadas en palabras clave CON FORMATO MEJORADO
        if (str_contains($message, 'carrera') || str_contains($message, 'licenciatura')) {
            if (!empty($context)) {
                return "🎓 **Oferta Educativa UAN**\n\n" .
                    "📋 **Información disponible:**\n" .
                    substr($context[0], 0, 300) . "...\n\n" .
                    "📞 **Más información:**\n" .
                    "• Tel: 311-211-8800\n" .
                    "• Web: https://www.uan.edu.mx/es/oferta";
            }
            return "🎓 **Carreras en la UAN**\n\n" .
                "📋 **¡Tenemos más de 40 programas para ti!**\n" .
                "La UAN ofrece licenciaturas en diversas áreas del conocimiento.\n\n" .
                "📞 **Información detallada:**\n" .
                "• Tel: 311-211-8800\n" .
                "• Web: https://www.uan.edu.mx/es/licenciaturas";
        }

        if (str_contains($message, 'inscripción') || str_contains($message, 'admisión')) {
            if (!empty($context)) {
                return "📝 **Proceso de Inscripción**\n\n" .
                    "📋 **Información:**\n" .
                    substr($context[0], 0, 300) . "...\n\n" .
                    "📞 **SA - Secretaría Académica:**\n" .
                    "• Tel: 311-211-8800 ext. 8803\n" .
                    "• Email: academica@uan.edu.mx";
            }
            return "📝 **Inscripciones UAN**\n\n" .
                "📋 **Requisitos principales:**\n" .
                "• Solicitud de examen impresa y llenada correctamente\n" .
                "• Recibo de pago original\n" .
                "• Constancia original con promedio general\n\n" .
                "• Copia legible de tu CURP\n\n" .
                "📞 **SA:**\n" .
                "• Tel: 311-211-8800 ext. 8803\n" .
                "• Email: academica@uan.edu.mx";
        }

        if (str_contains($message, 'biblioteca')) {
            return "📚 **Sistema Bibliotecario UAN**\n\n" .
                "📋 **Servicios disponibles:**\n" .
                "• Préstamo de libros\n" .
                "• Préstamo de Equipos de Cómputo\n" .
                "• Espacios de estudio\n" .
                "• Wifi gratuito\n\n" .
                "📞 **Biblioteca Magna:**\n" .
                "• Tel: 311-211-8800 ext. 8837";
                "• Email: biblioteca@uan.edu.mx";
        }

        if (str_contains($message, 'sistema') || str_contains($message, 'correo')) {
            return "💻 **Soporte Técnico UAN**\n\n" .
                "📋 **Servicios:**\n" .
                "• Plataformas educativas\n" .
                "📞 **Dirección General de Sistemas:**\n" .
                "• Tel: 311-211-8800 ext. 8640\n" .
                "• Email: dgs@uan.edu.mx";
        }

        // Saludo o respuesta general
        if (str_contains($message, 'hola') || str_contains($message, 'buenos')) {
            return "👋 **¡Hola! Soy Ociel**\n\n" .
                "🎓 **Tu asistente virtual de la UAN**\n\n" .
                "📋 **Puedo ayudarte con:**\n" .
                "• Información sobre carreras\n" .
                "• Trámites estudiantiles\n" .
                "• Servicios universitarios\n" .
                "• Contactos y ubicaciones\n\n" .
                "💬 **¿En qué puedo asistirte hoy?**";
        }

        // Respuesta general con contexto si existe
        if (!empty($context)) {
            return "📋 **Información UAN**\n\n" .
                substr($context[0], 0, 400) . "\n\n" .
                "📞 **Más información:**\n" .
                "• Tel: 311-211-8800\n" .
                "• Web: https://www.uan.edu.mx";
        }

        // Respuesta por defecto MEJORADA
        return "👋 **¡Hola! Soy Ociel**\n\n" .
            "🎓 **Asistente Virtual de la UAN**\n\n" .
            "📋 **Puedo ayudarte con información sobre:**\n" .
            "• Carreras y programas académicos\n" .
            "• Trámites estudiantiles\n" .
            "• Servicios universitarios\n" .
            "• Contactos y ubicaciones\n\n" .
            "📞 **Contacto general:**\n" .
            "• Tel: 311-211-8800\n" .
            "• Web: https://www.uan.edu.mx\n\n" .
            "💬 **¿En qué puedo ayudarte?**";
    }

    /**
     * REGISTRAR INTERACCIÓN EN BASE DE DATOS
     */
    private function logChatInteraction(array $data): void
    {
        try {
            DB::table('chat_interactions')->insert(array_merge($data, [
                'created_at' => now(),
                'updated_at' => now()
            ]));
        } catch (\Exception $e) {
            Log::error('Failed to log chat interaction: ' . $e->getMessage(), $data);
        }
    }

    /**
     * Endpoint para feedback del usuario
     */
    public function feedback(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required|string|max:100',
            'was_helpful' => 'required|boolean',
            'feedback_comment' => 'nullable|string|max:500',
            'rating' => 'nullable|integer|min:1|max:5'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Datos inválidos',
                'details' => $validator->errors()
            ], 400);
        }

        $validated = $validator->validated();

        try {
            // Actualizar la interacción más reciente de esta sesión
            $updated = DB::table('chat_interactions')
                ->where('session_id', $validated['session_id'])
                ->orderBy('created_at', 'desc')
                ->limit(1)
                ->update([
                    'was_helpful' => $validated['was_helpful'],
                    'feedback_comment' => $validated['feedback_comment'] ?? null,
                    'updated_at' => now()
                ]);

            // Log para analytics
            Log::info('User feedback received', [
                'session_id' => $validated['session_id'],
                'was_helpful' => $validated['was_helpful'],
                'has_comment' => !empty($validated['feedback_comment']),
                'rating' => $validated['rating'] ?? null
            ]);

            return response()->json([
                'success' => $updated > 0,
                'message' => $updated > 0 ?
                    'Gracias por tu feedback, nos ayuda a mejorar' :
                    'No se encontró la sesión especificada',
                'data' => [
                    'feedback_recorded' => $updated > 0,
                    'timestamp' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error recording feedback: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Endpoint para obtener departamentos activos
     */
    public function departments(): JsonResponse
    {
        try {
            $departments = DB::table('departments')
                ->where('is_active', true)
                ->select([
                    'code', 'name', 'short_name', 'type', 'contact_phone',
                    'contact_email', 'location', 'schedule', 'services'
                ])
                ->orderBy('type')
                ->orderBy('name')
                ->get()
                ->map(function ($dept) {
                    if ($dept->services) {
                        $dept->services = json_decode($dept->services, true);
                    }
                    return $dept;
                });

            return response()->json([
                'success' => true,
                'data' => $departments,
                'total' => $departments->count(),
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching departments: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error obteniendo información de departamentos'
            ], 500);
        }
    }

    /**
     * Health check completo del sistema
     */
    public function health(): JsonResponse
    {
        $startTime = microtime(true);

        $health = [
            'status' => 'ok',
            'service' => 'Hola Ociel API',
            'version' => '1.2.0',
            'timestamp' => now()->toISOString(),
            'response_time_ms' => 0,
            'components' => []
        ];

        // Verificar base de datos
        try {
            $dbStart = microtime(true);
            $knowledgeCount = DB::table('knowledge_base')->where('is_active', true)->count();
            $interactionsToday = DB::table('chat_interactions')
                ->whereDate('created_at', today())
                ->count();

            $health['components']['database'] = [
                'status' => 'healthy',
                'response_time_ms' => round((microtime(true) - $dbStart) * 1000),
                'details' => [
                    'knowledge_entries' => $knowledgeCount,
                    'interactions_today' => $interactionsToday
                ]
            ];
        } catch (\Exception $e) {
            $health['components']['database'] = [
                'status' => 'unhealthy',
                'error' => 'Database connection failed'
            ];
        }

        // Verificar Ollama
        try {
            $ollamaStart = microtime(true);
            $ollamaHealthy = $this->ollamaService->isHealthy();
            $models = $ollamaHealthy ? $this->ollamaService->checkRequiredModels() : [];

            $health['components']['ollama'] = [
                'status' => $ollamaHealthy ? 'healthy' : 'unhealthy',
                'response_time_ms' => round((microtime(true) - $ollamaStart) * 1000),
                'details' => [
                    'models_available' => $models,
                    'service_url' => config('services.ollama.url')
                ]
            ];
        } catch (Exception $e) {
            $health['components']['ollama'] = [
                'status' => 'unhealthy',
                'error' => 'Ollama service check failed'
            ];
        }

        // Verificar Knowledge Base Service
        try {
            $kbStart = microtime(true);
            $kbHealthy = $this->knowledgeService->isHealthy();
            $kbStats = $kbHealthy ? $this->knowledgeService->getStats() : [];

            $health['components']['knowledge_base'] = [
                'status' => $kbHealthy ? 'healthy' : 'unhealthy',
                'response_time_ms' => round((microtime(true) - $kbStart) * 1000),
                'details' => $kbStats
            ];
        } catch (\Exception $e) {
            $health['components']['knowledge_base'] = [
                'status' => 'unhealthy',
                'error' => 'Knowledge base service check failed'
            ];
        }

        // Calcular estado general
        $componentStatuses = collect($health['components'])->pluck('status');
        $allHealthy = $componentStatuses->every(fn($status) => $status === 'healthy');
        $anyUnhealthy = $componentStatuses->contains('unhealthy');

        if ($allHealthy) {
            $health['status'] = 'healthy';
        } elseif ($anyUnhealthy) {
            $health['status'] = 'unhealthy';
        } else {
            $health['status'] = 'degraded';
        }

        $health['response_time_ms'] = round((microtime(true) - $startTime) * 1000);

        return response()->json($health, $allHealthy ? 200 : 503);
    }

    /**
     * Verificar salud de la base de datos
     */
    private function checkDatabaseHealth(): bool
    {
        try {
            DB::getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
