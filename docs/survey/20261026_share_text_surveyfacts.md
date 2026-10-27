# Survey — Ferramenta Share Text (Hastebin Clone & API) (2026-10-26)

> Levantamento automático da sessão de grill (`/grill-with-docs`) — fronteira esgotada e entendimento compartilhado confirmado pelo usuário.

## 1. Contexto da tarefa
- **Pedido original:** Criar uma nova ferramenta para compartilhar textos baseada no site https://www.toptal.com/developers/hastebin/about, implementando todas as funcionalidades incluindo a API. Os textos devem ficar salvos em `storage/app/data/sharetext/{user_id}` ou `storage/app/data/sharetext/anonymous` para não cadastrados. Para cadastrados o texto é permanente com limite aceitável de arquivo. Para não cadastrados os textos persistem por 72 horas com limite de 1 MB. Criar uma rota pública utilizando ULID para identificar cada texto e salvar com este ULID no storage.
- **Tela / Módulo:** 
  - Web UI: `/{locale}/tools/share-text/{id?}` e atalho `/share/{id}`
  - Rota de visualização crua: `/raw/{id}`
  - API Hastebin: `POST /documents`, `GET /documents/{id}`
  - API de Histórico e Gestão: `GET /api/tools/share-text/history`, `DELETE /api/tools/share-text/{id}`
  - Componente Vue: `resources/js/tools/share-text/Tool.vue`
- **Skill:** `/grill-with-docs` (grilling + domain-modeling) · branch `develop_natan`
- **Método:** 2 rodadas de grilling, 7 decisões mapeadas, inspeção do ecossistema e ferramentas híbridas (`base64-image`, `ai-sql-builder`).
- **Arquivos-chave lidos:**
  - `docs/adr/ADR-055-ferramenta-base64-image-processamento-servidor-e-retencao.md`
  - `app/Http/Controllers/Api/Base64ImageController.php`
  - `app/Console/Commands/PruneBase64Images.php`
  - `config/tools.php`
  - `resources/js/components/tool/CodeEditor.vue`
  - `resources/js/tools/icons.ts`
  - `routes/web.php` e `routes/api.php`

---

## 2. Decisões das rodadas

### Rodada 1 — Arquitetura de Rotas, Armazenamento e Limites
| # | Decisão | Resolução |
|---|---|---|
| Q1 | Superfície de Rotas e API | **Opção A:** Compatibilidade total com Hastebin na raiz (`POST /documents`, `GET /documents/{id}`, `GET /raw/{id}`) + rota pública web `/{locale}/tools/share-text/{id}` e atalho `/share/{id}`. |
| Q2 | Indexação e Metadados dos ULIDs | **Opção A:** Tabela `shared_texts` no banco de dados para busca O(1), suporte a histórico do usuário logado e poda atômica por `expires_at`. |
| Q3 | Limites de Tamanho para Usuários Cadastrados | **Opção A:** 5 MB para usuários cadastrados gratuitos (Free) e 25 MB para assinantes Pro, configurável via `config/tools.php` e `.env`. |
| Q4 | Ações na Interface Web | **Opção A:** Paridade total com Hastebin (Novo, Salvar, Duplicar e Editar, Raw, Copiar Link/Compartilhar) + histórico com remoção para usuários logados. |

### Rodada 2 — Extensões, Autenticação de API e Exclusão
| # | Decisão | Resolução |
|---|---|---|
| Q1 | Extensões na URL (`/{id}.{ext}`) | **Opção A:** Suporte a extensão opcional no identificador para ativação de syntax highlight no editor; rota `/raw/{id}` responde sempre com `Content-Type: text/plain; charset=utf-8` para segurança. |
| Q2 | Autenticação no `POST /documents` | **Opção A:** Endpoint público que detecta autenticação (sessão web ou Bearer Token Sanctum) para conceder retenção permanente e limites ampliados da conta. |
| Q3 | Exclusão de Textos no Histórico | **Opção A:** Exclusão física imediata do arquivo em disco e remoção do registro no banco de dados (respostas subsequentes dão 404). |

### Defaults assumidos (não contestados)
- **Slug:** `share-text` registrado sob a categoria `text`.
- **Ícone:** `file-text` do Lucide.
- **Formato dos arquivos em disco:** Arquivos gravados em `storage/app/data/sharetext/{user_id}/{ulid}.txt` ou `storage/app/data/sharetext/anonymous/{ulid}.txt`.
- **Poda periódica:** Comando `App\Console\Commands\PruneSharedTexts` agendado a cada hora em `task_schedules` para limpar registros anônimos com `expires_at <= now()`.
- **Internacionalização:** Traduções em 5 idiomas (`pt-BR`, `pt-PT`, `en`, `es`, `fr`) sob `tools.share-text.*`.

---

## 3. Relatório de fatos levantados

### Precedente Híbrido no Projeto
O ADR-055 abriu precedente seguro para ferramentas que requerem persistência em disco do servidor sob `storage/app/data/` com identificadores ULID, particionamento por `{user_id}` e `anonymous/`, e rotina periódica de poda via `TaskSchedule`. A ferramenta `share-text` segue exatamente esta mesma arquitetura comprovada.

### Segurança e Rota Raw
A rota `/raw/{id}` deve sempre forçar `Content-Type: text/plain; charset=utf-8` e cabeçalhos de segurança contra MIME-sniffing (`X-Content-Type-Options: nosniff`) para evitar que scripts maliciosos (como arquivos `.html` ou `.svg` enviados) sejam executados no contexto da origem da aplicação.

### CodeMirror e Destaque Dinâmico
O componente `CodeEditor.vue` aceita extensões do CodeMirror 6. Podemos associar a extensão da URL (ex: `.js`, `.ts`, `.py`, `.json`, `.sql`, `.php`, `.html`, `.css`, `.md`) à linguagem do CodeMirror correspondente para destaque de sintaxe automático tanto na visualização quanto na edição.
