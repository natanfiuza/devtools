# ADR-002-processamento-local
Status: Aceito
Data: 2026-10-05

## Decisao
Tools deterministicas rodam no navegador. Apenas o AI SQL Builder usa backend.

## Consequencias
Ver SPEC.md.

## Emenda da F5 (2026-10-20)

A excecao deixou de ser promessa e virou codigo: o AI SQL Builder e a **unica**
tool com backend. As oito tools de navegador continuam sem endpoint, e a regra
"nada de requisicao ao servidor" segue valendo para elas - o teste de cada tool
continua cobrindo isso. O backend da F5 e o que a excecao exigia e nada alem:
`POST /api/ai/queries` e `GET /api/ai/queries/{ulid}` (ADR-042), o job da fila
(ADR-019), o provedor (ADR-039) e o ledger/cache (ADR-040). O resto do site
continua estatico: a pagina e o SEO saem do servidor, mas nenhum texto da tool
e processado la.

## Emenda da F3 do BadgeForge (2026-11-02)

A gestao de logos personalizados e presets (ADR-079) acrescentou ao BadgeForge
uma excecao **estreita e explicita**. Geracao, preview, snippets e download
continuam 100% no navegador; nenhuma digitacao faz requisicao. O servidor e
chamado apenas em acao de conta: buscar os paths do logo personalizado
escolhido, enviar/renomear/excluir logo e salvar/renomear/excluir preset
(`/badgeforge/*` e `/dashboard/badgeforge`, sob `auth, verified, active`).
Nao ha endpoint de render ou de validacao de SVG, e as demais tools de
navegador seguem sem backend - a guarda "digitar nao dispara fetch" esta nos
testes da tool.
