# CLAUDE.md - devtools.natanfiuza.dev.br

Leia SEMPRE: docs/SPEC.md, docs/glossario.md, docs/adr/*, docs/design/DESIGN.md.

## Regras

- Stack: PHP 8.3, Laravel 13, Inertia, Vue 3 + TypeScript estrito, Tailwind, shadcn-vue, MySQL, Pest, Vitest.
- Trabalhe por FASE (docs/SPEC.md secao 9). Ao fim de cada fase: rodar `composer test` e `npm run test`, atualizar docs e parar para revisao.
- Nunca fixe nomes de modelo de IA, precos ou limites no codigo: use config/.env/tabelas.
- Nunca registre em log o conteudo de schema ou prompt.
- Toda string visivel via i18n (5 idiomas). Sem texto hardcoded.
- Decisao nova relevante => novo ADR em docs/adr/ e termo novo => docs/glossario.md.
- Tools do cliente rodam 100% no navegador; nada de requisicao ao servidor.
- Deploy: cron executa a copia instalada `deploy/deploy-instaled.sh` (fonte: `deploy/deploy.sh`) a cada minuto. Migrations devem ser retrocompativeis (sem downtime). Guia: `docs/guides/cron.md`.
- GIT: nunca faca commit nem push. O usuario commita e abre PR pelo gitpr, aprovando cada um. Se um commit for necessario, peca intervencao manual do usuario e pare.
- Ao finalizar qualquer tarefa, gerar relatório detalhado em `docs/claude-code/reports/{branch}/{dataatual}_{branch}_{taskname}.md`, onde {dataatual} deve ser substituida pelo data atual, e onde {branch} deve ser substituida pela branch atual, onde {taskname} deve ser uma descricao curta da tarefa, sem espaços ou caracteres especiais, apenas letras minusculas, separados por underscore.
- Toda vez que um plano de implementação for gerado ele deve ser salvo em `docs/claude-code/plans/{branch}/{dataatual}_{branch}_{planname}.md`, onde {dataatual} deve ser substituida pelo data atual, e onde {branch} deve ser substituida pela branch atual, onde {planname} deve ser uma descrição curta do plano, sem espaços ou caracteres especiais, apenas letras minusculas, separados por underscore.

## BadgeForge

- Ferramenta: /tools/badgeforge
- Especificação: docs/SPEC-BADGEFORGE.md
- ADRs: docs/adr/ADR-062-badgeforge a ADR-082-badgeforge
- API: docs/badgeforge/API.md; documentação pública: `/{locale}/docs/badgeforge`
- Fase atual: F4 — providers + badges pessoais + PAT + docs públicas (entregue)
- Regras: SVG é o formato canônico; PNG (F2) via Imagick com cache em disco; fontes empacotadas via `?font=` (ADR-077); providers em `/api/badges/{provider}/{definition}/{path}` (ADR-080).
- Logos personalizados e presets (F3, ADR-078 e ADR-079): SVG multi-path sanitizado persistido só como `viewBox` + paths normalizados; referência pública `u_<ULID>`; limites em `config/badgeforge.php` (Free: 2 logos; pago: ilimitado; presets: ilimitados); gestão web autenticada em `/dashboard/badgeforge` e `/badgeforge/*`; paths buscados sob demanda (nunca nas props); gerar/prever/snippet continua 100% no navegador.
- Acesso a providers (F4, ADR-068/ADR-080): item público é lido anonimamente na API pública (sem token global em `.env`); PAT é do usuário pagador e só para item privado, cifrado, vinculado ao badge pessoal, e a URL leva apenas o ULID. Cache de provider por URL resolvida, payload projetado (sem descrição/README), condicional, stale-if-error e fallback 200 — nunca 500. `parser_class` é inerte; `client_key`/`extractor_key` resolvem clientes e métricas em código.
- Badges pessoais (F4, ADR-081): `/b/{username}/{ulid}.{svg|png}` stateless, só pago cria, arquivo em `storage/app/data/tools/badgeforge/personal/{username}/{ulid}.{ext}`; `?refresh=1` é ignorado — atualizar é `POST /badgeforge/badges/{id}/refresh` do dono; downgrade serve/copia/exclui mas bloqueia edição.
- Docs públicas do BadgeForge (F4, ADR-082): `/{locale}/docs/badgeforge`, namespace i18n `docs`, tabela gerada do catálogo, SEO/sitemap/rodapé; label/message da imagem são neutros de idioma (exceção documentada).
