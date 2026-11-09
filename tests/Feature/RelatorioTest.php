<?php

namespace UrbanoDev\Visitas\Tests\Feature;

use Illuminate\Support\Carbon;
use UrbanoDev\Visitas\Tests\TestCase;
use UrbanoDev\Visitas\Visitas;

class RelatorioTest extends TestCase
{
    public function test_resumo_por_dia_paginas_e_desde(): void
    {
        $this->travelTo(Carbon::parse('2026-11-06 10:00', 'America/Sao_Paulo'));
        $this->abrir('/');
        $this->abrir('/imoveis/a');
        $this->abrir('/', agente: 'curl/8.0');

        $this->travelTo(Carbon::parse('2026-11-08 10:00', 'America/Sao_Paulo'));
        $this->app->forgetScopedInstances();
        $this->abrir('/imoveis/a');
        $this->abrir('/imoveis/a', ip: '9.9.9.9');

        $r = Visitas::relatorio();
        $inicio = Carbon::parse('2026-11-06');
        $fim = Carbon::parse('2026-11-08');

        $this->assertSame(['visualizacoes' => 4, 'visitantes' => 3, 'robos' => 1], $r->resumo($inicio, $fim));
        $this->assertSame([
            ['dia' => '2026-11-06', 'visualizacoes' => 2, 'visitantes' => 1],
            ['dia' => '2026-11-07', 'visualizacoes' => 0, 'visitantes' => 0],
            ['dia' => '2026-11-08', 'visualizacoes' => 2, 'visitantes' => 2],
        ], $r->porDia($inicio, $fim));
        $this->assertSame([
            ['caminho' => '/imoveis/a', 'rota' => 'site.imovel', 'visualizacoes' => 3, 'visitantes' => 3],
            ['caminho' => '/', 'rota' => 'site.home', 'visualizacoes' => 1, 'visitantes' => 1],
        ], $r->paginas($inicio, $fim));
        $this->assertSame([['caminho' => '/imoveis/a', 'rota' => 'site.imovel', 'visualizacoes' => 3, 'visitantes' => 3]], $r->paginas($inicio, $fim, rota: 'site.imovel'));
        $this->assertSame('2026-11-06', $r->desde());
    }

    public function test_relatorio_vazio_responde_zero(): void
    {
        $r = Visitas::relatorio();

        $this->assertSame(['visualizacoes' => 0, 'visitantes' => 0, 'robos' => 0], $r->resumo(now()->subWeek(), now()));
        $this->assertSame([], $r->paginas(now()->subWeek(), now()));
        $this->assertNull($r->desde());
    }
}
