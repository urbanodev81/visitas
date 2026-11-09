<?php

namespace UrbanoDev\Visitas;

use Illuminate\Support\ServiceProvider;
use UrbanoDev\Visitas\Http\ContarVisita;

class VisitasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/visitas.php', 'visitas');
        $this->app->scoped(Contador::class);
    }

    public function boot(): void
    {
        if (config('visitas.carregar_migrations')) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        // Só o apelido: o pacote não põe o middleware em grupo nenhum sozinho.
        $this->app['router']->aliasMiddleware('contar-visita', ContarVisita::class);

        $this->publishes([__DIR__.'/../config/visitas.php' => config_path('visitas.php')], 'visitas-config');
        $this->publishesMigrations([__DIR__.'/../database/migrations' => database_path('migrations')], 'visitas-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\Instalar::class,
                Console\Conferir::class,
                Console\Faxinar::class,
            ]);
        }
    }
}
