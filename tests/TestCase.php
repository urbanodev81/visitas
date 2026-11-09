<?php

namespace UrbanoDev\Visitas\Tests;

use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase as Orchestra;
use UrbanoDev\Visitas\Escopo;
use UrbanoDev\Visitas\VisitasServiceProvider;

abstract class TestCase extends Orchestra
{
    protected const NAVEGADOR = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();
        Escopo::resolverCom(null);
    }

    protected function tearDown(): void
    {
        Escopo::resolverCom(null);
        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [VisitasServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('database.default', 'testing');

        if (getenv('VISITAS_TESTE_PGSQL')) {
            $app['config']->set('database.default', 'pgsql');
            $app['config']->set('database.connections.pgsql', [
                'driver' => 'pgsql',
                'host' => getenv('DB_HOST') ?: '127.0.0.1',
                'port' => getenv('DB_PORT') ?: '5432',
                'database' => getenv('DB_DATABASE') ?: 'visitas',
                'username' => getenv('DB_USERNAME') ?: 'postgres',
                'password' => getenv('DB_PASSWORD') ?: '',
                'charset' => 'utf8',
                'prefix' => '',
                'search_path' => 'public',
                'sslmode' => 'prefer',
            ]);
        }
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function defineRoutes($router): void
    {
        Route::middleware(['web', 'contar-visita'])->group(function () {
            Route::get('/', fn () => 'home')->name('site.home');
            Route::get('/imoveis/{slug}', fn ($slug) => "imóvel {$slug}")->name('site.imovel');
            Route::get('/sitemap.xml', fn () => '<xml/>')->name('site.sitemap');
            Route::get('/sumiu', fn () => abort(404))->name('site.sumiu');
            Route::post('/contato', fn () => 'ok')->name('site.contato');
            Route::get('/painel', fn () => 'painel')->name('painel.inicio');
            Route::get('/sem-nome', fn () => 'x');
        });
    }

    /** @param array<string, string> $cabecalhos */
    protected function abrir(string $url, array $cabecalhos = [], string $ip = '200.1.1.1', ?string $agente = self::NAVEGADOR): \Illuminate\Testing\TestResponse
    {
        $servidor = ['REMOTE_ADDR' => $ip];
        if ($agente !== null) {
            $servidor['HTTP_USER_AGENT'] = $agente;
        }
        foreach ($cabecalhos as $nome => $valor) {
            $servidor['HTTP_'.strtoupper(str_replace('-', '_', $nome))] = $valor;
        }

        return $this->call('GET', $url, [], [], [], $servidor);
    }
}
