<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class OllamaService
{
    private $client;
    private $baseUrl;
    private $primaryModel;
    private $secondaryModel;
    private $embeddingModel;

    public function __construct()
    {
        $this->baseUrl = config('services.ollama.url', 'http://localhost:11434');
        $this->primaryModel = config('services.ollama.primary_model', 'mistral:7b');
        $this->secondaryModel = config('services.ollama.secondary_model', 'llama3.2:3b');
        $this->embeddingModel = config('services.ollama.embedding_model', 'nomic-embed-text');

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 120,
            'connect_timeout' => 30,
            'read_timeout' => 120,
            'http_errors' => false,
            'verify' => false,
            'headers' => [
                'Connection' => 'keep-alive',
                'Keep-Alive' => 'timeout=300, max=1000'
            ],
            'curl' => [
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_TCP_KEEPALIVE => 1,
                CURLOPT_TCP_KEEPIDLE => 300,
                CURLOPT_TCP_KEEPINTVL => 60
            ]
        ]);
    }

    /**
     * Generar respuesta contextualizada para Ociel con formato optimizado
     */
    public function generateOcielResponse(string $userMessage, array $context = [], string $userType = 'public', string $department = null): array
    {
        $systemPrompt = $this->buildOptimizedOcielPrompt($context, $userType, $department);
        $fullPrompt = $systemPrompt . "\n\nCONSULTA DEL USUARIO: " . $userMessage . "\n\nRESPUESTA DE OCIEL:";

        // Usar modelo secundario para consultas simples, primario para complejas
        $useSecondaryModel = $this->isSimpleQuery($userMessage);
        $model = $useSecondaryModel ? $this->secondaryModel : $this->primaryModel;

        $result = $this->generateResponse($fullPrompt, [
            'model' => $model,
            'temperature' => 0.2, // Temperatura muy baja para consistencia
            'max_tokens' => 500,   // Respuestas más concisas
            'top_p' => 0.8,
            'repeat_penalty' => 1.1
        ]);

        // Post-procesar respuesta para optimizar formato
        if ($result['success']) {
            $result = $this->postProcessOcielResponse($result, $context, $userMessage);
        }

        return $result;
    }

    /**
     * Construir prompt optimizado para Ociel con mejor formato
     */
    private function buildOptimizedOcielPrompt(array $context, string $userType, ?string $department): string
    {
        $prompt = "ERES OCIEL - ASISTENTE VIRTUAL OFICIAL DE LA UNIVERSIDAD AUTÓNOMA DE NAYARIT (UAN)\n\n";

        // === REGLAS CRÍTICAS DE FORMATO ===
        $prompt .= "REGLAS CRÍTICAS DE FORMATO - NUNCA LAS VIOLATES:\n";
        $prompt .= "1. USA PÁRRAFOS CORTOS: Máximo 2-3 líneas por párrafo\n";
        $prompt .= "2. USA GUIONES SIMPLES (-) para listas, NUNCA asteriscos (*)\n";
        $prompt .= "3. USA UN SOLO salto de línea (\\n) entre párrafos\n";
        $prompt .= "4. USA DOS saltos de línea (\\n\\n) solo entre secciones principales\n";
        $prompt .= "5. NO uses Markdown complejo, mantén el formato simple\n";
        $prompt .= "6. MÁXIMO 4 párrafos por respuesta\n";
        $prompt .= "7. UN SOLO contacto por respuesta\n\n";

        // === REGLAS DE CONTENIDO ===
        $prompt .= "REGLAS DE CONTENIDO:\n";
        $prompt .= "1. SOLO responde con información del CONTEXTO OFICIAL proporcionado\n";
        $prompt .= "2. Si NO tienes información específica, di: 'Para información específica contacta...'\n";
        $prompt .= "3. NUNCA inventes datos, fechas, precios, requisitos o procedimientos\n";
        $prompt .= "4. Si dudas, es mejor referir al departamento correspondiente\n\n";

        // === INFORMACIÓN DEL USUARIO ===
        $prompt .= "PERFIL DEL USUARIO:\n";
        $prompt .= "- Tipo: " . ucfirst($userType) . "\n";
        if ($department) {
            $prompt .= "- Departamento: " . $department . "\n";
        }
        $prompt .= "\n";

        // === CONTEXTO OFICIAL ===
        if (!empty($context)) {
            $prompt .= "INFORMACIÓN OFICIAL DISPONIBLE:\n";
            foreach (array_slice($context, 0, 2) as $index => $item) {
                $prompt .= "FUENTE " . ($index + 1) . ": " . substr($item, 0, 300) . "\n\n";
            }
        } else {
            $prompt .= "CONTEXTO: Sin información específica disponible para esta consulta.\n\n";
        }

        // === CONTACTOS VERIFICADOS ===
        $prompt .= "CONTACTOS OFICIALES VERIFICADOS:\n";
        $prompt .= "- SA (Servicios Académicos): 311-211-8800 ext. 8803\n";
        $prompt .= "- DGS (Sistemas): 311-211-8800 ext. 8640\n";
        $prompt .= "- Biblioteca: 311-211-8800 ext. 8837\n";
        $prompt .= "- Información general: 311-211-8800\n";
        $prompt .= "- Sitio web: https://www.uan.edu.mx\n\n";

        // === ESTRUCTURA DE RESPUESTA OBLIGATORIA ===
        $prompt .= "ESTRUCTURA OBLIGATORIA DE RESPUESTA:\n";
        $prompt .= "1. SALUDO: Breve y apropiado (1 línea)\n";
        $prompt .= "2. INFORMACIÓN: Principal y relevante (2-3 párrafos cortos)\n";
        $prompt .= "3. CONTACTO: UN solo contacto específico y relevante\n";
        $prompt .= "4. SEGUIMIENTO: Pregunta breve si corresponde\n\n";

        // === EJEMPLOS DE FORMATO CORRECTO ===
        $prompt .= "EJEMPLO DE FORMATO CORRECTO:\n";
        $prompt .= "¡Hola! Te ayudo con información sobre [tema].\n\n";
        $prompt .= "Para [trámite/servicio] necesitas:\n";
        $prompt .= "- Requisito 1\n";
        $prompt .= "- Requisito 2\n";
        $prompt .= "- Requisito 3\n\n";
        $prompt .= "El proceso es sencillo y puedes realizarlo en [ubicación].\n\n";
        $prompt .= "📞 SA: 311-211-8800 ext. 8803\n\n";
        $prompt .= "¿Necesitas información sobre algún requisito específico?\n\n";

        // === INSTRUCCIONES FINALES ===
        $prompt .= "INSTRUCCIONES FINALES:\n";
        $prompt .= "- Responde en español mexicano formal pero amigable\n";
        $prompt .= "- Usa SOLO UN emoji por línea de contacto: 📞 para teléfonos, 📧 para emails\n";
        $prompt .= "- NO repitas números de teléfono en la misma línea\n";
        $prompt .= "- Formato de contacto: '📞 SA: 311-211-8800 ext. 8803' (sin duplicar números)\n";
        $prompt .= "- Si no tienes información completa, deriva al contacto apropiado\n";
        $prompt .= "- Mantén siempre un tono profesional y empático\n";
        $prompt .= "- Termina con una pregunta de seguimiento cuando sea apropiado\n\n";

        return $prompt;
    }

    /**
     * Post-procesar respuesta de Ociel para optimizar formato
     */
    private function postProcessOcielResponse(array $result, array $context, string $userMessage): array
    {
        $response = $result['response'];

        // 1. Limpiar formato problemático
        $response = $this->optimizeResponseFormat($response);

        // 2. Calcular confianza mejorada
        $confidence = $this->calculateOptimizedConfidence($response, $context, $userMessage);
        $result['confidence'] = $confidence;

        // 3. Si la confianza es muy baja, usar respuesta de respaldo
        if ($confidence < 0.6) {
            $result['response'] = $this->generateFallbackResponse($userMessage, $context);
            $result['confidence'] = 0.8;
            $result['fallback_used'] = true;
        } else {
            $result['response'] = $response;
        }

        // 4. Validar y mejorar contactos
        $result['response'] = $this->validateContacts($result['response']);

        return $result;
    }

    /**
     * Optimizar formato de respuesta para evitar desfase
     */
    private function optimizeResponseFormat(string $response): string
    {
        // 1. Convertir asteriscos a guiones en listas
        $response = preg_replace('/^\* /m', '- ', $response);
        $response = preg_replace('/^\*\*/m', '**', $response);

        // 2. Eliminar múltiples saltos de línea
        $response = preg_replace('/\n{3,}/', "\n\n", $response);

        // 3. Asegurar espaciado correcto después de listas
        $response = preg_replace('/(-\s.+)\n([A-Z])/m', '$1' . "\n\n" . '$2', $response);

        // 4. Limpiar formato de títulos
        $response = preg_replace('/^#{1,6}\s*(.+)$/m', '**$1**', $response);

        // 5. Asegurar formato correcto de contactos
        $response = preg_replace('/📞\s*(.+)/', '📞 $1', $response);

        // 6. Eliminar espacios en blanco al final de líneas
        $response = preg_replace('/[ \t]+$/m', '', $response);

        // 7. Asegurar que no hay líneas vacías al inicio o final
        $response = trim($response);

        return $response;
    }

    /**
     * Calcular confianza optimizada
     */
    private function calculateOptimizedConfidence(string $response, array $context, string $userMessage): float
    {
        $confidence = 0.5; // Base

        // Bonus por tener contexto
        if (!empty($context)) {
            $confidence += 0.3;
        }

        // Bonus por longitud apropiada
        $length = strlen($response);
        if ($length >= 100 && $length <= 600) {
            $confidence += 0.2;
        } else {
            $confidence -= 0.1;
        }

        // Bonus por tener contacto oficial
        if (preg_match('/311-211-8800|ext\.\s*\d+|@uan\.edu\.mx/', $response)) {
            $confidence += 0.1;
        }

        // Penalizar respuestas con formato problemático
        if (preg_match('/\*\s+/', $response) || preg_match('/\n{3,}/', $response)) {
            $confidence -= 0.2;
        }

        // Bonus por estructura apropiada
        if ($this->hasGoodStructure($response)) {
            $confidence += 0.1;
        }

        return max(0.1, min(1.0, $confidence));
    }

    /**
     * Verificar si la respuesta tiene buena estructura
     */
    private function hasGoodStructure(string $response): bool
    {
        $paragraphs = explode("\n\n", $response);

        // Debe tener entre 2 y 4 secciones
        if (count($paragraphs) < 2 || count($paragraphs) > 4) {
            return false;
        }

        // Debe tener al menos un contacto
        if (!preg_match('/📞|311-211-8800/', $response)) {
            return false;
        }

        return true;
    }

    /**
     * Generar respuesta de respaldo optimizada
     */
    private function generateFallbackResponse(string $userMessage, array $context): string
    {
        $messageLower = strtolower($userMessage);

        // Respuestas específicas por tipo de consulta
        if (preg_match('/inscripci[oó]n|admisi[oó]n/', $messageLower)) {
            return "¡Hola! Te ayudo con información sobre inscripciones.\n\n" .
                   "Para el proceso de inscripción necesitas:\n" .
                   "- Certificado de bachillerato\n" .
                   "- Aprobar examen de admisión\n" .
                   "- Completar documentación requerida\n\n" .
                   "📞 SA: 311-211-8800 ext. 8803\n\n" .
                   "¿Necesitas información sobre fechas de convocatoria?";
        }

        if (preg_match('/carrera|licenciatura/', $messageLower)) {
            return "¡Hola! Te comparto información sobre nuestra oferta educativa.\n\n" .
                   "La UAN ofrece más de 40 programas de licenciatura en diversas áreas del conocimiento. " .
                   "Cada programa está diseñado para formar profesionistas competentes.\n\n" .
                   "📞 Información general: 311-211-8800\n\n" .
                   "¿Te interesa alguna área específica del conocimiento?";
        }

        if (preg_match('/sistema|soporte|plataforma/', $messageLower)) {
            return "¡Hola! Te ayudo con soporte técnico.\n\n" .
                   "Para problemas con sistemas, plataformas o acceso a servicios digitales, " .
                   "nuestro equipo técnico especializado está disponible.\n\n" .
                   "💻 DGS: 311-211-8800 ext. 8540\n\n" .
                   "¿El problema es con alguna plataforma específica?";
        }

        // Respuesta general con contexto si existe
        if (!empty($context)) {
            return "¡Hola! Encontré información relacionada con tu consulta.\n\n" .
                   substr($context[0], 0, 200) . "...\n\n" .
                   "📞 Para información completa: 311-211-8800\n\n" .
                   "¿Necesitas detalles sobre algún aspecto específico?";
        }

        // Respuesta completamente general
        return "¡Hola! Soy Ociel, tu asistente de la UAN.\n\n" .
               "Estoy aquí para ayudarte con información sobre trámites, carreras, " .
               "servicios y todo lo relacionado con nuestra universidad.\n\n" .
               "📞 Información general: 311-211-8800\n\n" .
               "¿Puedes ser más específico sobre lo que necesitas?";
    }

    /**
     * Validar formato de contactos
     */
    private function validateContacts(string $response): string
    {
        // Limpiar duplicaciones de teléfonos
        $response = preg_replace('/311-211-8800\s+ext\.\s+311-211-8800\s+ext\.\s+(\d+)/', '311-211-8800 ext. $1', $response);

        // Limpiar duplicaciones de íconos
        $response = preg_replace('/📞\s*📞/', '📞', $response);

        // Asegurar formato correcto de extensiones sin duplicar
        $response = preg_replace('/\bext\.\s*8530\b(?!\s+ext\.)/', 'ext. 8530', $response);
        $response = preg_replace('/\bext\.\s*8540\b(?!\s+ext\.)/', 'ext. 8540', $response);
        $response = preg_replace('/\bext\.\s*8600\b(?!\s+ext\.)/', 'ext. 8600', $response);

        // Solo agregar números completos si no están presentes
        if (!preg_match('/311-211-8800/', $response) && preg_match('/\bext\.\s*8530\b/', $response)) {
            $response = preg_replace('/\bext\.\s*8530\b/', '311-211-8800 ext. 8530', $response);
        }
        if (!preg_match('/311-211-8800/', $response) && preg_match('/\bext\.\s*8540\b/', $response)) {
            $response = preg_replace('/\bext\.\s*8540\b/', '311-211-8800 ext. 8540', $response);
        }
        if (!preg_match('/311-211-8800/', $response) && preg_match('/\bext\.\s*8600\b/', $response)) {
            $response = preg_replace('/\bext\.\s*8600\b/', '311-211-8800 ext. 8600', $response);
        }

        // Asegurar formato de emails sin duplicar
        $response = preg_replace('/\b([a-z]+)@uan\.edu\.mx\b/', '$1@uan.edu.mx', $response);

        return $response;
    }

    // === MÉTODOS EXISTENTES SIN CAMBIOS ===

    public function isHealthy(): bool
    {
        $maxRetries = 3;
        $retryDelay = 2;

        for ($i = 0; $i < $maxRetries; $i++) {
            try {
                $response = $this->client->get('/api/version', [
                    'timeout' => 10
                ]);

                if ($response->getStatusCode() === 200) {
                    Log::info("Ollama health check successful on attempt " . ($i + 1));
                    return true;
                }
            } catch (RequestException $e) {
                Log::warning("Ollama health check failed on attempt " . ($i + 1) . ": " . $e->getMessage());

                if ($i < $maxRetries - 1) {
                    sleep($retryDelay);
                }
            }
        }

        Log::error('Ollama health check failed after ' . $maxRetries . ' attempts');
        return false;
    }

    public function getAvailableModels(): array
    {
        try {
            $response = $this->client->get('/api/tags');
            $data = json_decode($response->getBody(), true);

            return collect($data['models'] ?? [])
                ->map(function ($model) {
                    return [
                        'name' => $model['name'],
                        'size' => $model['size'] ?? 0,
                        'modified_at' => $model['modified_at'] ?? null
                    ];
                })
                ->toArray();
        } catch (RequestException $e) {
            Log::error('Failed to get Ollama models: ' . $e->getMessage());
            return [];
        }
    }

    public function generateResponse(string $prompt, array $options = []): array
    {
        $model = $options['model'] ?? $this->primaryModel;
        $temperature = $options['temperature'] ?? 0.7;
        $maxTokens = $options['max_tokens'] ?? 1000;

        if (!$this->isHealthy()) {
            return [
                'success' => false,
                'error' => 'Ollama service is not available',
                'model' => $model,
                'response_time' => 0,
            ];
        }

        try {
            $startTime = microtime(true);

            $response = $this->client->post('/api/generate', [
                'json' => [
                    'model' => $model,
                    'prompt' => $prompt,
                    'stream' => false,
                    'options' => [
                        'temperature' => $temperature,
                        'num_predict' => $maxTokens,
                        'top_p' => $options['top_p'] ?? 0.9,
                        'top_k' => 40,
                        'repeat_penalty' => $options['repeat_penalty'] ?? 1.0,
                    ]
                ],
                'timeout' => 90,
            ]);

            $responseTime = round((microtime(true) - $startTime) * 1000);

            if ($response->getStatusCode() !== 200) {
                Log::error('Ollama HTTP error: ' . $response->getStatusCode() . ' - ' . $response->getBody());
                return [
                    'success' => false,
                    'error' => 'HTTP error: ' . $response->getStatusCode(),
                    'model' => $model,
                    'response_time' => $responseTime,
                ];
            }

            $data = json_decode($response->getBody(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Invalid JSON response from Ollama: ' . $response->getBody());
                return [
                    'success' => false,
                    'error' => 'Invalid JSON response',
                    'model' => $model,
                    'response_time' => $responseTime,
                ];
            }

            if (isset($data['error'])) {
                Log::error('Ollama API error: ' . $data['error']);
                return [
                    'success' => false,
                    'error' => $data['error'],
                    'model' => $model,
                    'response_time' => $responseTime,
                ];
            }

            return [
                'success' => true,
                'response' => $data['response'] ?? '',
                'model' => $model,
                'response_time' => $responseTime,
                'tokens_evaluated' => $data['eval_count'] ?? 0,
                'tokens_generated' => $data['eval_count'] ?? 0,
            ];

        } catch (ConnectException $e) {
            Log::error('Ollama connection failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Connection failed: Service may be down',
                'model' => $model,
                'response_time' => 0,
            ];

        } catch (RequestException $e) {
            $responseTime = round((microtime(true) - $startTime) * 1000);

            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $body = $e->getResponse()->getBody()->getContents();
                Log::error("Ollama request failed: HTTP {$statusCode} - {$body}");

                return [
                    'success' => false,
                    'error' => "HTTP {$statusCode}: " . substr($body, 0, 100),
                    'model' => $model,
                    'response_time' => $responseTime,
                ];
            }

            Log::error('Ollama request failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Request timeout or network error',
                'model' => $model,
                'response_time' => $responseTime,
            ];

        } catch (\Exception $e) {
            Log::error('Unexpected error in Ollama generation: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Unexpected error occurred',
                'model' => $model,
                'response_time' => 0,
            ];
        }
    }

    public function generateEmbedding(string $text): array
    {
        $cacheKey = 'embedding_' . md5($text);

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $response = $this->client->post('/api/embeddings', [
                'json' => [
                    'model' => $this->embeddingModel,
                    'prompt' => $text
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            $embedding = $data['embedding'] ?? [];

            Cache::put($cacheKey, $embedding, 3600);

            return $embedding;

        } catch (RequestException $e) {
            Log::error('Ollama embedding failed: ' . $e->getMessage());
            return [];
        }
    }

    private function isSimpleQuery(string $message): bool
    {
        $simplePatterns = [
            '/^(hola|hi|hello)/i',
            '/^(gracias|thanks)/i',
            '/^(adiós|bye)/i',
            '/^(sí|no|ok)/i',
            '/\?$/',
        ];

        $wordCount = str_word_count($message);

        if ($wordCount < 8) {
            return true;
        }

        foreach ($simplePatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }

        return false;
    }

    public function checkRequiredModels(): array
    {
        $availableModels = collect($this->getAvailableModels())->pluck('name')->toArray();

        $requiredModels = [
            'primary' => $this->primaryModel,
            'secondary' => $this->secondaryModel,
            'embedding' => $this->embeddingModel
        ];

        $status = [];

        foreach ($requiredModels as $type => $model) {
            $status[$type] = [
                'model' => $model,
                'available' => in_array($model, $availableModels),
                'type' => $type
            ];
        }

        return $status;
    }

    public function getUsageStats(): array
    {
        return [
            'total_requests' => Cache::get('ollama_requests', 0),
            'average_response_time' => Cache::get('ollama_avg_time', 0),
            'models_used' => Cache::get('ollama_models_used', []),
            'health_status' => $this->isHealthy()
        ];
    }
}
