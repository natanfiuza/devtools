# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: adiciona ferramenta de compartilhamento de texto compatível com Hastebin
```

---

## 🎯 Resumo

Este PR entrega a ferramenta **Share Text**, um serviço de compartilhamento de textos e snippets com links curtos baseados em ULID e compatibilidade total com a API do Hastebin (ADR-058). O objetivo é oferecer uma alternativa interna ao pastebin/Hastebin, permitindo que visitantes anônimos compartilhem conteúdo rapidamente (retenção de 72h) e que usuários autenticados tenham armazenamento permanente com histórico e exclusão gerenciados. A entrega também inclui a infraestrutura de limpeza automática de conteúdos anônimos expirados, garantindo conformidade com retenção mínima e uso controlado de disco.

## 🛠️ Mudanças Técnicas

- **Novo domínio `SharedText`**: model com ULID, `expires_at` opcional, escopos `expired` e `forUser`, além de helpers `getContent`/`deletePhysicalFile` para persistência em disco.
- **`ShareTextStorage`**: serviço central que aplica limites por plano (anônimo 1 MB, free 5 MB, pago 25 MB), serializa o arquivo físico em `storage/app/data/sharetext/{user_id|anonymous}/{ulid}.txt` e centraliza busca/exclusão.
- **`ShareTextController`**: expõe endpoints compatíveis com Hastebin (`POST /documents`, `GET /documents/{id}`), texto cru seguro (`GET /raw/{id}` com `X-Content-Type-Options: nosniff`), redirecionamento curto (`/share/{id}`) e API interna autenticada de histórico/exclusão.
- **Comando `share-text:prune`**: poda registros anônimos expirados e arquivos órfãos do diretório `anonymous`, com suporte a `--dry-run`.
- **Agendamento automático**: migration que registra o cron horário `0 * * * *` do comando de poda em `task_schedules`.
- **Migração `shared_texts`**: tabela com FK opcional para `users` (cascade on delete), índices em `(user_id, created_at)` e `expires_at`.
- **Roteamento**: novas rotas `/share/{id}`, `/raw/{id}`, `/documents` e grupo `api/tools/share-text/*`, além da rota web `tools/share-text/{id}` para hidratar o editor com o documento.
- **`ShareTextToolProps`**: adiciona `initialDocument`, `isAuthenticated`, `isPaidPlan`, `maxUploadBytes` e `retentionHours` na página genérica da ferramenta.
- **Frontend `Tool.vue`**: editor CodeMirror com seletor de linguagem (auto/JSON/SQL/Markdown), atalhos de teclado (Ctrl+S/N/D/R), dialog de compartilhamento com URLs curta/raw, histórico autenticado e exclusão.
- **Internacionalização**: chaves `tools.share-text.*` em `en`, `es`, `fr`, `pt_BR` e `pt_PT`.
- **Testes**: cobertura Feature (armazenamento, limites, expiração, poda, autenticação, redirect) e Vitest (renderização anônima/autenticada, badges, histórico).
- **Chore**: reformatação Prettier em `base64-image` (`DecoderPanel.vue`, `HistoryPanel.vue`, `Tool.vue`) e remoção de import não utilizado em `Contact.vue`.

## ⚠️ Impacto/Avisos

- **Banco de dados**: duas migrations são executadas — criação de `shared_texts` e seed em `task_schedules` para a poda horária. Em ambientes com `task_schedules` já populado, o seed é idempotente via verificação por `command`.
- **Variáveis de ambiente**: introduzidas `TOOLS_SHARE_TEXT_ANONYMOUS_MAX_BYTES` (1 MB), `TOOLS_SHARE_TEXT_ANONYMOUS_RETENTION_HOURS` (72), `TOOLS_SHARE_TEXT_FREE_MAX_BYTES` (5 MB) e `TOOLS_SHARE_TEXT_PAID_MAX_BYTES` (25 MB). Os defaults já estão no `config/tools.php`, mas recomenda-se revisar em produção.
- **Segurança / CSRF**: `/documents*` foi adicionado à lista de exceções CSRF em `bootstrap/app.php` para suportar clientes CLI (Hastebin). A API é pública por design; monitore abuso via limites de tamanho por plano/visitante.
- **Armazenamento em disco**: novos arquivos em `storage/app/data/sharetext/`. Certifique-se de incluir no backup e verifique espaço em disco; o comando `share-text:prune` faz a limpeza de anônimos expirados de hora em hora.
- **Scheduler**: o cron depende do agendador do Laravel rodando (`schedule:run`). Sem ele, a poda horária não será executada e o disco crescerá indefinidamente com anônimos expirados.
- **Compatibilidade Hastebin**: o retorno de `POST /documents` segue o formato `{"key": "<ulid>"}`, diferente do `{key, ...}` canônico do Hastebin original; clientes que dependem de campos extras podem precisar de ajuste.
- **Assets**: novo `public/og/share-text.png` e atualizações em `og-default.png`/`og/base64-image.png` — validar cache/CDN após deploy.


close #103


---

[![GitPR](https://img.shields.io/badge/GitPR-0_errors_%C2%B7_30_warnings-yellow)](https://gitpr.natanfiuza.dev.br/)