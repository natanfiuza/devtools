# ADR-021-tema-claro-escuro
Status: Aceito
Data: 2026-10-13

## Decisao
O tema tem **tres estados** — claro, escuro e sistema (`system` e o padrao) —
com a preferencia guardada em dois lugares: cookie `appearance` para visitante
anonimo e a coluna `users.appearance` para quem esta logado. A conta ganha do
cookie. O servidor grava o cookie sempre que o valor e valido.

**A resolucao acontece no servidor, antes de renderizar.** O `HandleInertiaRequests`
le cookie -> coluna do usuario -> `system`, e o Blade escreve no `<html>`:

```html
<html lang="pt-BR" data-appearance="dark" class="dark">
```

O valor resolvido tambem vai como prop compartilhada (`appearance`), para o
front saber o que o servidor decidiu.

**Anti-FOUC:** com `data-appearance` ja no `<html>` da resposta, nao existe
flash — o CSS pinta a pagina certa na primeira tinta. Para o caso `system`, o
unico que depende de media query do dispositivo, o Blade emite um `matchMedia`
inline **somente nesse caso**, antes do CSS pintar. O JS
(`initializeTheme`/`useAppearance`) le localStorage -> dataset do servidor ->
sistema, e **nunca sobrescreve** a decisao do servidor: se divergir, ele
corrige o `data-appearance`/classe para o valor do servidor, nao o contrario.

## Consequencias
- Os cookies escritos pelo JS (`appearance` e `locale`) precisam estar em
  `encryptCookies(except: [...])` — o Laravel criptografa cookies por padrao e
  o navegador nao leria o valor original.
- Sem banco de dados no render: para visitante anonimo a decisao e cookie +
  header, sem query nenhuma; para autenticado o usuario ja esta carregado pelo
  auth.
- Tema **claro** passou a ser alvo de validacao AA (o DESIGN.md e dark-first);
  os pares de tokens nos dois temas sao testados no Vitest e falham abaixo de
  4.5:1 para texto. Ver [DESIGN.md](../design/DESIGN.md).
- A preferencia de quem nunca escolheu nada e derivada do sistema a cada
  request; nao ha nada persistido a inferir.
- Consequencia aceita: o primeiro paint depende de JS apenas no modo
  `system` (o inline `matchMedia`). E um script de uma linha, sem rede, e e o
  preco de nao ter flash no modo que a maioria usa.

## Alternativas descartadas
- **Decidir so no cliente (localStorage)**: a pagina chega no tema padrao e
  troca depois do hydrate — flash visivel, e pior no SSR, onde o servidor
  precisa saber a classe para renderizar o HTML certo.
- **Script inline bloqueante resolvendo tudo**: seria o caminho classico
  anti-FOUC, mas empurra decisao de negocio (conta logada) para o cliente e
  exige expor dado do usuario no HTML. Ficou restrito ao caso `system`.
- **Preferencia so na conta**: visitante anonimo (a maioria, ate a F5) cairia
  sempre no tema do sistema sem poder escolher.
- **Segundo par de tokens so para o claro**: um conjunto de tokens com o valor
  trocado por tema e menos superficie para divergir.
