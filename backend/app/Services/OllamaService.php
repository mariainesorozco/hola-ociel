<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
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
            'timeout' => 60,
            'connect_timeout' => 10,
        ]);
    }

    /**
     * Verificar si Ollama está funcionando
     */
    public function isHealthy(): bool
    {
        try {
            $response = $this->client->get('/api/version');
            return $response->getStatusCode() === 200;
        } catch (RequestException $e) {
            Log::error('Ollama health check failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener lista de modelos disponibles
     */
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

    /**
     * Generar respuesta usando el modelo especificado
     */
    public function generateResponse(string $prompt, array $options = []): array
    {
        $model = $options['model'] ?? $this->primaryModel;
        $temperature = $options['temperature'] ?? 0.7;
        $maxTokens = $options['max_tokens'] ?? 1000;

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
                        'top_p' => 0.9,
                        'top_k' => 40,
                    ]
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            $responseTime = round((microtime(true) - $startTime) * 1000);

            return [
                'success' => true,
                'response' => $data['response'] ?? '',
                'model' => $model,
                'response_time' => $responseTime,
                'tokens_evaluated' => $data['eval_count'] ?? 0,
                'tokens_generated' => $data['eval_count'] ?? 0,
            ];

        } catch (RequestException $e) {
            Log::error('Ollama generation failed: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'model' => $model,
                'response_time' => 0,
            ];
        }
    }

    /**
     * Generar embeddings para búsqueda semántica
     */
    public function generateEmbedding(string $text): array
    {
        $cacheKey = 'embedding_' . md5($text);

        // Verificar cache primero
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

            // Cachear por 1 hora
            Cache::put($cacheKey, $embedding, 3600);

            return $embedding;

        } catch (RequestException $e) {
            Log::error('Ollama embedding failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Generar respuesta contextualizada para Ociel
     */
    public function generateOcielResponse(string $userMessage, array $context = [], string $userType = 'public', string $department = null): array
    {
        $systemPrompt = $this->buildOcielSystemPrompt($context, $userType, $department);
        $fullPrompt = $systemPrompt . "\n\nUsuario: " . $userMessage . "\n\nOciel:";

        // Usar modelo secundario para consultas simples, primario para complejas
        $useSecondaryModel = $this->isSimpleQuery($userMessage);
        $model = $useSecondaryModel ? $this->secondaryModel : $this->primaryModel;

        return $this->generateResponse($fullPrompt, [
            'model' => $model,
            'temperature' => 0.7,
            'max_tokens' => 800
        ]);
    }

    /**
     * Construir prompt del sistema para Ociel
     */
    private function buildOcielSystemPrompt(array $context, string $userType, ?string $department): string
    {
        $prompt = "Eres Ociel, el asistente virtual oficial de la Universidad Autónoma de Nayarit (UAN). ";
        $prompt .= "Tu misión es brindar información precisa, actualizada y útil sobre la universidad.\n\n";

        $prompt .= "INFORMACIÓN DEL USUARIO:\n";
        $prompt .= "- Tipo: " . ucfirst($userType) . "\n";

        if ($department) {
            $prompt .= "- Departamento de interés: " . $department . "\n";
        }

        if (!empty($context)) {
            $prompt .= "\nINFORMACIÓN RELEVANTE DE LA UAN:\n";
            foreach ($context as $item) {
                $prompt .= "• " . $item . "\n";
            }
        }

        $prompt .= "\nINSTRUCCIONES:\n";
        $prompt .= "- Responde en español de manera amigable y profesional\n";
        $prompt .= "- Sé conciso pero informativo\n";
        $prompt .= "- Si no tienes información específica, indica cómo pueden obtener ayuda\n";
        $prompt .= "- Proporciona datos de contacto cuando sea relevante\n";
        $prompt .= "- Mantén un tono conversacional pero académico\n";
        $prompt .= "- Si la consulta requiere atención especializada, dirige al departamento correcto\n";
        $prompt .= "- Usa emojis apropiados ocasionalmente para hacer la conversación más amigable\n\n";

        return $prompt;
    }

    /**
     * Determinar si una consulta es simple
     */
    private function isSimpleQuery(string $message): bool
    {
        $simplePatterns = [
            '/^(hola|hi|hello)/i',
            '/^(gracias|thanks)/i',
            '/^(adiós|bye)/i',
            '/^(sí|no|ok)/i',
            '/\?$/',  // Preguntas simples
        ];

        $wordCount = str_word_count($message);

        // Si tiene menos de 10 palabras o coincide con patrones simples
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

    /**
     * Verificar que los modelos estén disponibles
     */
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

    /**
     * Obtener estadísticas de uso
     */
    public function getUsageStats(): array
    {
        // Esto se podría expandir para incluir métricas reales
        return [
            'total_requests' => Cache::get('ollama_requests', 0),
            'average_response_time' => Cache::get('ollama_avg_time', 0),
            'models_used' => Cache::get('ollama_models_used', []),
            'health_status' => $this->isHealthy()
        ];
    }
}
