# Survey — Base64 Image Encoder / Decoder (2026-10-23)

> Levantamento automatico da sessao de grill (`/grill-with-docs`) — fronteira esgotada e entendimento compartilhado confirmado pelo usuario.

## 1. Contexto da tarefa
- **Pedido original:** Criar uma nova ferramenta: Base64 Image Encoder / Decoder. Receber imagens e converter para Base64 (exibicao em tag img, css ou texto puro). Limitacao de 2 MB no plano Free e limite maximo aceitavel no plano Pago (conversao no lado do servidor via PHP; download direto se muito grande para exibir). Historico de conversao para plano pago (arquivos mantidos por ate 30 dias; registro persiste no banco). Arquivos guardados com identificador ULID em `storage/app/data/base64_images/{user_id}/` (apagados apos 24h no free) e `storage/app/data/base64_images/anonymous` (apagados apos 24h). Usuarios anonimos tem historico em localStorage sem banco. Usuarios cadastrados no free registram no banco para sempre e arquivo fisico apaga apos 24h.
- **Tela / Modulo:** Rota publica `/{locale}/tools/base64-image`, manifest `resources/js/tools/base64-image/manifest.json`, componente `Tool.vue`, endpoints em `app/Http/Controllers/Api/Base64ImageController.php` e download/preview em `app/Http/Controllers/Base64ImageDownloadController.php`.
- **Skill:** `/grill-with-docs` (grilling + domain-modeling) · branch `develop_natan`
- **Metodo:** 3 rodadas de interrogacao técnica, 12 decisoes resolvidas, analise do modelo de planos, task scheduler, armazenamento e componentes do ecossistema.
- **Arquivos-chave lidos:**
  - `docs/SPEC.md`
  - `docs/glossario.md`
  - `docs/RUNBOOK.md`
  - `docs/adr/ADR-002-processamento-local.md`
  - `config/tools.php`
  - `app/Models/Plan.php`
  - `app/Models/User.php`
  - `app/Support/ToolProps.php`
  - `app/Http/Controllers/ToolController.php`
  - `app/Console/Commands/PruneHistory.php`
  - `bootstrap/app.php`

## 2. Decisoes das rodadas

### Rodada 1 — Limites, Armazenamento e Escopo do Decoder
| # | Decisao | Resolucao |
|---|---|---|
| Q1 | Limite maximo do plano pago e limiar para download direto | **25 MB de teto de upload no plano pago**; limiar de **2 MB para download direto** em vez de renderizacao completa da string no DOM/CodeMirror. |
| Q2 | Arquivos persistidos no disco sob o ULID | **Ambos os arquivos salvos**: a imagem original (`{ulid}.original.{ext}`) e o texto convertido (`{ulid}.txt`). |
| Q3 | Escopo e arquitetura do modo Decoder | **Decoder 100% no cliente (navegador)**: decodificacao de Data-URI/Base64 instantanea via browser com visualizacao de imagem e download nativo via Blob. |
| Q4 | Slug canonico e categoria da tool | **Slug `base64-image`** na categoria `media` (URL `/{locale}/tools/base64-image`). |

### Rodada 2 — Experiencia do Historico, Retencao e Configuracao
| # | Decisao | Resolucao |
|---|---|---|
| Q5 | Onde exibir o Historico de Conversoes | **Aba/secao dedicada de Historico dentro da propria ferramenta**, unificando consulta para autenticados (via backend) e anonimos (via localStorage). |
| Q6 | Exibicao de registros cujo arquivo fisico expirou | **Metadados tecnicos preservados no historico**, exibindo selo/badge `Expirado` e acoes de download desabilitadas quando o arquivo for removido do disco. |
| Q7 | Conteudo do arquivo no download direto | **Sempre o Data URI puro (`data:image/...;base64,...`)** no arquivo `.txt` baixado. |
| Q8 | Onde configurar os limites de upload por plano | **Coluna `max_image_upload_bytes` na tabela `plans`**, com fallbacks em `config/tools.php` e `.env` (`TOOLS_BASE64_IMAGE_FREE_MAX_BYTES` e `TOOLS_BASE64_IMAGE_PAID_MAX_BYTES`). |

### Rodada 3 — Fluxo de Download, Seguranca e Limpeza
| # | Decisao | Resolucao |
|---|---|---|
| Q9 | Mecanismo tecnico do download direto para arquivos grandes (>2 MB) | **Upload via AJAX/API**: backend responde `{ direct_download: true, download_url: ... }`; frontend dispara o download imediato do `.txt` via JavaScript e carrega o preview da imagem fisica via URL `/tools/base64-image/preview/{ulid}`. |
| Q10 | Seguranca de download/preview para usuarios anonimos | **Acesso controlado por ULID**: entropia forte de 128 bits; arquivos anonimos sao servidos se presentes no disco com idade inferior a 24 horas. |
| Q11 | Acao de exclusao manual no historico | **Permitida a exclusao manual imediata**: usuario pode excluir a conversao do historico a qualquer momento antes do prazo automatico. |
| Q12 | Frequencia da rotina de limpeza (`base64-images:prune`) | **Execucao horaria (`hourly`)** registrada na tabela `task_schedules`, garantindo expurgo pontual de arquivos Free e Anonimos com mais de 24 horas. |

### Defaults assumidos (nao contestados)
- Conversao no PHP utilizando deteccao confiavel de MIME (ex.: `finfo` ou `getimagesize`), gerando Data URI compativel com padroes web (`data:image/png;base64,...`).
- A tabela `base64_image_conversions` utilizara ULID como chave primaria de 26 caracteres.
- Formatacao nos modos Texto Puro, Tag `<img>` e CSS `background-image` realizada na interface do frontend a partir do Data URI retornado.
- Textos e rotulos internacionalizados nos 5 idiomas suportados (`en`, `pt-BR`, `pt-PT`, `fr`, `es`).
- Novo registro no glossario e criacao de ADR-055 justificando a adicao de conversao no servidor para manipulacao de midia e retencao diferenciada por plano.

## 3. Relatorio de fatos levantados

### Precedentes de Armazenamento Fisico e Expurgo
- O projeto ja possui um diretorio sob `storage/app/data/` utilizado para imagens de perfil (`storage/app/data/user_profile_image/{uuid}`), com rotas dedicadas e expurgo associado a carencia de exclusao (ADR-035).
- A estrutura exigida para a nova ferramenta segue a mesma raiz: `storage/app/data/base64_images/{user_id}/` e `storage/app/data/base64_images/anonymous/`.
- O agendador de tarefas (`bootstrap/app.php` + tabela `task_schedules`) le rotinas ativas no banco e despacha via `ExecuteCommand` na fila do Supervisor, sem necessidade de tocar no crontab do sistema operacional.

### Relacao com a Regra de Processamento Local (ADR-002)
- O ADR-002 estabelecia o AI SQL Builder como a unica excecao de tool que rodava no servidor.
- A introducao do Base64 Image Encoder com armazenamento seguro no VPS, quotas por plano de assinatura e download direto para cargas pesadas constitui uma nova excecao de dominio justificada pela seguranca de memoria e requisitos de persistencia.
- A criacao do ADR-055 formaliza essa decisao tecnica e seus impactos.
