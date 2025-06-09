<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MarkdownProcessingService;
use App\Services\KnowledgeBaseService;
use App\Services\QdrantVectorService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportMarkdown extends Command
{
    protected $signature = 'ociel:import-markdown
                           {path : Ruta del archivo o directorio Markdown}
                           {--category= : Categoría específica para el contenido}
                           {--department= : Departamento responsable del contenido}
                           {--user-types= : Tipos de usuario separados por coma (student,employee,public)}
                           {--priority= : Prioridad del contenido (high,medium,low)}
                           {--source-name= : Nombre de la fuente para identificación}
                           {--auto-categorize : Usar IA para categorización automática}
                           {--extract-metadata : Extraer metadatos del documento}
                           {--split-sections : Dividir en secciones automáticamente}
                           {--min-section-length=50 : Longitud mínima de sección}
                           {--max-section-length=2000 : Longitud máxima de sección}
                           {--generate-keywords : Generar keywords automáticamente}
                           {--force : Sobrescribir contenido existente}
                           {--dry-run : Mostrar qué se procesaría sin hacer cambios}
                           {--index-vectors : Indexar automáticamente en Qdrant}
                           {--validate-only : Solo validar archivos sin procesar}';

    protected $description = 'Importar archivos Markdown a la base de conocimientos de ¡Hola Ociel!';

    private $markdownService;
    private $knowledgeService;
    private $vectorService;

    public function __construct(
        MarkdownProcessingService $markdownService,
        KnowledgeBaseService $knowledgeService,
        QdrantVectorService $vectorService = null
    ) {
        parent::__construct();
        $this->markdownService = $markdownService;
        $this->knowledgeService = $knowledgeService;
        $this->vectorService = $vectorService;
    }

    public function handle()
    {
        $path = $this->argument('path');
        $validateOnly = $this->option('validate-only');
        $dryRun = $this->option('dry-run');

        $this->info('📝 Importador de Markdown para ¡Hola Ociel!');
        $this->newLine();

        // Validar ruta
        if (!$this->validatePath($path)) {
            return 1;
        }

        // Obtener archivos a procesar
        $files = $this->getMarkdownFiles($path);

        if (empty($files)) {
            $this->error('❌ No se encontraron archivos Markdown en la ruta especificada.');
            return 1;
        }

        $this->info("📁 Encontrados " . count($files) . " archivo(s) Markdown:");
        foreach ($files as $file) {
            $this->line("   📄 " . basename($file));
        }
        $this->newLine();

        // Solo validación
        if ($validateOnly) {
            return $this->validateFiles($files);
        }

        // Mostrar configuración
        $this->displayConfiguration();

        // Confirmar procesamiento
        if (!$dryRun && !$this->option('force')) {
            if (!$this->confirm('¿Proceder con la importación?')) {
                $this->info('❌ Importación cancelada por el usuario.');
                return 0;
            }
        }

        // Procesar archivos
        return $this->processFiles($files, $dryRun);
    }

    private function validatePath(string $path): bool
    {
        if (!File::exists($path)) {
            $this->error("❌ La ruta especificada no existe: {$path}");
            return false;
        }

        if (!File::isReadable($path)) {
            $this->error("❌ No se puede leer la ruta especificada: {$path}");
            return false;
        }

        return true;
    }

    private function getMarkdownFiles(string $path): array
    {
        if (File::isFile($path)) {
            // Archivo individual
            if (pathinfo($path, PATHINFO_EXTENSION) === 'md') {
                return [$path];
            } else {
                $this->error("❌ El archivo especificado no es Markdown: {$path}");
                return [];
            }
        }

        if (File::isDirectory($path)) {
            // Directorio - buscar archivos .md
            $files = File::glob($path . '/*.md');

            // Buscar recursivamente si no hay archivos en el nivel superior
            if (empty($files)) {
                $files = File::allFiles($path);
                $files = array_filter($files, function($file) {
                    return $file->getExtension() === 'md';
                });
                $files = array_map(function($file) {
                    return $file->getPathname();
                }, $files);
            }

            return $files;
        }

        return [];
    }

    private function validateFiles(array $files): int
    {
        $this->info('🔍 Validando archivos Markdown...');
        $this->newLine();

        $validFiles = 0;
        $invalidFiles = 0;

        foreach ($files as $file) {
            $this->line("📄 Validando: " . basename($file));

            $issues = [];

            // Verificar tamaño
            $size = File::size($file);
            if ($size === 0) {
                $issues[] = 'Archivo vacío';
            } elseif ($size > 10 * 1024 * 1024) { // 10MB
                $issues[] = 'Archivo muy grande (> 10MB)';
            }

            // Verificar contenido
            try {
                $content = File::get($file);

                if (empty(trim($content))) {
                    $issues[] = 'Contenido vacío';
                }

                // Verificar encoding UTF-8
                if (!mb_check_encoding($content, 'UTF-8')) {
                    $issues[] = 'Encoding no es UTF-8';
                }

                // Verificar que tenga estructura básica
                if (!preg_match('/^#{1,6}\s+.+/m', $content)) {
                    $issues[] = 'Sin títulos/headers detectados';
                }

                $wordCount = str_word_count($content);
                if ($wordCount < 10) {
                    $issues[] = 'Contenido muy corto (< 10 palabras)';
                }

            } catch (\Exception $e) {
                $issues[] = 'Error leyendo archivo: ' . $e->getMessage();
            }

            if (empty($issues)) {
                $this->info("   ✅ Válido");
                $validFiles++;
            } else {
                $this->error("   ❌ Problemas encontrados:");
                foreach ($issues as $issue) {
                    $this->line("      • {$issue}");
                }
                $invalidFiles++;
            }

            $this->newLine();
        }

        $this->info("📊 Resultado de validación:");
        $this->table(
            ['Estado', 'Cantidad'],
            [
                ['✅ Archivos válidos', $validFiles],
                ['❌ Archivos con problemas', $invalidFiles],
                ['📄 Total archivos', count($files)]
            ]
        );

        return $invalidFiles > 0 ? 1 : 0;
    }

    private function displayConfiguration(): void
    {
        $this->info('⚙️  Configuración de importación:');

        $config = [
            ['Categoría', $this->option('category') ?: 'Auto-detectar'],
            ['Departamento', $this->option('department') ?: 'Auto-detectar'],
            ['Tipos de usuario', $this->option('user-types') ?: 'Auto-detectar'],
            ['Prioridad', $this->option('priority') ?: 'medium'],
            ['Nombre de fuente', $this->option('source-name') ?: 'markdown_import'],
            ['Auto-categorizar', $this->option('auto-categorize') ? '✅ Sí' : '❌ No'],
            ['Extraer metadatos', $this->option('extract-metadata') ? '✅ Sí' : '❌ No'],
            ['Dividir secciones', $this->option('split-sections') ? '✅ Sí' : '❌ No'],
            ['Generar keywords', $this->option('generate-keywords') ? '✅ Sí' : '❌ No'],
            ['Indexar vectores', $this->option('index-vectors') ? '✅ Sí' : '❌ No'],
            ['Modo dry-run', $this->option('dry-run') ? '✅ Sí' : '❌ No']
        ];

        $this->table(['Opción', 'Valor'], $config);
        $this->newLine();
    }

    private function processFiles(array $files, bool $dryRun): int
    {
        $totalFiles = count($files);
        $processedFiles = 0;
        $errorFiles = 0;
        $totalEntries = 0;

        $this->info("🚀 Procesando {$totalFiles} archivo(s)...");
        $this->newLine();

        $progressBar = $this->output->createProgressBar($totalFiles);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');

        foreach ($files as $index => $file) {
            $progressBar->setMessage("Procesando: " . basename($file));

            try {
                $result = $this->processSingleFile($file, $dryRun);

                if ($result['success']) {
                    $processedFiles++;
                    $totalEntries += $result['entries_created'];

                    if (!$dryRun) {
                        $this->newLine();
                        $this->info("✅ " . basename($file) . " - {$result['entries_created']} entradas creadas");
                    }
                } else {
                    $errorFiles++;
                    $this->newLine();
                    $this->error("❌ " . basename($file) . " - Error: " . $result['error']);
                }

            } catch (\Exception $e) {
                $errorFiles++;
                $this->newLine();
                $this->error("❌ " . basename($file) . " - Excepción: " . $e->getMessage());
                Log::error('Error procesando archivo Markdown', [
                    'file' => $file,
                    'error' => $e->getMessage()
                ]);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Mostrar resultados finales
        $this->displayResults($totalFiles, $processedFiles, $errorFiles, $totalEntries, $dryRun);

        return $errorFiles > 0 ? 1 : 0;
    }

    private function processSingleFile(string $file, bool $dryRun): array
    {
        // Leer contenido del archivo
        try {
            $content = File::get($file);
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'No se pudo leer el archivo: ' . $e->getMessage()];
        }

        if (empty(trim($content))) {
            return ['success' => false, 'error' => 'Archivo vacío'];
        }

        // Preparar opciones de procesamiento
        $options = [
            'source_name' => $this->option('source-name') ?: 'markdown_import_' . basename($file, '.md'),
            'auto_categorize' => $this->option('auto-categorize') ?? true,
            'extract_metadata' => $this->option('extract-metadata') ?? true,
            'split_by_sections' => $this->option('split-sections') ?? true,
            'min_section_length' => (int) $this->option('min-section-length'),
            'max_section_length' => (int) $this->option('max-section-length'),
            'generate_keywords' => $this->option('generate-keywords') ?? true,
            'preserve_formatting' => true
        ];

        // Overrides manuales si se especificaron
        if ($this->option('category')) {
            $options['auto_categorize'] = false;
            $options['manual_category'] = $this->option('category');
        }

        if ($this->option('department')) {
            $options['manual_department'] = $this->option('department');
        }

        if ($this->option('user-types')) {
            $options['manual_user_types'] = explode(',', $this->option('user-types'));
        }

        if ($this->option('priority')) {
            $options['manual_priority'] = $this->option('priority');
        }

        // Modo dry-run
        if ($dryRun) {
            return $this->simulateProcessing($content, $options, $file);
        }

        // Procesamiento real usando MarkdownProcessingService o fallback
        try {
            if ($this->markdownService) {
                $result = $this->markdownService->processMarkdownContent($content, $options);
            } else {
                // Fallback: procesamiento simple
                $result = $this->simpleMarkdownProcessing($content, $options);
            }

            // Indexación vectorial si se solicitó
            if ($this->option('index-vectors') && $result['entries_created'] > 0) {
                $this->indexNewEntries($options['source_name']);
            }

            return [
                'success' => true,
                'entries_created' => $result['entries_created'] ?? 1,
                'entries_updated' => $result['entries_updated'] ?? 0,
                'sections_found' => $result['sections_found'] ?? 1,
                'categories_detected' => $result['categories_detected'] ?? []
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Procesamiento simple de Markdown como fallback
     */
    private function simpleMarkdownProcessing(string $content, array $options): array
    {
        // Extraer título del primer header
        $title = 'Documento Markdown';
        if (preg_match('/^#{1,6}\s+(.+)$/m', $content, $matches)) {
            $title = trim($matches[1]);
        }

        // Limpiar contenido básico
        $cleanContent = strip_tags($content);
        $cleanContent = preg_replace('/\n{3,}/', "\n\n", $cleanContent);

        // Generar keywords básicos
        $words = explode(' ', strtolower($cleanContent));
        $keywords = array_filter($words, function($word) {
            return strlen($word) > 3 && !in_array($word, ['para', 'como', 'este', 'esta', 'porque', 'cuando']);
        });
        $keywords = array_slice(array_unique($keywords), 0, 10);

        // Determinar categoría y departamento
        $category = $options['manual_category'] ?? 'informacion_general';
        $department = $options['manual_department'] ?? 'GENERAL';
        $userTypes = $options['manual_user_types'] ?? ['student', 'public'];
        $priority = $options['manual_priority'] ?? 'medium';

        // Guardar en base de datos
        $data = [
            'title' => $title,
            'content' => $cleanContent,
            'category' => $category,
            'department' => $department,
            'user_types' => json_encode($userTypes),
            'keywords' => json_encode($keywords),
            'priority' => $priority,
            'is_active' => true,
            'created_by' => $options['source_name'],
            'created_at' => now(),
            'updated_at' => now()
        ];

        try {
            DB::table('knowledge_base')->insert($data);
            return ['entries_created' => 1, 'entries_updated' => 0, 'sections_found' => 1];
        } catch (\Exception $e) {
            throw new \Exception('Error guardando en base de datos: ' . $e->getMessage());
        }
    }

    private function simulateProcessing(string $content, array $options, string $file): array
    {
        // Simulación para dry-run
        $lines = explode("\n", $content);
        $wordCount = str_word_count($content);
        $estimatedSections = max(1, substr_count($content, '#'));

        return [
            'success' => true,
            'entries_created' => $estimatedSections,
            'entries_updated' => 0,
            'sections_found' => $estimatedSections,
            'simulation' => true,
            'estimated_words' => $wordCount,
            'estimated_lines' => count($lines)
        ];
    }

    private function indexNewEntries(string $sourceName): void
    {
        try {
            if (!$this->vectorService || !$this->vectorService->isHealthy()) {
                $this->warn("⚠️  Qdrant no disponible - omitiendo indexación vectorial");
                return;
            }

            // Obtener entradas recién creadas
            $newEntries = DB::table('knowledge_base')
                ->where('created_by', $sourceName)
                ->where('created_at', '>', now()->subMinutes(5))
                ->count();

            if ($newEntries > 0) {
                $this->line("🔍 Indexando {$newEntries} nuevas entradas en Qdrant...");

                // Ejecutar indexación si el comando existe
                if ($this->getApplication()->has('ociel:index-knowledge')) {
                    $this->call('ociel:index-knowledge', [
                        '--batch-size' => 10
                    ]);
                } else {
                    $this->warn("⚠️  Comando de indexación no disponible");
                }
            }

        } catch (\Exception $e) {
            $this->warn("⚠️  Error en indexación vectorial: " . $e->getMessage());
        }
    }

    private function displayResults(int $total, int $processed, int $errors, int $entries, bool $dryRun): void
    {
        $mode = $dryRun ? ' (SIMULACIÓN)' : '';
        $this->info("🎉 Importación completada{$mode}!");
        $this->newLine();

        $successRate = $total > 0 ? round(($processed / $total) * 100, 1) : 0;

        $results = [
            ['📄 Total archivos', $total],
            ['✅ Procesados exitosamente', $processed],
            ['❌ Archivos con error', $errors],
            ['📊 Tasa de éxito', $successRate . '%']
        ];

        if (!$dryRun) {
            $results[] = ['📝 Total entradas creadas', $entries];
            $results[] = ['🔍 Indexación vectorial', $this->option('index-vectors') ? '✅ Sí' : '❌ No'];
        }

        $this->table(['Métrica', 'Valor'], $results);

        if ($dryRun) {
            $this->newLine();
            $this->info('💡 Para ejecutar la importación real, ejecuta el comando sin --dry-run');
        }

        if ($errors > 0) {
            $this->newLine();
            $this->warn("⚠️  {$errors} archivo(s) tuvieron problemas. Revisa los logs para más detalles.");
        }

        // Mostrar siguiente paso
        if (!$dryRun && $processed > 0) {
            $this->newLine();
            $this->info('🚀 Próximos pasos recomendados:');
            $this->line('   1. Verificar importación: php artisan ociel:test-search "' . basename($this->argument('path'), '.md') . '"');
            $this->line('   2. Ver estadísticas: php artisan ociel:status --detailed');
            $this->line('   3. Probar en el widget web');

            if ($this->vectorService && $this->vectorService->isHealthy()) {
                $this->line('   4. Verificar vectores: php artisan ociel:debug-qdrant');
            }
        }
    }
}
