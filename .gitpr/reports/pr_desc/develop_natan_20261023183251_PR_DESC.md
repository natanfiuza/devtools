# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: adiciona ferramenta de conversão de imagem Base64
```

---

## 🎯 Resumo

Entrega a nova ferramenta **Base64 Image Encoder / Decoder**, que converte imagens em Data URI Base64 (texto puro, tag `<img>` e CSS) e decodifica Base64 de volta para imagem, com pré-visualização e download. A funcionalidade é monetizável: o plano Free fica limitado a 2 MB e 24 h de retenção, enquanto planos pagos chegam a 25 MB e 30 dias, com download direto automático para arquivos grandes e evitando sobrecarga de memória do navegador. O histórico é persistido para usuários autenticados e mantido em `localStorage` para anônimos, e o descarte de arquivos físicos é feito por rotina agendada, mantendo apenas o registro histórico no banco.

## 🛠️ Mudanças Técnicas

- **Backend/API**: novo `Base64ImageController` (convert, history, destroy) e `Base64ImageDownloadController` (download/preview) com autorização por dono, checagem de expiração e respostas 403/404/410.
- **Modelo**: `Base64ImageConversion` com ULIDs, `deletePhysicalFiles()`, `isExpired()` e scope `forUser`.
- **Manutenção**: comando `base64-images:prune` (com `--dry-run`) que remove arquivos Free > 24 h, Pagos > 30 dias e anônimos > 24 h; agendamento horário registrado via migration em `task_schedules`.
- **Planos**: novo campo `max_image_upload_bytes` no model `Plan`, factory e migration com backfill (Free 2 MB, pro 25 MB).
- **Configuração**: bloco `tools.limits.base64_image` em `config/tools.php` (limites, limiar de download direto e retenções).
- **Props/Registro**: `Base64ImageToolProps` integrado ao `ToolProps` e entrada no registry (`manifest.json`).
- **Frontend**: componentes `Tool`, `EncoderPanel`, `DecoderPanel` e `HistoryPanel`, libs `decoder.ts` e `storage.ts`, tipos e ícone `image`.
- **i18n**: traduções da ferramenta em `pt_BR`, `pt_PT`, `en`, `es` e `fr`, além de imagem OG dedicada.
- **Testes**: feature tests de conversão, histórico, download/preview, exclusão e poda, mais testes unitários de decoder e storage no frontend.

## ⚠️ Impacto/Avisos

- **Banco de dados**: três migrations novas — adiciona coluna `plans.max_image_upload_bytes` (com backfill), cria a tabela `base64_image_conversions` e insere registro em `task_schedules`. Requer `migrate` no deploy; o `down()` de cada uma é reversível.
- **Agendamento**: a poda depende de `schedule:run` ativo e do registro em `task_schedules`; sem isso os arquivos físicos não são removidos e o disco cresce indefinidamente.
- **Variáveis de ambiente**: novas opcionais `TOOLS_BASE64_IMAGE_FREE_MAX_BYTES`, `TOOLS_BASE64_IMAGE_PAID_MAX_BYTES`, `TOOLS_BASE64_IMAGE_DOWNLOAD_THRESHOLD_BYTES`, `TOOLS_BASE64_IMAGE_FREE_RETENTION_HOURS` e `TOOLS_BASE64_IMAGE_PAID_RETENTION_DAYS` (todas com default seguro).
- **Storage**: passa a gravar arquivos em `storage/app/data/base64_images/{user_id|anonymous}`; é necessário garantir volume persistente e espaço disponível em produção.
- **Segurança/Privacidade**: arquivos anônimos são acessíveis apenas por ULID e expiram em 24 h; downloads autenticados exigem ser o dono do registro. Recomenda-se validar política de retenção de dados sensíveis.
- **Dependências**: nenhuma nova dependência de runtime; os OG images existentes foram regerados (apenas assets).


close #74

---

[![GitPR](https://img.shields.io/badge/GitPR-0_errors_%C2%B7_21_warnings-yellow)](https://gitpr.natanfiuza.dev.br/)