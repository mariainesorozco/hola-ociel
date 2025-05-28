<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PiidaScrapingService;
use App\Services\EnhancedQdrantVectorService;
use App\Services\KnowledgeBaseService;
use App\Services\OllamaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DiagnosePiidaSystem extends Command
{
    protected $signature = 'ociel:piida-diagnose
                           {--detailed : Mostrar diagnóstico detallado}
                           {--fix : Intentar corregir problemas encontrados}';

    protected $description = 'Diagnosticar el estado completo del sistema PIIDA de Ociel';

    private $piidaService;
    private $qdrantService;
    private $knowledgeService;
    private $ollamaService;

    public function __construct(
        PiidaScrapingService $piidaService = null,
        EnhancedQdrantVectorService $qdrantService = null,
        KnowledgeBaseService $knowledgeService = null,
        OllamaService $ollamaService = null
    ) {
        parent::__construct();
        $this->piidaService = $piidaService;
        $this->qdrantService = $qdrantService;
        $this->knowledgeService = $knowledgeService;
        $this->ollamaService = $ollamaService;
    }

    public function handle()
    {
        $this->info('🔍 Diagnóstico integral del sistema PIIDA - ¡Hola Ociel!');
        $this->newLine();

        $detailed = $this->option('detailed');
        $fix = $this->option('fix');
        $issues = [];

        // 1. Verificar conectividad con PIIDA
        $this->checkPiidaConnectivity($issues);

        // 2. Verificar base de conocimientos
        $this->checkKnowledgeBase($issues);

        // 3. Verificar Ollama para embeddings
        $this->checkOllamaService($issues);

        // 4. Verificar Qdrant
        $this->checkQdrantService($issues);

        // 5. Verificar integridad de datos
        $this->checkDataIntegrity($issues, $detailed);

        // 6. Verificar configuración
        $this->checkConfiguration($issues);

        $this->newLine();
        $this->displayDiagnosisResults($issues, $fix);

        return empty($issues) ? 0 : 1;
    }

    private function checkPiidaConnectivity(array &$issues): void
    {
        $this->line('📡 Verificando conectividad con PIIDA...');

        try {
            $client = new \GuzzleHttp\Client(['timeout' => 10]);
            $response = $client->get(config('services.piida.base_url', 'https://piida.uan.mx'));

            if ($response->getStatusCode() === 200) {
                $this->info('✅ PIIDA accesible');
            } else {
                $issues[] = [
                    'component' => 'PIIDA',
                    'severity' => 'warning',
                    'message' => 'PIIDA respondió con código: ' . $response->getStatusCode(),
                    'fix' => 'Verificar estado del servidor PIIDA'
                ];
            }
        } catch (\Exception $e) {
            $issues[] = [
                'component' => 'PIIDA',
                'severity' => 'error',
                'message' => 'No se puede conectar con PIIDA: ' . $e->getMessage(),
                'fix' => 'Verificar conectividad de red y URL de PIIDA'
            ];
        }
    }

    private function checkKnowledgeBase(array &$issues): void
    {
        $this->line('🧠 Verificando base de conocimientos...');

        try {
            // Verificar conexión directa a base de datos
            $connection = DB::getPdo();

            if ($connection) {
                $this->info('✅ Conexión a base de datos establecida');

                // Verificar tablas necesarias
                $tables = ['knowledge_base', 'chat_interactions'];
                foreach ($tables as $table) {
                    if (Schema::hasTable($table)) {
                        $this->info("✅ Tabla {$table} existe");
                    } else {
                        $issues[] = [
                            'component' => 'Database',
                            'severity' => 'error',
                            'message' => "Tabla {$table} no existe",
                            'fix' => 'Ejecutar: php artisan migrate'
                        ];
                    }
                }

                // Verificar contenido
                $totalEntries = DB::table('knowledge_base')->where('is_active', true)->count();

                if ($totalEntries === 0) {
                    $issues[] = [
                        'component' => 'Knowledge Base',
                        'severity' => 'warning',
                        'message' => 'Base de conocimientos vacía',
                        'fix' => 'Ejecutar: php artisan ociel:piida-manage scrape'
                    ];
                } else {
                    $this->info("✅ Base de conocimientos: {$totalEntries} entradas");
                }
            }

        } catch (\Exception $e) {
            $issues[] = [
                'component' => 'Knowledge Base',
                'severity' => 'error',
                'message' => 'Error verificando base de conocimientos: ' . $e->getMessage(),
                'fix' => 'Revisar configuración de base de datos'
            ];
        }
    }

    private function checkOllamaService(array &$issues): void
    {
        $this->line('🤖 Verificando servicio Ollama...');

        try {
            $ollamaUrl = config('services.ollama.url', 'http://localhost:11434');

            // Verificar conectividad básica
            $client = new \GuzzleHttp\Client(['timeout' => 10]);
            $response = $client->get($ollamaUrl);

            if ($response->getStatusCode() === 200) {
                $this->info('✅ Ollama está disponible');

                // Verificar modelos específicos
                $requiredModels = [
                    'primary' => config('services.ollama.primary_model', 'mistral:7b'),
                    'secondary' => config('services.ollama.secondary_model', 'llama3.2:3b'),
                    'embedding' => config('services.ollama.embedding_model', 'nomic-embed-text')
                ];

                try {
                    $tagsResponse = $client->get($ollamaUrl . '/api/tags');
                    $tags = json_decode($tagsResponse->getBody(), true);
                    $availableModels = collect($tags['models'] ?? [])->pluck('name')->toArray();

                    foreach ($requiredModels as $type => $model) {
                        if (in_array($model, $availableModels)) {
                            $this->info("✅ Modelo {$type} disponible: {$model}");
                        } else {
                            $issues[] = [
                                'component' => 'Ollama',
                                'severity' => 'error',
                                'message' => "Modelo {$type} no disponible: {$model}",
                                'fix' => "Ejecutar: ollama pull {$model}"
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    $issues[] = [
                        'component' => 'Ollama',
                        'severity' => 'warning',
                        'message' => 'No se pudieron verificar los modelos: ' . $e->getMessage(),
                        'fix' => 'Verificar que Ollama esté funcionando correctamente'
                    ];
                }

            } else {
                $issues[] = [
                    'component' => 'Ollama',
                    'severity' => 'error',
                    'message' => 'Ollama respondió con código: ' . $response->getStatusCode(),
                    'fix' => 'Verificar configuración de Ollama'
                ];
            }

        } catch (\Exception $e) {
            $issues[] = [
                'component' => 'Ollama',
                'severity' => 'error',
                'message' => 'Ollama no está disponible: ' . $e->getMessage(),
                'fix' => 'Iniciar Ollama: ollama serve'
            ];
        }
    }

    private function checkQdrantService(array &$issues): void
    {
        $this->line('🔍 Verificando servicio Qdrant...');

        try {
            $qdrantUrl = config('services.qdrant.url', 'http://localhost:6333');
            $client = new \GuzzleHttp\Client(['timeout' => 10]);

            $response = $client->get($qdrantUrl);

            if ($response->getStatusCode() === 200) {
                $this->info('✅ Qdrant está disponible');

                // Verificar colección específica
                $collectionName = config('services.qdrant.collection', 'uan_piida_knowledge');

                try {
                    $collectionResponse = $client->get("{$qdrantUrl}/collections/{$collectionName}");
                    $collectionData = json_decode($collectionResponse->getBody(), true);

                    if (isset($collectionData['result'])) {
                        $pointsCount = $collectionData['result']['points_count'] ?? 0;
                        $indexedCount = $collectionData['result']['indexed_vectors_count'] ?? 0;
                        $status = $collectionData['result']['status'] ?? 'unknown';

                        $this->info("✅ Colección {$collectionName}: {$indexedCount}/{$pointsCount} vectores indexados");

                        if ($pointsCount === 0) {
                            $issues[] = [
                                'component' => 'Qdrant',
                                'severity' => 'warning',
                                'message' => 'Colección vectorial vacía',
                                'fix' => 'Ejecutar: php artisan ociel:piida-manage index'
                            ];
                        }

                        if ($status !== 'green') {
                            $issues[] = [
                                'component' => 'Qdrant',
                                'severity' => 'warning',
                                'message' => "Estado de colección: {$status}",
                                'fix' => 'Verificar salud de la colección Qdrant'
                            ];
                        }
                    }

                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), '404') !== false) {
                        $issues[] = [
                            'component' => 'Qdrant',
                            'severity' => 'warning',
                            'message' => "Colección {$collectionName} no existe",
                            'fix' => 'Ejecutar: php artisan ociel:piida-manage index'
                        ];
                    } else {
                        $issues[] = [
                            'component' => 'Qdrant',
                            'severity' => 'warning',
                            'message' => 'Error verificando colección: ' . $e->getMessage(),
                            'fix' => 'Verificar configuración de Qdrant'
                        ];
                    }
                }

            } else {
                $issues[] = [
                    'component' => 'Qdrant',
                    'severity' => 'error',
                    'message' => 'Qdrant respondió con código: ' . $response->getStatusCode(),
                    'fix' => 'Verificar configuración de Qdrant'
                ];
            }

        } catch (\Exception $e) {
            $issues[] = [
                'component' => 'Qdrant',
                'severity' => 'error',
                'message' => 'Qdrant no está disponible: ' . $e->getMessage(),
                'fix' => 'Iniciar Qdrant: docker run -p 6333:6333 qdrant/qdrant'
            ];
        }
    }

    private function checkDataIntegrity(array &$issues, bool $detailed): void
    {
        $this->line('🔒 Verificando integridad de datos...');

        try {
            // Verificar consistencia entre base de datos y vectores
            $dbRecords = DB::table('knowledge_base')
                ->where('is_active', true)
                ->count();

            $this->info("✅ Registros en base de datos: {$dbRecords}");

            if ($detailed) {
                // Verificar distribución por categorías
                $categories = DB::table('knowledge_base')
                    ->where('is_active', true)
                    ->groupBy('category')
                    ->selectRaw('category, COUNT(*) as count')
                    ->get();

                $this->line('📊 Distribución por categorías:');
                foreach ($categories as $cat) {
                    $this->line("   {$cat->category}: {$cat->count}");
                }

                // Verificar contenido reciente
                $recentContent = DB::table('knowledge_base')
                    ->where('is_active', true)
                    ->where('updated_at', '>', now()->subDays(7))
                    ->count();

                $this->line("📅 Contenido actualizado en última semana: {$recentContent}");
            }

        } catch (\Exception $e) {
            $issues[] = [
                'component' => 'Data Integrity',
                'severity' => 'warning',
                'message' => 'No se pudo verificar integridad: ' . $e->getMessage(),
                'fix' => 'Verificar conectividad con base de datos'
            ];
        }
    }

    private function checkConfiguration(array &$issues): void
    {
        $this->line('⚙️  Verificando configuración...');

        $requiredConfigs = [
            'services.piida.base_url' => 'URL de PIIDA',
            'services.qdrant.url' => 'URL de Qdrant',
            'services.ollama.url' => 'URL de Ollama',
            'services.ollama.embedding_model' => 'Modelo de embeddings'
        ];

        $configOk = true;

        foreach ($requiredConfigs as $config => $description) {
            $value = config($config);
            if (!$value) {
                $issues[] = [
                    'component' => 'Configuration',
                    'severity' => 'error',
                    'message' => "Configuración faltante: {$description}",
                    'fix' => "Configurar {$config} en config/services.php o .env"
                ];
                $configOk = false;
            }
        }

        if ($configOk) {
            $this->info('✅ Configuración verificada');
        }

        // Verificar variables de entorno adicionales
        $envVars = [
            'DB_CONNECTION' => 'Conexión de base de datos',
            'DB_DATABASE' => 'Nombre de base de datos',
            'APP_KEY' => 'Clave de aplicación'
        ];

        foreach ($envVars as $var => $description) {
            if (!env($var)) {
                $issues[] = [
                    'component' => 'Environment',
                    'severity' => 'warning',
                    'message' => "Variable de entorno faltante: {$description}",
                    'fix' => "Configurar {$var} en archivo .env"
                ];
            }
        }
    }

    private function displayDiagnosisResults(array $issues, bool $fix): void
    {
        if (empty($issues)) {
            $this->info('🎉 ¡Sistema PIIDA completamente saludable!');
            $this->newLine();
            $this->line('Todos los componentes están funcionando correctamente.');
            $this->line('El sistema está listo para procesar consultas de usuarios.');
            return;
        }

        $errors = array_filter($issues, fn($issue) => $issue['severity'] === 'error');
        $warnings = array_filter($issues, fn($issue) => $issue['severity'] === 'warning');

        if (!empty($errors)) {
            $this->error('❌ Errores críticos encontrados:');
            foreach ($errors as $error) {
                $this->line("   {$error['component']}: {$error['message']}");
                if ($fix) {
                    $this->line("   🔧 Solución: {$error['fix']}");
                }
            }
            $this->newLine();
        }

        if (!empty($warnings)) {
            $this->warn('⚠️  Advertencias encontradas:');
            foreach ($warnings as $warning) {
                $this->line("   {$warning['component']}: {$warning['message']}");
                if ($fix) {
                    $this->line("   🔧 Solución: {$warning['fix']}");
                }
            }
            $this->newLine();
        }

        $this->info('💡 Resumen:');
        $this->line("   Errores críticos: " . count($errors));
        $this->line("   Advertencias: " . count($warnings));

        if (!$fix) {
            $this->newLine();
            $this->line("🔧 Usa --fix para ver soluciones sugeridas:");
            $this->line("   php artisan ociel:piida-diagnose --fix");
        }

        if (count($errors) === 0) {
            $this->newLine();
            $this->info('✨ El sistema puede funcionar con las advertencias actuales.');
            $this->line('Se recomienda resolver las advertencias para un rendimiento óptimo.');
        }
    }
}
