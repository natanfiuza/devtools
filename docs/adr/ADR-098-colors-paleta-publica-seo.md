# ADR-098 - Paleta pública com SEO
Status: Aceito

## Decisão
Paletas públicas terão página /colors/p/{slug} com SEO, OG image e possibilidade de duplicar.

## Consequências
Aumenta descoberta; exige slug único e canonical.

## Emenda 2026-11-11
A rota é `/{locale}/colors/p/{slug}` com slug global imutável ao renomear; privada, arquivada e slug inexistente respondem o mesmo 404. Canonical e hreflang saem **sem query string** (para `?duplicate=1` não contaminar a indexação) e o sitemap inclui só paletas públicas ativas, com teto configurável (ADR-124). A página não mostra autor, curtidas, comentários, seguidores nem protótipo — social é F6/F7; "testar nova base" recalcula só em memória e a duplicação autenticada copia o que está na tela respeitando a cota, com "Uso" incrementando apenas quando quem duplica não é o dono (ADR-124). A OG própria é anunciada só quando `og_version` e arquivo existem; senão vale a OG padrão (ADR-125).
