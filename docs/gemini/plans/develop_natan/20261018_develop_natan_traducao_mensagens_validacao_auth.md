# Plano - Traducao das Mensagens de Validacao e Atributos nos 5 Idiomas

Branch: `develop_natan`.
Data: 2026-10-18.
Escopo: Criacao dos arquivos de traducao de validacao (`lang/{locale}/validation.php`) nos 5 idiomas suportados (`en`, `pt_BR`, `pt_PT`, `fr`, `es`), traducao dos atributos de formulario e testes automatizados de validacao e paridade.

---

## 1. Contexto e Diagnostico (Grill com Docs)

### 1.1. O Problema
- Na tela de cadastro (`/{locale}/register`), ao informar uma senha com menos de 8 caracteres (ou submeter campos vazios/invalidos), o erro exibido era em ingles:
  `"The password field must be at least 8 characters."`, mesmo com a interface navegada em portugues (`/pt-br/register`).
- O mesmo comportamento ocorria para os demais idiomas (`/pt-pt/register`, `/fr/register`, `/es/register`).

### 1.2. Causa Raiz
- O framework Laravel disponibiliza por padrao apenas o arquivo `vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php`.
- No repositorio, a pasta `lang/` possui diretorios para os 5 idiomas (`en`, `pt_BR`, `pt_PT`, `fr`, `es`), contendo `auth.php`, `passwords.php`, `mail.php`, etc., mas **nenhum** arquivo `validation.php`.
- Quando ocorre um erro de validacao (ex.: `CreateNewUser::create()` via Fortify), o Laravel invoca `trans('validation.min.string', ['attribute' => 'password', 'min' => 8], $locale)`.
- Como `lang/{locale}/validation.php` nao existe para `pt_BR`, `pt_PT`, `fr`, `es`, o Laravel recorre ao fallback em ingles do framework (`vendor/.../en/validation.php`).
- Alem disso, sem a traducao da chave de atributo `'password'` na secao `'attributes'`, mesmo que a regra estivesse traduzida, o nome do campo continuaria em ingles (`"O campo password..."`).
- Esse problema ja estava mapeado na documentacao como pendencia da Fase 4 em `docs/SPEC.md` secao 11.2:
  `"- F4: mensagens de validacao nos 5 idiomas. O Laravel so traz validation.php em ingles; nas telas traduzidas o erro de formulario ainda sai em ingles ('The email field is required'), junto com nomes de campo em ingles."`

### 1.3. Solucao
1. Criar `lang/{locale}/validation.php` para os 5 idiomas (`en`, `pt_BR`, `pt_PT`, `fr`, `es`) com todas as mensagens de validacao padrao do Laravel (incluindo regras de `min`, `required`, `email`, `confirmed`, `unique`, regras de senha `password.*`, etc.).
2. Adicionar o mapeamento completo de atributos no array `'attributes'` de cada idioma (`password` -> `senha` / `palavra-passe` / `mot de passe` / `contraseña` / `password`, `name`, `email`, `password_confirmation`, `current_password`, `username`, `locale`, `newsletter`, `document`, `phone`, `payment_method`, etc.).
3. Adicionar `'validation'` em `tests/Feature/TranslationsParityTest.php` para garantir 100% de paridade de chaves e ausencia de valores vazios nos 5 idiomas.
4. Adicionar testes de integracao em `tests/Feature/Auth/RegistrationTest.php` validando a exibicao da mensagem de erro de senha e campos obrigatorios traduzidos nos 5 idiomas.
5. Atualizar `docs/SPEC.md` marcando a pendencia F4 como resolvida.

---

## 2. Arquivos Envolvidos e Modificacoes

### 2.1. Arquivos de Traducao (`lang/{locale}/validation.php`)
- `lang/en/validation.php`: Base completa das regras de validacao do Laravel com atributos traduzidos.
- `lang/pt_BR/validation.php`: Regras traduzidas para Portugues do Brasil (com `senha`, `confirmação da senha`, `O campo :attribute deve ter pelo menos :min caracteres.`).
- `lang/pt_PT/validation.php`: Regras traduzidas para Portugues de Portugal (com `palavra-passe`, `confirmação da palavra-passe`, `O campo :attribute deve ter pelo menos :min caracteres.`).
- `lang/es/validation.php`: Regras traduzidas para Espanhol (com `contraseña`, `confirmación de la contraseña`, `El campo :attribute debe tener al menos :min caracteres.`).
- `lang/fr/validation.php`: Regras traduzidas para Frances (com `mot de passe`, `confirmation du mot de passe`, `Le champ :attribute doit contenir au moins :min caractères.`).

### 2.2. Testes Automatizados
- `tests/Feature/TranslationsParityTest.php`: Adicionar `'validation'` na funcao `allTranslatedNamespaces()`.
- `tests/Feature/Auth/RegistrationTest.php`: Adicionar testes para submissao de cadastro com senha menor que 8 caracteres nos 5 idiomas e confirmar a mensagem traduzida correspondente na sessao de erros.

### 2.3. Documentacao
- `docs/SPEC.md`: Atualizar secao 11.2 indicando a entrega de `validation.php` nos 5 idiomas.

---

## 3. Plano de Testes e Validacao

1. Rodar `vendor/bin/pest tests/Feature/TranslationsParityTest.php` para atestar paridade estrita de chaves.
2. Rodar `vendor/bin/pest tests/Feature/Auth/RegistrationTest.php` para atestar erro traduzido nos 5 idiomas.
3. Rodar `composer test` (Pint, PHPStan, Pest).
4. Rodar `npm run test` e `npm run types:check`.
5. Gerar relatorio detalhado em `docs/gemini/reports/develop_natan/20261018_develop_natan_traducao_mensagens_validacao_auth.md`.
