<?php

namespace UrbanoDev\Visitas\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Publica o config (se ainda não existe), cria as tabelas e roda o
 * `visitas:conferir`. Rodar de novo não quebra nem duplica. Não liga o
 * middleware: quem decide o que conta é o sistema.
 */
class Instalar extends Command
{
    protected $signature = 'visitas:instalar';

    protected $description = 'Instala o contador de visitas (config e tabelas) e confere';

    public function handle(): int
    {
        if (file_exists(config_path('visitas.php'))) {
            $this->line('  config/visitas.php já existe — mantido.');
        } else {
            $this->call('vendor:publish', ['--tag' => 'visitas-config']);
        }

        if (! config('visitas.carregar_migrations')) {
            $this->warn('  VISITAS_CARREGAR_MIGRATIONS=false (banco por tenant): publique com `vendor:publish --tag=visitas-migrations` e mova para database/migrations/tenant.');
        } elseif (Schema::connection(config('visitas.conexao'))->hasTable('visitas_dias')) {
            $this->line('  tabelas já existem — mantidas.');
        } else {
            // Só a migration do pacote: nunca roda as pendentes do sistema junto.
            $this->call('migrate', ['--path' => realpath(__DIR__.'/../../database/migrations'), '--realpath' => true, '--force' => true]);
        }

        $this->newLine();
        $resultado = $this->call('visitas:conferir');

        $this->newLine();
        $this->line('Próximos passos (nada disso liga sozinho):');
        $this->line('  1. Ponha o middleware no site público: ->middleware(\'contar-visita\') no grupo das rotas, ou');
        $this->line('     $middleware->web(append: [\\UrbanoDev\\Visitas\\Http\\ContarVisita::class]) no bootstrap/app.php.');
        $this->line('  2. Confira visitas.rotas (padrão `site.*`) contra o nome das rotas públicas.');
        $this->line('  3. Painel: \\UrbanoDev\\Visitas\\Visitas::relatorio()->resumo($inicio, $fim), porDia(), paginas().');
        $this->line('  4. Sistema com cliente: visitas.escopo.resolver ANTES da primeira visita.');

        return $resultado;
    }
}
