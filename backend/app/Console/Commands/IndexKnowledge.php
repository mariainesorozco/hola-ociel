<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\QdrantVectorService;
use App\Services\KnowledgeBaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class IndexKnowledge extends Command
{
    protected $signature = 'ociel:index-knowledge
                           {--force : Forzar re-indexación completa}
                           {--batch-size=50 : Tamaño de lote para procesamiento}
                           {--only-new : Solo indexar contenido nuevo}';

    protected $description = 'Indexar la base de conocimientos de Ociel en el motor de búsqueda vectorial';

    private $vectorService;
    private $knowledgeService;

    public function __construct(QdrantVectorService $vectorService, KnowledgeBaseService $knowledgeService)
    {
        parent::__construct();
        $this->vectorService = $vectorService;
        $this->knowledgeService = $knowledgeService;
    }

    public function handle()
    {
        $this->info('🔍 Iniciando indexación de la base de conocimientos...');
        $this->newLine();

        $force = $this->option('force');
        $batchSize = (int) $this->option('batch-size');
        $onlyNew = $this->option('only-new');

        try {
            // 1. Verificar conectividad con Qdrant
            if (!$this->vectorService->isHealthy()) {
                $this->error('❌ No se puede conectar con Qdrant');
                $this->warn('💡 Asegúrate de que Qdrant esté ejecutándose en: ' . config('services.qdrant.url', 'http://localhost:6333'));
                return 1;
            }

            $this->info('✅ Conexión con Qdrant establecida');

            // 2. Asegurar que la colección existe
            if (!$this->vectorService->ensureCollection()) {
                $this->error('❌ No se pudo crear/acceder a la colección de vectores');
                return 1;
            }

            $this->info('✅ Colección de vectores lista');

            // 3. Obtener estadísticas iniciales
            $initialStats = $this->vectorService->getCollectionStats();
            $this->displayStats('Estadísticas iniciales', $initialStats);

            // 4. Determinar qué contenido indexar
            $query = DB::table('knowledge_base')->where('is_active', true);

            if ($onlyNew) {
                // Solo contenido creado/actualizado en las últimas 24 horas
                $query->where('updated_at', '>=', now()->subDay());
                $this->info('📋 Modo: Solo contenido nuevo (últimas 24 horas)');
            } elseif ($force) {
                $this->info('🔄 Modo: Re-indexación completa (forzada)');
            } else {
                $this->info('📋 Modo: Indexación incremental');
            }

            $totalEntries = $query->count();

            if ($totalEntries === 0) {
                $this->warn('⚠️  No hay contenido para indexar');
                return 0;
            }

            $this->info("📊 Total de entradas a procesar: {$totalEntries}");
            $this->newLine();

            // 5. Procesar en lotes
            $processed = 0;
            $errors = 0;
            $updated = 0;

            $progressBar = $this->output->createProgressBar($totalEntries);
            $progressBar->setFormat('verbose');

            $query->orderBy('priority', 'desc')
                  ->orderBy('updated_at', 'desc')
                  ->chunk($batchSize, function ($entries) use (&$processed, &$errors, &$updated, $progressBar, $force) {

                      $results = $this->processEntryBatch($entries, $force);

                      $processed += $results['processed'];
                      $errors += $results['errors'];
                      $updated += $results['updated'];

                      $progressBar->advance(count($entries));
                  });

            $progressBar->finish();
            $this->newLine(2);

            // 6. Estadísticas finales
            $finalStats = $this->vectorService->getCollectionStats();
            $this->displayStats('Estadísticas finales', $finalStats);

            // 7. Resumen del proceso
            $this->displaySummary($processed, $errors, $updated, $totalEntries);

            // 8. Verificar integridad
            $this->verifyIndexIntegrity();

            $this->newLine();
            $this->info('🎉 Indexación completada exitosamente');

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Error durante la indexación: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }
    }

    /**
     * Procesar un lote de entradas
     */
    private function processEntryBatch($entries, bool $force): array
    {
        $results = ['processed' => 0, 'errors' => 0, 'updated' => 0];
        $points = [];

        foreach ($entries as $entry) {
            try {
                // Verificar si ya está indexado (solo si no es forzado)
                if (!$force && $this->isAlreadyIndexed($entry->id)) {
                    $results['processed']++;
                    continue;
                }

                // Crear texto combinado para embedding
                $combinedText = $this->createCombinedText($entry);

                // Generar embedding
                $embedding = $this->generateEmbedding($combinedText);

                if (empty($embedding)) {
                    $this->warn("⚠️  No se pudo generar embedding para: {$entry->title}");
                    $results['errors']++;
                    continue;
                }

                // Preparar punto para Qdrant
                $points[] = [
                    'id' => $entry->id,
                    'vector' => $embedding,
                    'payload' => [
                        'title' => $entry->title,
                        'content_preview' => $this->createContentPreview($entry->content),
                        'category' => $entry->category,
                        'department' => $entry->department,
                        'keywords' => json_decode($entry->keywords ?? '[]', true),
                        'priority' => $entry->priority,
                        'user_types' => json_decode($entry->user_types ?? '[]', true),
                        'indexed_at' => now()->toISOString(),
                        'content_hash' => md5($entry->content)
                    ]
                ];

                $results['processed']++;

            } catch (\Exception $e) {
                $this->warn("⚠️  Error procesando entrada {$entry->id}: {$e->getMessage()}");
                $results['errors']++;
            }
        }

        // Insertar lote en Qdrant
        if (!empty($points)) {
            if ($this->upsertPointsBatch($points)) {
                $results['updated'] += count($points);
            } else {
                $results['errors'] += count($points);
            }
        }

        return $results;
    }

    /**
     * Crear texto combinado para embedding
     */
    private function createCombinedText($entry): string
    {
        $text = $entry->title . "\n\n" . $entry->content;

        // Agregar keywords si existen
        $keywords = json_decode($entry->keywords ?? '[]', true);
        if (!empty($keywords)) {
            $text .= "\n\nPalabras clave: " . implode(', ', $keywords);
        }

        // Agregar metadatos contextuales
        $text .= "\n\nCategoría: " . $entry->category;
        $text .= "\nDepartamento: " . $entry->department;
        $text .= "\nPrioridad: " . $entry->priority;

        return $text;
    }

    /**
     * Crear preview del contenido
     */
    private function createContentPreview(string $content): string
    {
        $preview = strip_tags($content);
        return strlen($preview) > 300 ? substr($preview, 0, 300) . '...' : $preview;
    }

    /**
     * Generar embedding con cache
     */
    private function generateEmbedding(string $text): array
    {
        $cacheKey = 'embedding_' . md5($text);

        // Verificar cache
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // Generar nuevo embedding
        $embedding = app(\App\Services\OllamaService::class)->generateEmbedding($text);

        if (!empty($embedding)) {
            // Cachear por 24 horas
            Cache::put($cacheKey, $embedding, 86400);
        }

        return $embedding;
    }

    /**
     * Verificar si una entrada ya está indexada
     */
    private function isAlreadyIndexed(int $entryId): bool
    {
        // Implementar verificación en Qdrant
        // Por ahora, asumir que no está indexado
        return false;
    }

    /**
     * Insertar lote de puntos en Qdrant
     */
    private function upsertPointsBatch(array $points): bool
    {
        try {
            return app(\App\Services\QdrantVectorService::class)->upsertPoints($points);
        } catch (\Exception $e) {
            $this->error("Error insertando lote: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Mostrar estadísticas
     */
    private function displayStats(string $title, array $stats): void
    {
        $this->info("📊 {$title}:");

        if (empty($stats)) {
            $this->warn('   No hay estadísticas disponibles');
            return;
        }

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Puntos totales', $stats['total_points'] ?? 'N/A'],
                ['Puntos indexados', $stats['indexed_points'] ?? 'N/A'],
                ['Estado de colección', $stats['collection_status'] ?? 'N/A']
            ]
        );
    }

    /**
     * Mostrar resumen del proceso
     */
    private function displaySummary(int $processed, int $errors, int $updated, int $total): void
    {
        $this->newLine();
        $this->info('📈 Resumen del proceso:');

        $successRate = $total > 0 ? round(($processed / $total) * 100, 2) : 0;

        $this->table(
            ['Métrica', 'Cantidad', 'Porcentaje'],
            [
                ['Entradas procesadas', $processed, "{$successRate}%"],
                ['Errores encontrados', $errors, $total > 0 ? round(($errors / $total) * 100, 2) . '%' : '0%'],
                ['Vectores actualizados', $updated, $total > 0 ? round(($updated / $total) * 100, 2) . '%' : '0%'],
                ['Total de entradas', $total, '100%']
            ]
        );

        if ($errors > 0) {
            $this->warn("⚠️  Se encontraron {$errors} errores durante el procesamiento");
            $this->info('💡 Revisa los logs para más detalles sobre los errores');
        }
    }

    /**
     * Verificar integridad del índice
     */
    private function verifyIndexIntegrity(): void
    {
        $this->info('🔍 Verificando integridad del índice...');

        try {
            // Contar entradas en DB vs vectores en Qdrant
            $dbCount = DB::table('knowledge_base')->where('is_active', true)->count();
            $vectorStats = $this->vectorService->getCollectionStats();
            $vectorCount = $vectorStats['total_points'] ?? 0;

            $this->info("📋 Entradas en DB: {$dbCount}");
            $this->info("🔍 Vectores en Qdrant: {$vectorCount}");

            $difference = abs($dbCount - $vectorCount);
            $syncPercentage = $dbCount > 0 ? round((min($dbCount, $vectorCount) / $dbCount) * 100, 2) : 100;

            if ($difference === 0) {
                $this->info("✅ Índice perfectamente sincronizado");
            } elseif ($difference <= ($dbCount * 0.05)) { // Tolerancia del 5%
                $this->info("✅ Índice sincronizado (diferencia menor al 5%)");
            } else {
                $this->warn("⚠️  Diferencia significativa detectada: {$difference} entradas");
                $this->info("💡 Considera ejecutar: php artisan ociel:index-knowledge --force");
            }

            $this->info("📊 Porcentaje de sincronización: {$syncPercentage}%");

        } catch (\Exception $e) {
            $this->warn("⚠️  No se pudo verificar la integridad: {$e->getMessage()}");
        }
    }
}
