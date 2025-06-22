<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class KnowledgeBaseService
{
    private $ollamaService;
    private $vectorService;

    public function __construct(OllamaService $ollamaService, $vectorService = null)
    {
        $this->ollamaService = $ollamaService;
        $this->vectorService = $vectorService;
    }

    /**
     * Buscar contenido relevante en la base de conocimientos - SOLO BÚSQUEDA VECTORIAL
     */
    public function searchRelevantContent(string $query, string $userType = 'public', ?string $department = null): array
    {
        Log::info('Searching knowledge base with vector search only', [
            'query' => $query,
            'user_type' => $userType,
            'department' => $department
        ]);

        // SOLO búsqueda semántica usando embeddings
        $semanticResults = $this->semanticSearch($query, $userType, $department);

        if ($semanticResults->isEmpty()) {
            Log::warning('No semantic search results found', [
                'query' => $query,
                'user_type' => $userType,
                'department' => $department
            ]);
            return [];
        }

        // Extraer solo el contenido textual para el contexto
        $finalResults = $semanticResults->take(5)->pluck('content')->toArray();

        Log::info('Vector search completed', [
            'semantic_count' => $semanticResults->count(),
            'final_count' => count($finalResults)
        ]);

        return $finalResults;
    }

    /**
     * Búsqueda semántica usando embeddings - AHORA FUNCIONAL
     */
    private function semanticSearch(string $query, string $userType, ?string $department): Collection
    {
        // Verificar si el servicio vectorial está disponible
        if (!$this->vectorService || !$this->vectorService->isHealthy()) {
            Log::warning('Vector service not available for semantic search');
            return collect([]);
        }

        try {
            // Preparar filtros
            $filters = ['user_type' => $userType];
            if ($department) {
                $filters['department'] = $department;
            }

            // Ejecutar búsqueda semántica
            $results = $this->vectorService->searchSimilarContent($query, 5, $filters);

            // Convertir a formato estándar
            return collect($results)->map(function ($result) {
                return (object) [
                    'id' => $result['id'],
                    'title' => $result['title'] ?? '',
                    'content' => $this->getFullContentById($result['id']),
                    'category' => $result['category'] ?? '',
                    'department' => $result['department'] ?? '',
                    'contact_info' => null,
                    'priority' => 'high', // Búsqueda semántica tiene alta prioridad
                    'search_type' => 'semantic',
                    'relevance_score' => $result['score'] ?? 0.8
                ];
            });

        } catch (\Exception $e) {
            Log::error('Semantic search failed', [
                'error' => $e->getMessage(),
                'query' => $query
            ]);
            return collect([]);
        }
    }

    /**
     * Obtener contenido completo por ID
     */
    private function getFullContentById(int $id): string
    {
        $content = DB::table('knowledge_base')
            ->where('id', $id)
            ->where('is_active', true)
            ->value('content');

        return $content ?? '';
    }

    /**
     * Búsqueda por palabras clave usando MySQL Full-Text Search - MEJORADA
     */
    private function keywordSearch(string $query, string $userType, ?string $department): Collection
    {
        $baseQuery = DB::table('knowledge_base')
            ->where('is_active', true)
            ->whereRaw('JSON_CONTAINS(user_types, ?)', [json_encode($userType)]);

        if ($department) {
            $baseQuery->where(function($q) use ($department) {
                $q->where('department', $department)
                  ->orWhere('department', 'GENERAL');
            });
        }

        // Usar full-text search si está disponible
        try {
            $results = $baseQuery
                ->whereRaw('MATCH(title, content) AGAINST(? IN BOOLEAN MODE)', [$this->prepareSearchTerm($query)])
                ->select(['id', 'title', 'content', 'category', 'department', 'contact_info', 'priority'])
                ->orderByRaw('MATCH(title, content) AGAINST(? IN BOOLEAN MODE) DESC', [$this->prepareSearchTerm($query)])
                ->limit(5)
                ->get();

        } catch (\Exception $e) {
            Log::warning('Full-text search failed, using LIKE fallback', ['error' => $e->getMessage()]);

            // Fallback a LIKE search si full-text no funciona
            $results = $baseQuery
                ->where(function($q) use ($query) {
                    $q->where('title', 'LIKE', "%{$query}%")
                      ->orWhere('content', 'LIKE', "%{$query}%")
                      ->orWhereRaw('JSON_SEARCH(keywords, "one", ?) IS NOT NULL', ["%{$query}%"]);
                })
                ->select(['id', 'title', 'content', 'category', 'department', 'contact_info', 'priority'])
                ->orderBy('priority', 'desc')
                ->limit(5)
                ->get();
        }

        return $results->map(function($item) {
            $item->search_type = 'keyword';
            $item->relevance_score = $this->calculateKeywordRelevance($item);
            return $item;
        });
    }

    /**
     * Combinar y rankear resultados de ambos tipos de búsqueda - MEJORADO
     */
    private function combineAndRankResults(Collection $keywordResults, Collection $semanticResults): Collection
    {
        // Si tenemos resultados semánticos, darles prioridad
        if ($semanticResults->isNotEmpty()) {
            // Combinar, dando peso extra a resultados semánticos
            $combined = $semanticResults->map(function ($item) {
                $item->relevance_score = ($item->relevance_score ?? 0.8) + 0.2; // Bonus semántico
                return $item;
            })->concat(
                $keywordResults->filter(function ($keywordItem) use ($semanticResults) {
                    // Evitar duplicados basados en ID
                    return !$semanticResults->contains('id', $keywordItem->id);
                })
            );
        } else {
            // Solo resultados de palabras clave
            $combined = $keywordResults;
        }

        // Ordenar por relevancia combinada
        return $combined->sortByDesc(function ($item) {
            $baseScore = $item->relevance_score ?? 0.5;

            // Bonus por tipo de búsqueda
            $typeBonus = ($item->search_type === 'semantic') ? 0.3 : 0.1;

            // Bonus por prioridad
            switch ($item->priority ?? 'medium') {
                case 'high':
                    $priorityBonus = 0.2;
                    break;
                case 'medium':
                    $priorityBonus = 0.1;
                    break;
                case 'low':
                    $priorityBonus = 0.05;
                    break;
                default:
                    $priorityBonus = 0.1;
                    break;
            }

            return $baseScore + $typeBonus + $priorityBonus;
        });
    }

    /**
     * Calcular relevancia para resultados de búsqueda por palabras clave
     */
    private function calculateKeywordRelevance($item): float
    {
        $score = 0.5; // Base score

        // Bonus por prioridad
        switch ($item->priority) {
            case 'high':
                $score += 0.3;
                break;
            case 'medium':
                $score += 0.2;
                break;
            case 'low':
                $score += 0.1;
                break;
        }

        // Bonus por categoría específica
        if (in_array($item->category, ['tramites', 'servicios'])) {
            $score += 0.2;
        }

        return min(1.0, $score);
    }

    /**
     * Preparar término de búsqueda para MySQL Full-Text
     */
    private function prepareSearchTerm(string $query): string
    {
        // Limpiar y preparar el término para búsqueda booleana
        $terms = preg_split('/\s+/', trim($query));
        $terms = array_filter($terms, function($term) {
            return strlen($term) > 2; // Ignorar términos muy cortos
        });

        // Convertir a formato de búsqueda booleana
        $searchTerms = array_map(function($term) {
            return "+{$term}*"; // Prefijo obligatorio + wildcard
        }, $terms);

        return implode(' ', $searchTerms);
    }

    /**
     * Agregar nuevo contenido a la base de conocimientos - CON AUTO-INDEXACIÓN
     */
    public function addContent(array $data): bool
    {
        try {
            $data['created_at'] = now();
            $data['updated_at'] = now();

            // Validar que user_types sea JSON
            if (isset($data['user_types']) && !is_string($data['user_types'])) {
                $data['user_types'] = json_encode($data['user_types']);
            }

            if (isset($data['keywords']) && !is_string($data['keywords'])) {
                $data['keywords'] = json_encode($data['keywords']);
            }

            // Insertar y obtener ID
            $id = DB::table('knowledge_base')->insertGetId($data);

            // AUTO-INDEXAR EN QDRANT
            $this->autoIndexContent($id, $data);

            return true;

        } catch (\Exception $e) {
            Log::error('Error adding knowledge base content: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Auto-indexar contenido nuevo en la base vectorial
     */
    private function autoIndexContent(int $id, array $data): void
    {
        if (!$this->vectorService || !$this->vectorService->isHealthy()) {
            Log::warning('Vector service not available for auto-indexing', ['content_id' => $id]);
            return;
        }

        try {
            // Crear texto combinado
            $combinedText = $data['title'] . "\n\n" . $data['content'];

            // Agregar keywords si existen
            $keywords = is_string($data['keywords'] ?? '')
                ? json_decode($data['keywords'], true)
                : ($data['keywords'] ?? []);

            if (!empty($keywords)) {
                $combinedText .= "\n\nPalabras clave: " . implode(', ', $keywords);
            }

            // Agregar metadatos
            $combinedText .= "\n\nCategoría: " . ($data['category'] ?? 'general');
            $combinedText .= "\nDepartamento: " . ($data['department'] ?? 'GENERAL');

            // Generar embedding
            $embedding = $this->ollamaService->generateEmbedding($combinedText);

            if (!empty($embedding)) {
                // Preparar payload para Qdrant
                $payload = [
                    'title' => $data['title'],
                    'content_preview' => substr($data['content'], 0, 500),
                    'category' => $data['category'] ?? 'general',
                    'department' => $data['department'] ?? 'GENERAL',
                    'keywords' => $keywords,
                    'indexed_at' => now()->toISOString()
                ];

                // Indexar en Qdrant
                $points = [[
                    'id' => $id,
                    'vector' => $embedding,
                    'payload' => $payload
                ]];

                if ($this->vectorService->upsertPoints($points)) {
                    Log::info('Content auto-indexed successfully', ['content_id' => $id]);
                } else {
                    Log::warning('Failed to auto-index content', ['content_id' => $id]);
                }
            } else {
                Log::warning('Failed to generate embedding for auto-indexing', ['content_id' => $id]);
            }

        } catch (\Exception $e) {
            Log::error('Auto-indexing failed', [
                'content_id' => $id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Actualizar contenido existente - CON RE-INDEXACIÓN
     */
    public function updateContent(int $id, array $data): bool
    {
        try {
            $data['updated_at'] = now();

            // Validar JSON fields
            if (isset($data['user_types']) && !is_string($data['user_types'])) {
                $data['user_types'] = json_encode($data['user_types']);
            }

            if (isset($data['keywords']) && !is_string($data['keywords'])) {
                $data['keywords'] = json_encode($data['keywords']);
            }

            // Actualizar en base de datos
            $updated = DB::table('knowledge_base')
                ->where('id', $id)
                ->update($data);

            if ($updated) {
                // RE-INDEXAR automáticamente
                $this->reIndexContent($id);
                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error('Error updating knowledge base content: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Re-indexar contenido actualizado
     */
    private function reIndexContent(int $id): void
    {
        if (!$this->vectorService || !$this->vectorService->isHealthy()) {
            return;
        }

        try {
            // Obtener contenido actualizado
            $content = DB::table('knowledge_base')
                ->where('id', $id)
                ->where('is_active', true)
                ->first();

            if (!$content) {
                return;
            }

            // Re-generar embedding con contenido actualizado
            $combinedText = $content->title . "\n\n" . $content->content;

            $keywords = json_decode($content->keywords, true) ?? [];
            if (!empty($keywords)) {
                $combinedText .= "\n\nPalabras clave: " . implode(', ', $keywords);
            }

            $combinedText .= "\n\nCategoría: " . $content->category;
            $combinedText .= "\nDepartamento: " . $content->department;

            $embedding = $this->ollamaService->generateEmbedding($combinedText);

            if (!empty($embedding)) {
                $payload = [
                    'title' => $content->title,
                    'content_preview' => substr($content->content, 0, 500),
                    'category' => $content->category,
                    'department' => $content->department,
                    'keywords' => $keywords,
                    'indexed_at' => now()->toISOString()
                ];

                $points = [[
                    'id' => $id,
                    'vector' => $embedding,
                    'payload' => $payload
                ]];

                $this->vectorService->upsertPoints($points);
                Log::info('Content re-indexed successfully', ['content_id' => $id]);
            }

        } catch (\Exception $e) {
            Log::error('Re-indexing failed', [
                'content_id' => $id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Eliminar contenido - CON LIMPIEZA DE ÍNDICE
     */
    public function deleteContent(int $id): bool
    {
        try {
            // Eliminar de base de datos
            $deleted = DB::table('knowledge_base')->where('id', $id)->delete();

            if ($deleted && $this->vectorService && $this->vectorService->isHealthy()) {
                // Eliminar del índice vectorial
                $this->vectorService->deleteContent($id);
                Log::info('Content deleted from both DB and vector index', ['content_id' => $id]);
            }

            return $deleted > 0;

        } catch (\Exception $e) {
            Log::error('Error deleting knowledge base content: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener contenido por categoría
     */
    public function getContentByCategory(string $category, string $userType = 'public'): Collection
    {
        return DB::table('knowledge_base')
            ->where('category', $category)
            ->where('is_active', true)
            ->whereRaw('JSON_CONTAINS(user_types, ?)', [json_encode($userType)])
            ->orderBy('priority', 'desc')
            ->orderBy('title')
            ->get();
    }

    /**
     * Obtener contenido por departamento
     */
    public function getContentByDepartment(string $department, string $userType = 'public'): Collection
    {
        return DB::table('knowledge_base')
            ->where(function($q) use ($department) {
                $q->where('department', $department)
                  ->orWhere('department', 'GENERAL');
            })
            ->where('is_active', true)
            ->whereRaw('JSON_CONTAINS(user_types, ?)', [json_encode($userType)])
            ->orderBy('priority', 'desc')
            ->orderBy('title')
            ->get();
    }

    /**
     * Obtener sugerencias de contenido relacionado
     */
    public function getRelatedContent(int $contentId, int $limit = 3): Collection
    {
        $originalContent = DB::table('knowledge_base')->find($contentId);

        if (!$originalContent) {
            return collect([]);
        }

        // Buscar contenido relacionado por categoría y departamento
        return DB::table('knowledge_base')
            ->where('id', '!=', $contentId)
            ->where('is_active', true)
            ->where(function($q) use ($originalContent) {
                $q->where('category', $originalContent->category)
                  ->orWhere('department', $originalContent->department);
            })
            ->orderBy('priority', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Obtener estadísticas de la base de conocimientos - MEJORADAS
     */
    public function getStats(): array
    {
        $basicStats = [
            'total_entries' => DB::table('knowledge_base')->where('is_active', true)->count(),
            'by_category' => DB::table('knowledge_base')
                ->where('is_active', true)
                ->groupBy('category')
                ->selectRaw('category, COUNT(*) as count')
                ->pluck('count', 'category')
                ->toArray(),
            'by_department' => DB::table('knowledge_base')
                ->where('is_active', true)
                ->groupBy('department')
                ->selectRaw('department, COUNT(*) as count')
                ->pluck('count', 'department')
                ->toArray(),
            'by_priority' => DB::table('knowledge_base')
                ->where('is_active', true)
                ->groupBy('priority')
                ->selectRaw('priority, COUNT(*) as count')
                ->pluck('count', 'priority')
                ->toArray()
        ];

        // Agregar estadísticas vectoriales si están disponibles
        if ($this->vectorService && $this->vectorService->isHealthy()) {
            try {
                $vectorStats = $this->vectorService->getCollectionStats();
                $basicStats['vector_index'] = [
                    'total_indexed' => $vectorStats['total_points'] ?? 0,
                    'indexing_status' => $vectorStats['collection_status'] ?? 'unknown',
                    'semantic_search_enabled' => true
                ];
            } catch (\Exception $e) {
                $basicStats['vector_index'] = [
                    'total_indexed' => 0,
                    'indexing_status' => 'error',
                    'semantic_search_enabled' => false
                ];
            }
        } else {
            $basicStats['vector_index'] = [
                'total_indexed' => 0,
                'indexing_status' => 'unavailable',
                'semantic_search_enabled' => false
            ];
        }

        return $basicStats;
    }

    /**
     * Verificar salud del servicio - MEJORADO
     */
    public function isHealthy(): bool
    {
        try {
            // Verificar acceso a la base de datos
            $count = DB::table('knowledge_base')->where('is_active', true)->count();

            // Verificar si hay contenido
            $hasContent = $count > 0;

            // Verificar servicio vectorial (opcional)
            $vectorHealthy = $this->vectorService ? $this->vectorService->isHealthy() : true;

            return $hasContent && $vectorHealthy;

        } catch (\Exception $e) {
            Log::error('Knowledge base health check failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Buscar contenido por consultas frecuentes
     */
    public function searchFrequentQuestions(string $query): Collection
    {
        $commonQuestions = [
            'inscripción' => 'tramites',
            'carrera' => 'oferta_educativa',
            'titulación' => 'tramites',
            'biblioteca' => 'servicios',
            'sistema' => 'servicios',
            'contacto' => 'informacion_general'
        ];

        foreach ($commonQuestions as $keyword => $category) {
            if (str_contains(strtolower($query), $keyword)) {
                return $this->getContentByCategory($category);
            }
        }

        return collect([]);
    }

    /**
     * Método auxiliar para verificar si la búsqueda semántica está disponible
     */
    public function isSemanticSearchAvailable(): bool
    {
        if (!$this->vectorService) {
            return false;
        }

        try {
            $stats = $this->vectorService->getCollectionStats();
            return ($stats['total_points'] ?? 0) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
}
