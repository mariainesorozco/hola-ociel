<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

class QdrantVectorService
{
    private $client;
    private $baseUrl;
    private $collectionName;
    private $ollamaService;

    public function __construct(OllamaService $ollamaService)
    {
        $this->baseUrl = config('services.qdrant.url', 'http://localhost:6333');
        $this->collectionName = config('services.qdrant.collection', 'uan_knowledge');
        $this->ollamaService = $ollamaService;

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json'
            ]
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
     * Crear colección si no existe
     */
    public function ensureCollection(): bool
    {
        try {
            // Verificar si la colección existe
            $response = $this->client->get("/collections/{$this->collectionName}");

            if ($response->getStatusCode() === 200) {
                return true; // Ya existe
            }
        } catch (RequestException $e) {
            if ($e->getResponse() && $e->getResponse()->getStatusCode() === 404) {
                // La colección no existe, crearla
                return $this->createCollection();
            }

            Log::error('Error checking Qdrant collection: ' . $e->getMessage());
            return false;
        }

        return false;
    }

    /**
     * Crear nueva colección
     */
    private function createCollection(): bool
    {
        try {
            $response = $this->client->put("/collections/{$this->collectionName}", [
                'json' => [
                    'vectors' => [
                        'size' => 768, // Tamaño del vector para nomic-embed-text
                        'distance' => 'Cosine'
                    ],
                    'optimizers_config' => [
                        'default_segment_number' => 2
                    ],
                    'replication_factor' => 1
                ]
            ]);

            return $response->getStatusCode() === 200;

        } catch (RequestException $e) {
            Log::error('Error creating Qdrant collection: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Indexar contenido de la base de conocimientos
     */
    public function indexKnowledgeBase(): array
    {
        if (!$this->ensureCollection()) {
            throw new \Exception('No se pudo crear/acceder a la colección de Qdrant');
        }

        $results = ['indexed' => 0, 'errors' => 0, 'updated' => 0];

        // Obtener todo el contenido activo
        $knowledge = \DB::table('knowledge_base')
            ->where('is_active', true)
            ->select(['id', 'title', 'content', 'category', 'department', 'keywords'])
            ->get();

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

        return $results;
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
                return [];
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
                'score_threshold' => 0.7 // Umbral de similitud
            ];

            if (!empty($searchFilters)) {
                $searchData['filter'] = $searchFilters;
            }

            $response = $this->client->post("/collections/{$this->collectionName}/points/search", [
                'json' => $searchData
            ]);

            $data = json_decode($response->getBody(), true);

            return $this->processSearchResults($data['result'] ?? []);

        } catch (RequestException $e) {
            Log::error('Qdrant search failed: ' . $e->getMessage());
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
     * Insertar/actualizar puntos en Qdrant
     */
    private function upsertPoints(array $points): bool
    {
        try {
            $response = $this->client->put("/collections/{$this->collectionName}/points", [
                'json' => [
                    'points' => $points
                ]
            ]);

            return $response->getStatusCode() === 200;

        } catch (RequestException $e) {
            Log::error('Error upserting points to Qdrant: ' . $e->getMessage());
            return false;
        }
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
            $response = $this->client->post("/collections/{$this->collectionName}/points/delete", [
                'json' => [
                    'points' => [$id]
                ]
            ]);

            return $response->getStatusCode() === 200;

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
                'collection_status' => $data['result']['status'] ?? 'unknown'
            ];

        } catch (RequestException $e) {
            Log::error('Error getting Qdrant collection stats: ' . $e->getMessage());
            return [];
        }
    }
}
