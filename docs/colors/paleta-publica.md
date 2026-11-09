# Paleta pública

## URL
/{locale}/colors/p/{slug}

## Recursos
- Visualização.
- Duplicar.
- Compartilhar.
- SEO.
- OG image dinâmica, gerada no cliente e salva em `storage/app/data/og_paleta_image` (nunca versionada no Git).
- Tags.
- Autor.
- Curtir, inclusive anônimo, por identificador anônimo assinado de primeira parte (ADR-112).
- Comentar (threads de no máximo 2 níveis, logados; ADR-111).
- Seguir autor.
- Denunciar comentário.

## Regras
- Apenas paletas públicas.
- Slug único.
- Canonical.
- OG image enviada como imagem.
