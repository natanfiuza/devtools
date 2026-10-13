# ADR-003-llm-provider
Status: Aceito
Data: 2026-10-05

## Decisao
Interface LlmProvider; implementacao DeepSeek (API compativel com OpenAI), modelo flash configuravel em config/.env; chave do servidor. Avaliar Laravel AI SDK vs cliente HTTP proprio na F5 (padrao: cliente HTTP fino, menos dependencia).

## Consequencias
Ver SPEC.md.
