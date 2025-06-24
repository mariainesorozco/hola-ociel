<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\OllamaService;
use App\Services\GeminiService;

class TestSolarModel extends Command
{
    protected $signature = 'ociel:test-solar';
    protected $description = 'Probar el modelo solar:10.7b y el sistema de fallback con Gemini';

    public function handle()
    {
        $this->info('🚀 Probando modelo solar:10.7b y sistema de fallback...');
        $this->newLine();

        // Test Ollama con solar:10.7b
        $this->info('1. Verificando Ollama con modelo solar:10.7b...');
        
        $ollamaService = app(OllamaService::class);
        
        if ($ollamaService->isHealthy()) {
            $this->info('✅ Ollama está disponible');
            
            // Verificar modelos disponibles
            $models = $ollamaService->getAvailableModels();
            $solarAvailable = collect($models)->contains(fn($model) => str_contains($model['name'], 'solar'));
            
            if ($solarAvailable) {
                $this->info('✅ Modelo solar:10.7b encontrado');
                
                // Test de respuesta
                $testMessage = "Hola, ¿puedes ayudarme con información de la UAN?";
                $response = $ollamaService->generateOcielResponse($testMessage, [], 'student');
                
                if ($response['success']) {
                    $this->info('✅ Respuesta exitosa con solar:10.7b');
                    $this->line('📝 Respuesta: ' . substr($response['response'], 0, 100) . '...');
                    $this->line('⏱️  Tiempo: ' . ($response['response_time'] ?? 0) . 'ms');
                } else {
                    $this->error('❌ Error generando respuesta: ' . ($response['error'] ?? 'unknown'));
                }
            } else {
                $this->error('❌ Modelo solar:10.7b no encontrado en Ollama');
                $this->line('📋 Modelos disponibles:');
                foreach ($models as $model) {
                    $this->line('   - ' . $model['name']);
                }
                $this->newLine();
                $this->warn('💡 Para instalar solar:10.7b ejecuta: ollama pull solar:10.7b');
            }
        } else {
            $this->error('❌ Ollama no está disponible');
        }

        $this->newLine();

        // Test Gemini
        $this->info('2. Verificando Gemini AI...');
        
        $geminiService = app(GeminiService::class);
        
        if ($geminiService->isEnabled()) {
            $this->info('✅ Gemini está habilitado');
            
            if ($geminiService->isHealthy()) {
                $this->info('✅ Gemini está funcionando');
                
                // Test de respuesta
                $testMessage = "Hola, ¿puedes ayudarme con información de la UAN?";
                $response = $geminiService->generateOcielResponse($testMessage, [], 'student');
                
                if ($response['success']) {
                    $this->info('✅ Respuesta exitosa con Gemini');
                    $this->line('📝 Respuesta: ' . substr($response['response'], 0, 100) . '...');
                    $this->line('⏱️  Tiempo: ' . ($response['response_time'] ?? 0) . 'ms');
                } else {
                    $this->error('❌ Error generando respuesta: ' . ($response['error'] ?? 'unknown'));
                }
            } else {
                $this->error('❌ Gemini no está funcionando correctamente');
            }
        } else {
            $this->error('❌ Gemini no está habilitado');
            $this->line('💡 Para habilitar Gemini, configura GEMINI_ENABLED=true en .env');
        }

        $this->newLine();

        // Test del sistema de fallback
        $this->info('3. Probando sistema de fallback completo...');
        
        $promptService = app(\App\Services\EnhancedPromptService::class);
        $testMessage = "¿Qué servicios ofrece la UAN para estudiantes?";
        
        $response = $promptService->generateProfessionalResponse($testMessage, 'student');
        
        if ($response['success']) {
            $this->info('✅ Sistema de fallback funcionando');
            $this->line('🔧 Servicio usado: ' . ($response['service_used'] ?? 'unknown'));
            $this->line('🎯 Confianza: ' . round(($response['confidence'] ?? 0) * 100, 1) . '%');
            $this->line('📝 Respuesta: ' . substr($response['response'], 0, 150) . '...');
        } else {
            $this->error('❌ Sistema de fallback falló');
        }

        $this->newLine();
        $this->info('🏁 Pruebas completadas');

        // Recomendaciones
        $this->newLine();
        $this->info('📋 Recomendaciones para optimizar Ociel:');
        $this->line('1. Asegúrate de que ollama pull solar:10.7b esté ejecutado');
        $this->line('2. Verifica que la API key de Gemini sea válida');
        $this->line('3. Ejecuta: php artisan config:cache para aplicar cambios');
        $this->line('4. Reinicia el servidor Laravel: php artisan serve');

        return 0;
    }
}