# ADR-056: Internacionalizacao de Prompts e Isolamento de Cache no AI SQL Builder

Status: Aceito
Data: 2026-10-23
Relacionados: ADR-010 (i18n), ADR-011 (output targets), ADR-016 (aicache md5), ADR-040 (ciclo da solicitacao), ADR-042 (contrato http da ia)

## Contexto

O AI SQL Builder gera consultas SQL e codigos para diversos ORMs/alvos a partir de um schema e descricao em linguagem natural. A interface do site esta disponivel em cinco idiomas (`en`, `pt_BR`, `pt_PT`, `fr`, `es`).

Anteriormente, o prompt de sistema do backend (`PromptBuilder`) era fixo em ingles com uma diretiva generica de "responder no mesmo idioma do pedido". Esse modelo causava inconsistencias:
1. Muitas vezes o LLM respondia os campos descritivos (`explanation`, `assumptions`, `warnings`) em ingles, mesmo quando o usuario navegava em portugues, espanhol ou frances;
2. O `CanonicalPayload` e o `prompt_hash` (usado como chave de busca no `AiCache` e na deduplicacao em voo) nao consideravam o idioma da requisicao. Isso poderia ocasionar colisoes de cache entre idiomas: uma consulta identica submetida em ingles e depois em portugues serviria a explicacao cacheada em ingles para o usuario em portugues.

## Decisao

### 1. Envio Explicito de Locale pelo Frontend e Fallback

O frontend inclui o `locale` ativo da sessao no payload da requisicao (`POST /api/ai/queries` e `POST /api/v1/sql-queries`).
O `StoreAiQueryRequest` valida o campo contra os idiomas suportados pela plataforma (`config/locales.php`). Caso o campo venha omitido (ex: chamadas externas da API v1 sem header ou parametro), o sistema adota `Locales::preferred($request)`.

### 2. Prompts de Sistema Dedicados por Idioma

Criou-se a pasta `resources/prompts/system/` com um arquivo Markdown para cada idioma suportado:
- `en.md`
- `pt_BR.md`
- `pt_PT.md`
- `es.md`
- `fr.md`

Cada arquivo contem as instrucoes de sistema e regras duras redigidas no proprio idioma alvo, exigindo estritamente que os campos textuais (`title`, `explanation`, `assumptions`, `warnings` e `clarification`) sejam gerados no idioma selecionado.
O formato JSON (`JSON SHAPE`) e as chaves de dados permanecem em ingles (`sql`, `dialect`, `assumptions`, `warnings`, `outputs`, etc.), garantindo total compatibilidade com o validador de contrato (`ContractValidator`).

### 3. Inclusao do Locale no CanonicalPayload e Prompt Hash

O `CanonicalPayload` passa a conter a propriedade `locale`.
O array serializado inclui `'locale' => $this->locale` e mantem as chaves ordenadas via `ksort`.
O `prompt_hash` (MD5 do JSON canonico) reflete agora o idioma da consulta. Consultas em idiomas diferentes possuem hashes distintos e geram registros independentes no `AiCache`.

Para retrocompatibilidade (sem downtime durante deploys com workers ativos), `CanonicalPayload::fromArray()` assume `Locales::fallback()` (`en`) se o campo `locale` estiver ausente no payload descompactado.

### 4. Persistencia em `ai_requests`

Adicionou-se a coluna `locale` (`string(10)->nullable()`) na tabela `ai_requests` via migration retrocompativel, indexada para fins de auditoria, historico e relatorios operacionais.

## Consequencias

### Positivas
- Garantia de que as explicacoes ("Como a consulta foi construida"), premissas e avisos sejam gerados no idioma em que o usuario esta utilizando a plataforma.
- Eliminacao de colisao de cache entre linguas no `AiCache`.
- Preservacao integral do contrato JSON estrito do validador.
- Compatibilidade retroativa garantida tanto no desempacotamento de payloads em voo quanto em chamadas legadas da API v1.

### Negativas / Custos
- Manutencao de 5 arquivos de prompt de sistema sob `resources/prompts/system/` quando regras gerais de sistema forem atualizadas.
