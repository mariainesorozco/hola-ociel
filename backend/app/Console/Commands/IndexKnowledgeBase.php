<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\OllamaService;
use App\Services\QdrantVectorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IndexKnowledgeBase extends Command
{
    protected $signature = 'ociel:index-knowledge
                           {--force : Forzar re-indexación de todo el contenido}
                           {--batch-size=10 : Número de elementos a procesar por lote}
                           {--test-only : Solo probar con 5 elementos}';

    protected $description = 'Indexar toda la base de conocimientos con embeddings para búsqueda semántica';

    private $ollamaService;
    private $vectorService;

    public function __construct(OllamaService $ollamaService, QdrantVectorService $vectorService)
    {
        parent::__construct();
        $this->ollamaService = $ollamaService;
        $this->vectorService = $vectorService;
    }

    public function handle()
    {
        $this->info('🚀 Iniciando indexación de la base de conocimientos...');
        $this->newLine();

        // Verificar prerequisitos
        if (!$this->checkPrerequisites()) {
            return 1;
        }

        $force = $this->option('force');
        $batchSize = (int) $this->option('batch-size');
        $testOnly = $this->option('test-only');

        // Obtener contenido a indexar
        $query = DB::table('knowledge_base')->where('is_active', true);

        if ($testOnly) {
            $query->limit(5);
            $this->warn('🧪 Modo de prueba: solo procesando 5 elementos');
        }

        $knowledge = $query->get(['id', 'title', 'content', 'category', 'department', 'keywords']);
        $total = $knowledge->count();

        if ($total === 0) {
            $this->error('❌ No se encontró contenido para indexar');
            return 1;
        }

        $this->info("📊 Total de elementos a procesar: {$total}");
        $this->newLine();

        // Asegurar que la colección existe
        if (!$this->vectorService->ensureCollection()) {
            $this->error('❌ No se pudo crear/acceder a la colección de Qdrant');
            return 1;
        }

        $stats = [
            'processed' => 0,
            'indexed' => 0,
            'errors' => 0,
            'skipped' => 0
        ];

        // Procesar en lotes
        $batches = $knowledge->chunk($batchSize);

        foreach ($batches as $batchIndex => $batch) {
            $this->info("🔄 Procesando lote " . ($batchIndex + 1) . " de " . $batches->count());

            $batchStats = $this->processBatch($batch, $force);

            // Actualizar estadísticas
            $stats['processed'] += $batchStats['processed'];
            $stats['indexed'] += $batchStats['indexed'];
            $stats['errors'] += $batchStats['errors'];
            $stats['skipped'] += $batchStats['skipped'];

            // Mostrar progreso
            $this->line("   ✅ Indexados: {$batchStats['indexed']} | ❌ Errores: {$batchStats['errors']} | ⏭️ Omitidos: {$batchStats['skipped']}");

            // Pausa entre lotes para no sobrecargar
            if (!$testOnly && $batchIndex < $batches->count() - 1) {
                sleep(1);
            }
        }

        $this->newLine();
        $this->displayFinalStats($stats);

        // Verificar estado de la colección
        $this->showCollectionStats();

        return $stats['errors'] > 0 ? 1 : 0;
    }

    private function checkPrerequisites(): bool
    {
        $this->line('🔍 Verificando prerequisitos...');

        // Verificar Ollama
        if (!$this->ollamaService->isHealthy()) {
            $this->error('❌ Ollama no está disponible');
            $this->warn('💡 Ejecuta: ollama serve');
            return false;
        }
        $this->info('✅ Ollama funcionando');

        // Verificar modelos requeridos
        $models = $this->ollamaService->checkRequiredModels();
        if (!$models['embedding']['available']) {
            $this->error('❌ Modelo de embeddings no disponible: ' . $models['embedding']['model']);
            $this->warn('💡 Ejecuta: ollama pull ' . $models['embedding']['model']);
            return false;
        }
        $this->info('✅ Modelo de embeddings disponible');

        // Verificar Qdrant
        if (!$this->vectorService->isHealthy()) {
            $this->error('❌ Qdrant no está disponible');
            $this->warn('💡 Verifica que Qdrant esté ejecutándose en: ' . config('services.qdrant.url', 'http://localhost:6333'));
            return false;
        }
        $this->info('✅ Qdrant funcionando');

        return true;
    }

    private function processBatch($batch, bool $force): array
    {
        $stats = ['processed' => 0, 'indexed' => 0, 'errors' => 0, 'skipped' => 0];

        foreach ($batch as $item) {
            $stats['processed']++;

            try {
                // Verificar si ya está indexado (a menos que sea forzado)
                if (!$force && $this->isAlreadyIndexed($item->id)) {
                    $stats['skipped']++;
                    continue;
                }

                // Crear texto combinado para embedding
                $combinedText = $this->createCombinedText($item);

                // Generar embedding
                $embedding = $this->ollamaService->generateEmbedding($combinedText);

                if (empty($embedding)) {
                    $this->warn("⚠️ No se pudo generar embedding para: {$item->title}");
                    $stats['errors']++;
                    continue;
                }

                // Preparar payload para Qdrant
                $payload = [
                    'title' => $item->title,
                    'content_preview' => substr($item->content, 0, 500),
                    'category' => $item->category,
                    'department' => $item->department,
                    'keywords' => json_decode($item->keywords, true) ?? [],
                    'indexed_at' => now()->toISOString()
                ];

                // Indexar en Qdrant
                $points = [[
                    'id' => $item->id,
                    'vector' => $embedding,
                    'payload' => $payload
                ]];

                if ($this->vectorService->upsertPoints($points)) {
                    $stats['indexed']++;

                    // Marcar como indexado en la base de datos
                    $this->markAsIndexed($item->id);
                } else {
                    $stats['errors']++;
                }

            } catch (\Exception $e) {
                $this->error("❌ Error procesando '{$item->title}': " . $e->getMessage());
                Log::error('Indexing error', [
                    'item_id' => $item->id,
                    'error' => $e->getMessage()
                ]);
                $stats['errors']++;
            }
        }

        return $stats;
    }

    private function createCombinedText($item): string
    {
        $text = $item->title . "\n\n" . $item->content;

        // Agregar keywords si existen
        $keywords = json_decode($item->keywords, true);
        if (!empty($keywords)) {
            $text .= "\n\nPalabras clave: " . implode(', ', $keywords);
        }

        // Agregar metadatos contextuales
        $text .= "\n\nCategoría: " . $item->category;
        $text .= "\nDepartamento: " . $item->department;

        return $text;
    }

    private function isAlreadyIndexed(int $id): bool
    {
        // Verificar si existe en una tabla de control o usar timestamp
        return DB::table('knowledge_base')
            ->where('id', $id)
            ->whereNotNull('updated_at')
            ->where('updated_at', '>', now()->subHours(24))
            ->exists();
    }

    private function markAsIndexed(int $id): void
    {
        DB::table('knowledge_base')
            ->where('id', $id)
            ->update(['updated_at' => now()]);
    }

    private function displayFinalStats(array $stats): void
    {
        $this->info('🎉 Indexación completada!');
        $this->newLine();

        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Total procesados', $stats['processed']],
                ['Exitosamente indexados', $stats['indexed']],
                ['Errores', $stats['errors']],
                ['Omitidos (ya indexados)', $stats['skipped']],
                ['Tasa de éxito', round(($stats['indexed'] / max(1, $stats['processed'])) * 100, 2) . '%']
            ]
        );
    }

    private function showCollectionStats(): void
    {
        try {
            $collectionStats = $this->vectorService->getCollectionStats();

            if (!empty($collectionStats)) {
                $this->newLine();
                $this->info('📊 Estado de la colección vectorial:');
                $this->table(
                    ['Métrica', 'Valor'],
                    [
                        ['Total de puntos', $collectionStats['total_points'] ?? 0],
                        ['Puntos indexados', $collectionStats['indexed_points'] ?? 0],
                        ['Estado', $collectionStats['collection_status'] ?? 'unknown']
                    ]
                );
            }
        } catch (\Exception $e) {
            $this->warn('⚠️ No se pudieron obtener estadísticas de la colección');
        }
    }
}
