<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use App\Services\EnhancedQdrantVectorService;
use App\Services\OllamaService;
use App\Services\EnhancedPromptService;

class NotionSearchService
{
    private $vectorService;
    private $ollamaService;
    private $promptService;

    public function __construct(
        EnhancedQdrantVectorService $vectorService,
        OllamaService $ollamaService,
        EnhancedPromptService $promptService
    ) {
        $this->vectorService = $vectorService;
        $this->ollamaService = $ollamaService;
        $this->promptService = $promptService;
    }

    /**
     * Procesar búsqueda al estilo Notion con respuesta estructurada
     */
    public function searchAndRespond(string $query, array $options = []): array
    {
        try {
            // 1. Analizar la consulta para identificar intención
            $queryAnalysis = $this->analyzeQuery($query);

            // 2. Ejecutar búsqueda semántica optimizada
            $searchResults = $this->executeSemanticSearch($query, $queryAnalysis);

            // 3. Si hay archivos CSV cargados, procesarlos
            if (!empty($options['uploaded_files'])) {
                $csvData = $this->processCsvFiles($options['uploaded_files']);
                $searchResults = $this->enrichWithCsvData($searchResults, $csvData);
            }

            // 4. Generar respuesta estructurada tipo Notion
            $response = $this->generateNotionStyleResponse($query, $searchResults, $queryAnalysis);

            return [
                'success' => true,
                'response' => $response,
                'metadata' => [
                    'sources' => count($searchResults),
                    'confidence' => $this->calculateConfidence($searchResults),
                    'query_type' => $queryAnalysis['type']
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Error en búsqueda Notion: ' . $e->getMessage());
            return [
                'success' => false,
                'response' => '🐯 ¡Ups! Tuve un problema técnico. ¿Podrías reformular tu pregunta?',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Analizar consulta para determinar tipo e intención
     */
    private function analyzeQuery(string $query): array
    {
        $queryLower = strtolower($query);

        // Detectar tipo de consulta
        $patterns = [
            'servicio_especifico' => [
                'keywords' => ['servicio', 'trámite', 'cómo', 'proceso', 'requisitos'],
                'pattern' => '/(?:servicio|trámite|proceso) (?:de|para) (\w+)/i'
            ],
            'informacion_general' => [
                'keywords' => ['qué es', 'información', 'detalles', 'explicar'],
                'pattern' => '/(?:qué es|información sobre|detalles de) (\w+)/i'
            ],
            'busqueda_departamento' => [
                'keywords' => ['secretaría', 'dirección', 'departamento', 'dependencia'],
                'pattern' => '/(?:secretaría|dirección|departamento) (?:de|general) (\w+)/i'
            ],
            'consulta_csv' => [
                'keywords' => ['lista', 'tabla', 'servicios de', 'todos los'],
                'pattern' => '/(?:lista|tabla|todos los) (?:servicios|trámites)/i'
            ]
        ];

        $type = 'general';
        $entities = [];

        foreach ($patterns as $patternType => $config) {
            foreach ($config['keywords'] as $keyword) {
                if (strpos($queryLower, $keyword) !== false) {
                    $type = $patternType;

                    // Extraer entidades con regex
                    if (preg_match($config['pattern'], $query, $matches)) {
                        $entities[] = $matches[1];
                    }
                    break 2;
                }
            }
        }

        return [
            'type' => $type,
            'entities' => $entities,
            'original_query' => $query,
            'requires_csv' => in_array($type, ['consulta_csv', 'servicio_especifico'])
        ];
    }

    /**
     * Ejecutar búsqueda semántica optimizada
     */
    private function executeSemanticSearch(string $query, array $queryAnalysis): array
    {
        // Configurar filtros según el análisis
        $filters = [];

        if ($queryAnalysis['type'] === 'busqueda_departamento' && !empty($queryAnalysis['entities'])) {
            $filters['department'] = $queryAnalysis['entities'][0];
        }

        // Usar diferentes métodos según el tipo
        switch ($queryAnalysis['type']) {
            case 'servicio_especifico':
                return $this->vectorService->searchNotionServices($query, $filters, 5, 0.5);

            case 'consulta_csv':
                return $this->vectorService->searchNotionServices($query, $filters, 10, 0.4);

            default:
                return $this->vectorService->hybridSearchPiida($query, $filters, 5);
        }
    }

    /**
     * Procesar archivos CSV cargados
     */
    private function processCsvFiles(array $files): array
    {
        $csvData = [];

        foreach ($files as $file) {
            if (str_ends_with($file['name'], '.csv')) {
                // Leer y parsear CSV
                $content = file_get_contents($file['path']);
                $rows = array_map('str_getcsv', explode("\n", $content));
                $headers = array_shift($rows);

                foreach ($rows as $row) {
                    if (count($row) === count($headers)) {
                        $csvData[] = array_combine($headers, $row);
                    }
                }
            }
        }

        return $csvData;
    }

    /**
     * Enriquecer resultados con datos CSV
     */
    private function enrichWithCsvData(array $searchResults, array $csvData): array
    {
        if (empty($csvData)) {
            return $searchResults;
        }

        // Mapear servicios del CSV con resultados de búsqueda
        foreach ($searchResults as &$result) {
            foreach ($csvData as $csvRow) {
                if ($this->matchesService($result, $csvRow)) {
                    // Enriquecer con datos del CSV
                    $result['csv_data'] = $csvRow;
                    $result['has_csv_match'] = true;
                }
            }
        }

        // Agregar servicios del CSV que no estén en los resultados
        foreach ($csvData as $csvRow) {
            $found = false;
            foreach ($searchResults as $result) {
                if ($this->matchesService($result, $csvRow)) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $searchResults[] = [
                    'content' => $this->buildContentFromCsv($csvRow),
                    'csv_data' => $csvRow,
                    'score' => 0.8,
                    'source' => 'csv_file'
                ];
            }
        }

        return $searchResults;
    }

    /**
     * Verificar si un resultado coincide con un servicio CSV
     */
    private function matchesService(array $result, array $csvRow): bool
    {
        $serviceName = $csvRow['Servicio'] ?? '';
        $serviceId = $csvRow['Id_Servicio'] ?? '';

        return (
            stripos($result['content'], $serviceName) !== false ||
            stripos($result['content'], $serviceId) !== false
        );
    }

    /**
     * Construir contenido desde datos CSV
     */
    private function buildContentFromCsv(array $csvRow): string
    {
        $content = "Servicio: " . ($csvRow['Servicio'] ?? 'N/A') . "\n";
        $content .= "ID: " . ($csvRow['Id_Servicio'] ?? 'N/A') . "\n";
        $content .= "Categoría: " . ($csvRow['Categoria'] ?? 'N/A') . "\n";
        $content .= "Subcategoría: " . ($csvRow['Subcategoria'] ?? 'N/A') . "\n";
        $content .= "Dependencia: " . ($csvRow['Dependencia'] ?? 'N/A') . "\n";
        $content .= "Modalidad: " . ($csvRow['Modalidad'] ?? 'N/A') . "\n";
        $content .= "Costo: " . ($csvRow['Costo'] ?? 'Gratuito') . "\n";
        $content .= "Estado: " . ($csvRow['Estado'] ?? 'Activo') . "\n";
        $content .= "Usuarios: " . ($csvRow['Usuarios'] ?? 'Público general') . "\n";

        return $content;
    }

    /**
     * Generar respuesta estilo Notion con formato estructurado
     */
    private function generateNotionStyleResponse(string $query, array $results, array $queryAnalysis): string
    {
        if (empty($results)) {
            return $this->generateNoResultsResponse($query);
        }

        // Preparar contexto para el modelo
        $context = [
            'query' => $query,
            'query_type' => $queryAnalysis['type'],
            'results' => $results
        ];

        // Construir prompt específico para respuestas tipo Notion
        $prompt = $this->buildNotionStylePrompt($context);

        // Generar respuesta con Ollama
        $response = $this->ollamaService->generateOcielResponse(
            $query,
            ['knowledge_base' => $results],
            'public',
            null
        );

        if ($response['success']) {
            return $this->formatNotionResponse($response['response'], $results);
        }

        return $this->generateFallbackResponse($results);
    }

    /**
     * Construir prompt para respuestas estilo Notion
     */
    private function buildNotionStylePrompt(array $context): string
    {
        $prompt = "MODO NOTION AI - RESPUESTA ESTRUCTURADA\n\n";

        $prompt .= "Genera una respuesta siguiendo el estilo de Notion AI:\n";
        $prompt .= "- Usa emojis relevantes para cada sección\n";
        $prompt .= "- Estructura la información con headers claros\n";
        $prompt .= "- Para servicios, incluye: Descripción, Requisitos, Proceso, Contacto\n";
        $prompt .= "- Mantén un tono profesional pero accesible\n";
        $prompt .= "- Si hay múltiples resultados, organízalos en una lista\n\n";

        $prompt .= "INFORMACIÓN DISPONIBLE:\n";
        foreach ($context['results'] as $idx => $result) {
            $prompt .= "\n--- Resultado " . ($idx + 1) . " ---\n";
            $prompt .= $result['content'] . "\n";

            if (isset($result['csv_data'])) {
                $prompt .= "\nDATOS ESTRUCTURADOS:\n";
                foreach ($result['csv_data'] as $key => $value) {
                    $prompt .= "- {$key}: {$value}\n";
                }
            }
        }

        $prompt .= "\nCONSULTA: " . $context['query'];

        return $prompt;
    }

    /**
     * Formatear respuesta con estructura Notion
     */
    private function formatNotionResponse(string $response, array $results): string
    {
        // Agregar metadatos si hay servicios específicos
        if ($this->hasServiceData($results)) {
            $response .= "\n\n---\n";
            $response .= "📊 **Datos técnicos**\n";

            foreach ($results as $result) {
                if (isset($result['csv_data'])) {
                    $data = $result['csv_data'];
                    $response .= "\n🆔 ID: `" . ($data['Id_Servicio'] ?? 'N/A') . "`\n";
                    $response .= "🏢 Dependencia: " . ($data['Dependencia'] ?? 'N/A') . "\n";
                    $response .= "💰 Costo: " . ($data['Costo'] ?? 'Gratuito') . "\n";
                    $response .= "✅ Estado: " . ($data['Estado'] ?? 'Activo') . "\n";
                    break; // Solo mostrar el primero
                }
            }
        }

        return $response;
    }

    /**
     * Verificar si hay datos de servicio
     */
    private function hasServiceData(array $results): bool
    {
        foreach ($results as $result) {
            if (isset($result['csv_data'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Generar respuesta cuando no hay resultados
     */
    private function generateNoResultsResponse(string $query): string
    {
        return "🐯 No encontré información específica sobre \"{$query}\" en mi base de conocimientos.\n\n" .
               "**¿Qué puedo hacer?**\n" .
               "- Intenta con términos más generales\n" .
               "- Verifica la ortografía\n" .
               "- Pregunta por categorías específicas (servicios, trámites, dependencias)\n\n" .
               "¿En qué más puedo ayudarte?";
    }

    /**
     * Generar respuesta de respaldo
     */
    private function generateFallbackResponse(array $results): string
    {
        $response = "🐯 Encontré la siguiente información:\n\n";

        foreach (array_slice($results, 0, 3) as $idx => $result) {
            $response .= ($idx + 1) . ". " . $this->extractSummary($result['content']) . "\n\n";
        }

        return $response;
    }

    /**
     * Extraer resumen del contenido
     */
    private function extractSummary(string $content): string
    {
        $lines = explode("\n", $content);
        return implode(" ", array_slice($lines, 0, 2));
    }

    /**
     * Calcular confianza de los resultados
     */
    private function calculateConfidence(array $results): float
    {
        if (empty($results)) {
            return 0.0;
        }

        $scores = array_column($results, 'score');
        $avgScore = array_sum($scores) / count($scores);

        // Ajustar por cantidad de resultados
        $quantityBonus = min(0.2, count($results) * 0.05);

        return min(1.0, $avgScore + $quantityBonus);
    }
}

// Controlador para manejar las consultas
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\NotionSearchService;

class NotionSearchController extends Controller
{
    private $notionSearch;

    public function __construct(NotionSearchService $notionSearch)
    {
        $this->notionSearch = $notionSearch;
    }

    /**
     * Procesar consulta tipo Notion
     */
    public function search(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:3',
            'user_type' => 'sometimes|string|in:student,employee,public',
            'files' => 'sometimes|array'
        ]);

        $options = [
            'user_type' => $request->input('user_type', 'public'),
            'uploaded_files' => []
        ];

        // Procesar archivos cargados si existen
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $options['uploaded_files'][] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $file->getRealPath()
                ];
            }
        }

        $result = $this->notionSearch->searchAndRespond(
            $request->input('query'),
            $options
        );

        return response()->json($result);
    }
}

// Comando para probar la búsqueda
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\NotionSearchService;

class TestNotionSearch extends Command
{
    protected $signature = 'ociel:test-notion {query} {--csv=}';
    protected $description = 'Probar búsqueda estilo Notion';

    private $notionSearch;

    public function __construct(NotionSearchService $notionSearch)
    {
        parent::__construct();
        $this->notionSearch = $notionSearch;
    }

    public function handle()
    {
        $query = $this->argument('query');
        $csvFile = $this->option('csv');

        $this->info("🔍 Buscando: {$query}");

        $options = [];
        if ($csvFile && file_exists($csvFile)) {
            $options['uploaded_files'] = [[
                'name' => basename($csvFile),
                'path' => $csvFile
            ]];
            $this->info("📄 Usando archivo CSV: {$csvFile}");
        }

        $result = $this->notionSearch->searchAndRespond($query, $options);

        if ($result['success']) {
            $this->newLine();
            $this->line($result['response']);
            $this->newLine();
            $this->info("Confianza: " . ($result['metadata']['confidence'] * 100) . "%");
            $this->info("Fuentes: " . $result['metadata']['sources']);
        } else {
            $this->error("Error: " . $result['error']);
        }
    }
}
