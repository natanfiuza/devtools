# OG Image

## Duas estratégias distintas

### OG estática de página de tool

- Gerada pelo comando existente `php artisan seo:og-image`.
- Salva em `public/og/<slug>.png` (1200x1200), versionada no Git.
- Regenerável executando o comando novamente.
- Não existe `colors:generate-og-image`.

### OG dinâmica de paleta pública (Fase 4, implementada em 2026-11-11)

- Gerada no cliente pelo template `og` dos protótipos vetoriais (papéis do tema **claro**), rasterizada em 1x e enviada após criar ou salvar uma paleta pública (ADR-125, ADR-126).
- Enviada ao backend e salva em `storage/app/data/og_paleta_image/{ulid}.png`; nunca versionada no Git.
- URL pública controlada apenas para paletas públicas ativas; privada ou arquivada responde 404.
- Regenerável manualmente pela paleta (botão “Gerar imagem” no editor; falha nunca bloqueia o salvamento).
- Imagens obsoletas são apagadas quando a paleta muda visualmente e a URL leva `?v={og_version}` para não servir cache antigo.

## Upload e validação

- `POST /dashboard/colors/palettes/{id}/og`, multipart, dono apenas (404 para outra conta), `throttle:colors-og` (10/min).
- Validado em `UploadPaletteOgRequest`: PNG de verdade (assinatura + `getimagesize`), **1200×630 exatos** e teto de bytes — todos os valores de `colors.og` (`COLORS_OG_*`): `width` 1200, `height` 630, `max_bytes` 2 MiB.
- `DELETE` na mesma rota apaga o arquivo e anula a versão.
- Gravação atômica: arquivo temporário + `File::replace`; o `og_version` é incrementado na mesma operação.

## Serving

- `GET /{locale}/colors/p/{slug}/og.png` serve só paleta pública ativa com `og_version` e arquivo existente; caso contrário 404.
- `Cache-Control: public, max-age=colors.og.cache_seconds` (padrão 86400) e `X-Content-Type-Options: nosniff`.
- A meta OG da página só aponta para a imagem quando ela existe; sem ela, o site usa a OG padrão.

## Dimensões

- OG estática: 1200x1200 (padrão do `seo:og-image`).
- OG dinâmica de paleta: 1200x630.

## Formato

PNG.
