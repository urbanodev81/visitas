<?php

namespace UrbanoDev\Visitas;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * Grava a visita. Cinco comandos curtos por página aberta, nenhum SELECT antes
 * de somar: a soma é `INSERT ... ON CONFLICT DO UPDATE`, atômica, sem corrida
 * entre duas visitas no mesmo segundo.
 *
 * O visitante do dia é uma MARCA: sha256 de (sal do dia, escopo, IP, user-agent).
 * O IP e o user-agent entram no hash e são descartados ali mesmo; o sal é
 * aleatório e morre no primeiro acesso do dia seguinte, levando as marcas junto.
 * Sem cookie, sem sessão, nada que siga a pessoa de um dia para o outro — por
 * isso não exige banner de consentimento.
 */
class Contador
{
    /** @var array<string, string> */
    private array $sais = [];

    public function registrar(string $escopo, string $dia, string $caminho, ?string $rota, string $ip, string $userAgent): void
    {
        $sal = $this->salDoDia($dia);
        $pessoa = $sal."\0".$escopo."\0".$ip."\0".$userAgent;

        $novoNoSite = $this->marcar($dia, hash('sha256', $pessoa));
        $novoNaPagina = $this->marcar($dia, hash('sha256', $pessoa."\0".$caminho));

        $this->somar('visitas_paginas', ['escopo', 'data', 'caminho'], [
            'escopo' => $escopo,
            'data' => $dia,
            'caminho' => $caminho,
            'rota' => $rota,
            'visualizacoes' => 1,
            'visitantes' => $novoNaPagina ? 1 : 0,
        ], ['visualizacoes', 'visitantes']);

        $this->somar('visitas_dias', ['escopo', 'data'], [
            'escopo' => $escopo,
            'data' => $dia,
            'visualizacoes' => 1,
            'visitantes' => $novoNoSite ? 1 : 0,
            'robos' => 0,
        ], ['visualizacoes', 'visitantes']);
    }

    public function registrarRobo(string $escopo, string $dia): void
    {
        $this->somar('visitas_dias', ['escopo', 'data'], [
            'escopo' => $escopo,
            'data' => $dia,
            'visualizacoes' => 0,
            'visitantes' => 0,
            'robos' => 1,
        ], ['robos']);
    }

    /** Apaga marcas e sais de antes de `$dia`. Devolve quantas marcas saíram. */
    public function faxinar(string $dia): int
    {
        $marcas = $this->db()->table('visitas_marcas')->where('dia', '<', $dia)->delete();
        $this->db()->table('visitas_sais')->where('dia', '<', $dia)->delete();

        return $marcas;
    }

    private function salDoDia(string $dia): string
    {
        if (isset($this->sais[$dia])) {
            return $this->sais[$dia];
        }

        $sal = $this->db()->table('visitas_sais')->where('dia', $dia)->value('sal');

        if ($sal === null) {
            $criou = $this->db()->table('visitas_sais')->insertOrIgnore(['dia' => $dia, 'sal' => bin2hex(random_bytes(32))]);

            // Primeiro acesso do dia: o de ontem não serve mais para nada.
            if ($criou > 0) {
                $this->faxinar($dia);
            }

            $sal = $this->db()->table('visitas_sais')->where('dia', $dia)->value('sal');
        }

        return $this->sais[$dia] = (string) $sal;
    }

    private function marcar(string $dia, string $marca): bool
    {
        return $this->db()->table('visitas_marcas')->insertOrIgnore(['dia' => $dia, 'marca' => $marca]) > 0;
    }

    /**
     * @param  list<string>  $chave
     * @param  array<string, mixed>  $linha
     * @param  list<string>  $somar
     */
    private function somar(string $tabela, array $chave, array $linha, array $somar): void
    {
        $db = $this->db();
        $alvo = $db->getQueryGrammar()->wrapTable($tabela);
        $agora = now();

        $atualizar = ['updated_at' => $agora];
        foreach ($somar as $coluna) {
            $col = $db->getQueryGrammar()->wrap($coluna);
            $atualizar[$coluna] = DB::raw("{$alvo}.{$col} + ".(int) $linha[$coluna]);
        }

        $db->table($tabela)->upsert([$linha + ['created_at' => $agora, 'updated_at' => $agora]], $chave, $atualizar);
    }

    private function db(): ConnectionInterface
    {
        return DB::connection(config('visitas.conexao'));
    }
}
