<?php

return [
    // false em sistema com banco por tenant (stancl): publique as migrations
    // (`vendor:publish --tag=visitas-migrations`) para database/migrations/tenant.
    'carregar_migrations' => env('VISITAS_CARREGAR_MIGRATIONS', true),

    // Conexão do banco (null = a padrão do sistema).
    'conexao' => env('VISITAS_CONEXAO'),

    // O dia da contagem é o dia DESTE fuso, não o do servidor (a VPS roda em UTC:
    // sem isto, a visita das 22h de Brasília cairia no dia seguinte).
    'fuso' => env('VISITAS_FUSO', 'America/Sao_Paulo'),

    /*
     * Quais páginas contam: nomes de rota, com curinga (`site.*`). Rota sem nome
     * nunca conta. Comece pelo grupo do site público e não pelo `*`: o `*` conta
     * login, recuperação de senha e qualquer rota que um sistema crie depois.
     */
    'rotas' => ['site.*'],

    // Rotas que casam com `rotas` mas não são página que alguém abre.
    'fora' => ['*.sitemap', '*.llms', '*.robots', '*.feed', '*.previa'],

    // Quem está logado é a casa, não o público. true só se o site público for
    // de quem entra com senha (área do aluno, do associado).
    'contar_logado' => false,

    /*
     * O que denuncia robô no user-agent (busca sem diferenciar maiúscula). Lista
     * curta de propósito: erra para o lado de contar a mais. Robô que não se
     * anuncia só seria pego por impressão digital, que é o que não se faz aqui.
     * O que ela pega não some: vira o número `robos` do dia.
     */
    'robos' => [
        'bot', 'crawl', 'spider', 'slurp', 'facebookexternalhit', 'whatsapp',
        'telegram', 'discord', 'preview', 'embedly', 'headless', 'lighthouse',
        'pingdom', 'monitor', 'uptime', 'curl', 'wget', 'python', 'httpclient',
        'okhttp', 'go-http', 'java/', 'axios', 'node-fetch', 'scrapy',
    ],

    /*
     * Escopo por cliente (tenant). Site pequeno deixa `resolver` nulo.
     * Multi-inquilino (USI) liga ANTES da primeira visita: classe com
     * __invoke(): ?string, ou `Escopo::resolverCom(fn () => ...)` num provider.
     * Com `obrigatorio`, visita sem cliente identificado não é contada.
     */
    'escopo' => [
        'resolver' => null,
        'obrigatorio' => false,
    ],
];
