# ADR-007-registro-de-tools
Status: Aceito
Data: 2026-10-05
Atualizado: 2026-10-14 (a fonte do registro passa a ser `manifest.json`, com dois leitores; a F1 implementou)

## Decisao
**O registro de tools e o conjunto dos `manifest.json` em
`resources/js/tools/<slug>/`. O manifesto guarda identidade e ciclo de vida; o
que a pessoa le vem do i18n. Os dois lados do sistema - o TypeScript que monta a
home, os chips e a busca, e o PHP que monta o sitemap - leem os mesmos
arquivos.**

- Um diretorio por tool, `resources/js/tools/<slug>/manifest.json`, com `slug`,
  `status` (`soon` | `available` | `hidden`), `category`, `icon`, `order` e
  `keywords`. O slug e o nome do diretorio e tambem o prefixo das chaves de
  traducao.
- **Nenhum texto visivel mora no manifesto.** O nome, a descricao e as
  palavras-chave traduzidas saem de `tools.<slug>.name`,
  `tools.<slug>.description` e `tools.<slug>.keywords` nos 5 idiomas, por
  convencao a partir do slug; o `keywords` do manifesto e so o termo tecnico que
  nao se traduz (`jsonpath`, `jwt`, `base64`).
- O lado TypeScript (`registry.ts`) carrega tudo com
  `import.meta.glob('./*/manifest.json', { eager: true })`, valida os campos e
  ordena por `order`. Tipo de status desconhecido ou categoria fora do catalogo
  derrubam o teste, nao a tela.
- O lado PHP (`App\Support\Tools`) le os mesmos arquivos do disco para o
  sitemap, e **so** para o sitemap. O caminho e parametro do metodo, para os
  testes apontarem para uma fixture.
- O catalogo de categorias (`categories.ts`: id, ordem e icone) e separado do
  registro, porque e uma lista fechada e vive no tipo `ToolCategoryId`, nao nos
  manifestos. Os chips mostram so as categorias que tem tool.
- **`status` e o que separa o que existe do que ainda nao existe**:
  `available` navega e entra no sitemap, `soon` aparece com selo e nao navega, e
  `hidden` nao aparece em lugar nenhum (nem na busca).

## Por que
A versao anterior deste ADR dizia `manifest.ts` e deixava os leitores
implicitos. A F1 precisou decidir o formato, porque o mesmo dado passou a ser
lido de dois mundos: o front monta a home e a palette, e o back monta o
sitemap - que nao pode listar uma tool que ainda nao abre.

Um arquivo **JSON** e a unica forma de os dois lerem a mesma coisa sem um gerar
codigo para o outro: o Vite o transforma em modulo no build
(`import.meta.glob`), e o PHP o le com `json_decode`. Um `manifest.ts` seria
mais expressivo para o TypeScript (permitiria importar o componente do icone) e
ilegivel para o PHP, que teria que parsear TypeScript ou manter uma segunda
lista - e uma segunda lista e exatamente o que este ADR existe para evitar.

Os textos ficam fora do manifesto pelo mesmo motivo que os precos ficam fora do
codigo: o manifesto e lido no build, e traducao e conteudo que muda sem deploy.
Um nome de produto em cinco idiomas dentro do JSON viraria cinco manifestos.

## Consequencias
- `tools.path` em `config/tools.php` aponta para o diretorio do registro; os
  testes trocam por `tests/Fixtures/tools`, que tem uma tool `available` e uma
  `soon` - e assim o sitemap e testado nos dois estados sem depender de qual
  tool ja ficou pronta.
- Registrar uma tool nova = criar o diretorio, o manifesto e as chaves de
  traducao nos 5 idiomas. O `TranslationsParityTest` cobra a paridade e o
  `ToolsRegistryTest` cobra icone, categoria e ordem.
- **A rota `/{locale}/tools/{slug}` so existe a partir da F2**, junto com a
  primeira tool `available`. Na F1 nada e `available`, entao nao ha link
  quebrado e o sitemap nao lista tool nenhuma; `Sitemap::urls()` pergunta por
  `Route::has('tools.show')` antes de tentar montar essas URLs.
- O icone e um **nome** no manifesto (`braces`, `database`), resolvido em
  `icons.ts` para um componente do Lucide: o JSON nao carrega componente, e o
  mapa nao pode ter buraco (ha teste para isso).
- `hidden` existe desde ja para tool despublicada sem apagar o diretorio. Nada
  usa o estado ainda, mas **a F3 alinhou os dois leitores**: o `Tools::all()` do
  PHP tambem exclui `hidden`, como o `allTools` do TypeScript. O que motivou o
  alinhamento foi o consumidor que este ADR nao contava - `GenerateOgImage`
  desenha o cartao padrao de compartilhamento a partir de `all()`, e uma tool
  escondida entraria no nome dos cards. O `Tools::find()` le `all()`, entao uma
  tool `hidden` nao e encontrada: e o mesmo `null` do slug desconhecido, que a
  pagina da tool transforma no mesmo 404 (ADR-029).
- O sitemap so conhece o registro, nao o banco: nao ha consulta para monta-lo, e
  um manifesto malformado aparece como erro no teste, nunca como 500 no ar.

## Alternativas descartadas
- **`manifest.ts`** (o que o ADR dizia): o PHP nao le, e o sitemap ficaria com
  uma lista paralela.
- **Registro num arquivo unico** (`tools.json` com as 8): a tool deixa de ser um
  modulo - o diretorio dela passa a guardar so o componente, e a identidade fica
  longe do codigo que a implementa.
- **Texto visivel no manifesto**: cinco manifestos por tool ou traducao presa no
  build. A regra do projeto e i18n em `lang/{locale}/*.php`.
- **Derivar o sitemap de uma rota `tools.*` no banco**: o que existe e um
  arquivo, e um estado de banco so para dizer "esta tool ja funciona" seria mais
  uma coisa para manter em sincronia com o codigo.
- **Um `sort` no lugar de `order`**: o nome ficou `order` porque e o que o
  `manifest.json` diz; `sort` ficaria parecido com o `sort` da tabela `plans`
  sem ser a mesma coisa.
