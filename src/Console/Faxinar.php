<?php

namespace UrbanoDev\Visitas\Console;

use Illuminate\Console\Command;
use UrbanoDev\Visitas\Contador;

/**
 * Apaga as marcas e os sais de antes de hoje. O primeiro acesso de cada dia já
 * faz isso sozinho; este comando é para site que passa dias sem visita e quer a
 * garantia no relógio (agende diário, se quiser).
 */
class Faxinar extends Command
{
    protected $signature = 'visitas:faxinar';

    protected $description = 'Apaga as marcas de visitante e os sais dos dias anteriores (o total do dia fica)';

    public function handle(Contador $contador): int
    {
        $marcas = $contador->faxinar(now(config('visitas.fuso', 'America/Sao_Paulo'))->toDateString());
        $this->info("{$marcas} marca(s) de dias anteriores apagada(s). Os totais não mudam.");

        return self::SUCCESS;
    }
}
