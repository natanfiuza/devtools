# Survey — Cards da API e de Doação na Home (2026-10-28)

> Levantamento automático da sessão de grill (`/grill-with-docs`) — fronteira esgotada e entendimento compartilhado confirmado pelo usuário.

## 1. Contexto da tarefa
- **Pedido original:** "Adicione na pagina principal um card para a API destacando a sua importancia para o programador. Adicione tambem na pagina principal uma chamada para Doação e ajuda ao projeto."
- **Tela / Módulo:** Home pública (`resources/js/pages/Home.vue`, rota `/{locale}`), traduções (`lang/{locale}/home.php`).
- **Skill:** `/grill-with-docs` (grilling + domain-modeling) · branch `develop_natan`
- **Método:** 1 rodada com 3 decisões essenciais (posicionamento, estrutura visual do card da API, formato da chamada de doação), busca autônoma no código e confirmação do usuário.
- **Arquivos-chave lidos:**
  - `resources/js/pages/Home.vue`
  - `resources/js/pages/ApiDocs.vue`
  - `resources/js/pages/Donate.vue`
  - `resources/js/layouts/PublicLayout.vue`
  - `resources/js/composables/useRoutes.ts`
  - `lang/pt_BR/home.php`
  - `docs/design/DESIGN.md`
  - `resources/js/__tests__/home.test.ts`
  - `tests/Feature/HomeTest.php`

## 2. Decisões das rodadas

### Rodada 1 — Posicionamento, Design e Abordagem
| # | Decisão | Resolução |
|---|---|---|
| Q1 | Posicionamento na Home | **Opção A**: Sequência natural: Hero/Busca ➔ Grid de Ferramentas ➔ AI SQL Builder ➔ **Card da API** ➔ **Chamada de Doação** (antes do rodapé). |
| Q2 | Estrutura do Card da API | **Opção A**: Layout espelhado ao AI SQL Builder (2 colunas em desktop). Coluna esquerda com badge `API REST v1`, título de impacto, texto enfatizando valor para o dev (CI/CD, CLI, scripts, automação sem copy/paste), autenticação Bearer token e CTAs ("Documentação da API" e "Criar token / Começar"). Coluna direita com bloco de preview de código `curl` / HTTP. |
| Q3 | Formato da Chamada de Doação | **Opção A**: Card destacado em `bg-surface` com badge e ícone `HeartHandshake`, ressaltando gratuidade, privacidade, ausência de anúncios, processamento local e financiamento transparente dos custos de infraestrutura e IA via Pix, com botão principal "Apoiar o projeto via Pix" linkando para `/{locale}/donate`. |

### Defaults assumidos (não contestados)
- Toda e qualquer string exibida na interface é traduzida nos 5 idiomas suportados (`pt_BR`, `en`, `pt_PT`, `fr`, `es`), sem texto hardcoded.
- Sem necessidade de novo termo no glossário ou novo ADR, pois a estrutura segue estritamente os padrões de tokens e layout descritos em `docs/design/DESIGN.md`.
- No card da API, se o usuário estiver autenticado, o botão secundário aponta para `/settings/api-tokens`; se visitante, aponta para registro (`/register`).
- Cobertura de testes garantida tanto no Vitest (`resources/js/__tests__/home.test.ts`) quanto no Pest (`tests/Feature/HomeTest.php`).

## 3. Relatório de fatos levantados
### Padrão visual do DESIGN.md
- Tokens: `--surface`, `--surface-2`, `--border`, `--fg-muted`, `--fg-subtle`, `--accent`.
- Cards sem sombra, com borda de 1px e padding responsivo (`p-6 sm:p-10`).
- Tipografia: Inter para UI e JetBrains Mono para códigos e snippets monoespaçados.
- Preview em `<figure>` com estilo terminal/código limpo, com scroll horizontal sem quebra forçada.

### Rotas e Links existentes
- `routes.apiDocs.value`: `/{locale}/api-docs`
- `routes.donate.value`: `/{locale}/donate`
- `apiTokensIndex.url()`: `/settings/api-tokens` (via `@/routes/api-tokens`)
- `routes.register.value`: `/{locale}/register`

### Validação rigorosa de traduções
- O teste `tests/Feature/HomeTest.php` valida que todas as chaves sob `home.*` existem e não retornam a chave crua em nenhum dos 5 idiomas. Qualquer nova chave deve obrigatoriamente estar presente em `pt_BR`, `en`, `pt_PT`, `fr` e `es`.
