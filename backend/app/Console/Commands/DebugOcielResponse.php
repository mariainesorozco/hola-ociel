<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\EnhancedQdrantVectorService;
use App\Services\EnhancedPromptService;

class DebugOcielResponse extends Command
{
    protected $signature = 'ociel:debug-response {message}';
    protected $description = 'Debug completo de respuesta de Ociel';

    public function handle()
    {
        $message = $this->argument('message');
        
        $this->info("🔍 Debug de consulta: {$message}");
        $this->newLine();

        // 1. Verificar búsqueda en Qdrant
        $this->info('1. Búsqueda en Qdrant...');
        $qdrantService = app(EnhancedQdrantVectorService::class);
        $results = $qdrantService->searchNotionServices($message, [], 5, 0.65);
        
        $this->line("Resultados encontrados: " . count($results));
        foreach ($results as $i => $result) {
            $this->line("Resultado " . ($i + 1) . ":");
            if (is_array($result)) {
                $this->line("  Título: " . ($result['title'] ?? 'N/A'));
                $this->line("  Score: " . ($result['score'] ?? 'N/A'));
                $this->line("  Contenido: " . substr($result['content_preview'] ?? '', 0, 100) . "...");
            } else {
                $this->line("  Contenido: " . substr($result, 0, 100) . "...");
            }
            $this->newLine();
        }

        // 2. Verificar respuesta completa
        $this->info('2. Generando respuesta completa...');
        $promptService = app(EnhancedPromptService::class);
        $response = $promptService->generateProfessionalResponse($message, 'public');
        
        $this->line("Success: " . ($response['success'] ? 'true' : 'false'));
        $this->line("Service: " . ($response['service_used'] ?? 'unknown'));
        $this->line("Confidence: " . round(($response['confidence'] ?? 0) * 100, 1) . "%");
        $this->newLine();
        $this->line("Respuesta:");
        $this->line($response['response'] ?? 'No response');

        return 0;
    }
}