# ADR-114 - Denúncia de comentário
Status: Aceito

## Decisão
Usuários logados podem denunciar comentários. Admin revisa.

## Consequências
Tabela reports; status pending/resolved/dismissed.

## Emenda 2026-11-15

Implementado na F7 (ADR-141): `color_comment_reports` com unique
`(comment_id, user_id)` e motivo em enum fixo (`spam`, `harassment`, `hate`,
`sexual`, `other`), sem texto livre. Repetir `pending`/`resolved` responde 422
sem linha nova; `dismissed` reabre a mesma linha. Denunciar não esconde nem
altera o comentário. Rejeitar ou excluir o comentário resolve as pendentes
automaticamente, sem `resolved_by`; a decisão do admin audita
`comment_report.resolved`/`dismissed` sem o texto. Sem notificação ao
denunciante nesta fase.
