# ADR-004-schema-sem-dados
Status: Aceito
Data: 2026-10-05

## Decisao
Entrada somente schema, nunca dados; aviso na UI.

## Consequencias
Ver SPEC.md.

## Emenda da F5 (2026-10-20)

A regra foi implementada e ampliada, e nao so declarada:

- **Entrada**: `schema` + `description` + `schema_source` + `dialect` +
  `output_target`. Nao ha campo para linhas, resultados de consulta, dumps ou
  credenciais; o formulario diz, no texto de ajuda, que so o schema deve ser
  colado (o aviso esta nos cinco idiomas).
- **O que vai ao provedor**: o schema e a descricao, dentro de delimitadores
  que os tratam como dado (ADR-040/SPEC secao 6); nada mais da conta.
- **O que fica no servidor**: o payload canonico comprimido em
  `ai_requests.canonical_payload` enquanto a solicitacao esta em voo, apagado
  ao terminar, e a resposta validada em `ai_cache` por ate 30 dias (ADR-016).
- **O que nunca fica**: schema, descricao ou prompt em log, excecao ou resposta
  de erro - os testes conferem que o texto marcado nao aparece no log do job.
- A Politica de Privacidade foi reescrita nos cinco idiomas para dizer que esta
  tool envia o schema ao provedor de IA, que a resposta pode ficar em cache e
  que o SQL nunca e executado; a frase antiga de que "nada sai do navegador"
  deixou de valer para esta tool e continua valendo para as outras oito.
