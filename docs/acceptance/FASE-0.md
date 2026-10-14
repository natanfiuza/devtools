# Criterios de aceite — FASE 0 (Fundacao)

Gerado em 2026-10-13, no inicio da fase (regra do `docs/SPEC.md` secao 9).
Fonte: `docs/SPEC.md` secao 9 (F0) + `docs/design/DESIGN.md` (entregaveis) + decisoes do grilling.

## Escopo da fase

Projeto Laravel 13 + starter Vue sobre `laravel/vue-starter-kit` (`dev-main`), TypeScript estrito, tokens do DESIGN.md, shadcn-vue, i18n em 5 idiomas, layout, tema claro/escuro, `deploy.sh`, supervisor, Pest e Vitest.

## Criterios

| # | Criterio | Como verificar |
|---|---|---|
| 1 | O app sobe local do zero seguindo o `README.md` (composer install, npm install, `.env`, migrate, dev) | Seguir o README numa copia limpa |
| 2 | `composer test` verde com **Pest 4** | `composer test` |
| 3 | Paridade de chaves entre os 5 arquivos de idioma | teste Pest `lang/*` |
| 4 | Resolucao de locale e redirect de `/` para `/en` | testes Pest de rota |
| 5 | `npm run test` verde com **Vitest 5** | `npm run test` |
| 6 | Contraste **AA** dos pares de tokens nos dois temas | teste Vitest que calcula o ratio WCAG |
| 7 | `npm run build` e `npm run build:ssr` sem erro | os dois comandos |
| 8 | `vue-tsc --noEmit` limpo com TS estrito | `npm run types:check` |
| 9 | As 5 URLs (`/en`, `/pt-br`, `/pt-pt`, `/fr`, `/es`) respondem; `/` redireciona para `/en`; seletor troca e persiste; `hreflang` no head | smoke manual + teste Pest |
| 10 | Tema claro/escuro/sistema funciona, persiste e **nao pisca** ao recarregar | smoke manual (recarregar em cada tema) |
| 11 | `/styleguide` renderiza os componentes do DESIGN nos dois temas | navegar em `/styleguide` |
| 12 | Zero string hardcoded nas telas (tudo via i18n) | revisao + busca por texto literal nos componentes |
| 13 | Nada de modelo/preco/limite de IA no codigo | revisao (regra do CLAUDE.md) |
| 14 | `deploy/deploy.sh` (build no servidor, atomico, PATH explicito) e `deploy/supervisor/devtools-ssr.conf` prontos | revisao dos arquivos + RUNBOOK |
| 15 | `README.md` e `docs/RUNBOOK.md` escritos | revisao |
| 16 | Docs atualizados: `SPEC.md` §11, `ADR-014`, `ADR-021`, `DESIGN.md`, `CHANGELOG.md` | revisao |
| 17 | **Nenhum commit feito pelo assistente** | `git log` / `git status` |

## Fora do escopo da F0

- Registro de tools, home real, busca Cmd+K, banner LGPD, GA4, sitemap e OG images (F1).
- JSON Viewer e CodeMirror (F2).
- Auth/OAuth/verificacao (F4) — o scaffold do Fortify entra, mas a fase de auth e a F4.
- Deploy real no VPS: feito pelo usuario, guiado pelo RUNBOOK.

## Resultado (2026-10-13)

| # | Criterio | Resultado |
|---|---|---|
| 1 | App sobe do zero pelo README | Escrito e conferido contra o fluxo real; a prova "do zero" depende do banco `devtools` no MariaDB local (SQL no README), que e acao do usuario |
| 2 | `composer test` verde (Pint + PHPStan nivel 7 + Pest 4) | OK — 55 testes, 775 assercoes |
| 3 | Paridade de chaves entre os 5 idiomas | OK — `TranslationsParityTest` (chaves iguais nos 5, nada vazio, nomes de idioma completos) |
| 4 | Resolucao de locale e redirect de `/` | OK — `LocaleRoutingTest` (header, cookie, conta, query preservada, 404 fora da lista, cookie gravado ao visitar `/es`) |
| 5 | `npm run test` verde (Vitest 5) | OK — 59 testes em 3 arquivos |
| 6 | Contraste AA nos dois temas | OK — pior par 4.55 no claro e 4.68 no escuro; anel de foco 4.55 / 10.58 |
| 7 | `npm run build` e `npm run build:ssr` | OK — 5.6s e 3.0s |
| 8 | `vue-tsc --noEmit` limpo (TS estrito) | OK |
| 9 | 5 URLs, redirect de `/`, seletor, hreflang | OK — testes + smoke com SSR ligado: `/` -> 302 `/en`, `/de` -> 404, hreflang dos 5 + `x-default`, canonical |
| 10 | Tema claro/escuro/sistema sem flash | OK — `AppearanceTest` + smoke: cookie `dark` da `data-appearance="dark" class="dark"`; sem cookie, `system` com o `matchMedia` inline |
| 11 | `/styleguide` nos dois temas | Rota OK (renderiza nos testes); conferencia visual fica para a revisao |
| 12 | Zero string hardcoded nas telas da F0 | OK nas telas da F0 (Home, Styleguide, layout, header, footer, settings/Appearance); as telas de auth do kit seguem em ingles (carry-over da F4, abaixo) |
| 13 | Nada de modelo/preco/limite de IA no codigo | OK — so `config/` e `.env` |
| 14 | `deploy.sh` + supervisor prontos | OK — revisados; o deploy em si so roda no VPS |
| 15 | README e RUNBOOK escritos | OK |
| 16 | Docs atualizados | OK — SPEC (§2, §3, §9, §10, §11), ADR-014 revisado, ADR-021 novo, DESIGN (AA + fontes), CHANGELOG novo |
| 17 | Nenhum commit feito pelo assistente | OK — `git log` so tem o `first commit` do usuario |

Smoke com SSR ligado (`INERTIA_SSR_ENABLED=true`, SQLite descartavel em
diretorio temporario, removido depois): as 5 home pages renderizam no
servidor (~26 KB de HTML contra ~13 KB sem SSR), com traducao no HTML
("Ferramentas" no pt-BR) e **zero erro no log do SSR**. Dois bugs de SSR
foram encontrados e corrigidos nesse smoke: o glob de paginas apontava para o
diretorio errado (invisivel no cliente, porque o plugin do Inertia injeta o
`resolve`) e a diretiva `v-focus` so estava registrada no cliente.

## Carry-overs

- **F4**: i18n das telas de auth e de settings do kit (Login, Register,
  ForgotPassword, ResetPassword, VerifyEmail, Profile, Security, DeleteUser) e
  dos breadcrumbs de item unico; gravar `users.appearance` pela conta (na F0 a
  coluna so e lida).
- **F4**: decidir se 2FA/passkeys voltam — os dois foram desligados no config do
  Fortify e os testes correspondentes removidos na F0.
- **F1**: home real, registro de tools, Cmd+K, banner LGPD, GA4, sitemap, OG images.
- **F2**: `--elevated` como cor de pontuacao JSON (SPEC secao 11.2).
- **F5**: coluna de dialetos em `output_targets` e a escolha entre AI SDK e
  cliente HTTP fino (SPEC secao 11.2).
- **Operacao (usuario)**: criar banco/usuario `devtools` no MariaDB local;
  confirmar PHP 8.3 e usuario do cron no VPS; `composer self-update` (a versao
  2.9.x local tem advisory corrigido na 2.10.3); rotacao de chaves no VPS.
