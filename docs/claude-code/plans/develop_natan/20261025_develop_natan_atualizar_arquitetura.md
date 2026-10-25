# Plano de implementação: atualizar arquitetura

- Data: 2026-10-25
- Branch: `develop_natan`
- Plano da sessão: `docs/plans/develop_natan/20261025_atualizar_arquitetura_plansfacts.md`
- Survey: `docs/survey/20261025_atualizar_arquitetura_surveyfacts.md`

## Contexto

O `docs/ARQUITETURA.md` está parado no commit `5875d42` (2026-10-22) e não
reflete ADR-051 (histórico), ADR-054 (cota acumulativa), ADR-055 (Base64 Image
no servidor), ADR-056 (locale em prompts/cache), a página de contato e as
correções recentes do Mercado Pago. O objetivo é atualizar o documento sem
duplicar as fontes canônicas, converter o texto para português com acentos e
corrigir os mesmos fatos no CHANGELOG e no glossário.

## Escopo

Arquivos alterados:

- `docs/ARQUITETURA.md` — reescrita.
- `CHANGELOG.md` — três correções factuais.
- `docs/glossario.md` — quatro correções factuais.

Artefatos gerados:

- `docs/survey/20261025_atualizar_arquitetura_surveyfacts.md`
- `docs/plans/develop_natan/20261025_atualizar_arquitetura_plansfacts.md`
- Este arquivo.
- `docs/claude-code/reports/develop_natan/20261025_develop_natan_atualizar_arquitetura.md`

## Etapas

1. **ARQUITETURA.md**: reescrever em português com acentos, com:
   intro das 10 tools e fontes canônicas; mapa das superfícies; Base64 Image e
   Contato; diagrama da IA e diagrama de pagamentos; ciclo da IA em sete
   passos; cache; API v1; fila/tempos; tabela de estados e falhas; pagamentos
   em quatro caminhos, período/cota, preapproval, estornos, webhooks, doações
   e operação; segurança HTTP; o que nunca sai daqui.
2. **CHANGELOG.md**: remover `StartPreapproval`; corrigir o backoff; trocar a
   frase da cota pela ADR-054. Manter o estilo sem acentos.
3. **Glossário**: ajustar as entradas de solicitação/privacidade, painel
   administrativo e histórico. Manter o estilo sem acentos.
4. **Verificação**: greps de termos obsoletos, checagem de links relativos,
   largura dos ASCII, `git diff --check` e revisão visual.
5. **Relatório**: registrar o que mudou, os fatos verificados, os riscos e a
   ausência de commit/push.

## Fatos que guiam a reescrita

- Ordem do POST: dialeto antes da transação; locale → prompt → payload/hash →
  estimativa; na transação: lock → dedupe → cache → limites → reserva →
  criação; dispatch após commit.
- Cota sem coluna de saldo; ADR-054 acumulativa com rollover; Free 5 usos.
- Histórico opt-in, pago, campos em claro por até 90 dias; payload canônico
  apagado no terminal.
- API v1 com cinco endpoints e escopo por token.
- `ai:reconcile` marca `failed` + `error_code=timeout`; não existe status
  `timeout`.
- `invalid_response` é motivo de log, não código gravado.
- Pagamentos em quatro caminhos; sem conciliação; `StartCheckout` cobre
  `one_time` e `recurring`; não existem `StartPreapproval` nem
  `/subscribe/recurring`.
- Estornos e preapproval conforme ADR-049.
- Base64 Image e Contato conforme ADR-055 e código atual.

## Critérios de aceite

- Nenhum termo obsoleto no ARQUITETURA.md.
- Links relativos resolvem a partir de `docs/`.
- CHANGELOG e glossário sem acentos e sem seção pós-F7.
- Diagramas ASCII legíveis, sem Unicode de caixa.
- Relatório final gerado.
- Nenhum commit ou push.
