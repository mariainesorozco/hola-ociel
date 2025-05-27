<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GhostIntegrationService;
use App\Services\QdrantVectorService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SyncGhostContent extends Command
{
    protected $signature = 'ociel:sync-ghost
                           {--full : Ejecutar sincronización completa}
                           {--incremental : Sincronización incremental desde última vez}
                           {--cleanup : Limpiar contenido huérfano}
                           {--reindex : Reindexar vectores en Qdrant}
                           {--force : Forzar sincronización aunque se haya ejecutado recientemente}';

    protected $description = 'Sincronizar contenido de Ghost CMS con la base de conocimientos de Ociel';

    private $ghostService;
    private $vectorService;

    public function __construct(GhostIntegrationService $ghostService, QdrantVectorService $vectorService = null)
    {
        parent::__construct();
        $this->ghostService = $ghostService;
        $this->vectorService = $vectorService;
    }

    public function handle()
    {
        $this->showHeader();

        // Verificar conexión con Ghost
        if (!$this->checkGhostConnection()) {
            return 1;
        }

        $results = [];

        try {
            // Verificar si se ejecutó recientemente
            if (!$this->option('force') && $this->wasRecentlyExecuted()) {
                $this->warn('⚠️  Sincronización ejecutada recientemente. Usa --force para ejecutar de nuevo.');
                return 0;
            }

            // Ejecutar sincronización según opciones
            if ($this->option('full')) {
                $results = $this->executeFullSync();
            } elseif ($this->option('incremental')) {
                $results = $this->executeIncrementalSync();
            } elseif ($this->option('cleanup')) {
                $results = $this->executeCleanup();
            } else {
                // Sincronización inteligente por defecto
                $results = $this->executeSmartSync();
            }

            // Reindexar vectores si se solicita
            if ($this->option('reindex') && $this->vectorService) {
                $this->reindexVectors();
            }

            // Mostrar resultados
            $this->displayResults($results);

            // Actualizar cache
            Cache::put('ghost_sync_last_run', now(), 3600);

            $this->info('✅ Sincronización completada exitosamente');
            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Error durante la sincronización: ' . $e->getMessage());
            $this->error('📍 Stack trace: ' . $e->getTraceAsString());
            return 1;
        }
    }

    private function showHeader(): void
    {
        $this->info('🔄 Sincronización con Ghost CMS - ¡Hola Ociel!');
        $this->info('================================================');
        $this->newLine();
    }

    private function checkGhostConnection(): bool
    {
        $this->line('🔍 Verificando conexión con Ghost CMS...');

        $health = $this->ghostService->healthCheck();

        if (!$health['ghost_reachable']) {
            $this->error('❌ No se puede conectar con Ghost CMS');
            $this->error('🔧 Verifica la configuración en config/services.php');
            return false;
        }

        if (!$health['api_key_valid']) {
            $this->error('❌ API key de Ghost inválida');
            $this->error('🔧 Verifica GHOST_API_KEY en tu archivo .env');
            return false;
        }

        $this->info('✅ Conexión con Ghost establecida');
        $this->line("📊 Contenido sincronizado actual: {$health['total_synced_content']} entradas");
        $this->newLine();

        return true;
    }

    private function wasRecentlyExecuted(): bool
    {
        $lastRun = Cache::get('ghost_sync_last_run');
        if ($lastRun && $lastRun->diffInMinutes(now()) < 30) {
            $this->line("🕒 Última ejecución: {$lastRun->diffForHumans()}");
            return true;
        }
        return false;
    }

    private function executeFullSync(): array
    {
        $this->info('🚀 Ejecutando sincronización completa...');
        $this->newLine();

        $bar = $this->output->createProgressBar(3);
        $bar->setFormat('debug');

        $bar->setMessage('Sincronizando posts de Ghost...');
        $bar->advance();

        $results = $this->ghostService->fullSync();

        $bar->setMessage('Procesando contenido...');
        $bar->advance();

        sleep(1); // Simular procesamiento

        $bar->setMessage('Finalizando...');
        $bar->advance();

        $bar->finish();
        $this->newLine(2);

        return $results;
    }

    private function executeIncrementalSync(): array
    {
        $this->info('⚡ Ejecutando sincronización incremental...');

        $lastSync = Cache::get('ghost_last_sync');
        if ($lastSync) {
            $this->line("📅 Desde: {$lastSync->format('Y-m-d H:i:s')}");
        } else {
            $this->warn('⚠️  No hay registro de sincronización previa. Ejecutando sincronización completa...');
            return $this->executeFullSync();
        }

        $this->newLine();

        return $this->ghostService->incrementalSync($lastSync);
    }

    private function executeCleanup(): array
    {
        $this->info('🧹 Limpiando contenido huérfano...');
        $this->newLine();

        if (!$this->confirm('¿Estás seguro de que quieres eliminar contenido que ya no existe en Ghost?')) {
            $this->info('❌ Operación cancelada');
            return ['deleted' => 0, 'checked' => 0];
        }

        return $this->ghostService->cleanupOrphanedContent();
    }

    private function executeSmartSync(): array
    {
        $this->info('🧠 Ejecutando sincronización inteligente...');
        $this->newLine();

        $lastSync = Cache::get('ghost_last_sync');

        if (!$lastSync || $lastSync->diffInHours(now()) > 24) {
            $this->line('📊 Ejecutando sincronización completa (más de 24h desde la última)');
            return $this->executeFullSync();
        } else {
            $this->line('⚡ Ejecutando sincronización incremental');
            return $this->executeIncrementalSync();
        }
    }

    private function reindexVectors(): void
    {
        $this->info('🔍 Reindexando vectores en Qdrant...');
        $this->newLine();

        if (!$this->vectorService->isHealthy()) {
            $this->warn('⚠️  Qdrant no está disponible. Saltando reindexación de vectores.');
            return;
        }

        $bar = $this->output->createProgressBar();
        $bar->setMessage('Indexando contenido en Qdrant...');
        $bar->start();

        try {
            $vectorResults = $this->vectorService->indexKnowledgeBase();

            $bar->finish();
            $this->newLine();

            $this->info("✅ Vectores reindexados: {$vectorResults['indexed']} entradas");
            if ($vectorResults['errors'] > 0) {
                $this->warn("⚠️  Errores en indexación: {$vectorResults['errors']}");
            }

        } catch (\Exception $e) {
            $bar->finish();
            $this->newLine();
            $this->error('❌ Error reindexando vectores: ' . $e->getMessage());
        }
    }

    private function displayResults(array $results): void
    {
        $this->newLine();
        $this->info('📊 Resultados de la Sincronización:');
        $this->line('========================================');

        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Posts procesados', $results['posts_processed'] ?? 0],
                ['Páginas procesadas', $results['pages_processed'] ?? 0],
                ['Entradas creadas', $results['entries_created'] ?? 0],
                ['Entradas actualizadas', $results['entries_updated'] ?? 0],
                ['Errores', $results['errors'] ?? 0],
                ['Tiempo de ejecución', ($results['execution_time'] ?? 0) . 's'],
            ]
        );

        // Mostrar estadísticas adicionales
        $this->showAdditionalStats();
    }

    private function showAdditionalStats(): void
    {
        $this->newLine();
        $this->info('📈 Estadísticas de la Base de Conocimientos:');

        $stats = DB::table('knowledge_base')
            ->selectRaw('
                COUNT(*) as total,
                COUNT(CASE WHEN ghost_id IS NOT NULL THEN 1 END) as from_ghost,
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as recent
            ')
            ->first();

        $categoryStats = DB::table('knowledge_base')
            ->selectRaw('category, COUNT(*) as count')
            ->where('is_active', true)
            ->groupBy('category')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get();

        $this->table(
            ['Métrica General', 'Cantidad'],
            [
                ['Total de entradas', $stats->total],
                ['Provenientes de Ghost', $stats->from_ghost],
                ['Entradas activas', $stats->active],
                ['Creadas en últimos 7 días', $stats->recent]
            ]
        );

        if ($categoryStats->isNotEmpty()) {
            $this->newLine();
            $this->info('🏷️  Top 5 Categorías:');

            $categoryData = $categoryStats->map(function ($cat) {
                return [ucfirst($cat->category), $cat->count];
            })->toArray();

            $this->table(['Categoría', 'Entradas'], $categoryData);
        }

        // Verificar salud del sistema
        $this->checkSystemHealth();
    }

    private function checkSystemHealth(): void
    {
        $this->newLine();
        $this->info('🏥 Estado del Sistema:');

        $checks = [
            'Ghost CMS' => $this->ghostService->healthCheck()['status'] === 'ok',
            'Base de Datos' => $this->checkDatabaseHealth(),
            'Qdrant Vector DB' => $this->vectorService ? $this->vectorService->isHealthy() : null,
            'Cache' => $this->checkCacheHealth()
        ];

        foreach ($checks as $component => $status) {
            if ($status === null) {
                $this->line("⚪ {$component}: No configurado");
            } elseif ($status) {
                $this->line("✅ {$component}: Funcionando");
            } else {
                $this->line("❌ {$component}: Con problemas");
            }
        }
    }

    private function checkDatabaseHealth(): bool
    {
        try {
            DB::getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkCacheHealth(): bool
    {
        try {
            Cache::put('health_check', 'ok', 10);
            return Cache::get('health_check') === 'ok';
        } catch (\Exception $e) {
            return false;
        }
    }
}
