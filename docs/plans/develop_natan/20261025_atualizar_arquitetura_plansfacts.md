# Plano: atualizar docs/ARQUITETURA.md

- Data: 2026-10-25
- Branch: `develop_natan`
- Origem: sessão `/grill-with-docs` com o pedido "atualize o arquivo
  `docs/ARQUITETURA.md`".
- Survey: `docs/survey/20261025_atualizar_arquitetura_surveyfacts.md`.

## Objetivo

Deixar o ARQUITETURA.md fiel ao runtime atual, em português com acentos, com
fontes canônicas linkadas e sem duplicar contrato HTTP, operação ou produto.
Corrigir apenas os fatos errados no CHANGELOG e no glossário, preservando o
estilo sem acentos dos dois.

## Estrutura do ARQUITETURA.md

1. `# Arquitetura` + intro: 10 tools; oito só no navegador; AI SQL Builder,
   Base64 Image e Contato com servidor; parágrafo de fontes canônicas.
2. `## Mapa das superfícies do servidor`: tabela com AI web, API v1,
   pagamentos/webhooks, Base64 Image, Contato, Admin, i18n/prompts, SEO,
   deploy/operação, contas/planos/cota, segurança.
3. `### Base64 Image (ADR-055)`: processamento, limites, arquivos, retenção,
   download direto, histórico e ausência de cota.
4. `### Contato`: parágrafo sobre `contact_messages`, rate limit, honeypot e
   PII.
5. `## Visão em uma tela`: diagrama ASCII da IA com locale, hash, dedupe,
   cache, fila, contrato, Gate do SQL, liquidação e histórico.
6. `## Fronteiras da IA`: tabela de camadas, incluindo API v1.
7. `## Ciclo de uma solicitação`: POST e ordem real, reserva/cota ADR-054,
   fila, provedor/contrato, liquidação, polling e histórico/retenção.
8. `## Cache`: hash, TTL, hit sem débito, dois casos de linha removida, cache
   global e visão do admin.
9. `## API pública v1`: duas portas, tabela dos cinco endpoints, PAT, escopo
   por token e links para `API.md`/`openapi.yaml`.
10. `## Fila e tempos`: 60/90/120/150 e `ai:reconcile` como `failed` +
    `error_code=timeout`.
11. `## Estados e falhas da IA`: tabela de estados e de códigos, com a nota
    sobre `invalid_response`.
12. `## Pagamentos e webhooks`: quatro caminhos, diagrama, camadas, período e
    cota, ciclo da preapproval, estornos, webhooks, doações e operação.
13. `## Segurança HTTP`: fatos atuais, acentos e links para ADR-052/RUNBOOK.
14. `## O que nunca sai daqui`: logs, corpo do provedor, SQL, cache,
    histórico, PAT, segredo do gateway, PII de webhook, doação e Base64.

## Correções pontuais

### CHANGELOG.md

- Remover `StartPreapproval` da linha de checkout; citar `StartCheckout` com
  `mode=one_time|recurring` em `POST /subscribe/checkout`.
- Explicar que `tries=3` com backoff `[10,60,300]` usa apenas 10 s e 60 s.
- Trocar "a cota lê a janela da assinatura vigente" pela cota acumulativa da
  ADR-054.
- Não criar seção pós-F7; preservar o estilo sem acentos.

### docs/glossario.md

- `Solicitacao de IA` e `Privacidade da IA`: payload canônico apagado no
  terminal, mas `title`/`prompt`/`schema` ficam em claro até 90 dias quando o
  histórico está visível.
- `Painel administrativo`: incluir Assinaturas, Eventos de webhook, Alvos de
  saída e Cache de IA.
- `Historico`, `Retencao do historico` e `Apagar historico`: explicitar os
  campos em claro e que a poda não toca em ledger nem cache.
- Preservar o estilo sem acentos.

## Verificação

- `rg` para termos obsoletos: `StartPreapproval`, `subscribe/recurring`,
  `outras oito`, `soma do usage_ledger`, `janela da assinatura vigente`,
  `gate lexical`, status `timeout`.
- Conferir todos os links relativos a partir de `docs/`.
- Conferir diagramas ASCII com até ~90 colunas e sem box drawing Unicode.
- `git diff --check` nos três arquivos.
- Revisão visual do diff; não rodar `npm run format` para não reformatar o
  CHANGELOG.
- Não há suíte de documentação; `composer test`/`npm run test` não se aplicam
  a uma mudança só de docs.

## Riscos

- O upgrade via gateway mantém a linha antiga ativa até `current_period_end`;
  documentar como comportamento atual, não como invariante.
- Aprovação tardia de checkout vencido ainda abre período.
- `SPEC.md`, `API.md`, `openapi.yaml` e `DESIGN.md` têm drift próprio; fora do
  escopo.
- Nenhum commit ou push.

## Ordem de execução

1. Salvar survey e este plano.
2. Salvar o plano em `docs/claude-code/plans/`.
3. Reescrever `docs/ARQUITETURA.md`.
4. Corrigir `CHANGELOG.md` e `docs/glossario.md`.
5. Verificar termos, links, diagramas e diff.
6. Gerar o relatório em `docs/claude-code/reports/`.
