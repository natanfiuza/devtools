# Plano de Implementacao: Base64 Image Encoder / Decoder

> **Data:** 2026-10-23  
> **Branch:** `develop_natan`  
> **Identificador:** `base64_image_encoder_decoder`  
> **Status:** Pronto para aprovacao e execucao  
> **Referencia de alinhamento:** `docs/survey/20261023_base64_image_encoder_decoder_surveyfacts.md`

---

## 1. Visao Geral e Motivacao

Criacao da ferramenta **Base64 Image Encoder / Decoder** (`base64-image`), pertencente a categoria `media` no catalogo de ferramentas do `devtools.natanfiuza.dev.br`.

A ferramenta combina:
1. **Encoder no Servidor (PHP)**: Recebe uploads de imagem, valida MIME e tamanho conforme o plano do usuario (2 MB para Free/Anonimo e 25 MB para Planos Pagos), armazena a imagem original e o texto Data URI em disco com identificador ULID (`storage/app/data/base64_images/{user_id|anonymous}/`) e, para arquivos cujo Base64 ultrapasse o limiar de 2 MB no plano pago, dispara download direto do arquivo `.txt` contendo a Data URI pura para evitar travamento do navegador.
2. **Decoder no Cliente (Navegador)**: Processamento 100% local no browser que recebe Base64 ou Data URI, extrai dimensoes e formato, renderiza o preview imediato e permite download da imagem convertida via Blob nativo, sem onerar o servidor.
3. **Historico com Retencao Diferenciada**:
   - **Anonimos**: Registros gravados no `localStorage` do cliente; arquivos fisicos no servidor em `storage/app/data/base64_images/anonymous/` expiram e sao apagados em 24 horas.
   - **Usuarios Free (cadastrados)**: Registro persistido para sempre na tabela `base64_image_conversions`; arquivos fisicos no disco sao apagados apos 24 horas.
   - **Usuarios Pagos (Pro/Anual)**: Registro persistido para sempre na tabela `base64_image_conversions`; arquivos fisicos disponiveis para download por ate 30 dias.
4. **Agendador de Limpeza (`base64-images:prune`)**: Execucao horaria (`hourly`) registrada na tabela `task_schedules`, removendo arquivos expirados do disco pontualmente.

---

## 2. Alteracoes de Banco de Dados

### 2.1. Migracao: Adicionar `max_image_upload_bytes` na tabela `plans`
- **Arquivo:** `database/migrations/2026_10_23_000001_add_max_image_upload_bytes_to_plans_table.php`
- Adiciona coluna: `unsignedBigInteger('max_image_upload_bytes')->default(2097152)->after('max_prompt_chars');`
- Atualiza catalogo existente:
  - Plano `free`: `2097152` (2 MB).
  - Planos pagos (`pro-monthly`, `pro-yearly`): `26214400` (25 MB).
- Rollback: remove a coluna `max_image_upload_bytes`.

### 2.2. Migracao: Criar tabela `base64_image_conversions`
- **Arquivo:** `database/migrations/2026_10_23_000002_create_base64_image_conversions_table.php`
- Campos:
  - `string('id', 26)->primary()` (ULID)
  - `foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete()`
  - `string('filename', 255)` (nome original do arquivo)
  - `string('mime_type', 64)` (ex.: `image/png`, `image/webp`)
  - `unsignedBigInteger('file_size_bytes')` (tamanho do arquivo original)
  - `unsignedBigInteger('base64_size_bytes')` (tamanho em bytes do texto gerado)
  - `string('disk_path_original', 255)->nullable()` (caminho relativo do arquivo de imagem)
  - `string('disk_path_converted', 255)->nullable()` (caminho relativo do `.txt` gerado)
  - `timestamp('file_deleted_at')->nullable()->index()` (data em que os arquivos fisicos foram apagados)
  - `timestamps()`
- Indices: `['user_id', 'created_at']`, `['file_deleted_at', 'created_at']`.

### 2.3. Migracao: Registrar comando de limpeza na tabela `task_schedules`
- **Arquivo:** `database/migrations/2026_10_23_000003_seed_base64_image_maintenance_schedule.php`
- Registra `base64-images:prune` com expressao `0 * * * *` (a cada hora).

---

## 3. Backend (PHP / Laravel)

### 3.1. Configuracao e Modelos
- **`config/tools.php`**:
  - Acrescentar limites e retencoes para `base64_image`:
    - `free_max_bytes`: env `TOOLS_BASE64_IMAGE_FREE_MAX_BYTES` (2 MB)
    - `paid_max_bytes`: env `TOOLS_BASE64_IMAGE_PAID_MAX_BYTES` (25 MB)
    - `download_threshold_bytes`: env `TOOLS_BASE64_IMAGE_DOWNLOAD_THRESHOLD_BYTES` (2 MB)
    - `free_retention_hours`: 24
    - `paid_retention_days`: 30
- **`app/Models/Plan.php`**:
  - Adicionar `max_image_upload_bytes` no `#[Fillable]` e no metodo `casts()`.
- **`app/Models/Base64ImageConversion.php`**:
  - Model com cast de atributos inteiros e datas.
  - Relacao `user()`.
  - Scopes: `scopeForUser($query, $userId)`, `scopeExpiredFree($query)`, `scopeExpiredPaid($query)`.
  - Metodos: `isExpired(): bool`, `deletePhysicalFiles(): void`.

### 3.2. Suporte e Props da Tool
- **`app/Support/Base64Image/Base64ImageToolProps.php`**:
  - Monta os limites para a pagina Inertia com base no usuario autenticado:
    - `maxUploadBytes`: cota do plano efetivo (2 MB ou 25 MB).
    - `directDownloadThresholdBytes`: 2 MB.
    - `isPaidPlan`: bool indicando se e plano com assinatura ativa.
    - `isAuthenticated`: bool.
    - `retentionHours`: 24 para Free, 720 (30 dias) para Pago.
- **`app/Support/ToolProps.php`**:
  - Mapear `'base64-image' => (object) Base64ImageToolProps::for($user)`.

### 3.3. Servicos e Endpoints de API
- **`app/Http/Controllers/Api/Base64ImageController.php`**:
  - `POST /api/tools/base64-image/convert`:
    - Valida arquivo: `file`, MIME de imagem permitido (`jpeg,png,jpg,gif,svg,webp,bmp,ico,tiff,avif`), tamanho maximo segundo plano do usuario.
    - Gera ULID.
    - Define diretorio:
      - Autenticado: `storage/app/data/base64_images/{user_id}/`
      - Anonimo: `storage/app/data/base64_images/anonymous/`
    - Converte bytes em Base64 Data URI: `data:{mime};base64,{base64_string}`.
    - Salva `{ulid}.original.{ext}` e `{ulid}.txt` (contendo o Data URI puro).
    - Se usuario autenticado: cria registro em `base64_image_conversions`.
    - Resposta HTTP:
      - Se `base64_size_bytes > 2 MB` e plano pago: responde com `{ direct_download: true, download_url: "..." }`.
      - Se `<= 2 MB`: responde com `{ direct_download: false, data_uri: "...", ... }`.
  - `GET /api/tools/base64-image/history`:
    - Retorna lista das conversoes do usuario autenticado ordenadas por `created_at desc`.
  - `DELETE /api/tools/base64-image/history/{id}`:
    - Exclusao manual da conversao e remocao fisica dos arquivos.
- **`app/Http/Controllers/Base64ImageDownloadController.php`**:
  - `GET /tools/base64-image/download/{id}`:
    - Serve o arquivo `.txt` como anexo (`Content-Disposition: attachment; filename="{filename}.base64.txt"`).
    - Valida posse para usuarios autenticados ou existencia valida (<24h) para anonimos.
  - `GET /tools/base64-image/preview/{id}`:
    - Serve a imagem fisica original para prévia visual na tag `<img>`.
- **`app/Console/Commands/PruneBase64Images.php`**:
  - `base64-images:prune`:
    - Localiza conversoes Free com mais de 24h sem `file_deleted_at`: apaga arquivos fisicos e atualiza `file_deleted_at = now()`.
    - Localiza conversoes de Planos Pagos com mais de 30 dias sem `file_deleted_at`: apaga arquivos fisicos e atualiza `file_deleted_at = now()`.
    - Varre diretorio `storage/app/data/base64_images/anonymous/` e remove arquivos com `mtime` superior a 24 horas.

---

## 4. Frontend (Vue 3, TypeScript, Tailwind 4, shadcn-vue)

### 4.1. Registro da Tool
- **`resources/js/tools/base64-image/manifest.json`**:
  - `slug`: `base64-image`
  - `status`: `available`
  - `category`: `media`
  - `icon`: `Image` (Lucide)
  - `order`: 9
  - `keywords`: `["base64", "image", "data-uri", "converter", "encoder", "decoder", "css", "img"]`
- **`resources/js/tools/categories.ts`**:
  - Garantir que a categoria `media` esteja registrada com icone e titulo traduzido.
- **`resources/js/tools/components.ts`**:
  - Mapear carregamento lazy do componente.

### 4.2. Componentes da Ferramenta
- **`resources/js/tools/base64-image/Tool.vue`**:
  - Abas principais: `[ Codificador (Encoder) | Decodificador (Decoder) | Historico ]`.
  - Integra dados vindos de `toolProps` (limites, plano, autenticacao).
- **`resources/js/tools/base64-image/EncoderPanel.vue`**:
  - Area de arrastar-e-soltar / upload de arquivo de imagem com `FileDropzone`.
  - Exibicao de limite maximo dinamico (2 MB Free / 25 MB Pro).
  - Apos conversao:
    - Previa da imagem.
    - Seletor de formato de saida:
      - **Texto Puro / Data URI**: `data:image/png;base64,...`
      - **Tag HTML `<img>`**: `<img src="data:image/png;base64,..." alt="..." />`
      - **CSS**: `background-image: url('data:image/png;base64,...');`
    - Copiar snippet com feedback.
    - Botao para baixar arquivo `.txt` contendo o Data URI.
    - Caso `direct_download = true`: aviso de arquivo grande com download automatico disparado e previa carregada via URL de preview.
- **`resources/js/tools/base64-image/DecoderPanel.vue`**:
  - Area de entrada de texto para colar Data URI ou Base64 puro.
  - Validacao local com extracao de MIME type, largura e altura da imagem.
  - Previa instantanea da imagem decodificada.
  - Botao de download local da imagem decodificada (como arquivo `.png`, `.jpg`, etc.) via `URL.createObjectURL(blob)`.
- **`resources/js/tools/base64-image/HistoryPanel.vue`**:
  - Lista de conversoes recentes.
  - Se autenticado: consome API `/api/tools/base64-image/history`.
  - Se anonimo: consome `localStorage` via helper `lib/storage.ts`.
  - Exibe status: `Ativo (restam X horas/dias)` ou `Arquivo expirado`.
  - Acoes: Copiar Data URI (se disponivel), Baixar `.txt`, Baixar Imagem Original (desabilitados se expirado), Excluir do historico.
- **`resources/js/tools/base64-image/lib/storage.ts`**:
  - Gerenciador de historico local para anonimos com expurgo automatico de itens com mais de 24 horas.

---

## 5. Internacionalizacao (i18n)

Adicionar chaves completas e acentuadas nos 5 idiomas em `lang/{locale}/tools.php`:
- `tools.base64-image.name`
- `tools.base64-image.description`
- `tools.base64-image.keywords`
- `tools.base64-image.tabs.encoder`
- `tools.base64-image.tabs.decoder`
- `tools.base64-image.tabs.history`
- `tools.base64-image.limits.*`
- `tools.base64-image.formats.*`
- `tools.base64-image.messages.*`

---

## 6. Documentacao do Repositorio

- **`docs/adr/ADR-055-ferramenta-base64-image-processamento-servidor-e-retencao.md`**:
  - Formaliza a decisao de processamento no servidor para manipulacao de imagens de grande porte e politicas de armazenamento/retencao diferenciadas por plano.
- **`docs/glossario.md`**:
  - Inclusao dos novos termos: `Conversao Base64 de Imagem`, `Data URI de Imagem`, `Retencao de Imagem Base64`, `Download Direto de Base64`.
- **`docs/SPEC.md`**:
  - Atualizacao da secao 5 de tools para incluir a especificacao da ferramenta `base64-image`.

---

## 7. Estrategia de Testes

1. **Backend (Pest 4)**:
   - `tests/Feature/Tools/Base64ImageConvertTest.php`:
     - Upload valido no plano Free <= 2 MB.
     - Bloqueio de upload no plano Free > 2 MB (retorna 422).
     - Upload valido no plano Pago ate 25 MB.
     - Ativacao de `direct_download: true` quando > 2 MB no plano pago.
     - Gravacao de arquivos no disco sob estrutura correta (`storage/app/data/base64_images/{user_id|anonymous}/`).
     - Criacao de registro no banco para cadastrado e ausencia de registro no banco para anonimo.
   - `tests/Feature/Tools/Base64ImageHistoryTest.php`:
     - Listagem de historico do usuario autenticado com isolamento de contas.
     - Exclusao manual de conversao e remocao de arquivos.
   - `tests/Feature/Tools/Base64ImageDownloadTest.php`:
     - Download de `.txt` por usuario dono e bloqueio para outros usuarios.
     - Download anonimo valido em menos de 24h e 404/410 se expirado.
   - `tests/Feature/Tools/Base64ImagePruneTest.php`:
     - Execucao de `base64-images:prune` removendo arquivos fisicos Free (>24h) e Pago (>30 dias).
     - Verificacao de que o registro no banco permanece com `file_deleted_at` preenchido.
     - Remocao de arquivos anonimos no disco com mais de 24h.
2. **Frontend (Vitest 5)**:
   - Testes unitarios do decodificador cliente e criacao de Blobs.
   - Testes do `localStorage` para historico de anonimos.
3. **Checagem de Qualidade**:
   - `composer test` (Pint, PHPStan, Pest).
   - `npm run test` (Vitest).
   - `npm run types:check` (vue-tsc).
