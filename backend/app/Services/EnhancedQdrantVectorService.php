<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EnhancedQdrantVectorService
{
    private $client;
    private $baseUrl;
    private $collectionName;
    private $ollamaService;
    private $vectorSize;
    private $distanceMetric;

    public function __construct(OllamaService $ollamaService)
    {
        $this->baseUrl = config('services.qdrant.url', 'http://localhost:6333');
        $this->collectionName = config('services.qdrant.collection', 'ociel_knowledge');
        $this->vectorSize = (int) config('services.qdrant.vector_size', 768);
        $this->distanceMetric = config('services.qdrant.distance_metric', 'Cosine');
        $this->ollamaService = $ollamaService;

        $headers = ['Content-Type' => 'application/json'];

        // Agregar API key si está configurada (para Qdrant Cloud)
        if ($apiKey = config('services.qdrant.api_key')) {
            $headers['api-key'] = $apiKey;
        }

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => config('services.qdrant.timeout', 30),
            'headers' => $headers
        ]);
    }

    /**
     * Obtener cliente HTTP para operaciones avanzadas
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Verificar conexión con Qdrant
     */
    public function isHealthy(): bool
    {
        try {
            $response = $this->client->get('/');
            return $response->getStatusCode() === 200;
        } catch (RequestException $e) {
            Log::error('Qdrant health check failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Inicializar colección con configuraciones específicas para PIIDA
     */
    public function initializePiidaCollection(): bool
    {
        try {
            // Verificar si la colección existe
            $response = $this->client->get("/collections/{$this->collectionName}");

            if ($response->getStatusCode() === 200) {
                Log::info("Colección {$this->collectionName} ya existe");
                return true;
            }
        } catch (RequestException $e) {
            if ($e->getResponse() && $e->getResponse()->getStatusCode() === 404) {
                // La colección no existe, crearla
                return $this->createPiidaCollection();
            }

            Log::error('Error checking Qdrant collection: ' . $e->getMessage());
            return false;
        }

        return false;
    }

    /**
     * Crear colección optimizada para contenido PIIDA
     */
    private function createPiidaCollection(): bool
    {
        try {
            $collectionConfig = [
                'vectors' => [
                    'size' => $this->vectorSize,
                    'distance' => $this->distanceMetric,
                    'hnsw_config' => [
                        'on_disk' => false, // Para mejor rendimiento en RAM
                        'm' => 16,
                        'ef_construct' => 200
                    ]
                ],
                'optimizers_config' => [
                    'default_segment_number' => 4,
                    'max_segment_size' => 200000,
                    'memmap_threshold' => 50000
                ],
                'replication_factor' => 1,
                'write_consistency_factor' => 1,
                'shard_number' => 1
            ];

            $response = $this->client->put("/collections/{$this->collectionName}", [
                'json' => $collectionConfig
            ]);

            if ($response->getStatusCode() === 200) {
                Log::info("Colección {$this->collectionName} creada exitosamente");

                // Crear índices de payload para búsquedas eficientes
                $this->createPayloadIndices();

                return true;
            }

            return false;

        } catch (RequestException $e) {
            Log::error('Error creating Qdrant collection: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Crear índices de payload para búsquedas más eficientes
     */
    private function createPayloadIndices(): void
    {
        $indices = [
            'category' => 'keyword',
            'department' => 'keyword',
            'user_types' => 'keyword',
            'source_type' => 'keyword',
            'priority' => 'keyword',
            'is_active' => 'bool',
            'created_at' => 'datetime'
        ];

        foreach ($indices as $field => $type) {
            try {
                $this->client->put("/collections/{$this->collectionName}/index", [
                    'json' => [
                        'field_name' => $field,
                        'field_schema' => $type
                    ]
                ]);

                Log::info("Índice creado para campo: {$field}");
            } catch (RequestException $e) {
                Log::warning("No se pudo crear índice para {$field}: " . $e->getMessage());
            }
        }
    }

    /**
     * Indexar contenido específico de PIIDA con metadatos enriquecidos
     */
    public function indexPiidaContent(): array
    {
        if (!$this->initializePiidaCollection()) {
            throw new \Exception('No se pudo inicializar la colección de PIIDA');
        }

        $results = ['indexed' => 0, 'updated' => 0, 'errors' => 0, 'skipped' => 0];
        $batchSize = 50;
        $points = [];

        // Obtener todo el contenido activo de PIIDA
        $knowledge = DB::table('knowledge_base')
            ->where('is_active', true)
            ->whereIn('category', array_keys(config('services.piida.categories', [])))
            ->select([
                'id', 'title', 'content', 'category', 'department',
                'user_types', 'keywords', 'source_url', 'contact_info',
                'priority', 'created_at', 'updated_at'
            ])
            ->orderBy('priority', 'desc')
            ->orderBy('updated_at', 'desc')
            ->get();

        Log::info("Procesando {$knowledge->count()} elementos de conocimiento PIIDA");

        foreach ($knowledge as $item) {
            try {
                // Verificar si ya existe el vector
                if ($this->vectorExists($item->id)) {
                    // Verificar si necesita actualización
                    if (!$this->needsUpdate($item)) {
                        $results['skipped']++;
                        continue;
                    }
                }

                // Crear texto enriquecido para embedding
                $enrichedText = $this->createEnrichedText($item);

                // Generar embedding con retry
                $embedding = $this->generateEmbeddingWithRetry($enrichedText);

                if (empty($embedding)) {
                    Log::warning("No se pudo generar embedding para item {$item->id}: {$item->title}");
                    $results['errors']++;
                    continue;
                }

                // Preparar punto con metadatos enriquecidos
                $points[] = [
                    'id' => $item->id,
                    'vector' => $embedding,
                    'payload' => $this->createEnrichedPayload($item)
                ];

                // Procesar en lotes
                if (count($points) >= $batchSize) {
                    $batchResult = $this->processBatch($points);
                    $results['indexed'] += $batchResult['success'];
                    $results['errors'] += $batchResult['errors'];
                    $points = [];
                }

            } catch (\Exception $e) {
                Log::error("Error procesando item {$item->id}: " . $e->getMessage());
                $results['errors']++;
            }
        }

        // Procesar puntos restantes
        if (!empty($points)) {
            $batchResult = $this->processBatch($points);
            $results['indexed'] += $batchResult['success'];
            $results['errors'] += $batchResult['errors'];
        }

        // Optimizar colección después de la indexación
        $this->optimizeCollection();

        Log::info("Indexación PIIDA completada", $results);
        return $results;
    }

    /**
     * Búsqueda semántica avanzada específica para PIIDA
     */
    public function searchPiidaContent(
        string $query,
        array $filters = [],
        int $limit = 5,
        float $scoreThreshold = 0.7
    ): array {
        try {
            // Generar embedding de la consulta con contexto PIIDA
            $contextualQuery = $this->enrichQueryContext($query, $filters);
            $queryEmbedding = $this->generateEmbeddingWithRetry($contextualQuery);

            if (empty($queryEmbedding)) {
                Log::warning("No se pudo generar embedding para consulta: {$query}");
                return [];
            }

            // Construir filtros específicos de PIIDA
            $qdrantFilters = $this->buildPiidaFilters($filters);

            // Configurar búsqueda
            $searchData = [
                'vector' => $queryEmbedding,
                'limit' => $limit,
                'score_threshold' => $scoreThreshold,
                'with_payload' => true,
                'with_vector' => false // No necesitamos los vectores en la respuesta
            ];

            if (!empty($qdrantFilters)) {
                $searchData['filter'] = $qdrantFilters;
            }

            $response = $this->client->post("/collections/{$this->collectionName}/points/search", [
                'json' => $searchData
            ]);

            $data = json_decode($response->getBody(), true);
            $results = $this->processPiidaSearchResults($data['result'] ?? []);

            Log::info("Búsqueda PIIDA completada", [
                'query' => $query,
                'results_count' => count($results),
                'filters' => $filters
            ]);

            return $results;

        } catch (RequestException $e) {
            Log::error('Búsqueda PIIDA falló: ' . $e->getMessage(), [
                'query' => $query,
                'filters' => $filters
            ]);
            return [];
        }
    }

    /**
     * Búsqueda semántica específica para servicios de Notion
     */
    public function searchNotionServices(
        string $query,
        array $filters = [],
        int $limit = 5,
        float $scoreThreshold = 0.5
    ): array {
        try {
            // Generar embedding de la consulta con contexto de servicios Notion
            $contextualQuery = $this->enrichNotionQueryContext($query, $filters);
            $queryEmbedding = $this->generateEmbeddingWithRetry($contextualQuery);

            if (empty($queryEmbedding)) {
                Log::warning("No se pudo generar embedding para consulta: {$query}");
                return [];
            }

            // Construir filtros específicos de servicios Notion
            $qdrantFilters = $this->buildNotionFilters($filters);

            // Configurar búsqueda
            $searchData = [
                'vector' => $queryEmbedding,
                'limit' => $limit,
                'score_threshold' => $scoreThreshold,
                'with_payload' => true,
                'with_vector' => false
            ];

            if (!empty($qdrantFilters)) {
                $searchData['filter'] = $qdrantFilters;
            }

            $response = $this->client->post("/collections/{$this->collectionName}/points/search", [
                'json' => $searchData
            ]);

            $data = json_decode($response->getBody(), true);
            $results = $this->processNotionSearchResults($data['result'] ?? []);

            Log::info("Búsqueda de servicios Notion completada", [
                'query' => $query,
                'results_count' => count($results),
                'filters' => $filters
            ]);

            return $results;

        } catch (RequestException $e) {
            Log::error('Búsqueda de servicios Notion falló: ' . $e->getMessage(), [
                'query' => $query,
                'filters' => $filters
            ]);
            return [];
        }
    }

    /**
     * Búsqueda híbrida que combina semántica y palabras clave
     */
    public function hybridSearchPiida(
        string $query,
        array $filters = [],
        int $limit = 5
    ): array {
        // 1. Búsqueda semántica
        $semanticResults = $this->searchPiidaContent($query, $filters, $limit);

        // 2. Búsqueda por palabras clave en base de datos
        $keywordResults = $this->keywordSearchPiida($query, $filters, $limit);

        // 3. Combinar y rankear resultados
        return $this->mergeAndRankResults($semanticResults, $keywordResults, $limit);
    }

    /**
     * Obtener sugerencias relacionadas basadas en contenido PIIDA
     */
    public function getPiidaSuggestions(int $contentId, int $limit = 3): array
    {
        try {
            // Obtener el vector del contenido original
            $response = $this->client->get("/collections/{$this->collectionName}/points/{$contentId}");
            $data = json_decode($response->getBody(), true);

            if (!isset($data['result']['vector'])) {
                return [];
            }

            $originalVector = $data['result']['vector'];
            $originalPayload = $data['result']['payload'];

            // Buscar contenido similar
            $searchData = [
                'vector' => $originalVector,
                'limit' => $limit + 1, // +1 para excluir el original
                'with_payload' => true,
                'filter' => [
                    'must_not' => [
                        ['key' => 'id', 'match' => ['value' => $contentId]]
                    ]
                ]
            ];

            // Filtrar por categoría similar si es posible
            if (isset($originalPayload['category'])) {
                $searchData['filter']['should'] = [
                    ['key' => 'category', 'match' => ['value' => $originalPayload['category']]]
                ];
            }

            $response = $this->client->post("/collections/{$this->collectionName}/points/search", [
                'json' => $searchData
            ]);

            $data = json_decode($response->getBody(), true);
            return $this->processPiidaSearchResults($data['result'] ?? []);

        } catch (RequestException $e) {
            Log::error("Error obteniendo sugerencias PIIDA para contenido {$contentId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Métodos auxiliares privados
     */

    private function createEnrichedText($item): string
    {
        $text = $item->title . "\n\n" . $item->content;

        // Agregar contexto de categoría PIIDA
        $categoryName = config("services.piida.categories.{$item->category}", $item->category);
        $text .= "\n\nCategoría: " . $categoryName;

        // Agregar información de departamento
        if ($item->department && $item->department !== 'GENERAL') {
            $text .= "\nDepartamento: " . $item->department;
        }

        // Agregar palabras clave
        $keywords = json_decode($item->keywords, true);
        if (!empty($keywords)) {
            $text .= "\nPalabras clave: " . implode(', ', $keywords);
        }

        // Agregar información de contacto si existe
        if ($item->contact_info) {
            $text .= "\nContacto: " . $item->contact_info;
        }

        // Agregar contexto temporal
        $text .= "\nÚltima actualización: " . $item->updated_at;

        return $text;
    }

    private function createEnrichedPayload($item): array
    {
        $userTypes = json_decode($item->user_types, true) ?? [];
        $keywords = json_decode($item->keywords, true) ?? [];

        return [
            'title' => $item->title,
            'content_preview' => substr($item->content, 0, 500),
            'category' => $item->category,
            'category_name' => config("services.piida.categories.{$item->category}", $item->category),
            'department' => $item->department,
            'user_types' => $userTypes,
            'keywords' => $keywords,
            'source_url' => $item->source_url,
            'contact_info' => $item->contact_info,
            'priority' => $item->priority,
            'source_type' => 'piida',
            'is_active' => true,
            'indexed_at' => now()->toISOString(),
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at
        ];
    }

    private function enrichQueryContext(string $query, array $filters): string
    {
        $enrichedQuery = $query;

        // Agregar contexto de usuario si está especificado
        if (isset($filters['user_type'])) {
            $enrichedQuery .= " (contexto: " . $filters['user_type'] . ")";
        }

        // Agregar contexto de categoría si está especificado
        if (isset($filters['category'])) {
            $categoryName = config("services.piida.categories.{$filters['category']}", $filters['category']);
            $enrichedQuery .= " (categoría: " . $categoryName . ")";
        }

        // Agregar contexto institucional
        $enrichedQuery .= " Universidad Autónoma de Nayarit UAN PIIDA";

        return $enrichedQuery;
    }

    private function buildPiidaFilters(array $filters): array
    {
        $qdrantFilters = ['must' => []];

        // Filtro por tipo de usuario - Solo aplicar si NO es contenido de Notion
        if (isset($filters['user_type']) && (!isset($filters['source_type']) || $filters['source_type'] !== 'notion')) {
            $qdrantFilters['must'][] = [
                'key' => 'user_types',
                'match' => ['any' => [$filters['user_type']]]
            ];
        }

        // Filtro por categoría
        if (isset($filters['category'])) {
            $qdrantFilters['must'][] = [
                'key' => 'category',
                'match' => ['value' => $filters['category']]
            ];
        }

        // Filtro por departamento
        if (isset($filters['department'])) {
            $qdrantFilters['must'][] = [
                'key' => 'department',
                'match' => ['value' => $filters['department']]
            ];
        }

        // Filtro por prioridad
        if (isset($filters['priority'])) {
            $qdrantFilters['must'][] = [
                'key' => 'priority',
                'match' => ['value' => $filters['priority']]
            ];
        }

        // Siempre filtrar por contenido activo - Solo para contenido PIIDA, no Notion
        if (!isset($filters['source_type']) || $filters['source_type'] !== 'notion') {
            $qdrantFilters['must'][] = [
                'key' => 'is_active',
                'match' => ['value' => true]
            ];
        }

        // Filtrar por tipo de fuente (PIIDA o Notion)
        if (isset($filters['source_type'])) {
            $qdrantFilters['must'][] = [
                'key' => 'source_type',
                'match' => ['value' => $filters['source_type']]
            ];
        } else {
            // Por defecto, buscar en PIIDA
            $qdrantFilters['must'][] = [
                'key' => 'source_type',
                'match' => ['value' => 'piida']
            ];
        }

        return $qdrantFilters;
    }

    /**
     * Insertar o actualizar puntos en la colección
     */
    public function upsertPoints(array $points): bool
    {
        try {
            $response = $this->client->put("/collections/{$this->collectionName}/points", [
                'json' => ['points' => $points]
            ]);

            return $response->getStatusCode() === 200;

        } catch (\Exception $e) {
            Log::error('Error upserting points to Qdrant: ' . $e->getMessage());
            return false;
        }
    }

    private function processPiidaSearchResults(array $results): array
    {
        $processed = [];

        foreach ($results as $result) {
            $payload = $result['payload'] ?? [];

            $processed[] = [
                'id' => $result['id'],
                'score' => $result['score'],
                'title' => $payload['title'] ?? '',
                'content_preview' => $payload['content_preview'] ?? '',
                'category' => $payload['category'] ?? '',
                'category_name' => $payload['category_name'] ?? '',
                'department' => $payload['department'] ?? '',
                'user_types' => $payload['user_types'] ?? [],
                'keywords' => $payload['keywords'] ?? [],
                'contact_info' => $payload['contact_info'] ?? '',
                'source_url' => $payload['source_url'] ?? '',
                'priority' => $payload['priority'] ?? 'medium',
                'search_type' => 'semantic',
                'relevance_explanation' => $this->generateRelevanceExplanation($result, $payload)
            ];
        }

        return $processed;
    }

    private function generateEmbeddingWithRetry(string $text, int $maxRetries = 3): array
    {
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $embedding = $this->ollamaService->generateEmbedding($text);

                if (!empty($embedding) && count($embedding) === $this->vectorSize) {
                    return $embedding;
                }

                Log::warning("Embedding inválido en intento {$attempt}, reintentando...");

            } catch (\Exception $e) {
                Log::warning("Error generando embedding en intento {$attempt}: " . $e->getMessage());
            }

            if ($attempt < $maxRetries) {
                sleep(1); // Pausa antes del siguiente intento
            }
        }

        return [];
    }

    private function vectorExists($id): bool
    {
        try {
            $response = $this->client->get("/collections/{$this->collectionName}/points/{$id}");
            return $response->getStatusCode() === 200;
        } catch (RequestException $e) {
            return false;
        }
    }

    private function needsUpdate($item): bool
    {
        try {
            $response = $this->client->get("/collections/{$this->collectionName}/points/{$item->id}");
            $data = json_decode($response->getBody(), true);

            $storedUpdatedAt = $data['result']['payload']['updated_at'] ?? null;
            $currentUpdatedAt = $item->updated_at;

            return $storedUpdatedAt !== $currentUpdatedAt;

        } catch (RequestException $e) {
            return true; // Si no podemos verificar, mejor actualizar
        }
    }

    private function processBatch(array $points): array
    {
        try {
            $response = $this->client->put("/collections/{$this->collectionName}/points", [
                'json' => ['points' => $points]
            ]);

            return [
                'success' => $response->getStatusCode() === 200 ? count($points) : 0,
                'errors' => $response->getStatusCode() === 200 ? 0 : count($points)
            ];

        } catch (RequestException $e) {
            Log::error('Error procesando lote en Qdrant: ' . $e->getMessage());
            return ['success' => 0, 'errors' => count($points)];
        }
    }

    private function optimizeCollection(): void
    {
        try {
            $this->client->post("/collections/{$this->collectionName}/index", [
                'json' => ['wait' => true]
            ]);

            Log::info("Colección {$this->collectionName} optimizada");
        } catch (RequestException $e) {
            Log::warning("No se pudo optimizar la colección: " . $e->getMessage());
        }
    }

    private function keywordSearchPiida(string $query, array $filters, int $limit): array
    {
        // Implementar búsqueda por palabras clave en la base de datos
        // como fallback para complementar la búsqueda semántica

        $dbQuery = DB::table('knowledge_base')
            ->where('is_active', true)
            ->whereIn('category', array_keys(config('services.piida.categories', [])));

        // Aplicar filtros
        if (isset($filters['user_type'])) {
            $dbQuery->whereRaw('JSON_CONTAINS(user_types, ?)', [json_encode($filters['user_type'])]);
        }

        if (isset($filters['category'])) {
            $dbQuery->where('category', $filters['category']);
        }

        if (isset($filters['department'])) {
            $dbQuery->where('department', $filters['department']);
        }

        // Búsqueda por texto
        $dbQuery->where(function($q) use ($query) {
            $q->where('title', 'LIKE', "%{$query}%")
              ->orWhere('content', 'LIKE', "%{$query}%")
              ->orWhereRaw('JSON_SEARCH(keywords, "one", ?) IS NOT NULL', ["%{$query}%"]);
        });

        $results = $dbQuery->orderBy('priority', 'desc')
            ->limit($limit)
            ->get(['id', 'title', 'content', 'category', 'department', 'contact_info', 'keywords'])
            ->map(function($item) {
                return [
                    'id' => $item->id,
                    'score' => 0.8, // Score fijo para resultados de palabras clave
                    'title' => $item->title,
                    'content_preview' => substr($item->content, 0, 500),
                    'category' => $item->category,
                    'department' => $item->department,
                    'contact_info' => $item->contact_info,
                    'search_type' => 'keyword'
                ];
            })
            ->toArray();

        return $results;
    }

    private function mergeAndRankResults(array $semanticResults, array $keywordResults, int $limit): array
    {
        // Combinar resultados eliminando duplicados
        $combined = collect($semanticResults)
            ->concat(collect($keywordResults))
            ->unique('id')
            ->sortByDesc(function($item) {
                // Priorizar resultados semánticos con score alto
                $baseScore = $item['score'];
                if ($item['search_type'] === 'semantic' && $baseScore > 0.8) {
                    $baseScore += 0.1;
                }
                return $baseScore;
            })
            ->take($limit)
            ->values()
            ->toArray();

        return $combined;
    }

    private function generateRelevanceExplanation(array $result, array $payload): string
    {
        $score = $result['score'];
        $category = $payload['category_name'] ?? $payload['category'] ?? '';

        if ($score > 0.9) {
            return "Altamente relevante - Coincidencia semántica muy fuerte en {$category}";
        } elseif ($score > 0.8) {
            return "Muy relevante - Buena coincidencia semántica en {$category}";
        } elseif ($score > 0.7) {
            return "Relevante - Coincidencia moderada en {$category}";
        } else {
            return "Parcialmente relevante - Coincidencia básica en {$category}";
        }
    }

    /**
     * Obtener estadísticas detalladas de la colección PIIDA
     */
    public function getPiidaCollectionStats(): array
    {
        try {
            $response = $this->client->get("/collections/{$this->collectionName}");
            $data = json_decode($response->getBody(), true);

            $collectionInfo = $data['result'] ?? [];

            // Obtener estadísticas por categoría
            $categoryStats = $this->getCategoryStats();

            return [
                'collection_name' => $this->collectionName,
                'total_points' => $collectionInfo['points_count'] ?? 0,
                'indexed_points' => $collectionInfo['indexed_vectors_count'] ?? 0,
                'collection_status' => $collectionInfo['status'] ?? 'unknown',
                'vector_size' => $this->vectorSize,
                'distance_metric' => $this->distanceMetric,
                'categories' => $categoryStats,
                'last_updated' => now()->toISOString(),
                'health_status' => $this->isHealthy() ? 'healthy' : 'unhealthy'
            ];

        } catch (RequestException $e) {
            Log::error('Error obteniendo estadísticas de colección PIIDA: ' . $e->getMessage());
            return [
                'error' => 'No se pudieron obtener las estadísticas',
                'health_status' => 'unhealthy'
            ];
        }
    }

    /**
     * Obtener estadísticas por categoría PIIDA
     */
    private function getCategoryStats(): array
    {
        try {
            $stats = [];
            $categories = config('services.piida.categories', []);

            foreach ($categories as $categoryKey => $categoryName) {
                $response = $this->client->post("/collections/{$this->collectionName}/points/count", [
                    'json' => [
                        'filter' => [
                            'must' => [
                                ['key' => 'category', 'match' => ['value' => $categoryKey]],
                                ['key' => 'is_active', 'match' => ['value' => true]]
                            ]
                        ]
                    ]
                ]);

                $countData = json_decode($response->getBody(), true);
                $count = $countData['result']['count'] ?? 0;

                $stats[$categoryKey] = [
                    'name' => $categoryName,
                    'count' => $count,
                    'percentage' => 0 // Se calculará después
                ];
            }

            // Calcular porcentajes
            $total = array_sum(array_column($stats, 'count'));
            if ($total > 0) {
                foreach ($stats as $key => $category) {
                    $stats[$key]['percentage'] = round(($category['count'] / $total) * 100, 2);
                }
            }

            return $stats;

        } catch (RequestException $e) {
            Log::error('Error obteniendo estadísticas por categoría: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Limpiar vectores obsoletos
     */
    public function cleanupObsoleteVectors(): array
    {
        try {
            $results = ['deleted' => 0, 'errors' => 0];

            // Obtener IDs de contenido activo en la base de datos
            $activeIds = DB::table('knowledge_base')
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();

            // Obtener todos los puntos en Qdrant
            $response = $this->client->post("/collections/{$this->collectionName}/points/scroll", [
                'json' => [
                    'limit' => 10000,
                    'with_payload' => true,
                    'with_vector' => false
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            $existingPoints = $data['result']['points'] ?? [];

            // Identificar puntos obsoletos
            $obsoleteIds = [];
            foreach ($existingPoints as $point) {
                $pointId = $point['id'];
                if (!in_array($pointId, $activeIds)) {
                    $obsoleteIds[] = $pointId;
                }
            }

            // Eliminar puntos obsoletos en lotes
            $batchSize = 100;
            $batches = array_chunk($obsoleteIds, $batchSize);

            foreach ($batches as $batch) {
                try {
                    $this->client->post("/collections/{$this->collectionName}/points/delete", [
                        'json' => ['points' => $batch]
                    ]);

                    $results['deleted'] += count($batch);
                    Log::info("Eliminados " . count($batch) . " vectores obsoletos");

                } catch (RequestException $e) {
                    Log::error("Error eliminando lote de vectores: " . $e->getMessage());
                    $results['errors'] += count($batch);
                }
            }

            return $results;

        } catch (RequestException $e) {
            Log::error('Error en limpieza de vectores obsoletos: ' . $e->getMessage());
            return ['deleted' => 0, 'errors' => 1, 'message' => $e->getMessage()];
        }
    }

    /**
     * Respaldar colección
     */
    public function backupCollection(string $backupPath = null): bool
    {
        try {
            $backupPath = $backupPath ?? storage_path('backups/qdrant');

            if (!is_dir($backupPath)) {
                mkdir($backupPath, 0755, true);
            }

            $backupFile = $backupPath . "/piida_collection_" . date('Y-m-d_H-i-s') . ".json";

            // Obtener todos los puntos
            $response = $this->client->post("/collections/{$this->collectionName}/points/scroll", [
                'json' => [
                    'limit' => 10000,
                    'with_payload' => true,
                    'with_vector' => true
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            $backupData = [
                'collection_name' => $this->collectionName,
                'backup_date' => now()->toISOString(),
                'vector_size' => $this->vectorSize,
                'distance_metric' => $this->distanceMetric,
                'points' => $data['result']['points'] ?? []
            ];

            file_put_contents($backupFile, json_encode($backupData, JSON_PRETTY_PRINT));

            Log::info("Respaldo de colección PIIDA creado: {$backupFile}");
            return true;

        } catch (\Exception $e) {
            Log::error('Error creando respaldo de colección: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Restaurar colección desde respaldo
     */
    public function restoreCollection(string $backupFile): bool
    {
        try {
            if (!file_exists($backupFile)) {
                throw new \Exception("Archivo de respaldo no encontrado: {$backupFile}");
            }

            $backupData = json_decode(file_get_contents($backupFile), true);

            if (!$backupData || !isset($backupData['points'])) {
                throw new \Exception("Archivo de respaldo inválido");
            }

            // Recrear colección
            $this->deleteCollection();
            $this->createPiidaCollection();

            // Restaurar puntos en lotes
            $points = $backupData['points'];
            $batchSize = 50;
            $batches = array_chunk($points, $batchSize);

            foreach ($batches as $batch) {
                $this->client->put("/collections/{$this->collectionName}/points", [
                    'json' => ['points' => $batch]
                ]);
            }

            Log::info("Colección PIIDA restaurada desde: {$backupFile}");
            return true;

        } catch (\Exception $e) {
            Log::error('Error restaurando colección: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Eliminar colección
     */
    public function deleteCollection(): bool
    {
        try {
            $this->client->delete("/collections/{$this->collectionName}");
            Log::info("Colección {$this->collectionName} eliminada");
            return true;

        } catch (RequestException $e) {
            Log::error("Error eliminando colección: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verificar integridad de la colección
     */
    public function verifyCollectionIntegrity(): array
    {
        $issues = [];
        $stats = ['total_checked' => 0, 'issues_found' => 0];

        try {
            // Verificar que todos los registros activos tienen vectores
            $activeRecords = DB::table('knowledge_base')
                ->where('is_active', true)
                ->whereIn('category', array_keys(config('services.piida.categories', [])))
                ->pluck('id');

            foreach ($activeRecords as $recordId) {
                $stats['total_checked']++;

                if (!$this->vectorExists($recordId)) {
                    $issues[] = "Falta vector para registro {$recordId}";
                    $stats['issues_found']++;
                }
            }

            // Verificar vectores huérfanos
            $response = $this->client->post("/collections/{$this->collectionName}/points/scroll", [
                'json' => [
                    'limit' => 10000,
                    'with_payload' => false,
                    'with_vector' => false
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            $vectorIds = array_column($data['result']['points'] ?? [], 'id');

            foreach ($vectorIds as $vectorId) {
                $exists = DB::table('knowledge_base')
                    ->where('id', $vectorId)
                    ->where('is_active', true)
                    ->exists();

                if (!$exists) {
                    $issues[] = "Vector huérfano encontrado: {$vectorId}";
                    $stats['issues_found']++;
                }
            }

        } catch (\Exception $e) {
            $issues[] = "Error durante verificación: " . $e->getMessage();
            $stats['issues_found']++;
        }

        return [
            'integrity_status' => empty($issues) ? 'healthy' : 'issues_found',
            'statistics' => $stats,
            'issues' => $issues,
            'checked_at' => now()->toISOString()
        ];
    }

    /**
     * Métodos específicos para servicios de Notion
     */

    /**
     * Enriquecer contexto de consulta para servicios de Notion
     */
    private function enrichNotionQueryContext(string $query, array $filters): string
    {
        $enrichedQuery = $query;

        // Agregar contexto de tipo de usuario si está especificado
        if (isset($filters['user_type'])) {
            $enrichedQuery .= " (usuario: " . $filters['user_type'] . ")";
        }

        // Agregar contexto de categoría si está especificado
        if (isset($filters['categoria'])) {
            $enrichedQuery .= " (categoría: " . $filters['categoria'] . ")";
        }

        // Agregar contexto de dependencia si está especificado
        if (isset($filters['dependencia'])) {
            $enrichedQuery .= " (dependencia: " . $filters['dependencia'] . ")";
        }

        // Agregar contexto institucional específico para servicios
        $enrichedQuery .= " Universidad Autónoma de Nayarit UAN servicios institucionales";

        return $enrichedQuery;
    }

    /**
     * Construir filtros específicos para servicios de Notion
     */
    private function buildNotionFilters(array $filters): array
    {
        $qdrantFilters = ['must' => []];

        // Filtro por tipo de usuario - solo para servicios específicos
        if (isset($filters['user_type']) && isset($filters['usuarios_especificos'])) {
            $qdrantFilters['must'][] = [
                'key' => 'usuarios',
                'match' => ['any' => [$filters['user_type']]]
            ];
        }

        // Filtro por categoría de servicio
        if (isset($filters['categoria'])) {
            $qdrantFilters['must'][] = [
                'key' => 'categoria',
                'match' => ['value' => $filters['categoria']]
            ];
        }

        // Filtro por subcategoría
        if (isset($filters['subcategoria'])) {
            $qdrantFilters['must'][] = [
                'key' => 'subcategoria',
                'match' => ['value' => $filters['subcategoria']]
            ];
        }

        // Filtro por dependencia
        if (isset($filters['dependencia'])) {
            $qdrantFilters['must'][] = [
                'key' => 'dependencia',
                'match' => ['value' => $filters['dependencia']]
            ];
        }

        // Filtro por modalidad
        if (isset($filters['modalidad'])) {
            $qdrantFilters['must'][] = [
                'key' => 'modalidad',
                'match' => ['value' => $filters['modalidad']]
            ];
        }

        // Filtro por estado activo
        $qdrantFilters['must'][] = [
            'key' => 'estado',
            'match' => ['value' => 'Activo']
        ];

        // Filtrar por tipo de fuente (solo Notion)
        $qdrantFilters['must'][] = [
            'key' => 'source_type',
            'match' => ['value' => 'notion']
        ];

        return $qdrantFilters;
    }

    /**
     * Procesar resultados de búsqueda de servicios Notion
     */
    private function processNotionSearchResults(array $results): array
    {
        $processed = [];

        foreach ($results as $result) {
            $payload = $result['payload'] ?? [];

            $processed[] = [
                'id' => $result['id'],
                'score' => $result['score'],
                'title' => $payload['title'] ?? '',
                'content_preview' => $payload['content_preview'] ?? '',
                'categoria' => $payload['categoria'] ?? '',
                'subcategoria' => $payload['subcategoria'] ?? '',
                'dependencia' => $payload['dependencia'] ?? '',
                'modalidad' => $payload['modalidad'] ?? '',
                'usuarios' => $payload['usuarios'] ?? '',
                'estado' => $payload['estado'] ?? '',
                'costo' => $payload['costo'] ?? '',
                'contact_info' => $payload['contact_info'] ?? '',
                'search_type' => 'semantic_notion',
                'relevance_explanation' => $this->generateNotionRelevanceExplanation($result, $payload)
            ];
        }

        return $processed;
    }

    /**
     * Generar explicación de relevancia para servicios de Notion
     */
    private function generateNotionRelevanceExplanation(array $result, array $payload): string
    {
        $score = $result['score'];
        $categoria = $payload['categoria'] ?? '';
        $subcategoria = $payload['subcategoria'] ?? '';

        if ($score > 0.9) {
            return "Altamente relevante - Coincidencia exacta en {$categoria} ({$subcategoria})";
        } elseif ($score > 0.8) {
            return "Muy relevante - Buena coincidencia en {$categoria}";
        } elseif ($score > 0.7) {
            return "Relevante - Servicio relacionado en {$categoria}";
        } else {
            return "Parcialmente relevante - Servicio disponible en {$categoria}";
        }
    }

    /**
 * Indexar un único punto en Qdrant
    *
    * @param array $point Estructura del punto con 'id', 'content' y 'metadata'
    * @return bool
    */
    public function indexSinglePoint(array $point): bool
    {
        try {
            // Validar estructura del punto
            if (!isset($point['id']) || !isset($point['content'])) {
                Log::error('Punto inválido: falta id o content');
                return false;
            }

            // Generar embedding del contenido
            $embedding = $this->generateEmbeddingWithRetry($point['content']);

            if (empty($embedding)) {
                Log::error('No se pudo generar embedding para el contenido');
                return false;
            }

            // Preparar punto para Qdrant
            $qdrantPoint = [
                'id' => (int) $point['id'],
                'vector' => $embedding,
                'payload' => array_merge(
                    [
                        'content' => $point['content'],
                        'indexed_at' => now()->toIso8601String(),
                    ],
                    $point['metadata'] ?? []
                )
            ];

            // Enviar a Qdrant
            $response = $this->client->put(
                "/collections/{$this->collectionName}/points",
                [
                    'json' => [
                        'points' => [$qdrantPoint]
                    ]
                ]
            );

            $result = json_decode($response->getBody(), true);

            if ($result['status'] === 'ok') {
                Log::info("Punto indexado exitosamente", [
                    'id' => $point['id'],
                    'title' => $point['metadata']['title'] ?? 'Sin título'
                ]);
                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error('Error indexando punto individual: ' . $e->getMessage(), [
                'point_id' => $point['id'] ?? 'unknown'
            ]);
            return false;
        }
    }

    /**
     * Indexar múltiples puntos en lote (método alternativo mejorado)
     *
     * @param array $points Array de puntos
     * @param int $batchSize Tamaño del lote
     * @return array Resultados de indexación
     */
    public function indexMultiplePoints(array $points, int $batchSize = 100): array
    {
        $results = [
            'indexed' => 0,
            'failed' => 0,
            'errors' => []
        ];

        // Procesar en lotes
        $batches = array_chunk($points, $batchSize);

        foreach ($batches as $batchIndex => $batch) {
            try {
                $qdrantPoints = [];

                foreach ($batch as $point) {
                    // Validar punto
                    if (!isset($point['id']) || !isset($point['content'])) {
                        $results['failed']++;
                        $results['errors'][] = "Punto inválido en lote {$batchIndex}";
                        continue;
                    }

                    // Generar embedding
                    $embedding = $this->generateEmbeddingWithRetry($point['content']);

                    if (empty($embedding)) {
                        $results['failed']++;
                        $results['errors'][] = "No se pudo generar embedding para punto {$point['id']}";
                        continue;
                    }

                    $qdrantPoints[] = [
                        'id' => (int) $point['id'],
                        'vector' => $embedding,
                        'payload' => array_merge(
                            [
                                'content' => $point['content'],
                                'indexed_at' => now()->toIso8601String(),
                            ],
                            $point['metadata'] ?? []
                        )
                    ];
                }

                if (!empty($qdrantPoints)) {
                    // Enviar lote a Qdrant
                    $response = $this->client->put(
                        "/collections/{$this->collectionName}/points",
                        [
                            'json' => [
                                'points' => $qdrantPoints
                            ]
                        ]
                    );

                    $result = json_decode($response->getBody(), true);

                    if ($result['status'] === 'ok') {
                        $results['indexed'] += count($qdrantPoints);
                    } else {
                        $results['failed'] += count($qdrantPoints);
                        $results['errors'][] = "Error en lote {$batchIndex}: " . ($result['error'] ?? 'Unknown error');
                    }
                }

            } catch (\Exception $e) {
                $results['failed'] += count($batch);
                $results['errors'][] = "Error procesando lote {$batchIndex}: " . $e->getMessage();
            }
        }

        Log::info("Indexación múltiple completada", $results);

        return $results;
    }

    /**
     * Actualizar punto existente en Qdrant
     *
     * @param int $pointId ID del punto a actualizar
     * @param array $updates Array con 'content' y/o 'metadata' para actualizar
     * @return bool
     */
    public function updatePoint(int $pointId, array $updates): bool
    {
        try {
            $updateData = [];

            // Si se actualiza el contenido, regenerar embedding
            if (isset($updates['content'])) {
                $embedding = $this->generateEmbeddingWithRetry($updates['content']);

                if (empty($embedding)) {
                    Log::error('No se pudo generar embedding para actualización');
                    return false;
                }

                // Actualizar vector
                $vectorResponse = $this->client->put(
                    "/collections/{$this->collectionName}/points/vectors",
                    [
                        'json' => [
                            'points' => [
                                [
                                    'id' => $pointId,
                                    'vector' => $embedding
                                ]
                            ]
                        ]
                    ]
                );

                $vectorResult = json_decode($vectorResponse->getBody(), true);
                if ($vectorResult['status'] !== 'ok') {
                    return false;
                }
            }

            // Actualizar payload si hay metadatos
            if (isset($updates['metadata']) || isset($updates['content'])) {
                $payload = $updates['metadata'] ?? [];

                if (isset($updates['content'])) {
                    $payload['content'] = $updates['content'];
                }

                $payload['updated_at'] = now()->toIso8601String();

                $payloadResponse = $this->client->post(
                    "/collections/{$this->collectionName}/points/payload",
                    [
                        'json' => [
                            'payload' => $payload,
                            'points' => [$pointId]
                        ]
                    ]
                );

                $payloadResult = json_decode($payloadResponse->getBody(), true);
                return $payloadResult['status'] === 'ok';
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Error actualizando punto: ' . $e->getMessage(), [
                'point_id' => $pointId
            ]);
            return false;
        }
    }

    /**
     * Eliminar punto de Qdrant
     *
     * @param int|array $pointIds ID o array de IDs a eliminar
     * @return bool
     */
    public function deletePoints($pointIds): bool
    {
        try {
            $ids = is_array($pointIds) ? $pointIds : [$pointIds];

            $response = $this->client->post(
                "/collections/{$this->collectionName}/points/delete",
                [
                    'json' => [
                        'points' => array_map('intval', $ids)
                    ]
                ]
            );

            $result = json_decode($response->getBody(), true);

            if ($result['status'] === 'ok') {
                Log::info('Puntos eliminados exitosamente', ['count' => count($ids)]);
                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error('Error eliminando puntos: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Indexar contenido de Notion directamente en Qdrant
     */
    public function indexNotionContent(array $notionData): array
    {
        $results = ['indexed' => 0, 'errors' => 0, 'skipped' => 0];
        $points = [];

        foreach ($notionData as $item) {
            try {
                // Verificar si ya existe
                if ($this->vectorExists($item['id'])) {
                    Log::info("Vector ya existe para Notion ID: {$item['id']}, actualizando...");
                }

                // Crear texto enriquecido para embedding
                $enrichedText = $this->createNotionEnrichedText($item);

                // Generar embedding
                $embedding = $this->generateEmbeddingWithRetry($enrichedText);

                if (empty($embedding)) {
                    Log::warning("No se pudo generar embedding para Notion ID: {$item['id']}");
                    $results['errors']++;
                    continue;
                }

                // Preparar punto para Qdrant
                $points[] = [
                    'id' => $item['id'],
                    'vector' => $embedding,
                    'payload' => $this->createNotionPayload($item)
                ];

                $results['indexed']++;

            } catch (\Exception $e) {
                Log::error("Error procesando item Notion {$item['id']}: " . $e->getMessage());
                $results['errors']++;
            }
        }

        // Insertar puntos en Qdrant
        if (!empty($points)) {
            $batchResult = $this->processBatch($points);
            $results['indexed'] = $batchResult['success'];
            $results['errors'] += $batchResult['errors'];
        }

        Log::info("Indexación Notion completada", $results);
        return $results;
    }

    /**
     * Crear texto enriquecido para embedding de Notion
     */
    private function createNotionEnrichedText(array $item): string
    {
        $metadata = $item['metadata'] ?? [];
        $content = $item['content'] ?? '';

        $enrichedParts = [];

        // Título
        if (!empty($metadata['title'])) {
            $enrichedParts[] = "Título: " . $metadata['title'];
        }

        // Categoría y subcategoría
        if (!empty($metadata['category'])) {
            $enrichedParts[] = "Categoría: " . $metadata['category'];
        }
        if (!empty($metadata['subcategory'])) {
            $enrichedParts[] = "Subcategoría: " . $metadata['subcategory'];
        }

        // Departamento
        if (!empty($metadata['department'])) {
            $enrichedParts[] = "Departamento: " . $metadata['department'];
        }

        // Modalidad y usuarios
        if (!empty($metadata['modality'])) {
            $enrichedParts[] = "Modalidad: " . $metadata['modality'];
        }
        if (!empty($metadata['users'])) {
            $enrichedParts[] = "Usuarios: " . $metadata['users'];
        }

        // Costo
        if (!empty($metadata['cost'])) {
            $enrichedParts[] = "Costo: " . $metadata['cost'];
        }

        // Contenido principal
        $enrichedParts[] = "Contenido: " . $content;

        return implode(" | ", $enrichedParts);
    }

    /**
     * Crear payload para Qdrant desde datos de Notion
     */
    private function createNotionPayload(array $item): array
    {
        $metadata = $item['metadata'] ?? [];

        return [
            'title' => $metadata['title'] ?? '',
            'content_preview' => substr($item['content'] ?? '', 0, 300),
            'category' => $metadata['category'] ?? '',
            'subcategory' => $metadata['subcategory'] ?? '',
            'department' => $metadata['department'] ?? '',
            'service_id' => $metadata['service_id'] ?? '',
            'cost' => $metadata['cost'] ?? '',
            'modality' => $metadata['modality'] ?? '',
            'status' => $metadata['status'] ?? 'Activo',
            'users' => $metadata['users'] ?? '',
            'dependency' => $metadata['dependency'] ?? '',
            'source_type' => 'notion',
            'source_url' => $metadata['source_url'] ?? '',
            'notion_id' => $metadata['notion_id'] ?? '',
            'is_active' => true,
            'indexed_at' => now()->toISOString(),
            'created_time' => $metadata['created_time'] ?? null,
            'last_edited_time' => $metadata['last_edited_time'] ?? null
        ];
    }


}
