<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use DOMDocument;
use DOMXPath;

class WebScrapingService
{
    private $client;
    private $baseUrls;
    private $ollamaService;

    public function __construct(OllamaService $ollamaService)
    {
        $this->client = new Client([
            'timeout' => 30,
            'connect_timeout' => 10,
            'headers' => [
                'User-Agent' => 'UAN-Ociel-Bot/1.0 (Contact: sistemas@uan.edu.mx)'
            ]
        ]);

        $this->ollamaService = $ollamaService;

        // URLs oficiales de la UAN para scraping
        $this->baseUrls = [
            'main' => 'https://www.uan.edu.mx',
            'admissions' => 'https://www.uan.edu.mx/admisiones',
            'academic' => 'https://www.uan.edu.mx/oferta-educativa',
            'services' => 'https://www.uan.edu.mx/servicios',
            'tramites' => 'https://www.uan.edu.mx/tramites',
            'dgsa' => 'https://dgsa.uan.edu.mx',
            'sistemas' => 'https://sistemas.uan.edu.mx'
        ];
    }

    /**
     * Ejecutar scraping completo de todas las fuentes
     */
    public function scrapeAllSources(): array
    {
        $results = [];

        foreach ($this->baseUrls as $source => $url) {
            try {
                Log::info("Iniciando scraping de: {$source} - {$url}");
                $result = $this->scrapeSingleUrl($url, $source);
                $results[$source] = $result;

                // Pausa entre requests para no sobrecargar el servidor
                sleep(2);

            } catch (\Exception $e) {
                Log::error("Error scraping {$source}: " . $e->getMessage());
                $results[$source] = ['error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Scraping de una URL específica
     */
    public function scrapeSingleUrl(string $url, string $source): array
    {
        try {
            $response = $this->client->get($url);
            $html = $response->getBody()->getContents();

            $content = $this->extractContentFromHtml($html);
            $structuredData = $this->structureContent($content, $source);

            // Procesar con IA para mejorar el contenido
            $enhancedData = $this->enhanceContentWithAI($structuredData);

            // Guardar en la base de conocimientos
            $saved = $this->saveToKnowledgeBase($enhancedData, $source, $url);

            return [
                'url' => $url,
                'content_blocks' => count($enhancedData),
                'saved_entries' => $saved,
                'status' => 'success'
            ];

        } catch (RequestException $e) {
            throw new \Exception("Error HTTP: " . $e->getMessage());
        }
    }

    /**
     * Extraer contenido relevante del HTML
     */
    private function extractContentFromHtml(string $html): array
    {
        $doc = new DOMDocument();
        @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($doc);

        $content = [];

        // Extraer títulos principales
        $titles = $xpath->query('//h1 | //h2 | //h3');
        foreach ($titles as $title) {
            if (strlen(trim($title->textContent)) > 10) {
                $content[] = [
                    'type' => 'title',
                    'level' => $title->tagName,
                    'text' => trim($title->textContent)
                ];
            }
        }

        // Extraer párrafos con contenido sustancial
        $paragraphs = $xpath->query('//p | //div[contains(@class, "content")] | //article//text()');
        foreach ($paragraphs as $p) {
            $text = trim($p->textContent);
            if (strlen($text) > 50 && !$this->isUnwantedContent($text)) {
                $content[] = [
                    'type' => 'paragraph',
                    'text' => $text
                ];
            }
        }

        // Extraer listas informativas
        $lists = $xpath->query('//ul | //ol');
        foreach ($lists as $list) {
            $items = [];
            $listItems = $xpath->query('.//li', $list);
            foreach ($listItems as $item) {
                $itemText = trim($item->textContent);
                if (strlen($itemText) > 10) {
                    $items[] = $itemText;
                }
            }
            if (count($items) >= 2) {
                $content[] = [
                    'type' => 'list',
                    'items' => $items
                ];
            }
        }

        // Extraer información de contacto
        $contacts = $this->extractContactInfo($xpath);
        if (!empty($contacts)) {
            $content[] = [
                'type' => 'contact',
                'data' => $contacts
            ];
        }

        return $content;
    }

    /**
     * Extraer información de contacto
     */
    private function extractContactInfo(DOMXPath $xpath): array
    {
        $contacts = [];

        // Buscar teléfonos
        $phonePattern = '/(\+?52\s?)?(\(?311\)?[\s\-]?)?[\d\s\-\.]{7,}/';
        $phones = $xpath->query('//*[contains(text(), "311") or contains(text(), "teléfono") or contains(text(), "tel")]');

        foreach ($phones as $phone) {
            if (preg_match($phonePattern, $phone->textContent, $matches)) {
                $contacts['phones'][] = trim($matches[0]);
            }
        }

        // Buscar emails
        $emailPattern = '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/';
        $emails = $xpath->query('//*[contains(text(), "@")]');

        foreach ($emails as $email) {
            if (preg_match($emailPattern, $email->textContent, $matches)) {
                $contacts['emails'][] = trim($matches[0]);
            }
        }

        // Buscar direcciones
        $addresses = $xpath->query('//*[contains(text(), "Tepic") or contains(text(), "Nayarit") or contains(text(), "Amado Nervo")]');
        foreach ($addresses as $addr) {
            $text = trim($addr->textContent);
            if (strlen($text) > 20 && strlen($text) < 200) {
                $contacts['addresses'][] = $text;
            }
        }

        return $contacts;
    }

    /**
     * Estructurar contenido por categorías
     */
    private function structureContent(array $content, string $source): array
    {
        $structured = [];
        $currentTitle = null;
        $currentContent = [];

        foreach ($content as $item) {
            if ($item['type'] === 'title') {
                // Guardar bloque anterior si existe
                if ($currentTitle && !empty($currentContent)) {
                    $structured[] = [
                        'title' => $currentTitle,
                        'content' => $this->combineContent($currentContent),
                        'category' => $this->determineCategory($currentTitle, $source),
                        'source' => $source
                    ];
                }

                $currentTitle = $item['text'];
                $currentContent = [];

            } else {
                $currentContent[] = $item;
            }
        }

        // Guardar último bloque
        if ($currentTitle && !empty($currentContent)) {
            $structured[] = [
                'title' => $currentTitle,
                'content' => $this->combineContent($currentContent),
                'category' => $this->determineCategory($currentTitle, $source),
                'source' => $source
            ];
        }

        return $structured;
    }

    /**
     * Combinar contenido en texto legible
     */
    private function combineContent(array $content): string
    {
        $text = '';

        foreach ($content as $item) {
            switch ($item['type']) {
                case 'paragraph':
                    $text .= $item['text'] . "\n\n";
                    break;
                case 'list':
                    $text .= "• " . implode("\n• ", $item['items']) . "\n\n";
                    break;
                case 'contact':
                    if (isset($item['data']['phones'])) {
                        $text .= "Teléfonos: " . implode(', ', array_unique($item['data']['phones'])) . "\n";
                    }
                    if (isset($item['data']['emails'])) {
                        $text .= "Emails: " . implode(', ', array_unique($item['data']['emails'])) . "\n";
                    }
                    if (isset($item['data']['addresses'])) {
                        $text .= "Direcciones: " . implode(', ', array_unique($item['data']['addresses'])) . "\n";
                    }
                    $text .= "\n";
                    break;
            }
        }

        return trim($text);
    }

    /**
     * Determinar categoría automáticamente
     */
    private function determineCategory(string $title, string $source): string
    {
        $title = strtolower($title);

        $categories = [
            'tramites' => ['inscripci', 'admisi', 'tramite', 'requisito', 'proceso', 'documentos'],
            'oferta_educativa' => ['carrera', 'licenciatura', 'program', 'académic', 'plan de estudio'],
            'servicios' => ['servicio', 'biblioteca', 'apoyo', 'becas', 'residencia'],
            'informacion_general' => ['universidad', 'historia', 'misión', 'visión', 'campus'],
            'sistemas' => ['sistema', 'plataforma', 'correo', 'tecnolog', 'soporte técnico']
        ];

        foreach ($categories as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($title, $keyword)) {
                    return $category;
                }
            }
        }

        // Determinar por fuente si no se puede categorizar por título
        $sourceCategories = [
            'admissions' => 'tramites',
            'academic' => 'oferta_educativa',
            'services' => 'servicios',
            'sistemas' => 'sistemas',
            'dgsa' => 'tramites'
        ];

        return $sourceCategories[$source] ?? 'informacion_general';
    }

    /**
     * Mejorar contenido con IA
     */
    private function enhanceContentWithAI(array $structuredData): array
    {
        $enhanced = [];

        foreach ($structuredData as $item) {
            try {
                // Generar resumen y palabras clave con IA
                $prompt = "Analiza el siguiente contenido universitario y genera:\n" .
                         "1. Un resumen de máximo 300 caracteres\n" .
                         "2. 5-8 palabras clave relevantes separadas por comas\n" .
                         "3. Determina si requiere seguimiento humano (sí/no)\n\n" .
                         "Título: {$item['title']}\n" .
                         "Contenido: " . substr($item['content'], 0, 1000) . "\n\n" .
                         "Responde en formato JSON.";

                $aiResponse = $this->ollamaService->generateOcielResponse($prompt, [], 'public');

                if ($aiResponse['success']) {
                    $aiData = $this->parseAIResponse($aiResponse['response']);

                    $enhanced[] = array_merge($item, [
                        'ai_summary' => $aiData['summary'] ?? substr($item['content'], 0, 300),
                        'ai_keywords' => $aiData['keywords'] ?? $this->extractSimpleKeywords($item['title'] . ' ' . $item['content']),
                        'requires_human_follow_up' => $aiData['requires_follow_up'] ?? false
                    ]);
                } else {
                    // Fallback sin IA
                    $enhanced[] = array_merge($item, [
                        'ai_summary' => substr($item['content'], 0, 300),
                        'ai_keywords' => $this->extractSimpleKeywords($item['title'] . ' ' . $item['content']),
                        'requires_human_follow_up' => false
                    ]);
                }

            } catch (\Exception $e) {
                Log::warning("Error enhancing content with AI: " . $e->getMessage());
                $enhanced[] = $item;
            }
        }

        return $enhanced;
    }

    /**
     * Guardar en la base de conocimientos
     */
    private function saveToKnowledgeBase(array $enhancedData, string $source, string $sourceUrl): int
    {
        $saved = 0;

        foreach ($enhancedData as $item) {
            try {
                // Verificar si ya existe contenido similar
                $existing = DB::table('knowledge_base')
                    ->where('title', $item['title'])
                    ->where('source_url', $sourceUrl)
                    ->first();

                $data = [
                    'title' => $item['title'],
                    'content' => $item['content'],
                    'category' => $item['category'],
                    'department' => $this->getDepartmentFromSource($source),
                    'user_types' => json_encode(['student', 'employee', 'public']),
                    'keywords' => json_encode($item['ai_keywords'] ?? []),
                    'source_url' => $sourceUrl,
                    'priority' => $this->determinePriority($item['category']),
                    'is_active' => true,
                    'created_by' => 'web_scraper',
                    'updated_at' => now()
                ];

                if ($existing) {
                    // Actualizar existente
                    DB::table('knowledge_base')
                        ->where('id', $existing->id)
                        ->update($data);
                } else {
                    // Crear nuevo
                    $data['created_at'] = now();
                    DB::table('knowledge_base')->insert($data);
                    $saved++;
                }

            } catch (\Exception $e) {
                Log::error("Error saving to knowledge base: " . $e->getMessage(), [
                    'title' => $item['title'],
                    'source' => $source
                ]);
            }
        }

        return $saved;
    }

    /**
     * Métodos auxiliares
     */
    private function isUnwantedContent(string $text): bool
    {
        $unwanted = ['cookie', 'javascript', 'nav-', 'menu-', 'footer', 'header', 'política de privacidad'];

        foreach ($unwanted as $term) {
            if (str_contains(strtolower($text), $term)) {
                return true;
            }
        }

        return false;
    }

    private function extractSimpleKeywords(string $text): array
    {
        $text = strtolower($text);
        $words = preg_split('/\s+/', $text);
        $keywords = [];

        $stopWords = ['de', 'la', 'el', 'en', 'a', 'y', 'que', 'es', 'se', 'con', 'por', 'para', 'del', 'los', 'las'];

        foreach ($words as $word) {
            $word = trim($word, '.,;:()[]{}');
            if (strlen($word) > 3 && !in_array($word, $stopWords)) {
                $keywords[] = $word;
            }
        }

        return array_unique(array_slice($keywords, 0, 8));
    }

    private function parseAIResponse(string $response): array
    {
        // Intentar parsear como JSON
        $json = json_decode($response, true);
        if ($json) {
            return $json;
        }

        // Fallback: extraer información básica
        return [
            'summary' => substr($response, 0, 300),
            'keywords' => [],
            'requires_follow_up' => false
        ];
    }

    private function getDepartmentFromSource(string $source): string
    {
        $departments = [
            'admissions' => 'DGSA',
            'dgsa' => 'DGSA',
            'sistemas' => 'DGS',
            'academic' => 'GENERAL',
            'services' => 'GENERAL'
        ];

        return $departments[$source] ?? 'GENERAL';
    }

    private function determinePriority(string $category): string
    {
        $priorities = [
            'tramites' => 'high',
            'oferta_educativa' => 'high',
            'servicios' => 'medium',
            'sistemas' => 'medium',
            'informacion_general' => 'low'
        ];

        return $priorities[$category] ?? 'medium';
    }

    /**
     * Programar scraping automático
     */
    public function scheduleAutoScraping(): void
    {
        // Este método se llamará desde un comando de Artisan programado
        Cache::put('last_scraping', now(), 86400); // 24 horas

        $results = $this->scrapeAllSources();

        Log::info('Scheduled scraping completed', $results);
    }
}
