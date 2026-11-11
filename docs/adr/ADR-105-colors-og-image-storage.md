# ADR-105 - OG image storage
Status: Aceito

## Decisão
Imagens OG estáticas das páginas de tool usam o comando existente `seo:og-image` (`app/Console/Commands/GenerateOgImage.php`) e ficam em `public/og/<slug>.png` (1200x1200), versionadas.

## Consequências
O robô recebe a imagem certa sem depender de geração em produção.

## Emenda 2026-11-07
A decisão anterior (OG de paleta gerada no cliente e salva em `public/og`) foi substituída: a imagem OG **dinâmica** de paleta fica em `storage/app/data/og_paleta_image` e **nunca** é versionada no Git. O nome correto do comando é `seo:og-image`; `colors:generate-og-image` não existe.

## Emenda 2026-11-11
A F4 implementa o ciclo da OG dinâmica (ADR-125): o PNG 1200×630 é desenhado no cliente pelo template `og` dos protótipos, enviado por `POST /dashboard/colors/palettes/{id}/og` (multipart, dono = 404, `throttle:colors-og`) e validado em `UploadPaletteOgRequest` (PNG, `dimensions:width=1200,height=630`, teto de bytes — tudo de `colors.og`/`COLORS_OG_*`). `PaletteOgStorage` grava `{ulid}.png` com tmp + `File::replace` e incrementa `og_version`; mudança visual (nome, descrição, tipo, versão, cores, papéis) apaga o arquivo e anula a versão, e o cliente regenera após o salvamento. `GET /{locale}/colors/p/{slug}/og.png` só serve paleta pública ativa com versão e arquivo (senão 404), com `Cache-Control: public, max-age=colors.og.cache_seconds` e `nosniff`; a meta usa `?v={og_version}`. Falha de geração/upload nunca bloqueia o salvamento.
