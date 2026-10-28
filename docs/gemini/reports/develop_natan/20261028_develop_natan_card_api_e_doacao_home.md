# Relatório — Card da API e Chamada de Doação na Home

- **Data:** 2026-10-28
- **Branch:** `develop_natan`
- **Tarefa:** `card_api_e_doacao_home`
- **Status:** Concluído com sucesso e validado por testes automatizados

---

## 1. Resumo da Tarefa
Implementação na página inicial (`resources/js/pages/Home.vue`) de:
1. **Card da API REST v1**: Destaque para o valor da API para desenvolvedores (integração direta em pipelines CI/CD, scripts CLI, ferramentas internas, extensões e fluxos n8n sem necessidade de interação manual na interface), autenticação Bearer Token via Laravel Sanctum, processamento assíncrono e resposta instantânea via cache, acompanhado de preview de requisição cURL e CTAs para a documentação (`/{locale}/api-docs`) e gerenciamento de tokens (`/settings/api-tokens` ou cadastro se visitante).
2. **Chamada de Doação e Apoio ao Projeto**: Destaque para a independência e modelo gratuito do devtools (ferramentas locais no navegador, foco em privacidade e sem anúncios invasivos), com convite para apoio financeiro via Pix para cobrir custos de infraestrutura e IA, e CTA direto para a página de doação (`/{locale}/donate`).

---

## 2. Arquivos Modificados e Criados

### Internacionalização (i18n)
- `lang/pt_BR/home.php`: Chaves `home.api.*` e `home.donation.*` em português brasileiro.
- `lang/en/home.php`: Chaves `home.api.*` e `home.donation.*` em inglês.
- `lang/pt_PT/home.php`: Chaves `home.api.*` e `home.donation.*` em português de Portugal.
- `lang/fr/home.php`: Chaves `home.api.*` e `home.donation.*` em francês.
- `lang/es/home.php`: Chaves `home.api.*` e `home.donation.*` em espanhol.

### Frontend
- `resources/js/pages/Home.vue`:
  - Adição da seção do Card da API com layout em 2 colunas, Badge `secondary` com ícone `Terminal`, título, descrição, nota técnica, botões de ação e bloco de preview cURL.
  - Adição da seção da Chamada de Doação com Badge `secondary` com ícone `HeartHandshake`, título, corpo explicando o projeto independente e botão de ação para doação Pix.
  - Suporte a verificação de autenticação (`isAuthenticated`) para direcionar o CTA de tokens adequadamente.

### Testes Automatizados
- `tests/Feature/HomeTest.php`: Adição das novas chaves de tradução à asserção de disponibilidade nos 5 idiomas suportados.
- `resources/js/__tests__/home.test.ts`: Mock das novas chaves no setup de testes e adição de casos de teste para o Card da API e a Chamada de Doação.

### Documentação e Governança
- `docs/survey/20261028_home_card_api_e_doacao_surveyfacts.md`: Levantamento de fatos da sessão de `/grill-with-docs`.
- `docs/gemini/plans/develop_natan/20261028_develop_natan_card_api_e_doacao_home.md`: Plano de implementação aprovado.
- `docs/gemini/reports/develop_natan/20261028_develop_natan_card_api_e_doacao_home.md`: Este relatório detalhado de entrega.

---

## 3. Verificação e Testes

Todos os comandos de validação passaram com 100% de sucesso:

1. **Testes Unitários do Frontend (Vitest)**:
   - `npm run test`: **63 test files passed (63)**, **816 tests passed (816)**.
2. **Checagem Estrita de Tipos (TypeScript & Wayfinder)**:
   - `npm run types:check`: Wayfinder gerou rotas e ações tipadas; `vue-tsc --noEmit` finalizou sem erros.
3. **Linter do Frontend (ESLint)**:
   - `npx eslint resources/js/pages/Home.vue resources/js/__tests__/home.test.ts`: 0 erros.
4. **Testes do Backend (Pint, PHPStan e Pest)**:
   - `composer test`:
     - Pint: passed (código estilizado conforme padrões).
     - PHPStan: passed (0 erros).
     - Pest: **750 passed (750 tests, 16.584 assertions)**.
5. **Build de Produção do Cliente e SSR**:
   - `npm run build`: Build cliente gerado com sucesso.
   - `npm run build:ssr`: Bundle SSR gerado com sucesso.
