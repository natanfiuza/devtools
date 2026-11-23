# Matriz de aceite
Vitest: determinismo/versionamento; quatro estratégias; Primary idêntica à seed até aceite; ajuste por tema/ambos; bloqueio independente; bloqueado mantém lock após aceite; feedback estável; variantes distintas de estados; seeds extremas/acromáticas; alpha explícito; gamut; AA/AAA não altera valores automaticamente; contraste sem arredondar para classificar; avisos exportáveis; MD/CSS/ZIP mesmo snapshot; prefixo/escopo inválidos; cinco idiomas; rascunho/import consentido; copiar anônimo/export autenticado.
Pest onde backend existir: auth, proprietário, fonte privada, criação/duplicação na quota conjunta 25, concorrência paleta/sistema, arquivados contam, exclusão libera vaga, downgrade preserva dados, private/public/archive/restore, sitemap, URLs não reutilizadas e cache/recurso público revogado.
Testes manuais: teclado/leitor, comparação em mobile, contraste real em protótipo, export CSS aplicado em exemplo de teste claro/escuro e fallback em ambiente sem OKLCH. Não prometer acessibilidade de toda aplicação baseada só no quadro.
Rodar scripts reais de composer/package: testes relevantes, suíte de regressão, typecheck e build. Registrar falhas existentes e novas. Não usar banco de produção.

## Cobertura acrescentada na auditoria (F6, 2026-11-22)

Os quatro requisitos que estavam sem teste direto ganharam casos:

- bloqueio retido depois do aceite de sugestão (`layout-harmonizer-overrides.test.ts`);
- feedback independente da seed (`layout-harmonizer-engine.test.ts`);
- hover/active/variant distintos por tema e estratégia (`layout-harmonizer-engine.test.ts`);
- Primary inicial igual à seed efetiva por tema (`layout-harmonizer-engine.test.ts`);
- alpha na secundária (com/sem fundo, fundo com alpha, composição) e alpha no fundo da seed recusado (`layout-harmonizer-engine.test.ts`, `layout-harmonizer-state.test.ts`);
- teto de croma e versões 2/1 em todas as fixtures (`layout-harmonizer-params.test.ts`);
- regressão de regeneração do quadro congelado (`layout-harmonizer-regeneration.test.ts`);
- rascunho: fechar o diálogo não descarta; descarte explícito do anônimo limpa quadro e storage (`layout-harmonizer-draft-ui.test.ts`).

## Como rodar

```bash
npm run test          # Vitest (suíte completa)
composer test         # Pest (suíte completa)
npm run types:check
npm run format:check
npm run lint:check
npm run build
```

A concorrência real de linha (cota conjunta) é opt-in e nunca aponta para
dev/prod:

```bash
DB_CONNECTION=mysql DB_DATABASE=devtools_test DB_USERNAME=... DB_PASSWORD=... \
LAYOUT_CONCURRENCY_TEST=1 php artisan test --filter=LayoutSystemConcurrencyTest
```

O teste roda só com driver `mysql`/`mariadb` e nome de banco terminando em
`_test` (guarda tripla); fora disso, fica `skipped` com o motivo. Sem MySQL de
teste descartável, o resultado esperado é o skip — registrar no aceite.
