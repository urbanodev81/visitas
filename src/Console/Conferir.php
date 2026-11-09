<?php

namespace UrbanoDev\Visitas\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use UrbanoDev\Visitas\Escopo;
use UrbanoDev\Visitas\Http\ContarVisita;

/** Confere se o contador está ligado e contando o que deveria. Só lê. */
class Conferir extends Command
{
    protected $signature = 'visitas:conferir';

    protected $description = 'Confere tabelas, middleware, rotas contadas, fuso e escopo do contador de visitas';

    public function handle(Router $router, Kernel $kernel): int
    {
        $falhas = 0;
        $ok = function (bool $passou, string $texto, string $conserto = '') use (&$falhas): void {
            if ($passou) {
                $this->line("  <info>ok</info>    {$texto}");

                return;
            }
            $falhas++;
            $this->line("  <error>falta</error> {$texto}".($conserto !== '' ? " — {$conserto}" : ''));
        };

        $schema = Schema::connection(config('visitas.conexao'));
        $tabelas = ['visitas_paginas', 'visitas_dias', 'visitas_marcas', 'visitas_sais'];
        $temTabelas = collect($tabelas)->every(fn ($t) => $schema->hasTable($t));
        $ok($temTabelas, 'tabelas '.implode(', ', $tabelas), 'php artisan visitas:instalar');

        $fuso = (string) config('visitas.fuso');
        $ok(in_array($fuso, timezone_identifiers_list(), true), "fuso {$fuso}", 'use um nome como America/Sao_Paulo');

        // Quais rotas nomeadas o padrão pega.
        $contadas = collect($router->getRoutes()->getRoutes())
            ->map(fn ($r) => $r->getName())
            ->filter(fn ($n) => $n !== null
                && Str::is((array) config('visitas.rotas', []), $n)
                && ! Str::is((array) config('visitas.fora', []), $n))
            ->values();
        $ok($contadas->isNotEmpty(), $contadas->count().' rota(s) contada(s) por visitas.rotas ('.implode(', ', (array) config('visitas.rotas')).')'
            .($contadas->isNotEmpty() ? ': '.$contadas->take(6)->implode(', ').($contadas->count() > 6 ? ', …' : '') : ''),
            'ajuste visitas.rotas ao nome das rotas públicas');

        $classe = ContarVisita::class;
        $ligado = (method_exists($kernel, 'hasMiddleware') && $kernel->hasMiddleware($classe))
            || collect($router->getMiddlewareGroups())->flatten()->contains($classe)
            || collect($router->getRoutes()->getRoutes())->contains(fn ($r) => array_intersect(['contar-visita', $classe], $r->gatherMiddleware()) !== []);
        $ok($ligado, 'middleware ContarVisita em uso', "->middleware('contar-visita') no grupo do site público");

        if (Escopo::obrigatorio()) {
            $ok(Escopo::temResolver(), 'escopo obrigatório tem resolver', 'visitas.escopo.resolver ou Escopo::resolverCom()');
        }

        if ($temTabelas) {
            $antes = now($fuso)->subDay()->toDateString();
            $velhas = DB::connection(config('visitas.conexao'))->table('visitas_marcas')->where('dia', '<', $antes)->count();
            $ok($velhas === 0, 'nenhuma marca de visitante com mais de um dia', "{$velhas} sobrando: php artisan visitas:faxinar");

            $ultimo = DB::connection(config('visitas.conexao'))->table('visitas_dias')->max('data');
            $this->line('  <comment>info</comment>  último dia com contagem: '.($ultimo ? substr((string) $ultimo, 0, 10) : 'nenhum ainda'));
        }

        return $falhas === 0 ? self::SUCCESS : self::FAILURE;
    }
}
