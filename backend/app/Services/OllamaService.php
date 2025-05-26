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
            'timeout' => 120,           // Aumentado a 2 minutos
            'connect_timeout' => 30,    // Aumentado a 30 segundos
            'read_timeout' => 120,      // Timeout de lectura
            'http_errors' => false,     // No lanzar excepciones por HTTP errors
            'verify' => false,          // Deshabilitar verificación SSL para localhost
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
     * Generar respuesta contextualizada para Ociel con técnicas anti-alucinación
     */
    public function generateOcielResponse(string $userMessage, array $context = [], string $userType = 'public', string $department = null): array
    {
        $systemPrompt = $this->buildAdvancedOcielPrompt($context, $userType, $department);
        $fullPrompt = $systemPrompt . "\n\nCONSULTA DEL USUARIO: " . $userMessage . "\n\nRESPUESTA DE OCIEL:";

        // Usar modelo secundario para consultas simples, primario para complejas
        $useSecondaryModel = $this->isSimpleQuery($userMessage);
        $model = $useSecondaryModel ? $this->secondaryModel : $this->primaryModel;

        $result = $this->generateResponse($fullPrompt, [
            'model' => $model,
            'temperature' => 0.3, // Temperatura más baja para mayor precisión
            'max_tokens' => 800,
            'top_p' => 0.8,
            'repeat_penalty' => 1.1
        ]);

        // Post-procesar respuesta para validar y mejorar
        if ($result['success']) {
            $result = $this->postProcessResponse($result, $context, $userMessage);
        }

        return $result;
    }

    /**
     * Construir prompt avanzado con técnicas anti-alucinación
     */
    private function buildAdvancedOcielPrompt(array $context, string $userType, ?string $department): string
    {
        $prompt = "ERES OCIEL - ASISTENTE VIRTUAL OFICIAL DE LA UNIVERSIDAD AUTÓNOMA DE NAYARIT (UAN)\n\n";

        // === REGLAS FUNDAMENTALES ===
        $prompt .= "REGLAS CRÍTICAS - NUNCA LAS VIOLATES:\n";
        $prompt .= "1. SOLO responde con información que esté en el CONTEXTO OFICIAL proporcionado\n";
        $prompt .= "2. Si NO tienes información específica en el contexto, di exactamente: 'No tengo esa información específica. Te recomiendo contactar...'\n";
        $prompt .= "3. NUNCA inventes datos, fechas, precios, requisitos o procedimientos\n";
        $prompt .= "4. SIEMPRE cita fuentes cuando proporcionas información específica\n";
        $prompt .= "5. Si dudas sobre algo, es mejor referir al usuario al departamento correspondiente\n\n";

        // === INFORMACIÓN DEL USUARIO ===
        $prompt .= "PERFIL DEL USUARIO:\n";
        $prompt .= "- Tipo: " . ucfirst($userType) . "\n";
        if ($department) {
            $prompt .= "- Departamento de interés: " . $department . "\n";
        }
        $prompt .= "\n";

        // === CONTEXTO OFICIAL ===
        if (!empty($context)) {
            $prompt .= "INFORMACIÓN OFICIAL DISPONIBLE:\n";
            $prompt .= "=== INICIO DEL CONTEXTO OFICIAL ===\n";
            foreach ($context as $index => $item) {
                $prompt .= "FUENTE " . ($index + 1) . ":\n" . $item . "\n\n";
            }
            $prompt .= "=== FIN DEL CONTEXTO OFICIAL ===\n\n";
        } else {
            $prompt .= "CONTEXTO: No se encontró información específica en la base de conocimientos para esta consulta.\n\n";
        }

        // === INFORMACIÓN BÁSICA VERIFICADA ===
        $prompt .= "DATOS BÁSICOS VERIFICADOS DE LA UAN:\n";
        $prompt .= "- Nombre completo: Universidad Autónoma de Nayarit\n";
        $prompt .= "- Ubicación: Ciudad de la Cultura 'Amado Nervo', Tepic, Nayarit, México\n";
        $prompt .= "- Teléfono principal: 311-211-8800\n";
        $prompt .= "- Sitio web oficial: https://www.uan.edu.mx\n";
        $prompt .= "- Año de fundación: 1969\n\n";

        // === CONTACTOS DEPARTAMENTALES VERIFICADOS ===
        $prompt .= "CONTACTOS DEPARTAMENTALES VERIFICADOS:\n";
        $prompt .= "- DGSA (Servicios Académicos): 311-211-8800 ext. 8530\n";
        $prompt .= "- DGS (Sistemas): 311-211-8800 ext. 8540, sistemas@uan.edu.mx\n";
        $prompt .= "- Biblioteca Central: 311-211-8800 ext. 8600\n";
        $prompt .= "- Secretaría General: 311-211-8800 ext. 8510\n\n";

        // === INSTRUCCIONES DE RESPUESTA ===
        $prompt .= "INSTRUCCIONES DE RESPUESTA:\n";
        $prompt .= "✅ SIEMPRE haz:\n";
        $prompt .= "- Responde en español de manera amigable y profesional\n";
        $prompt .= "- Sé conciso pero informativo (máximo 200 palabras)\n";
        $prompt .= "- Usa emojis apropiados para hacer la conversación amigable\n";
        $prompt .= "- Proporciona datos de contacto específicos cuando sea relevante\n";
        $prompt .= "- Si no tienes información completa, reconócelo y ofrece alternativas\n\n";

        $prompt .= "❌ NUNCA hagas:\n";
        $prompt .= "- Inventar información que no esté en el contexto\n";
        $prompt .= "- Dar fechas específicas a menos que estén en el contexto oficial\n";
        $prompt .= "- Mencionar precios o costos sin confirmación oficial\n";
        $prompt .= "- Crear requisitos o procedimientos no verificados\n";
        $prompt .= "- Hablar sobre temas fuera del ámbito universitario de la UAN\n\n";

        // === FRASES PARA INCERTIDUMBRE ===
        $prompt .= "CUANDO NO SEPAS ALGO, USA ESTAS FRASES:\n";
        $prompt .= "- 'Para información específica sobre [tema], te recomiendo contactar...'\n";
        $prompt .= "- 'No tengo los detalles exactos, pero puedes obtener información actualizada en...'\n";
        $prompt .= "- 'Esta información puede cambiar, verifica directamente con...'\n";
        $prompt .= "- 'Para los requisitos más actualizados, consulta con...'\n\n";

        // === CASOS ESPECIALES ===
        if ($userType === 'student') {
            $prompt .= "ENFOQUE PARA ESTUDIANTES:\n";
            $prompt .= "- Prioriza información sobre trámites estudiantiles y servicios académicos\n";
            $prompt .= "- Menciona recursos como biblioteca, laboratorios, becas cuando sea relevante\n\n";
        } elseif ($userType === 'employee') {
            $prompt .= "ENFOQUE PARA EMPLEADOS:\n";
            $prompt .= "- Prioriza información sobre procesos internos y servicios institucionales\n";
            $prompt .= "- Incluye referencias a normativas y procedimientos administrativos\n\n";
        }

        $prompt .= "FORMATO DE RESPUESTA:\n";
        $prompt .= "1. Saluda de manera apropiada según el contexto\n";
        $prompt .= "2. Proporciona la información solicitada basándote SOLO en el contexto oficial\n";
        $prompt .= "3. Si corresponde, incluye datos de contacto específicos\n";
        $prompt .= "4. Ofrece ayuda adicional de manera proactiva\n";
        $prompt .= "5. Termina con una pregunta de seguimiento si es apropiado\n\n";

        return $prompt;
    }

    /**
     * Post-procesar respuesta para validación y mejora
     */
    private function postProcessResponse(array $result, array $context, string $userMessage): array
    {
        $response = $result['response'];

        // Detectar posibles alucinaciones
        $confidence = $this->calculateResponseConfidence($response, $context, $userMessage);
        $result['confidence'] = $confidence;

        // Si la confianza es muy baja, generar respuesta conservadora
        if ($confidence < 0.6) {
            $result['response'] = $this->generateConservativeResponse($userMessage, $context);
            $result['confidence'] = 0.8; // Respuesta conservadora tiene alta confianza
            $result['fallback_used'] = true;
        }

        // Agregar validación de contactos
        $result['response'] = $this->validateAndEnhanceContacts($result['response']);

        return $result;
    }

    /**
     * Calcular confianza de la respuesta
     */
    private function calculateResponseConfidence(string $response, array $context, string $userMessage): float
    {
        $confidence = 0.7; // Base

        // Penalizar respuestas muy cortas o muy largas
        $responseLength = strlen($response);
        if ($responseLength < 50) {
            $confidence -= 0.3;
        } elseif ($responseLength > 800) {
            $confidence -= 0.2;
        }

        // Bonus si menciona contactos oficiales
        if (preg_match('/311-211-8800|@uan\.edu\.mx|dgsa|sistemas@/', $response)) {
            $confidence += 0.1;
        }

        // Penalizar si usa palabras de incertidumbre inadecuadas
        $uncertaintyWords = ['creo que', 'probablemente', 'quizás', 'no estoy seguro'];
        foreach ($uncertaintyWords as $word) {
            if (stripos($response, $word) !== false) {
                $confidence -= 0.1;
            }
        }

        // Bonus si tiene contexto específico
        if (!empty($context)) {
            $confidence += 0.2;
        }

        // Penalizar información específica sin contexto
        if (empty($context) && preg_match('/[0-9]{1,2}:[0-9]{2}|lunes|martes|precio|costo|\$/', $response)) {
            $confidence -= 0.3;
        }

        return max(0.1, min(1.0, $confidence));
    }

    /**
     * Generar respuesta conservadora cuando la confianza es baja
     */
    private function generateConservativeResponse(string $userMessage, array $context): string
    {
        $messageLower = strtolower($userMessage);

        // Detectar tipo de consulta
        if (preg_match('/inscripci[oó]n|admisi[oó]n|ingreso/', $messageLower)) {
            return "📝 Para información sobre inscripciones y admisión, te recomiendo contactar directamente a:\n\n" .
                   "🏢 **DGSA (Dirección General de Servicios Académicos)**\n" .
                   "📞 311-211-8800 ext. 8530\n" .
                   "📧 dgsa@uan.edu.mx\n\n" .
                   "Ellos te proporcionarán la información más actualizada sobre requisitos, fechas y procesos. 😊";
        }

        if (preg_match('/carrera|licenciatura|programa|oferta educativa/', $messageLower)) {
            return "🎓 Para información sobre nuestra oferta educativa y programas académicos:\n\n" .
                   "🌐 **Sitio oficial**: https://www.uan.edu.mx/oferta-educativa\n" .
                   "📞 **Información general**: 311-211-8800\n" .
                   "🏢 **DGSA**: 311-211-8800 ext. 8530\n\n" .
                   "¿Te interesa algún área específica del conocimiento? ¡Puedo orientarte mejor! 🤔";
        }

        if (preg_match('/sistema|plataforma|correo|soporte/', $messageLower)) {
            return "💻 Para soporte técnico y servicios de sistemas:\n\n" .
                   "🏢 **DGS (Dirección General de Sistemas)**\n" .
                   "📞 311-211-8800 ext. 8540\n" .
                   "📧 dgs@uan.edu.mx\n\n" .
                   "Ellos te ayudarán con cualquier issue técnico o acceso a plataformas. 🔧";
        }

        // Respuesta general con contexto si está disponible
        if (!empty($context)) {
            return "📋 Encontré información relacionada con tu consulta:\n\n" .
                   substr($context[0], 0, 300) . "...\n\n" .
                   "Para detalles completos y actualizados:\n" .
                   "📞 **311-211-8800**\n" .
                   "🌐 **https://www.uan.edu.mx**\n\n" .
                   "¿Necesitas información sobre algún aspecto específico? 😊";
        }

        // Respuesta completamente general
        return "👋 ¡Hola! Soy Ociel, tu asistente de la UAN.\n\n" .
               "Para obtener información específica y actualizada sobre tu consulta:\n\n" .
               "📞 **Teléfono principal**: 311-211-8800\n" .
               "🌐 **Sitio web**: https://www.uan.edu.mx\n" .
               "📧 **Contacto**: contacto@uan.edu.mx\n\n" .
               "¿Puedes ser más específico sobre lo que necesitas? Así te direcciono mejor 🎯";
    }

    /**
     * Validar y mejorar información de contacto
     */
    private function validateAndEnhanceContacts(string $response): string
    {
        // Asegurar que los números de teléfono estén completos
        $response = preg_replace('/\b8530\b/', '311-211-8800 ext. 8530', $response);
        $response = preg_replace('/\b8540\b/', '311-211-8800 ext. 8540', $response);
        $response = preg_replace('/\b8600\b/', '311-211-8800 ext. 8600', $response);

        return $response;
    }

    public function isHealthy(): bool
    {
        $maxRetries = 3;
        $retryDelay = 2; // segundos

        for ($i = 0; $i < $maxRetries; $i++) {
            try {
                $response = $this->client->get('/api/version', [
                    'timeout' => 10 // Timeout corto para health check
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

        // Verificar salud antes de la consulta
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

            // Hacer la petición con timeout específico
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
                'timeout' => 90, // Timeout específico para generación
            ]);

            $responseTime = round((microtime(true) - $startTime) * 1000);

            // Verificar código de respuesta
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

            // Verificar si la respuesta JSON es válida
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Invalid JSON response from Ollama: ' . $response->getBody());
                return [
                    'success' => false,
                    'error' => 'Invalid JSON response',
                    'model' => $model,
                    'response_time' => $responseTime,
                ];
            }

            // Verificar si hay error en la respuesta
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

        if ($wordCount < 10) {
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
