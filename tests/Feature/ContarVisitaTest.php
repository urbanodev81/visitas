<?php

namespace UrbanoDev\Visitas\Tests\Feature;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\DB;
use UrbanoDev\Visitas\Escopo;
use UrbanoDev\Visitas\Tests\TestCase;
use UrbanoDev\Visitas\Visitas;

class ContarVisitaTest extends TestCase
{
    private function dia(string $escopo = ''): ?object
    {
        return DB::table('visitas_dias')->where('escopo', $escopo)->first();
    }

    public function test_conta_visualizacao_e_visitante(): void
    {
        $this->abrir('/')->assertOk();

        $this->assertSame(1, (int) $this->dia()->visualizacoes);
        $this->assertSame(1, (int) $this->dia()->visitantes);
        $pagina = DB::table('visitas_paginas')->first();
        $this->assertSame('/', $pagina->caminho);
        $this->assertSame('site.home', $pagina->rota);
    }

    public function test_recarga_soma_visualizacao_mas_nao_visitante(): void
    {
        $this->abrir('/');
        $this->abrir('/');
        $this->abrir('/imoveis/casa-azul');

        $this->assertSame(3, (int) $this->dia()->visualizacoes);
        $this->assertSame(1, (int) $this->dia()->visitantes);
        $home = DB::table('visitas_paginas')->where('caminho', '/')->first();
        $this->assertSame([2, 1], [(int) $home->visualizacoes, (int) $home->visitantes]);
    }

    public function test_outra_pessoa_e_outro_visitante(): void
    {
        $this->abrir('/', ip: '200.1.1.1');
        $this->abrir('/', ip: '200.2.2.2');

        $this->assertSame(2, (int) $this->dia()->visitantes);
    }

    public function test_query_string_e_a_mesma_pagina(): void
    {
        $this->abrir('/imoveis/casa?utm_source=instagram');
        $this->abrir('/imoveis/casa');

        $this->assertSame(1, DB::table('visitas_paginas')->count());
        $this->assertSame('/imoveis/casa', DB::table('visitas_paginas')->value('caminho'));
    }

    public function test_robo_nao_entra_no_total_e_vira_numero_proprio(): void
    {
        $this->abrir('/', agente: 'WhatsApp/2.23.20.0');
        $this->abrir('/', agente: 'Mozilla/5.0 (compatible; Googlebot/2.1)');
        $this->abrir('/', agente: ''); // o Symfony põe 'Symfony' quando falta

        $this->assertSame(0, (int) $this->dia()->visualizacoes);
        $this->assertSame(3, (int) $this->dia()->robos);
        $this->assertSame(0, DB::table('visitas_paginas')->count());
        $this->assertSame(0, DB::table('visitas_marcas')->count());
    }

    public function test_prefetch_e_recarga_parcial_nao_contam(): void
    {
        $this->abrir('/', ['Sec-Purpose' => 'prefetch;prerender']);
        $this->abrir('/', ['Purpose' => 'prefetch']);
        $this->abrir('/', ['X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'Home']);

        $this->assertNull($this->dia());
    }

    public function test_o_que_nao_e_pagina_publica_nao_conta(): void
    {
        $this->abrir('/sitemap.xml')->assertOk();
        $this->abrir('/sumiu')->assertNotFound();
        $this->call('POST', '/contato', [], [], [], ['HTTP_USER_AGENT' => self::NAVEGADOR]);
        $this->abrir('/painel')->assertOk();
        $this->abrir('/sem-nome')->assertOk();

        $this->assertNull($this->dia());
    }

    public function test_logado_nao_conta(): void
    {
        $usuario = User::forceCreate(['name' => 'Casa', 'email' => 'casa@exemplo.test', 'password' => 'x']);

        $this->actingAs($usuario);
        $this->abrir('/')->assertOk();

        $this->assertNull($this->dia());
    }

    public function test_banco_fora_nao_derruba_a_pagina(): void
    {
        \Illuminate\Support\Facades\Schema::drop('visitas_dias');

        $this->abrir('/')->assertOk()->assertSee('home');
    }

    public function test_dia_e_o_do_brasil_e_nao_o_do_servidor(): void
    {
        // 01:30 UTC de 09/11 = 22:30 de 08/11 em Brasília.
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-09 01:30:00', 'UTC'));
        $this->abrir('/');

        $this->assertSame('2026-11-08', substr((string) $this->dia()->data, 0, 10));
    }

    public function test_primeiro_acesso_do_dia_apaga_marcas_e_sal_de_ontem(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-08 15:00', 'America/Sao_Paulo'));
        $this->abrir('/');
        $salDeOntem = DB::table('visitas_sais')->value('sal');
        $this->assertSame(2, DB::table('visitas_marcas')->count());

        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-09 09:00', 'America/Sao_Paulo'));
        $this->app->forgetScopedInstances();
        $this->abrir('/');

        $this->assertSame(['2026-11-09'], DB::table('visitas_marcas')->pluck('dia')->map(fn ($d) => substr((string) $d, 0, 10))->unique()->values()->all());
        $this->assertSame(1, DB::table('visitas_sais')->count());
        $this->assertNotSame($salDeOntem, DB::table('visitas_sais')->value('sal'));
        // A mesma pessoa, no dia seguinte, é visitante de novo (não seguimos ninguém entre dias).
        $this->assertSame(2, (int) DB::table('visitas_dias')->sum('visitantes'));
    }

    public function test_nada_de_ip_nem_user_agent_vai_para_o_banco(): void
    {
        $this->abrir('/', ip: '201.55.66.77');

        $tudo = json_encode([
            DB::table('visitas_paginas')->get(), DB::table('visitas_dias')->get(),
            DB::table('visitas_marcas')->get(), DB::table('visitas_sais')->get(),
        ]);
        $this->assertStringNotContainsString('201.55.66.77', $tudo);
        $this->assertStringNotContainsString('Chrome', $tudo);
    }

    public function test_escopo_separa_clientes(): void
    {
        Escopo::resolverCom(fn () => 'aurora');
        $this->abrir('/');
        Escopo::resolverCom(fn () => 'jacob');
        $this->abrir('/');
        $this->abrir('/');

        $this->assertSame(1, (int) $this->dia('aurora')->visualizacoes);
        $this->assertSame(2, (int) $this->dia('jacob')->visualizacoes);
        $this->assertNull($this->dia(''));
        $this->assertSame(2, Visitas::relatorio('jacob')->resumo(now()->subDay(), now()->addDay())['visualizacoes']);
    }

    public function test_escopo_obrigatorio_sem_cliente_nao_conta(): void
    {
        config(['visitas.escopo.obrigatorio' => true]);
        Escopo::resolverCom(fn () => null);

        $this->abrir('/')->assertOk();

        $this->assertSame(0, DB::table('visitas_dias')->count());
    }
}
