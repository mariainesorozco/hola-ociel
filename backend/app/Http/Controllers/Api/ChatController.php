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
     * Endpoint principal de chat con Ociel
     */
    public function chat(Request $request): JsonResponse
    {
        $startTime = microtime(true);

        // Validar la entrada
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:1000',
            'user_type' => 'in:student,employee,public',
            'department' => 'nullable|string|max:100',
            'session_id' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Datos inválidos',
                'details' => $validator->errors()
            ], 400);
        }

        $validated = $validator->validated();
        $message = $validated['message'];
        $userType = $validated['user_type'] ?? 'public';
        $department = $validated['department'] ?? null;
        $sessionId = $validated['session_id'] ?? Str::uuid();

        try {
            // 1. Buscar información relevante en la base de conocimientos (simplificado)
            $context = $this->searchKnowledgeSimple($message, $userType, $department);

            // 2. Generar respuesta - Probar primero sin Ollama
            $response = $this->generateSimpleResponse($message, $context, $userType);
            $modelUsed = 'knowledge_base';
            $confidence = 0.8;
            $responseTime = round((microtime(true) - $startTime) * 1000);

            // 3. Registrar la interacción (simplificado)
            $this->logChatInteractionSimple([
                'session_id' => $sessionId,
                'user_type' => $userType,
                'department' => $department,
                'message' => $message,
                'response' => $response,
                'confidence' => $confidence,
                'model_used' => $modelUsed,
                'response_time' => $responseTime,
                'ip_address' => $request->ip(),
                'channel' => 'web'
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'response' => $response,
                    'session_id' => $sessionId,
                    'confidence' => $confidence,
                    'model_used' => $modelUsed,
                    'response_time' => $responseTime,
                    'requires_human_follow_up' => false,
                    'contact_info' => $this->getRelevantContactInfo($department, $context)
                ],
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            \Log::error('Chat error: ' . $e->getMessage(), [
                'message' => $message,
                'user_type' => $userType,
                'stack' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor',
                'error_details' => $e->getMessage(), // Temporal para debug
                'data' => [
                    'response' => 'Disculpa, estoy experimentando dificultades técnicas. Por favor, intenta de nuevo en unos momentos.',
                    'session_id' => $sessionId,
                    'confidence' => 0.0,
                    'requires_human_follow_up' => true
                ]
            ], 500);
        }
    }

    /**
     * Búsqueda simplificada en knowledge base
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
                ->limit(3)
                ->get(['content']);

            return $results->pluck('content')->toArray();
        } catch (\Exception $e) {
            \Log::error('Knowledge search error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Generar respuesta simple basada en knowledge base
     */
    private function generateSimpleResponse(string $message, array $context, string $userType): string
    {
        $message = strtolower($message);

        // Respuestas específicas basadas en palabras clave
        if (str_contains($message, 'carrera') || str_contains($message, 'licenciatura')) {
            if (!empty($context)) {
                return "🎓 " . $context[0] . "\n\nPara más información sobre nuestra oferta educativa, puedes contactar al 311-211-8800 o visitar https://www.uan.edu.mx";
            }
            return "🎓 La UAN ofrece más de 40 programas de licenciatura en diversas áreas. Para información detallada sobre nuestra oferta educativa, contacta al 311-211-8800 o visita https://www.uan.edu.mx";
        }

        if (str_contains($message, 'inscripción') || str_contains($message, 'admisión')) {
            if (!empty($context)) {
                return "📝 " . $context[0] . "\n\nPara más detalles, contacta a la DGSA al 311-211-8800 ext. 8530.";
            }
            return "📝 Para inscribirte necesitas certificado de bachillerato y aprobar el examen de admisión. Contacta a la DGSA al 311-211-8800 ext. 8530 para más información.";
        }

        if (str_contains($message, 'biblioteca')) {
            return "📚 La UAN cuenta con biblioteca central y bibliotecas especializadas con servicios de préstamo, consulta en línea y espacios de estudio. Más información: 311-211-8800 ext. 8600.";
        }

        if (str_contains($message, 'sistema') || str_contains($message, 'correo')) {
            return "💻 Para soporte técnico y servicios de sistemas contacta a la DGS al 311-211-8800 ext. 8540 o envía un correo a sistemas@uan.edu.mx";
        }

        // Saludo o respuesta general
        if (str_contains($message, 'hola') || str_contains($message, 'buenos')) {
            return "¡Hola! 👋 Soy Ociel, tu asistente virtual de la Universidad Autónoma de Nayarit. Estoy aquí para ayudarte con información sobre trámites, carreras, servicios y más. ¿En qué puedo asistirte hoy?";
        }

        // Respuesta general con contexto si existe
        if (!empty($context)) {
            return "📋 Basado en tu consulta, te comparto esta información: " . $context[0] . "\n\nSi necesitas más detalles, puedes contactar al 311-211-8800.";
        }

        // Respuesta por defecto
        return "👋 ¡Hola! Soy Ociel, tu asistente de la UAN. Puedo ayudarte con información sobre carreras, trámites, servicios y más. Para consultas específicas, contacta al 311-211-8800 o visita https://www.uan.edu.mx ¿En qué más puedo ayudarte?";
    }

    /**
     * Log simplificado de interacciones
     */
    private function logChatInteractionSimple(array $data): void
    {
        try {
            DB::table('chat_interactions')->insert(array_merge($data, [
                'created_at' => now(),
                'updated_at' => now()
            ]));
        } catch (\Exception $e) {
            \Log::error('Failed to log chat interaction: ' . $e->getMessage());
        }
    }

    /**
     * Endpoint para dar feedback sobre la respuesta
     */
    public function feedback(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required|string',
            'was_helpful' => 'required|boolean',
            'feedback_comment' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Datos inválidos'
            ], 400);
        }

        $validated = $validator->validated();

        // Actualizar la interacción más reciente de esta sesión
        $updated = DB::table('chat_interactions')
            ->where('session_id', $validated['session_id'])
            ->orderBy('created_at', 'desc')
            ->limit(1)
            ->update([
                'was_helpful' => $validated['was_helpful'],
                'feedback_comment' => $validated['feedback_comment'],
                'updated_at' => now()
            ]);

        return response()->json([
            'success' => $updated > 0,
            'message' => $updated > 0 ? 'Gracias por tu feedback' : 'Sesión no encontrada'
        ]);
    }

    /**
     * Endpoint para obtener información de departamentos
     */
    public function departments(): JsonResponse
    {
        $departments = DB::table('departments')
            ->where('is_active', true)
            ->select(['code', 'name', 'short_name', 'type', 'contact_phone', 'contact_email', 'services'])
            ->get()
            ->map(function ($dept) {
                if ($dept->services) {
                    $dept->services = json_decode($dept->services);
                }
                return $dept;
            });

        return response()->json([
            'success' => true,
            'data' => $departments
        ]);
    }

    /**
     * Health check del sistema
     */
    public function health(): JsonResponse
    {
        $health = [
            'status' => 'ok',
            'service' => 'Hola Ociel API',
            'version' => '1.0.0',
            'timestamp' => now()->toISOString(),
            'components' => [
                'database' => $this->checkDatabaseHealth(),
                'ollama' => $this->ollamaService->isHealthy(),
                'knowledge_base' => $this->knowledgeService->isHealthy()
            ]
        ];

        $allHealthy = collect($health['components'])->every(fn($status) => $status === true);
        $health['status'] = $allHealthy ? 'healthy' : 'degraded';

        return response()->json($health, $allHealthy ? 200 : 503);
    }

    // ===== MÉTODOS PRIVADOS =====

    private function getFallbackResponse(string $message, array $context): string
    {
        // Respuestas predeterminadas si la IA no está disponible
        $responses = [
            'general' => 'Gracias por contactar a la UAN. Para obtener información específica, puedes llamar al 311-211-8800 o visitar nuestro sitio web https://www.uan.edu.mx',
            'tramites' => 'Para información sobre trámites, contacta a la Dirección General de Servicios Académicos al 311-211-8800 ext. 8530.',
            'sistemas' => 'Para soporte técnico, contacta a la Dirección General de Sistemas al 311-211-8800 ext. 8540.'
        ];

        // Intentar detectar el tipo de consulta
        $message = strtolower($message);

        if (str_contains($message, 'trámite') || str_contains($message, 'inscripción') || str_contains($message, 'titulación')) {
            return $responses['tramites'];
        }

        if (str_contains($message, 'sistema') || str_contains($message, 'correo') || str_contains($message, 'plataforma')) {
            return $responses['sistemas'];
        }

        return $responses['general'];
    }

    private function calculateConfidence(array $context, array $aiResponse): float
    {
        $confidence = 0.5; // Base

        // Aumentar confianza si hay contexto relevante
        if (!empty($context)) {
            $confidence += 0.3;
        }

        // Usar confianza del modelo si está disponible
        if (isset($aiResponse['confidence'])) {
            $confidence = ($confidence + $aiResponse['confidence']) / 2;
        }

        return min(1.0, $confidence);
    }

    private function shouldEscalateToHuman(string $message, float $confidence, array $context): bool
    {
        // Escalar si la confianza es muy baja
        if ($confidence < 0.6) {
            return true;
        }

        // Escalar para ciertos tipos de consultas
        $escalationKeywords = ['queja', 'problema urgente', 'director', 'rector', 'emergencia'];

        foreach ($escalationKeywords as $keyword) {
            if (str_contains(strtolower($message), $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function getSuggestedActions(string $message, ?string $department, array $context): array
    {
        $actions = [];

        if (str_contains(strtolower($message), 'inscripción')) {
            $actions[] = 'Revisar requisitos de admisión';
            $actions[] = 'Contactar a DGSA para más información';
        }

        if (str_contains(strtolower($message), 'carrera')) {
            $actions[] = 'Explorar oferta educativa completa';
            $actions[] = 'Agendar cita con orientación vocacional';
        }

        return $actions;
    }

    private function getRelevantContactInfo(?string $department, array $context): array
    {
        if ($department) {
            $dept = DB::table('departments')
                ->where('code', $department)
                ->first(['contact_phone', 'contact_email', 'location']);

            if ($dept) {
                return [
                    'phone' => $dept->contact_phone,
                    'email' => $dept->contact_email,
                    'location' => $dept->location
                ];
            }
        }

        // Información general por defecto
        return [
            'phone' => '311-211-8800',
            'email' => 'contacto@uan.edu.mx',
            'website' => 'https://www.uan.edu.mx'
        ];
    }

    private function logChatInteraction(array $data): void
    {
        DB::table('chat_interactions')->insert(array_merge($data, [
            'created_at' => now(),
            'updated_at' => now()
        ]));
    }

    private function checkDatabaseHealth(): bool
    {
        try {
            DB::connection()->getPDO();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
