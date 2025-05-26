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
     * BÚSQUEDA RELEVANTE MEJORADA CON ESTRATEGIA HÍBRIDA
     */
    public function searchRelevantContent(string $query, string $userType = 'public', ?string $department = null): array
    {
        $cacheKey = $this->generateCacheKey($query, $userType, $department);

        // Verificar cache primero
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            // 1. BÚSQUEDA SEMÁNTICA (si Qdrant está disponible)
            $semanticResults = $this->performSemanticSearch($query, $userType, $department);

            // 2. BÚSQUEDA POR PALABRAS CLAVE (MySQL Full-Text)
            $keywordResults = $this->performKeywordSearch($query, $userType, $department);

            // 3. BÚSQUEDA POR CATEGORÍA/INTENCIÓN
            $categoryResults = $this->performCategorySearch($query, $userType, $department);

            // 4. COMBINAR Y RANKEAR RESULTADOS
            $combinedResults = $this->hybridRanking($semanticResults, $keywordResults, $categoryResults, $query);

            // 5. EXTRAER CONTENIDO FINAL
            $finalContent = $this->extractFinalContent($combinedResults);

            // Cachear resultado por 1 hora
            Cache::put($cacheKey, $finalContent, 3600);

            return $finalContent;

        } catch (\Exception $e) {
            Log::error('Knowledge search error: ' . $e->getMessage(), [
                'query' => $query,
                'user_type' => $userType,
                'department' => $department
            ]);

            // Fallback a búsqueda simple
            return $this->performSimpleFallbackSearch($query, $userType, $department);
        }
    }

    /**
     * BÚSQUEDA SEMÁNTICA USANDO VECTORES
     */
    private function performSemanticSearch(string $query, string $userType, ?string $department): Collection
    {
        if (!$this->vectorService || !$this->vectorService->isHealthy()) {
            return collect([]);
        }

        try {
            // Preparar filtros para búsqueda vectorial
            $filters = ['user_type' => $userType];
            if ($department) {
                $filters['department'] = $department;
            }

            // Realizar búsqueda semántica
            $results = $this->vectorService->searchSimilarContent($query, 5, $filters);

            return collect($results)->map(function ($item) {
                return [
                    'id' => $item['id'],
                    'content' => $this->getFullContentById($item['id']),
                    'title' => $item['title'] ?? '',
                    'score' => $item['score'],
                    'search_type' => 'semantic',
                    'category' => $item['category'] ?? '',
                    'department' => $item['department'] ?? ''
                ];
            })->filter(function($item) {
                return !empty($item['content']);
            });

        } catch (\Exception $e) {
            Log::warning('Semantic search failed: ' . $e->getMessage());
            return collect([]);
        }
    }

    /**
     * BÚSQUEDA POR PALABRAS CLAVE MEJORADA
     */
    private function performKeywordSearch(string $query, string $userType, ?string $department): Collection
    {
        $baseQuery = DB::table('knowledge_base')
            ->where('is_active', true)
            ->whereRaw('JSON_CONTAINS(user_types, ?)', [json_encode($userType)]);

        // Filtro por departamento
        if ($department) {
            $baseQuery->where(function($q) use ($department) {
                $q->where('department', $department)
                  ->orWhere('department', 'GENERAL');
            });
        }

        // Estrategia de búsqueda escalonada
        $results = collect([]);

        // 1. Búsqueda Full-Text (más precisa)
        try {
            $fullTextResults = (clone $baseQuery)
                ->whereRaw('MATCH(title, content) AGAINST(? IN BOOLEAN MODE)', [$this->prepareSearchTerm($query)])
                ->selectRaw('*, MATCH(title, content) AGAINST(? IN BOOLEAN MODE) as relevance_score', [$this->prepareSearchTerm($query)])
                ->orderBy('relevance_score', 'desc')
                ->orderBy('priority', 'desc')
                ->limit(3)
                ->get();

            $results = $results->concat($fullTextResults->map(function($item) {
                $item->search_type = 'fulltext';
                $item->score = $item->relevance_score ?? 0.5;
                return $item;
            }));

        } catch (\Exception $e) {
            Log::warning('Full-text search failed, falling back to LIKE: ' . $e->getMessage());
        }

        // 2. Búsqueda LIKE (si Full-Text no encuentra suficientes resultados)
        if ($results->count() < 2) {
            $likeResults = (clone $baseQuery)
                ->where(function($q) use ($query) {
                    $q->where('title', 'LIKE', "%{$query}%")
                      ->orWhere('content', 'LIKE', "%{$query}%")
                      ->orWhereRaw('JSON_SEARCH(keywords, "one", ?) IS NOT NULL', ["%{$query}%"]);
                })
                ->select(['id', 'title', 'content', 'category', 'department', 'contact_info', 'priority'])
                ->orderBy('priority', 'desc')
                ->orderByRaw('CASE WHEN title LIKE ? THEN 1 ELSE 2 END', ["%{$query}%"])
                ->limit(3)
                ->get();

            $results = $results->concat($likeResults->map(function($item) {
                $item->search_type = 'like';
                $item->score = $this->calculateKeywordScore($item);
                return $item;
            }));
        }

        return $results->unique('id');
    }

    /**
     * BÚSQUEDA POR CATEGORÍA/INTENCIÓN
     */
    private function performCategorySearch(string $query, string $userType, ?string $department): Collection
    {
        $detectedCategory = $this->detectQueryCategory($query);

        if (!$detectedCategory) {
            return collect([]);
        }

        return DB::table('knowledge_base')
            ->where('is_active', true)
            ->where('category', $detectedCategory)
            ->whereRaw('JSON_CONTAINS(user_types, ?)', [json_encode($userType)])
            ->when($department, function($q, $dept) {
                $q->where(function($subQ) use ($dept) {
                    $subQ->where('department', $dept)->orWhere('department', 'GENERAL');
                });
            })
            ->orderBy('priority', 'desc')
            ->limit(2)
            ->get()
            ->map(function($item) {
                $item->search_type = 'category';
                $item->score = 0.6; // Score moderado para resultados por categoría
                return $item;
            });
    }

    /**
     * DETECTAR CATEGORÍA DE LA CONSULTA
     */
    private function detectQueryCategory(string $query): ?string
    {
        $queryLower = strtolower($query);

        $categoryPatterns = [
            'tramites' => [
                'inscripción', 'inscripcion', 'admisión', 'admision', 'registro', 'matricula',
                'titulación', 'titulacion', 'grado', 'certificado', 'constancia', 'trámite',
                'proceso', 'requisito', 'documento', 'solicitud'
            ],
            'oferta_educativa' => [
                'carrera', 'licenciatura', 'programa', 'plan de estudios', 'académico',
                'maestría', 'doctorado', 'posgrado', 'especialidad', 'diplomado',
                'oferta educativa', 'pensum', 'materias', 'asignaturas'
            ],
            'servicios' => [
                'biblioteca', 'laboratorio', 'servicio', 'apoyo', 'beca', 'residencia',
                'comedor', 'transporte', 'médico', 'psicológico', 'deportivo',
                'cultural', 'wifi', 'internet', 'cafetería'
            ],
            'sistemas' => [
                'sistema', 'plataforma', 'correo', 'email', 'password', 'contraseña',
                'login', 'acceso', 'cuenta', 'usuario', 'tecnología', 'soporte técnico',
                'error', 'falla', 'problema técnico'
            ],
            'informacion_general' => [
                'universidad', 'historia', 'misión', 'visión', 'campus', 'ubicación',
                'dirección', 'contacto', 'teléfono', 'información general', 'acerca de'
            ]
        ];

        foreach ($categoryPatterns as $category => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($queryLower, $pattern) !== false) {
                    return $category;
                }
            }
        }

        return null;
    }

    /**
     * RANKING HÍBRIDO DE RESULTADOS
     */
    private function hybridRanking(Collection $semantic, Collection $keyword, Collection $category, string $query): Collection
    {
        $allResults = collect([]);

        // Agregar resultados semánticos con boost
        $semantic->each(function($item) use ($allResults) {
            $item->final_score = ($item->score * 0.4) + 0.3; // Boost semántico
            $allResults->push($item);
        });

        // Agregar resultados de keywords
        $keyword->each(function($item) use ($allResults) {
            $existing = $allResults->firstWhere('id', $item->id);
            if ($existing) {
                // Combinar scores si ya existe
                $existing->final_score = max($existing->final_score, $item->score * 0.35);
                $existing->search_type .= '+keyword';
            } else {
                $item->final_score = $item->score * 0.35;
                $allResults->push($item);
            }
        });

        // Agregar resultados de categoría
        $category->each(function($item) use ($allResults) {
            $existing = $allResults->firstWhere('id', $item->id);
            if ($existing) {
                $existing->final_score += 0.15; // Boost por relevancia categórica
                $existing->search_type .= '+category';
            } else {
                $item->final_score = 0.25;
                $allResults->push($item);
            }
        });

        // Aplicar boost por priority y relevancia del título
        $queryLower = strtolower($query);
        $allResults->each(function($item) use ($queryLower) {
            // Boost por prioridad
            $priorityBoost = $this->getPriorityBoost($item->priority ?? 'medium');

            // Boost por coincidencia en título
            $titleBoost = (strpos(strtolower($item->title ?? ''), $queryLower) !== false) ? 0.1 : 0;

            $item->final_score += $priorityBoost + $titleBoost;
        });

        return $allResults->sortByDesc('final_score')->unique('id');
    }

    /**
     * OBTENER BOOST POR PRIORIDAD
     */
    private function getPriorityBoost(string $priority): float
    {
        switch ($priority) {
            case 'high':
                return 0.15;
            case 'medium':
                return 0.05;
            case 'low':
                return 0;
            default:
                return 0.05;
        }
    }

    /**
     * EXTRAER CONTENIDO FINAL OPTIMIZADO
     */
    private function extractFinalContent(Collection $rankedResults): array
    {
        return $rankedResults
            ->take(3)
            ->map(function($item) {
                // Optimizar longitud del contenido
                $content = $item->content ?? '';
                if (strlen($content) > 800) {
                    $content = substr($content, 0, 800) . '...';
                }
                return $content;
            })
            ->filter(function($content) {
                return !empty(trim($content));
            })
            ->values()
            ->toArray();
    }

    /**
     * BÚSQUEDA SIMPLE PARA FALLBACK
     */
    private function performSimpleFallbackSearch(string $query, string $userType, ?string $department): array
    {
        try {
            $results = DB::table('knowledge_base')
                ->where('is_active', true)
                ->whereRaw('JSON_CONTAINS(user_types, ?)', [json_encode($userType)])
                ->where(function($q) use ($query) {
                    $q->where('title', 'LIKE', "%{$query}%")
                      ->orWhere('content', 'LIKE', "%{$query}%");
                })
                ->orderBy('priority', 'desc')
                ->limit(2)
                ->get(['content']);

            return $results->pluck('content')->toArray();
        } catch (\Exception $e) {
            Log::error('Fallback search failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * OBTENER CONTENIDO COMPLETO POR ID
     */
    private function getFullContentById(int $id): string
    {
        try {
            $item = DB::table('knowledge_base')
                ->where('id', $id)
                ->where('is_active', true)
                ->first(['content']);

            return $item->content ?? '';
        } catch (\Exception $e) {
            Log::warning("Failed to get content for ID {$id}: " . $e->getMessage());
            return '';
        }
    }

    /**
     * GENERAR CLAVE DE CACHE
     */
    private function generateCacheKey(string $query, string $userType, ?string $department): string
    {
        $key = 'knowledge_search_' . md5($query . $userType . ($department ?? ''));
        return $key;
    }

    /**
     * CALCULAR SCORE DE PALABRA CLAVE
     */
    private function calculateKeywordScore($item): float
    {
        $score = 0.4; // Base score

        // Bonus por prioridad usando método auxiliar
        $score += $this->getPriorityScoreBonus($item->priority ?? 'medium');

        // Bonus por categoría específica
        if (in_array($item->category ?? '', ['tramites', 'servicios', 'oferta_educativa'])) {
            $score += 0.2;
        }

        return min(1.0, $score);
    }

    /**
     * OBTENER BONUS DE SCORE POR PRIORIDAD
     */
    private function getPriorityScoreBonus(string $priority): float
    {
        switch ($priority) {
            case 'high':
                return 0.3;
            case 'medium':
                return 0.2;
            case 'low':
                return 0.1;
            default:
                return 0.2;
        }
    }

    /**
     * PREPARAR TÉRMINO DE BÚSQUEDA PARA MYSQL FULL-TEXT
     */
    private function prepareSearchTerm(string $query): string
    {
        // Limpiar y preparar el término
        $terms = preg_split('/\s+/', trim($query));
        $terms = array_filter($terms, function($term) {
            return strlen($term) > 2;
        });

        if (empty($terms)) {
            return $query;
        }

        // Convertir a formato de búsqueda booleana
        $searchTerms = array_map(function($term) {
            return "+{$term}*";
        }, $terms);

        return implode(' ', $searchTerms);
    }

    /**
     * BÚSQUEDA POR PREGUNTAS FRECUENTES MEJORADA
     */
    public function searchFrequentQuestions(string $query): Collection
    {
        $commonQuestions = [
            'inscripción' => ['tramites', 'admisión', 'registro'],
            'carrera' => ['oferta_educativa', 'programa', 'licenciatura'],
            'titulación' => ['tramites', 'grado', 'egreso'],
            'biblioteca' => ['servicios', 'libros', 'consulta'],
            'sistema' => ['servicios', 'tecnología', 'soporte'],
            'contacto' => ['informacion_general', 'teléfono', 'dirección'],
            'beca' => ['servicios', 'apoyo', 'financiero'],
            'examen' => ['tramites', 'evaluación', 'admisión']
        ];

        $queryLower = strtolower($query);
        $matchedCategories = [];

        foreach ($commonQuestions as $keyword => $categories) {
            if (strpos($queryLower, $keyword) !== false) {
                $matchedCategories = array_merge($matchedCategories, $categories);
            }
        }

        if (empty($matchedCategories)) {
            return collect([]);
        }

        return DB::table('knowledge_base')
            ->where('is_active', true)
            ->whereIn('category', array_unique($matchedCategories))
            ->orderBy('priority', 'desc')
            ->limit(2)
            ->get();
    }

    /**
     * OBTENER ESTADÍSTICAS MEJORADAS
     */
    public function getStats(): array
    {
        try {
            $baseStats = [
                'total_entries' => DB::table('knowledge_base')->where('is_active', true)->count(),
                'total_inactive' => DB::table('knowledge_base')->where('is_active', false)->count(),
                'last_updated' => DB::table('knowledge_base')->max('updated_at')
            ];

            $categoryStats = DB::table('knowledge_base')
                ->where('is_active', true)
                ->groupBy('category')
                ->selectRaw('category, COUNT(*) as count, MAX(updated_at) as last_update')
                ->get()
                ->mapWithKeys(function($item) {
                    return [$item->category => [
                        'count' => $item->count,
                        'last_update' => $item->last_update
                    ]];
                })
                ->toArray();

            $departmentStats = DB::table('knowledge_base')
                ->where('is_active', true)
                ->groupBy('department')
                ->selectRaw('department, COUNT(*) as count')
                ->get()
                ->mapWithKeys(function($item) {
                    return [$item->department => $item->count];
                })
                ->toArray();

            $priorityStats = DB::table('knowledge_base')
                ->where('is_active', true)
                ->groupBy('priority')
                ->selectRaw('priority, COUNT(*) as count')
                ->get()
                ->mapWithKeys(function($item) {
                    return [$item->priority => $item->count];
                })
                ->toArray();

            // Estadísticas de vectores si está disponible
            $vectorStats = [];
            if ($this->vectorService && $this->vectorService->isHealthy()) {
                $vectorStats = $this->vectorService->getCollectionStats();
            }

            return array_merge($baseStats, [
                'by_category' => $categoryStats,
                'by_department' => $departmentStats,
                'by_priority' => $priorityStats,
                'vector_stats' => $vectorStats
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting knowledge base stats: ' . $e->getMessage());
            return ['error' => 'No se pudieron obtener las estadísticas'];
        }
    }

    /**
     * VERIFICAR SALUD DEL SERVICIO
     */
    public function isHealthy(): bool
    {
        try {
            $count = DB::table('knowledge_base')->where('is_active', true)->count();
            return $count > 0;
        } catch (\Exception $e) {
            Log::error('Knowledge base health check failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * MÉTODOS EXISTENTES MANTENIDOS PARA COMPATIBILIDAD
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

    public function addContent(array $data): bool
    {
        try {
            $data['created_at'] = now();
            $data['updated_at'] = now();

            if (isset($data['user_types']) && !is_string($data['user_types'])) {
                $data['user_types'] = json_encode($data['user_types']);
            }

            if (isset($data['keywords']) && !is_string($data['keywords'])) {
                $data['keywords'] = json_encode($data['keywords']);
            }

            $id = DB::table('knowledge_base')->insertGetId($data);

            // Invalidar cache relacionado
            $this->invalidateRelatedCache($data['category'] ?? '', $data['department'] ?? '');

            // Indexar en vectores si está disponible
            if ($this->vectorService && $this->vectorService->isHealthy()) {
                $this->indexNewContent($id);
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Error adding knowledge base content: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * INVALIDAR CACHE RELACIONADO
     */
    private function invalidateRelatedCache(string $category, string $department): void
    {
        // Por simplicidad, limpiar todo el cache de búsquedas
        // En producción, implementar limpieza selectiva por patrón
        Cache::flush();
    }

    /**
     * INDEXAR NUEVO CONTENIDO EN VECTORES
     */
    private function indexNewContent(int $id): void
    {
        try {
            $content = DB::table('knowledge_base')->find($id);
            if ($content && $content->is_active) {
                // Generar embedding y indexar
                $combinedText = $content->title . "\n\n" . $content->content;
                $embedding = $this->ollamaService->generateEmbedding($combinedText);

                if (!empty($embedding)) {
                    // Código para indexar en Qdrant
                    // Implementar según necesidades específicas
                }
            }
        } catch (\Exception $e) {
            Log::warning("Failed to index new content {$id}: " . $e->getMessage());
        }
    }
}
