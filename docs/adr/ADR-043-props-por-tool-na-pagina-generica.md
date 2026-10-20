# ADR-043: Props por tool na pagina generica (`toolProps`)

Status: Aceito
Data: 2026-10-20
Relacionados: ADR-029 (pagina generica), ADR-040 (ciclo da solicitacao), ADR-042 (contrato HTTP)

## Contexto

A pagina generica `/{locale}/tools/{slug}` (ADR-029) recebe sempre quatro
limites de `config/tools.php` e injeta o componente da tool por glob lazy. Ate
a F3 bastava: oito ferramentas rodam no navegador e nao precisam de nada do
servidor alem dos limites de entrada.

O AI SQL Builder quebra esse molde. Antes de desenhar o formulario ele precisa
saber:

- se o provedor esta configurado (sem chave, a tool esta "indisponivel");
- por que a conta nao pode usar, se for o caso (visitante, e-mail nao
  verificado, conta suspensa, cota esgotada);
- o estado atual da cota e os limites efetivos do plano (schema e descricao);
- os alvos de saida ligados e os dialetos de cada um;
- o ritmo do polling e os endpoints da API.

Havia duas alternativas feias: (a) o componente consultar tudo por `fetch` no
`onMounted`, o que daria um estado de carregamento para dados que o servidor ja
tem; (b) o `ToolController` passar props que so uma tool entende para todas as
nove paginas, o que faria as outras oito receberem atributos que nao declaram
(caindo no DOM, com `inheritAttrs` default) ou exigir `v-bind` condicional em
cada componente.

## Decisao

A pagina generica passa a receber **dois pacotes**:

1. os **quatro limites** de `config/tools.php`, como antes, para as tools de
   navegador;
2. `toolProps`: um objeto por tool, resolvido no servidor por
   `App\Support\ToolProps::for($slug, $user)`.

```php
return match ($slug) {
    'ai-sql-builder' => (object) AiToolProps::for($user),
    default => (object) [],
};
```

- Tools de navegador recebem `{}` - um contrato estavel, e nao uma prop que
  aparece e some.
- `AiToolProps` monta o pacote: `available`, `canUse` (`allowed` + `reason`),
  `quota`, `targets`, `sources`, `dialects`, `limits`, `poll`, `endpoints`.
  `limits` e o **menor** entre o teto tecnico de `config/ai.php` e o limite do
  plano; visitante ve o do Free, que e o piso.
- O motivo de bloqueio segue uma ordem fixa: `guest`, `email_unverified`,
  `account_suspended`, `unavailable`, `quota_exhausted`. A ordem e a do proximo
  passo que a pessoa deve dar.
- `Show.vue` faz `v-bind="toolProps"`; o componente da tool declara so as props
  que consome. O `Tool.vue` do AI usa `inheritAttrs: false` para os quatro
  limites que nao lhe dizem respeito nao vazarem para o DOM.
- **Isto e conveniencia de interface, nao autorizacao.** O POST valida de novo
  conta, verificacao, suspensao, cota, dialeto e limites; esconder o formulario
  nao substitui nenhuma dessas checagens.

Os rotulos dos alvos vem de `ai.targets.<slug>` no i18n; `label` e `language`
do banco sao identificacao do dado, nao texto de interface (CLAUDE.md).

## Consequencias

### Positivas

- A tela abre ja no estado certo: visitante ve o CTA de login, conta sem
  verificacao ve o reenvio, cota esgotada ve o caminho de planos - sem um
  "carregando" que so existe porque o dado estava no servidor.
- Nenhuma tool de navegador ganha prop nova nem markup extra; o contrato delas
  nao muda.
- O pacote e testavel no servidor (`AiToolPropsTest`) e tipado no cliente
  (`AiToolProps` em `types.ts`).
- Uma tool nova com necessidades de servidor entra no `match` sem tocar na
  pagina generica nem nas outras oito.

### Negativas / trade-offs

- O pacote e publico na pagina: mostra limites do plano e estado da cota para
  quem abriu a pagina. Sao dados da propria conta (visitante ve o Free), nao de
  terceiros; a API repete a cota em cada resposta.
- O `ToolController` ganha uma dependencia do dominio de IA (`ToolProps`), que e
  o ponto unico onde o acoplamento fica visivel.
- `toolProps` e um `Record<string, unknown>` no `Show.vue`: o componente da tool
  e quem tipa o conteudo; um erro de forma so aparece no teste da tool.

## Alternativas descartadas

- **Fetch no `onMounted`**: criaria um estado de carregamento e um request extra
  para dados que o SSR ja entrega prontos.
- **Props de IA em todas as tools**: as oito tools de navegador receberiam
  atributos que nao declaram; sem `inheritAttrs: false` em cada uma, cairiam no
  DOM.
- **Props fixas no `ToolController`**: cada tool nova exigiria mudar a assinatura
  do controller e da pagina, contra a ideia de pagina generica.
- **Autorizacao no cliente**: qualquer bloqueio so na tela e contornavel; a
  checagem real fica nas rotas e na acao (ADR-040, ADR-042).
