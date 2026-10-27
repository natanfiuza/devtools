# Plano de Implementação — Ferramenta Share Text (Hastebin Clone & API)

Data: 2026-10-26  
Branch: `develop_natan`  
Task: `share_text`  
Status: Proposto / Pronto para Execução  

---

## 1. Visão Geral e Objetivos

Implementar a ferramenta **Share Text** no devtools, servindo como uma alternativa completa ao Hastebin (https://www.toptal.com/developers/hastebin/about), com:
1. **Armazenamento de Textos:**
   - Textos salvos em disco em `storage/app/data/sharetext/{user_id}/{ulid}.txt` para usuários autenticados.
   - Textos salvos em `storage/app/data/sharetext/anonymous/{ulid}.txt` para visitantes não autenticados.
2. **Retenção e Cotas:**
   - Não cadastrados (anônimos): retenção de 72 horas com limite de 1 MB.
   - Cadastrados gratuitos (Free): retenção permanente com limite de 5 MB.
   - Cadastrados assinantes (Pro): retenção permanente com limite de 25 MB.
3. **API Hastebin Compatível:**
   - `POST /documents`: aceita payload de texto puro ou JSON (`{"data": "..."}`), detecta autenticação (sessão ou Sanctum Token) e retorna `{"key": "<ulid>"}`.
   - `GET /documents/{id}`: retorna `{"key": "<ulid>", "data": "<conteudo>"}`.
   - `GET /raw/{id}`: entrega o texto puro com `Content-Type: text/plain; charset=utf-8` e proteção `nosniff`.
4. **Rotas Web Amigáveis:**
   - `/{locale}/tools/share-text/{id?}`: interface web com o editor carregando o conteúdo do ULID em modo leitura/duplicação.
   - `/share/{id}`: atalho público curto que redireciona para o idioma do visitante.
5. **Interface Web (Vue 3 + CodeMirror 6):**
   - Ações: Novo (`Ctrl+N`), Salvar (`Ctrl+S`), Duplicar & Editar (`Ctrl+D`), Raw (`Ctrl+R`), Copiar Link.
   - Suporte a extensões para syntax highlighting (ex: `.js`, `.py`, `.sql`, etc.).
   - Drawer/Aba de histórico para usuários logados com remoção física imediata.
6. **Comando de Limpeza:**
   - `App\Console\Commands\PruneSharedTexts` agendado a cada hora para podar anônimos expirados (> 72h).
7. **Documentação e Padrões:**
   - ADR-058, atualização do glossário, i18n em 5 idiomas (`pt-BR`, `pt-PT`, `en`, `es`, `fr`), testes Pest e Vitest.

---

## 2. Etapas de Execução

### Fase 1: Configuração, Migração e Modelo Eloquent
- **1.1. `config/tools.php`:**
  - Adicionar chave `limits.share_text` com `anonymous_max_bytes` (1MB), `anonymous_retention_hours` (72), `free_max_bytes` (5MB) e `paid_max_bytes` (25MB).
- **1.2. Migration:**
  - Criar `database/migrations/2026_10_26_000001_create_shared_texts_table.php`:
    - `id` (ULID char 26, PK)
    - `user_id` (foreignId nullable, cascade on delete)
    - `file_path` (string)
    - `size_bytes` (unsignedBigInteger)
    - `extension` (string(16) nullable)
    - `expires_at` (timestamp nullable, indexado)
    - `created_at`, `updated_at`
- **1.3. Modelo Eloquent `App\Models\SharedText`:**
  - Relacionamento com `User`.
  - Métodos utilitários: `getContent()`, `deletePhysicalFile()`, `isExpired()`, scopes `expired()` e `forUser()`.

### Fase 2: Serviços de Armazenamento e Controllers de Backend
- **2.1. Serviço/Suporte `App\Support\ShareText\ShareTextStorage`:**
  - Lógica centralizada para determinar diretório de destino (`{user_id}` vs `anonymous`), validar limites de tamanho por tipo de usuário (anônimo, Free, Pro), gravar arquivo físico no disco, criar registro no banco e retornar o modelo.
- **2.2. Controller Hastebin API `App\Http\Controllers\ShareTextController`:**
  - `store(Request $request)`: implementa `POST /documents`. Suporta body cru ou JSON `{ data }`. Responde `{"key": "<ulid>"}` com status 201.
  - `show(Request $request, string $id)`: implementa `GET /documents/{id}`. Suporta `{id}` com ou sem extensão. Responde `{"key": "<ulid>", "data": "..."}`.
  - `raw(Request $request, string $id)`: implementa `GET /raw/{id}`. Responde com `Response::make($content, 200, ['Content-Type' => 'text/plain; charset=utf-8', 'X-Content-Type-Options' => 'nosniff'])`.
  - `redirectShort(Request $request, string $id)`: implementa `/share/{id}`, redirecionando para a rota web com locale.
- **2.3. Endpoints de Histórico e Gestão:**
  - `history(Request $request)`: lista os textos do usuário autenticado paginados ou ordenados por criação.
  - `destroy(Request $request, string $id)`: remove fisicamente e apaga o registro do texto pertencente ao usuário autenticado.
- **2.4. Integração com `ToolProps` e `ToolController`:**
  - Atualizar `app/Support/ToolProps.php` e criar `App\Support\ShareText\ShareTextToolProps` para injetar o texto carregado quando `share-text` receber o parâmetro `{id}` na rota web.

### Fase 3: Rotas
- **3.1. `routes/web.php`:**
  - Rota de atalho curto: `/share/{id}` -> `ShareTextController@redirectShort`
  - Rota de raw: `/raw/{id}` -> `ShareTextController@raw`
  - Rota Hastebin documents: `POST /documents` e `GET /documents/{id}` -> `ShareTextController`
  - Rota da ferramenta com ID opcional: `Route::get('tools/share-text/{id?}', ToolController::class)`
  - Rotas de API interna da ferramenta:
    - `GET /api/tools/share-text/history`
    - `DELETE /api/tools/share-text/{id}`

### Fase 4: Comando de Limpeza (Prune) e Agendamento
- **4.1. Console Command `App\Console\Commands\PruneSharedTexts`:**
  - Consulta registros anônimos com `expires_at <= now()`.
  - Remove os arquivos físicos em `storage/app/data/sharetext/anonymous/` e deleta os registros em lote.
  - Suporte a flag `--dry-run`.
- **4.2. Agendamento em Migration:**
  - Migration para adicionar o comando `share-text:prune` na tabela `task_schedules` com frequência horária (`0 * * * *`).

### Fase 5: Frontend (Manifesto, Registro e Interface da Ferramenta)
- **5.1. Manifesto:**
  - Criar `resources/js/tools/share-text/manifest.json`:
    - `slug: "share-text"`, `status: "available"`, `category: "text"`, `icon: "file-text"`, `order: 56`, keywords pertinentes.
- **5.2. Componente Principal `resources/js/tools/share-text/Tool.vue`:**
  - Editor CodeMirror 6 ocupando o espaço com numeração de linha e suporte a sintaxes dinâmicas.
  - Barra de ações superior:
    - Botão **Novo** (`Ctrl+N`): reseta o estado e limpa a URL para permitir um novo paste.
    - Botão **Salvar** (`Ctrl+S`): envia para `POST /documents`, recebe o ULID, atualiza a rota e exibe modal/toast de compartilhamento.
    - Botão **Duplicar & Editar** (`Ctrl+D`): clona o conteúdo atual para um novo documento editável.
    - Botão **Raw** (`Ctrl+R`): abre `/raw/{id}` em nova aba.
    - Botão **Copiar Link**: copia a URL pública para o clipboard.
    - Botão **Histórico** (visível para usuários logados): abre gaveta com a lista de textos do usuário, status de permanência e botão de exclusão.
- **5.3. Atalhos globais de teclado:**
  - Interceptar `Ctrl+S`, `Ctrl+N`, `Ctrl+D`, `Ctrl+R` quando o editor estiver em foco ou na ferramenta.

### Fase 6: Internacionalização (i18n)
- Adicionar chaves de tradução completas e com acentuação correta para os 5 idiomas (`pt-BR`, `pt-PT`, `en`, `es`, `fr`):
  - Título, descrição, ações (Salvar, Novo, Duplicar, Raw, Copiar link), mensagens de sucesso/erro, histórico, avisos de expiração (72h para anônimos vs permanente para cadastrados).

### Fase 7: Documentação e Governança
- **7.1. ADR-058:**
  - `docs/adr/ADR-058-ferramenta-share-text-armazenamento-api-hastebin-e-retencao.md`.
- **7.2. Glossário:**
  - Adicionar os termos `Share Text`, `Shared Text` e `Hastebin API` em `docs/glossario.md`.

### Fase 8: Testes Automatizados e Validação
- **8.1. Testes Backend (Pest):**
  - Upload anônimo (limite 1 MB, expiração 72h, criação física em `anonymous/`).
  - Upload autenticado Free (limite 5 MB, expiração null, criação física em `{user_id}/`).
  - Upload autenticado Pro (limite 25 MB).
  - Bloqueio quando excede o tamanho permitido.
  - Compatibilidade com Hastebin CLI (`POST /documents` com texto puro, `GET /documents/{id}`, `GET /raw/{id}`).
  - Poda de anônimos expirados via `share-text:prune`.
  - Exclusão de texto por dono autenticado.
- **8.2. Testes Frontend (Vitest):**
  - Validação do registro e manifesto de `share-text`.
  - Renderização do componente `Tool.vue` e disparo de atalhos/ações.
- **8.3. Verificações de Qualidade:**
  - `composer test` (Pint, PHPStan, Pest).
  - `npm run test` (Vitest).
  - `npm run types:check` (rotas Wayfinder + vue-tsc).

---

## 3. Critérios de Conclusão e Entrega
- Todas as verificações de qualidade passando sem erros ou alertas de tipagem.
- Documentação sincronizada (ADR-058, glossário, survey e plano).
- Geração do relatório final em `docs/gemini/reports/develop_natan/20261026_develop_natan_share_text.md`.
