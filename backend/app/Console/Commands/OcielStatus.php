<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\OllamaService;
use App\Services\KnowledgeBaseService;
use App\Services\QdrantVectorService;
use Illuminate\Support\Facades\DB;

class OcielStatus extends Command
{
    protected $signature = 'ociel:status
                           {--detailed : Mostrar información detallada}
                           {--json : Salida en formato JSON}';

    protected $description = 'Mostrar el estado completo del sistema ¡Hola Ociel!';

    private $ollamaService;
    private $knowledgeService;
    private $vectorService;

    public function __construct(
        OllamaService $ollamaService,
        KnowledgeBaseService $knowledgeService,
        QdrantVectorService $vectorService
    ) {
        parent::__construct();
        $this->ollamaService = $ollamaService;
        $this->knowledgeService = $knowledgeService;
        $this->vectorService = $vectorService;
    }

    public function handle()
    {
        $detailed = $this->option('detailed');
        $json = $this->option('json');

        $status = $this->gatherSystemStatus($detailed);

        if ($json) {
            $this->line(json_encode($status, JSON_PRETTY_PRINT));
            return 0;
        }

        $this->displayStatus($status, $detailed);
        return 0;
    }

    private function gatherSystemStatus(bool $detailed): array
    {
        $status = [
            'timestamp' => now()->toISOString(),
            'overall_health' => 'unknown',
            'components' => []
        ];

        // Estado de la base de datos
        try {
            $dbStatus = [
                'status' => 'healthy',
                'connection' => 'active'
            ];

            if ($detailed) {
                $dbStatus['details'] = [
                    'knowledge_entries' => DB::table('knowledge_base')->where('is_active', true)->count(),
                    'total_interactions' => DB::table('chat_interactions')->count(),
                    'interactions_today' => DB::table('chat_interactions')->whereDate('created_at', today())->count(),
                    'departments' => DB::table('departments')->where('is_active', true)->count()
                ];
            }

            $status['components']['database'] = $dbStatus;

        } catch (\Exception $e) {
            $status['components']['database'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }

        // Estado de Ollama
        try {
            $ollamaHealthy = $this->ollamaService->isHealthy();
            $ollamaStatus = [
                'status' => $ollamaHealthy ? 'healthy' : 'unhealthy',
                'service_url' => config('services.ollama.url')
            ];

            if ($detailed && $ollamaHealthy) {
                $models = $this->ollamaService->checkRequiredModels();
                $usage = $this->ollamaService->getUsageStats();

                $ollamaStatus['details'] = [
                    'models' => $models,
                    'usage_stats' => $usage
                ];
            }

            $status['components']['ollama'] = $ollamaStatus;

        } catch (\Exception $e) {
            $status['components']['ollama'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }

        // Estado de Qdrant
        try {
            $qdrantHealthy = $this->vectorService->isHealthy();
            $qdrantStatus = [
                'status' => $qdrantHealthy ? 'healthy' : 'unhealthy',
                'service_url' => config('services.qdrant.url')
            ];

            if ($detailed && $qdrantHealthy) {
                $qdrantStatus['details'] = $this->vectorService->getCollectionStats();
            }

            $status['components']['qdrant'] = $qdrantStatus;

        } catch (\Exception $e) {
            $status['components']['qdrant'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }

        // Estado del Knowledge Base
        try {
            $kbHealthy = $this->knowledgeService->isHealthy();
            $kbStatus = [
                'status' => $kbHealthy ? 'healthy' : 'unhealthy'
            ];

            if ($detailed && $kbHealthy) {
                $kbStatus['details'] = $this->knowledgeService->getStats();
            }

            $status['components']['knowledge_base'] = $kbStatus;

        } catch (\Exception $e) {
            $status['components']['knowledge_base'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }

        // Determinar estado general
        $componentStatuses = collect($status['components'])->pluck('status');
        $allHealthy = $componentStatuses->every(fn($s) => $s === 'healthy');
        $anyUnhealthy = $componentStatuses->contains('unhealthy');

        $status['overall_health'] = $allHealthy ? 'healthy' : ($anyUnhealthy ? 'unhealthy' : 'degraded');

        return $status;
    }

    private function displayStatus(array $status, bool $detailed): void
    {
        $this->info('🤖 Estado del Sistema ¡Hola Ociel!');
        $this->info('Timestamp: ' . $status['timestamp']);
        $this->newLine();

        // Estado general
        $healthIcon = '❓';
        switch ($status['overall_health']) {
            case 'healthy':
                $healthIcon = '✅';
                break;
            case 'unhealthy':
                $healthIcon = '❌';
                break;
            case 'degraded':
                $healthIcon = '⚠️';
                break;
        }

        $this->info("{$healthIcon} Estado General: " . strtoupper($status['overall_health']));
        $this->newLine();

        // Estado de componentes
        $this->info('📊 Estado de Componentes:');
        $this->newLine();

        foreach ($status['components'] as $component => $data) {
            $icon = $data['status'] === 'healthy' ? '✅' : '❌';
            $name = ucfirst(str_replace('_', ' ', $component));

            $this->info("  {$icon} {$name}: " . strtoupper($data['status']));

            if (isset($data['service_url'])) {
                $this->line("     URL: {$data['service_url']}");
            }

            if (isset($data['error'])) {
                $this->error("     Error: {$data['error']}");
            }

            if ($detailed && isset($data['details'])) {
                $this->displayComponentDetails($data['details'], $component);
            }

            $this->newLine();
        }

        // Recomendaciones
        $this->displayRecommendations($status);
    }

    private function displayComponentDetails(array $details, string $component): void
    {
        switch ($component) {
            case 'database':
                if (isset($details['knowledge_entries'])) {
                    $this->line("     Entradas de conocimiento: {$details['knowledge_entries']}");
                    $this->line("     Interacciones hoy: {$details['interactions_today']}");
                    $this->line("     Total interacciones: {$details['total_interactions']}");
                    $this->line("     Departamentos activos: {$details['departments']}");
                }
                break;

            case 'ollama':
                if (isset($details['models'])) {
                    $this->line("     Modelos disponibles:");
                    foreach ($details['models'] as $type => $model) {
                        $status = $model['available'] ? '✅' : '❌';
                        $this->line("       {$status} {$type}: {$model['model']}");
                    }
                }
                break;

            case 'qdrant':
                if (isset($details['total_points'])) {
                    $this->line("     Vectores totales: {$details['total_points']}");
                    $this->line("     Vectores indexados: {$details['indexed_points']}");
                    $this->line("     Estado colección: {$details['collection_status']}");
                }
                break;

            case 'knowledge_base':
                if (isset($details['total_entries'])) {
                    $this->line("     Total entradas: {$details['total_entries']}");
                    $this->line("     Última actualización: {$details['last_updated']}");

                    if (isset($details['by_category'])) {
                        $this->line("     Por categoría:");
                        foreach ($details['by_category'] as $cat => $count) {
                            $this->line("       - {$cat}: {$count}");
                        }
                    }
                }
                break;
        }
    }

    private function displayRecommendations(array $status): void
    {
        $recommendations = [];

        foreach ($status['components'] as $component => $data) {
            if ($data['status'] !== 'healthy') {
                switch ($component) {
                    case 'ollama':
                        $recommendations[] = "🔧 Verificar que Ollama esté ejecutándose: ollama serve";
                        $recommendations[] = "📥 Descargar modelos requeridos: php artisan ociel:diagnose-ollama";
                        break;

                    case 'qdrant':
                        $recommendations[] = "🔧 Iniciar Qdrant: docker run -p 6333:6333 qdrant/qdrant";
                        $recommendations[] = "🔍 Verificar configuración en .env: QDRANT_URL";
                        break;

                    case 'database':
                        $recommendations[] = "🔧 Verificar conexión a base de datos";
                        $recommendations[] = "📊 Ejecutar migraciones: php artisan migrate";
                        break;

                    case 'knowledge_base':
                        $recommendations[] = "📚 Ejecutar seeders: php artisan db:seed";
                        $recommendations[] = "🔍 Indexar conocimiento: php artisan ociel:index-knowledge";
                        break;
                }
            }
        }

        if (!empty($recommendations)) {
            $this->newLine();
            $this->warn('💡 Recomendaciones:');
            foreach (array_unique($recommendations) as $rec) {
                $this->line("   {$rec}");
            }
        }
    }
}
