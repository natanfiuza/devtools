# devtools.natanfiuza.dev.br

Ferramentas para desenvolvedores que rodam **100% no navegador**: nenhuma
ferramenta envia dado do usuario para o servidor. O servidor existe para
entregar a pagina, o SEO e (a partir da F5) as funcoes que precisam de conta.

- Especificacao: [docs/SPEC.md](docs/SPEC.md)
- Decisoes: [docs/adr/](docs/adr/)
- Design system: [docs/design/DESIGN.md](docs/design/DESIGN.md)
- Glossario: [docs/glossario.md](docs/glossario.md)
- Operacao no servidor: [docs/RUNBOOK.md](docs/RUNBOOK.md)

## Stack

| Camada         | Escolha                                                     |
| -------------- | ----------------------------------------------------------- |
| Backend        | PHP 8.3, Laravel 13, Inertia 3, Fortify                     |
| Frontend       | Vue 3, TypeScript estrito, Tailwind 4, shadcn-vue           |
| Build          | Vite 8 (+ SSR do Inertia)                                   |
| Banco          | MariaDB 11.x (dev e producao); SQLite `:memory:` nos testes |
| Testes         | Pest 4 (PHP), Vitest 5 (TS)                                 |
| Rotas no front | Wayfinder (rotas tipadas geradas do Laravel)                |
| Idiomas        | en, pt-BR, pt-PT, fr, es                                    |

## Requisitos

- PHP **8.3+** com as extensoes usuais do Laravel (`pdo_mysql`, `mbstring`, `intl`)
- Composer **2.10+**
- Node **22+** e npm
- MariaDB **11.x** rodando na 3306

## Setup local

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Crie o banco e o usuario (o `.env.example` ja aponta para eles):

```sql
CREATE DATABASE devtools CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'devtools'@'localhost' IDENTIFIED BY 'troque-esta-senha';
GRANT ALL PRIVILEGES ON devtools.* TO 'devtools'@'localhost';
FLUSH PRIVILEGES;
```

No MariaDB a collation e `utf8mb4_unicode_ci` (`utf8mb4_0900_ai_ci` e do MySQL
8 e nao existe no MariaDB). Ponha a senha em `DB_PASSWORD` no `.env` e siga:

```bash
php artisan migrate
npm install
```

### Subir o ambiente

```bash
composer dev
```

Um comando so: `php artisan serve` (http://localhost:8000), `npm run dev`
(Vite com HMR), a fila e o `pail` (logs). Para rodar separado, `php artisan
serve` e `npm run dev` em dois terminais.

## Comandos

| Comando                   | O que faz                                             |
| ------------------------- | ----------------------------------------------------- |
| `composer dev`            | Servidor + Vite + fila + logs                         |
| `composer test`           | `config:clear`, Pint, PHPStan e Pest                  |
| `npm run test`            | Vitest                                                |
| `npm run types:check`     | Wayfinder + `vue-tsc --noEmit`                        |
| `npm run lint` / `format` | ESLint e Prettier (com `--fix`/`--write`)             |
| `npm run build`           | Build de producao do cliente                          |
| `npm run build:ssr`       | Build do cliente + bundle de SSR                      |
| `npm run ssr`             | Sobe o servidor de SSR local (`bootstrap/ssr/ssr.js`) |

Antes de abrir um PR: `composer test`, `npm run test` e `npm run types:check`.

## Idioma e tema

A raiz `/` resolve o idioma do visitante (cookie, conta, `Accept-Language` e,
por ultimo, o fallback `en`) e redireciona para `/en`, `/pt-br`, `/pt-pt`,
`/fr` ou `/es`. As paginas publicas vivem sob o prefixo do idioma; dentro da
conta autenticada a URL nao tem prefixo e o idioma continua valendo pela
sessao. As traducao sao escritas em `lang/{locale}/*.php` e chegam ao front
pelas props compartilhadas do Inertia.

O tema (claro / escuro / sistema) e resolvido **no servidor** a partir do
cookie e da coluna do usuario, entao a pagina ja chega com a classe certa no
`<html>` e nao pisca. Em `/styleguide` estao os tokens e os componentes nos
dois temas.

## Builds

`public/build` e `bootstrap/ssr` sao artefatos: nao vao para o git e sao
construidos **no servidor** pelo `deploy/deploy.sh`. Localmente, `npm run
build` (ou o Vite em dev) resolve.

## Fluxo de trabalho

Trabalho por fase, conforme o [docs/SPEC.md](docs/SPEC.md). Ao fim de cada
fase: testes verdes, docs atualizados e parada para revisao. Commits e PRs
passam pelo `gitpr` com aprovacao — nao commite direto na branch.
