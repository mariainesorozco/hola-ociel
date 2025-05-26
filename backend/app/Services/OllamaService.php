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

        $response = $this->generateResponse($fullPrompt, [
            'model' => $model,
            'temperature' => 0.7,
            'max_tokens' => 1200 // Incrementado para respuestas más detalladas
        ]);

        // Aplicar mejoras de formato a la respuesta
        if ($response['success'] && !empty($response['response'])) {
            $response['response'] = $this->enhanceResponseFormat($response['response']);

            // Agregar información de contacto específica si no está presente
            $response['response'] = $this->ensureContactInfo($response['response'], $department, $userType);
        }

        return $response;
    }

    /**
     * Asegurar que la respuesta incluya información de contacto relevante
     */
    private function ensureContactInfo(string $response, ?string $department, string $userType): string
    {
        // Si ya contiene información de contacto, no agregar más
        if (preg_match('/📞|teléfono|tel:|email|contacto/i', $response)) {
            return $response;
        }

        // Agregar información de contacto según el departamento
        $contactInfo = $this->getContactInfoByDepartment($department);

        if ($contactInfo) {
            $response .= "\n\n📞 **Más información:**\n";
            $response .= $contactInfo;
        }

        return $response;
    }

    /**
     * Obtener información de contacto específica por departamento
     */
    private function getContactInfoByDepartment(?string $department): string
    {
        $contacts = [
            'DGAE' => "📞 DGAE: 311-211-8800 ext. 8530\n📧 dgae@uan.edu.mx",
            'UAM' => "📞 Medicina: 311-211-8800 ext. 8630\n📧 direccion.medicina@uan.edu.mx",
            'UACBI' => "📞 Ingenierías: 311-211-8800 ext. 8600\n📧 direccion@uan.edu.mx",
            'UACS' => "📞 Ciencias Sociales: 311-211-8800 ext. 8610\n📧 direccion.cs@uan.edu.mx",
            'DGS' => "📞 Sistemas: 311-211-8800 ext. 8540\n📧 dgs@uan.edu.mx"
        ];

        return $contacts[$department] ?? "📞 Información general: 311-211-8800\n🌐 www.uan.edu.mx";
    }

    /**
     * Determinar si una consulta es compleja y requiere respuesta detallada
     */
    private function isComplexQuery(string $message): bool
    {
        $complexIndicators = [
            'plan de estudios', 'requisitos', 'proceso de', 'cómo puedo',
            'información detallada', 'explicame', 'diferencia entre',
            'comparar', 'ventajas', 'desventajas', 'modalidades'
        ];

        $messageLower = strtolower($message);

        foreach ($complexIndicators as $indicator) {
            if (str_contains($messageLower, $indicator)) {
                return true;
            }
        }

        // Si el mensaje es largo (más de 15 palabras), probablemente es complejo
        return str_word_count($message) > 15;
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

        $prompt .= "\nFORMATO DE RESPUESTA REQUERIDO:\n";
        $prompt .= "- Usa una estructura clara con títulos y subtítulos\n";
        $prompt .= "- Incluye emojis relevantes al inicio de cada sección (🎓 📋 📞 etc.)\n";
        $prompt .= "- Organiza la información en listas cuando sea apropiado\n";
        $prompt .= "- Resalta datos importantes como duración, costos, fechas\n";
        $prompt .= "- Siempre incluye información de contacto específica al final\n";
        $prompt .= "- Usa un tono amigable pero profesional\n\n";

        $prompt .= "INSTRUCCIONES ESPECÍFICAS:\n";
        $prompt .= "- Responde en español de manera estructurada y organizada\n";
        $prompt .= "- Sé conciso pero completo en la información\n";
        $prompt .= "- Si no tienes información específica, indica cómo pueden obtener ayuda\n";
        $prompt .= "- Proporciona datos de contacto relevantes\n";
        $prompt .= "- Mantén un tono conversacional pero académico\n";
        $prompt .= "- Si la consulta requiere atención especializada, dirige al departamento correcto\n";
        $prompt .= "- Estructura las respuestas con subtítulos claros\n\n";

        // Agregar plantillas de respuesta según el tipo de consulta
        $prompt .= "PLANTILLAS DE RESPUESTA:\n\n";

        $prompt .= "Para CARRERAS/PROGRAMAS ACADÉMICOS:\n";
        $prompt .= "🎓 [Nombre de la Carrera] - UAN\n";
        $prompt .= "📋 Información general:\n";
        $prompt .= "• Duración: [X años/semestres]\n";
        $prompt .= "• Modalidad: [Presencial/Virtual/Mixta]\n";
        $prompt .= "• Ubicación: [Campus/Sede]\n";
        $prompt .= "📚 Plan de estudios: [descripción breve]\n";
        $prompt .= "🎯 Campo laboral: [áreas de trabajo]\n";
        $prompt .= "📞 Contacto: [información específica]\n\n";

        $prompt .= "Para TRÁMITES/SERVICIOS:\n";
        $prompt .= "📝 [Nombre del Trámite]\n";
        $prompt .= "📋 Requisitos:\n";
        $prompt .= "• [Listar requisitos]\n";
        $prompt .= "⏰ Proceso:\n";
        $prompt .= "• [Pasos a seguir]\n";
        $prompt .= "📍 Ubicación: [dónde realizar el trámite]\n";
        $prompt .= "📞 Más información: [contacto específico]\n\n";

        $prompt .= "Para INFORMACIÓN GENERAL:\n";
        $prompt .= "🏛️ Universidad Autónoma de Nayarit\n";
        $prompt .= "[Información solicitada organizadamente]\n";
        $prompt .= "📞 Contacto general: 311-211-8800\n\n";

        return $prompt;
    }

    // Método adicional para post-procesar respuestas y mejorar formato
    private function enhanceResponseFormat(string $response): string
    {
        // Mejorar la estructura de las respuestas generadas
        $enhanced = $response;

        // Asegurar que hay emojis al inicio de títulos principales
        $enhanced = preg_replace('/^([A-ZÁÉÍÓÚ][^:\n]*):?$/m', '🎯 $1:', $enhanced);

        // Mejorar formato de listas
        $enhanced = preg_replace('/^[\*\-]\s+(.+)$/m', '• $1', $enhanced);

        // Resaltar información importante (números, fechas, etc.)
        $enhanced = preg_replace('/(\d+\s*(años?|meses?|semestres?))/i', '**$1**', $enhanced);
        $enhanced = preg_replace('/(\$[\d,]+)/i', '**$1**', $enhanced);

        // Mejorar información de contacto
        $enhanced = preg_replace('/(Tel(?:éfono)?:?\s*)([\d\-\s\(\)ext\.]+)/i', '📞 $2', $enhanced);
        $enhanced = preg_replace('/(Email:?\s*)([\w\.\-]+@[\w\.\-]+)/i', '📧 $2', $enhanced);

        return $enhanced;
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
