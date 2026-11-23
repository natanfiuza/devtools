# Plano de execução — Layout Harmonizer F6 (auditoria final e entrega)

Data: 2026-11-22
Branch: `feature/layout-hamonizer-fase-4`
Plano completo:
`docs/claude-code/plans/feature_layout-hamonizer-fase-4/20261122_feature_layout-hamonizer-fase-4_f6_auditoria.md`
Survey: `docs/survey/20261122_layout_harmonizer_f6_auditoria_surveyfacts.md`

## Decisões que guiam a execução

1. Corrigir tudo o que a auditoria achar; se exigir reabrir produto, parar e perguntar.
2. Teto de croma: claro ≤ 0.012 / escuro ≤ 0.020 (4 tokens), `algorithm`/`params` → 2, fixtures regeneradas.
3. Accent: segue `TOKENS.md` (catálogo de 33 tokens; sem mudança de motor); fechar no ADR-178.
4. Secundária manual com alpha: fundo de composição explícito, composição antes de derivar; envelope
   aditivo; `state`/`snapshot`/`schema` seguem 1.
5. Fundo da seed com alpha: rejeitar explicitamente (sem descarte silencioso).
6. Testes faltantes + concorrência MySQL opt-in (guardas: env, driver, banco `_test`).
7. Aceite + ADR-178 + RUNBOOK §12 + `.env.example`; remover os 22 ADRs locais no aceite.
8. Sem commit/push/deploy; parar para revisão.

## Etapas

| # | Etapa | Gate |
| --- | --- | --- |
| 0 | Salvar artefatos e baseline dos gates | baseline verde |
| 1 | Teto de croma + versões + fixtures + teste de parâmetros | Vitest motor/params/fixtures |
| 2 | Alpha na secundária (motor/parsers/estado) | Vitest motor/estado |
| 3 | Validação do servidor + Pest | `composer test` |
| 4 | UI/controlador/i18n/exports | Vitest UI/i18n/export |
| 5 | Testes faltantes + concorrência opt-in | Vitest/Pest |
| 6 | Auditoria completa e correções | matriz preenchida |
| 7 | Aceite, ADR-178, RUNBOOK, `.env.example`, CHANGELOG e docs; remover ADRs locais | revisão documental |
| 8 | Gates completos + Playwright do CSS + relatório | todos verdes; parada |

## Comandos de gate

```bash
composer test
npm run test
npm run types:check
npm run format:check
npm run lint:check
npm run build
LAYOUT_CONCURRENCY_TEST=1 php artisan test --filter=LayoutSystemConcurrencyTest   # opt-in
```
