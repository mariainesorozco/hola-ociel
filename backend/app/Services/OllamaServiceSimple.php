<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class OllamaServiceSimple
{
    private $client;
    private $baseUrl;
    private $primaryModel;
    private $secondaryModel;
    private $embeddingModel;

    public function __construct()
    {
        $this->baseUrl = config('services.ollama.url', 'http://localhost:11434');
        $this->primaryModel = config('services.ollama.primary_model', 'solar:10.7b');
        $this->secondaryModel = config('services.ollama.secondary_model', 'llama3.2:3b');
        $this->embeddingModel = config('services.ollama.embedding_model', 'nomic-embed-text');

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 120,
            'connect_timeout' => 30,
            'read_timeout' => 120,
            'http_errors' => false,
        ]);
    }

    /**
     * Generar respuesta simple y directa para Ociel
     */
    public function generateOcielResponse(string $userMessage, array $context = [], string $userType = 'public', string $department = null): array
    {
        $systemPrompt = $this->buildSimpleOcielPrompt($context);
        $fullPrompt = $systemPrompt . "\n\nUsuario pregunta: " . $userMessage . "\n\nOciel responde:";

        $result = $this->generateResponse($fullPrompt, [
            'model' => $this->primaryModel,
            'temperature' => 0.2,
            'max_tokens' => 400,
            'top_p' => 0.9,
            'repeat_penalty' => 1.1
        ]);

        if ($result['success']) {
            $result['response'] = $this->cleanResponse($result['response']);
        }

        return $result;
    }

    /**
     * Prompt SÚPER SIMPLE para Ociel
     */
    private function buildSimpleOcielPrompt(array $context): string
    {
        $prompt = "Eres Ociel 🐯, asistente amigable de la Universidad Autónoma de Nayarit (UAN) ubicada en Tepic, Nayarit, México.\n\n";
        
        $prompt .= "IMPORTANTE: UAN = Universidad Autónoma de Nayarit (Nayarit, México) - NO confundir con Universidad Autónoma de Nuevo León.\n\n";
        
        $prompt .= "REGLAS:\n";
        $prompt .= "- Habla como un amigo que ayuda\n";
        $prompt .= "- NO uses formato markdown (###, **)\n";
        $prompt .= "- NO respondas con preguntas estructuradas\n";
        $prompt .= "- Responde directamente lo que se pregunta\n";
        $prompt .= "- Si no sabes algo, dilo honestamente\n";
        $prompt .= "- Termina con 🐾\n\n";

        if (!empty($context)) {
            $prompt .= "INFORMACIÓN DISPONIBLE:\n";
            foreach (array_slice($context, 0, 1) as $item) {
                $cleanItem = strip_tags($item);
                $cleanItem = preg_replace('/\*\*.*?\*\*/', '', $cleanItem);
                $cleanItem = preg_replace('/###.*/', '', $cleanItem);
                $prompt .= substr($cleanItem, 0, 300) . "\n\n";
            }
        } else {
            $prompt .= "Sin información específica disponible.\n\n";
        }

        return $prompt;
    }

    /**
     * Limpiar respuesta simple
     */
    private function cleanResponse(string $response): string
    {
        // Eliminar formato markdown
        $response = preg_replace('/#{1,6}\s*(.+)$/m', '$1', $response);
        $response = preg_replace('/\*\*(.+?)\*\*/', '$1', $response);
        $response = preg_replace('/^\s*[-*•]\s+/m', '', $response);
        
        // Limpiar líneas vacías
        $response = preg_replace('/\n{3,}/', "\n\n", $response);
        $response = trim($response);
        
        // Asegurar cierre empático
        if (!str_contains($response, '🐾') && !str_contains($response, '🐯')) {
            $response .= "\n\n¿Necesitas algo más? Estoy aquí para apoyarte 🐾";
        }

        return $response;
    }

    // Métodos heredados del OllamaService original
    public function isHealthy(): bool
    {
        try {
            $response = $this->client->get('/api/version', ['timeout' => 10]);
            return $response->getStatusCode() === 200;
        } catch (RequestException $e) {
            return false;
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
                        'repeat_penalty' => $options['repeat_penalty'] ?? 1.0,
                    ]
                ],
                'timeout' => 90,
            ]);

            $responseTime = round((microtime(true) - $startTime) * 1000);

            if ($response->getStatusCode() !== 200) {
                return [
                    'success' => false,
                    'error' => 'HTTP error: ' . $response->getStatusCode(),
                    'model' => $model,
                    'response_time' => $responseTime,
                ];
            }

            $data = json_decode($response->getBody(), true);

            if (isset($data['error'])) {
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
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Request failed: ' . $e->getMessage(),
                'model' => $model,
                'response_time' => 0,
            ];
        }
    }
}