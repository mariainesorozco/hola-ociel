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

    public function __construct(OllamaService $ollamaService, QdrantVectorService $vectorService = null)
    {
        $this->ollamaService = $ollamaService;
        $this->vectorService = $vectorService;
    }

    /**
     * Buscar contenido relevante en la base de conocimientos
     */
    public function searchRelevantContent(string $query, string $userType = 'public', ?string $department = null): array
    {
        // 1. Búsqueda por palabras clave (rápida)
        $keywordResults = $this->keywordSearch($query, $userType, $department);

        // 2. Búsqueda semántica usando embeddings (más precisa)
        $semanticResults = $this->semanticSearch($query, $userType, $department);

        // 3. Combinar y rankear resultados
        $combinedResults = $this->combineAndRankResults($keywordResults, $semanticResults);

        // 4. Extraer solo el contenido textual para el contexto
        return $combinedResults->take(3)->pluck('content')->toArray();
    }

    /**
     * Búsqueda por palabras clave usando MySQL Full-Text Search
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
     * Búsqueda semántica usando embeddings
     */
    private function semanticSearch(string $query, string $userType, ?string $department): Collection
    {
        // Por ahora, retorna vacío - implementaremos cuando tengamos Qdrant configurado
        // TODO: Implementar búsqueda vectorial con Qdrant

        return collect([]);
    }

    /**
     * Combinar y rankear resultados de ambos tipos de búsqueda
     */
    private function combineAndRankResults(Collection $keywordResults, Collection $semanticResults): Collection
    {
        // Combinar resultados eliminando duplicados
        $combined = $keywordResults->concat($semanticResults)
            ->unique('id')
            ->sortByDesc('relevance_score');

        return $combined;
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
     * Obtener estadísticas de la base de conocimientos
     */
    public function getStats(): array
    {
        return [
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
    }

    /**
     * Verificar salud del servicio
     */
    public function isHealthy(): bool
    {
        try {
            $count = DB::table('knowledge_base')->where('is_active', true)->count();
            return $count > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Agregar nuevo contenido a la base de conocimientos
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

            DB::table('knowledge_base')->insert($data);
            return true;

        } catch (\Exception $e) {
            \Log::error('Error adding knowledge base content: ' . $e->getMessage());
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
}
