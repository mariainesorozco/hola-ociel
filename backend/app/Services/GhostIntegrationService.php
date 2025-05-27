<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class GhostIntegrationService
{
    private $client;
    private $ghostUrl;
    private $apiKey;
    private $ollamaService;

    public function __construct(OllamaService $ollamaService)
    {
        $this->ghostUrl = config('services.ghost.url');
        $this->apiKey = config('services.ghost.api_key');
        $this->ollamaService = $ollamaService;

        $this->client = new Client([
            'base_uri' => $this->ghostUrl,
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'UAN-Ociel-Ghost-Integration/1.0'
            ]
        ]);
    }

    /**
     * Sincronización completa con Ghost CMS
     */
    public function fullSync(): array
    {
        Log::info('Iniciando sincronización completa con Ghost CMS');

        $results = [
            'posts_processed' => 0,
            'pages_processed' => 0,
            'entries_created' => 0,
            'entries_updated' => 0,
            'errors' => 0,
            'execution_time' => 0
        ];

        $startTime = microtime(true);

        try {
            // Sincronizar posts (noticias, eventos, etc.)
            $postsResult = $this->syncPosts();
            $results['posts_processed'] = $postsResult['processed'];
            $results['entries_created'] += $postsResult['created'];
            $results['entries_updated'] += $postsResult['updated'];

            // Sincronizar páginas (información institucional)
            $pagesResult = $this->syncPages();
            $results['pages_processed'] = $pagesResult['processed'];
            $results['entries_created'] += $pagesResult['created'];
            $results['entries_updated'] += $pagesResult['updated'];

            // Sincronizar tags para mejor categorización
            $this->syncTags();

            $results['execution_time'] = round((microtime(true) - $startTime), 2);

            // Actualizar timestamp de última sincronización
            Cache::put('ghost_last_sync', now(), 86400);

            Log::info('Sincronización con Ghost completada', $results);

        } catch (\Exception $e) {
            $results['errors']++;
            Log::error('Error en sincronización con Ghost: ' . $e->getMessage());
        }

        return $results;
    }

    /**
     * Sincronizar posts de Ghost
     */
    private function syncPosts(): array
    {
        $result = ['processed' => 0, 'created' => 0, 'updated' => 0];

        try {
            $posts = $this->fetchFromGhost('/ghost/api/content/posts/', [
                'include' => 'tags,authors',
                'limit' => 'all',
                'fields' => 'id,title,slug,html,plaintext,published_at,updated_at,featured,excerpt,tags,url'
            ]);

            foreach ($posts as $post) {
                $processed = $this->processGhostContent($post, 'post');

                if ($processed) {
                    $saved = $this->saveToKnowledgeBase($processed);
                    if ($saved['created']) {
                        $result['created']++;
                    } else {
                        $result['updated']++;
                    }
                    $result['processed']++;
                }
            }

        } catch (\Exception $e) {
            Log::error('Error sincronizando posts de Ghost: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Sincronizar páginas de Ghost
     */
    private function syncPages(): array
    {
        $result = ['processed' => 0, 'created' => 0, 'updated' => 0];

        try {
            $pages = $this->fetchFromGhost('/ghost/api/content/pages/', [
                'include' => 'tags,authors',
                'limit' => 'all',
                'fields' => 'id,title,slug,html,plaintext,published_at,updated_at,featured,excerpt,tags,url'
            ]);

            foreach ($pages as $page) {
                $processed = $this->processGhostContent($page, 'page');

                if ($processed) {
                    $saved = $this->saveToKnowledgeBase($processed);
                    if ($saved['created']) {
                        $result['created']++;
                    } else {
                        $result['updated']++;
                    }
                    $result['processed']++;
                }
            }

        } catch (\Exception $e) {
            Log::error('Error sincronizando páginas de Ghost: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Obtener datos de Ghost API
     */
    private function fetchFromGhost(string $endpoint, array $params = []): array
    {
        try {
            $response = $this->client->get($endpoint, [
                'query' => array_merge($params, [
                    'key' => $this->apiKey
                ])
            ]);

            $data = json_decode($response->getBody(), true);

            return $data['posts'] ?? $data['pages'] ?? $data['tags'] ?? [];

        } catch (RequestException $e) {
            Log::error('Error fetching from Ghost API: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Procesar contenido de Ghost para knowledge base
     */
    private function processGhostContent(array $content, string $type): ?array
    {
        try {
            // Limpiar y procesar el contenido HTML
            $cleanContent = $this->cleanHtmlContent($content['html'] ?? $content['plaintext'] ?? '');

            // Si el contenido es muy corto, saltarlo
            if (strlen($cleanContent) < 100) {
                return null;
            }

            // Determinar categoría basada en tags y tipo de contenido
            $category = $this->determineCategory($content['tags'] ?? [], $type, $content['title']);

            // Extraer keywords usando IA
            $keywords = $this->extractIntelligentKeywords($content['title'], $cleanContent);

            // Determinar departamento responsable
            $department = $this->determineDepartment($content['tags'] ?? [], $category, $content['title']);

            // Generar resumen inteligente
            $summary = $this->generateIntelligentSummary($content['title'], $cleanContent);

            return [
                'title' => $content['title'],
                'content' => $cleanContent,
                'summary' => $summary,
                'category' => $category,
                'department' => $department,
                'user_types' => json_encode($this->determineUserTypes($category, $content['tags'] ?? [])),
                'keywords' => json_encode($keywords),
                'source_url' => $content['url'] ?? null,
                'priority' => $this->determinePriority($content['featured'] ?? false, $type, $category),
                'is_active' => true,
                'ghost_id' => $content['id'],
                'ghost_type' => $type,
                'published_at' => $content['published_at'] ? Carbon::parse($content['published_at']) : null,
                'created_by' => 'ghost_sync',
                'updated_by' => 'ghost_sync'
            ];

        } catch (\Exception $e) {
            Log::error('Error procesando contenido de Ghost: ' . $e->getMessage(), [
                'content_id' => $content['id'] ?? 'unknown',
                'title' => $content['title'] ?? 'unknown'
            ]);
            return null;
        }
    }

    /**
     * Limpiar contenido HTML
     */
    private function cleanHtmlContent(string $html): string
    {
        // Remover scripts y estilos
        $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $html);
        $html = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $html);

        // Convertir elementos HTML a texto legible
        $html = str_replace(['<br>', '<br/>', '<br />'], "\n", $html);
        $html = str_replace(['<li>', '</li>'], ["\n• ", ""], $html);
        $html = str_replace(['<h1>', '<h2>', '<h3>', '<h4>', '<h5>', '<h6>'], "\n\n### ", $html);
        $html = str_replace(['</h1>', '</h2>', '</h3>', '</h4>', '</h5>', '</h6>'], " ###\n", $html);
        $html = str_replace(['<p>', '</p>'], ["\n", "\n"], $html);

        // Remover todas las etiquetas HTML restantes
        $text = strip_tags($html);

        // Limpiar espacios múltiples y saltos de línea
        $text = preg_replace('/\s+/', ' ', $text);
        $text = preg_replace('/\n\s*\n/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Extraer keywords inteligentes usando IA
     */
    private function extractIntelligentKeywords(string $title, string $content): array
    {
        try {
            $prompt = "Analiza el siguiente contenido universitario y extrae 8-12 palabras clave relevantes para búsqueda y categorización.

Título: {$title}

Contenido: " . substr($content, 0, 1000) . "

Instrucciones:
- Incluye términos específicos de la UAN
- Considera sinónimos que los usuarios podrían usar
- Incluye nombres de departamentos, servicios o trámites mencionados
- Evita palabras muy genéricas
- Responde solo con las palabras clave separadas por comas";

            $response = $this->ollamaService->generateResponse($prompt, [
                'temperature' => 0.3,
                'max_tokens' => 200
            ]);

            if ($response['success']) {
                $keywords = array_map('trim', explode(',', $response['response']));
                return array_filter($keywords, fn($k) => strlen($k) > 2 && strlen($k) < 50);
            }

        } catch (\Exception $e) {
            Log::warning('Error extrayendo keywords con IA: ' . $e->getMessage());
        }

        // Fallback: extracción simple
        return $this->extractSimpleKeywords($title . ' ' . $content);
    }

    /**
     * Generar resumen inteligente
     */
    private function generateIntelligentSummary(string $title, string $content): string
    {
        try {
            $prompt = "Genera un resumen conciso de máximo 200 caracteres del siguiente contenido universitario:

Título: {$title}
Contenido: " . substr($content, 0, 800) . "

El resumen debe:
- Ser informativo y preciso
- Incluir puntos clave principales
- Ser útil para búsqueda rápida
- Mantener terminología oficial de la UAN";

            $response = $this->ollamaService->generateResponse($prompt, [
                'temperature' => 0.2,
                'max_tokens' => 100
            ]);

            if ($response['success']) {
                return substr(trim($response['response']), 0, 200);
            }

        } catch (\Exception $e) {
            Log::warning('Error generando resumen con IA: ' . $e->getMessage());
        }

        // Fallback: primeras líneas del contenido
        return substr($content, 0, 200) . '...';
    }

    /**
     * Determinar categoría inteligente
     */
    private function determineCategory(array $tags, string $type, string $title): string
    {
        $titleLower = strtolower($title);
        $tagNames = array_map(fn($tag) => strtolower($tag['name'] ?? ''), $tags);

        // Mapeo de categorías por tags
        $categoryMapping = [
            'tramites' => ['tramite', 'proceso', 'inscripcion', 'admision', 'titulacion', 'servicio-escolar'],
            'oferta_educativa' => ['carrera', 'licenciatura', 'programa', 'academico', 'plan-estudios'],
            'servicios' => ['servicio', 'biblioteca', 'laboratorio', 'clinica', 'deporte'],
            'eventos' => ['evento', 'congreso', 'conferencia', 'ceremonia', 'festival'],
            'noticias' => ['noticia', 'comunicado', 'aviso', 'boletin'],
            'investigacion' => ['investigacion', 'proyecto', 'ciencia', 'innovacion'],
            'administrativa' => ['reglamento', 'normativa', 'procedimiento', 'administracion']
        ];

        // Verificar por tags primero
        foreach ($categoryMapping as $category => $keywords) {
            foreach ($tagNames as $tagName) {
                if (in_array($tagName, $keywords)) {
                    return $category;
                }
            }
        }

        // Verificar por título
        foreach ($categoryMapping as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($titleLower, $keyword)) {
                    return $category;
                }
            }
        }

        // Categoría por tipo de contenido
        return $type === 'page' ? 'informacion_general' : 'noticias';
    }

    /**
     * Determinar departamento responsable
     */
    private function determineDepartment(array $tags, string $category, string $title): string
    {
        $titleLower = strtolower($title);
        $tagNames = array_map(fn($tag) => strtolower($tag['name'] ?? ''), $tags);

        $departmentMapping = [
            'DGSA' => ['dgsa', 'servicios-academicos', 'inscripcion', 'titulacion', 'control-escolar'],
            'DGS' => ['sistemas', 'tecnologia', 'plataforma', 'correo', 'soporte'],
            'BIBLIOTECA' => ['biblioteca', 'libros', 'acervo', 'consulta'],
            'VINCULACION' => ['vinculacion', 'empresa', 'egresados', 'bolsa-trabajo'],
            'INVESTIGACION' => ['investigacion', 'posgrado', 'maestria', 'doctorado'],
            'DIFUSION' => ['cultura', 'arte', 'evento', 'extension'],
            'SECRETARIA_GENERAL' => ['reglamento', 'normativa', 'juridico']
        ];

        // Verificar por tags
        foreach ($departmentMapping as $dept => $keywords) {
            foreach ($tagNames as $tagName) {
                if (in_array($tagName, $keywords)) {
                    return $dept;
                }
            }
        }

        // Verificar por título
        foreach ($departmentMapping as $dept => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($titleLower, $keyword)) {
                    return $dept;
                }
            }
        }

        // Mapeo por categoría
        $categoryToDepartment = [
            'tramites' => 'DGSA',
            'servicios' => 'GENERAL',
            'sistemas' => 'DGS',
            'investigacion' => 'INVESTIGACION'
        ];

        return $categoryToDepartment[$category] ?? 'GENERAL';
    }

    /**
     * Determinar tipos de usuario
     */
    private function determineUserTypes(string $category, array $tags): array
    {
        $tagNames = array_map(fn($tag) => strtolower($tag['name'] ?? ''), $tags);

        // Mapeo por categorías
        $userTypeMapping = [
            'tramites' => ['student', 'employee'],
            'oferta_educativa' => ['student', 'public'],
            'servicios' => ['student', 'employee'],
            'eventos' => ['student', 'employee', 'public'],
            'noticias' => ['student', 'employee', 'public'],
            'investigacion' => ['employee', 'public'],
            'administrativa' => ['employee']
        ];

        // Verificar tags específicos
        if (in_array('estudiantes', $tagNames)) {
            return ['student'];
        }
        if (in_array('empleados', $tagNames)) {
            return ['employee'];
        }
        if (in_array('publico-general', $tagNames)) {
            return ['public'];
        }

        return $userTypeMapping[$category] ?? ['student', 'employee', 'public'];
    }

    /**
     * Determinar prioridad
     */
    private function determinePriority(bool $featured, string $type, string $category): string
    {
        if ($featured) {
            return 'high';
        }

        $highPriorityCategories = ['tramites', 'servicios', 'eventos'];
        if (in_array($category, $highPriorityCategories)) {
            return 'high';
        }

        return $type === 'page' ? 'medium' : 'low';
    }

    /**
     * Guardar en knowledge base
     */
    private function saveToKnowledgeBase(array $data): array
    {
        $result = ['created' => false, 'updated' => false];

        try {
            // Verificar si ya existe por ghost_id
            $existing = DB::table('knowledge_base')
                ->where('ghost_id', $data['ghost_id'])
                ->first();

            if ($existing) {
                // Actualizar existente
                unset($data['ghost_id']); // No actualizar el ID
                $data['updated_at'] = now();

                DB::table('knowledge_base')
                    ->where('id', $existing->id)
                    ->update($data);

                $result['updated'] = true;

                Log::debug('Contenido Ghost actualizado', ['id' => $existing->id, 'title' => $data['title']]);
            } else {
                // Crear nuevo
                $data['created_at'] = now();
                $data['updated_at'] = now();

                DB::table('knowledge_base')->insert($data);
                $result['created'] = true;

                Log::debug('Nuevo contenido Ghost creado', ['title' => $data['title']]);
            }

        } catch (\Exception $e) {
            Log::error('Error guardando contenido de Ghost en knowledge base: ' . $e->getMessage(), [
                'title' => $data['title'] ?? 'unknown'
            ]);
        }

        return $result;
    }

    /**
     * Extracción simple de keywords (fallback)
     */
    private function extractSimpleKeywords(string $text): array
    {
        $text = strtolower($text);
        $words = preg_split('/\s+/', $text);
        $keywords = [];

        $stopWords = [
            'de', 'la', 'el', 'en', 'a', 'y', 'que', 'es', 'se', 'con', 'por', 'para',
            'del', 'los', 'las', 'un', 'una', 'su', 'sus', 'al', 'le', 'da', 'muy',
            'mas', 'fue', 'son', 'como', 'pero', 'sus', 'le', 'ya', 'o', 'porque'
        ];

        foreach ($words as $word) {
            $word = trim($word, '.,;:()[]{}¿?¡!');
            if (strlen($word) > 3 && !in_array($word, $stopWords) && !is_numeric($word)) {
                $keywords[] = $word;
            }
        }

        return array_unique(array_slice($keywords, 0, 10));
    }

    /**
     * Sincronizar tags de Ghost para mejor categorización
     */
    private function syncTags(): void
    {
        try {
            $tags = $this->fetchFromGhost('/ghost/api/content/tags/', [
                'limit' => 'all',
                'fields' => 'id,name,slug,description'
            ]);

            // Guardar tags en cache para uso en categorización
            $tagMapping = [];
            foreach ($tags as $tag) {
                $tagMapping[$tag['slug']] = [
                    'name' => $tag['name'],
                    'description' => $tag['description'] ?? ''
                ];
            }

            Cache::put('ghost_tags', $tagMapping, 3600);

            Log::info('Tags de Ghost sincronizados', ['count' => count($tags)]);

        } catch (\Exception $e) {
            Log::error('Error sincronizando tags de Ghost: ' . $e->getMessage());
        }
    }

    /**
     * Sincronización incremental basada en fecha
     */
    public function incrementalSync(?Carbon $since = null): array
    {
        $since = $since ?? Cache::get('ghost_last_sync', now()->subDays(1));

        Log::info('Iniciando sincronización incremental desde: ' . $since->toISOString());

        $results = ['processed' => 0, 'created' => 0, 'updated' => 0, 'errors' => 0];

        try {
            // Posts actualizados desde la fecha
            $posts = $this->fetchFromGhost('/ghost/api/content/posts/', [
                'include' => 'tags,authors',
                'filter' => 'updated_at:>\'' . $since->toISOString() . '\'',
                'fields' => 'id,title,slug,html,plaintext,published_at,updated_at,featured,excerpt,tags,url'
            ]);

            foreach ($posts as $post) {
                $processed = $this->processGhostContent($post, 'post');
                if ($processed) {
                    $saved = $this->saveToKnowledgeBase($processed);
                    if ($saved['created']) $results['created']++;
                    if ($saved['updated']) $results['updated']++;
                    $results['processed']++;
                }
            }

            // Pages actualizadas desde la fecha
            $pages = $this->fetchFromGhost('/ghost/api/content/pages/', [
                'include' => 'tags,authors',
                'filter' => 'updated_at:>\'' . $since->toISOString() . '\'',
                'fields' => 'id,title,slug,html,plaintext,published_at,updated_at,featured,excerpt,tags,url'
            ]);

            foreach ($pages as $page) {
                $processed = $this->processGhostContent($page, 'page');
                if ($processed) {
                    $saved = $this->saveToKnowledgeBase($processed);
                    if ($saved['created']) $results['created']++;
                    if ($saved['updated']) $results['updated']++;
                    $results['processed']++;
                }
            }

            Cache::put('ghost_last_sync', now(), 86400);

        } catch (\Exception $e) {
            $results['errors']++;
            Log::error('Error en sincronización incremental: ' . $e->getMessage());
        }

        return $results;
    }

    /**
     * Eliminar contenido que ya no existe en Ghost
     */
    public function cleanupOrphanedContent(): array
    {
        $results = ['deleted' => 0, 'checked' => 0];

        try {
            $ghostContent = DB::table('knowledge_base')
                ->whereNotNull('ghost_id')
                ->select(['id', 'ghost_id', 'ghost_type'])
                ->get();

            foreach ($ghostContent as $content) {
                $results['checked']++;

                $endpoint = $content->ghost_type === 'post' ? '/ghost/api/content/posts/' : '/ghost/api/content/pages/';

                try {
                    $this->fetchFromGhost($endpoint . $content->ghost_id);
                } catch (\Exception $e) {
                    // Si da 404, el contenido ya no existe en Ghost
                    if (str_contains($e->getMessage(), '404')) {
                        DB::table('knowledge_base')->where('id', $content->id)->delete();
                        $results['deleted']++;
                        Log::info('Contenido huérfano eliminado', ['id' => $content->id, 'ghost_id' => $content->ghost_id]);
                    }
                }
            }

        } catch (\Exception $e) {
            Log::error('Error limpiando contenido huérfano: ' . $e->getMessage());
        }

        return $results;
    }

    /**
     * Verificar salud de la conexión con Ghost
     */
    public function healthCheck(): array
    {
        $health = [
            'status' => 'ok',
            'ghost_reachable' => false,
            'api_key_valid' => false,
            'last_sync' => Cache::get('ghost_last_sync'),
            'total_synced_content' => 0
        ];

        try {
            // Verificar conectividad básica
            $response = $this->client->get('/ghost/api/content/posts/', [
                'query' => ['key' => $this->apiKey, 'limit' => 1]
            ]);

            if ($response->getStatusCode() === 200) {
                $health['ghost_reachable'] = true;
                $health['api_key_valid'] = true;
            }

            // Contar contenido sincronizado
            $health['total_synced_content'] = DB::table('knowledge_base')
                ->whereNotNull('ghost_id')
                ->count();

        } catch (\Exception $e) {
            $health['status'] = 'error';
            $health['error'] = $e->getMessage();
        }

        return $health;
    }
}
