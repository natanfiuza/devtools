# Relatório de Implementação — Ferramenta Share Text (Hastebin Clone & API)

**Data:** 2026-10-26  
**Branch:** `develop_natan`  
**Tarefa:** `share_text`  
**Status:** Concluída com Sucesso  

---

## 1. Resumo da Tarefa e Decisões

Foi desenvolvida e integrada com sucesso a nova ferramenta **Share Text** (`share-text`), servindo para compartilhamento ágil de textos, códigos e notas, com paridade integral à especificação e ecossistema do **Hastebin** (https://www.toptal.com/developers/hastebin/about), incluindo:
- Armazenamento físico particionado no disco:
  - `storage/app/data/sharetext/{user_id}/{ulid}.txt` para usuários autenticados (permanente).
  - `storage/app/data/sharetext/anonymous/{ulid}.txt` para visitantes não autenticados (retenção de 72 horas).
- Limites de tamanho configuráveis em `config/tools.php` com suporte a variáveis de ambiente (`.env`):
  - Anônimos: 1 MB (`TOOLS_SHARE_TEXT_ANONYMOUS_MAX_BYTES`).
  - Cadastrados Free: 5 MB (`TOOLS_SHARE_TEXT_FREE_MAX_BYTES`).
  - Cadastrados Pro: 25 MB (`TOOLS_SHARE_TEXT_PAID_MAX_BYTES`).
- Superfície de API pública Hastebin universal:
  - `POST /documents`: aceita payload de texto puro ou JSON (`{"data": "..."}`), detecta sessão web ou Bearer Token Sanctum, isento de validação CSRF em `bootstrap/app.php` para suporte nativo a clientes CLI (`curl`, `haste-client`). Retorna `{"key": "<ulid>"}` com status 201.
  - `GET /documents/{id}`: retorna `{"key": "<ulid>", "data": "<conteudo>"}`.
  - `GET /raw/{id}`: entrega o texto puro com `Content-Type: text/plain; charset=utf-8` e `X-Content-Type-Options: nosniff`.
- Rotas públicas da aplicação:
  - `/{locale}/tools/share-text/{id?}`: página web da ferramenta, carregando documento pelo ULID no editor em modo leitura/duplicação.
  - `/share/{id}`: atalho público curto que detecta o idioma de preferência do visitante e redireciona.
  - Suporte a extensões na URL (`/{id}.{ext}`) para ativação automática do modo de sintaxe no CodeMirror 6.
- Interface Web (`Tool.vue`):
  - Ações e atalhos de teclado: Salvar (`Ctrl+S`), Novo (`Ctrl+N`), Duplicar & Editar (`Ctrl+D`), Raw (`Ctrl+R`), Copiar Link e Histórico.
  - Diálogo de compartilhamento com links para cópia direta.
  - Histórico de textos para usuários logados com exclusão imediata e atômica.
- Limpeza e Poda Automática:
  - Comando `App\Console\Commands\PruneSharedTexts` (`share-text:prune`) com suporte a `--dry-run`.
  - Agendamento horário registrado na tabela `task_schedules`.

---

## 2. Arquivos Criados e Modificados

### Arquivos Criados:
1. `config/tools.php` — adicionado bloco de configuração de limites e retenção `share_text`.
2. `database/migrations/2026_10_26_000001_create_shared_texts_table.php` — tabela `shared_texts` com ULID primário, chave estrangeira nullable para `users`, caminho de arquivo, tamanho em bytes, extensão e índice de expiração.
3. `database/migrations/2026_10_26_000002_seed_share_text_maintenance_schedule.php` — agendamento horário do comando `share-text:prune` em `task_schedules`.
4. `database/factories/SharedTextFactory.php` — factory com estados `anonymous` e `expired`.
5. `app/Models/SharedText.php` — modelo Eloquent com métodos utilitários `getContent()`, `deletePhysicalFile()`, `isExpired()` e escopos `expired()` e `forUser()`.
6. `app/Support/ShareText/ShareTextStorage.php` — gerenciador central de disco e ciclo de vida de textos compartilhados.
7. `app/Support/ShareText/ShareTextToolProps.php` — empacotador de propriedades da ferramenta para injeção via Inertia.
8. `app/Http/Controllers/ShareTextController.php` — endpoints `/documents`, `/documents/{id}`, `/raw/{id}`, `/share/{id}` e API interna de histórico.
9. `app/Console/Commands/PruneSharedTexts.php` — comando de poda de textos anônimos expirados (> 72h).
10. `resources/js/tools/share-text/manifest.json` — manifesto da ferramenta sob categoria `text`, ordem `56` e ícone `file-text`.
11. `resources/js/tools/share-text/Tool.vue` — componente Vue 3 com CodeMirror 6, toolbar, atalhos de teclado, diálogos de compartilhamento e histórico.
12. `docs/adr/ADR-058-ferramenta-share-text-armazenamento-api-hastebin-e-retencao.md` — registro de decisão arquitetural.
13. `docs/survey/20261026_share_text_surveyfacts.md` — levantamento da sessão de grill (`/grill-with-docs`).
14. `docs/gemini/plans/develop_natan/20261026_develop_natan_share_text.md` — plano aprovado de implementação.
15. `tests/Feature/Tools/ShareTextTest.php` — 13 testes cobrindo upload anônimo e autenticado, compatibilidade Hastebin CLI, rota `/raw`, limites de tamanho, histórico e comando de poda.
16. `resources/js/__tests__/share-text.test.ts` — testes unitários Vitest do componente `Tool.vue`.
17. `public/og/share-text.png` — imagem OG gerada para compartilhamento social (1200x1200).

### Arquivos Modificados:
1. `app/Http/Controllers/ToolController.php` e `app/Support/ToolProps.php` — suporte a resolução de documento pelo parâmetro de rota.
2. `app/Models/User.php` — relacionamento `sharedTexts()`.
3. `bootstrap/app.php` — isenção de verificação CSRF para `documents*`.
4. `routes/web.php` — registro das rotas públicas web, rotas curtas `/share/{id}` e `/raw/{id}`, rotas da API Hastebin `/documents` e endpoints de histórico.
5. `lang/{en,es,fr,pt_BR,pt_PT}/tools.php` — traduções completas nos 5 idiomas para chaves `name`, `description`, `intro`, `keywords`, `actions`, `badges`, `dialog`, `history`, `languages`, `errors` e `messages`.
6. `docs/glossario.md` — termos adicionados: `Share Text`, `Shared Text` e `API Hastebin`.
7. `tests/Feature/Tools/ToolsRegistryTest.php` e `resources/js/__tests__/tools-registry.test.ts` — catálogo de tools atualizado com `share-text`.

---

## 3. Validação e Qualidade

Todos os testes e ferramentas de verificação de qualidade foram executados com sucesso:
- **Backend (Pest):** 709 testes passando, 0 falhas, 16.012 asserções (`tests/Feature/Tools/ShareTextTest.php` 100% verde).
- **Frontend (Vitest):** 62 arquivos de teste passando, 806 testes unitários no total (`resources/js/__tests__/share-text.test.ts` 100% verde).
- **Tipagem (Wayfinder + vue-tsc):** `npm run types:check` passou com 0 erros.
- **PHPStan:** 0 erros no nível estrito.
- **Pint:** Código PHP formatado rigorosamente de acordo com as diretrizes do Laravel Pint.
- **Prettier & ESLint:** Arquivos do frontend validados e formatados conforme o padrão do projeto.
- **Builds de Produção:** `npm run build` e `npm run build:ssr` finalizados com sucesso sem erros.
