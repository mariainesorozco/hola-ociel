<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Services\EnhancedQdrantVectorService;

class SimpleNotionService
{
    private $client;
    private $apiKey;
    private $vectorService;
    private const NOTION_VERSION = '2022-06-28';
    private const NOTION_API_URL = 'https://api.notion.com/v1';

    // IDs de las bases de datos de Notion
    private $databases;

    // Mapeo de base de datos a categorías
    private $databaseCategoryMap = [
        'finanzas' => 'servicios_financieros',
        'academica' => 'servicios_academicos',
        'recursos_humanos' => 'servicios_rrhh',
        'servicios_tecnologicos' => 'servicios_tecnologicos'
    ];

    public function __construct(
        EnhancedQdrantVectorService $vectorService
    ) {
        $this->apiKey = config('services.notion.api_key');
        $this->vectorService = $vectorService;

        // Cargar IDs de bases de datos desde config
        $this->databases = [
            'finanzas' => config('services.notion.databases.finanzas'),
            'academica' => config('services.notion.databases.academica'),
            'recursos_humanos' => config('services.notion.databases.recursos_humanos'),
            'servicios_tecnologicos' => config('services.notion.databases.servicios_tecnologicos')
        ];

        $this->client = new Client([
            'base_uri' => self::NOTION_API_URL,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Notion-Version' => self::NOTION_VERSION,
                'Content-Type' => 'application/json'
            ]
        ]);
    }

    /**
     * Sincronizar todas las bases de datos configuradas
     */
    public function syncAllDatabases(): array
    {
        $totalResults = [
            'databases_synced' => 0,
            'total_pages' => 0,
            'total_indexed' => 0,
            'total_errors' => 0,
            'details' => []
        ];

        foreach ($this->databases as $name => $databaseId) {
            if (empty($databaseId)) {
                Log::warning("Database ID no configurado para: {$name}");
                continue;
            }

            $this->info("Sincronizando base de datos: {$name}");

            $category = $this->databaseCategoryMap[$name] ?? 'general';
            $results = $this->syncDatabase($databaseId, $category);

            $totalResults['databases_synced']++;
            $totalResults['total_pages'] += $results['total_pages'];
            $totalResults['total_indexed'] += $results['indexed'];
            $totalResults['total_errors'] += $results['errors'];
            $totalResults['details'][$name] = $results;
        }

        return $totalResults;
    }

    /**
     * Buscar en todas las bases de datos configuradas
     */
    public function searchAllDatabases(string $query): array
    {
        $allResults = [];

        foreach ($this->databases as $name => $databaseId) {
            if (empty($databaseId)) {
                continue;
            }

            $results = $this->searchInDatabase($databaseId, $query);

            foreach ($results as &$result) {
                $result['database_name'] = $name;
                $result['category'] = $this->databaseCategoryMap[$name] ?? 'general';
            }

            $allResults = array_merge($allResults, $results);
        }

        // Ordenar por relevancia (simulada por ahora)
        usort($allResults, function($a, $b) {
            return strlen($b['title']) <=> strlen($a['title']);
        });

        return array_slice($allResults, 0, 10);
    }

    /**
     * Buscar en una base de datos específica
     */
    public function searchInDatabase(string $databaseId, string $query): array
    {
        try {
            $response = $this->client->post('/v1/databases/' . $databaseId . '/query', [
                'json' => [
                    'page_size' => 20,
                    'filter' => [
                        'or' => [
                            [
                                'property' => 'Servicio',
                                'title' => [
                                    'contains' => $query
                                ]
                            ],
                            [
                                'property' => 'Descripcion',
                                'rich_text' => [
                                    'contains' => $query
                                ]
                            ],
                            [
                                'property' => 'Categoria',
                                'select' => [
                                    'contains' => $query
                                ]
                            ]
                        ]
                    ]
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            return $this->processSearchResults($data['results'] ?? []);

        } catch (\Exception $e) {
            Log::error('Error buscando en database: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener base de datos por nombre
     */
    public function getDatabaseByName(string $name): ?string
    {
        return $this->databases[$name] ?? null;
    }

    /**
     * Sincronizar base de datos específica por nombre
     */
    public function syncDatabaseByName(string $name): array
    {
        $databaseId = $this->getDatabaseByName($name);

        if (!$databaseId) {
            return [
                'error' => "Base de datos '{$name}' no configurada",
                'available' => array_keys($this->databases)
            ];
        }

        $category = $this->databaseCategoryMap[$name] ?? 'general';
        return $this->syncDatabase($databaseId, $category);
    }

    /**
     * Log helper para mostrar progreso
     */
    private function info(string $message): void
    {
        if (app()->runningInConsole()) {
            echo "[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL;
        }
        Log::info($message);
    }

    /**
     * Obtener contenido de una página
     */
    public function getPage(string $pageId): ?array
    {
        try {
            // Obtener metadatos de la página
            $pageResponse = $this->client->get('/v1/pages/' . $pageId);
            $pageData = json_decode($pageResponse->getBody(), true);

            // Obtener bloques de contenido
            $blocksResponse = $this->client->get('/v1/blocks/' . $pageId . '/children');
            $blocksData = json_decode($blocksResponse->getBody(), true);

            return $this->extractPageContent($pageData, $blocksData['results'] ?? []);

        } catch (\Exception $e) {
            Log::error('Error obteniendo página: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtener páginas de una base de datos
     */
    public function queryDatabase(string $databaseId, array $filter = [], $startCursor = null): array
    {
        try {
            $body = [
                'page_size' => 100
            ];

            if (!empty($filter)) {
                $body['filter'] = $filter;
            }

            if ($startCursor) {
                $body['start_cursor'] = $startCursor;
            }

            $response = $this->client->post('/v1/databases/' . $databaseId . '/query', [
                'json' => $body
            ]);

            return json_decode($response->getBody(), true);

        } catch (\Exception $e) {
            Log::error('Error consultando database: ' . $e->getMessage());
            return ['results' => [], 'has_more' => false];
        }
    }

    /**
     * Sincronizar una base de datos completa
     */
    public function syncDatabase(string $databaseId, string $category = 'general'): array
    {
        $results = [
            'total_pages' => 0,
            'indexed' => 0,
            'errors' => 0
        ];

        $hasMore = true;
        $startCursor = null;

        while ($hasMore) {
            $data = $this->queryDatabase($databaseId, [], $startCursor);

            foreach ($data['results'] as $page) {
                $results['total_pages']++;

                // Procesar página
                $processed = $this->processAndIndexPage($page, $category);

                if ($processed) {
                    $results['indexed']++;
                } else {
                    $results['errors']++;
                }
            }

            $hasMore = $data['has_more'] ?? false;
            $startCursor = $data['next_cursor'] ?? null;
        }

        return $results;
    }

    /**
     * Procesar y indexar una página
     */
    private function processAndIndexPage(array $pageData, string $category): bool
    {
        try {
            // Extraer propiedades
            $properties = $this->extractProperties($pageData['properties'] ?? []);

            // Obtener contenido completo
            $content = $this->getPageBlocks($pageData['id']);

            // Preparar datos para Qdrant directamente
            $qdrantData = [
                'id' => $pageData['id'], // Usar Notion ID como vector ID
                'content' => $content,
                'metadata' => [
                    'title' => $properties['title'] ?? 'Sin título',
                    'category' => $category,
                    'subcategory' => $properties['Subcategoria'] ?? null,
                    'department' => $this->mapDepartment($properties['Dependencia'] ?? ''),
                    'service_id' => $properties['Id_Servicio'] ?? null,
                    'cost' => $properties['Costo'] ?? null,
                    'modality' => $properties['Modalidad'] ?? null,
                    'status' => $properties['Estado'] ?? 'Activo',
                    'users' => $properties['Usuarios'] ?? '',
                    'dependency' => $properties['Dependencia'] ?? '',
                    'source_url' => $pageData['url'] ?? '',
                    'source_type' => 'notion',
                    'notion_id' => $pageData['id'],
                    'created_time' => $pageData['created_time'],
                    'last_edited_time' => $pageData['last_edited_time']
                ]
            ];

            // Indexar directamente en Qdrant
            return $this->indexInQdrant($qdrantData);

        } catch (\Exception $e) {
            Log::error('Error procesando página: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener bloques de contenido de una página
     */
    private function getPageBlocks(string $pageId): string
    {
        try {
            $response = $this->client->get('/v1/blocks/' . $pageId . '/children');
            $data = json_decode($response->getBody(), true);

            $content = '';
            foreach ($data['results'] as $block) {
                $content .= $this->extractBlockText($block) . "\n";
            }

            return trim($content);

        } catch (\Exception $e) {
            Log::error('Error obteniendo bloques: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Extraer texto de un bloque
     */
    private function extractBlockText(array $block): string
    {
        $type = $block['type'];
        $text = '';

        // Extraer texto según el tipo de bloque
        switch ($type) {
            case 'paragraph':
            case 'heading_1':
            case 'heading_2':
            case 'heading_3':
            case 'bulleted_list_item':
            case 'numbered_list_item':
            case 'toggle':
            case 'quote':
            case 'callout':
                if (isset($block[$type]['rich_text'])) {
                    foreach ($block[$type]['rich_text'] as $richText) {
                        $text .= $richText['plain_text'] ?? '';
                    }
                }
                break;

            case 'code':
                $text = $block[$type]['rich_text'][0]['plain_text'] ?? '';
                break;

            case 'table':
                // Las tablas requieren procesamiento adicional
                $text = '[Tabla]';
                break;
        }

        return $text;
    }

    /**
     * Extraer propiedades de una página
     */
    private function extractProperties(array $properties): array
    {
        $extracted = [];

        foreach ($properties as $name => $property) {
            $value = $this->extractPropertyValue($property);
            if ($value !== null) {
                $extracted[$name] = $value;
            }
        }

        // Extraer título especialmente
        if (isset($properties['Servicio']) || isset($properties['Name']) || isset($properties['Título'])) {
            $titleProperty = $properties['Servicio'] ?? $properties['Name'] ?? $properties['Título'] ?? null;
            if ($titleProperty && $titleProperty['type'] === 'title') {
                $extracted['title'] = $this->extractPropertyValue($titleProperty);
            }
        }

        return $extracted;
    }

    /**
     * Extraer valor de una propiedad
     */
    private function extractPropertyValue(array $property)
    {
        $type = $property['type'];

        switch ($type) {
            case 'title':
            case 'rich_text':
                $text = '';
                foreach ($property[$type] as $richText) {
                    $text .= $richText['plain_text'] ?? '';
                }
                return $text;

            case 'number':
                return $property['number'];

            case 'select':
                return $property['select']['name'] ?? null;

            case 'multi_select':
                $values = [];
                foreach ($property['multi_select'] as $option) {
                    $values[] = $option['name'];
                }
                return implode(', ', $values);

            case 'date':
                return $property['date']['start'] ?? null;

            case 'checkbox':
                return $property['checkbox'];

            case 'url':
                return $property['url'];

            case 'email':
                return $property['email'];

            case 'phone_number':
                return $property['phone_number'];

            default:
                return null;
        }
    }

    /**
     * Procesar resultados de búsqueda
     */
    private function processSearchResults(array $results): array
    {
        $processed = [];

        foreach ($results as $result) {
            $type = $result['object'];

            if ($type === 'page') {
                $properties = $this->extractProperties($result['properties'] ?? []);

                $processed[] = [
                    'id' => $result['id'],
                    'title' => $properties['title'] ?? 'Sin título',
                    'type' => 'page',
                    'url' => $result['url'] ?? '',
                    'properties' => $properties,
                    'last_edited' => $result['last_edited_time'] ?? null
                ];
            } elseif ($type === 'database') {
                $processed[] = [
                    'id' => $result['id'],
                    'title' => $result['title'][0]['plain_text'] ?? 'Base de datos',
                    'type' => 'database',
                    'url' => $result['url'] ?? ''
                ];
            }
        }

        return $processed;
    }

    /**
     * Indexar en Qdrant
     */
    private function indexInQdrant($data): bool
    {
        try {
            // Usar el método de indexación de Notion específico
            return $this->vectorService->indexNotionContent([$data])['indexed'] > 0;

        } catch (\Exception $e) {
            Log::error('Error indexando en Qdrant: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Mapear departamento basado en la base de datos
     */
    private function mapDepartment(string $department, string $databaseName = ''): string
    {
        // Mapeo directo por texto del departamento
        $map = [
            'Secretaría de Finanzas' => 'Secretaría de Finanzas',
            'Dirección de Entidades Productivas' => 'Secretaría de Finanzas',
            'Secretaría Académica' => 'Secretaría Académica',
            'Dirección General de Sistemas' => 'Dirección de Infraestructura y Servicios Tecnológicos',
            'Recursos Humanos' => 'Dirección de Nómina y Recursos Humanos',
            'Dirección de Nómina' => 'Dirección de Nómina y Recursos Humanos'
        ];

        return $map[$department] ?? $department ?: 'General';
    }


    /**
     * Extraer contenido completo de página con bloques
     */
    private function extractPageContent(array $pageData, array $blocks): array
    {
        $content = [
            'title' => '',
            'content' => '',
            'properties' => $this->extractProperties($pageData['properties'] ?? []),
            'blocks' => []
        ];

        // Título
        $content['title'] = $content['properties']['title'] ?? 'Sin título';

        // Procesar bloques
        foreach ($blocks as $block) {
            $blockText = $this->extractBlockText($block);
            if ($blockText) {
                $content['content'] .= $blockText . "\n";
                $content['blocks'][] = [
                    'type' => $block['type'],
                    'text' => $blockText
                ];
            }
        }

        return $content;
    }

    /**
     * Verificar salud del servicio Notion
     */
    public function isHealthy(): bool
    {
        try {
            $response = $this->client->get('/v1/users/me');
            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            Log::error('Notion health check failed: ' . $e->getMessage());
            return false;
        }
    }
}
