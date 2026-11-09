<?php

namespace UrbanoDev\Visitas;

/** Ponto de entrada para ler os números. */
final class Visitas
{
    /**
     * Relatório de um cliente. Sem argumento: o escopo da requisição ('' em site
     * sem cliente). Painel central que olha um cliente por vez passa o escopo.
     */
    public static function relatorio(?string $escopo = null): Relatorio
    {
        return new Relatorio($escopo ?? (Escopo::atual() ?? ''));
    }
}
