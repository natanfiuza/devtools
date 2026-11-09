# OG Image

## Duas estratégias distintas

### OG estática de página de tool
- Gerada pelo comando existente `php artisan seo:og-image`.
- Salva em `public/og/<slug>.png` (1200x1200), versionada no Git.
- Regenerável executando o comando novamente.
- Não existe `colors:generate-og-image`.

### OG dinâmica de paleta pública
- Gerada no cliente a partir da paleta.
- Enviada ao backend e salva em `storage/app/data/og_paleta_image`.
- Nunca versionada no Git.
- URL pública controlada apenas para paletas públicas.
- Regenerável manualmente pela paleta.
- Imagens obsoletas são atualizadas ou removidas.

## Dimensões
- OG estática: 1200x1200 (padrão do `seo:og-image`).
- OG dinâmica de paleta: 1200x630.

## Formato
PNG.
