# Changelog

## [Não lançado]

### Adicionado
- **v0.1.0:** middleware `ContarVisita` (apelido `contar-visita`): conta `GET` 200 de quem não está
  logado em rota de `visitas.rotas`; robô anunciado vira `robos` do dia; prefetch, prerender e
  recarga parcial do Inertia fora; grava no `terminate`, e falha de contagem nunca derruba a página.
- Visitante do dia por marca sha256 (sal do dia, escopo, IP, navegador), sem cookie e sem IP
  guardado; o primeiro acesso do dia apaga as marcas e o sal anteriores.
- Escopo por cliente (`visitas.escopo.resolver`, `obrigatorio`), dia no fuso `visitas.fuso`.
- `Visitas::relatorio()`: `resumo`, `porDia` (dia vazio = 0), `paginas` (filtro por rota), `desde`.
- `visitas:instalar`, `visitas:conferir` (tabelas, fuso, rotas contadas, middleware em uso, marca
  velha) e `visitas:faxinar`.
