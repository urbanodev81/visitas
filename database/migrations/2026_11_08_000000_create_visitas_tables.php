<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contador de visitas (urbanodev/visitas).
 *
 * As duas primeiras tabelas são o que fica para sempre, e não têm dado pessoal:
 * "a página X foi aberta N vezes por M pessoas no dia D". Não há titular, então
 * não há o que expurgar.
 *
 * As duas últimas são DO DIA e se apagam sozinhas no primeiro acesso do dia
 * seguinte. `visitas_marcas` guarda o sha256 de (sal do dia, escopo, IP,
 * user-agent): é o que impede a recarga de contar como gente nova. Sem o sal,
 * que é aleatório e morre junto, a marca não volta a ser IP.
 *
 * `escopo` é texto NÃO nulo com '' de padrão: no Postgres NULL nunca é igual a
 * NULL, e o `ON CONFLICT (escopo, data, ...)` nunca acharia a linha de um site
 * sem cliente — criaria uma linha nova a cada visita.
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('visitas.conexao');
    }

    public function up(): void
    {
        Schema::create('visitas_paginas', function (Blueprint $table) {
            $table->id();
            $table->string('escopo', 64)->default('');
            $table->date('data');
            // Sem query string nem domínio; cortado em 255.
            $table->string('caminho', 255);
            // Redundante de propósito: agrupa "todas as fichas" sem `like` e sobrevive a slug renomeado.
            $table->string('rota', 100)->nullable();
            $table->unsignedInteger('visualizacoes')->default(0);
            $table->unsignedInteger('visitantes')->default(0);
            $table->timestamps();

            $table->unique(['escopo', 'data', 'caminho']);
            $table->index(['escopo', 'rota', 'data']);
        });

        Schema::create('visitas_dias', function (Blueprint $table) {
            $table->id();
            $table->string('escopo', 64)->default('');
            $table->date('data');
            $table->unsignedInteger('visualizacoes')->default(0);
            $table->unsignedInteger('visitantes')->default(0);
            // O que o filtro de robô barrou. Nunca entra no total; serve para mostrar que o filtro existe.
            $table->unsignedInteger('robos')->default(0);
            $table->timestamps();

            $table->unique(['escopo', 'data']);
        });

        Schema::create('visitas_marcas', function (Blueprint $table) {
            $table->date('dia');
            $table->char('marca', 64);
            $table->primary(['dia', 'marca']);
        });

        Schema::create('visitas_sais', function (Blueprint $table) {
            $table->date('dia')->primary();
            $table->char('sal', 64);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitas_sais');
        Schema::dropIfExists('visitas_marcas');
        Schema::dropIfExists('visitas_dias');
        Schema::dropIfExists('visitas_paginas');
    }
};
