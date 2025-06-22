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
        $this->vectorSize = config('services.qdrant.vector_size', 768);
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

        // Filtro por tipo de usuario
        if (isset($filters['user_type'])) {
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

        // Siempre filtrar por contenido activo
        $qdrantFilters['must'][] = [
            'key' => 'is_active',
            'match' => ['value' => true]
        ];

        // Filtrar por fuente PIIDA
        $qdrantFilters['must'][] = [
            'key' => 'source_type',
            'match' => ['value' => 'piida']
        ];

        return $qdrantFilters;
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

    private function vectorExists(int $id): bool
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
}
