<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Registrar KnowledgeBaseService con el EnhancedQdrantVectorService
        $this->app->when(\App\Services\KnowledgeBaseService::class)
            ->needs('$vectorService')
            ->give(function ($app) {
                return $app->make(\App\Services\EnhancedQdrantVectorService::class);
            });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
