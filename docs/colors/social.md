# Social

## Recursos
- Curtir paleta pública, inclusive anônimo, por identificador anônimo assinado de primeira parte (ADR-112); IP só como sinal antiabuso.
- Comentar paleta pública (threads de no máximo 2 níveis, logados, 255 chars; ADR-111).
- Seguir usuário (login).
- Favoritar paleta.
- Denunciar comentário (login).
- Editar/excluir próprio comentário.

## Moderação
- Filtro de palavras.
- IA DeepSeek (cache em `ai_cache`; "permanente" exige sentinela de `expires_at` na Fase 7).
- Fila.
- Status: pending, approved, rejected, flagged.
- Admin revisa flagged.

## Notificações
- Curtida.
- Comentário.
- Resposta.
- Follow.
- Canais: mail + database, imediato.
- Silenciar por paleta.
