# Survey — Formatação de Retenção do Histórico em Dias (Base64 Image) (2026-10-26)

> Levantamento automático da sessão de grill (`/grill-with-docs`) — fronteira esgotada e entendimento compartilhado confirmado pelo usuário.

## 1. Contexto da tarefa
- **Pedido original:** Em Base64 Image Encoder / Decoder quando um arquivo é convertido uma mensagem "Histórico disponível por 720h" está mostrando em horas e deve mostrar em dias quando for maior que 24 horas.
- **Tela / Módulo:** Tool Base64 Image Encoder / Decoder (`resources/js/tools/base64-image/EncoderPanel.vue`).
- **Skill:** `/grill-with-docs` (grilling + domain-modeling) · branch `develop_natan`
- **Método:** 1 rodada de grill com 3 decisões técnicas alinhadas, investigação exaustiva prévia de código e internacionalização nos 5 idiomas suportados.
- **Arquivos-chave lidos:**
  - `resources/js/tools/base64-image/EncoderPanel.vue`
  - `resources/js/tools/base64-image/HistoryPanel.vue`
  - `resources/js/tools/base64-image/Tool.vue`
  - `resources/js/tools/base64-image/lib/formatters.ts`
  - `resources/js/__tests__/base64-image-component.test.ts`
  - `app/Support/Base64Image/Base64ImageToolProps.php`
  - `config/tools.php`
  - `lang/{en,es,fr,pt_BR,pt_PT}/tools.php`

## 2. Decisões das rodadas

### Rodada 1 — Formatação, Cálculo e Arquitetura de Componentes
| # | Decisão | Resolução |
|---|---|---|
| Q1 | Formatação em dias e chaves de i18n | **Opção 1**: Exibir por extenso (`"Histórico disponível por {days} dias"` e singular `"Histórico disponível por {days} dia"`), criando as chaves `retention_info_days` e `retention_info_day` nos 5 idiomas (`pt_BR`, `pt_PT`, `en`, `es`, `fr`), mantendo `retention_info` (`{hours}h`) para durações <= 24 horas. |
| Q2 | Cálculo e arredondamento dos dias | **Opção 1**: `Math.round(hours / 24)` quando `hours > 24`, convertendo 720h para exatamente 30 dias. |
| Q3 | Estrutura da função e cobertura de testes | **Opção 1**: Implementar `formatRetentionTime(hours: number, t: (key: string, params?: Record<string, unknown>) => string): string` em `resources/js/tools/base64-image/lib/formatters.ts`, com testes unitários em `formatters.test.ts` e validação nos testes de integração em `base64-image-component.test.ts`. |

### Defaults assumidos (não contestados)
- Quando `retentionHours <= 24`, a exibição permanece inalterada em horas utilizando `tools.base64-image.retention_info` (ex.: "24h").
- Nenhuma alteração estrutural no backend (`Base64ImageToolProps.php` continua repassando `retentionHours: 720` no plano pago e `24` no plano gratuito).
- O componente `EncoderPanel.vue` consumirá a função utilitária `formatRetentionTime`.
- Não há necessidade de novos ADRs, pois a alteração é puramente de apresentação no frontend e não envolve decisões arquiteturais de difícil reversão.

## 3. Relatório de fatos levantados
### Localização e Fluxo do Valor de Retenção
- `config/tools.php` define `free_retention_hours => 24` e `paid_retention_days => 30`.
- `Base64ImageToolProps.php` injeta no frontend:
  ```php
  'retentionHours' => $isPaidPlan ? $paidDays * 24 : $freeHours,
  ```
- O frontend recebe `retentionHours` através das props do componente (`Base64ImageToolProps`).
- Em `resources/js/tools/base64-image/EncoderPanel.vue` (linha 298), a mensagem de rodapé de conversão exibe:
  ```html
  {{ t('tools.base64-image.retention_info', { hours: toolProps?.retentionHours ?? 24 }) }}
  ```
  Isso fazia com que usuários do plano Pro visualizassem "720h" em vez de "30 dias".

### Internacionalização (i18n)
- A chave existente `tools.base64-image.retention_info` está presente nos 5 arquivos de tradução:
  - `lang/pt_BR/tools.php`: `'retention_info' => 'Histórico disponível por {hours}h'`
  - `lang/pt_PT/tools.php`: `'retention_info' => 'Histórico disponível por {hours}h'`
  - `lang/en/tools.php`: `'retention_info' => 'History retained for {hours}h'`
  - `lang/es/tools.php`: `'retention_info' => 'Historial disponible por {hours}h'`
  - `lang/fr/tools.php`: `'retention_info' => 'Historique conservé pendant {hours}h'`
- Serão adicionadas as variantes de dias em cada um dos 5 idiomas para preservar 100% de cobertura sem strings hardcoded.
