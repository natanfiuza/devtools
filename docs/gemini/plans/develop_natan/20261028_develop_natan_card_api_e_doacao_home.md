# Plano de Implementação — Card da API e Chamada de Doação na Home

## 1. Descrição do Objetivo
Adicionar à página principal (`resources/js/pages/Home.vue`):
1. **Card da API REST**: Destacar a importância da API para o programador, evidenciando cenários reais como integração em pipelines de CI/CD, scripts CLI, ferramentas internas e automações (ex: n8n), além de autenticação com Bearer Token e geração de consultas em múltiplos dialetos e ORMs, acompanhado de um preview em código cURL.
2. **Chamada de Doação**: Destacar o modelo transparente, gratuito e focado em privacidade do devtools, explicando que as ferramentas rodam localmente e que as doações via Pix sustentam os custos operacionais (servidores e cotas de IA), convidando o usuário a apoiar o projeto com link direto para `/{locale}/donate`.

---

## 2. User Review Required
> [!NOTE]
> Todas as decisões estruturais foram alinhadas e aprovadas na sessão de **/grill-with-docs** (Rodada 1):
> - **Ordem dos elementos**: Hero ➔ Ferramentas ➔ AI SQL Builder ➔ **Card da API** ➔ **Chamada de Doação**.
> - **Card da API**: Layout 2 colunas desktop (conteúdo + bloco de código cURL), coerente com o padrão visual do AI SQL Builder estabelecido em `DESIGN.md`.
> - **Chamada de Doação**: Card em `bg-surface` com badge temático, ícone `HeartHandshake`, texto explicativo e botão de ação direta para a tela de doação Pix.

---

## 3. Mudanças Propostas

### Internacionalização (i18n)
Adição das chaves `home.api.*` e `home.donation.*` em todos os 5 idiomas suportados (`pt_BR`, `en`, `pt_PT`, `fr`, `es`).

#### [MODIFY] `lang/pt_BR/home.php`
- `home.api.badge`: "API REST v1"
- `home.api.title`: "Automatize e integre direto no seu fluxo de trabalho"
- `home.api.body`: "Gere SQL e código de ORM programaticamente. Conecte o devtools às suas rotinas de CI/CD, scripts CLI, extensões de editor e fluxos no n8n sem depender de copiar e colar pela interface."
- `home.api.note`: "Autenticação via Bearer token (Sanctum) com processamento assíncrono e resposta instantânea para consultas em cache."
- `home.api.cta_docs`: "Ver documentação da API"
- `home.api.cta_tokens`: "Gerenciar tokens"
- `home.api.preview_title`: "Exemplo de chamada HTTP"
- `home.api.preview_code`: Snippet cURL com POST `/api/v1/sql-queries`
- `home.donation.badge`: "Apoie o devtools"
- `home.donation.title`: "Mantenha o projeto gratuito e independente"
- `home.donation.body`: "O devtools é construído por desenvolvedores e para desenvolvedores. Todas as ferramentas rodam localmente no seu navegador, sem rastreadores invasivos nem anúncios. Sua doação via Pix ajuda a pagar a infraestrutura de servidores e o processamento de modelos de IA."
- `home.donation.note`: "Qualquer valor faz diferença e mantém a plataforma acessível a todos."
- `home.donation.cta`: "Fazer uma doação via Pix"

#### [MODIFY] `lang/en/home.php`
Traduções equivalentes em inglês.

#### [MODIFY] `lang/pt_PT/home.php`
Traduções equivalentes em português de Portugal.

#### [MODIFY] `lang/fr/home.php`
Traduções equivalentes em francês.

#### [MODIFY] `lang/es/home.php`
Traduções equivalentes em espanhol.

---

### Frontend Vue

#### [MODIFY] `resources/js/pages/Home.vue`
- Importar `Terminal` e `HeartHandshake` de `@lucide/vue`.
- Importar `usePage` de `@inertiajs/vue3` e calcular `isAuthenticated`.
- Importar `index as apiTokensIndex` de `@/routes/api-tokens`.
- Adicionar o bloco `<section class="mt-20">` para o **Card da API**:
  - Badge `secondary` com ícone de terminal e texto `t('home.api.badge')`.
  - Título `h2` e parágrafo `p`.
  - Nota informativa `p.text-subtle`.
  - Botões de ação: CTA primário para `routes.apiDocs.value` e secundário para `/settings/api-tokens` (ou `/register` se visitante).
  - Figure monoespaçado com o snippet cURL formatado.
- Adicionar o bloco `<section class="mt-20">` para a **Chamada de Doação**:
  - Container `rounded-xl border bg-surface p-6 sm:p-10`.
  - Badge com ícone `HeartHandshake`.
  - Título e descrição do projeto independente e gratuito.
  - Botão de ação em destaque para `routes.donate.value`.

---

### Testes Automatizados

#### [MODIFY] `tests/Feature/HomeTest.php`
- Adicionar as novas chaves à lista de validação dos 5 idiomas (`home.api.title`, `home.api.body`, `home.donation.title`, `home.donation.body`, etc.).

#### [MODIFY] `resources/js/__tests__/home.test.ts`
- Incluir as novas chaves no objeto de mock de traduções `traducoes.home.api` e `traducoes.home.donation`.
- Adicionar testes de unidade verificando a renderização do Card da API (com link para a documentação da API e tokens) e da Chamada de Doação (com link para a página de doação).

---

## 4. Plano de Verificação

### Testes Automatizados
Executar a suíte de validação do projeto:
```powershell
# 1. Testes unitários do Frontend
npm run test

# 2. Checagem estrita de tipos TypeScript
npm run types:check

# 3. Testes do Backend (Pest, Pint, PHPStan)
composer test
```

### Verificação Manual
1. Inspecionar visualmente a página inicial (`Home.vue`) no tema claro e tema escuro.
2. Testar a responsividade em telas mobile (`< 768px`) e desktop (`>= 1024px`).
3. Clicar no botão "Ver documentação da API" e validar navegação para `/{locale}/api-docs`.
4. Clicar no botão "Fazer uma doação via Pix" e validar navegação para `/{locale}/donate`.
5. Alternar os idiomas (`pt-BR`, `en`, `pt-PT`, `fr`, `es`) e verificar a correta aplicação das strings traduzidas.
