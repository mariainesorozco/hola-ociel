<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use DOMDocument;
use DOMXPath;
use DOMElement;

class PiidaScrapingService
{
    private $client;
    private $baseUrl;
    private $ollamaService;
    private $qdrantService;
    private $categories;

    public function __construct(
        OllamaService $ollamaService,
        EnhancedQdrantVectorService $qdrantService = null
    ) {
        $this->baseUrl = config('services.piida.base_url', 'https://piida.uan.mx');
        $this->ollamaService = $ollamaService;
        $this->qdrantService = $qdrantService;
        $this->categories = config('services.piida.categories', []);

        $this->client = new Client([
            'timeout' => config('services.piida.api_timeout', 30),
            'connect_timeout' => 10,
            'headers' => [
                'User-Agent' => config('services.web_scraping.user_agent', 'UAN-Ociel-Bot/2.0'),
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'es-MX,es;q=0.9,en;q=0.8',
                'Accept-Encoding' => 'gzip, deflate, br',
                'DNT' => '1',
                'Connection' => 'keep-alive',
                'Upgrade-Insecure-Requests' => '1'
            ]
        ]);
    }

    /**
     * Ejecutar scraping completo de PIIDA
     */
    public function scrapeAllPiidaContent(): array
    {
        $results = [
            'total_processed' => 0,
            'successful' => 0,
            'errors' => 0,
            'updated' => 0,
            'categories' => []
        ];

        Log::info('Iniciando scraping completo de PIIDA');

        try {
            // 1. Scrapear página principal de servicios
            $mainServicesResult = $this->scrapeMainServicesPage();
            $results['categories']['main_services'] = $mainServicesResult;

            // 2. Scrapear directorio de dependencias
            $directoryResult = $this->scrapeDirectoryPage();
            $results['categories']['directory'] = $directoryResult;

            // 3. Scrapear normatividad
            $normativityResult = $this->scrapeNormativityPage();
            $results['categories']['normatividad'] = $normativityResult;

            // 4. Scrapear páginas específicas de trámites
            $tramitesResult = $this->scrapeTramitesPages();
            $results['categories']['tramites'] = $tramitesResult;

            // 5. Scrapear eventos y convocatorias (si están disponibles)
            $eventsResult = $this->scrapeEventsPage();
            $results['categories']['eventos'] = $eventsResult;

            // Calcular totales
            foreach ($results['categories'] as $category) {
                $results['total_processed'] += $category['total_processed'] ?? 0;
                $results['successful'] += $category['successful'] ?? 0;
                $results['errors'] += $category['errors'] ?? 0;
                $results['updated'] += $category['updated'] ?? 0;
            }

            // Actualizar índice vectorial si está disponible
            if ($this->qdrantService) {
                $this->updateVectorIndex();
            }

            Log::info('Scraping de PIIDA completado', $results);

        } catch (\Exception $e) {
            Log::error('Error durante scraping de PIIDA: ' . $e->getMessage());
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Scrapear página principal de servicios
     */
    public function scrapeMainServicesPage(): array
    {
        $url = $this->baseUrl . '/servicios';
        $results = ['total_processed' => 0, 'successful' => 0, 'errors' => 0];

        try {
            Log::info("Scrapeando servicios principales: {$url}");

            $response = $this->client->get($url);
            $html = $response->getBody()->getContents();

            $services = $this->extractServicesFromHtml($html);

            foreach ($services as $service) {
                $results['total_processed']++;

                try {
                    $saved = $this->saveServiceToKnowledgeBase($service, 'servicios_academicos', $url);

                    if ($saved) {
                        $results['successful']++;
                    }
                } catch (\Exception $e) {
                    Log::error("Error guardando servicio: " . $e->getMessage());
                    $results['errors']++;
                }
            }

            // Pausa entre requests
            sleep(config('services.piida.scraping_delay', 2));

        } catch (RequestException $e) {
            Log::error("Error scrapeando servicios principales: " . $e->getMessage());
            $results['errors']++;
        }

        return $results;
    }

    /**
     * Scrapear directorio de dependencias
     */
    public function scrapeDirectoryPage(): array
    {
        $url = $this->baseUrl . '/directorio';
        $results = ['total_processed' => 0, 'successful' => 0, 'errors' => 0];

        try {
            Log::info("Scrapeando directorio: {$url}");

            $response = $this->client->get($url);
            $html = $response->getBody()->getContents();

            $departments = $this->extractDepartmentsFromHtml($html);

            foreach ($departments as $department) {
                $results['total_processed']++;

                try {
                    $saved = $this->saveDepartmentToKnowledgeBase($department, 'directorio', $url);

                    if ($saved) {
                        $results['successful']++;
                    }
                } catch (\Exception $e) {
                    Log::error("Error guardando departamento: " . $e->getMessage());
                    $results['errors']++;
                }
            }

            sleep(config('services.piida.scraping_delay', 2));

        } catch (RequestException $e) {
            Log::error("Error scrapeando directorio: " . $e->getMessage());
            $results['errors']++;
        }

        return $results;
    }

    /**
     * Scrapear normatividad
     */
    public function scrapeNormativityPage(): array
    {
        $url = $this->baseUrl . '/normatividad';
        $results = ['total_processed' => 0, 'successful' => 0, 'errors' => 0];

        try {
            Log::info("Scrapeando normatividad: {$url}");

            $response = $this->client->get($url);
            $html = $response->getBody()->getContents();

            $norms = $this->extractNormativityFromHtml($html);

            foreach ($norms as $norm) {
                $results['total_processed']++;

                try {
                    $saved = $this->saveNormToKnowledgeBase($norm, 'normatividad', $url);

                    if ($saved) {
                        $results['successful']++;
                    }
                } catch (\Exception $e) {
                    Log::error("Error guardando norma: " . $e->getMessage());
                    $results['errors']++;
                }
            }

            sleep(config('services.piida.scraping_delay', 2));

        } catch (RequestException $e) {
            Log::error("Error scrapeando normatividad: " . $e->getMessage());
            $results['errors']++;
        }

        return $results;
    }

    /**
     * Scrapear páginas específicas de trámites
     */
    public function scrapeTramitesPages(): array
    {
        $results = ['total_processed' => 0, 'successful' => 0, 'errors' => 0];

        // URLs específicas de trámites que podemos identificar
        $tramiteUrls = [
            '/servicios/estudiantes' => 'tramites_estudiantes',
            '/servicios/docentes' => 'tramites_docentes',
            '/servicios/titulacion' => 'tramites_estudiantes',
            '/servicios/movilidad' => 'tramites_estudiantes'
        ];

        foreach ($tramiteUrls as $path => $category) {
            try {
                $url = $this->baseUrl . $path;
                Log::info("Scrapeando trámites: {$url}");

                $response = $this->client->get($url);
                $html = $response->getBody()->getContents();

                $tramites = $this->extractTramitesFromHtml($html, $category);

                foreach ($tramites as $tramite) {
                    $results['total_processed']++;

                    try {
                        $saved = $this->saveTramiteToKnowledgeBase($tramite, $category, $url);

                        if ($saved) {
                            $results['successful']++;
                        }
                    } catch (\Exception $e) {
                        Log::error("Error guardando trámite: " . $e->getMessage());
                        $results['errors']++;
                    }
                }

                sleep(config('services.piida.scraping_delay', 2));

            } catch (RequestException $e) {
                Log::info("Página no encontrada o inaccesible: {$path}");
                // No es error crítico, algunas páginas pueden no existir
            }
        }

        return $results;
    }

    /**
     * Scrapear eventos y convocatorias
     */
    public function scrapeEventsPage(): array
    {
        $results = ['total_processed' => 0, 'successful' => 0, 'errors' => 0];

        $eventUrls = [
            '/eventos',
            '/convocatorias',
            '/noticias'
        ];

        foreach ($eventUrls as $path) {
            try {
                $url = $this->baseUrl . $path;
                Log::info("Scrapeando eventos: {$url}");

                $response = $this->client->get($url);
                $html = $response->getBody()->getContents();

                $events = $this->extractEventsFromHtml($html);

                foreach ($events as $event) {
                    $results['total_processed']++;

                    try {
                        $saved = $this->saveEventToKnowledgeBase($event, 'eventos', $url);

                        if ($saved) {
                            $results['successful']++;
                        }
                    } catch (\Exception $e) {
                        Log::error("Error guardando evento: " . $e->getMessage());
                        $results['errors']++;
                    }
                }

                sleep(config('services.piida.scraping_delay', 2));

            } catch (RequestException $e) {
                Log::info("Página de eventos no encontrada: {$path}");
            }
        }

        return $results;
    }

    /**
     * Extraer servicios del HTML
     */
    private function extractServicesFromHtml(string $html): array
    {
        $services = [];

        try {
            $doc = new DOMDocument();
            @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
            $xpath = new DOMXPath($doc);

            // Buscar secciones de servicios
            $serviceNodes = $xpath->query('//div[contains(@class, "service")] | //article | //section[contains(@class, "content")]');

            foreach ($serviceNodes as $node) {
                $title = $this->extractTextFromNode($xpath, './/*[self::h1 or self::h2 or self::h3 or self::h4]', $node);
                $content = $this->extractTextFromNode($xpath, './/p | .//div[not(contains(@class, "nav"))]', $node);

                if (strlen(trim($title)) > 5 && strlen(trim($content)) > 20) {
                    $services[] = [
                        'title' => trim($title),
                        'content' => trim($content),
                        'type' => 'service'
                    ];
                }
            }

            // Si no encontramos servicios estructurados, extraer texto general
            if (empty($services)) {
                $services = $this->extractGeneralContentFromHtml($html, 'service');
            }

        } catch (\Exception $e) {
            Log::warning("Error extrayendo servicios del HTML: " . $e->getMessage());
        }

        return $services;
    }

    /**
     * Extraer departamentos del HTML
     */
    private function extractDepartmentsFromHtml(string $html): array
    {
        $departments = [];

        try {
            $doc = new DOMDocument();
            @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
            $xpath = new DOMXPath($doc);

            // Buscar entradas de directorio
            $deptNodes = $xpath->query('//div[contains(@class, "department")] | //tr | //li[contains(@class, "contact")]');

            foreach ($deptNodes as $node) {
                $name = $this->extractTextFromNode($xpath, './/strong | .//b | .//*[contains(@class, "name")]', $node);
                $contact = $this->extractContactFromNode($xpath, $node);
                $location = $this->extractTextFromNode($xpath, './/*[contains(text(), "Edificio") or contains(text(), "ubicad")]', $node);

                if (strlen(trim($name)) > 3) {
                    $departments[] = [
                        'title' => "Directorio: " . trim($name),
                        'content' => $this->buildDepartmentContent($name, $contact, $location),
                        'contact_info' => $contact,
                        'type' => 'department'
                    ];
                }
            }

            // Extraer también información general de contacto
            $generalContact = $this->extractGeneralContactInfo($xpath);
            if (!empty($generalContact)) {
                $departments[] = [
                    'title' => 'Información General de Contacto - UAN',
                    'content' => $generalContact,
                    'type' => 'contact'
                ];
            }

        } catch (\Exception $e) {
            Log::warning("Error extrayendo departamentos del HTML: " . $e->getMessage());
        }

        return $departments;
    }

    /**
     * Extraer normatividad del HTML - VERSIÓN CORREGIDA
     */
    private function extractNormativityFromHtml(string $html): array
    {
        $norms = [];

        try {
            $doc = new DOMDocument();
            @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
            $xpath = new DOMXPath($doc);

            // Buscar documentos normativos
            $normNodes = $xpath->query('//a[contains(@href, ".pdf")] | //li[contains(text(), "Reglamento")] | //li[contains(text(), "Ley")] | //li[contains(text(), "Acuerdo")]');

            foreach ($normNodes as $node) {
                $title = trim($node->textContent);
                $link = '';

                // Verificar que el nodo es un DOMElement antes de usar getAttribute
                if ($node instanceof DOMElement) {
                    $link = $node->getAttribute('href');
                }

                if (strlen($title) > 10) {
                    $norms[] = [
                        'title' => $title,
                        'content' => $this->buildNormContent($title, $link),
                        'document_url' => $link,
                        'type' => 'regulation'
                    ];
                }
            }

        } catch (\Exception $e) {
            Log::warning("Error extrayendo normatividad del HTML: " . $e->getMessage());
        }

        return $norms;
    }

    /**
     * Extraer trámites del HTML
     */
    private function extractTramitesFromHtml(string $html, string $category): array
    {
        $tramites = [];

        try {
            $doc = new DOMDocument();
            @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
            $xpath = new DOMXPath($doc);

            // Buscar secciones de trámites
            $tramiteNodes = $xpath->query('//div[contains(@class, "tramite")] | //section | //article');

            foreach ($tramiteNodes as $node) {
                $title = $this->extractTextFromNode($xpath, './/*[self::h1 or self::h2 or self::h3]', $node);
                $content = $this->extractTextFromNode($xpath, './/p | .//ul | .//ol', $node);
                $requirements = $this->extractRequirements($xpath, $node);

                if (strlen(trim($title)) > 5 && strlen(trim($content)) > 20) {
                    $tramites[] = [
                        'title' => trim($title),
                        'content' => $this->buildTramiteContent($content, $requirements),
                        'category' => $category,
                        'type' => 'procedure'
                    ];
                }
            }

        } catch (\Exception $e) {
            Log::warning("Error extrayendo trámites del HTML: " . $e->getMessage());
        }

        return $tramites;
    }

    /**
     * Extraer eventos del HTML
     */
    private function extractEventsFromHtml(string $html): array
    {
        $events = [];

        try {
            $doc = new DOMDocument();
            @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
            $xpath = new DOMXPath($doc);

            // Buscar eventos y noticias
            $eventNodes = $xpath->query('//article | //div[contains(@class, "event")] | //div[contains(@class, "news")]');

            foreach ($eventNodes as $node) {
                $title = $this->extractTextFromNode($xpath, './/*[self::h1 or self::h2 or self::h3]', $node);
                $content = $this->extractTextFromNode($xpath, './/p', $node);
                $date = $this->extractDateFromNode($xpath, $node);

                if (strlen(trim($title)) > 5 && strlen(trim($content)) > 20) {
                    $events[] = [
                        'title' => trim($title),
                        'content' => trim($content),
                        'event_date' => $date,
                        'type' => 'event'
                    ];
                }
            }

        } catch (\Exception $e) {
            Log::warning("Error extrayendo eventos del HTML: " . $e->getMessage());
        }

        return $events;
    }

    /**
     * Métodos auxiliares para extracción de contenido - VERSIÓN CORREGIDA
     */
    private function extractTextFromNode(DOMXPath $xpath, string $query, \DOMNode $context = null): string
    {
        $nodes = $xpath->query($query, $context);
        $text = '';

        foreach ($nodes as $node) {
            $nodeText = trim($node->textContent);
            if (strlen($nodeText) > 0) {
                $text .= $nodeText . "\n";
            }
        }

        return trim($text);
    }

    private function extractContactFromNode(DOMXPath $xpath, \DOMNode $context): string
    {
        $contact = '';

        // Buscar teléfonos
        $phoneNodes = $xpath->query('.//*[contains(text(), "311") or contains(text(), "tel")]', $context);
        foreach ($phoneNodes as $node) {
            if (preg_match('/(\+?52\s?)?(\(?311\)?[\s\-]?)?[\d\s\-\.]{7,}/', $node->textContent, $matches)) {
                $contact .= "Tel: " . trim($matches[0]) . "\n";
            }
        }

        // Buscar emails
        $emailNodes = $xpath->query('.//*[contains(text(), "@")]', $context);
        foreach ($emailNodes as $node) {
            if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $node->textContent, $matches)) {
                $contact .= "Email: " . trim($matches[0]) . "\n";
            }
        }

        return trim($contact);
    }

    private function extractGeneralContactInfo(DOMXPath $xpath): string
    {
        $contact = '';

        // Buscar información de contacto general
        $contactNodes = $xpath->query('//*[contains(text(), "311-211-8800") or contains(text(), "Tepic") or contains(text(), "Nayarit")]');

        foreach ($contactNodes as $node) {
            $text = trim($node->textContent);
            if (strlen($text) > 20 && strlen($text) < 200) {
                $contact .= $text . "\n";
            }
        }

        return trim($contact);
    }

    private function extractRequirements(DOMXPath $xpath, \DOMNode $context): string
    {
        $requirements = '';

        // Buscar listas de requisitos
        $reqNodes = $xpath->query('.//ul[contains(preceding-sibling::*[1], "requisito")] | .//ol[contains(preceding-sibling::*[1], "requisito")]', $context);

        foreach ($reqNodes as $node) {
            $items = $xpath->query('.//li', $node);
            foreach ($items as $item) {
                $requirements .= "• " . trim($item->textContent) . "\n";
            }
        }

        return trim($requirements);
    }

    private function extractDateFromNode(DOMXPath $xpath, \DOMNode $context): ?string
    {
        $dateNodes = $xpath->query('.//*[contains(@class, "date") or contains(@class, "fecha")]', $context);

        foreach ($dateNodes as $node) {
            $text = trim($node->textContent);
            if (preg_match('/\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4}/', $text, $matches)) {
                return $matches[0];
            }
        }

        return null;
    }

    private function extractGeneralContentFromHtml(string $html, string $type): array
    {
        $content = [];

        try {
            $doc = new DOMDocument();
            @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
            $xpath = new DOMXPath($doc);

            // Extraer párrafos con contenido sustancial
            $paragraphs = $xpath->query('//p[string-length(text()) > 50]');

            $currentTitle = 'Información General';
            $currentContent = '';

            foreach ($paragraphs as $p) {
                $text = trim($p->textContent);
                if (strlen($text) > 50 && !$this->isUnwantedContent($text)) {
                    $currentContent .= $text . "\n\n";

                    // Crear bloques de contenido cada cierto número de párrafos
                    if (strlen($currentContent) > 800) {
                        $content[] = [
                            'title' => $currentTitle,
                            'content' => trim($currentContent),
                            'type' => $type
                        ];

                        $currentContent = '';
                    }
                }
            }

            // Agregar último bloque si hay contenido
            if (strlen(trim($currentContent)) > 100) {
                $content[] = [
                    'title' => $currentTitle,
                    'content' => trim($currentContent),
                    'type' => $type
                ];
            }

        } catch (\Exception $e) {
            Log::warning("Error extrayendo contenido general: " . $e->getMessage());
        }

        return $content;
    }

    // Resto de métodos auxiliares...
    private function buildDepartmentContent(string $name, string $contact, string $location): string
    {
        $content = "Departamento: {$name}\n\n";

        if ($location) {
            $content .= "Ubicación: {$location}\n\n";
        }

        if ($contact) {
            $content .= "Información de contacto:\n{$contact}\n\n";
        }

        $content .= "Este departamento forma parte de la estructura organizacional de la Universidad Autónoma de Nayarit y brinda servicios especializados a la comunidad universitaria.";

        return $content;
    }

    private function buildNormContent(string $title, string $link): string
    {
        $content = "Documento normativo: {$title}\n\n";
        $content .= "Este documento forma parte del marco jurídico y normativo de la Universidad Autónoma de Nayarit. ";
        $content .= "Establece las reglas, procedimientos y lineamientos que rigen las actividades institucionales.\n\n";

        if ($link) {
            $content .= "Documento disponible en: {$link}\n\n";
        }

        $content .= "Para consultas específicas sobre este documento, contacte a la Secretaría General de la UAN.";

        return $content;
    }

    private function buildTramiteContent(string $content, string $requirements): string
    {
        $fullContent = $content;

        if ($requirements) {
            $fullContent .= "\n\nRequisitos:\n" . $requirements;
        }

        $fullContent .= "\n\nPara más información sobre este trámite, contacte a la dependencia correspondiente o visite la plataforma PIIDA.";

        return $fullContent;
    }

    // Métodos para guardar contenido en la base de conocimientos
    private function saveServiceToKnowledgeBase(array $service, string $category, string $sourceUrl): bool
    {
        return $this->saveToKnowledgeBase([
            'title' => $service['title'],
            'content' => $service['content'],
            'category' => $category,
            'department' => 'GENERAL',
            'user_types' => json_encode(['student', 'employee', 'public']),
            'keywords' => json_encode($this->generateKeywords($service['title'] . ' ' . $service['content'])),
            'source_url' => $sourceUrl,
            'priority' => 'medium',
            'contact_info' => $this->extractContactFromContent($service['content'])
        ]);
    }

    private function saveDepartmentToKnowledgeBase(array $department, string $category, string $sourceUrl): bool
    {
        return $this->saveToKnowledgeBase([
            'title' => $department['title'],
            'content' => $department['content'],
            'category' => $category,
            'department' => 'GENERAL',
            'user_types' => json_encode(['student', 'employee', 'public']),
            'keywords' => json_encode($this->generateKeywords($department['title'])),
            'source_url' => $sourceUrl,
            'contact_info' => $department['contact_info'] ?? '',
            'priority' => 'high'
        ]);
    }

    private function saveNormToKnowledgeBase(array $norm, string $category, string $sourceUrl): bool
    {
        return $this->saveToKnowledgeBase([
            'title' => $norm['title'],
            'content' => $norm['content'],
            'category' => $category,
            'department' => 'SECRETARIA_GENERAL',
            'user_types' => json_encode(['employee', 'public']),
            'keywords' => json_encode($this->generateKeywords($norm['title'])),
            'source_url' => $norm['document_url'] ?? $sourceUrl,
            'priority' => 'high'
        ]);
    }

    private function saveTramiteToKnowledgeBase(array $tramite, string $category, string $sourceUrl): bool
    {
        $userTypes = $this->determineUserTypesForCategory($category);

        return $this->saveToKnowledgeBase([
            'title' => $tramite['title'],
            'content' => $tramite['content'],
            'category' => $category,
            'department' => $this->determineDepartmentForCategory($category),
            'user_types' => json_encode($userTypes),
            'keywords' => json_encode($this->generateKeywords($tramite['title'] . ' ' . $tramite['content'])),
            'source_url' => $sourceUrl,
            'priority' => 'high'
        ]);
    }

    private function saveEventToKnowledgeBase(array $event, string $category, string $sourceUrl): bool
    {
        return $this->saveToKnowledgeBase([
            'title' => $event['title'],
            'content' => $event['content'],
            'category' => $category,
            'department' => 'GENERAL',
            'user_types' => json_encode(['student', 'employee', 'public']),
            'keywords' => json_encode($this->generateKeywords($event['title'])),
            'source_url' => $sourceUrl,
            'priority' => 'medium',
            'valid_until' => $this->parseEventDate($event['event_date'] ?? null)
        ]);
    }

    /**
     * Método genérico para guardar en la base de conocimientos
     */
    private function saveToKnowledgeBase(array $data): bool
    {
        try {
            // Verificar si ya existe contenido similar
            $existing = DB::table('knowledge_base')
                ->where('title', $data['title'])
                ->where('source_url', $data['source_url'])
                ->first();

            $baseData = array_merge($data, [
                'is_active' => true,
                'created_by' => 'piida_scraper',
                'updated_at' => now()
            ]);

            if ($existing) {
                // Actualizar existente
                DB::table('knowledge_base')
                    ->where('id', $existing->id)
                    ->update($baseData);

                Log::info("Contenido PIIDA actualizado: {$data['title']}");
            } else {
                // Crear nuevo
                $baseData['created_at'] = now();
                DB::table('knowledge_base')->insert($baseData);

                Log::info("Nuevo contenido PIIDA creado: {$data['title']}");
            }

            return true;

        } catch (\Exception $e) {
            Log::error("Error guardando contenido PIIDA: " . $e->getMessage(), [
                'title' => $data['title'],
                'category' => $data['category']
            ]);
            return false;
        }
    }

    /**
     * Métodos auxiliares
     */
    private function generateKeywords(string $text): array
    {
        $text = strtolower($text);
        $words = preg_split('/\s+/', $text);
        $keywords = [];

        $stopWords = [
            'de', 'la', 'el', 'en', 'a', 'y', 'que', 'es', 'se', 'con', 'por', 'para',
            'del', 'los', 'las', 'un', 'una', 'su', 'al', 'le', 'da', 'su', 'por', 'son'
        ];

        foreach ($words as $word) {
            $word = trim($word, '.,;:()[]{}¡!¿?"');
            if (strlen($word) > 3 && !in_array($word, $stopWords)) {
                $keywords[] = $word;
            }
        }

        // Agregar keywords específicos de PIIDA
        $piidaKeywords = ['piida', 'tramite', 'servicio', 'uan', 'universidad', 'nayarit'];
        $keywords = array_merge($keywords, $piidaKeywords);

        return array_values(array_unique(array_slice($keywords, 0, 10)));
    }

    private function extractContactFromContent(string $content): string
    {
        $contact = '';

        // Buscar teléfonos
        if (preg_match('/(\+?52\s?)?(\(?311\)?[\s\-]?)?[\d\s\-\.]{7,}/', $content, $matches)) {
            $contact .= "Tel: " . trim($matches[0]) . "\n";
        }

        // Buscar emails
        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $content, $matches)) {
            $contact .= "Email: " . trim($matches[0]) . "\n";
        }

        return trim($contact);
    }

    private function determineUserTypesForCategory(string $category): array
    {
        if (str_contains($category, 'estudiantes')) {
            return ['student'];
        } elseif (str_contains($category, 'docentes')) {
            return ['employee'];
        } else {
            return ['student', 'employee', 'public'];
        }
    }

    private function determineDepartmentForCategory(string $category): string
    {
        if (str_contains($category, 'estudiantes') || str_contains($category, 'tramites')) {
            return 'SA';
        } elseif (str_contains($category, 'docentes')) {
            return 'SECRETARIA_ACADEMICA';
        } else {
            return 'GENERAL';
        }
    }

    private function parseEventDate(?string $dateString): ?string
    {
        if (!$dateString) {
            return null;
        }

        try {
            // Intentar parsear diferentes formatos de fecha
            $formats = ['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/y'];

            foreach ($formats as $format) {
                $date = \DateTime::createFromFormat($format, $dateString);
                if ($date) {
                    return $date->format('Y-m-d');
                }
            }
        } catch (\Exception $e) {
            Log::warning("No se pudo parsear fecha: {$dateString}");
        }

        return null;
    }

    private function isUnwantedContent(string $text): bool
    {
        $unwanted = [
            'cookie', 'javascript', 'nav-', 'menu-', 'footer', 'header',
            'política de privacidad', 'términos y condiciones', 'loading',
            'buscar', 'search', 'click here', 'más información'
        ];

        $textLower = strtolower($text);
        foreach ($unwanted as $term) {
            if (str_contains($textLower, $term)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Actualizar índice vectorial después del scraping
     */
    private function updateVectorIndex(): void
    {
        try {
            Log::info('Actualizando índice vectorial de PIIDA...');

            if ($this->qdrantService) {
                $result = $this->qdrantService->indexPiidaContent();
                Log::info('Índice vectorial actualizado', $result);
            } else {
                Log::info('Servicio Qdrant no disponible, omitiendo indexación vectorial');
            }

        } catch (\Exception $e) {
            Log::error('Error actualizando índice vectorial: ' . $e->getMessage());
        }
    }

    /**
     * Obtener estadísticas del scraping
     */
    public function getScrapingStats(): array
    {
        $lastRun = Cache::get('piida_last_scraping');

        $stats = [
            'last_run' => $lastRun,
            'total_piida_content' => DB::table('knowledge_base')
                ->where('created_by', 'piida_scraper')
                ->where('is_active', true)
                ->count(),
            'by_category' => DB::table('knowledge_base')
                ->where('created_by', 'piida_scraper')
                ->where('is_active', true)
                ->whereIn('category', array_keys($this->categories))
                ->groupBy('category')
                ->selectRaw('category, COUNT(*) as count')
                ->pluck('count', 'category')
                ->toArray(),
            'recent_updates' => DB::table('knowledge_base')
                ->where('created_by', 'piida_scraper')
                ->where('updated_at', '>', now()->subDays(7))
                ->count()
        ];

        return $stats;
    }

    /**
     * Programar scraping automático
     */
    public function scheduleScrapingUpdate(): void
    {
        $cacheKey = 'piida_last_scraping';

        if (Cache::has($cacheKey)) {
            $lastRun = Cache::get($cacheKey);
            Log::info("Último scraping de PIIDA: {$lastRun}");
        }

        $results = $this->scrapeAllPiidaContent();

        Cache::put($cacheKey, now()->toISOString(), 86400); // 24 horas

        Log::info('Scraping programado de PIIDA completado', $results);
    }
}
