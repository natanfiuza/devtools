# ADR-105 - OG image storage
Status: Aceito

## Decisão
Imagens OG estáticas das páginas de tool usam o comando existente `seo:og-image` (`app/Console/Commands/GenerateOgImage.php`) e ficam em `public/og/<slug>.png` (1200x1200), versionadas.

## Consequências
O robô recebe a imagem certa sem depender de geração em produção.

## Emenda 2026-11-07
A decisão anterior (OG de paleta gerada no cliente e salva em `public/og`) foi substituída: a imagem OG **dinâmica** de paleta fica em `storage/app/data/og_paleta_image` e **nunca** é versionada no Git. O nome correto do comando é `seo:og-image`; `colors:generate-og-image` não existe.
