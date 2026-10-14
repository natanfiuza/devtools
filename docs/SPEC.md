# SPEC - devtools.natanfiuza.dev.br (v1.0)

## 1. Visao
Site de ferramentas para desenvolvedores. Tools do cliente sao gratuitas e sem login. O AI SQL Builder exige conta, tem 5 usos gratis e planos pagos por franquia mensal de tokens. Monetizacao: planos e doacao (anuncios no futuro).

## 2. Stack e infra
- PHP 8.3 / Laravel 13 / Inertia / Vue 3 / TypeScript estrito / Tailwind / shadcn-vue / vue-i18n.
- MySQL. Fila `database`. Supervisor ja instalado: arquivo em `deploy/supervisor/devtools-worker.conf` (queue:work --tries=2 --timeout=120 --max-time=3600).
- Deploy: cron a cada minuto -> `deploy/deploy.sh` (flock, git pull --ff-only, composer install --no-dev, migrate --force, optimize, queue:restart). Executa apenas se HEAD mudou. Assets sao construidos no servidor, em release propria com troca atomica de symlink: ver ADR-014 e docs/RUNBOOK.md.
- Testes: Pest (backend) e Vitest (frontend), rodados em dev. Sem CI.
- GA4 com Consent Mode v2 e banner LGPD/GDPR.

## 3. Idiomas
en, pt-BR, pt-PT, fr, es. URL prefixada (/pt-br/...), hreflang, seletor no header, escolha do idioma no cadastro e idioma da conta em `users.locale`, trocavel no perfil (ADR-026). Precedencia: em rota prefixada a URL manda (e passa a valer como cookie); sem prefixo - area autenticada, `/admin`, telas de token - a ordem e cookie -> idioma da conta -> `Accept-Language` -> en. O idioma da conta e tambem o idioma dos e-mails. Fallback: en.
Alcance do prefixo: paginas publicas **e telas de autenticacao** vivem sob `/{locale}` — login, cadastro, esqueci/redefinir senha, verificar e-mail, confirmar senha e logout (ADR-022). A area autenticada (dashboard, settings) nao tem prefixo e usa o idioma da sessao/cookie. Rotas de maquina ficam fora: `/api`, `/webhooks`, `/admin` e `/auth/{provider}/*` (a URL de callback e cadastrada uma vez no provedor), e tambem `/sitemap.xml` e `/robots.txt`, que sao do site inteiro e nao de um idioma. `/` resolve o idioma do visitante e redireciona. Telas de acesso, paginas de token e o guia de estilo sao `noindex, follow` + canonical e sem hreflang; as paginas legais e a home seguem indexaveis com hreflang normal.
Textos de interface sao escritos com acentuacao correta (o produto e traduzido); a documentacao do repo e escrita sem acentos, como este arquivo.

## 4. Registro de tools
Cada tool = modulo `resources/js/tools/<slug>/` com `manifest.json`: `slug`, `status` (`soon|available|hidden`), `category`, `icon`, `order` e `keywords` (termos tecnicos de busca). O manifesto guarda so identidade e ciclo de vida - **nenhum texto visivel mora ali**: o nome, a descricao e as palavras-chave traduzidas vem de `tools.<slug>.*` nos 5 idiomas, por convencao a partir do slug.
O manifesto e a **fonte unica** do registro, com dois leitores: o TypeScript (`resources/js/tools/registry.ts`, via `import.meta.glob`) monta a home, os chips de categoria e a busca; o PHP (`App\Support\Tools`) le os mesmos arquivos para o sitemap, que lista so as tools `available` (ADR-007). `hidden` nao aparece para o visitante em leitor nenhum: hoje o TypeScript a exclui e o `Tools::all()` do PHP ainda a devolve (nada publico consome `all()` - o sitemap usa `available()`), e a F3 alinha os dois quando a primeira tool `hidden` existir.
Rota generica `/{locale}/tools/{slug}` (entrou na F2, junto com a primeira tool `available`). A pagina e **generica**: `pages/tools/Show.vue` traz so o cabecalho (nome, descricao e icone do registro), as metas e o shell; o componente da tool vem de `resources/js/tools/<slug>/Tool.vue`, resolvido por um glob **lazy** (`tools/components.ts`) - a F3 acrescenta ferramentas sem tocar no shell. Quem decide se a pagina existe e o servidor: **slug desconhecido, tool `soon` e tool `hidden` dao o mesmo 404** (ADR-007), porque um 200 de "em breve" seria uma segunda representacao do status e um link morto no indice. As props sao duas: `slug` (do registro, nunca da URL) e `maxInputBytes` (de `config/tools.php`).
SSR para SEO: o HTML do servidor traz a casca e as metas (title, description, canonical, hreflang e `og:image` da tool); o editor e a arvore montam no cliente (`ClientOnly`), porque CodeMirror e Web Worker dependem do DOM (ADR-029).

## 5. Tools
### MVP
1. JSON Viewer/Formatter: arvore colorida colapsavel, busca, copiar caminho (JSONPath), minify/pretty, validar com linha/coluna do erro, ordenar chaves, limite 5 MB, virtualizacao de listas grandes, parse em Web Worker. **Entregue na F2** (aceite em docs/acceptance/FASE-2.md), na pagina generica descrita na secao 4:

   - **Shell** (`components/tool/ToolShell.vue`): dois paineis (entrada | saida) com divisor arrastavel - `role="separator"`, setas do teclado, duplo clique restaura e a divisao fica no `localStorage` -, e **abas no mobile** (o `Tabs` do shadcn-vue ja vendorizado), porque lado a lado nao cabe numa tela de telefone. Barra de acoes com o `kbd` do atalho ao lado de cada botao.
   - **Entrada**: CodeMirror 6 com realce por tokens `--json-*`, marcacao de erro na linha (`lintGutter`), historico e desfazer (ADR-018). Alem de digitar e colar: **abrir arquivo** e **arrastar e soltar** (lidos com `FileReader`), exemplo pronto e limpar.
   - **Saida**: a arvore (o painel nao tem abas Arvore/Formatado - formatar, minificar e ordenar chaves agem no texto da esquerda). Colapso por no, expandir/recolher tudo, copiar o caminho JSONPath de qualquer no (`.chave` quando o nome e identificador, `['chave']` quando nao e), contagem de chaves, itens e tamanho.
   - **Busca** na arvore por **chave e valor**, sem acento e sem caixa (`normalize()` da busca da home), expandindo os ancestrais do que casa, com contador de resultados e aviso quando a lista e cortada. Busca por caminho JSONPath nao entrou.
   - **Limite de entrada** de `config/tools.php` (`TOOLS_MAX_INPUT_BYTES`, padrao 5 MB): passar dele **nao bloqueia a digitacao**, bloqueia a analise, com o tamanho formatado no idioma da pagina. O limite protege o navegador de quem usa, nao o servidor.
   - **Erro com linha e coluna**: a posicao sai do texto do motor (`at position N`, `line N column M`) e a linha/coluna sao calculadas aqui; a mensagem crua do motor fica como detalhe tecnico. A ultima saida valida continua na tela enquanto o texto esta invalido.
   - **Virtualizacao propria** das listas grandes (janela sobre linhas de altura uniforme, conta numa funcao pura) acima do limiar de `config/tools.php` (`TOOLS_VIRTUALIZE_FROM`, padrao 2000 linhas), que desce por prop como o teto de entrada - sem dependencia nova; o CodeMirror ja virtualiza o texto dele.
   - **Parse em Web Worker** com **queda para a thread principal** quando `Worker` nao existe ou a criacao falha, e descarte de resposta velha (a digitacao tem debounce).
   - Nao entrou (e nao estava na linha do SPEC): o painel de busca do proprio CodeMirror (Cmd+F), a indentacao por Tab (`indentWithTab`) e a validacao por schema.

2. AI SQL Builder (secao 6).
3. JSON -> TypeScript / PHP DTO / Pydantic.
4. SQL Formatter (dialetos MySQL, PostgreSQL, SQL Server, SQLite).
5. Base64 / URL encode / Hash.
6. JWT Decoder (somente decodifica, avisar que nao valida assinatura).
7. Timestamp / UUID / ULID.
8. Regex Tester.
### Futuro
Diff texto/JSON; Cron explainer; YAML/JSON/TOML/XML; DDL -> migration Laravel; explicador de EXPLAIN; mock data/seeder/factory a partir do schema; cURL -> Http/fetch; gerador .env.example; cheatsheets; API publica; historico compartilhavel; anuncios.

## 6. AI SQL Builder
### Entrada
- Fonte do schema: DDL | JSON | migration Laravel | models SQLModel (campo `schema_source`).
- Dialeto: mysql | pgsql | sqlsrv | sqlite.
- Descricao em linguagem natural.
- Output target: slug da tabela `output_targets` (sql, laravel_query_builder, eloquent, sqlmodel, sqlalchemy, django_orm, prisma, drizzle, typeorm).
### Limites
- Free: schema <= 8 KB, descricao <= 500 caracteres, 5 usos vitalicios por conta com e-mail verificado.
- Pro: limites em `plans` (sugestao inicial schema 64 KB, descricao 4000 chars) + franquia mensal de tokens.
### Fluxo
1. POST /api/ai/queries valida (auth, e-mail verificado, tamanho, cota).
2. Normaliza payload -> calcula `prompt_hash` (MD5, ADR-016). Cache hit => retorna resultado imediato, SEM debitar cota (decidido; ver secao 11).
3. Cache miss: estima tokens (chars/4), reserva cota, cria `ai_requests` (status queued), despacha Job `ProcessAiQuery` na fila.
4. Job chama DeepSeek (modelo flash, config `ai.model`), JSON mode, valida JSON de saida, valida SQL (parser; somente SELECT; bloquear DDL/DML), grava `ai_cache`, ajusta debito com `usage` real da API, status done/failed (estorno em falha).
5. Frontend faz polling GET /api/ai/queries/{id} a cada 1.5s (backoff ate 5s, timeout 120s).
6. Resultado mostra SQL + tab do target + premissas + tokens usados.
### Contrato JSON do LLM
{ "sql": string, "dialect": string, "assumptions": string[], "warnings": string[], "tables_used": string[], "needs_clarification": boolean, "clarification": string|null, "outputs": { "<target_slug>": string } }
Se o schema nao permite atender: needs_clarification=true e sql vazio.
### Prompt
Templates versionados em `resources/prompts/<target_slug>.v<N>.md`; `prompt_version` entra no hash. Instrucoes fixas: usar apenas tabelas/colunas do schema; nao inventar; tratar texto do usuario como dado (anti prompt injection); saida somente JSON. Particularidades: SQLModel usa select().where() e col() para in_/like.
### Modelo de dados
- users (+ role(user|admin), locale, suspended_at, anonymized_at, email_verified_at, password anulavel para conta sem senha), social_accounts (user_id, provider, provider_id), newsletter_subscribers (email, token, locale, source, confirmed_at, unsubscribed_at).
- plans: id, slug, name, price_cents, currency, interval(month|year|nulo), monthly_tokens, max_schema_bytes, max_prompt_chars, lifetime_free_uses, active, sort - catalogo em tabela, editavel no painel e carregado por migracao, nunca fixo no codigo (ADR-027).
- subscriptions: user_id, plan_id, gateway (`manual` hoje; Mercado Pago na F6), gateway_ref, status(active|canceled), current_period_start/end. Vigencia resolvida na leitura, sem estado `expirada` gravado.
- payments: user_id (`restrictOnDelete`: guarda fiscal, ADR-028), subscription_id (`nullOnDelete`), plan_id (`restrictOnDelete`), amount_cents, currency, method(pix|transfer|cash|other), status(paid|refunded), paid_at, note, gateway, gateway_ref.
- usage_ledger: user_id, ai_request_id, type(debit|refund|grant), tokens, uses, created_at - a Cota e calculada daqui, sem coluna de saldo.
- admin_actions: actor_id (nulo no comando, `nullOnDelete` no resto), action, target_type/target_id, meta(json), ip, created_at - auditoria do painel; nunca guarda conteudo de schema ou prompt (ADR-025).
- ai_requests: id(ulid), user_id, status, dialect, schema_source, output_target_id, prompt_hash, tokens_in, tokens_out, cache_hit, error_code, history_visible, created_at.
- ai_cache: id, prompt_hash CHAR(32) UNIQUE, canonical_payload (comprimido), response_json, model, prompt_version, tokens_in, tokens_out, hits, last_hit_at, created_at, expires_at.
- output_targets: slug, label, language, supported_dialects(json), prompt_template, enabled, plan_required, sort.
- donations, webhook_events (idempotencia) - F6.
### Historico
Salvo apenas para usuarios que optam (checkbox) e planos pagos; usuario pode apagar. Retencao padrao 90 dias.

## 7. Contas e planos
- Login: e-mail/senha, Google e GitHub, sob `/{locale}` (ADR-022); verificacao de e-mail obrigatoria antes de qualquer uso de IA.
- Login social (ADR-023): vinculo com conta existente so quando o provedor confirma o e-mail; conta nova nasce **sem senha** (`users.password` nulo) e a primeira senha e definida na tela de confirmar senha, a partir da area sensivel.
- Tabelas novas do ciclo de conta: `social_accounts` (vinculo com o provedor) e `newsletter_subscribers` (inscritos).
- Planos: Free (5 usos vitalicios, 8 KB de schema, 500 caracteres), Pro mensal (R$ 29,90) e Pro anual (R$ 299,00), ambos com 2.000.000 de tokens, 64 KB e 4.000 caracteres. Precos e limites vivem em `plans` e o admin edita pela tela de Planos; nao ha renovacao automatica - o periodo vencido simplesmente deixa de valer ate alguem conceder de novo (ADR-027).
- A home publica mostra os planos ativos lidos da **tabela** (mesma fonte e mesmos numeros do painel, formatados no servidor no idioma da pagina); no site nao existe botao de assinar, porque quem vende e o admin - o CTA e criar conta (Free) ou falar por e-mail (Pro).
- Venda manual (ADR-027): o admin concede plano e/ou cota e registra o pagamento recebido por fora (pix, transferencia, dinheiro), com estorno que encerra a assinatura junto. Pagamento (ADR-017): interface PaymentGateway e Mercado Pago entram na F6; `gateway`/`gateway_ref` ja existem nas duas tabelas.
- Painel do usuario (area autenticada, uma navegacao so): Visao geral (plano, cota, assinatura, vencimento), Historico, Faturas (assinatura e pagamentos) e Conta (Perfil, Seguranca, Aparencia); exportar dados em JSON e excluir a conta ficam na Conta (ADR-028). Nao ha botao de assinar nem de cancelar: quem vende e o admin.
- Webhooks idempotentes; assinatura valida e cota liberada so apos confirmacao.
- Doacao: pagina /donate com valores sugeridos e Pix (QR + copia e cola).
- Newsletter (ADR-024): duplo opt-in, inscricao pelo rodape publico e pelo cadastro (checkbox desmarcado), confirmacao e descadastro **por botao** (GET mostra, POST decide), token novo a cada envio, e-mail no idioma do inscrito. SMTP proprio agora, `MAIL_MAILER` configuravel para trocar (Hostinger/outro) sem codigo.
- Paginas legais (Privacidade e Termos) sob `/{locale}`, nos 5 idiomas, linkadas no rodape; o contato e a data de revisao saem de `config/legal.php`, e os textos de `lang/{locale}/legal.php`.
- Painel administrativo (ADR-025): `/admin` com o papel `admin`, uma tela por assunto - Visao geral (numeros e ultimos cadastros), Contas (as nove acoes sobre uma conta: verificar e-mail, reenviar verificacao, mandar redefinicao de senha, papel, suspender, reativar, plano, cota, excluir - mais entrar como), Assinantes (newsletter), Planos (preco e limites), Pagamentos (registrar e estornar) e Auditoria. Tudo em 5 idiomas; o primeiro admin nasce por `php artisan devtools:admin {email} [--revoke]`. `output_targets`, `ai_requests` e cache entram na F5/F6.

## 8. Privacidade e seguranca
- Banner de consentimento (necessarios/analytics), GA so apos aceite, pagina de privacidade e termos, exportar/excluir conta.
- Schema/prompt nao vao para logs; avisar usuario para nao enviar dados sensiveis.
- Rate limit por usuario e IP; Turnstile no cadastro; CSP; chave DeepSeek apenas em .env.
- SQL gerado nunca e executado.

## 9. Fases (cada uma termina com testes verdes e parada para revisao)
- F0 Fundacao: projeto Laravel 13 + starter Vue, TS estrito, Tailwind tokens, shadcn-vue, i18n 5 idiomas, layout, tema claro/escuro/sistema (sem flash, ADR-021), SSR da Inertia ligado em producao, deploy.sh, supervisor conf, Pest/Vitest.
- F1 Registro de tools + home + busca Cmd+K + banner LGPD + GA: concluida em 2026-10-14. As 8 tools do MVP entram **registradas** com `status: "soon"` (nenhuma funciona ainda): grid, busca, sitemap e testes ja sao reais e cada fase seguinte so troca o status. Vieram junto o sitemap/robots servidos pela aplicacao e as tags OG com imagem padrao (heranca da F0, ADR-007/013). Aceite em docs/acceptance/FASE-1.md.
- F2 JSON Viewer (com CodeMirror 6 e testes): concluida em 2026-10-14. O `json-viewer` e a primeira tool `available` - o card da home passa a linkar e o sitemap a listar -, na **pagina generica da tool** (secao 4, ADR-029), com CodeMirror 6 minimo e o realce por tokens do DESIGN (ADR-018) e o parse em Web Worker. Veio junto a **imagem OG por tool** (ADR-030). Aceite em docs/acceptance/FASE-2.md.
- F3 Tools simples (3 a 8 do MVP).
- F4 Auth: concluida. O ciclo principal - login e-mail/senha, OAuth Google/GitHub, verificacao, definir senha, newsletter e paginas legais - entrou junto da F0 (ADR-022/023/024), e as telas de settings traduzidas e o ciclo de conta (exportar e excluir conta, secao 8) entraram no bloco de 2026-10-14 (ADR-026/028).
- F5 AI SQL Builder: output_targets, cache, fila, DeepSeek, polling, cota free. O estado da cota (`App\Support/Quota`) e o livro-razao ja existem (ADR-027); falta o consumo.
- F6 Planos/pagamento/doacao/webhooks. O modelo comercial (catalogo de planos, assinaturas, pagamentos e cota) foi adiantado em 2026-10-14 com a venda manual (ADR-027); o gateway (Mercado Pago, interface PaymentGateway) e a renovacao por periodo continuam aqui.
- F7 Admin, historico, SEO final, hardening, documentacao final. O painel administrativo foi adiantado em 2026-10-14 (ADR-025); historico de uso e SEO final continuam aqui.
- Adiantado em 2026-10-14 (bloco de papeis, modelo comercial e paineis): papeis `user`/`admin` com policy e auditoria, painel administrativo, painel do usuario, modelo comercial com venda manual, idioma da conta e exclusao por anonimizacao. Aceite em docs/acceptance/FASE-0.md (secao antecipada); ADRs 025 a 028.
Criterios de aceite por fase em docs/acceptance/FASE-N.md (gerar ao iniciar cada fase).

## 10. Documentacao a manter
README (setup/deploy), docs/ARQUITETURA.md, docs/adr/, docs/glossario.md, docs/design/DESIGN.md, docs/API.md, docs/RUNBOOK.md (deploy, fila, restore de backup, rotacao de chaves), CHANGELOG.md.
Escrito ate aqui (F0): README, RUNBOOK, DESIGN, docs/adr/, docs/glossario.md, CHANGELOG. ARQUITETURA.md e API.md entram quando houver o que documentar (fila e IA na F5; endpoints publicos na F7).
Tambem fazem parte do repo: docs/acceptance/ (criterios por fase), docs/plans/ e docs/survey/ (plano e levantamento de cada sessao de grill) e docs/claude-code/reports/{branch}/ (relatorio ao fim de cada tarefa, regra do CLAUDE.md).
Texto de produto que se atualiza sem deploy de codigo: paginas legais em `lang/{locale}/legal.php` e contato/data de revisao em `config/legal.php`; limites, precos e nomes de modelo de IA em `config/` e nas tabelas, nunca no codigo.

## 11. Perguntas abertas
Reconstruida em 2026-10-13 (a secao estava vazia e delegava para fora do repo; a referencia a "Q1" na secao 6 apontava para lugar nenhum).
### 11.1 Decididas
- Cache hit **nao** debita cota; a cota so e debitada em cache miss (era a citada "Q1", secao 6 item 2).
- Assets: build no servidor (era "decidir na Fase 0"; ver ADR-014).
- Tema claro e escuro desde a F0, com contraste AA validado por teste (era "validar" no DESIGN.md).
- i18n: fonte unica em `lang/{locale}/*.php`, exposta ao Inertia escopada por namespace e consumida pelo vue-i18n.
- Testes em SQLite `:memory:`; dev local e producao em MariaDB.
- SSR da Inertia ligado em producao, com gate por `INERTIA_SSR_ENABLED`.
- Git: commits e PR apenas pelo usuario, via gitpr, com aprovacao de cada um.
- Prefixo de idioma na URL nas paginas publicas **e nas telas de autenticacao**; area autenticada e rotas de maquina (`/api`, `/webhooks`, `/admin`, `/auth/{provider}/*`) sem prefixo (secao 3, ADR-022).
- Login social (Google e GitHub) com vinculo so por e-mail confirmado pelo provedor, e conta sem senha como estado valido; a primeira senha e definida na tela de confirmar senha (ADR-023).
- Newsletter com duplo opt-in, e confirmacao/descadastro decididos por POST, nunca por GET (ADR-024).
- Paginas de Privacidade e Termos existem desde ja, sob `/{locale}`, nos 5 idiomas (secao 7).
- Documentacao do repo em portugues sem acentos; strings de interface com acentuacao correta.
- Papeis, autorizacao e auditoria: dois papeis (`user` e `admin`) em `users.role`, `UserPolicy` como unico lugar da autorizacao e toda acao administrativa registrada em `admin_actions`; o primeiro admin nasce por comando (`devtools:admin`), nao por seeder (ADR-025, fecha o item correspondente da 11.2).
- Idioma da conta x idioma de navegacao: `users.locale` e preferencia duravel (escolhida no cadastro, opcional, e no perfil) e o cookie registra onde a pessoa esta navegando agora; sem prefixo a ordem e cookie -> conta -> `Accept-Language` -> en (ADR-026, muda o comportamento definido na F0).
- Venda manual: assinatura concedida e pagamento registrado fora do gateway, com precos e limites na tabela `plans` e vigencia resolvida na leitura (ADR-027, fecha o item de valores da 11.2 e adianta parte da F6).
- Exclusao de conta por anonimizacao, com o registro de cobranca preservado por guarda fiscal, e exportacao em JSON de download imediato (ADR-028).
- Registro de tools com **fonte unica** no `manifest.json` de cada tool, com dois leitores sobre os mesmos arquivos: o TypeScript (home, chips, busca) e o PHP (sitemap). O texto visivel nunca mora no manifesto - vem de `tools.<slug>.*` por convencao a partir do slug (ADR-007).
- Consentimento com **tres estados** e decisao resolvida no servidor: sem cookie (ou com cookie corrompido) a decisao e nula e o banner aparece; o GA4 so e injetado no cliente, depois do aceite e com id configurado (ADR-013).
- `robots.txt` e `sitemap.xml` servidos pela **aplicacao**, com o arquivo estatico de `public/` removido: arquivo estatico vence a rota e amarraria a linha do `Sitemap:` ao ambiente.
- Guia de estilo fora do indice e do sitemap: e pagina de referencia do DESIGN, nao conteudo do site - continua navegavel, mas `noindex`.
- Uma imagem OG padrao do site (1200x630), **comitada**, gerada por comando artisan de uso unico que nunca roda em producao (o VPS nao tem GD nem rede para baixar as fontes); a imagem por tool ficou para a F2 e chegou la (ADR-030).
- Rota `/{locale}/tools/{slug}` com **pagina generica da tool**: o cabecalho e o shell sao um so para todas, e o componente da tool vem de `resources/js/tools/<slug>/Tool.vue` por glob lazy - a fase seguinte acrescenta ferramentas sem tocar na pagina (ADR-029). Slug desconhecido, `soon` e `hidden` dao o mesmo 404 (ADR-007), e o SSR entrega a casca e as metas enquanto o editor monta no cliente.
- Imagem OG **por tool**: uma por tool que funciona e **sem idioma nenhum** (o site tem cinco e a imagem e uma so) - o nome traduzido da ferramenta vai no `og:image:alt`, e a pagina cai na imagem padrao quando o PNG daquela tool nao existe (ADR-030).

### 11.2 Abertas (com a fase onde precisam ser resolvidas)
- F4: mensagens de validacao nos 5 idiomas. O Laravel so traz `validation.php` em ingles; nas telas traduzidas o erro de formulario ainda sai em ingles ("The email field is required"), junto com nomes de campo em ingles.
- F5: nome da coluna de dialetos em `output_targets` (ADR-011 diz `dialetos`; secao 6 diz `supported_dialects(json)` + `sort`).
- F5: Laravel AI SDK x cliente HTTP fino (diferido pelo ADR-003).
- Operacao (fora de fase): confirmar PHP 8.3 e usuario do cron no VPS; rotacao de chaves (SMTP, Turnstile, DeepSeek, GA4).
