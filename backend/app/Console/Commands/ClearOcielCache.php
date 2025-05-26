<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ClearOcielCache extends Command
{
    protected $signature = 'ociel:clear-cache
                           {--type=all : Tipo de cache a limpiar (all, knowledge, embeddings, interactions)}
                           {--force : Forzar limpieza sin confirmación}';

    protected $description = 'Limpiar cache del sistema Ociel';

    public function handle()
    {
        $type = $this->option('type');
        $force = $this->option('force');

        if (!$force) {
            if (!$this->confirm("¿Estás seguro de que quieres limpiar el cache '{$type}'?")) {
                $this->info('Operación cancelada.');
                return 0;
            }
        }

        $this->info("🧹 Limpiando cache: {$type}");

        try {
            switch ($type) {
                case 'knowledge':
                    $this->clearKnowledgeCache();
                    break;
                case 'embeddings':
                    $this->clearEmbeddingsCache();
                    break;
                case 'interactions':
                    $this->clearInteractionsCache();
                    break;
                case 'all':
                default:
                    $this->clearAllCache();
                    break;
            }

            $this->info('✅ Cache limpiado exitosamente');
            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Error limpiando cache: ' . $e->getMessage());
            return 1;
        }
    }

    private function clearKnowledgeCache(): void
    {
        $keys = Cache::keys('knowledge_search_*');
        foreach ($keys as $key) {
            Cache::forget($key);
        }
        $this->info("   🗑️  " . count($keys) . " entradas de búsqueda eliminadas");
    }

    private function clearEmbeddingsCache(): void
    {
        $keys = Cache::keys('embedding_*');
        foreach ($keys as $key) {
            Cache::forget($key);
        }
        $this->info("   🧠  " . count($keys) . " embeddings eliminados");
    }

    private function clearInteractionsCache(): void
    {
        $keys = Cache::keys('last_scraping*');
        foreach ($keys as $key) {
            Cache::forget($key);
        }
        $this->info("   💬  Cache de interacciones eliminado");
    }

    private function clearAllCache(): void
    {
        Cache::flush();
        $this->info("   🧹  Todo el cache eliminado");
    }
}
