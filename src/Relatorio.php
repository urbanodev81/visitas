<?php

namespace UrbanoDev\Visitas;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * O que o painel mostra. Tudo de um escopo (cliente) só.
 *
 * ⚠️ `visitantes` somado num período é "pessoa por dia": quem voltou em três
 * dias conta três. Visitante único de um mês exigiria guardar a marca o mês
 * inteiro — que é seguir a pessoa, e é o que este pacote não faz. Escreva o
 * rótulo da tela de acordo ("visitas", não "pessoas diferentes").
 */
class Relatorio
{
    public function __construct(private readonly string $escopo = '') {}

    /** @return array{visualizacoes: int, visitantes: int, robos: int} */
    public function resumo(CarbonInterface $inicio, CarbonInterface $fim): array
    {
        $linha = $this->dias($inicio, $fim)
            ->selectRaw('coalesce(sum(visualizacoes), 0) as v, coalesce(sum(visitantes), 0) as p, coalesce(sum(robos), 0) as r')
            ->first();

        return ['visualizacoes' => (int) $linha->v, 'visitantes' => (int) $linha->p, 'robos' => (int) $linha->r];
    }

    /**
     * Um item por dia do período, inclusive o dia sem ninguém (0, não buraco:
     * a linha do gráfico ligaria dois pontos e "subiria" onde não houve visita).
     *
     * @return list<array{dia: string, visualizacoes: int, visitantes: int}>
     */
    public function porDia(CarbonInterface $inicio, CarbonInterface $fim): array
    {
        $dados = $this->dias($inicio, $fim)->orderBy('data')->get(['data', 'visualizacoes', 'visitantes'])
            ->keyBy(fn ($l) => substr((string) $l->data, 0, 10));

        $saida = [];
        for ($d = CarbonImmutable::parse($inicio->toDateString()); $d->lte($fim->toDateString()); $d = $d->addDay()) {
            $l = $dados->get($d->toDateString());
            $saida[] = [
                'dia' => $d->toDateString(),
                'visualizacoes' => (int) ($l->visualizacoes ?? 0),
                'visitantes' => (int) ($l->visitantes ?? 0),
            ];
        }

        return $saida;
    }

    /**
     * As páginas mais vistas do período, da mais para a menos.
     *
     * @return list<array{caminho: string, rota: ?string, visualizacoes: int, visitantes: int}>
     */
    public function paginas(CarbonInterface $inicio, CarbonInterface $fim, int $limite = 10, ?string $rota = null): array
    {
        return $this->tabela('visitas_paginas')
            ->whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])
            ->when($rota !== null, fn (Builder $q) => $q->where('rota', $rota))
            ->groupBy('caminho')
            ->selectRaw('caminho, max(rota) as rota, sum(visualizacoes) as v, sum(visitantes) as p')
            ->orderByDesc('v')->orderBy('caminho')
            ->limit($limite)
            ->get()
            ->map(fn ($l) => ['caminho' => $l->caminho, 'rota' => $l->rota, 'visualizacoes' => (int) $l->v, 'visitantes' => (int) $l->p])
            ->all();
    }

    /** Primeiro dia com contagem (Y-m-d), ou null se nunca contou. */
    public function desde(): ?string
    {
        $data = $this->tabela('visitas_dias')->min('data');

        return $data === null ? null : substr((string) $data, 0, 10);
    }

    private function dias(CarbonInterface $inicio, CarbonInterface $fim): Builder
    {
        return $this->tabela('visitas_dias')
            ->whereBetween('data', [$inicio->toDateString(), $fim->toDateString()]);
    }

    private function tabela(string $nome): Builder
    {
        return DB::connection(config('visitas.conexao'))->table($nome)->where('escopo', $this->escopo);
    }
}
