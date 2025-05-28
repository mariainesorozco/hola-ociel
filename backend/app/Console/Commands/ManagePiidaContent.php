<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PiidaScrapingService;
use App\Services\EnhancedQdrantVectorService;
use App\Services\KnowledgeBaseService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ManagePiidaContent extends Command
{
    protected $signature = 'ociel:piida-manage
                           {action : Acción a ejecutar (scrape|index|stats|cleanup|backup|restore)}
                           {--force : Forzar ejecución aunque se haya ejecutado recientemente}
                           {--category= : Categoría específica para procesar}
                           {--backup-file= : Archivo de respaldo para restaurar}
                           {--dry-run : Ejecutar sin hacer cambios reales}';

    protected $description = 'Gestionar contenido de PIIDA: scraping, indexación vectorial y mantenimiento';

    private $piidaService;
    private $qdrantService;
    private $knowledgeService;

    public function __construct(
        PiidaScrapingService $piidaService,
        EnhancedQdrantVectorService $qdrantService,
        KnowledgeBaseService $knowledgeService
    ) {
        parent::__construct();
        $this->piidaService = $piidaService;
        $this->qdrantService = $qdrantService;
        $this->knowledgeService = $knowledgeService;
    }

    public function handle()
    {
        $action = $this->argument('action');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        $this->info("🔧 Gestión de contenido PIIDA - Acción: {$action}");

        if ($dryRun) {
            $this->warn("⚠️  Modo DRY-RUN activado - No se realizarán cambios reales");
        }

        $this->newLine();

        try {
            switch ($action) {
                case 'scrape':
                    return $this->handleScraping($force, $dryRun);

                case 'index':
                    return $this->handleIndexing($force, $dryRun);

                case 'stats':
                    return $this->handleStats();

                case 'cleanup':
                    return $this->handleCleanup($dryRun);

                case 'backup':
                    return $this->handleBackup();

                case 'restore':
                    return $this->handleRestore($dryRun);

                default:
                    $this->error("❌ Acción no válida: {$action}");
                    $this->line("Acciones disponibles: scrape, index, stats, cleanup, backup, restore");
                    return 1;
            }

        } catch (\Exception $e) {
            $this->error("❌ Error ejecutando acción {$action}: " . $e->getMessage());
            $this->line($e->getTraceAsString());
            return 1;
        }
    }

    private function handleScraping(bool $force, bool $dryRun): int
    {
        $this->info("🕷️  Iniciando scraping de contenido PIIDA");

        // Verificar ejecución reciente
        if (!$force && Cache::has('piida_last_scraping')) {
            $lastRun = Cache::get('piida_last_scraping');
            $this->warn("⚠️  Scraping ejecutado recientemente: {$lastRun}");

            if (!$this->confirm('¿Continuar de todos modos?')) {
                return 0;
            }
        }

        if ($dryRun) {
            $this->info("📋 En modo real se ejecutaría:");
            $this->line("   - Scraping de páginas principales de PIIDA");
            $this->line("   - Extracción de servicios, trámites y directorio");
            $this->line("   - Actualización de base de conocimientos");
            $this->line("   - Indexación vectorial automática");
            return 0;
        }

        // Mostrar progreso del scraping
        $this->line("📡 Conectando con PIIDA...");

        $results = $this->piidaService->scrapeAllPiidaContent();

        $this->newLine();
        $this->info("✅ Scraping completado");

        // Mostrar resultados detallados
        $this->displayScrapingResults($results);

        // Actualizar cache
        Cache::put('piida_last_scraping', now()->toISOString(), 86400);

        return 0;
    }

    private function handleIndexing(bool $force, bool $dryRun): int
    {
        $this->info("🔍 Iniciando indexación vectorial de contenido PIIDA");

        // Verificar salud de Qdrant
        if (!$this->qdrantService->isHealthy()) {
            $this->error("❌ Qdrant no está disponible");
            $this->line("💡 Asegúrate de que Qdrant esté ejecutándose en: " . config('services.qdrant.url'));
            return 1;
        }

        $this->info("✅ Qdrant está funcionando correctamente");

        if ($dryRun) {
            $stats = $this->qdrantService->getPiidaCollectionStats();
            $this->info("📊 Estado actual de la colección:");
            $this->displayCollectionStats($stats);

            $this->info("📋 En modo real se ejecutaría:");
            $this->line("   - Inicialización de colección PIIDA");
            $this->line("   - Generación de embeddings para todo el contenido");
            $this->line("   - Indexación en base de datos vectorial");
            $this->line("   - Optimización de la colección");
            return 0;
        }

        // Inicializar colección
        $this->line("🗂️  Inicializando colección PIIDA...");

        if (!$this->qdrantService->initializePiidaCollection()) {
            $this->error("❌ No se pudo inicializar la colección");
            return 1;
        }

        $this->info("✅ Colección inicializada");

        // Ejecutar indexación con barra de progreso
        $this->line("🧠 Generando embeddings e indexando contenido...");

        $results = $this->qdrantService->indexPiidaContent();

        $this->newLine();
        $this->info("✅ Indexación completada");

        // Mostrar resultados
        $this->displayIndexingResults($results);

        return 0;
    }

    private function handleStats(): int
    {
        $this->info("📊 Estadísticas de contenido PIIDA");
        $this->newLine();

        // Estadísticas de scraping
        $scrapingStats = $this->piidaService->getScrapingStats();
        $this->displayScrapingStats($scrapingStats);

        $this->newLine();

        // Estadísticas de base de conocimientos
        $knowledgeStats = $this->knowledgeService->getStats();
        $this->displayKnowledgeStats($knowledgeStats);

        $this->newLine();

        // Estadísticas de Qdrant
        if ($this->qdrantService->isHealthy()) {
            $qdrantStats = $this->qdrantService->getPiidaCollectionStats();
            $this->displayCollectionStats($qdrantStats);
        } else {
            $this->warn("⚠️  Qdrant no está disponible - no se pueden mostrar estadísticas vectoriales");
        }

        return 0;
    }

    private function handleCleanup(bool $dryRun): int
    {
        $this->info("🧹 Iniciando limpieza de contenido obsoleto");

        if ($dryRun) {
            $this->info("📋 En modo real se ejecutaría:");
            $this->line("   - Eliminación de vectores huérfanos en Qdrant");
            $this->line("   - Limpieza de contenido inactivo en base de datos");
            $this->line("   - Optimización de índices");
            return 0;
        }

        // Limpiar vectores obsoletos
        if ($this->qdrantService->isHealthy()) {
            $this->line("🔍 Limpiando vectores obsoletos...");
            $cleanupResults = $this->qdrantService->cleanupObsoleteVectors();

            $this->info("✅ Vectores eliminados: " . $cleanupResults['deleted']);
            if ($cleanupResults['errors'] > 0) {
                $this->warn("⚠️  Errores durante limpieza: " . $cleanupResults['errors']);
            }
        }

        // Limpiar contenido inactivo de la base de datos
        $this->line("🗄️  Limpiando contenido inactivo...");
        $deletedRecords = DB::table('knowledge_base')
            ->where('is_active', false)
            ->where('updated_at', '<', now()->subDays(30))
            ->delete();

        $this->info("✅ Registros de base de datos eliminados: {$deletedRecords}");

        return 0;
    }

    private function handleBackup(): int
    {
        $this->info("💾 Creando respaldo de colección PIIDA");

        if (!$this->qdrantService->isHealthy()) {
            $this->error("❌ Qdrant no está disponible");
            return 1;
        }

        $backupPath = storage_path('backups/qdrant');
        $this->line("📁 Directorio de respaldo: {$backupPath}");

        if ($this->qdrantService->backupCollection($backupPath)) {
            $this->info("✅ Respaldo creado exitosamente");

            // Mostrar archivos de respaldo disponibles
            $backupFiles = glob($backupPath . '/piida_collection_*.json');
            if (!empty($backupFiles)) {
                $this->line("\n📋 Respaldos disponibles:");
                foreach (array_slice($backupFiles, -5) as $file) {
                    $this->line("   " . basename($file));
                }
            }
        } else {
            $this->error("❌ Error creando respaldo");
            return 1;
        }

        return 0;
    }

    private function handleRestore(bool $dryRun): int
    {
        $backupFile = $this->option('backup-file');

        if (!$backupFile) {
            // Mostrar archivos disponibles
            $backupPath = storage_path('backups/qdrant');
            $backupFiles = glob($backupPath . '/piida_collection_*.json');

            if (empty($backupFiles)) {
                $this->error("❌ No se encontraron archivos de respaldo");
                return 1;
            }

            $this->info("📋 Archivos de respaldo disponibles:");
            foreach ($backupFiles as $index => $file) {
                $this->line("   [{$index}] " . basename($file));
            }

            $selection = $this->ask('Selecciona el índice del archivo a restaurar');

            if (!isset($backupFiles[$selection])) {
                $this->error("❌ Selección inválida");
                return 1;
            }

            $backupFile = $backupFiles[$selection];
        }

        $this->info("🔄 Restaurando desde: " . basename($backupFile));

        if ($dryRun) {
            $this->info("📋 En modo real se ejecutaría:");
            $this->line("   - Eliminación de colección actual");
            $this->line("   - Recreación de colección desde respaldo");
            $this->line("   - Restauración de todos los vectores");
            return 0;
        }

        if (!$this->confirm('⚠️  Esto eliminará la colección actual. ¿Continuar?')) {
            return 0;
        }

        if ($this->qdrantService->restoreCollection($backupFile)) {
            $this->info("✅ Colección restaurada exitosamente");
        } else {
            $this->error("❌ Error restaurando colección");
            return 1;
        }

        return 0;
    }

    // Métodos para mostrar resultados

    private function displayScrapingResults(array $results): void
    {
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Total procesado', $results['total_processed']],
                ['Exitosos', $results['successful']],
                ['Errores', $results['errors']],
                ['Actualizados', $results['updated']]
            ]
        );

        if (!empty($results['categories'])) {
            $this->newLine();
            $this->info("📊 Resultados por categoría:");

            $categoryData = [];
            foreach ($results['categories'] as $category => $data) {
                $categoryData[] = [
                    $category,
                    $data['total_processed'] ?? 0,
                    $data['successful'] ?? 0,
                    $data['errors'] ?? 0
                ];
            }

            $this->table(
                ['Categoría', 'Procesados', 'Exitosos', 'Errores'],
                $categoryData
            );
        }
    }

    private function displayIndexingResults(array $results): void
    {
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Indexados', $results['indexed']],
                ['Actualizados', $results['updated'] ?? 0],
                ['Errores', $results['errors']],
                ['Omitidos', $results['skipped'] ?? 0]
            ]
        );
    }

    private function displayScrapingStats(array $stats): void
    {
        $this->info("🕷️  Estadísticas de Scraping:");

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Última ejecución', $stats['last_run'] ?? 'Nunca'],
                ['Total contenido PIIDA', $stats['total_piida_content']],
                ['Actualizaciones recientes', $stats['recent_updates']]
            ]
        );

        if (!empty($stats['by_category'])) {
            $this->newLine();
            $this->line("📊 Contenido por categoría:");

            $categoryData = [];
            foreach ($stats['by_category'] as $category => $count) {
                $categoryName = config("services.piida.categories.{$category}", $category);
                $categoryData[] = [$categoryName, $count];
            }

            $this->table(['Categoría', 'Cantidad'], $categoryData);
        }
    }

    private function displayKnowledgeStats(array $stats): void
    {
        $this->info("🧠 Estadísticas de Base de Conocimientos:");

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Total entradas activas', $stats['total_entries']],
                ['Por prioridad alta', $stats['by_priority']['high'] ?? 0],
                ['Por prioridad media', $stats['by_priority']['medium'] ?? 0],
                ['Por prioridad baja', $stats['by_priority']['low'] ?? 0]
            ]
        );
    }

    private function displayCollectionStats(array $stats): void
    {
        $this->info("🔍 Estadísticas de Colección Vectorial:");

        if (isset($stats['error'])) {
            $this->error("❌ " . $stats['error']);
            return;
        }

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Nombre de colección', $stats['collection_name'] ?? 'N/A'],
                ['Total de puntos', $stats['total_points'] ?? 0],
                ['Puntos indexados', $stats['indexed_points'] ?? 0],
                ['Estado', $stats['collection_status'] ?? 'unknown'],
                ['Tamaño de vector', $stats['vector_size'] ?? 0],
                ['Métrica de distancia', $stats['distance_metric'] ?? 'N/A'],
                ['Estado de salud', $stats['health_status'] ?? 'unknown']
            ]
        );

        if (!empty($stats['categories'])) {
            $this->newLine();
            $this->line("📊 Vectores por categoría:");

            $categoryData = [];
            foreach ($stats['categories'] as $key => $category) {
                $categoryData[] = [
                    $category['name'],
                    $category['count'],
                    $category['percentage'] . '%'
                ];
            }

            $this->table(['Categoría', 'Cantidad', 'Porcentaje'], $categoryData);
        }
    }
}

// ===== COMANDO PARA DIAGNÓSTICO INTEGRAL =====

class DiagnosePiidaSystem extends Command
{
    protected $signature = 'ociel:piida-diagnose
                           {--detailed : Mostrar diagnóstico detallado}
                           {--fix : Intentar corregir problemas encontrados}';

    protected $description = 'Diagnosticar el estado completo del sistema PIIDA de Ociel';

    private $piidaService;
    private $qdrantService;
    private $knowledgeService;
    private $ollamaService;

    public function __construct(
        PiidaScrapingService $piidaService,
        EnhancedQdrantVectorService $qdrantService,
        KnowledgeBaseService $knowledgeService,
        \App\Services\OllamaService $ollamaService
    ) {
        parent::__construct();
        $this->piidaService = $piidaService;
        $this->qdrantService = $qdrantService;
        $this->knowledgeService = $knowledgeService;
        $this->ollamaService = $ollamaService;
    }

    public function handle()
    {
        $this->info('🔍 Diagnóstico integral del sistema PIIDA - ¡Hola Ociel!');
        $this->newLine();

        $detailed = $this->option('detailed');
        $fix = $this->option('fix');
        $issues = [];

        // 1. Verificar conectividad con PIIDA
        $this->checkPiidaConnectivity($issues);

        // 2. Verificar base de conocimientos
        $this->checkKnowledgeBase($issues);

        // 3. Verificar Ollama para embeddings
        $this->checkOllamaService($issues);

        // 4. Verificar Qdrant
        $this->checkQdrantService($issues);

        // 5. Verificar integridad de datos
        $this->checkDataIntegrity($issues, $detailed);

        // 6. Verificar configuración
        $this->checkConfiguration($issues);

        $this->newLine();
        $this->displayDiagnosisResults($issues, $fix);

        return empty($issues) ? 0 : 1;
    }

    private function checkPiidaConnectivity(array &$issues): void
    {
        $this->line('📡 Verificando conectividad con PIIDA...');

        try {
            $client = new \GuzzleHttp\Client(['timeout' => 10]);
            $response = $client->get(config('services.piida.base_url'));

            if ($response->getStatusCode() === 200) {
                $this->info('✅ PIIDA accesible');
            } else {
                $issues[] = [
                    'component' => 'PIIDA',
                    'severity' => 'warning',
                    'message' => 'PIIDA respondió con código: ' . $response->getStatusCode(),
                    'fix' => 'Verificar estado del servidor PIIDA'
                ];
            }
        } catch (\Exception $e) {
            $issues[] = [
                'component' => 'PIIDA',
                'severity' => 'error',
                'message' => 'No se puede conectar con PIIDA: ' . $e->getMessage(),
                'fix' => 'Verificar conectividad de red y URL de PIIDA'
            ];
        }
    }

    private function checkKnowledgeBase(array &$issues): void
    {
        $this->line('🧠 Verificando base de conocimientos...');

        try {
            if ($this->knowledgeService->isHealthy()) {
                $stats = $this->knowledgeService->getStats();

                if ($stats['total_entries'] === 0) {
                    $issues[] = [
                        'component' => 'Knowledge Base',
                        'severity' => 'warning',
                        'message' => 'Base de conocimientos vacía',
                        'fix' => 'Ejecutar: php artisan ociel:piida-manage scrape'
                    ];
                } else {
                    $this->info("✅ Base de conocimientos: {$stats['total_entries']} entradas");
                }
            } else {
                $issues[] = [
                    'component' => 'Knowledge Base',
                    'severity' => 'error',
                    'message' => 'Base de conocimientos no accesible',
                    'fix' => 'Verificar conexión a base de datos'
                ];
            }
        } catch (\Exception $e) {
            $issues[] = [
                'component' => 'Knowledge Base',
                'severity' => 'error',
                'message' => 'Error verificando base de conocimientos: ' . $e->getMessage(),
                'fix' => 'Revisar configuración de base de datos'
            ];
        }
    }

    private function checkOllamaService(array &$issues): void
    {
        $this->line('🤖 Verificando servicio Ollama...');

        try {
            if ($this->ollamaService->isHealthy()) {
                $models = $this->ollamaService->checkRequiredModels();
                $missingModels = array_filter($models, fn($model) => !$model['available']);

                if (empty($missingModels)) {
                    $this->info('✅ Ollama y modelos disponibles');
                } else {
                    foreach ($missingModels as $type => $model) {
                        $issues[] = [
                            'component' => 'Ollama',
                            'severity' => 'error',
                            'message' => "Modelo {$type} no disponible: {$model['model']}",
                            'fix' => "Ejecutar: ollama pull {$model['model']}"
                        ];
                    }
                }
            } else {
                $issues[] = [
                    'component' => 'Ollama',
                    'severity' => 'error',
                    'message' => 'Ollama no está disponible',
                    'fix' => 'Iniciar Ollama: ollama serve'
                ];
            }
        } catch (\Exception $e) {
            $issues[] = [
                'component' => 'Ollama',
                'severity' => 'error',
                'message' => 'Error verificando Ollama: ' . $e->getMessage(),
                'fix' => 'Verificar instalación y configuración de Ollama'
            ];
        }
    }

    private function checkQdrantService(array &$issues): void
    {
        $this->line('🔍 Verificando servicio Qdrant...');

        try {
            if ($this->qdrantService->isHealthy()) {
                $stats = $this->qdrantService->getPiidaCollectionStats();

                if (isset($stats['error'])) {
                    $issues[] = [
                        'component' => 'Qdrant',
                        'severity' => 'warning',
                        'message' => 'Colección PIIDA no disponible',
                        'fix' => 'Ejecutar: php artisan ociel:piida-manage index'
                    ];
                } else {
                    $totalPoints = $stats['total_points'] ?? 0;
                    $indexedPoints = $stats['indexed_points'] ?? 0;

                    $this->info("✅ Qdrant: {$indexedPoints}/{$totalPoints} vectores indexados");

                    if ($totalPoints === 0) {
                        $issues[] = [
                            'component' => 'Qdrant',
                            'severity' => 'warning',
                            'message' => 'Colección vectorial vacía',
                            'fix' => 'Ejecutar: php artisan ociel:piida-manage index'
                        ];
                    }
                }
            } else {
                $issues[] = [
                    'component' => 'Qdrant',
                    'severity' => 'error',
                    'message' => 'Qdrant no está disponible',
                    'fix' => 'Iniciar Qdrant o verificar configuración de URL'
                ];
            }
        } catch (\Exception $e) {
            $issues[] = [
                'component' => 'Qdrant',
                'severity' => 'error',
                'message' => 'Error verificando Qdrant: ' . $e->getMessage(),
                'fix' => 'Verificar instalación y configuración de Qdrant'
            ];
        }
    }

    private function checkDataIntegrity(array &$issues, bool $detailed): void
    {
        $this->line('🔒 Verificando integridad de datos...');

        try {
            if ($this->qdrantService->isHealthy()) {
                $integrity = $this->qdrantService->verifyCollectionIntegrity();

                if ($integrity['integrity_status'] === 'healthy') {
                    $this->info('✅ Integridad de datos verificada');
                } else {
                    foreach ($integrity['issues'] as $issue) {
                        $issues[] = [
                            'component' => 'Data Integrity',
                            'severity' => 'warning',
                            'message' => $issue,
                            'fix' => 'Ejecutar: php artisan ociel:piida-manage cleanup'
                        ];
                    }
                }

                if ($detailed && !empty($integrity['statistics'])) {
                    $this->line("📊 Estadísticas de integridad:");
                    $this->line("   Elementos verificados: " . $integrity['statistics']['total_checked']);
                    $this->line("   Problemas encontrados: " . $integrity['statistics']['issues_found']);
                }
            }
        } catch (\Exception $e) {
            $issues[] = [
                'component' => 'Data Integrity',
                'severity' => 'warning',
                'message' => 'No se pudo verificar integridad: ' . $e->getMessage(),
                'fix' => 'Verificar conectividad con Qdrant'
            ];
        }
    }

    private function checkConfiguration(array &$issues): void
    {
        $this->line('⚙️  Verificando configuración...');

        $requiredConfigs = [
            'services.piida.base_url' => 'URL de PIIDA',
            'services.qdrant.url' => 'URL de Qdrant',
            'services.ollama.url' => 'URL de Ollama',
            'services.ollama.embedding_model' => 'Modelo de embeddings'
        ];

        foreach ($requiredConfigs as $config => $description) {
            if (!config($config)) {
                $issues[] = [
                    'component' => 'Configuration',
                    'severity' => 'error',
                    'message' => "Configuración faltante: {$description}",
                    'fix' => "Configurar {$config} en config/services.php o .env"
                ];
            }
        }

        if (empty(array_filter($issues, fn($issue) => $issue['component'] === 'Configuration'))) {
            $this->info('✅ Configuración verificada');
        }
    }

    private function displayDiagnosisResults(array $issues, bool $fix): void
    {
        if (empty($issues)) {
            $this->info('🎉 ¡Sistema PIIDA completamente saludable!');
            return;
        }

        $errors = array_filter($issues, fn($issue) => $issue['severity'] === 'error');
        $warnings = array_filter($issues, fn($issue) => $issue['severity'] === 'warning');

        if (!empty($errors)) {
            $this->error('❌ Errores críticos encontrados:');
            foreach ($errors as $error) {
                $this->line("   {$error['component']}: {$error['message']}");
                if ($fix) {
                    $this->line("   🔧 Solución: {$error['fix']}");
                }
            }
            $this->newLine();
        }

        if (!empty($warnings)) {
            $this->warn('⚠️  Advertencias encontradas:');
            foreach ($warnings as $warning) {
                $this->line("   {$warning['component']}: {$warning['message']}");
                if ($fix) {
                    $this->line("   🔧 Solución: {$warning['fix']}");
                }
            }
            $this->newLine();
        }

        $this->info('💡 Resumen:');
        $this->line("   Errores críticos: " . count($errors));
        $this->line("   Advertencias: " . count($warnings));

        if (!$fix) {
            $this->line("\n🔧 Usa --fix para ver soluciones sugeridas");
        }
    }
}
