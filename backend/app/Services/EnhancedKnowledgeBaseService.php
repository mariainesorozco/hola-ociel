<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class EnhancedKnowledgeBaseService extends KnowledgeBaseService
{
    private $qdrantService;
    private $piidaCategories;

    public function __construct(
        OllamaService $ollamaService,
        EnhancedQdrantVectorService $qdrantService = null
    ) {
        parent::__construct($ollamaService, $qdrantService);
        $this->qdrantService = $qdrantService;
        $this->piidaCategories = config('services.piida.categories', []);
    }

    /**
     * Búsqueda vectorial única para PIIDA usando exclusivamente Qdrant
     */
    public function searchPiidaContent(
        string $query,
        string $userType = 'public',
        ?string $department = null,
        array $options = []
    ): array {
        $searchOptions = array_merge([
            'max_results' => 5,
            'score_threshold' => 0.6,
            'boost_recent' => true,
            'boost_priority' => true
        ], $options);

        try {
            // Verificar que Qdrant esté disponible
            if (!$this->qdrantService || !$this->qdrantService->isHealthy()) {
                Log::error('Qdrant service not available for vector search');
                throw new \Exception('Vector search service not available');
            }

            // Usar el método de búsqueda vectorial del servicio padre
            $semanticResults = parent::searchRelevantContent($query, $userType, $department);

            // Procesar y rankear resultados
            $finalResults = $this->processVectorResults($semanticResults, $query, $searchOptions);

            // Aplicar filtros específicos del usuario
            $filteredResults = $this->applyUserTypeFilters($finalResults, $userType, $department);

            Log::info('Búsqueda vectorial PIIDA completada', [
                'query' => $query,
                'user_type' => $userType,
                'results_count' => count($filteredResults),
                'search_method' => 'vector_only'
            ]);

            return array_slice($filteredResults, 0, $searchOptions['max_results']);

        } catch (\Exception $e) {
            Log::error('Error en búsqueda vectorial PIIDA: ' . $e->getMessage(), [
                'query' => $query,
                'user_type' => $userType,
                'department' => $department
            ]);

            // Sin fallback a MariaDB - forzar error si Qdrant no funciona
            return [];
        }
    }

    /**
     * Búsqueda semántica usando Qdrant
     */
    private function executeSemanticSearch(
        string $query,
        string $userType,
        ?string $department,
        array $options
    ): array {
        try {
            $filters = [
                'user_type' => $userType,
                'department' => $department
            ];

            return $this->qdrantService->searchPiidaContent(
                $query,
                array_filter($filters),
                $options['max_results'] * 2, // Obtener más para combinar después
                $options['score_threshold']
            );

        } catch (\Exception $e) {
            Log::warning('Error en búsqueda semántica: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Procesar resultados exclusivamente vectoriales
     */
    private function processVectorResults(array $semanticResults, string $query, array $options): array
    {
        // Los resultados vienen como array de strings de contenido
        // Convertirlos al formato esperado por el resto del sistema
        return collect($semanticResults)->map(function($content, $index) use ($query, $options) {
            return [
                'id' => $index + 1, // ID temporal
                'title' => 'Resultado ' . ($index + 1),
                'content_preview' => substr($content, 0, 500),
                'content' => $content,
                'category' => 'vectorial',
                'department' => 'GENERAL',
                'contact_info' => '',
                'source_url' => '',
                'priority' => 'high',
                'score' => 0.8, // Score base para resultados vectoriales
                'search_type' => 'vector_only',
                'final_score' => $this->calculateVectorScore($content, $query, $options)
            ];
        })->sortByDesc('final_score')->values()->toArray();
    }

    /**
     * Calcular score para resultados vectoriales
     */
    private function calculateVectorScore(string $content, string $query, array $options): float
    {
        $baseScore = 0.8; // Score alto para resultados vectoriales

        // Boost por coincidencia de palabras clave en el contenido
        $queryWords = explode(' ', strtolower($query));
        $contentLower = strtolower($content);
        
        $matches = 0;
        foreach ($queryWords as $word) {
            if (strlen($word) > 2 && str_contains($contentLower, $word)) {
                $matches++;
            }
        }
        
        $matchRatio = count($queryWords) > 0 ? $matches / count($queryWords) : 0;
        $baseScore += $matchRatio * 0.2;

        return min(1.0, max(0.0, $baseScore));
    }

    /**
     * Búsqueda basada en patrones específicos de PIIDA
     */
    private function executePatternBasedSearch(string $query, string $userType, ?string $department): array
    {
        $patterns = $this->identifyQueryPatterns($query);
        $results = [];

        foreach ($patterns as $pattern) {
            $patternResults = $this->searchByPattern($pattern, $userType, $department);
            $results = array_merge($results, $patternResults);
        }

        return $results;
    }

    /**
     * Analizar términos de búsqueda y extraer intención
     */
    private function analyzeSearchTerms(string $query): array
    {
        $normalizedQuery = $this->normalizeQuery($query);

        return [
            'original' => $query,
            'normalized' => $normalizedQuery,
            'terms' => $this->extractSearchTerms($normalizedQuery),
            'intent' => $this->detectSearchIntent($normalizedQuery),
            'category_hints' => $this->detectCategoryHints($normalizedQuery),
            'entity_types' => $this->detectEntityTypes($normalizedQuery)
        ];
    }

    /**
     * Normalizar consulta de búsqueda
     */
    private function normalizeQuery(string $query): string
    {
        // Convertir a minúsculas
        $normalized = strtolower(trim($query));

        // Remover caracteres especiales excepto espacios, acentos y números
        $normalized = preg_replace('/[^\w\sáéíóúüñ]/u', ' ', $normalized);

        // Normalizar espacios múltiples
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        // Expandir abreviaciones comunes
        $abbreviations = [
            'prof' => 'profesor',
            'dr' => 'doctor',
            'mtra' => 'maestra',
            'mtro' => 'maestro',
            'ing' => 'ingeniero',
            'lic' => 'licenciatura',
            'univ' => 'universidad',
            'inst' => 'instituto',
            'dpto' => 'departamento',
            'tel' => 'teléfono',
            'ext' => 'extensión'
        ];

        foreach ($abbreviations as $abbr => $full) {
            $normalized = preg_replace('/\b' . $abbr . '\b/', $full, $normalized);
        }

        return trim($normalized);
    }

    /**
     * Extraer términos de búsqueda relevantes
     */
    private function extractSearchTerms(string $normalizedQuery): array
    {
        $words = explode(' ', $normalizedQuery);

        $stopWords = [
            'el', 'la', 'de', 'que', 'y', 'a', 'en', 'un', 'es', 'se', 'no', 'te', 'lo', 'le', 'da', 'su', 'por', 'son', 'con', 'para', 'al', 'una', 'del', 'los', 'las', 'como', 'pero', 'sus', 'está', 'me', 'mi', 'sin', 'sobre', 'ser', 'tan', 'todo', 'fue', 'yo', 'mi', 'si', 'muy', 'más', 'ya', 'ese'
        ];

        $terms = array_filter($words, function($word) use ($stopWords) {
            return strlen($word) > 2 && !in_array($word, $stopWords);
        });

        return array_values($terms);
    }

    /**
     * Detectar intención de búsqueda
     */
    private function detectSearchIntent(string $query): string
    {
        $intentPatterns = [
            'procedural' => ['cómo', 'como', 'proceso', 'pasos', 'procedimiento', 'tramitar', 'solicitar'],
            'informational' => ['qué', 'que', 'cuál', 'cual', 'dónde', 'donde', 'información', 'datos'],
            'navigational' => ['contacto', 'teléfono', 'ubicación', 'dirección', 'horario', 'departamento'],
            'transactional' => ['inscripción', 'registro', 'solicitud', 'aplicar', 'obtener', 'descargar']
        ];

        foreach ($intentPatterns as $intent => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains($query, $pattern)) {
                    return $intent;
                }
            }
        }

        return 'general';
    }

    /**
     * Detectar pistas de categoría en la consulta
     */
    private function detectCategoryHints(string $query): array
    {
        $categoryKeywords = [
            'tramites_estudiantes' => ['inscripción', 'matrícula', 'beca', 'titulación', 'servicio social', 'egreso'],
            'tramites_docentes' => ['nombramiento', 'evaluación docente', 'academia', 'investigación', 'publicación'],
            'servicios_academicos' => ['biblioteca', 'laboratorio', 'aula', 'plataforma', 'sistema', 'correo'],
            'directorio' => ['contacto', 'teléfono', 'ubicación', 'departamento', 'oficina', 'extensión'],
            'normatividad' => ['reglamento', 'ley', 'norma', 'lineamiento', 'política', 'acuerdo'],
            'eventos' => ['convocatoria', 'evento', 'curso', 'taller', 'conferencia', 'actividad']
        ];

        $hints = [];
        foreach ($categoryKeywords as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($query, $keyword)) {
                    $hints[] = $category;
                    break;
                }
            }
        }

        return array_unique($hints);
    }

    /**
     * Detectar tipos de entidades en la consulta
     */
    private function detectEntityTypes(string $query): array
    {
        $entities = [];

        // Detectar nombres de carreras/programas
        $programPatterns = [
            'licenciatura', 'ingeniería', 'maestría', 'doctorado', 'posgrado',
            'medicina', 'derecho', 'psicología', 'administración', 'contaduría'
        ];

        foreach ($programPatterns as $pattern) {
            if (str_contains($query, $pattern)) {
                $entities[] = 'academic_program';
                break;
            }
        }

        // Detectar dependencias
        $deptPatterns = ['sa', 'secretaría', 'dirección', 'coordinación', 'oficina'];
        foreach ($deptPatterns as $pattern) {
            if (str_contains($query, $pattern)) {
                $entities[] = 'department';
                break;
            }
        }

        // Detectar números de teléfono
        if (preg_match('/\d{3}[-\s]?\d{3}[-\s]?\d{4}/', $query)) {
            $entities[] = 'phone';
        }

        return $entities;
    }

    /**
     * Construir consulta de texto inteligente para MySQL
     */
    private function buildIntelligentTextQuery(array $searchTerms): array
    {
        $terms = $searchTerms['terms'];
        $originalQuery = $searchTerms['original'];

        if (empty($terms)) {
            return [
                'where' => 'title LIKE ? OR content LIKE ?',
                'bindings' => ["%{$originalQuery}%", "%{$originalQuery}%"],
                'score_expression' => '1',
                'order_by' => 'priority DESC, updated_at DESC'
            ];
        }

        // Construir búsqueda full-text si está disponible
        $fullTextTerms = array_map(function($term) {
            return "+{$term}*";
        }, $terms);

        $fullTextQuery = implode(' ', $fullTextTerms);

        return [
            'where' => 'MATCH(title, content) AGAINST(? IN BOOLEAN MODE)',
            'bindings' => [$fullTextQuery],
            'score_expression' => 'MATCH(title, content) AGAINST(? IN BOOLEAN MODE)',
            'order_by' => 'MATCH(title, content) AGAINST(? IN BOOLEAN MODE) DESC, priority DESC'
        ];
    }

    /**
     * Buscar usando LIKE como fallback
     */
    private function executeLikeSearch($baseQuery, array $searchTerms, int $limit): array
    {
        $terms = $searchTerms['terms'];

        $query = clone $baseQuery;

        $query->where(function($q) use ($terms) {
            foreach ($terms as $term) {
                $q->orWhere('title', 'LIKE', "%{$term}%")
                  ->orWhere('content', 'LIKE', "%{$term}%")
                  ->orWhereRaw('JSON_SEARCH(keywords, "one", ?) IS NOT NULL', ["%{$term}%"]);
            }
        });

        $results = $query->select([
                'id', 'title', 'content', 'category', 'department',
                'contact_info', 'priority', 'keywords', 'source_url', 'updated_at'
            ])
            ->selectRaw('1 as relevance_score')
            ->orderBy('priority', 'desc')
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get();

        return $this->formatKeywordResults($results, 'keyword_fallback');
    }

    /**
     * Identificar patrones específicos en la consulta
     */
    private function identifyQueryPatterns(string $query): array
    {
        $patterns = [];
        $normalizedQuery = strtolower($query);

        // Patrón de consulta de contacto
        if (preg_match('/(?:teléfono|tel|contacto|ubicación|dirección).*(?:de|del|para)\s+(.+)/', $normalizedQuery, $matches)) {
            $patterns[] = [
                'type' => 'contact_inquiry',
                'entity' => trim($matches[1]),
                'priority' => 'high'
            ];
        }

        // Patrón de consulta de trámite
        if (preg_match('/(?:cómo|como)\s+(?:tramitar|solicitar|obtener)\s+(.+)/', $normalizedQuery, $matches)) {
            $patterns[] = [
                'type' => 'procedure_inquiry',
                'entity' => trim($matches[1]),
                'priority' => 'high'
            ];
        }

        // Patrón de consulta de requisitos
        if (preg_match('/(?:requisitos?|documentos?)\s+(?:para|de)\s+(.+)/', $normalizedQuery, $matches)) {
            $patterns[] = [
                'type' => 'requirements_inquiry',
                'entity' => trim($matches[1]),
                'priority' => 'high'
            ];
        }

        // Patrón de consulta de horarios
        if (preg_match('/(?:horario|hora|cuando)\s+(?:de|del|para)\s+(.+)/', $normalizedQuery, $matches)) {
            $patterns[] = [
                'type' => 'schedule_inquiry',
                'entity' => trim($matches[1]),
                'priority' => 'medium'
            ];
        }

        return $patterns;
    }

    /**
     * Buscar por patrón específico
     */
    private function searchByPattern(array $pattern, string $userType, ?string $department): array
    {
        $baseQuery = DB::table('knowledge_base')
            ->where('is_active', true)
            ->whereRaw('JSON_CONTAINS(user_types, ?)', [json_encode($userType)])
            ->whereIn('category', array_keys($this->piidaCategories));

        switch ($pattern['type']) {
            case 'contact_inquiry':
                return $this->searchContactPattern($baseQuery, $pattern['entity']);

            case 'procedure_inquiry':
                return $this->searchProcedurePattern($baseQuery, $pattern['entity']);

            case 'requirements_inquiry':
                return $this->searchRequirementsPattern($baseQuery, $pattern['entity']);

            case 'schedule_inquiry':
                return $this->searchSchedulePattern($baseQuery, $pattern['entity']);

            default:
                return [];
        }
    }

    /**
     * Buscar información de contacto
     */
    private function searchContactPattern($baseQuery, string $entity): array
    {
        $results = $baseQuery
            ->where(function($q) use ($entity) {
                $q->where('title', 'LIKE', "%{$entity}%")
                  ->orWhere('department', 'LIKE', "%{$entity}%");
            })
            ->whereNotNull('contact_info')
            ->where('contact_info', '!=', '')
            ->select(['id', 'title', 'content', 'category', 'department', 'contact_info'])
            ->orderBy('priority', 'desc')
            ->limit(3)
            ->get();

        return $this->formatKeywordResults($results, 'contact_pattern');
    }

    /**
     * Buscar procedimientos/trámites
     */
    private function searchProcedurePattern($baseQuery, string $entity): array
    {
        $results = $baseQuery
            ->where(function($q) use ($entity) {
                $q->where('title', 'LIKE', "%{$entity}%")
                  ->orWhere('content', 'LIKE', "%{$entity}%");
            })
            ->whereIn('category', ['tramites_estudiantes', 'tramites_docentes', 'servicios_academicos'])
            ->select(['id', 'title', 'content', 'category', 'department', 'contact_info'])
            ->orderBy('priority', 'desc')
            ->limit(3)
            ->get();

        return $this->formatKeywordResults($results, 'procedure_pattern');
    }

    /**
     * Buscar requisitos
     */
    private function searchRequirementsPattern($baseQuery, string $entity): array
    {
        $results = $baseQuery
            ->where(function($q) use ($entity) {
                $q->where('title', 'LIKE', "%{$entity}%")
                  ->orWhere('content', 'LIKE', "%requisito%")
                  ->orWhere('content', 'LIKE', "%documento%");
            })
            ->select(['id', 'title', 'content', 'category', 'department', 'contact_info'])
            ->orderBy('priority', 'desc')
            ->limit(3)
            ->get();

        return $this->formatKeywordResults($results, 'requirements_pattern');
    }

    /**
     * Buscar horarios
     */
    private function searchSchedulePattern($baseQuery, string $entity): array
    {
        $results = $baseQuery
            ->where(function($q) use ($entity) {
                $q->where('title', 'LIKE', "%{$entity}%")
                  ->orWhere('content', 'LIKE', "%horario%")
                  ->orWhere('content', 'LIKE', "%hora%");
            })
            ->select(['id', 'title', 'content', 'category', 'department', 'contact_info'])
            ->orderBy('priority', 'desc')
            ->limit(3)
            ->get();

        return $this->formatKeywordResults($results, 'schedule_pattern');
    }

    /**
     * Formatear resultados de búsqueda por palabras clave
     */
    private function formatKeywordResults($results, string $searchType): array
    {
        return $results->map(function($item) use ($searchType) {
            return [
                'id' => $item->id,
                'title' => $item->title,
                'content_preview' => substr($item->content, 0, 500),
                'category' => $item->category,
                'category_name' => $this->piidaCategories[$item->category] ?? $item->category,
                'department' => $item->department,
                'contact_info' => $item->contact_info ?? '',
                'source_url' => $item->source_url ?? '',
                'priority' => $item->priority,
                'score' => $item->relevance_score ?? 0.8,
                'search_type' => $searchType,
                'updated_at' => $item->updated_at
            ];
        })->toArray();
    }

    /**
     * Procesar y rankear resultados combinados
     */
    private function processAndRankResults(array $results, string $query, array $options): array
    {
        // Eliminar duplicados por ID
        $uniqueResults = collect($results)->unique('id')->values();

        // Aplicar scoring avanzado
        $scoredResults = $uniqueResults->map(function($result) use ($query, $options) {
            $result['final_score'] = $this->calculateAdvancedScore($result, $query, $options);
            return $result;
        });

        // Ordenar por score final
        return $scoredResults->sortByDesc('final_score')->values()->toArray();
    }

    /**
     * Calcular score avanzado para ranking
     */
    private function calculateAdvancedScore(array $result, string $query, array $options): float
    {
        $baseScore = $result['score'] ?? 0.5;

        // Boost por prioridad
        if ($options['boost_priority']) {
            $priorityBoost = [
                'high' => 0.3,
                'medium' => 0.1,
                'low' => 0.0
            ];
            $baseScore += $priorityBoost[$result['priority']] ?? 0.0;
        }

        // Boost por actualización reciente
        if ($options['boost_recent'] && isset($result['updated_at'])) {
            $updatedAt = strtotime($result['updated_at']);
            $daysSinceUpdate = (time() - $updatedAt) / (24 * 3600);

            if ($daysSinceUpdate < 30) {
                $baseScore += 0.1 * (1 - $daysSinceUpdate / 30);
            }
        }

        // Boost por tipo de búsqueda
        $searchTypeBoost = [
            'semantic' => 0.2,
            'contact_pattern' => 0.15,
            'procedure_pattern' => 0.15,
            'requirements_pattern' => 0.1,
            'keyword' => 0.05,
            'keyword_fallback' => 0.0
        ];
        $baseScore += $searchTypeBoost[$result['search_type']] ?? 0.0;

        // Boost por coincidencia exacta en título
        if (str_contains(strtolower($result['title']), strtolower($query))) {
            $baseScore += 0.2;
        }

        // Penalización por contenido muy corto
        $contentLength = strlen($result['content_preview'] ?? '');
        if ($contentLength < 100) {
            $baseScore -= 0.1;
        }

        return min(1.0, max(0.0, $baseScore));
    }

    /**
     * Aplicar filtros específicos del tipo de usuario
     */
    private function applyUserTypeFilters(array $results, string $userType, ?string $department): array
    {
        return collect($results)->filter(function($result) use ($userType, $department) {
            // Verificar que el contenido es apropiado para el tipo de usuario
            $userTypeMapping = config('services.piida.user_types_mapping', []);
            $allowedCategories = $userTypeMapping[$userType] ?? array_keys($this->piidaCategories);

            if (!in_array($result['category'], $allowedCategories)) {
                return false;
            }

            // Si se especifica departamento, priorizar contenido relevante
            if ($department && $result['department'] !== 'GENERAL' && $result['department'] !== $department) {
                // Reducir score pero no eliminar completamente
                $result['final_score'] *= 0.7;
            }

            return true;
        })->values()->toArray();
    }

    /**
     * Búsqueda básica como fallback en caso de error
     */
    private function executeBasicFallbackSearch(string $query, string $userType, ?string $department): array
    {
        try {
            $results = DB::table('knowledge_base')
                ->where('is_active', true)
                ->whereRaw('JSON_CONTAINS(user_types, ?)', [json_encode($userType)])
                ->where(function($q) use ($query) {
                    $q->where('title', 'LIKE', "%{$query}%")
                      ->orWhere('content', 'LIKE', "%{$query}%");
                })
                ->select(['id', 'title', 'content', 'category', 'department', 'contact_info'])
                ->orderBy('priority', 'desc')
                ->limit(3)
                ->get();

            return $this->formatKeywordResults($results, 'basic_fallback');

        } catch (\Exception $e) {
            Log::error('Error en búsqueda fallback: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener contenido relacionado inteligente para PIIDA
     */
    public function getPiidaRelatedContent(int $contentId, string $userType = 'public', int $limit = 3): array
    {
        try {
            // Obtener contenido original
            $originalContent = DB::table('knowledge_base')->find($contentId);

            if (!$originalContent) {
                return [];
            }

            $relatedResults = [];

            // 1. Búsqueda semántica de contenido relacionado
            if ($this->qdrantService && $this->qdrantService->isHealthy()) {
                $semanticRelated = $this->qdrantService->getPiidaSuggestions($contentId, $limit);
                $relatedResults = array_merge($relatedResults, $semanticRelated);
            }

            // 2. Búsqueda por categoría y palabras clave
            $keywordRelated = $this->findRelatedByKeywords($originalContent, $userType, $limit);
            $relatedResults = array_merge($relatedResults, $keywordRelated);

            // 3. Contenido del mismo departamento
            $departmentRelated = $this->findRelatedByDepartment($originalContent, $userType, $limit);
            $relatedResults = array_merge($relatedResults, $departmentRelated);

            // Procesar y deduplicar
            $uniqueRelated = collect($relatedResults)
                ->unique('id')
                ->filter(fn($item) => $item['id'] != $contentId)
                ->take($limit)
                ->values()
                ->toArray();

            return $uniqueRelated;

        } catch (\Exception $e) {
            Log::error('Error obteniendo contenido relacionado PIIDA: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Buscar contenido relacionado por palabras clave
     */
    private function findRelatedByKeywords($originalContent, string $userType, int $limit): array
    {
        $keywords = json_decode($originalContent->keywords, true) ?? [];

        if (empty($keywords)) {
            return [];
        }

        $results = DB::table('knowledge_base')
            ->where('is_active', true)
            ->where('id', '!=', $originalContent->id)
            ->whereRaw('JSON_CONTAINS(user_types, ?)', [json_encode($userType)])
            ->where(function($q) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $q->orWhere('title', 'LIKE', "%{$keyword}%")
                      ->orWhere('content', 'LIKE', "%{$keyword}%")
                      ->orWhereRaw('JSON_SEARCH(keywords, "one", ?) IS NOT NULL', ["%{$keyword}%"]);
                }
            })
            ->select(['id', 'title', 'content', 'category', 'department', 'contact_info'])
            ->orderBy('priority', 'desc')
            ->limit($limit)
            ->get();

        return $this->formatKeywordResults($results, 'related_keywords');
    }

    /**
     * Buscar contenido relacionado por departamento
     */
    private function findRelatedByDepartment($originalContent, string $userType, int $limit): array
    {
        if ($originalContent->department === 'GENERAL') {
            return [];
        }

        $results = DB::table('knowledge_base')
            ->where('is_active', true)
            ->where('id', '!=', $originalContent->id)
            ->where('department', $originalContent->department)
            ->whereRaw('JSON_CONTAINS(user_types, ?)', [json_encode($userType)])
            ->select(['id', 'title', 'content', 'category', 'department', 'contact_info'])
            ->orderBy('priority', 'desc')
            ->limit($limit)
            ->get();

        return $this->formatKeywordResults($results, 'related_department');
    }

    /**
     * Obtener estadísticas específicas de PIIDA
     */
    public function getPiidaStats(): array
    {
        $baseStats = parent::getStats();

        // Agregar estadísticas específicas de PIIDA
        $piidaStats = [
            'piida_categories' => DB::table('knowledge_base')
                ->where('is_active', true)
                ->whereIn('category', array_keys($this->piidaCategories))
                ->groupBy('category')
                ->selectRaw('category, COUNT(*) as count')
                ->pluck('count', 'category')
                ->toArray(),
            'recent_piida_updates' => DB::table('knowledge_base')
                ->where('is_active', true)
                ->where('created_by', 'piida_scraper')
                ->where('updated_at', '>', now()->subDays(7))
                ->count(),
            'content_with_contact' => DB::table('knowledge_base')
                ->where('is_active', true)
                ->whereIn('category', array_keys($this->piidaCategories))
                ->whereNotNull('contact_info')
                ->where('contact_info', '!=', '')
                ->count()
        ];

        return array_merge($baseStats, $piidaStats);
    }

    /**
     * Verificar salud específica del sistema PIIDA
     */
    public function isPiidaHealthy(): bool
    {
        try {
            // Verificar contenido PIIDA básico
            $piidaContent = DB::table('knowledge_base')
                ->where('is_active', true)
                ->whereIn('category', array_keys($this->piidaCategories))
                ->count();

            if ($piidaContent === 0) {
                return false;
            }

            // Verificar que hay contenido reciente
            $recentContent = DB::table('knowledge_base')
                ->where('is_active', true)
                ->whereIn('category', array_keys($this->piidaCategories))
                ->where('updated_at', '>', now()->subDays(30))
                ->count();

            return $recentContent > 0;

        } catch (\Exception $e) {
            Log::error('Error verificando salud PIIDA: ' . $e->getMessage());
            return false;
        }
    }
}
