# ADR-064: Badges pessoais
Status: Aceito

## Decisão
Apenas pagos criam badges pessoais. Todas públicas. URL /b/{username}/{ulid}.{svg|png}. Sem limite de quantidade.

## Consequências
URL previsível; sem privacidade por padrão.

## Emenda da F4 (2026-11-03)

O contorno virou codigo na ADR-081: tabela `badge_assets` com `user_id`
obrigatorio (nao existe badge anonima) e sem `slug` (a URL e username + ULID),
storage em `storage/app/data/tools/badgeforge/personal/{username}/{ulid}.{svg|png}`,
PAT cifrado por badge, refresh por `POST` autenticado e downgrade que serve e
permite copiar/excluir, mas bloqueia criar/renomear/trocar PAT/refresh. A
documentacao publica da badge pessoal e a pagina `/{locale}/docs/badgeforge`
(ADR-082).
