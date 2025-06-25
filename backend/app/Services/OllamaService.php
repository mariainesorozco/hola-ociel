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
        // $this->primaryModel = config('services.gemini.primary_model', 'gemini-1.5-flash:latest');
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
    public function generateOcielResponse(string $userMessage, array $context = [], string $userType = 'public', string $department = null, array $conversationHistory = []): array
    {
        $systemPrompt = $this->buildOptimizedOcielPrompt($context, $userType, $department, $conversationHistory);
        $fullPrompt = $systemPrompt . "\n\nCONSULTA DEL USUARIO: " . $userMessage . "\n\nRESPUESTA DE OCIEL:";

        // Usar modelo secundario para consultas simples, primario para complejas
        $useSecondaryModel = $this->isSimpleQuery($userMessage);
        $model = $useSecondaryModel ? $this->secondaryModel : $this->primaryModel;

        $result = $this->generateResponse($fullPrompt, [
            'model' => $model,
            'temperature' => 0.1,  // Temperatura muy baja para máxima precisión
            'max_tokens' => 600,   // Respuestas más cortas y directas
            'top_p' => 0.9,        // Mayor enfoque en palabras relevantes
            'repeat_penalty' => 1.2 // Evitar repeticiones más agresivamente
        ]);

        // Post-procesar respuesta para optimizar formato
        if ($result['success']) {
            $result = $this->postProcessOcielResponse($result, $context, $userMessage);
            // Aplicar limpieza ULTRA-AGRESIVA de markdown
            $result['response'] = $this->ultraCleanMarkdown($result['response']);
        }

        return $result;
    }

    /**
     * Construir prompt simplificado y directo para Ociel
     */
    private function buildOptimizedOcielPrompt(array $context, string $userType, ?string $department, array $conversationHistory = []): string
    {
        $prompt = "Eres Ociel, el asistente virtual amigable de la Universidad Autónoma de Nayarit (UAN).\n\n";

        $prompt .= "INSTRUCCIONES:\n";
        $prompt .= "- Responde de forma conversacional y natural\n";
        $prompt .= "- NO uses formato markdown ni estructura\n";
        $prompt .= "- Solo usa información del contexto si es relevante\n";
        $prompt .= "- Si no tienes información específica, dilo honestamente\n";
        $prompt .= "- Mantén un tono amigable como Ociel 🐯\n";
        $prompt .= "- Considera la conversación previa para dar continuidad al diálogo\n\n";

        // Agregar historial conversacional si existe
        if (!empty($conversationHistory)) {
            $prompt .= "CONVERSACIÓN PREVIA:\n";
            $recentHistory = array_slice($conversationHistory, -3); // Solo últimos 3 intercambios
            foreach ($recentHistory as $exchange) {
                $prompt .= "Usuario: " . $exchange['user_message'] . "\n";
                $prompt .= "Ociel: " . $exchange['bot_response'] . "\n\n";
            }
        }
        // Agregar contexto si está disponible
        if (!empty($context)) {
            $prompt .= "INFORMACIÓN DISPONIBLE:\n";
            foreach ($context as $idx => $item) {
                $prompt .= trim($item) . "\n\n";
            }
        }

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
        if ($confidence < 0.3) { // Threshold más bajo para usar menos fallbacks
            $result['response'] = $this->generateFallbackResponse($userMessage, $context);
            $result['confidence'] = 0.8;
            $result['fallback_used'] = true;
        } else {
            $result['response'] = $response;
        }

        // 4. Validar y limpiar contactos inventados (con filtros más específicos)
        if ($result['success']) {
            $result['response'] = $this->cleanFakeContactsMinimal($result['response'], $context);
        }

        return $result;
    }

    /**
     * Optimizar formato de respuesta para conversación natural
     */
    private function optimizeResponseFormat(string $response): string
    {
        // 1. ELIMINAR COMPLETAMENTE formato markdown visible
        $response = preg_replace('/📋\s*Información encontrada:\s*/i', '', $response);
        $response = preg_replace('/^#{1,6}\s*(.+)$/m', '$1', $response); // Quitar headers
        $response = preg_replace('/^\*\*([^*]+)\*\*:\s*/m', '', $response); // Quitar campos en negritas

        // 2. Eliminar secciones estructuradas
        $response = preg_replace('/### Descripción\s*/i', '', $response);
        $response = preg_replace('/### Contacto\s*/i', '', $response);
        $response = preg_replace('/\*\*Modalidad:\*\*/i', '', $response);
        $response = preg_replace('/\*\*Usuarios:\*\*/i', '', $response);
        $response = preg_replace('/\*\*Dependencia:\*\*/i', '', $response);
        $response = preg_replace('/\*\*Estado:\*\*/i', '', $response);
        $response = preg_replace('/\*\*Costo:\*\*/i', '', $response);

        // 3. Convertir listas a texto fluido
        $response = preg_replace('/^\* /m', '', $response);
        $response = preg_replace('/^- /m', '', $response);

        // 4. Limpiar múltiples saltos de línea
        $response = preg_replace('/\n{3,}/', "\n\n", $response);

        // 5. Eliminar líneas que quedaron vacías después de limpieza
        $response = preg_replace('/^\s*$/m', '', $response);
        $response = preg_replace('/\n{2,}/', "\n\n", $response);

        // 6. Asegurar que no hay líneas vacías al inicio o final
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

        // Bonus por tener contacto real del contexto
        if (preg_match('/\d{3}-\d{3}-\d{4}|ext\.\s*\d+|@uan\.edu\.mx/', $response)) {
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

        // Debe tener información útil (contacto o contenido específico)
        if (!preg_match('/📞|\d{3}-\d{3}-\d{4}|procedimiento|requisitos|información/', $response)) {
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

        // Respuestas específicas con personalidad Ociel Senpai
        if (preg_match('/inscripci[oó]n|admisi[oó]n/', $messageLower)) {
            return "¡Claro que sí! 🐯 Te ayudo con información sobre inscripciones y admisión.\n\n" .
                   "Te cuento que para estos temas es importante revisar la información más actualizada. Te recomiendo contactar directamente con la Secretaría Académica.\n\n" .
                   "Contacto: 311-211-8800 ext. 8520 - secretaria.academica@uan.edu.mx\n\n" .
                   "¿Hay algún proceso de inscripción específico sobre el que necesites información? Estoy aquí para apoyarte 🐾";
        }

        if (preg_match('/carrera|licenciatura/', $messageLower)) {
            return "¡Perfecto! 🐯 Te ayudo con información sobre carreras y programas académicos.\n\n" .
                   "Para conocer toda nuestra oferta educativa actualizada, te sugiero revisar la información oficial. Los datos más precisos los puedes obtener directamente.\n\n" .
                   "Para más información: 311-211-8800 - información general\n\n" .
                   "¿Te interesa información sobre alguna carrera en particular? Aquí estaré para lo que necesites 🐾";
        }

        if (preg_match('/sistema|soporte|plataforma/', $messageLower)) {
            return "¡Te ayudo con eso! 🐯 Para soporte técnico y sistemas estoy aquí.\n\n" .
                   "Los compañeros de la Dirección de Infraestructura y Servicios Tecnológicos son los expertos en estos temas. Te recomiendo contactarlos directamente.\n\n" .
                   "Contacto: 311-211-8800 ext. 8640 - sistemas@uan.edu.mx\n\n" .
                   "¿El problema es con alguna plataforma específica? Estoy aquí para apoyarte 🐾";
        }

        // Respuesta general con contexto si existe
        if (!empty($context)) {
            return "¡Hola! 🐯 Encontré información relacionada con tu consulta.\n\n" .
                   substr($context[0], 0, 200) . "...\n\n" .
                   "¿Necesitas que profundice en algún aspecto específico? Estoy aquí para apoyarte 🐾";
        }

        // Respuesta completamente general con personalidad Ociel
        return "¡Hola! Soy Ociel, tu compañero senpai digital 🐯\n\n" .
               "Estoy aquí para acompañarte y proporcionarte información específica de los servicios de nuestra universidad. Me especializo en ayudar a estudiantes, empleados y público general con todo lo que necesiten.\n\n" .
               "¿Sobre qué servicio específico necesitas información? Aquí estaré para lo que necesites 🐾";
    }

    /**
     * Limpiar contactos falsos inventados por el modelo
     */
    private function cleanFakeContactsMinimal(string $response, array $context): string
    {
        // Limpieza mínima para preservar contenido válido

        // Solo eliminar patrones claramente inventados
        $response = preg_replace('/555[-\s]?555[-\s]?5555/', '', $response);
        $response = preg_replace('/123[-\s]?456[-\s]?7890/', '', $response);
        $response = preg_replace('/ejemplo@uan\.edu\.mx/', '', $response);

        return trim($response);
    }

    private function cleanFakeContacts(string $response, array $context): string
    {
        Log::info('Cleaning fake contacts called', ['response_length' => strlen($response)]);

        // ESTRATEGIA SIMPLE: Eliminar TODOS los contactos inventados comunes

        // Eliminar CUALQUIER número de teléfono que parezca inventado
        // Patrones comunes: 3XX XXX XXXX, 3XX-XXX-XXXX, +52 3XX XXX XXXX
        $response = preg_replace('/\+?52\s?3\d{2}[-\s]?\d{3}[-\s]?\d{2,4}/', '', $response);
        $response = preg_replace('/3\d{2}[-\s]?\d{3}[-\s]?\d{2,4}/', '', $response);
        $response = preg_replace('/311[-\s]?211[-\s]?8800/', '', $response);

        // Eliminar CUALQUIER email @uan.edu.mx que parezca inventado
        $response = preg_replace('/\[?[a-zA-Z0-9._-]+@uan\.edu\.mx\]?/', '', $response);
        $response = preg_replace('/mailto:[a-zA-Z0-9._-]+@uan\.edu\.mx/', '', $response);

        // Limpiar líneas que quedaron vacías o solo con texto de enlace
        $response = preg_replace('/.*\[?\]?\(mailto:\).*$/m', '', $response);
        $response = preg_replace('/.*al teléfono\s*o por.*$/m', '', $response);
        $response = preg_replace('/.*puedes contactar.*al teléfono.*$/m', '', $response);
        $response = preg_replace('/.*\[.*\]\(mailto:.*\).*$/m', '', $response);

        // Si después de limpiar queda una sección de contacto vacía, eliminarla
        $response = preg_replace('/\*\*Contacto\*\*\s*\n\n/', '', $response);
        $response = preg_replace('/\*\*.*[Cc]ontacto.*\*\*\s*\n*$/', '', $response);

        // Limpiar líneas vacías múltiples
        $response = preg_replace('/\n{3,}/', "\n\n", $response);

        return trim($response);
    }

    /**
     * Limpieza profunda de markdown con múltiples pasadas
     */
    private function deepCleanMarkdown(string $response): string
    {
        // Pasada 1: Eliminar headers estructurados completamente
        $response = preg_replace('/📋\s*Información encontrada:\s*/i', '', $response);
        $response = preg_replace('/^#{1,6}\s*(.+)$/m', '$1', $response);
        $response = preg_replace('/### Descripción\s*/i', '', $response);
        $response = preg_replace('/### Contacto\s*/i', '', $response);
        $response = preg_replace('/### Procedimiento\s*/i', '', $response);
        $response = preg_replace('/### Requisitos\s*/i', '', $response);

        // Pasada 2: Eliminar campos en negritas estructurados
        $response = preg_replace('/^\*\*([^*]+)\*\*:\s*/m', '', $response);
        $response = preg_replace('/\*\*Modalidad:\*\*/i', '', $response);
        $response = preg_replace('/\*\*Usuarios:\*\*/i', '', $response);
        $response = preg_replace('/\*\*Dependencia:\*\*/i', '', $response);
        $response = preg_replace('/\*\*Estado:\*\*/i', '', $response);
        $response = preg_replace('/\*\*Costo:\*\*/i', '', $response);
        $response = preg_replace('/\*\*Categoria:\*\*/i', '', $response);
        $response = preg_replace('/\*\*Subcategoria:\*\*/i', '', $response);

        // Pasada 3: Convertir negritas restantes a texto normal
        $response = preg_replace('/\*\*(.+?)\*\*/i', '$1', $response);

        // Pasada 4: Eliminar listas estructuradas
        $response = preg_replace('/^\s*[-*•]\s+/m', '', $response);

        // Pasada 5: Limpiar líneas que quedaron solo con espacios
        $response = preg_replace('/^\s*$/m', '', $response);

        // Pasada 6: Normalizar saltos de línea
        $response = preg_replace('/\n{3,}/', "\n\n", $response);
        $response = preg_replace('/\n{2,}/', "\n\n", $response);

        // Pasada 7: Eliminar patrones específicos problemáticos
        $response = preg_replace('/^Modalidad:\s*/m', '', $response);
        $response = preg_replace('/^Usuarios:\s*/m', '', $response);
        $response = preg_replace('/^Dependencia:\s*/m', '', $response);
        $response = preg_replace('/^Estado:\s*/m', '', $response);
        $response = preg_replace('/^Costo:\s*/m', '', $response);

        return trim($response);
    }

    /**
     * Limpieza ULTRA-AGRESIVA para eliminar CUALQUIER formato markdown
     */
    private function ultraCleanMarkdown(string $response): string
    {
        // PRIMERA PASADA: Eliminar secciones completas problemáticas
        $response = preg_replace('/ANÁLISIS DEL CONTEXTO.*?(?=\n\n|\n[A-Z]|\n$|$)/s', '', $response);
        $response = preg_replace('/EXTRACCIÓN DE DATOS.*?(?=\n\n|\n[A-Z]|\n$|$)/s', '', $response);
        $response = preg_replace('/PASOS NUMERADOS.*?(?=\n\n|\n[A-Z]|\n$|$)/s', '', $response);
        $response = preg_replace('/RESTRICCIONES IMPORTANTES.*?(?=\n\n|\n[A-Z]|\n$|$)/s', '', $response);
        $response = preg_replace('/MEJOR RESPUESTA PRECISA Y CÁLIDA.*?(?=\n\n|\n[A-Z]|\n$|$)/s', '', $response);

        // SEGUNDA PASADA: Optimizar enlaces markdown
        $response = $this->optimizeMarkdownLinks($response);

        // TERCERA PASADA: Optimizar headers - mantener solo los útiles
        $response = preg_replace('/^[A-Z][A-Z\s]+$/m', '', $response); // Headers en mayúsculas técnicos
        $response = preg_replace('/#{4,6}\s*(.+)$/m', '### $1', $response); // Normalizar headers a h3
        $response = preg_replace('/^\*\*([A-Z\s]{20,})\*\*$/m', '', $response); // Headers largos en negritas

        // CUARTA PASADA: Limpiar campos técnicos pero mantener información útil
        $response = preg_replace('/^\*\*(Categoria|Subcategoria|Estado):\*\*\s*.*$/m', '', $response);
        $response = preg_replace('/^\*\*(Modalidad|Usuarios|Dependencia):\*\*\s*/m', '**$1:** ', $response);

        // QUINTA PASADA: Optimizar listas - mantener estructura pero limpiar
        $response = $this->optimizeMarkdownLists($response);

        // SEXTA PASADA: Eliminar múltiples cierres empáticos
        $response = $this->removeRedundantClosings($response);

        // SÉPTIMA PASADA: Limpieza AGRESIVA de cierres múltiples
        $response = $this->aggressiveClosingCleanup($response);

        // OCTAVA PASADA: Limpiar líneas vacías excesivas
        $response = preg_replace('/^\s*$/m', '', $response);
        $response = preg_replace('/\n{3,}/', "\n\n", $response);

        // OCTAVA PASADA: Verificar contenido útil
        $cleanResponse = trim($response);

        if (strlen($cleanResponse) < 50) {
            return "¡Hola! 🐯 Te ayudo con mucho gusto.\n\nSobre tu consulta, no tengo información específica en mi base de datos en este momento.\n\n**Contacto general:** 📞 311-211-8800\n🌐 www.uan.edu.mx\n\n¿Necesitas algo más? Estoy aquí para apoyarte 🐾";
        }

        // NOVENA PASADA: Asegurar un solo cierre empático apropiado
        $cleanResponse = $this->ensureSingleEmpathicClosing($cleanResponse);

        return $cleanResponse;
    }

    /**
     * Optimizar enlaces markdown para mejor experiencia
     */
    private function optimizeMarkdownLinks(string $text): string
    {
        // Convertir [texto](url) a formato optimizado
        $text = preg_replace_callback('/\[([^\]]+)\]\(([^\)]+)\)/', function($matches) {
            $linkText = $matches[1];
            $url = $matches[2];
            
            // Si son URLs simples como gmail.com, mostrar solo el dominio
            if (preg_match('/^https?:\/\/(www\.)?([^\/]+)\/?\??.*$/', $url, $urlMatches)) {
                $domain = $urlMatches[2];
                if (strtolower($linkText) === strtolower($domain) || 
                    strtolower($linkText) === 'www.' . strtolower($domain)) {
                    return $domain;
                }
                return "[$linkText]($url)"; // Mantener markdown para enlaces complejos
            }
            
            return "[$linkText]($url)";
        }, $text);
        
        return $text;
    }

    /**
     * Optimizar listas markdown manteniendo estructura útil
     */
    private function optimizeMarkdownLists(string $text): string
    {
        $lines = explode("\n", $text);
        $processedLines = [];
        $inList = false;

        foreach ($lines as $line) {
            $trimmedLine = trim($line);
            
            // Mantener listas útiles pero limpiar formato
            if (preg_match('/^[-*•]\s+(.+)$/', $trimmedLine, $matches)) {
                $listItem = $matches[1];
                // Solo mantener listas con contenido sustancial
                if (strlen($listItem) > 10 && !preg_match('/^(Categoria|Estado|Modalidad):\s*/', $listItem)) {
                    $processedLines[] = "- " . $listItem;
                    $inList = true;
                }
            } else {
                $processedLines[] = $line;
                $inList = false;
            }
        }

        return implode("\n", $processedLines);
    }

    /**
     * Eliminar múltiples cierres empáticos redundantes
     */
    private function removeRedundantClosings(string $text): string
    {
        // Patrones específicos de cierre redundante (más agresivos)
        $redundantPatterns = [
            '/¿Necesitas algo más\?\s*Estoy aquí para apoyarte[^\n]*🐾[^\n]*\n*/',
            '/¿Hay algo más en lo que pueda ayudarte\?[^\n]*\n*/',
            '/¿El problema persiste[^\n]*\?[^\n]*\n*/',
            '/¿Quieres que te ayude[^\n]*\?[^\n]*/',
            '/¿Necesitas ayuda con algo más\?[^\n]*\n*/'
        ];

        // Eliminar todos los patrones redundantes
        foreach ($redundantPatterns as $pattern) {
            $text = preg_replace($pattern, '', $text);
        }

        // Eliminar múltiples 🐾 consecutivos
        $text = preg_replace('/🐾\s*🐾+/', '🐾', $text);
        
        // Eliminar líneas que solo tienen espacios después de limpiar
        $text = preg_replace('/^\s*$/m', '', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Limpieza AGRESIVA de cierres múltiples
     */
    private function aggressiveClosingCleanup(string $text): string
    {
        // Identificar y conservar solo el último párrafo con contenido útil
        $paragraphs = explode("\n\n", $text);
        $cleanParagraphs = [];
        $foundMainContent = false;

        foreach ($paragraphs as $paragraph) {
            $cleanPara = trim($paragraph);
            
            // Saltar párrafos que son solo cierres empáticos
            if (preg_match('/^¿(Necesitas|Hay algo|El problema|Quieres)/', $cleanPara) && 
                strlen($cleanPara) < 100) {
                continue;
            }
            
            // Mantener párrafos con contenido sustancial
            if (strlen($cleanPara) > 20 && !preg_match('/^¿.*\?$/', $cleanPara)) {
                $cleanParagraphs[] = $cleanPara;
                $foundMainContent = true;
            }
        }

        // Ensamblar respuesta limpia
        $cleanText = implode("\n\n", $cleanParagraphs);
        
        return $cleanText;
    }

    /**
     * Asegurar un solo cierre empático apropiado
     */
    private function ensureSingleEmpathicClosing(string $text): string
    {
        // Verificar si ya tiene un cierre empático
        if (preg_match('/🐾|🐯/', $text)) {
            return $text;
        }

        return $text . "\n\n¿Necesitas algo más? Estoy aquí para apoyarte 🐾";
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
