<?php

namespace UrbanoDev\Visitas\Http;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use UrbanoDev\Visitas\Contador;
use UrbanoDev\Visitas\Escopo;

/**
 * Conta a página aberta pelo público. Decide no `handle` e grava no
 * `terminate`, depois de a página ter saído: o visitante não espera o banco.
 *
 * Não conta: método diferente de GET, resposta diferente de 200, quem está
 * logado, rota fora de `visitas.rotas` ou dentro de `visitas.fora`, prefetch e
 * prerender do navegador e do Inertia (a pessoa ainda não abriu a página),
 * recarga parcial do Inertia (é a mesma página). Robô anunciado vira só o
 * número `robos` do dia.
 *
 * Falha de contagem NUNCA derruba a página: vira uma linha de log.
 */
class ContarVisita
{
    private const ATRIBUTO = 'urbanodev.visitas';

    public function __construct(private readonly Contador $contador) {}

    public function handle(Request $request, Closure $next): Response
    {
        $resposta = $next($request);

        try {
            if ($this->deveContar($request, $resposta)) {
                $request->attributes->set(self::ATRIBUTO, [
                    'escopo' => Escopo::atual(),
                    'dia' => now(config('visitas.fuso', 'America/Sao_Paulo'))->toDateString(),
                    'caminho' => self::caminho($request),
                    'rota' => mb_substr((string) $request->route()?->getName(), 0, 100) ?: null,
                    'robo' => self::pareceRobo($request->userAgent()),
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('Visita não contada', ['erro' => $e->getMessage()]);
        }

        return $resposta;
    }

    public function terminate(Request $request, Response $response): void
    {
        $visita = $request->attributes->get(self::ATRIBUTO);

        if (! is_array($visita) || $visita['escopo'] === null) {
            return;
        }

        try {
            if ($visita['robo']) {
                $this->contador->registrarRobo($visita['escopo'], $visita['dia']);

                return;
            }

            $this->contador->registrar(
                $visita['escopo'],
                $visita['dia'],
                $visita['caminho'],
                $visita['rota'],
                (string) $request->ip(),
                (string) $request->userAgent(),
            );
        } catch (Throwable $e) {
            Log::warning('Visita não contada', ['erro' => $e->getMessage()]);
        }
    }

    private function deveContar(Request $request, Response $resposta): bool
    {
        if (! $request->isMethod('GET') || $resposta->getStatusCode() !== 200) {
            return false;
        }

        if (! config('visitas.contar_logado', false) && $request->user() !== null) {
            return false;
        }

        if (self::ehPrefetch($request) || $request->headers->has('X-Inertia-Partial-Component')) {
            return false;
        }

        $rota = $request->route()?->getName();

        return $rota !== null
            && Str::is((array) config('visitas.rotas', []), $rota)
            && ! Str::is((array) config('visitas.fora', []), $rota);
    }

    /** `Sec-Purpose` (Chrome, Speculation Rules), `Purpose` (Inertia, Safari), `X-Moz` (Firefox antigo). */
    public static function ehPrefetch(Request $request): bool
    {
        foreach (['Sec-Purpose', 'Purpose', 'X-Purpose', 'X-Moz'] as $cabecalho) {
            $valor = strtolower((string) $request->headers->get($cabecalho, ''));
            if (str_contains($valor, 'prefetch') || str_contains($valor, 'prerender') || str_contains($valor, 'preview')) {
                return true;
            }
        }

        return false;
    }

    public static function pareceRobo(?string $userAgent): bool
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return true; // navegador sempre manda um
        }

        $agente = strtolower($userAgent);
        foreach ((array) config('visitas.robos', []) as $marca) {
            if ($marca !== '' && str_contains($agente, strtolower((string) $marca))) {
                return true;
            }
        }

        return false;
    }

    /** Sem query string (`?utm_source=` é a mesma página), cortado em 255. */
    public static function caminho(Request $request): string
    {
        $caminho = '/'.ltrim($request->path(), '/');

        return mb_substr($caminho === '//' ? '/' : $caminho, 0, 255);
    }
}
