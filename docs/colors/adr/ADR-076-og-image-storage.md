# ADR-076 - OG image storage
Status: Aceito

## Decisão
OG image é dinâmica por paleta, gerada no cliente e salva em public/og. Comando artisan GenerateOgImage gera local e envia ao repo.

## Consequências
Reduz carga no servidor; exige versionamento das imagens no repo.
