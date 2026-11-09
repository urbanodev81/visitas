<?php

namespace UrbanoDev\Visitas;

use Closure;

/**
 * Quem é o cliente da visita (tenant). Site pequeno: nada configurado, '' sempre.
 * Mesmo desenho do `UrbanoDev\Midia\Escopo`.
 */
final class Escopo
{
    private static ?Closure $resolver = null;

    public static function resolverCom(?callable $resolver): void
    {
        self::$resolver = $resolver === null ? null : Closure::fromCallable($resolver);
    }

    public static function temResolver(): bool
    {
        return self::$resolver !== null || config('visitas.escopo.resolver') !== null;
    }

    public static function obrigatorio(): bool
    {
        return (bool) config('visitas.escopo.obrigatorio', false);
    }

    /** '' quando não há cliente; null quando é obrigatório e não deu para saber. */
    public static function atual(): ?string
    {
        $resolver = self::$resolver;

        if ($resolver === null && ($classe = config('visitas.escopo.resolver'))) {
            $resolver = app($classe);
        }

        $valor = $resolver === null ? null : $resolver();
        $valor = $valor === null ? '' : mb_substr((string) $valor, 0, 64);

        return $valor === '' && self::obrigatorio() ? null : $valor;
    }
}
