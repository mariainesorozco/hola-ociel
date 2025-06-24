<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class GeminiService
{
    private $client;
    private $apiKey;
    private $model;
    private $endpoint;
    private $timeout;
    private $maxTokens;
    private $temperature;
    private $enabled;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->model = config('services.gemini.model', 'gemini-1.5-flash');
        $this->endpoint = config('services.gemini.endpoint');
        $this->timeout = config('services.gemini.timeout', 30);
        $this->maxTokens = config('services.gemini.max_tokens', 1000);
        $this->temperature = config('services.gemini.temperature', 0.2);
        $this->enabled = config('services.gemini.enabled', false);

        $this->client = new Client([
            'timeout' => $this->timeout,
            'connect_timeout' => 10,
            'http_errors' => false,
            'headers' => [
                'Content-Type' => 'application/json',
            ]
        ]);
    }

    /**
     * Verificar si Gemini está habilitado y configurado
     */
    public function isEnabled(): bool
    {
        return $this->enabled && !empty($this->apiKey);
    }

    /**
     * Verificar estado de salud del servicio
     */
    public function isHealthy(): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $cacheKey = 'gemini_health_check';
        
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            // Test simple con Gemini
            $testPrompt = "Responde solo con 'OK' si me puedes escuchar.";
            $response = $this->generateResponse($testPrompt, ['max_tokens' => 10]);
            
            $isHealthy = $response['success'] && str_contains(strtolower($response['response'] ?? ''), 'ok');
            
            // Cache por 5 minutos
            Cache::put($cacheKey, $isHealthy, 300);
            
            return $isHealthy;

        } catch (\Exception $e) {
            Log::error('Gemini health check failed: ' . $e->getMessage());
            Cache::put($cacheKey, false, 60); // Cache negativo por 1 minuto
            return false;
        }
    }

    /**
     * Generar respuesta con Gemini usando el prompt optimizado de Ociel
     */
    public function generateOcielResponse(string $userMessage, array $context = [], string $userType = 'public', string $department = null): array
    {
        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'error' => 'Gemini service is not enabled',
                'fallback_reason' => 'service_disabled'
            ];
        }

        // Construir prompt optimizado para Ociel con Gemini
        $systemPrompt = $this->buildGeminiOcielPrompt($context, $userType, $department);
        $fullPrompt = $systemPrompt . "\n\nCONSULTA DEL USUARIO: " . $userMessage . "\n\nRESPUESTA DE OCIEL:";

        $result = $this->generateResponse($fullPrompt, [
            'temperature' => 0.15, // Muy baja para precisión
            'max_tokens' => 800,
        ]);

        // Post-procesar respuesta para formato Ociel
        if ($result['success']) {
            $result['response'] = $this->cleanGeminiResponse($result['response']);
            $result['model'] = 'gemini-' . $this->model;
        }

        return $result;
    }

    /**
     * Generar respuesta con Gemini
     */
    public function generateResponse(string $prompt, array $options = []): array
    {
        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'error' => 'Gemini service is not enabled',
                'response_time' => 0,
            ];
        }

        try {
            $startTime = microtime(true);

            $requestData = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => $options['temperature'] ?? $this->temperature,
                    'maxOutputTokens' => $options['max_tokens'] ?? $this->maxTokens,
                    'topP' => $options['top_p'] ?? 0.8,
                    'topK' => $options['top_k'] ?? 40,
                ]
            ];

            $url = $this->endpoint . '/models/' . $this->model . ':generateContent?key=' . $this->apiKey;

            $response = $this->client->post($url, [
                'json' => $requestData,
                'timeout' => $this->timeout,
            ]);

            $responseTime = round((microtime(true) - $startTime) * 1000);

            if ($response->getStatusCode() !== 200) {
                Log::error('Gemini HTTP error: ' . $response->getStatusCode() . ' - ' . $response->getBody());
                return [
                    'success' => false,
                    'error' => 'HTTP error: ' . $response->getStatusCode(),
                    'response_time' => $responseTime,
                ];
            }

            $data = json_decode($response->getBody(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Invalid JSON response from Gemini: ' . $response->getBody());
                return [
                    'success' => false,
                    'error' => 'Invalid JSON response',
                    'response_time' => $responseTime,
                ];
            }

            if (isset($data['error'])) {
                Log::error('Gemini API error: ' . json_encode($data['error']));
                return [
                    'success' => false,
                    'error' => $data['error']['message'] ?? 'Unknown API error',
                    'response_time' => $responseTime,
                ];
            }

            // Extraer texto de la respuesta de Gemini
            $responseText = '';
            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                $responseText = $data['candidates'][0]['content']['parts'][0]['text'];
            }

            return [
                'success' => true,
                'response' => $responseText,
                'model' => $this->model,
                'response_time' => $responseTime,
                'usage' => [
                    'prompt_tokens' => $data['usageMetadata']['promptTokenCount'] ?? 0,
                    'completion_tokens' => $data['usageMetadata']['candidatesTokenCount'] ?? 0,
                    'total_tokens' => $data['usageMetadata']['totalTokenCount'] ?? 0,
                ]
            ];

        } catch (RequestException $e) {
            $responseTime = round((microtime(true) - $startTime) * 1000);
            Log::error('Gemini request failed: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => 'Request failed: ' . $e->getMessage(),
                'response_time' => $responseTime,
            ];

        } catch (\Exception $e) {
            Log::error('Unexpected error in Gemini generation: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Unexpected error occurred',
                'response_time' => 0,
            ];
        }
    }

    /**
     * Construir prompt específico para Gemini con personalidad Ociel
     */
    private function buildGeminiOcielPrompt(array $context, string $userType, ?string $department): string
    {
        $prompt = "Eres Ociel 🐯, el Agente Virtual Senpai de la Universidad Autónoma de Nayarit (UAN).\n\n";
        
        $prompt .= "PERSONALIDAD OCIEL:\n";
        $prompt .= "- Carismático y alegre: Entusiasta, positivo, generas confianza\n";
        $prompt .= "- Protector y empático: Buscas que la persona se sienta acompañada\n";
        $prompt .= "- Claro y preciso: Información completa y confiable\n";
        $prompt .= "- Accesible y cercano: Compañero solidario, sin tecnicismos\n";
        $prompt .= "- Responsable: Tono amigable sin trivializar temas importantes\n\n";
        
        $prompt .= "REGLAS CRÍTICAS:\n";
        $prompt .= "❌ JAMÁS uses formato markdown visible (###, **, etc.)\n";
        $prompt .= "❌ NO inventes información que no esté en el contexto\n";
        $prompt .= "❌ NO agregues contactos genéricos sin base documental\n";
        $prompt .= "✅ SOLO usa información EXACTA del contexto\n";
        $prompt .= "✅ Si no tienes información, dilo claramente\n";
        $prompt .= "✅ Mantén tono cálido y empático siempre\n\n";
        
        $prompt .= "ESTRUCTURA DE RESPUESTA:\n";
        $prompt .= "1. Saludo empático (🐯)\n";
        $prompt .= "2. Información del contexto (máx 3 líneas)\n";
        $prompt .= "3. Datos específicos si existen\n";
        $prompt .= "4. Cierre con oferta de apoyo (🐾)\n\n";
        
        // Contexto específico del usuario
        $prompt .= "USUARIO: " . ucfirst($userType);
        if ($department) {
            $prompt .= " - Departamento: " . $department;
        }
        $prompt .= "\n\n";

        // Contexto oficial
        if (!empty($context)) {
            $prompt .= "INFORMACIÓN OFICIAL DISPONIBLE:\n";
            foreach (array_slice($context, 0, 2) as $index => $item) {
                $prompt .= "FUENTE " . ($index + 1) . ": " . substr($item, 0, 400) . "\n\n";
            }
        } else {
            $prompt .= "⚠️ CONTEXTO: Sin información específica disponible.\n";
            $prompt .= "ACCIÓN: Responder honestamente que no tienes información específica.\n\n";
        }

        return $prompt;
    }

    /**
     * Limpiar respuesta de Gemini para formato Ociel
     */
    private function cleanGeminiResponse(string $response): string
    {
        // Eliminar formato markdown estructurado
        $response = preg_replace('/#{1,6}\s*(.+)$/m', '$1', $response);
        $response = preg_replace('/\*\*(.+?)\*\*/', '$1', $response);
        $response = preg_replace('/^\s*[-*•]\s+/m', '- ', $response);
        
        // Normalizar espaciado
        $response = preg_replace('/\n{3,}/', "\n\n", $response);
        $response = trim($response);
        
        // Asegurar cierre empático Ociel si no lo tiene
        if (!preg_match('/🐾|🐯/', $response)) {
            $response .= "\n\n¿Necesitas algo más? Estoy aquí para apoyarte 🐾";
        }

        return $response;
    }

    /**
     * Obtener estadísticas de uso
     */
    public function getUsageStats(): array
    {
        return [
            'service' => 'Gemini AI',
            'model' => $this->model,
            'enabled' => $this->enabled,
            'healthy' => $this->isHealthy(),
            'total_requests' => Cache::get('gemini_requests', 0),
            'average_response_time' => Cache::get('gemini_avg_time', 0),
        ];
    }
}