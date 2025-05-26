<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\OllamaService;

class DiagnoseOllama extends Command
{
    protected $signature = 'ociel:diagnose-ollama';
    protected $description = 'Diagnosticar el estado de Ollama y los modelos para Hola Ociel';

    private $ollamaService;

    public function __construct(OllamaService $ollamaService)
    {
        parent::__construct();
        $this->ollamaService = $ollamaService;
    }

    public function handle()
    {
        $this->info('🤖 Diagnóstico de Ollama para ¡Hola Ociel!');
        $this->newLine();

        // Verificar conectividad
        $this->line('📡 Verificando conectividad con Ollama...');

        if ($this->ollamaService->isHealthy()) {
            $this->info('✅ Ollama está funcionando correctamente');
        } else {
            $this->error('❌ No se puede conectar con Ollama');
            $this->warn('💡 Asegúrate de que Ollama esté ejecutándose: ollama serve');
            return 1;
        }

        $this->newLine();

        // Verificar modelos disponibles
        $this->line('📚 Verificando modelos disponibles...');
        $models = $this->ollamaService->getAvailableModels();

        if (empty($models)) {
            $this->error('❌ No se encontraron modelos');
            return 1;
        }

        $this->table(
            ['Modelo', 'Tamaño', 'Última Modificación'],
            collect($models)->map(function ($model) {
                return [
                    $model['name'],
                    $this->formatBytes($model['size']),
                    $model['modified_at'] ? date('Y-m-d H:i', strtotime($model['modified_at'])) : 'N/A'
                ];
            })->toArray()
        );

        $this->newLine();

        // Verificar modelos requeridos
        $this->line('🎯 Verificando modelos requeridos para ¡Hola Ociel!...');
        $requiredModels = $this->ollamaService->checkRequiredModels();

        foreach ($requiredModels as $type => $info) {
            $status = $info['available'] ? '✅' : '❌';
            $message = sprintf('%s %s: %s', $status, ucfirst($type), $info['model']);

            if ($info['available']) {
                $this->info($message);
            } else {
                $this->error($message);
                $this->warn("   💡 Descargar con: ollama pull {$info['model']}");
            }
        }

        $this->newLine();

        // Probar generación de respuesta
        $this->line('🧪 Probando generación de respuesta...');

        $testPrompt = "Hola, soy un estudiante de la UAN. ¿Puedes ayudarme con información general?";
        $result = $this->ollamaService->generateOcielResponse($testPrompt, [], 'student');

        if ($result['success']) {
            $this->info('✅ Generación de respuesta exitosa');
            $this->line("📊 Tiempo: {$result['response_time']}ms | Modelo: {$result['model']}");
            $this->newLine();
            $this->line('💬 Respuesta de prueba:');
            $this->line($this->wrapText($result['response'], 70));
        } else {
            $this->error('❌ Error en generación de respuesta: ' . ($result['error'] ?? 'Error desconocido'));
        }

        $this->newLine();

        // Probar embeddings
        $this->line('🔍 Probando generación de embeddings...');
        $embedding = $this->ollamaService->generateEmbedding("Universidad Autónoma de Nayarit");

        if (!empty($embedding)) {
            $this->info('✅ Generación de embeddings exitosa');
            $this->line('📊 Dimensiones del vector: ' . count($embedding));
        } else {
            $this->error('❌ Error en generación de embeddings');
        }

        $this->newLine();

        // Estadísticas finales
        $stats = $this->ollamaService->getUsageStats();
        $this->line('📈 Estadísticas:');
        $this->line("   Estado: " . ($stats['health_status'] ? 'Saludable' : 'Con problemas'));

        $this->newLine();
        $this->info('🎉 Diagnóstico completado - ¡Hola Ociel! está listo para funcionar');

        return 0;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) return '0 B';

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes, 1024));

        return round($bytes / (1024 ** $i), 2) . ' ' . $units[$i];
    }

    private function wrapText(string $text, int $width): string
    {
        return wordwrap($text, $width, "\n   ");
    }
}
