<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\EnhancedQdrantVectorService;
use App\Services\KnowledgeBaseService;
use App\Services\NotionIntegrationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupVectorDbCommand extends Command
{
    protected $signature = 'ociel:cleanup-vector-notion 
                            {--force : Forzar limpieza sin confirmación}
                            {--reindex : Re-indexar contenido de Notion después de limpiar}';

    protected $description = 'Limpiar base de datos vectorial para mantener solo contenido de Notion';

    private $vectorService;
    private $knowledgeService;
    private $notionService;

    public function __construct(
        EnhancedQdrantVectorService $vectorService,
        KnowledgeBaseService $knowledgeService,
        NotionIntegrationService $notionService
    ) {
        parent::__construct();
        $this->vectorService = $vectorService;
        $this->knowledgeService = $knowledgeService;
        $this->notionService = $notionService;
    }

    public function handle()
    {
        $this->info('🧹 Iniciando limpieza de base de datos vectorial para contenido exclusivo de Notion');

        if (!$this->vectorService->isHealthy()) {
            $this->error('❌ El servicio vectorial no está disponible');
            return 1;
        }

        // Mostrar estadísticas actuales
        $this->showCurrentStats();

        // Confirmar acción
        if (!$this->option('force')) {
            if (!$this->confirm('¿Deseas continuar con la limpieza? Esto eliminará todo el contenido no-Notion de la base vectorial.')) {
                $this->info('Operación cancelada');
                return 0;
            }
        }

        try {
            $this->info('📊 Paso 1: Identificando contenido de Notion en la base de datos...');
            
            // Obtener IDs de contenido de Notion
            $notionContentIds = DB::table('knowledge_base')
                ->where('created_by', 'notion_sync')
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();

            $this->info("✅ Encontrados " . count($notionContentIds) . " elementos de Notion en la base de datos");

            if (empty($notionContentIds)) {
                $this->warn('⚠️  No se encontró contenido de Notion. Ejecuta primero: php artisan ociel:sync-notion');
                return 1;
            }

            $this->info('🗑️  Paso 2: Limpiando base vectorial...');

            // Obtener todos los puntos vectoriales
            $response = $this->vectorService->getClient()->post("/collections/ociel_knowledge/points/scroll", [
                'json' => [
                    'limit' => 10000,
                    'with_payload' => true,
                    'with_vector' => false
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            $allPoints = $data['result']['points'] ?? [];

            $toDelete = [];
            $toKeep = [];

            foreach ($allPoints as $point) {
                $pointId = $point['id'];
                $payload = $point['payload'] ?? [];
                
                // Mantener solo puntos que son de Notion
                if (in_array($pointId, $notionContentIds) || 
                    ($payload['source_type'] ?? '') === 'notion' ||
                    isset($payload['notion_id'])) {
                    $toKeep[] = $pointId;
                } else {
                    $toDelete[] = $pointId;
                }
            }

            $this->info("📋 Puntos a conservar (Notion): " . count($toKeep));
            $this->info("🗑️  Puntos a eliminar (No-Notion): " . count($toDelete));

            // Eliminar puntos no-Notion en lotes
            if (!empty($toDelete)) {
                $batchSize = 100;
                $batches = array_chunk($toDelete, $batchSize);
                $deleted = 0;

                $this->output->progressStart(count($batches));

                foreach ($batches as $batch) {
                    try {
                        $this->vectorService->getClient()->post("/collections/ociel_knowledge/points/delete", [
                            'json' => ['points' => $batch]
                        ]);
                        $deleted += count($batch);
                        $this->output->progressAdvance();
                    } catch (\Exception $e) {
                        $this->error("Error eliminando lote: " . $e->getMessage());
                    }
                }

                $this->output->progressFinish();
                $this->info("✅ Eliminados {$deleted} puntos vectoriales no-Notion");
            }

            // Limpiar base de datos también
            $this->info('🗄️  Paso 3: Limpiando base de datos...');
            
            $dbDeleted = DB::table('knowledge_base')
                ->where('created_by', '!=', 'notion_sync')
                ->delete();

            $this->info("✅ Eliminados {$dbDeleted} registros no-Notion de la base de datos");

            // Re-indexar si se solicita
            if ($this->option('reindex')) {
                $this->info('🔄 Paso 4: Re-indexando contenido de Notion...');
                
                $this->call('ociel:sync-notion', [
                    '--force' => true,
                    '--update-existing' => true
                ]);
            }

            // Mostrar estadísticas finales
            $this->showFinalStats();

            $this->info('🎉 Limpieza completada exitosamente');
            $this->info('📝 El sistema ahora responderá únicamente con contenido de Notion');

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Error durante la limpieza: ' . $e->getMessage());
            Log::error('Vector cleanup failed: ' . $e->getMessage());
            return 1;
        }
    }

    private function showCurrentStats()
    {
        $this->info('📊 Estadísticas actuales:');
        
        $totalDb = DB::table('knowledge_base')->where('is_active', true)->count();
        $notionDb = DB::table('knowledge_base')->where('created_by', 'notion_sync')->where('is_active', true)->count();
        
        $this->table(['Tipo', 'Cantidad'], [
            ['Total en DB', $totalDb],
            ['Notion en DB', $notionDb],
            ['No-Notion en DB', $totalDb - $notionDb]
        ]);
    }

    private function showFinalStats()
    {
        $this->info('📊 Estadísticas finales:');
        
        $totalDb = DB::table('knowledge_base')->where('is_active', true)->count();
        $notionDb = DB::table('knowledge_base')->where('created_by', 'notion_sync')->where('is_active', true)->count();
        
        $this->table(['Tipo', 'Cantidad'], [
            ['Total en DB', $totalDb],
            ['Notion en DB', $notionDb],
            ['No-Notion en DB', $totalDb - $notionDb]
        ]);
    }
}