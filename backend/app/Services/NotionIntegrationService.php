<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

class NotionIntegrationService
{
    private $client;
    private $apiKey;
    private $baseUrl;
    private $knowledgeService;
    private $markdownService;

    public function __construct(
        KnowledgeBaseService $knowledgeService,
        MarkdownProcessingService $markdownService
    ) {
        $this->apiKey = config('services.notion.api_key');
        $this->baseUrl = 'https://api.notion.com/v1';
        $this->knowledgeService = $knowledgeService;
        $this->markdownService = $markdownService;

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => config('services.notion.timeout', 30),
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Notion-Version' => '2022-06-28',
                'Content-Type' => 'application/json'
            ]
        ]);
    }

    /**
     * Verificar conexión con Notion API
     */
    public function isHealthy(): bool
    {
        if (!$this->apiKey) {
            Log::warning('Notion API key not configured');
            return false;
        }

        try {
            $response = $this->client->get('/users/me');
            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            Log::error('Notion health check failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Sincronizar base de datos de Notion completa
     */
    public function syncDatabase(string $databaseId, array $options = []): array
    {
        $syncOptions = array_merge([
            'auto_index' => true,
            'update_existing' => true,
            'category' => 'notion_docs',
            'user_types' => ['student', 'employee', 'public'],
            'department' => 'GENERAL'
        ], $options);

        Log::info('Starting Notion database sync', [
            'database_id' => $databaseId,
            'options' => $syncOptions
        ]);

        try {
            // 1. Obtener todas las páginas de la base de datos
            $pages = $this->getDatabasePages($databaseId);
            
            $syncResults = [
                'total_pages' => count($pages),
                'processed' => 0,
                'errors' => 0,
                'indexed' => 0,
                'updated' => 0,
                'created' => 0
            ];

            foreach ($pages as $page) {
                try {
                    $result = $this->syncSinglePage($page, $syncOptions);
                    $syncResults['processed']++;
                    
                    if ($result['success']) {
                        $syncResults[$result['action']]++;
                        if ($result['indexed']) {
                            $syncResults['indexed']++;
                        }
                    } else {
                        $syncResults['errors']++;
                    }

                } catch (\Exception $e) {
                    Log::error('Error syncing page: ' . $e->getMessage(), [
                        'page_id' => $page['id'] ?? 'unknown'
                    ]);
                    $syncResults['errors']++;
                }
            }

            Log::info('Notion sync completed', $syncResults);
            return $syncResults;

        } catch (\Exception $e) {
            Log::error('Notion database sync failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener todas las páginas de una base de datos
     */
    private function getDatabasePages(string $databaseId): array
    {
        $allPages = [];
        $hasMore = true;
        $startCursor = null;

        while ($hasMore) {
            try {
                $body = [
                    'page_size' => 100
                ];

                if ($startCursor) {
                    $body['start_cursor'] = $startCursor;
                }

                $response = $this->client->post("/databases/{$databaseId}/query", [
                    'json' => $body
                ]);

                $data = json_decode($response->getBody(), true);
                
                $allPages = array_merge($allPages, $data['results'] ?? []);
                $hasMore = $data['has_more'] ?? false;
                $startCursor = $data['next_cursor'] ?? null;

            } catch (RequestException $e) {
                Log::error('Error querying Notion database: ' . $e->getMessage());
                throw $e;
            }
        }

        Log::info('Retrieved pages from Notion database', [
            'database_id' => $databaseId,
            'total_pages' => count($allPages)
        ]);

        return $allPages;
    }

    /**
     * Sincronizar una página individual
     */
    private function syncSinglePage(array $pageData, array $options): array
    {
        $pageId = $pageData['id'];
        
        // 1. Obtener contenido completo de la página
        $pageContent = $this->getPageContent($pageId);
        
        if (empty($pageContent['markdown'])) {
            return [
                'success' => false,
                'reason' => 'Empty content',
                'indexed' => false
            ];
        }

        // 2. Extraer metadatos
        $metadata = $this->extractPageMetadata($pageData, $pageContent);
        
        // 3. Preparar datos para knowledge base
        $knowledgeData = [
            'title' => $metadata['title'],
            'content' => $pageContent['markdown'],
            'category' => $options['category'],
            'department' => $options['department'],
            'user_types' => json_encode($options['user_types']),
            'keywords' => json_encode($metadata['keywords']),
            'source_url' => $metadata['url'],
            'priority' => $metadata['priority'],
            'is_active' => true,
            'created_by' => 'notion_sync',
            'metadata' => json_encode([
                'notion_id' => $pageId,
                'last_edited' => $metadata['last_edited'],
                'notion_url' => $metadata['url'],
                'sync_timestamp' => now()->toISOString()
            ])
        ];

        // 4. Verificar si ya existe
        $existingId = $this->findExistingPage($pageId);
        
        if ($existingId && $options['update_existing']) {
            // Actualizar página existente
            $success = $this->knowledgeService->updateContent($existingId, $knowledgeData);
            return [
                'success' => $success,
                'action' => 'updated',
                'indexed' => $success && $options['auto_index'],
                'knowledge_id' => $existingId
            ];
        } elseif (!$existingId) {
            // Crear nueva página
            $success = $this->knowledgeService->addContent($knowledgeData);
            return [
                'success' => $success,
                'action' => 'created',
                'indexed' => $success && $options['auto_index'],
                'knowledge_id' => null // No tenemos el ID de la nueva entrada
            ];
        }

        return [
            'success' => false,
            'reason' => 'Page exists and update_existing is false',
            'indexed' => false
        ];
    }

    /**
     * Obtener contenido completo de una página
     */
    private function getPageContent(string $pageId): array
    {
        try {
            // 1. Obtener metadatos de la página
            $pageResponse = $this->client->get("/pages/{$pageId}");
            $pageData = json_decode($pageResponse->getBody(), true);

            // 2. Obtener bloques de contenido
            $blocksResponse = $this->client->get("/blocks/{$pageId}/children?page_size=100");
            $blocksData = json_decode($blocksResponse->getBody(), true);

            // 3. Convertir bloques a markdown
            $markdown = $this->convertBlocksToMarkdown($blocksData['results'] ?? []);

            return [
                'page_data' => $pageData,
                'blocks' => $blocksData['results'] ?? [],
                'markdown' => $markdown,
                'plain_text' => $this->stripMarkdown($markdown)
            ];

        } catch (RequestException $e) {
            Log::error('Error getting Notion page content: ' . $e->getMessage(), [
                'page_id' => $pageId
            ]);
            return ['markdown' => '', 'plain_text' => ''];
        }
    }

    /**
     * Convertir bloques de Notion a Markdown
     */
    private function convertBlocksToMarkdown(array $blocks): string
    {
        $markdown = '';

        foreach ($blocks as $block) {
            $type = $block['type'] ?? '';
            
            switch ($type) {
                case 'paragraph':
                    $text = $this->extractRichText($block[$type]['rich_text'] ?? []);
                    if (!empty($text)) {
                        $markdown .= $text . "\n\n";
                    }
                    break;

                case 'heading_1':
                    $text = $this->extractRichText($block[$type]['rich_text'] ?? []);
                    if (!empty($text)) {
                        $markdown .= "# {$text}\n\n";
                    }
                    break;

                case 'heading_2':
                    $text = $this->extractRichText($block[$type]['rich_text'] ?? []);
                    if (!empty($text)) {
                        $markdown .= "## {$text}\n\n";
                    }
                    break;

                case 'heading_3':
                    $text = $this->extractRichText($block[$type]['rich_text'] ?? []);
                    if (!empty($text)) {
                        $markdown .= "### {$text}\n\n";
                    }
                    break;

                case 'bulleted_list_item':
                    $text = $this->extractRichText($block[$type]['rich_text'] ?? []);
                    if (!empty($text)) {
                        $markdown .= "- {$text}\n";
                    }
                    break;

                case 'numbered_list_item':
                    $text = $this->extractRichText($block[$type]['rich_text'] ?? []);
                    if (!empty($text)) {
                        $markdown .= "1. {$text}\n";
                    }
                    break;

                case 'code':
                    $text = $this->extractRichText($block[$type]['rich_text'] ?? []);
                    $language = $block[$type]['language'] ?? '';
                    if (!empty($text)) {
                        $markdown .= "```{$language}\n{$text}\n```\n\n";
                    }
                    break;

                case 'quote':
                    $text = $this->extractRichText($block[$type]['rich_text'] ?? []);
                    if (!empty($text)) {
                        $markdown .= "> {$text}\n\n";
                    }
                    break;

                case 'divider':
                    $markdown .= "---\n\n";
                    break;

                default:
                    // Para tipos de bloque no manejados, intentar extraer texto básico
                    if (isset($block[$type]['rich_text'])) {
                        $text = $this->extractRichText($block[$type]['rich_text']);
                        if (!empty($text)) {
                            $markdown .= $text . "\n\n";
                        }
                    }
                    break;
            }
        }

        return trim($markdown);
    }

    /**
     * Extraer texto de rich text de Notion
     */
    private function extractRichText(array $richTextArray): string
    {
        $text = '';
        
        foreach ($richTextArray as $richText) {
            $plainText = $richText['plain_text'] ?? '';
            $annotations = $richText['annotations'] ?? [];
            
            // Aplicar formato de markdown según las anotaciones
            if ($annotations['bold'] ?? false) {
                $plainText = "**{$plainText}**";
            }
            if ($annotations['italic'] ?? false) {
                $plainText = "*{$plainText}*";
            }
            if ($annotations['code'] ?? false) {
                $plainText = "`{$plainText}`";
            }
            if ($annotations['strikethrough'] ?? false) {
                $plainText = "~~{$plainText}~~";
            }
            
            // Manejar enlaces
            if (isset($richText['href'])) {
                $plainText = "[{$plainText}]({$richText['href']})";
            }
            
            $text .= $plainText;
        }
        
        return $text;
    }

    /**
     * Extraer metadatos de una página
     */
    private function extractPageMetadata(array $pageData, array $pageContent): array
    {
        // Extraer título
        $title = 'Sin título';
        if (isset($pageData['properties'])) {
            foreach ($pageData['properties'] as $property) {
                if ($property['type'] === 'title' && !empty($property['title'])) {
                    $title = $this->extractRichText($property['title']);
                    break;
                }
            }
        }

        // Extraer palabras clave del contenido
        $keywords = $this->extractKeywords($pageContent['plain_text']);

        // Determinar prioridad basada en el título y contenido
        $priority = $this->determinePriority($title, $pageContent['plain_text']);

        return [
            'title' => $title,
            'keywords' => $keywords,
            'priority' => $priority,
            'url' => $pageData['url'] ?? '',
            'last_edited' => $pageData['last_edited_time'] ?? now()->toISOString(),
            'created' => $pageData['created_time'] ?? now()->toISOString()
        ];
    }

    /**
     * Extraer palabras clave del contenido
     */
    private function extractKeywords(string $content): array
    {
        // Palabras clave relevantes para contexto universitario
        $keywordPatterns = [
            'trámite', 'trámites', 'solicitud', 'solicitar',
            'inscripción', 'matrícula', 'registro',
            'biblioteca', 'laboratorio', 'aula',
            'correo', 'email', 'sistema', 'plataforma',
            'procedimiento', 'proceso', 'pasos',
            'requisitos', 'documentos', 'información',
            'servicio', 'servicios', 'contacto',
            'universidad', 'uan', 'estudiante', 'docente'
        ];

        $foundKeywords = [];
        $contentLower = strtolower($content);

        foreach ($keywordPatterns as $keyword) {
            if (str_contains($contentLower, $keyword)) {
                $foundKeywords[] = $keyword;
            }
        }

        return array_unique($foundKeywords);
    }

    /**
     * Determinar prioridad del contenido
     */
    private function determinePriority(string $title, string $content): string
    {
        $titleLower = strtolower($title);
        $contentLower = strtolower($content);

        // Alta prioridad para contenido crítico
        $highPriorityTerms = [
            'importante', 'urgente', 'crítico', 'obligatorio',
            'inscripción', 'matrícula', 'examen', 'fecha límite'
        ];

        foreach ($highPriorityTerms as $term) {
            if (str_contains($titleLower, $term) || str_contains($contentLower, $term)) {
                return 'high';
            }
        }

        // Prioridad media para servicios y trámites
        $mediumPriorityTerms = [
            'trámite', 'servicio', 'procedimiento', 'proceso'
        ];

        foreach ($mediumPriorityTerms as $term) {
            if (str_contains($titleLower, $term)) {
                return 'medium';
            }
        }

        return 'low';
    }

    /**
     * Buscar página existente por ID de Notion
     */
    private function findExistingPage(string $notionId): ?int
    {
        try {
            $result = \DB::table('knowledge_base')
                ->whereRaw("JSON_EXTRACT(metadata, '$.notion_id') = ?", [$notionId])
                ->where('is_active', true)
                ->first();

            return $result ? $result->id : null;
        } catch (\Exception $e) {
            Log::error('Error finding existing Notion page: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Eliminar markdown básico para obtener texto plano
     */
    private function stripMarkdown(string $markdown): string
    {
        // Remover elementos de markdown básicos
        $text = preg_replace('/^#{1,6}\s+/m', '', $markdown); // Headers
        $text = preg_replace('/\*\*(.*?)\*\*/', '$1', $text); // Bold
        $text = preg_replace('/\*(.*?)\*/', '$1', $text); // Italic
        $text = preg_replace('/`(.*?)`/', '$1', $text); // Code
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text); // Links
        $text = preg_replace('/^>\s+/m', '', $text); // Quotes
        $text = preg_replace('/^[-*+]\s+/m', '', $text); // Lists
        $text = preg_replace('/^\d+\.\s+/m', '', $text); // Numbered lists
        
        return trim($text);
    }

    /**
     * Sincronizar página individual por ID
     */
    public function syncPage(string $pageId, array $options = []): array
    {
        $syncOptions = array_merge([
            'auto_index' => true,
            'category' => 'notion_docs',
            'user_types' => ['student', 'employee', 'public'],
            'department' => 'GENERAL'
        ], $options);

        try {
            // Simular estructura de página para reutilizar syncSinglePage
            $pageData = ['id' => $pageId];
            return $this->syncSinglePage($pageData, $syncOptions);
        } catch (\Exception $e) {
            Log::error('Error syncing single Notion page: ' . $e->getMessage(), [
                'page_id' => $pageId
            ]);
            throw $e;
        }
    }

    /**
     * Obtener estadísticas de sincronización
     */
    public function getSyncStats(): array
    {
        try {
            $stats = [
                'total_notion_pages' => \DB::table('knowledge_base')
                    ->where('created_by', 'notion_sync')
                    ->where('is_active', true)
                    ->count(),
                'recent_syncs' => \DB::table('knowledge_base')
                    ->where('created_by', 'notion_sync')
                    ->where('updated_at', '>', now()->subHours(24))
                    ->count(),
                'last_sync' => \DB::table('knowledge_base')
                    ->where('created_by', 'notion_sync')
                    ->orderBy('updated_at', 'desc')
                    ->value('updated_at')
            ];

            return $stats;
        } catch (\Exception $e) {
            Log::error('Error getting Notion sync stats: ' . $e->getMessage());
            return [
                'total_notion_pages' => 0,
                'recent_syncs' => 0,
                'last_sync' => null
            ];
        }
    }
}