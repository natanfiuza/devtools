# Relatorio - comando de tinker para criar a conta de admin

Branch: `master`
Data: 2026-10-14
Tarefa: criar o comando (tinker) que cria a conta `devtools@natanfiuza.dev.br`

## 1. O pedido e o que foi entregue

Comando de duas linhas para rodar no `php artisan tinker`, idempotente
(`updateOrCreate`), verificado de ponta a ponta num banco SQLite descartavel
antes de ser entregue. **Nenhum arquivo do repositorio foi alterado** por esta
tarefa, alem deste relatorio.

## 2. O comando

```php
$u = App\Models\User::updateOrCreate(
    ['email' => 'devtools@natanfiuza.dev.br'],
    ['name' => 'Admin', 'password' => 'Devtools@13579'],
);

$u->forceFill(['email_verified_at' => now(), 'locale' => 'pt_BR'])->save();
```

Versao de uma linha so, para `tinker --execute` ou para colar de uma vez:

```php
$u = App\Models\User::updateOrCreate(["email" => "devtools@natanfiuza.dev.br"], ["name" => "Admin", "password" => "Devtools@13579"]); $u->forceFill(["email_verified_at" => now(), "locale" => "pt_BR"])->save();
```

## 3. Por que cada parte esta ali

- **`forceFill` na segunda linha**: `User` declara `#[Fillable(['name', 'email',
  'password'])]`, entao `email_verified_at` e `locale` passados no `create()`
  seriam **descartados em silencio** (o app nao usa `Model::shouldBeStrict()`).
  Sem `email_verified_at`, a conta cai na tela de verificar e-mail no primeiro
  login: `Features::emailVerification()` esta ligado em `config/fortify.php`.
- **Senha em texto puro**: a coluna tem o cast `'password' => 'hashed'`, entao o
  Eloquent faz o hash (bcrypt, cost 12) - a senha nunca e gravada em claro.
- **`locale => 'pt_BR'`**: `preferredLocale()` decide o idioma dos e-mails de
  reset e verificacao. Pode sair da linha se a conta deva seguir o app.
- **`updateOrCreate`**: rodar de novo nao duplica conta nem estoura o indice
  unico de `email`; reseta a senha para a informada, que e o esperado num
  bootstrap de admin.

## 4. Verificacao executada

Banco SQLite descartavel (`database/admin-check.sqlite`, migrado e apagado
depois) com `DB_CONNECTION`/`DB_DATABASE` por variavel de ambiente, para nao
tocar no MariaDB local:

| Passo | Resultado |
|---|---|
| Migrate das 6 migrations | OK |
| 1a execucao do comando | `id=1 nome=Admin email=devtools@natanfiuza.dev.br hasPassword=true verified=2026-10-14 03:32:19 locale=pt_BR` |
| 2a execucao (idempotencia) | mesma conta, `id=1`, uma unica linha em `users` |
| `Hash::check('Devtools@13579', $u->password)` | **confere** (`$2y$12$...`) |

## 5. Pendencia que este comando nao resolve

**Nao existe papel de admin no projeto ainda.** A tabela `users` nao tem coluna
de papel e nao ha gate/policy de admin; a area `/admin` e da F7 (`docs/SPEC.md`
secao 7), e a propria SPEC (secao 11.2) registra como **decisao em aberto** "como
se cria o primeiro admin (seeder ou comando artisan) e o alcance dos ajustes de
cota pelo admin".

Ou seja: esta conta e uma conta comum **chamada** "Admin". Quando a F7 fechar o
mecanismo de papel, ela e a candidata natural a ser promovida - e a decisao
seeder x comando artisan sera tomada la, com o comando deste relatorio como
insumo (nada foi criado agora para nao antecipar essa decisao).

## 6. Pre-requisitos para rodar

Banco `devtools` criado no MariaDB (SQL no `README.md`) e `php artisan migrate`
executado. Depois: `php artisan tinker` e colar o comando.
