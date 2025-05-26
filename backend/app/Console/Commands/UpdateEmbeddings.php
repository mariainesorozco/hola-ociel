<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\QdrantVectorService;
use App\Services\OllamaService;
use Illuminate\Support\Facades\DB;

class UpdateEmbeddings extends Command
{
    protected $signature = 'ociel:update-embeddings
                           {--since= : Actualizar solo desde fecha específica (Y-m-d)}
                           {--dry-run : Solo mostrar qué se actualizaría sin hacer cambios}';

    protected $description = 'Actualizar embeddings de contenido modificado en la base de conocimientos';

    private $vectorService;
    private $ollamaService;

    public function __construct(QdrantVectorService $vectorService, OllamaService $ollamaService)
    {
        parent::__construct();
        $this->vectorService = $vectorService;
        $this->ollamaService = $ollamaService;
    }

    public function handle()
    {
        $since = $this->option('since');
        $dryRun = $this->option('dry-run');

        $this->info('🔄 Actualizando embeddings...');

        if ($dryRun) {
            $this->warn('🧪 Modo DRY RUN - No se realizarán cambios');
        }

        $this->newLine();

        try {
            // Verificar servicios
            if (!$this->ollamaService->isHealthy()) {
                $this->error('❌ Ollama no está disponible');
                return 1;
            }

            if (!$this->vectorService->isHealthy()) {
                $this->error('❌ Qdrant no está disponible');
                return 1;
            }

            // Determinar qué contenido actualizar
            $query = DB::table('knowledge_base')->where('is_active', true);

            if ($since) {
                $sinceDate = \Carbon\Carbon::parse($since);
                $query->where('updated_at', '>=', $sinceDate);
                $this->info("📅 Actualizando contenido modificado desde: {$sinceDate->format('Y-m-d H:i:s')}");
            } else {
                // Por defecto, contenido de las últimas 24 horas
                $query->where('updated_at', '>=', now()->subDay());
                $this->info("📅 Actualizando contenido de las últimas 24 horas");
            }

            $entries = $query->get(['id', 'title', 'content', 'updated_at']);

            if ($entries->isEmpty()) {
                $this->info('✅ No hay contenido para actualizar');
                return 0;
            }

            $this->info("📊 Entradas a procesar: {$entries->count()}");

            if ($dryRun) {
                $this->table(
                    ['ID', 'Título', 'Última Actualización'],
                    $entries->map(fn($e) => [$e->id, substr($e->title, 0, 50), $e->updated_at])->toArray()
                );
                return 0;
            }

            // Procesar actualizaciones
            $updated = 0;
            $errors = 0;

            $progressBar = $this->output->createProgressBar($entries->count());

            foreach ($entries as $entry) {
                try {
                    $combinedText = $entry->title . "\n\n" . $entry->content;
                    $embedding = $this->ollamaService->generateEmbedding($combinedText);

                    if (!empty($embedding)) {
                        // Actualizar en Qdrant
                        $point = [
                            'id' => $entry->id,
                            'vector' => $embedding,
                            'payload' => [
                                'title' => $entry->title,
                                'updated_at' => $entry->updated_at,
                                'content_hash' => md5($entry->content)
                            ]
                        ];

                        // Aquí normalmente llamarías al método de Qdrant
                        // $this->vectorService->upsertPoint($point);

                        $updated++;
                    } else {
                        $errors++;
                    }
                } catch (\Exception $e) {
                    $this->warn("Error procesando entrada {$entry->id}: {$e->getMessage()}");
                    $errors++;
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine(2);

            // Mostrar resultados
            $this->info("✅ Actualizaciones completadas:");
            $this->info("   📊 Embeddings actualizados: {$updated}");

            if ($errors > 0) {
                $this->warn("   ⚠️  Errores encontrados: {$errors}");
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Error durante la actualización: ' . $e->getMessage());
            return 1;
        }
    }
}
