# GEMINI.md - devtools.natanfiuza.dev.br

Leia SEMPRE: `docs/SPEC.md`, `docs/glossario.md`, `docs/adr/*`, `docs/design/DESIGN.md`, `docs/RUNBOOK.md`.

## Regras
- Stack: PHP 8.3, Laravel 13, Inertia 3 (com SSR), Vue 3 + TypeScript estrito, Tailwind 4, shadcn-vue, MariaDB/MySQL (SQLite `:memory:` nos testes), Pest 4, Vitest 5.
- Trabalhe por FASE (`docs/SPEC.md` secao 9). Ao fim de cada fase: rodar `composer test`, `npm run test` e `npm run types:check`, atualizar docs e parar para revisao.
- Nunca fixe nomes de modelo de IA, precos ou limites no codigo: use `config/`, `.env` ou tabelas (`plans`, `output_targets`).
- Nunca registre em log o conteudo de schema ou prompt (nem em `admin_actions`).
- Toda string visivel via i18n (5 idiomas: `en`, `pt-BR`, `pt-PT`, `fr`, `es`). Sem texto hardcoded.
- Convencao de escrita: strings de interface usam acentuacao correta; documentacao do repositorio e escrita em portugues **sem acentos**.
- Decisao nova relevante => novo ADR em `docs/adr/` e termo novo => `docs/glossario.md`.
- Tools do cliente rodam 100% no navegador; nada de requisicao ao servidor.
- Deploy: cron executa a copia instalada `deploy/deploy-instaled.sh` (fonte em `deploy/deploy.sh` no repo; ver `deploy/deploy-instaled.md` e `docs/RUNBOOK.md`) a cada minuto. Migrations devem ser retrocompativeis (sem downtime).
- GIT: nunca faca commit nem push. O usuario commita e abre PR pelo `gitpr`, aprovando cada um. Se um commit for necessario, peca intervencao manual do usuario e pare.
- Ao finalizar qualquer tarefa, gerar relatorio detalhado em `docs/gemini/reports/{branch}/{dataatual}_{branch}_{taskname}.md`, onde `{dataatual}` deve ser substituida pela data atual (`YYYYMMDD`), `{branch}` pela branch atual e `{taskname}` por uma descricao curta da tarefa, sem espacos ou caracteres especiais, apenas letras minusculas separadas por underscore.
- Toda vez que um plano de implementação for gerado ele deve ser salvo em `docs/gemini/plans/{branch}/{dataatual}_{branch}_{planname}.md`, onde {dataatual} deve ser substituida pelo data atual, e onde {branch} deve ser substituida pela branch atual, onde {planname} deve ser uma descrição curta do plano, sem espaços ou caracteres especiais, apenas letras minusculas, separados por underscore.

## Comandos Principais
- `composer dev` — Sobe ambiente local (`php artisan serve`, Vite HMR, fila e `pail`).
- `composer test` — Executa `config:clear`, Pint (`--test`), PHPStan e Pest.
- `npm run test` — Executa testes unitarios do frontend (Vitest).
- `npm run types:check` — Gera rotas tipadas (Wayfinder) e roda `vue-tsc --noEmit`.
- `npm run lint` / `npm run format` — ESLint e Prettier.
- `npm run build` / `npm run build:ssr` — Build de producao do cliente e bundle SSR.

## Arquitetura e Convencoes Rapidas
- **Idiomas e Rotas**: Paginas publicas e de autenticacao vivem sob `/{locale}` (`en`, `pt-br`, `pt-pt`, `fr`, `es`). Area autenticada (`dashboard`, `settings`) e rotas de maquina (`/api`, `/webhooks`, `/admin`, `/auth/{provider}/*`, `/sitemap.xml`, `/robots.txt`) nao usam prefixo de idioma. Traducoes ficam em `lang/{locale}/*.php`.
- **Registro de Tools**: Fonte unica em `resources/js/tools/<slug>/manifest.json` (`slug`, `status: soon|available|hidden`, `category`, `icon`, `order`, `keywords`), lida no frontend por `resources/js/tools/registry.ts` e no backend por `App\Support\Tools`. Nenhum texto visivel fica no `manifest.json` (usar chaves `tools.<slug>.*` nas traducoes).
- **Pagina Generica de Tool**: `resources/js/pages/tools/Show.vue` renderiza cabecalho, metas/SEO e `ToolShell.vue`, carregando `resources/js/tools/<slug>/Tool.vue` via lazy glob (`resources/js/tools/components.ts`). Componentes e utilitarios compartilhados entre tools ficam em `resources/js/components/tool/`, `resources/js/lib/` e `resources/js/composables/`.
- **Artefatos Gerados**: `public/build`, `bootstrap/ssr`, `resources/js/actions`, `resources/js/routes` e `resources/js/wayfinder` nao sao versionados.
