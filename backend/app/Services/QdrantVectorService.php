<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class QdrantVectorService
{
    private $client;
    private $baseUrl;
    private $collectionName;
    private $vectorSize;
    private $ollamaService;

    public function __construct(OllamaService $ollamaService)
    {
        $this->baseUrl = config('services.qdrant.url', 'http://localhost:6333');
        $this->collectionName = config('services.qdrant.collection', 'ociel_knowledge');
        $this->vectorSize = config('services.qdrant.vector_size', 768);
        $this->ollamaService = $ollamaService;

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => config('services.qdrant.timeout', 30),
            'connect_timeout' => 10,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ],
            'http_errors' => false // No lanzar excepciones por códigos de error HTTP
        ]);
    }

    /**
     * Verificar conexión con Qdrant
     */
    public function isHealthy(): bool
    {
        try {
            $response = $this->client->get('/');
            Log::info('Qdrant health check successful', [
                'status_code' => $response->getStatusCode(),
                'url' => $this->baseUrl
            ]);
            return $response->getStatusCode() === 200;
        } catch (RequestException $e) {
            Log::error('Qdrant health check failed', [
                'url' => $this->baseUrl,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Crear colección si no existe - VERSIÓN MEJORADA
     */
    public function ensureCollection(): bool
    {
        try {
            Log::info('Checking if collection exists', [
                'collection' => $this->collectionName,
                'url' => $this->baseUrl
            ]);

            // Verificar si la colección existe
            $response = $this->client->get("/collections/{$this->collectionName}");

            if ($response->getStatusCode() === 200) {
                Log::info('Collection already exists', ['collection' => $this->collectionName]);
                return true;
            }

        } catch (RequestException $e) {
            $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 0;

            Log::info('Collection check response', [
                'collection' => $this->collectionName,
                'status_code' => $statusCode,
                'error' => $e->getMessage()
            ]);

            if ($statusCode === 404) {
                // La colección no existe, intentar crearla
                Log::info('Collection does not exist, creating...', [
                    'collection' => $this->collectionName
                ]);

                return $this->createCollection();
            }

            Log::error('Unexpected error checking collection', [
                'collection' => $this->collectionName,
                'status_code' => $statusCode,
                'error' => $e->getMessage()
            ]);
            return false;
        }

        return false;
    }

    /**
     * Crear nueva colección - VERSIÓN MEJORADA CON DEBUG
     */
    private function createCollection(): bool
    {
        try {
            $collectionConfig = [
                'vectors' => [
                    'size' => $this->vectorSize,
                    'distance' => 'Cosine'
                ]
            ];

            Log::info('Creating Qdrant collection', [
                'collection' => $this->collectionName,
                'config' => $collectionConfig,
                'url' => "{$this->baseUrl}/collections/{$this->collectionName}"
            ]);

            $response = $this->client->put("/collections/{$this->collectionName}", [
                'json' => $collectionConfig
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();

            Log::info('Collection creation response', [
                'collection' => $this->collectionName,
                'status_code' => $statusCode,
                'response_body' => $responseBody
            ]);

            if ($statusCode === 200) {
                Log::info('Collection created successfully', [
                    'collection' => $this->collectionName
                ]);
                return true;
            } else {
                Log::warning('Unexpected status code for collection creation', [
                    'status_code' => $statusCode,
                    'response' => $responseBody
                ]);
                return false;
            }

        } catch (RequestException $e) {
            $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 0;
            $responseBody = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : '';

            Log::error('Error creating Qdrant collection', [
                'collection' => $this->collectionName,
                'status_code' => $statusCode,
                'error' => $e->getMessage(),
                'response_body' => $responseBody,
                'config' => [
                    'vector_size' => $this->vectorSize,
                    'url' => $this->baseUrl
                ]
            ]);

            return false;
        }
    }

    /**
     * Método de debugging para verificar configuración
     */
    public function debugConfiguration(): array
    {
        return [
            'base_url' => $this->baseUrl,
            'collection_name' => $this->collectionName,
            'vector_size' => $this->vectorSize,
            'timeout' => config('services.qdrant.timeout', 30),
            'health_check' => $this->isHealthy()
        ];
    }

    /**
     * Listar todas las colecciones existentes
     */
    public function listCollections(): array
    {
        try {
            $response = $this->client->get('/collections');
            $data = json_decode($response->getBody(), true);

            Log::info('Collections list retrieved', [
                'collections' => $data['result']['collections'] ?? []
            ]);

            return $data['result']['collections'] ?? [];

        } catch (RequestException $e) {
            Log::error('Error listing collections: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Eliminar colección (útil para testing)
     */
    public function deleteCollection(): bool
    {
        try {
            $response = $this->client->delete("/collections/{$this->collectionName}");

            Log::info('Collection deleted', [
                'collection' => $this->collectionName,
                'status_code' => $response->getStatusCode()
            ]);

            return $response->getStatusCode() === 200;

        } catch (RequestException $e) {
            Log::error('Error deleting collection: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Insertar/actualizar puntos en Qdrant
     */
    public function upsertPoints(array $points): bool
    {
        try {
            Log::info('Upserting points to Qdrant', [
                'collection' => $this->collectionName,
                'points_count' => count($points)
            ]);

            $response = $this->client->put("/collections/{$this->collectionName}/points", [
                'json' => [
                    'points' => $points
                ]
            ]);

            $success = $response->getStatusCode() === 200;

            if ($success) {
                Log::info('Points upserted successfully', [
                    'collection' => $this->collectionName,
                    'count' => count($points)
                ]);
            } else {
                Log::warning('Unexpected response code for upsert', [
                    'status_code' => $response->getStatusCode(),
                    'body' => $response->getBody()->getContents()
                ]);
            }

            return $success;

        } catch (RequestException $e) {
            Log::error('Error upserting points to Qdrant', [
                'error' => $e->getMessage(),
                'points_count' => count($points),
                'collection' => $this->collectionName
            ]);
            return false;
        }
    }

    /**
     * Buscar contenido similar usando embeddings
     */
    public function searchSimilarContent(string $query, int $limit = 5, array $filters = []): array
    {
        try {
            // Generar embedding de la consulta
            $queryEmbedding = $this->ollamaService->generateEmbedding($query);

            if (empty($queryEmbedding)) {
                Log::warning('Could not generate embedding for query', ['query' => $query]);
                return [];
            }

            // Verificar que el embedding tenga el tamaño correcto
            if (count($queryEmbedding) !== $this->vectorSize) {
                Log::warning('Embedding size mismatch', [
                    'expected' => $this->vectorSize,
                    'actual' => count($queryEmbedding),
                    'query' => substr($query, 0, 50)
                ]);
            }

            // Preparar filtros para Qdrant
            $searchFilters = [];
            if (!empty($filters)) {
                $searchFilters = $this->buildQdrantFilters($filters);
            }

            // Buscar vectores similares
            $searchData = [
                'vector' => $queryEmbedding,
                'limit' => $limit,
                'with_payload' => true,
                'score_threshold' => 0.6
            ];

            if (!empty($searchFilters)) {
                $searchData['filter'] = $searchFilters;
            }

            Log::info('Performing semantic search', [
                'query' => substr($query, 0, 50),
                'embedding_size' => count($queryEmbedding),
                'limit' => $limit,
                'filters' => $filters
            ]);

            $response = $this->client->post("/collections/{$this->collectionName}/points/search", [
                'json' => $searchData
            ]);

            $data = json_decode($response->getBody(), true);
            $results = $this->processSearchResults($data['result'] ?? []);

            Log::info('Semantic search completed', [
                'query' => substr($query, 0, 50),
                'results_count' => count($results)
            ]);

            return $results;

        } catch (RequestException $e) {
            Log::error('Qdrant search failed', [
                'error' => $e->getMessage(),
                'query' => substr($query, 0, 50),
                'filters' => $filters
            ]);
            return [];
        }
    }

    /**
     * Crear texto combinado para embedding
     */
    private function createCombinedText($item): string
    {
        $text = $item->title . "\n\n" . $item->content;

        // Agregar keywords si existen
        $keywords = json_decode($item->keywords, true);
        if (!empty($keywords)) {
            $text .= "\n\nPalabras clave: " . implode(', ', $keywords);
        }

        // Agregar metadatos contextuales
        $text .= "\n\nCategoría: " . $item->category;
        $text .= "\nDepartamento: " . $item->department;

        return $text;
    }

    /**
     * Construir filtros para Qdrant
     */
    private function buildQdrantFilters(array $filters): array
    {
        $qdrantFilters = [];

        if (isset($filters['category'])) {
            $qdrantFilters['must'][] = [
                'key' => 'category',
                'match' => ['value' => $filters['category']]
            ];
        }

        if (isset($filters['department'])) {
            $qdrantFilters['must'][] = [
                'key' => 'department',
                'match' => ['value' => $filters['department']]
            ];
        }

        return $qdrantFilters;
    }

    /**
     * Procesar resultados de búsqueda
     */
    private function processSearchResults(array $results): array
    {
        $processed = [];

        foreach ($results as $result) {
            $processed[] = [
                'id' => $result['id'],
                'score' => $result['score'],
                'title' => $result['payload']['title'] ?? '',
                'content_preview' => $result['payload']['content_preview'] ?? '',
                'category' => $result['payload']['category'] ?? '',
                'department' => $result['payload']['department'] ?? '',
                'keywords' => $result['payload']['keywords'] ?? [],
                'search_type' => 'semantic'
            ];
        }

        return $processed;
    }

    /**
     * Eliminar contenido del índice
     */
    public function deleteContent(int $id): bool
    {
        try {
            Log::info('Deleting point from Qdrant', ['id' => $id]);

            $response = $this->client->post("/collections/{$this->collectionName}/points/delete", [
                'json' => [
                    'points' => [$id]
                ]
            ]);

            $success = $response->getStatusCode() === 200;

            if ($success) {
                Log::info('Point deleted successfully', ['id' => $id]);
            }

            return $success;

        } catch (RequestException $e) {
            Log::error("Error deleting point {$id} from Qdrant: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener estadísticas de la colección
     */
    public function getCollectionStats(): array
    {
        try {
            $response = $this->client->get("/collections/{$this->collectionName}");
            $data = json_decode($response->getBody(), true);

            return [
                'total_points' => $data['result']['points_count'] ?? 0,
                'indexed_points' => $data['result']['indexed_vectors_count'] ?? 0,
                'collection_status' => $data['result']['status'] ?? 'unknown',
                'collection_name' => $this->collectionName,
                'vector_size' => $this->vectorSize
            ];

        } catch (RequestException $e) {
            Log::error('Error getting Qdrant collection stats: ' . $e->getMessage());
            return [
                'total_points' => 0,
                'indexed_points' => 0,
                'collection_status' => 'error',
                'collection_name' => $this->collectionName,
                'vector_size' => $this->vectorSize
            ];
        }
    }

    /**
     * Indexar toda la base de conocimientos - MÉTODO FALTANTE
     */
    public function indexKnowledgeBase(): array
    {
        if (!$this->ensureCollection()) {
            throw new \Exception('No se pudo crear/acceder a la colección de Qdrant');
        }

        $results = ['indexed' => 0, 'errors' => 0, 'updated' => 0];

        try {
            // Obtener todo el contenido activo
            $knowledge = DB::table('knowledge_base')
                ->where('is_active', true)
                ->select(['id', 'title', 'content', 'category', 'department', 'keywords'])
                ->get();

            Log::info('Starting knowledge base indexing', [
                'total_entries' => $knowledge->count(),
                'collection' => $this->collectionName
            ]);

            $points = [];

            foreach ($knowledge as $item) {
                try {
                    // Crear texto combinado para embedding
                    $combinedText = $this->createCombinedText($item);

                    // Generar embedding
                    $embedding = $this->ollamaService->generateEmbedding($combinedText);

                    if (empty($embedding)) {
                        Log::warning("No se pudo generar embedding para item {$item->id}");
                        $results['errors']++;
                        continue;
                    }

                    // Preparar punto para Qdrant
                    $points[] = [
                        'id' => $item->id,
                        'vector' => $embedding,
                        'payload' => [
                            'title' => $item->title,
                            'content_preview' => substr($item->content, 0, 500),
                            'category' => $item->category,
                            'department' => $item->department,
                            'keywords' => json_decode($item->keywords, true) ?? [],
                            'indexed_at' => now()->toISOString()
                        ]
                    ];

                    // Procesar en lotes de 50
                    if (count($points) >= 50) {
                        if ($this->upsertPoints($points)) {
                            $results['indexed'] += count($points);
                        } else {
                            $results['errors'] += count($points);
                        }
                        $points = [];
                    }

                } catch (\Exception $e) {
                    Log::error("Error indexing knowledge item {$item->id}: " . $e->getMessage());
                    $results['errors']++;
                }
            }

            // Procesar puntos restantes
            if (!empty($points)) {
                if ($this->upsertPoints($points)) {
                    $results['indexed'] += count($points);
                } else {
                    $results['errors'] += count($points);
                }
            }

            Log::info('Knowledge base indexing completed', $results);

            return $results;

        } catch (\Exception $e) {
            Log::error('Knowledge base indexing failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Verificar si un documento específico está indexado
     */
    public function isDocumentIndexed(int $id): bool
    {
        try {
            $response = $this->client->get("/collections/{$this->collectionName}/points/{$id}");
            return $response->getStatusCode() === 200;
        } catch (RequestException $e) {
            return false;
        }
    }
}
