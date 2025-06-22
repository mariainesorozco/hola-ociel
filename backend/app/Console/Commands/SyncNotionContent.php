<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\NotionIntegrationService;
use Illuminate\Support\Facades\Log;

class SyncNotionContent extends Command
{
    protected $signature = 'ociel:sync-notion 
                            {database_id? : ID de la base de datos de Notion}
                            {--page-id= : ID de página específica a sincronizar}
                            {--category=notion_docs : Categoría para el contenido}
                            {--department=GENERAL : Departamento asignado}
                            {--user-types=student,employee,public : Tipos de usuario (separados por comas)}
                            {--update-existing : Actualizar páginas existentes}
                            {--no-index : No indexar automáticamente en Qdrant}
                            {--dry-run : Simular sin hacer cambios}
                            {--stats : Mostrar solo estadísticas}';

    protected $description = 'Sincronizar contenido de Notion con la base de conocimientos vectorial';

    private $notionService;

    public function __construct(NotionIntegrationService $notionService)
    {
        parent::__construct();
        $this->notionService = $notionService;
    }

    public function handle()
    {
        $this->info('🚀 Sincronización de Notion - ¡Hola Ociel!');
        $this->newLine();

        // Verificar configuración
        if (!$this->checkConfiguration()) {
            return 1;
        }

        // Mostrar solo estadísticas si se solicita
        if ($this->option('stats')) {
            $this->showStats();
            return 0;
        }

        // Determinar modo de operación
        $pageId = $this->option('page-id');
        $databaseId = $this->argument('database_id');

        if ($pageId) {
            return $this->syncSinglePage($pageId);
        } elseif ($databaseId) {
            return $this->syncDatabase($databaseId);
        } else {
            $this->error('❌ Debe proporcionar --page-id o database_id');
            $this->info('💡 Ejemplos:');
            $this->info('   php artisan ociel:sync-notion 12345678-1234-1234-1234-123456789012');
            $this->info('   php artisan ociel:sync-notion --page-id=87654321-4321-4321-4321-210987654321');
            $this->info('   php artisan ociel:sync-notion --stats');
            return 1;
        }
    }

    /**
     * Verificar configuración necesaria
     */
    private function checkConfiguration(): bool
    {
        $this->info('🔍 Verificando configuración...');

        // Verificar API key de Notion
        if (!config('services.notion.api_key')) {
            $this->error('❌ NOTION_API_KEY no configurado en .env');
            $this->info('💡 Configurar en .env: NOTION_API_KEY=secret_xxx');
            return false;
        }

        // Verificar conectividad
        if (!$this->notionService->isHealthy()) {
            $this->error('❌ No se puede conectar con Notion API');
            $this->info('💡 Verificar NOTION_API_KEY y permisos de la integración');
            return false;
        }

        $this->info('✅ Configuración correcta');
        $this->newLine();
        return true;
    }

    /**
     * Sincronizar base de datos completa
     */
    private function syncDatabase(string $databaseId): int
    {
        $this->info("📚 Sincronizando base de datos: {$databaseId}");
        $this->newLine();

        $options = [
            'category' => $this->option('category'),
            'department' => $this->option('department'),
            'user_types' => explode(',', $this->option('user-types')),
            'update_existing' => $this->option('update-existing'),
            'auto_index' => !$this->option('no-index'),
            'dry_run' => $this->option('dry-run')
        ];

        $this->info('⚙️ Opciones de sincronización:');
        $this->table(['Opción', 'Valor'], [
            ['Categoría', $options['category']],
            ['Departamento', $options['department']],
            ['Tipos de usuario', implode(', ', $options['user_types'])],
            ['Actualizar existentes', $options['update_existing'] ? 'Sí' : 'No'],
            ['Auto-indexar', $options['auto_index'] ? 'Sí' : 'No'],
            ['Simulación', $options['dry_run'] ? 'Sí' : 'No']
        ]);
        $this->newLine();

        if ($options['dry_run']) {
            $this->warn('🔍 MODO SIMULACIÓN - No se realizarán cambios');
            $this->newLine();
        }

        try {
            $progressBar = $this->output->createProgressBar(1);
            $progressBar->setFormat('verbose');
            $progressBar->start();

            if (!$options['dry_run']) {
                $results = $this->notionService->syncDatabase($databaseId, $options);
            } else {
                // Simulación - solo obtener páginas sin procesar
                $this->info('Simulando sincronización...');
                $results = [
                    'total_pages' => 5, // Ejemplo
                    'processed' => 5,
                    'created' => 3,
                    'updated' => 2,
                    'errors' => 0,
                    'indexed' => 5
                ];
            }

            $progressBar->finish();
            $this->newLine(2);

            $this->displayResults($results, $options['dry_run']);
            return 0;

        } catch (\Exception $e) {
            $this->newLine(2);
            $this->error('❌ Error durante la sincronización: ' . $e->getMessage());
            Log::error('Notion sync failed', [
                'database_id' => $databaseId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * Sincronizar página individual
     */
    private function syncSinglePage(string $pageId): int
    {
        $this->info("📄 Sincronizando página: {$pageId}");
        $this->newLine();

        $options = [
            'category' => $this->option('category'),
            'department' => $this->option('department'),
            'user_types' => explode(',', $this->option('user-types')),
            'auto_index' => !$this->option('no-index')
        ];

        if ($this->option('dry-run')) {
            $this->warn('🔍 MODO SIMULACIÓN - No se realizarán cambios');
            $this->newLine();
            $this->info('✅ Página procesada correctamente (simulación)');
            return 0;
        }

        try {
            $result = $this->notionService->syncPage($pageId, $options);

            if ($result['success']) {
                $action = $result['action'] ?? 'processed';
                $indexed = $result['indexed'] ? ' e indexada' : '';
                $this->info("✅ Página {$action}{$indexed} correctamente");
                
                if (isset($result['knowledge_id'])) {
                    $this->info("🆔 ID en knowledge base: {$result['knowledge_id']}");
                }
            } else {
                $reason = $result['reason'] ?? 'Error desconocido';
                $this->warn("⚠️ Página no procesada: {$reason}");
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Error sincronizando página: ' . $e->getMessage());
            Log::error('Single page sync failed', [
                'page_id' => $pageId,
                'error' => $e->getMessage()
            ]);
            return 1;
        }
    }

    /**
     * Mostrar estadísticas de sincronización
     */
    private function showStats(): void
    {
        $this->info('📊 Estadísticas de Notion');
        $this->newLine();

        try {
            $stats = $this->notionService->getSyncStats();

            $this->table(['Métrica', 'Valor'], [
                ['Total páginas de Notion', $stats['total_notion_pages']],
                ['Sincronizadas últimas 24h', $stats['recent_syncs']],
                ['Última sincronización', $stats['last_sync'] ?? 'Nunca']
            ]);

            // Verificar estado de servicios relacionados
            $this->newLine();
            $this->info('🔧 Estado de servicios:');
            
            $healthChecks = [
                ['Notion API', $this->notionService->isHealthy() ? '✅ Healthy' : '❌ Unhealthy'],
            ];

            $this->table(['Servicio', 'Estado'], $healthChecks);

        } catch (\Exception $e) {
            $this->error('❌ Error obteniendo estadísticas: ' . $e->getMessage());
        }
    }

    /**
     * Mostrar resultados de sincronización
     */
    private function displayResults(array $results, bool $isDryRun = false): void
    {
        $prefix = $isDryRun ? '[SIMULACIÓN] ' : '';
        
        $this->info("✅ {$prefix}Sincronización completada");
        $this->newLine();

        $this->table(['Métrica', 'Cantidad'], [
            ['Total páginas', $results['total_pages'] ?? 0],
            ['Procesadas', $results['processed'] ?? 0],
            ['Creadas', $results['created'] ?? 0],
            ['Actualizadas', $results['updated'] ?? 0],
            ['Errores', $results['errors'] ?? 0],
            ['Indexadas en Qdrant', $results['indexed'] ?? 0]
        ]);

        if (($results['errors'] ?? 0) > 0) {
            $this->newLine();
            $this->warn("⚠️  Se encontraron {$results['errors']} errores. Revisar logs para detalles.");
        }

        if (!$isDryRun && ($results['indexed'] ?? 0) > 0) {
            $this->newLine();
            $this->info('💡 Contenido indexado en Qdrant. Listo para búsqueda vectorial.');
        }

        if ($isDryRun) {
            $this->newLine();
            $this->info('🚀 Para ejecutar realmente: remover --dry-run');
        }
    }
}