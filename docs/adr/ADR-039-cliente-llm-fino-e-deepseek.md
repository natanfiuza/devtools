# ADR-039: Cliente LLM fino e DeepSeek como provedor da F5

Status: Aceito
Data: 2026-10-20
Relacionados: ADR-002 (excecao de servidor), ADR-003 (provider LLM), ADR-004 (schema sem dados), ADR-005 (saida estruturada), ADR-019 (filas)

## Contexto

O AI SQL Builder e a unica tool que fala com um modelo de IA (ADR-002). A F5
precisava escolher **como** o Laravel chama esse modelo. O projeto ja tem
regras que limitam a escolha:

- nomes de modelo, precos e limites **nunca** ficam fixos no codigo - vem de
  `config/` e `.env`;
- schema e descricao nao podem aparecer em log nem em mensagem de excecao;
- o request HTTP do usuario nao pode ficar preso aos 60 s de um provedor;
- a resposta precisa ser JSON validavel, porque o contrato e o que a tela
  desenha.

Na epoca existiam quatro caminhos: o Laravel AI SDK (abstracao oficial para
varios provedores), o SDK oficial do DeepSeek, o cliente HTTP direto do
Laravel (`Illuminate\Support\Facades\Http`) ou um cliente Guzzle proprio. O
provedor escolhido para a F5 foi o DeepSeek, cuja API e compativel com o
formato OpenAI (`POST /chat/completions`, `response_format: json_object`).

## Decisao

Adotamos um **cliente fino sobre o `Http` do Laravel**, atras de um contrato
proprio de uma operacao so:

- `App\Contracts\Ai\LlmProvider` com um metodo que recebe o prompt e o modelo e
  devolve `App\Support\Ai\LlmCompletion` (conteudo, tokens de entrada e saida,
  modelo respondido);
- `App\Services\Ai\DeepSeekProvider` faz `POST {AI_BASE_URL}/chat/completions`
  com `Bearer AI_API_KEY`, `response_format: {type: "json_object"}`,
  `temperature` e `max_tokens` vindos de `config/ai.php`;
- timeouts de `AI_TIMEOUT` (60 s) e `AI_CONNECT_TIMEOUT` (10 s), ambos
  configuraveis;
- o mapeamento de erro separa **retentavel** (429, 5xx, 408/timeout de rede) de
  **nao retentavel** (401, 403, 400, 422) e **nunca** copia o corpo da resposta
  do provedor para a excecao, o log ou o banco - no maximo status HTTP e um
  identificador de requisicao, quando o provedor manda;
- o binding e registrado em `App\Providers\AiServiceProvider` (junto dos
  rate limiters), e o job depende do contrato, nao da classe;
- em teste, o `Http::fake()` substitui o provedor: nenhum teste chama a rede.

**Nao** entrou o Laravel AI SDK nem o SDK do DeepSeek. O primeiro traz uma
camada de abstracao e dependencias para um cenario multi-provedor que ainda
nao existe; o segundo amarra o codigo ao fornecedor e ao formato de erro dele.
Um cliente de ~100 linhas sobre o `Http` cobre o que a F5 precisa, e o contrato
`LlmProvider` deixa a porta aberta para trocar o provedor sem tocar no job.

## Consequencias

### Positivas

- Uma dependencia a menos no `composer.json` e nenhuma abstracao que o projeto
  nao usa (sem streaming, sem tool calling, sem embeddings).
- Os timeouts e o mapeamento de erro sao do projeto, nao do SDK: o job sabe
  exatamente o que e retentavel e o que falha na primeira.
- O corpo do provedor nao vaza: a excecao carrega so o necessario para o
  `error_code` e o log carrega `ai_request_id` e metadados.
- Trocar DeepSeek por outro provedor compativel com OpenAI e uma classe nova e
  um binding; trocar por um formato diferente e uma implementacao nova do
  contrato, sem mudar a fila.

### Negativas / trade-offs

- O projeto mantem a conversa com o formato OpenAI na mao; uma mudanca do
  provedor (campo novo, erro novo) exige ajuste local.
- Nao ha retry automatico do SDK: o retry e do job (`tries=2`, backoff 5/15 s),
  e so para erros retentaveis. Erro de chave ou de requisicao falha na hora.
- Sem streaming na F5: a tela recebe a resposta pronta pelo polling. Streaming
  fica para uma fase posterior, se houver necessidade.

## Alternativas descartadas

- **Laravel AI SDK**: abstrai varios provedores, mas adiciona dependencia e
  superficie de configuracao para um unico provedor e um unico tipo de chamada;
  o controle de timeout e de vazamento de corpo ficaria menos explicito.
- **SDK oficial do DeepSeek**: amarraria a aplicacao ao fornecedor e ao formato
  de excecao dele, contra a regra de nao fixar provedor no codigo.
- **Guzzle direto**: mesmo resultado do `Http` com mais codigo; o `Http` ja e o
  cliente do framework e tem `fake()` para teste.
- **Chamar o provedor no request HTTP**: estouraria o tempo de resposta e
  prenderia o PHP-FPM; a fila existe exatamente para isso (ADR-019).
