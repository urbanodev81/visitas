<?php

namespace UrbanoDev\Visitas\Tests\Feature;

use Illuminate\Support\Facades\DB;
use UrbanoDev\Visitas\Tests\TestCase;

class ComandosTest extends TestCase
{
    public function test_conferir_passa_com_tudo_ligado(): void
    {
        $this->artisan('visitas:conferir')
            ->expectsOutputToContain('rota(s) contada(s)')
            ->expectsOutputToContain('middleware ContarVisita em uso')
            ->assertSuccessful();
    }

    public function test_conferir_acusa_marca_velha_e_rota_que_nao_casa(): void
    {
        DB::table('visitas_marcas')->insert(['dia' => now()->subDays(5)->toDateString(), 'marca' => str_repeat('a', 64)]);
        config(['visitas.rotas' => ['publico.*']]);

        $this->artisan('visitas:conferir')
            ->expectsOutputToContain('1 sobrando')
            ->expectsOutputToContain('0 rota(s) contada(s)')
            ->assertFailed();
    }

    public function test_faxinar_apaga_so_os_dias_anteriores(): void
    {
        $hoje = now('America/Sao_Paulo')->toDateString();
        DB::table('visitas_marcas')->insert([
            ['dia' => now()->subDays(2)->toDateString(), 'marca' => str_repeat('a', 64)],
            ['dia' => $hoje, 'marca' => str_repeat('b', 64)],
        ]);

        $this->artisan('visitas:faxinar')->expectsOutputToContain('1 marca(s)')->assertSuccessful();

        $this->assertSame(1, DB::table('visitas_marcas')->count());
    }

    public function test_instalar_roda_duas_vezes_sem_quebrar(): void
    {
        $this->artisan('visitas:instalar')->assertSuccessful();
        $this->artisan('visitas:instalar')->expectsOutputToContain('tabelas já existem')->assertSuccessful();
        @unlink(config_path('visitas.php'));
    }
}
