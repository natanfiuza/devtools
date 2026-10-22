# Criterios de aceite — FASE 4 (Auth)

**Backfill em 2026-10-20.** A F4 foi concluida sem este arquivo: o aceite ficou
registrado em dois blocos antecipados da `docs/acceptance/FASE-0.md` - o bloco
de auth/login social (2026-10-13) e o bloco de papeis, modelo comercial e
paineis (2026-10-14). Este documento consolida o que a fase entregou, aponta a
prova de cada criterio e preserva os carry-overs. Nao ha implementacao nova
nesta data; e um registro de aceite.

Fonte: `docs/SPEC.md` secao 7 (contas e planos), secao 3 (idiomas) e secao 8
(privacidade e seguranca), mais as decisoes das ADRs 022 a 028.

## Escopo entregue

O ciclo principal de conta: cadastro e login por e-mail/senha, login social
Google e GitHub, verificacao de e-mail, redefinicao e confirmacao de senha,
definicao da primeira senha de conta social, 2FA, telas de settings (perfil,
seguranca e aparencia), exportacao e exclusao de conta por anonimizacao,
newsletter com duplo opt-in e paginas legais nos cinco idiomas. O painel
administrativo e o modelo comercial com venda manual foram adiantados no bloco
de 2026-10-14 e tambem ficam registrados na FASE-0.

## Criterios

| # | Criterio | Como verificar |
|---|---|---|
| 1 | Cadastro e login por e-mail/senha funcionam sob `/{locale}`, com os cinco idiomas e mensagens de validacao traduzidas | `AuthenticationTest`, `RegistrationTest`, `LocalizedAuthPagesTest` e `TranslationsParityTest`; `lang/*/validation.php` existe nos cinco idiomas |
| 2 | Verificacao de e-mail e obrigatoria antes do uso da conta (e, na F5, antes da IA) | `EmailVerificationTest` e `VerificationNotificationTest`; middleware `verified` nas rotas autenticadas |
| 3 | Login social Google e GitHub; vinculo com conta existente so quando o provedor confirma o e-mail; conta nova nasce sem senha | `SocialLoginTest` e `SetPasswordTest` (ADR-023) |
| 4 | Redefinicao de senha e confirmacao de senha (area sensivel) funcionam | `PasswordResetTest` e `PasswordConfirmationTest` |
| 5 | 2FA disponivel no Fortify, com confirmacao e confirmacao de senha | `config/fortify.php` (`twoFactorAuthentication` com `confirm` e `confirmPassword`); colunas em `2026_10_18_000002_add_two_factor_columns_to_users_table.php`; sem teste dedicado - carry-over |
| 6 | Passkeys continuam desligadas de proposito, mantendo o pacote que o Fortify exige | `AppServiceProvider` (`Passkeys::ignoreRoutes()`) e `config/fortify.php` (comentario e ausencia de `Features::passkeys`) |
| 7 | Settings de perfil, seguranca e aparencia nos cinco idiomas; foto de perfil | `SecurityTest`, `ProfileUpdateTest`, `ProfilePhotoTest`, `AppearanceTest` e `TranslationsParityTest` |
| 8 | Exportar conta em JSON e excluir por anonimizacao, preservando pagamentos por guarda fiscal | `AccountPanelTest` e `ProfileUpdateTest` (ADR-028) |
| 9 | Suspensao derruba a sessao ativa e bloqueia login por senha e social | `AdminAccountActionsTest` (bloco antecipado da F0; ADR-025) |
| 10 | Newsletter com duplo opt-in, confirmacao e descadastro por POST | `NewsletterTest` (ADR-024) |
| 11 | Paginas de Privacidade e Termos nos cinco idiomas, com contato e data de revisao em `config/legal.php` | `LegalPagesTest` |
| 12 | Idioma da conta e precedencia cookie -> conta -> `Accept-Language` -> `en` | `LocaleRoutingTest` e `MailLocaleTest` (ADR-026) |
| 13 | Papeis `user`/`admin`, policy unica e auditoria administrativa | `AdminRoleTest`, `AdminPanelTest` e `AdminAccountActionsTest` (ADR-025) |
| 14 | Gates da fase | Registro da epoca: 220 testes e 4.660 assercoes (Pint + PHPStan + Pest 4), 105 testes em 7 arquivos (Vitest 5), `vue-tsc` limpo e os dois builds sem erro (FASE-0, bloco de 2026-10-14). Na data deste backfill os gates estao em 317 testes/9.673 assercoes e 694 testes/50 arquivos |
| 15 | Nenhum commit feito pelo assistente | O historico e do usuario; este backfill tambem nao commita |

## Carry-overs

- **`users.appearance` e somente leitura**: o tema vive no cookie; gravar a
  coluna pela conta segue pendente. O i18n das telas de settings fechou em
  2026-10-14.
- **2FA**: foi reabilitado no config do Fortify (commit `e11dcce`) sem um aceite
  formal proprio e sem teste dedicado. Fica registrado aqui como comportamento
  existente, nao como entrega nova desta data.
- **Passkeys**: continuam desligadas (`Passkeys::ignoreRoutes()`); o pacote
  permanece porque o Fortify o exige. Decisao do SPEC secao 7.
- **Mensagens de validacao nos cinco idiomas**: fecharam em 2026-10-18, com
  `lang/{en,pt_BR,pt_PT,fr,es}/validation.php`. O carry-over da FASE-0 esta
  encerrado.
- O consumo real da cota e as telas de IA ficam para a F5; gateway, doacao e
  webhooks para a F6; historico, SEO final e hardening para a F7.

## Auditoria 2026-10-21

Reauditoria completa de fechamento, sem implementacao nova. Nenhum bloqueio
foi encontrado; os 15 criterios continuam atendidos e os carry-overs foram
conferidos no codigo.

### Gates no inicio da auditoria

```text
composer test       -> 440 testes, 440 passaram, 10.973 assercoes (Pint + PHPStan 0)
npm run test        -> 730 testes, 53 arquivos, todos verdes
npm run types:check -> vue-tsc limpo
npm run lint:check  -> falha pre-existente de baseline: 4 erros e avisos de estilo
npm run format:check-> falha pre-existente de baseline (arquivos fora do Prettier)
```

Os dois ultimos nao eram regressao da F4: eram divida de estilo anterior
(stubs de teste com nomes reservados, imports sem uso e quatro arquivos fora do
Prettier), corrigida nesta sessao; a reexecucao final deixou `lint:check` e
`format:check` verdes. Os testes direcionados da fase somaram **209 testes e
8.371 assercoes**, todos verdes.

### Mapa criterio -> prova reexecutada

| Criterios | Evidencia reexecutada |
| --- | --- |
| 1-4 | `AuthenticationTest`, `RegistrationTest`, `LocalizedAuthPagesTest`, `EmailVerificationTest`, `VerificationNotificationTest`, `PasswordResetTest`, `PasswordConfirmationTest`, `SocialLoginTest`, `SetPasswordTest`, `TranslationsParityTest` |
| 5-6 | `config/fortify.php` (`twoFactorAuthentication` com `confirm` e `confirmPassword`), migration de 2FA presente, `AppServiceProvider` com `Passkeys::ignoreRoutes()`; 2FA segue sem teste dedicado (carry-over) e passkeys seguem desligadas |
| 7-8 | `SecurityTest`, `ProfileUpdateTest`, `ProfilePhotoTest`, `AppearanceTest`, `AccountPanelTest`; `users.appearance` continua somente leitura (carry-over) |
| 9 e 13 | `AdminAccountActionsTest`, `AdminRoleTest`, `AdminPanelTest`, `AdminImpersonationTest` |
| 10-12 | `NewsletterTest`, `LegalPagesTest`, `LocaleRoutingTest`, `MailLocaleTest` |
| 14-15 | Gates acima e `git status`/historico; nenhum commit ou push do assistente |

### Carry-overs preservados

- `users.appearance` somente leitura; o tema continua no cookie.
- 2FA habilitado no Fortify sem teste dedicado.
- Passkeys desligadas de proposito.
- Middleware `verified` nas rotas autenticadas; o POST de `/subscribe` usa
  apenas `auth` + `active` por decisao de produto.
- Historico, SEO final e hardening seguem para a F7; gateway, doacao e
  webhooks para a F6.
