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
- Deploy: cron executa deploy/deploy.sh a cada minuto. Migrations devem ser retrocompativeis (sem downtime).
- GIT: nunca faca commit nem push. O usuario commita e abre PR pelo gitpr, aprovando cada um. Se um commit for necessario, peca intervencao manual do usuario e pare.
- Ao finalizar qualquer tarefa, gerar relatório detalhado em `docs/claude-code/reports/{branch}/{dataatual}_{branch}_{taskname}.md`, onde {dataatual} deve ser substituida pelo data atual, e onde {branch} deve ser substituida pela branch atual, onde {taskname} deve ser uma descricao curta da tarefa, sem espaços ou caracteres especiais, apenas letras minusculas, separados por underscore.
- Toda vez que um plano de implementação for gerado ele deve ser salvo em `docs/claude-code/plans/{branch}/{dataatual}_{branch}_{planname}.md`, onde {dataatual} deve ser substituida pelo data atual, e onde {branch} deve ser substituida pela branch atual, onde {planname} deve ser uma descrição curta do plano, sem espaços ou caracteres especiais, apenas letras minusculas, separados por underscore.
