# ADR-055-ferramenta-base64-image-processamento-servidor-e-retencao
Status: Aceito
Data: 2026-10-23

## Contexto
O ADR-002 estabelecia que as ferramentas deterministicas rodavam no navegador e que apenas o AI SQL Builder utilizava o servidor.
Com a necessidade da nova ferramenta Base64 Image Encoder / Decoder, o processamento de imagens de formatos variados e tamanhos de ate 25 MB (no plano pago) impoe limitacoes tecnicas severas ao navegador do cliente:
1. Strings Base64 de dezenas de megabytes congelam o DOM e a arvore de componentes do Vue caso renderizadas em elementos de texto puro.
2. Politicas de plano (2 MB para Free/Anonimo e 25 MB para Planos Pagos) exigem validacao e controle de cotas no backend.
3. Exigencia de retencao e historico com arquivos fisicos mantidos por ate 24 horas no plano gratuito/anonimo e por ate 30 dias nos planos pagos.

## Decisao
1. **Processamento Hibrido (Encoder no Servidor, Decoder no Cliente)**:
   - O Encoder envia a imagem para o backend via `POST /api/tools/base64-image/convert`, onde o PHP valida o MIME, calcula o tamanho, grava os arquivos fisicos sob identificador ULID (`storage/app/data/base64_images/{user_id|anonymous}/`) e retorna a Data URI ou URL de download.
   - O Decoder roda 100% no navegador: recebe a Data URI ou Base64, analisa dimensoes/MIME e renderiza a previa e download nativo via Blob no cliente sem onerar disco do servidor.
2. **Download Direto para Cargas Pesadas**:
   - Conversao no plano pago resultante em tamanho superior a 2 MB aciona automaticamente o download direto do arquivo `.txt` contendo a Data URI, enquanto a visualizacao da imagem e servida por rota estatica leve (`preview/{ulid}`), protegendo a memoria do navegador.
3. **Persistencia e Expurgo**:
   - Usuarios anonimos guardam metadados no `localStorage` do navegador; arquivos fisicos em `storage/app/data/base64_images/anonymous/` sao expurgados apos 24 horas pelo comando `base64-images:prune`.
   - Usuarios autenticados persistem seus metadados para sempre na tabela `base64_image_conversions`. Arquivos fisicos sao removidos apos 24 horas (Free) ou 30 dias (Pagos), marcando `file_deleted_at = now()`.
   - A rotina `base64-images:prune` roda a cada hora via `TaskSchedule`.
4. **Limites em Banco**:
   - A coluna `max_image_upload_bytes` na tabela `plans` permite ajuste dinamico de limites por plano, com fallback em `config/tools.php`.

## Consequencias
- Cria-se uma nova excecao oficial ao ADR-002 para conversoes pesadas de midia.
- A ferramenta Base64 Image ganha suporte a historico persistente e seguranca contra congelamento de interface.
