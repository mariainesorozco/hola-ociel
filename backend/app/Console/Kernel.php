<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected $commands = [
        // Comandos activos
        Commands\DiagnoseOllama::class,
        Commands\TestSemanticSearch::class,
        Commands\DebugQdrant::class,
        Commands\SyncNotionContent::class,
        Commands\CleanupVectorDbCommand::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();
        // Scraping automático cada 6 horas
        $schedule->command('ociel:scrape-web')
            ->everySixHours()
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/scraping.log'));

        // Indexación automática de embeddings cada 2 horas
        $schedule->command('ociel:index-knowledge --batch-size=20')
            ->everyTwoHours()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/indexing.log'));

        // Actualizar embeddings cada hora
        // $schedule->command('ociel:update-embeddings')
        //     ->hourly()
        //     ->withoutOverlapping();

        // Diagnóstico del sistema cada 30 minutos
        $schedule->command('ociel:diagnose-ollama')
            ->everyThirtyMinutes()
            ->appendOutputTo(storage_path('logs/health-check.log'));

        // Sincronización automática de Notion (si está configurado)
        if (config('services.notion.sync_enabled')) {
            // Sincronización completa cada madrugada
            $schedule->command('ociel:sync-notion --all')
                ->dailyAt('03:00')
                ->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/notion-sync.log'));
            
            // Sincronización de Finanzas cada 4 horas (más frecuente por ser crítico)
            $schedule->command('ociel:sync-notion finanzas')
                ->everyFourHours()
                ->withoutOverlapping();
            
            // Sincronización de Académica cada 6 horas
            $schedule->command('ociel:sync-notion academica')
                ->everySixHours()
                ->withoutOverlapping();
            
            // Sincronización de RRHH y Tecnológicos una vez al día
            $schedule->command('ociel:sync-notion recursos_humanos')
                ->dailyAt('12:00')
                ->withoutOverlapping();
                
            $schedule->command('ociel:sync-notion servicios_tecnologicos')
                ->dailyAt('18:00')
                ->withoutOverlapping();
        }

        // Limpiar cache viejo cada día
        // $schedule->command('ociel:clear-cache --type=knowledge')
        //     ->dailyAt('02:00');

        // Status check cada 15 minutos (para monitoreo)
        // $schedule->command('ociel:status --json')
        //     ->everyFifteenMinutes()
        //     ->appendOutputTo(storage_path('logs/ociel-health.log'));
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
