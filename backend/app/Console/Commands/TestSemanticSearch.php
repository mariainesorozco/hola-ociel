<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\KnowledgeBaseService;
use App\Services\QdrantVectorService;

class TestSemanticSearch extends Command
{
    protected $signature = 'ociel:test-search
                           {query : La consulta a buscar}
                           {--user-type=public : Tipo de usuario (student, employee, public)}
                           {--department= : Departamento específico}
                           {--limit=5 : Número máximo de resultados}
                           {--compare : Comparar búsqueda por palabras clave vs semántica}';

    protected $description = 'Probar búsqueda semántica en la base de conocimientos';

    private $knowledgeService;
    private $vectorService;

    public function __construct(KnowledgeBaseService $knowledgeService, QdrantVectorService $vectorService)
    {
        parent::__construct();
        $this->knowledgeService = $knowledgeService;
        $this->vectorService = $vectorService;
    }

    public function handle()
    {
        $query = $this->argument('query');
        $userType = $this->option('user-type');
        $department = $this->option('department');
        $limit = (int) $this->option('limit');
        $compare = $this->option('compare');

        $this->info("🔍 Probando búsqueda semántica...");
        $this->newLine();

        $this->line("📝 Consulta: \"{$query}\"");
        $this->line("👤 Tipo de usuario: {$userType}");
        if ($department) {
            $this->line("🏢 Departamento: {$department}");
        }
        $this->newLine();

        // Verificar que el sistema esté listo
        if (!$this->checkSystemHealth()) {
            return 1;
        }

        if ($compare) {
            $this->compareSearchMethods($query, $userType, $department, $limit);
        } else {
            $this->performSemanticSearch($query, $userType, $department, $limit);
        }

        return 0;
    }

    private function checkSystemHealth(): bool
    {
        $this->line('🔍 Verificando estado del sistema...');

        // Verificar Qdrant
        if (!$this->vectorService->isHealthy()) {
            $this->error('❌ Qdrant no está disponible');
            return false;
        }

        // Verificar colección
        $stats = $this->vectorService->getCollectionStats();
        if (empty($stats) || ($stats['total_points'] ?? 0) === 0) {
            $this->error('❌ No hay contenido indexado en la base vectorial');
            $this->warn('💡 Ejecuta primero: php artisan ociel:index-knowledge');
            return false;
        }

        $this->info("✅ Sistema listo - {$stats['total_points']} documentos indexados");
        return true;
    }

    private function performSemanticSearch(string $query, string $userType, ?string $department, int $limit): void
    {
        $startTime = microtime(true);

        try {
            // Buscar usando búsqueda semántica
            $results = $this->vectorService->searchSimilarContent(
                $query,
                $limit,
                array_filter([
                    'user_type' => $userType,
                    'department' => $department
                ])
            );

            $searchTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->displayResults($results, $searchTime, 'Búsqueda Semántica');

        } catch (\Exception $e) {
            $this->error('❌ Error en búsqueda semántica: ' . $e->getMessage());
        }
    }

    private function compareSearchMethods(string $query, string $userType, ?string $department, int $limit): void
    {
        $this->info('🆚 Comparando métodos de búsqueda...');
        $this->newLine();

        // 1. Búsqueda por palabras clave
        $this->line('1️⃣ BÚSQUEDA POR PALABRAS CLAVE:');
        $keywordStart = microtime(true);

        try {
            $keywordResults = $this->knowledgeService->searchRelevantContent($query, $userType, $department);
            $keywordTime = round((microtime(true) - $keywordStart) * 1000, 2);

            $this->displaySimpleResults($keywordResults, $keywordTime, 'Palabras Clave');
        } catch (\Exception $e) {
            $this->error('❌ Error en búsqueda por palabras clave: ' . $e->getMessage());
        }

        $this->newLine();
        $this->line('═══════════════════════════════════════════════════════');
        $this->newLine();

        // 2. Búsqueda semántica
        $this->line('2️⃣ BÚSQUEDA SEMÁNTICA:');
        $this->performSemanticSearch($query, $userType, $department, $limit);

        $this->newLine();
        $this->info('💡 La búsqueda semántica debe encontrar contenido relacionado conceptualmente, no solo por palabras exactas.');
    }

    private function displayResults(array $results, float $searchTime, string $method): void
    {
        $this->info("⚡ {$method} completada en {$searchTime}ms");
        $this->newLine();

        if (empty($results)) {
            $this->warn('⚠️ No se encontraron resultados');
            return;
        }

        $this->info("📋 Encontrados " . count($results) . " resultados:");
        $this->newLine();

        foreach ($results as $index => $result) {
            $score = isset($result['score']) ? round($result['score'], 3) : 'N/A';
            $title = $result['title'] ?? 'Sin título';
            $category = $result['category'] ?? 'Sin categoría';
            $department = $result['department'] ?? 'Sin departamento';
            $preview = isset($result['content_preview'])
                ? substr($result['content_preview'], 0, 150) . '...'
                : 'Sin contenido';

            $this->line("📄 " . ($index + 1) . ". {$title}");
            $this->line("   🎯 Relevancia: {$score}");
            $this->line("   🏷️ Categoría: {$category} | Departamento: {$department}");
            $this->line("   📝 Resumen: {$preview}");
            $this->newLine();
        }
    }

    private function displaySimpleResults(array $results, float $searchTime, string $method): void
    {
        $this->info("⚡ {$method} completada en {$searchTime}ms");
        $this->newLine();

        if (empty($results)) {
            $this->warn('⚠️ No se encontraron resultados');
            return;
        }

        $this->info("📋 Encontrados " . count($results) . " resultados:");
        $this->newLine();

        foreach ($results as $index => $content) {
            $preview = substr($content, 0, 200) . '...';
            $this->line("📄 " . ($index + 1) . ". {$preview}");
            $this->newLine();
        }
    }
}
