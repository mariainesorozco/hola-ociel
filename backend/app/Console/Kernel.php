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
        // Comandos existentes
        Commands\DiagnoseOllama::class,
        Commands\ScrapeWebContent::class,

        // Nuevos comandos para embeddings
        Commands\IndexKnowledgeBase::class,
        Commands\TestSemanticSearch::class,

        Commands\DebugQdrant::class,

        // Otros comandos
        // Commands\UpdateEmbeddings::class,
        // Commands\ClearCache::class,
        // Commands\StatusCheck::class,
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
