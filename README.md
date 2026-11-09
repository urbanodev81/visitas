# urbanodev/visitas

Contador de visitas da casa para o site público de qualquer sistema Laravel 12 ou
13. Responde às duas perguntas que o cliente faz — **quantas pessoas vieram** e
**o que elas abriram** — sem cookie, sem script de terceiro e sem guardar IP.

Nasceu da pendência 263 (08/11/2026), a partir do `ContarVisita` do Girls on the
Rocks, com três coisas que faltavam lá: **visitante do dia** (recarga não conta
como gente nova), **prefetch e prerender fora** e **escopo por cliente** (USI).

## O que conta e o que não conta

Conta: `GET` com resposta 200, de quem **não** está logado, numa rota cujo nome
casa com `visitas.rotas` (padrão `site.*`).

Não conta:

| O quê | Por quê |
|---|---|
| Robô que se anuncia (Googlebot, prévia de link do WhatsApp, `curl`…) | Não é gente. Vira o número `robos` do dia, que nunca entra no total |
| Prefetch e prerender (`Sec-Purpose`, `Purpose`) | A pessoa ainda não abriu a página. O Inertia v2 manda `Purpose: prefetch` |
| Recarga parcial do Inertia | É a mesma página |
| Quem está logado | É a casa conferindo o próprio site |
| `visitas.fora` (`*.sitemap`, `*.llms`, `*.robots`, `*.feed`, `*.previa`) | Endereço de robô ou de uso interno |
| Rota sem nome, 404, 302, `POST` | Não é página vista |

Robô que se disfarça de navegador conta. Pegá-lo exigiria impressão digital, que
é o que este pacote não faz; a lista erra para o lado de contar a mais.

## O que fica guardado (LGPD)

| Tabela | Guarda | Até quando |
|---|---|---|
| `visitas_paginas` | dia, caminho, rota, visualizações, visitantes | sempre (não tem titular) |
| `visitas_dias` | dia, visualizações, visitantes, robôs | sempre |
| `visitas_marcas` | sha256 de (sal do dia, cliente, IP, navegador) | **até o primeiro acesso do dia seguinte** |
| `visitas_sais` | um número aleatório por dia | idem |

O IP e o navegador entram no hash e são descartados ali. O sal é aleatório e
morre no dia seguinte junto com as marcas: depois disso não há como saber de
quem era uma marca, nem ligar a visita de hoje à de amanhã. Por isso **não
exige banner de cookie** — e o banner que o site tiver não precisa condicionar
nada a isto.

Consequência a aceitar de olhos abertos: `visitantes` de um período é a soma
dos visitantes de cada dia (quem veio em três dias conta três). Na tela,
escreva **"visitas"**, não "pessoas diferentes".

## Instalar

```bash
composer config repositories.visitas vcs https://github.com/urbanodev81/visitas
composer require urbanodev/visitas:^0.1
php artisan visitas:instalar
```

O `visitas:instalar` publica o `config/visitas.php` (se ainda não existe), cria as
quatro tabelas e roda o `visitas:conferir`. Pode rodar de novo. **Ele não liga o
middleware** — o passo abaixo é seu.

Banco por tenant (stancl): `VISITAS_CARREGAR_MIGRATIONS=false` e
`php artisan vendor:publish --tag=visitas-migrations`, movendo para
`database/migrations/tenant`.

## Ligar

No grupo das rotas públicas:

```php
Route::middleware('contar-visita')->name('site.')->group(function () {
    Route::get('/', HomeController::class)->name('home');
    // ...
});
```

ou no `bootstrap/app.php`, se as rotas públicas já têm nome próprio
(`visitas.rotas` filtra o resto):

```php
$middleware->web(append: [\UrbanoDev\Visitas\Http\ContarVisita::class]);
```

**Ele precisa rodar depois do `StartSession` e da autenticação** (o grupo `web`
garante), senão não sabe quem está logado e conta a casa.

Depois confira:

```bash
php artisan visitas:conferir
```

Mostra as tabelas, o fuso, **quais rotas o padrão pega** (confira essa lista:
rota pública fora dela não é contada), se o middleware está em uso e se há marca
velha sobrando. Sai com erro se faltar algo.

## Ler os números (painel)

```php
use UrbanoDev\Visitas\Visitas;

$r = Visitas::relatorio();               // escopo da requisição; '' em site sem cliente
$r = Visitas::relatorio($imobiliaria->id); // painel central olhando um cliente

$r->resumo($inicio, $fim);   // ['visualizacoes' => 340, 'visitantes' => 128, 'robos' => 51]
$r->porDia($inicio, $fim);   // um item por dia, dia vazio = 0 (não buraco no gráfico)
$r->paginas($inicio, $fim, limite: 10);             // as mais vistas
$r->paginas($inicio, $fim, rota: 'site.imovel');    // só as fichas
$r->desde();                 // '2026-11-08' ou null
```

O pacote não traz tela: cada sistema mostra do seu jeito, no seu design.

## Sistema com cliente (USI)

**Ligue antes da primeira visita.**

```php
// config/visitas.php
'escopo' => [
    'resolver' => \App\Support\EscopoDaVisita::class,  // __invoke(): ?string
    'obrigatorio' => true,
],
```

ou num service provider: `\UrbanoDev\Visitas\Escopo::resolverCom(fn () => tenant('id'));`

Com `obrigatorio`, visita sem cliente identificado não é contada. A suíte testa
a separação entre clientes (`tests/Feature/ContarVisitaTest.php`).

## O dia da contagem

`visitas.fuso` (padrão `America/Sao_Paulo`). A VPS roda em UTC: sem isso, a
visita das 22h de Brasília cairia no dia seguinte.

## Faxina

Não precisa agendar nada: o primeiro acesso de cada dia apaga as marcas e o sal
dos dias anteriores. Para site que passa dias sem visita e quer a garantia no
relógio, agende `visitas:faxinar` diário (ele não mexe nos totais).

## Se a contagem falhar

A página nunca cai por causa dela: a gravação acontece depois de a resposta
sair (`terminate`) e qualquer erro vira um `Log::warning('Visita não contada')`.
Contagem parada? Rode `visitas:conferir` e procure essa linha no log.

## Testar o pacote

```bash
./scripts/testar.sh   # SQLite e Postgres descartável; precisa só de Docker
```
