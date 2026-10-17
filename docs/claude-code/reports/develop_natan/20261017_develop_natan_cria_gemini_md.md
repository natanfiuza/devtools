# Relatorio - criacao do arquivo `GEMINI.md`

Branch: `develop_natan`
Data: 2026-10-17
Tarefa: "Crie o arquivo GEMINI.md"

**Nada foi commitado pelo assistente.** O commit e o PR sao do usuario, pelo `gitpr`.

## 1. O que foi pedido

Criar o arquivo `GEMINI.md` na raiz do projeto com as diretrizes, regras e contexto do repositorio `devtools.natanfiuza.dev.br`.

## 2. O que foi feito

Foi criado o arquivo `GEMINI.md` na raiz do repositorio (seguindo a convencao de documentacao em portugues sem acentos definida em `docs/SPEC.md`), contendo:

1. **Leitura obrigatoria**: referencia direta para `docs/SPEC.md`, `docs/glossario.md`, `docs/adr/*`, `docs/design/DESIGN.md` e `docs/RUNBOOK.md`.
2. **Regras do projeto** (espelhadas de `CLAUDE.md` e atualizadas conforme o estado atual de `docs/SPEC.md`):
   - Stack completa (PHP 8.3, Laravel 13, Inertia 3 com SSR, Vue 3 + TS estrito, Tailwind 4, shadcn-vue, MariaDB/SQLite, Pest 4, Vitest 5).
   - Fluxo de trabalho por fase com execucao de `composer test`, `npm run test` e `npm run types:check`.
   - Proibicao de valores fixos para modelos de IA, precos e limites no codigo.
   - Proibicao de logar conteudo de schema ou prompt.
   - Obrigatoriedade de i18n nos 5 idiomas (`en`, `pt-BR`, `pt-PT`, `fr`, `es`) e convencao de acentuacao (interface com acentos; documentacao do repo sem acentos).
   - Registro de decisoes em `docs/adr/` e termos em `docs/glossario.md`.
   - Execucao 100% client-side para tools do navegador.
   - Regra de deploy via `deploy/deploy-instaled.sh` (fonte em `deploy/deploy.sh`) e migrations retrocompativeis.
   - Regra de Git (sem commit/push pelo assistente; fluxo via `gitpr`).
   - Geracao de relatorio detalhado em `docs/claude-code/reports/{branch}/{dataatual}_{branch}_{taskname}.md`.
3. **Comandos principais**: tabela rapida de comandos de desenvolvimento, testes, tipagem, lint e build.
4. **Arquitetura e convencoes rapidas**: resumo de rotas/idiomas, registro unico de tools via `manifest.json`, pagina generica `Show.vue` + `ToolShell.vue` e artefatos nao versionados.

## 3. Arquivos criados

- `GEMINI.md`
- `docs/claude-code/reports/develop_natan/20261017_develop_natan_cria_gemini_md.md`
