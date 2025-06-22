<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\QdrantVectorService;

class DebugQdrant extends Command
{
    protected $signature = 'ociel:debug-qdrant
                           {--delete-collection : Eliminar la colección existente}
                           {--create-test : Crear colección de prueba}';

    protected $description = 'Debug de configuración y conexión con Qdrant';

    private $vectorService;

    public function __construct(QdrantVectorService $vectorService)
    {
        parent::__construct();
        $this->vectorService = $vectorService;
    }

    public function handle()
    {
        $this->info('🔍 Debug de Qdrant para ¡Hola Ociel!');
        $this->newLine();

        // 1. Verificar configuración
        $this->checkConfiguration();

        // 2. Verificar conectividad
        $this->checkConnectivity();

        // 3. Listar colecciones existentes
        $this->listExistingCollections();

        // 4. Operaciones especiales
        if ($this->option('delete-collection')) {
            $this->deleteCollection();
        }

        if ($this->option('create-test')) {
            $this->createTestCollection();
        }

        // 5. Verificar creación de colección
        $this->testCollectionCreation();

        return 0;
    }

    private function checkConfiguration(): void
    {
        $this->line('📋 1. Verificando configuración...');

        $config = $this->vectorService->debugConfiguration();

        $this->table(
            ['Parámetro', 'Valor'],
            [
                ['URL Base', $config['base_url']],
                ['Nombre de Colección', $config['collection_name']],
                ['Tamaño de Vector', $config['vector_size']],
                ['Timeout', $config['timeout'] ?? 'No definido'],
                ['Health Check', $config['health_check'] ? '✅ OK' : '❌ FAIL']
            ]
        );

        $this->newLine();
    }

    private function checkConnectivity(): void
    {
        $this->line('🌐 2. Verificando conectividad...');

        if ($this->vectorService->isHealthy()) {
            $this->info('✅ Qdrant está accesible');
        } else {
            $this->error('❌ No se puede conectar con Qdrant');
            $this->warn('💡 Verifica que Qdrant esté ejecutándose:');
            $this->warn('   docker run -p 6333:6333 qdrant/qdrant');
            return;
        }

        $this->newLine();
    }

    private function listExistingCollections(): void
    {
        $this->line('📚 3. Listando colecciones existentes...');

        $collections = $this->vectorService->listCollections();

        if (empty($collections)) {
            $this->warn('⚠️ No hay colecciones existentes');
        } else {
            $this->info('📋 Colecciones encontradas:');
            foreach ($collections as $collection) {
                $this->line("   • {$collection['name']}");
            }
        }

        $this->newLine();
    }

    private function deleteCollection(): void
    {
        $this->line('🗑️ Eliminando colección existente...');

        if ($this->vectorService->deleteCollection()) {
            $this->info('✅ Colección eliminada exitosamente');
        } else {
            $this->warn('⚠️ No se pudo eliminar la colección (puede que no exista)');
        }

        $this->newLine();
    }

    private function createTestCollection(): void
    {
        $this->line('🧪 Creando colección de prueba...');

        if ($this->vectorService->ensureCollection()) {
            $this->info('✅ Colección de prueba creada exitosamente');

            // Obtener estadísticas
            $stats = $this->vectorService->getCollectionStats();
            $this->table(
                ['Métrica', 'Valor'],
                [
                    ['Nombre', $stats['collection_name']],
                    ['Estado', $stats['collection_status']],
                    ['Puntos Totales', $stats['total_points']],
                    ['Puntos Indexados', $stats['indexed_points']],
                    ['Tamaño Vector', $stats['vector_size']]
                ]
            );
        } else {
            $this->error('❌ No se pudo crear la colección de prueba');
        }

        $this->newLine();
    }

    private function testCollectionCreation(): void
    {
        $this->line('🧪 4. Probando creación de colección...');

        try {
            $result = $this->vectorService->ensureCollection();

            if ($result) {
                $this->info('✅ Colección creada/verificada exitosamente');

                // Mostrar estadísticas de la colección
                $stats = $this->vectorService->getCollectionStats();
                $this->info("📊 Estado: {$stats['collection_status']}");
                $this->info("📈 Puntos: {$stats['total_points']}");
            } else {
                $this->error('❌ No se pudo crear/verificar la colección');
                $this->suggestSolutions();
            }

        } catch (\Exception $e) {
            $this->error('❌ Error durante la creación de colección:');
            $this->error("   {$e->getMessage()}");
            $this->suggestSolutions();
        }
    }

    private function suggestSolutions(): void
    {
        $this->newLine();
        $this->warn('💡 Posibles soluciones:');
        $this->warn('   1. Verificar que Qdrant esté ejecutándose:');
        $this->warn('      docker run -p 6333:6333 qdrant/qdrant');
        $this->newLine();
        $this->warn('   2. Verificar variables de entorno:');
        $this->warn('      QDRANT_URL=http://localhost:6333');
        $this->warn('      QDRANT_COLLECTION=ociel_knowledge');
        $this->warn('      QDRANT_VECTOR_SIZE=768');
        $this->newLine();
        $this->warn('   3. Verificar logs de Laravel:');
        $this->warn('      tail -f storage/logs/laravel.log | grep -i qdrant');
        $this->newLine();
        $this->warn('   4. Probar conectividad manual:');
        $this->warn('      curl http://localhost:6333/');
        $this->newLine();
        $this->warn('   5. Recrear colección:');
        $this->warn('      php artisan ociel:debug-qdrant --delete-collection --create-test');
    }
}
