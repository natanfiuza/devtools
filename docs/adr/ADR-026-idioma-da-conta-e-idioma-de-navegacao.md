# ADR-026-idioma-da-conta-e-idioma-de-navegacao
Status: Aceito
Data: 2026-10-14

## Decisao
**O idioma da conta (`users.locale`) e uma preferencia explicita; o cookie e o
registro de onde a pessoa esta navegando agora. Quem decide cada request e o
primeiro que souber responder: a URL, o cookie, a conta ou o cabecalho.**

- A conta ganha `locale` (`string(5)`, anulavel) e ele e escolhido em dois
  lugares: no **cadastro** (select com os 5 idiomas nos proprios nomes -
  English, Portugues (Brasil), Portugues (Portugal), Francais, Espanol -,
  pre-selecionado com o idioma da tela, opcional: em branco vale o idioma em que
  a pessoa se cadastrou) e no **perfil**, que e onde se troca depois.
- O que conta guarda e o **codigo** (`pt_BR`), e nao o slug da URL (`pt-br`). O
  codigo e o diretorio em `lang/` e o valor da coluna; o slug e so a forma da
  URL e do cookie.
- Em rota **prefixada** (`/{locale}/...`), a URL manda: quem abre `/fr` ve
  frances, e o cookie passa a registrar `fr` (`ResolveLocale::rememberBrowsing`,
  1 ano).
- Em rota **sem prefixo** (area autenticada, `/admin`, telas de token), a ordem e
  `Locales::preferred()`:

  1. cookie `locale` (a escolha mais recente e mais local);
  2. `users.locale` (a preferencia duradoura, quando a conta tem uma);
  3. `Accept-Language` do navegador;
  4. `en` (fallback de `config/locales.php`).

- O idioma da conta e tambem o idioma dos **e-mails** que saem para ela; o
  perfil diz isso no texto de ajuda, para a troca nao parecer so estetica.
- Trocar o idioma no perfil grava a coluna **e** o cookie, para a interface mudar
  na hora sem depender de um reload em rota prefixada.

## Por que
Sao duas perguntas diferentes, e a versao anterior deste ADR respondia as duas
com uma so: "em que idioma esta pessoa navega agora" e "em que idioma esta
pessoa quer ser atendida". Quem tem a conta em portugues e abre
`/fr/tools/json-viewer` - por um link compartilhado, por exemplo - quer ver a
pagina em frances; mas quer continuar recebendo os e-mails em portugues, e quer
que a area autenticada (que nao tem prefixo) abra em portugues depois disso.

Sem a distincao, o cookie teria que carregar as duas coisas e o cadastro nao
teria onde gravar a escolha: a preferencia durava o que dura o cookie - o que
quebra em outro navegador, depois de limpar dados do site, ou no e-mail, que
sai do navegador e vai para a caixa de entrada.

O `Accept-Language` fica em terceiro porque e o palpite mais fraco: diz o idioma
do sistema, nao o da pessoa.

## Consequencias
- `users.locale` anulavel e e anulado junto com o resto do dado pessoal na
  exclusao por anonimizacao (ADR-028): conta anonimizada nao guarda preferencia.
- O cadastro e **tolerante** de proposito: campo ausente ou codigo desconhecido
  cai no idioma da pagina (`app()->getLocale()`) em vez de virar erro de
  validacao - ninguem perde a conta por causa de um select. A lista de opcoes da
  tela vem de `config/locales.php`.
- No perfil a regra e explicita (`sometimes`, `string`,
  `Rule::in(Locales::codes())`): valor fora da lista e erro de validacao e o
  campo ausente nao mexe no que esta gravado. Trocar ali grava a coluna **e** o
  cookie.
- Quem navega sem cookie e sem conta cai no `Accept-Language` - o comportamento
  de sempre - e o primeiro `/pt-br` visitado passa a valer como cookie.
- Os testes de idioma da conta olham a coluna, nao o cookie: o que se prova e que
  a escolha **sobrevive** a navegar em outro idioma no mesmo navegador.

## Alternativas descartadas
- **So o cookie** (o que a F0 fazia): a preferencia some ao limpar o navegador,
  nao vale em outro dispositivo e nao tem como escolher no cadastro.
- **So a conta**: navegar em `/fr` nao faria nada para quem esta logado, e o
  prefixo da URL - que existe por SEO e por hreflang - viraria decorativo.
- **Guardar o slug da URL na conta** (`pt-br`): amarraria o dado ao formato da
  URL; o codigo (`pt_BR`) e o que o `lang/` e os e-mails usam.
- **Preferencia obrigatoria no cadastro**: um campo a mais que trava o cadastro
  para responder uma pergunta que o proprio navegador ja respondeu; por isso o
  select vem pre-selecionado e pode ficar como esta.
