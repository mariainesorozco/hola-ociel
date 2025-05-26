<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WebScrapingService;

class ScrapeWebContent extends Command
{
    protected $signature = 'ociel:scrape-web
                           {--source= : Fuente específica a scrapear (main, admissions, academic, etc.)}
                           {--force : Forzar scraping aunque se haya ejecutado recientemente}';

    protected $description = 'Scrapea contenido de páginas web oficiales de la UAN para alimentar la base de conocimientos de Ociel';

    private $scrapingService;

    public function __construct(WebScrapingService $scrapingService)
    {
        parent::__construct();
        $this->scrapingService = $scrapingService;
    }

    public function handle()
    {
        $this->info('🕷️  Iniciando Web Scraping para ¡Hola Ociel!');
        $this->newLine();

        $source = $this->option('source');
        $force = $this->option('force');

        // Verificar si se ejecutó recientemente (a menos que sea forzado)
        if (!$force && \Cache::has('last_scraping')) {
            $lastRun = \Cache::get('last_scraping');
            $this->warn("⚠️  Scraping ejecutado recientemente: {$lastRun}");
            $this->warn("   Usa --force para ejecutar de nuevo");
            return 1;
        }

        try {
            if ($source) {
                // Scraping de fuente específica
                $this->info("📡 Scrapeando fuente específica: {$source}");
                $result = $this->scrapeSingleSource($source);
                $this->displayResult($source, $result);
            } else {
                // Scraping completo
                $this->info("🌐 Ejecutando scraping completo de todas las fuentes...");
                $this->withProgressBar($this->getSources(), function ($sourceData) {
                    [$name, $url] = $sourceData;
                    return $this->scrapingService->scrapeSingleUrl($url, $name);
                });
                $this->newLine();
            }

            // Actualizar cache
            \Cache::put('last_scraping', now(), 86400);

            $this->newLine();
            $this->info('✅ Scraping completado exitosamente');

            // Mostrar estadísticas
            $this->showStatistics();

        } catch (\Exception $e) {
            $this->error('❌ Error durante el scraping: ' . $e->getMessage());
            \Log::error('Scraping command failed: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function scrapeSingleSource(string $source): array
    {
        $urls = [
            'main' => 'https://www.uan.edu.mx',
            'admissions' => 'https://www.uan.edu.mx/admisiones',
            'academic' => 'https://www.uan.edu.mx/oferta-educativa',
            'services' => 'https://www.uan.edu.mx/servicios',
            'tramites' => 'https://www.uan.edu.mx/tramites',
            'dgsa' => 'https://dgsa.uan.edu.mx',
            'sistemas' => 'https://sistemas.uan.edu.mx'
        ];

        if (!isset($urls[$source])) {
            throw new \InvalidArgumentException("Fuente no válida: {$source}");
        }

        return $this->scrapingService->scrapeSingleUrl($urls[$source], $source);
    }

    private function getSources(): array
    {
        return [
            ['main', 'https://www.uan.edu.mx'],
            ['admissions', 'https://www.uan.edu.mx/admisiones'],
            ['academic', 'https://www.uan.edu.mx/oferta-educativa'],
            ['services', 'https://www.uan.edu.mx/servicios'],
            ['tramites', 'https://www.uan.edu.mx/tramites'],
            ['dgsa', 'https://piida.uan.mx/servicios'],
            ['sistemas', 'https://sistemas.uan.edu.mx']
        ];
    }

    private function displayResult(string $source, array $result): void
    {
        if (isset($result['error'])) {
            $this->error("❌ {$source}: {$result['error']}");
        } else {
            $this->info("✅ {$source}: {$result['saved_entries']} entradas guardadas");
            $this->line("   📄 Bloques de contenido: {$result['content_blocks']}");
            $this->line("   🔗 URL: {$result['url']}");
        }
    }

    private function showStatistics(): void
    {
        $stats = \DB::table('knowledge_base')
            ->selectRaw('
                COUNT(*) as total,
                COUNT(CASE WHEN created_by = "web_scraper" THEN 1 END) as scraped,
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active
            ')
            ->first();

        $this->newLine();
        $this->info('📊 Estadísticas de la Base de Conocimientos:');
        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Total de entradas', $stats->total],
                ['Entradas scrapeadas', $stats->scraped],
                ['Entradas activas', $stats->active],
                ['Última actualización', now()->format('Y-m-d H:i:s')]
            ]
        );

        // Mostrar distribución por categorías
        $categories = \DB::table('knowledge_base')
            ->selectRaw('category, COUNT(*) as count')
            ->where('is_active', true)
            ->groupBy('category')
            ->get();

        if ($categories->isNotEmpty()) {
            $this->newLine();
            $this->info('📈 Distribución por categorías:');
            $categoryData = $categories->map(function ($cat) {
                return [$cat->category, $cat->count];
            })->toArray();

            $this->table(['Categoría', 'Entradas'], $categoryData);
        }
    }
}
