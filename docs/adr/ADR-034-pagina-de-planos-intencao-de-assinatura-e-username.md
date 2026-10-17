# ADR-034: Pagina de planos dedicada, intencao de assinatura e reserva de username

Status: Aceito
Data: 2026-10-17
Emenda: ADR-027 (modelo comercial e venda manual)

## Contexto

Na F1 e no ADR-027, a grade de planos ficava na propria home (`/{locale}`) e o CTA dos planos pagos era um link `mailto:` para o e-mail de contato (`config('legal.contact_email')`). Na pratica, pedir para a pessoa redigir um e-mail avulso nao capturava os dados estruturados necessarios para ativar a assinatura manualmente (plano escolhido, documento fiscal, telefone/WhatsApp e forma de pagamento preferida) nem garantia a reserva imediata do identificador exclusivo `@nomedeusuario` da conta. Alem disso, manter os planos dentro da home poluía a pagina principal de ferramentas e impedia detalhar as vantagens do plano Pro em uma pagina propria indexavel.

Os valores iniciais de referencia do plano Pro tambem foram revisados para R$ 8,90 no ciclo mensal (`890` centavos) e R$ 89,00 no ciclo anual (`8900` centavos, equivalente a 2 meses gratis).

## Decisao

1. **Pagina publica dedicada de planos (`/{locale}/pricing`)**:
   - A secao de planos sai da `Home.vue` e passa a viver em `/{locale}/pricing` (`PricingController`, pagina `Pricing.vue`), indexavel, presente no `sitemap.xml` e com `hreflang` nos cinco idiomas.
   - Os valores de `pro-monthly` e `pro-yearly` no catalogo (`plans`) passam a `890` (R$ 8,90) e `8900` (R$ 89,00), mantendo a regra de nunca fixar precos ou limites no codigo.
   - A pagina apresenta os cards ativos da tabela `plans` e a secao de vantagens Pro: reserva exclusiva de `@nomedeusuario`, 2.000.000 de tokens/mes (schema de ate 64 KB e prompt de ate 4.000 caracteres), historico salvo por 90 dias no AI SQL Builder, suporte prioritario/acesso antecipado e desconto de 2 meses no plano anual.

2. **Identificador exclusivo `@nomedeusuario` (`users.username`)**:
   - Coluna `users.username` (`VARCHAR(30)`, `nullable`, `unique`), normalizada em minusculas e validada pelo formato `^[a-z0-9_]{3,30}$`.
   - O usuario pode definir ou alterar seu `@username` tanto ao enviar o formulario de intencao de assinatura quanto em `Configuracoes > Perfil` (`/settings/profile`).
   - Na anonimizacao da conta (`AnonymizeUser`, ADR-028), `username` e limpo (`null`) para liberar o identificador e nao reter dado pessoal; na exportacao de dados (`ExportUserData`), o `username` e as intencoes de assinatura entram no pacote JSON.

3. **Formulario autenticado de intencao de assinatura (`/subscribe`)**:
   - O CTA dos planos pagos em `/{locale}/pricing` aponta para `/subscribe?plan=<slug>`.
   - Visitantes nao autenticados que acessam `/subscribe` tem a URL completa gravada em `session('url.intended')` e sao redirecionados para `/{locale}/register` (onde tambem podem alternar para login). Concluido o cadastro ou login, o Fortify redireciona automaticamente de volta para `/subscribe?plan=<slug>`.
   - O formulario autenticado (`SubscriptionIntentController`) coleta: plano (`pro-monthly` ou `pro-yearly`), `@username`, telefone/WhatsApp (`phone`), CPF ou CNPJ (`document`), forma de pagamento preferida (`pix`, `credit_card`, `boleto`) e observacoes opcionais (`notes`).
   - Ao submeter:
     - `users.username` e gravado/atualizado imediatamente na conta do usuario;
     - um registro `subscription_intents` com `status = pending` e criado (ou atualizado caso ja exista intencao pendente da mesma conta);
     - uma notificacao por e-mail (`SubscriptionIntentSubmitted`) e enviada para `Legal::contactEmail()` quando configurado.

4. **Gestao no painel administrativo (`/admin/intents`)**:
   - Nova secao `Intencoes` no painel administrativo (`Admin\SubscriptionIntentController`, pagina `admin/Intents.vue`), com filtro por status (`pending`, `fulfilled`, `canceled`), busca por conta/username/documento e acoes para marcar como atendida (`intent.fulfilled`) ou cancelada (`intent.canceled`), ambas registradas em `admin_actions`.

## Consequencias

- A venda continua manual ate a entrada do gateway na F6, mas sem depender de `mailto:` no navegador do cliente: o pedido fica persistido no banco, auditavel no painel administrativo e notificado por e-mail.
- O link `Guia de estilo` (`/{locale}/styleguide`) sai da barra superior publica para enxugar a navegacao, mantendo a rota e a pagina ativas (`noindex`) para referencia visual do design.
- Os cartoes de ferramentas disponiveis (`ToolCard.vue`) passam a ter area clicavel em todo o card (stretched link via pseudo-elemento `after:inset-0`).
