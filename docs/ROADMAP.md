# ROADMAP — Programa Futuro: tools e frentes de produto

Versão: 1.2
Data: 2026-11-25
Fonte de verdade da seção "Futuro" da [SPEC](./SPEC.md) (ADR-179). Sem datas: ordem + porte.

## 1. Propósito e escopo

O Programa Futuro organiza as doze candidatas da SPEC §5 — **nove tools novas** e **três frentes de
produto** — em ondas temáticas e sete fases, com critérios de prioridade, escopo curto e um protocolo de
fase único.

O que este documento **não** é:

- não é spec de implementação: cada fase tem prompt, levantamento, plano e aceite próprios;
- não é cronograma: não há datas, apenas ordem recomendada e porte relativo (S/M/L);
- não escolhe bibliotecas: qualquer dependência é decidida no ADR da fase, com peso no bundle, alternativa
  nativa e manutenção registrados.

Relação com os outros documentos: a [SPEC](./SPEC.md) §5, §9 e §10 apontam para cá; cada fase produz
`docs/acceptance/FUTURO-FASE-N.md`; toda decisão relevante vira ADR em `docs/adr/` (a partir de 180 para
as fases; a ADR-179 registra o programa).

Regras herdadas, em uma linha cada:

- Tool client-side roda 100% no navegador; nenhuma requisição ao servidor. O `share-text` existente é a
  exceção de produto declarada, e o histórico compartilhável (F5) mexe nele, não nas tools de texto.
- URL sempre plana: `/{locale}/tools/{slug}`. Sem `group`; um agrupamento só existe com hub de verdade,
  como o Colors, e exigiria ADR próprio.
- Nenhum manifesto `soon` é criado por este roadmap: a tool entra no registro quando a fase começa.
- A ordem das ondas é recomendação, não bloqueio; só as fases de produto (F5–F7) têm gate de entrada.
- Toda string visível via i18n nos cinco idiomas; nada de texto hardcoded.
- Sem commit, push ou deploy por parte do agente: o usuário commita pelo gitpr.

Fora do programa: executar código do usuário no servidor, nova capacidade de IA, app mobile e conta/plano
novo fora do que já existe.

## 2. Critérios de priorização

A ordem abaixo é fixa e qualitativa — sem score numérico e sem pesos. Cada fase registra no seu
levantamento a evidência de demanda e de reuso.

| # | Critério | Pergunta que responde | Efeito na ordem |
| --- | --- | --- | --- |
| 1 | Reuso de infra de cliente | Quanto de `ToolShell`, `CodeEditor`, `FileDropzone`, worker, `lib/file`, `download` e `clipboard` entra sem mudança? | Mais reuso, mais cedo |
| 2 | Demanda real/busca | Há termo de busca ou uso comparável que justifique a tool? Evidência no survey da fase | Demanda comprovada sobe |
| 3 | Custo só-cliente | Roda 100% no navegador, sem servidor, cota ou retenção? | Sim = mais cedo |
| 4 | Sinergia | Compartilha parser ou domínio com tool existente (DDL ↔ AI SQL Builder; diff ↔ share-text)? | Sinergia sobe |
| 5 | Risco | Segurança, privacidade, legal ou produto? | Risco alto desce ou exige grill |
| — | Porte (desempate) | S antes de M antes de L quando os critérios empatam | Só desempate |

O porte nunca é o critério principal: uma tool barata que não atende demanda não sobe por ser barata.

## 3. Ondas e fases

### 3.1 Ondas

| Onda | Tema | Fases | Por que estão juntas |
| --- | --- | --- | --- |
| 1 | Conversão e comparação | F1 | Mesma infra de editor, arquivo e worker; nada de servidor |
| 2 | CLI e ambiente | F2 | Texto → texto, cópia primeiro, três tools pequenas |
| 3 | Banco e schema | F3 | Parser mais pesado do programa; sinergia com o vocabulário de DDL/dialetos do AI SQL Builder |
| 4 | Referência | F4 | Conteúdo, pouco código; fase elástica que pode ser adiantada |
| 5 | Produto | F5–F7 | Servidor, conta e negócio; cada uma com gate próprio |

### 3.2 Fases

| Fase | Onda | Escopo | Ferramentas/frente | Porte | Condição | Status |
| --- | --- | --- | --- | --- | --- | --- |
| F1 | 1 | Conversores e diff | `data-format-converter`, `diff` | L | — | concluída em 2026-11-24 ([aceite](../acceptance/FUTURO-FASE-1.md)) |
| F2 | 2 | CLI e ambiente | `cron-explainer`, `curl-to-code`, `env-example` | M | — | concluída em 2026-11-25 ([aceite](../acceptance/FUTURO-FASE-2.md)) |
| F3 | 3 | Banco e schema | `ddl-to-migration`, `schema-mock-data`, `sql-explain` | L | — | planejada |
| F4 | 4 | Referência | `cheatsheets` | S | — | planejada |
| F5 | 5 | Histórico compartilhável | extensão do `share-text` | M | grill + ADR de entrada | planejada |
| F6 | 5 | API pública v2 | frente de produto (sem tool) | L | grill + ADR de entrada | planejada |
| F7 | 5 | Anúncios | frente de produto (sem tool) | M | grill + ADR de entrada | planejada |

Definição de porte:

- **S** — uma transformação sobre o shell, sem parser novo (ex.: cheatsheets);
- **M** — parser ou várias saídas em uma tool, ou duas a três tools pequenas na mesma fase;
- **L** — motor/parser novo com várias tools, ou superfície de servidor.

Os slugs são provisórios: o manifesto da fase congela o slug, a categoria, o ícone e a ordem no catálogo.

### 3.3 Escopo por ferramenta

Cada fase decide os detalhes no próprio prompt/plano; aqui ficam o propósito, os limites e o que já se sabe
de reuso. Bibliotecas aparecem apenas como **candidatas a avaliar no ADR da fase**.

#### F1 — Conversores e diff (Onda 1, porte L)

**`data-format-converter`** — converter entre JSON, YAML, TOML e XML, escolhendo formato de entrada e de
saída na própria tela.

- Dentro: colar ou abrir arquivo com o limite do shell; diagnóstico de erro por linha/coluna quando o
  parser permitir; worker para entradas grandes; copiar e baixar o resultado.
- Fora: buscar URL remota, fazer upload, validar contra JSON Schema, prometer preservação de comentários ou
  ordem do YAML, converter vários arquivos de uma vez.
- Reuso: `ToolShell`, `CodeEditor`, `FileDropzone`, `lib/worker`, `lib/file`, `download`, `clipboard`.
- Candidatas a avaliar no ADR da fase: parser de YAML e de TOML (XML usa `DOMParser` nativo).

**`diff`** — comparar dois textos ou dois JSONs.

- Dentro: modo texto (diferenças linha a linha) e modo JSON (comparação semântica por caminho, sem exigir
  a mesma ordem de chaves); entrada por colar ou arquivo; navegação entre diferenças; copiar e baixar o
  relatório; worker para entradas grandes.
- Fora: persistir ou compartilhar a comparação (F5 é outro assunto), aplicar patch, merge de três vias,
  diff de diretórios, de repositório ou de binário.
- Reuso: os mesmos componentes da fase; o modo JSON pode dividir o analisador com o conversor.
- Candidatas a avaliar no ADR da fase: nenhuma — o algoritmo de diff pode ser escrito à mão.

#### F2 — CLI e ambiente (Onda 2, porte M)

**`cron-explainer`** — explicar uma expressão cron e mostrar as próximas execuções.

- Dentro: explicação campo a campo, próximas execuções no fuso exibido na tela, diagnóstico de expressão
  inválida.
- Fora: cobrir systemd timers, editar ou instalar crontab, executar comando.
- Reuso: shell e copiar; sem editor pesado.
- Candidatas a avaliar no ADR da fase: parser de cron próprio ou biblioteca.

**`curl-to-code`** — converter um comando cURL colado em código.

- Dentro: gerar cliente HTTP Laravel (`Http::...`), `fetch` e `axios`, preservando método, cabeçalhos,
  corpo e query; listar opções não suportadas em vez de ignorar em silêncio; copiar o resultado.
- Fora: executar a requisição, ser um parser completo de shell, ler caminhos de arquivo locais, guardar
  cookies ou segredos.
- Reuso: `CodeEditor` nos dois lados e copiar.
- Candidatas a avaliar no ADR da fase: nenhuma obrigatória; um tokenizador de shell mínimo pode ser próprio.

**`env-example`** — gerar um `.env.example` a partir de um `.env` colado.

- Dentro: preservar ordem, comentários e agrupamentos; mascarar valores sensíveis; avisar o que foi
  mascarado.
- Fora: validar contra esquema do projeto, guardar valores, buscar segredos em serviço remoto.
- Reuso: shell, arquivo e copiar.
- Candidatas a avaliar no ADR da fase: nenhuma.

#### F3 — Banco e schema (Onda 3, porte L)

**`ddl-to-migration`** — transformar DDL `CREATE TABLE` em migration Laravel.

- Dentro: DDL colado (MySQL na primeira versão; dialetos adicionais declarados na fase), `Schema::create`
  com colunas, índices e chaves estrangeiras, nomes revisáveis na tela.
- Fora: conectar em banco, rodar migration, cobrir views/triggers/procedures/grants, fazer o caminho
  inverso (migration → DDL), gerar modelos de ORM.
- Reuso: `CodeEditor`, worker e copiar; o vocabulário de DDL/dialetos dialoga com o AI SQL Builder, sem
  compartilhar código de servidor.
- Candidatas a avaliar no ADR da fase: parser SQL/DDL (extensão existente ou parser próprio limitado).

**`schema-mock-data`** — gerar factory/seeder a partir do schema.

- Dentro: dados determinísticos por tipo, respeitando chaves estrangeiras e unicidade; saída como factory e
  seeder Laravel.
- Fora: inserir em banco, cobrir procedures, gerar dado sensível real.
- Reuso: o mesmo parser de DDL da fase e o editor.
- Candidatas a avaliar no ADR da fase: gerador de dados falsos (ou tabela própria por tipo).

**`sql-explain`** — explicar um plano de execução colado.

- Dentro: explicar nós, acesso (full scan, índice), ordenação temporária e avisos, a partir de plano texto
  ou JSON do MySQL na primeira versão.
- Fora: conectar no banco, coletar plano em tempo real, otimizar a consulta automaticamente.
- Reuso: shell e editor.
- Candidatas a avaliar no ADR da fase: nenhuma obrigatória.

#### F4 — Cheatsheets (Onda 4, porte S)

**`cheatsheets`** — referência rápida com grupos (Git, SQL, HTTP, shell e outros definidos na fase), busca
e cópia, conteúdo nos cinco idiomas.

- Dentro: páginas de referência indexáveis, busca local e copiar trechos.
- Fora: conteúdo do usuário, trechos executáveis, acompanhar versão de ferramenta externa.
- Reuso: shell, copiar e i18n; o custo real é conteúdo e tradução, não código.
- Candidatas a avaliar no ADR da fase: nenhuma.

#### F5 — Histórico compartilhável (Onda 5, produto, porte M)

Estende o precedente do `share-text` (rota `/tools/share-text/{id}` reusando o `ToolController`) para
histórico opt-in com retenção e exclusão. Só começa com grill de produto + ADR de entrada; até lá, este
roadmap não define audiência, retenção, o que é exportado nem o que é excluído.

- Fora (até o grill/ADR): feed público, analytics de visitante, acesso entre contas.

#### F6 — API pública v2 (Onda 5, produto, porte L)

Versão nova da API pública além da v1 (Sanctum, abilities, cota do plano, rate limits), documentada em
`docs/API.md` e `docs/openapi.yaml`. Não é tool: sem slug, manifesto ou entrada no registro.

- Fora: quebrar a v1, nova capacidade de IA, página pública de documentação sem ADR, GraphQL.

#### F7 — Anúncios (Onda 5, produto, porte M)

Frente de produto, não tool: posições, consentimento, política e configuração. Só com grill + ADR de
entrada. A SPEC §8 hoje afirma que os "sinais de anúncio" estão desligados no `config` (ADR-032) e a
Privacidade diz que não há anúncios — a fase revisita as duas coisas.

- Fora: segmentação comportamental, script de terceiro antes do consentimento, escolher a rede de anúncios
  agora.

## 4. Protocolo de fase

### 4.1 Entrada

Prompt da fase + levantamento em `docs/survey/{YYYYMMDD}_{slug}_surveyfacts.md` + plano em
`docs/plans/{branch}/{YYYYMMDD}_{slug}_plansfacts.md`; cópia do plano em
`docs/claude-code/plans/{branch}/{YYYYMMDD}_{branch}_{taskname}.md`. Se a fase abrir uma decisão de produto
nova, o grill vem antes de qualquer código.

### 4.2 Implementação

Reusar a infra compartilhada antes de criar componente novo (ver §3.3). Nenhuma dependência entra sem o ADR
da fase. Nenhuma tool client-side faz requisição ao servidor, e nenhum conteúdo de entrada vai para log.

### 4.3 Registro, i18n e SEO

- `resources/js/tools/<slug>/manifest.json` + `Tool.vue`; ícone em `resources/js/tools/icons.ts`; categoria
  em `resources/js/tools/types.ts`/`categories.ts`; slug pinado em
  `tests/Feature/Tools/ToolsRegistryTest.php`.
- Chaves `tools.<slug>.name/description/keywords/intro` nos cinco idiomas (nome da tool em inglês, como as
  demais); nenhum texto hardcoded.
- OG via `php artisan seo:og-image` (ADR-030); sitemap segue `available` automaticamente; `ToolProps.php`
  só se a tool precisar de dado do servidor (ADR-043) — o que nenhuma fase client-side deve precisar.
- URL plana `/{locale}/tools/{slug}`; sem `group`.

### 4.4 Gates

`composer test`, `npm run test`, `npm run types:check`, `npm run format:check`, `npm run lint:check`,
`npm run build` — com resultados reais registrados no aceite, sem resultados inventados.

### 4.5 Aceite

`docs/acceptance/FUTURO-FASE-N.md` com: escopo; fora de escopo; decisões (ADRs); arquivos tocados; matriz
requisito → implementação → teste; gates executados; checklist de i18n, SEO e registro; pendências por
gravidade; evidências; status.

### 4.6 ADR e relatório

ADR quando houver decisão com alternativas reais (dependência, dialeto, retenção); relatório detalhado em
`docs/claude-code/reports/{branch}/{YYYYMMDD}_{branch}_{taskname}.md`.

### 4.7 Parada

Ao fim da fase: sem commit, push ou deploy; o usuário revisa e commita pelo gitpr.

## 5. Fases de produto (F5–F7)

Critérios de entrada — todos obrigatórios:

1. grill de produto registrado no survey/plansfacts da fase;
2. ADR de entrada cobrindo modelo de produto, retenção/privacidade, planos e preço, métrica de sucesso,
   rollout/rollback e impacto legal;
3. as perguntas abertas da SPEC §11.2 respondidas e movidas para §11.1;
4. só então o plano técnico.

Se o grill concluir que não deve existir, a fase fica **cancelada** no roadmap — o ID nunca é reutilizado
nem renumerado.

Perguntas que o roadmap deliberadamente **não** decide:

- **F5 — histórico compartilhável**: o que um link mostra; público como o `share-text` ou restrito à conta;
  retenção; opt-in; o que entra na exportação e na exclusão da conta; moderação e planos.
- **F6 — API v2**: escopo e abilities; se a v1 é depreciada ou coexiste; rate limits; documentação pública
  (padrão da ADR-050); política de compatibilidade.
- **F7 — anúncios**: formato e posição; se assinante fica sem anúncio; consentimento/LGPD; efeito em CSP e
  nos cookies; revisão da SPEC §8 e da Política de Privacidade; receita esperada.

## 6. Manutenção do roadmap

- **Reordenar ondas ou fases**: livre, com linha datada em §7; não exige ADR.
- **Mudar o escopo interno de uma fase**: emenda datada na subseção da fase, antes do aceite.
- **Concluir uma fase**: status `concluída em AAAA-MM-DD` + link do aceite; o escopo não é apagado.
- **Novo candidato**: entra numa onda existente ou no backlog, com porte e nota de critério.
- **Decisões fixas** (critérios, URL plana, nenhuma lib no roadmap, gates de produto, sem `soon`): só mudam
  por ADR que emende ou substitua a ADR-179.

## 7. Revisões

| Versão | Data | Mudança | ADR |
| --- | --- | --- | --- |
| 1.0 | 2026-11-24 | criação do Programa Futuro (5 ondas, 7 fases, protocolo) | ADR-179 |
| 1.1 | 2026-11-24 | F1 concluída: conversor de formatos e diff entregues, com o aceite registrado | ADR-180 |
| 1.2 | 2026-11-25 | F2 concluída: explicador de cron, cURL para código e `.env` para exemplo, com o aceite registrado | ADR-181 |
