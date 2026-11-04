# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: adiciona fontes embutidas e saida PNG ao BadgeForge
```

---

## 🎯 Resumo

O BadgeForge da Fase 1 só produzia SVG e ignorava a tipografia do usuário. Esta entrega fecha a Fase 2 tornando o badge **portável e personalizável**: o usuário escolhe entre três famílias embutidas (Fira Code, Libre Franklin, Space Grotesk) e baixa o resultado em SVG ou PNG, ambos estáveis e cacheáveis.

O PNG existe porque consumidores como Slack, Discord e e-mails não renderizam SVG de forma confiável — sem ele, o badge não circula fora do navegador. Já a fonte embutida garante que a **métrica medida no servidor** corresponda ao que o cliente desenha, evitando badges com texto cortado ou espaço sobrando.

A escolha de arquitetura foi manter o SVG como formato canônico: o PNG é uma rasterização dele (Imagick) endereçada pelo `sha256` do SVG, então os dois formatos nunca divergem e o cache é naturalmente imutável.

## 🛠️ Mudanças Técnicas

- **Fontes empacotadas (`?font=`)** — `BadgeFont`/`BadgeFontCatalog` leem `config('badgeforge.fonts')`; slug desconhecido cai na pilha do estilo, sem erro (mesmo padrão do catálogo de logos).
- **Tabela de métricas por família** — `font-metrics.json` passa a trazer `families.<slug>` e `family_bold_percent` (Bold/Regular por fonte), consumidos pelo renderer PHP e pelo espelho TypeScript. `sans`/`helvetica` mantêm a média global.
- **Leitor TrueType próprio** — `TrueTypeFont` lê `head`/`hhea`/`hmtx`/`cmap` (formatos 4 e 12) e normaliza o avanço para 1000 unidades/em.
- **Comando `badgeforge:font-metrics`** — regenera o JSON a partir dos TTF; `--check` compara byte a byte para detectar drift em CI.
- **Saída PNG (`StaticBadgeController`)** — `PngRenderer` rasteriza via Imagick com fundo transparente e downscale Lanczos acima de `png.max_width`; sem a extensão responde **503 `image_unavailable`**. O ETag passa a ser o hash do corpo enviado.
- **Cache em disco content-addressed** — `PngCache` grava em `storage/app/data/tools/badgeforge/static` com escrita atômica (tmp + rename), tolerando corrida entre requisições.
- **Poda do cache** — comando `badgeforge:prune` (com `--dry-run`) remove PNGs com mtime acima de `prune_after_hours`; um arquivo removido é regenerado sob demanda.
- **Download** — `?download=1` devolve `Content-Disposition: attachment` com nome ASCII sanitizado e `filename*` UTF-8 (RFC 5987).
- **Frontend** — preview passa a ser SVG **inline** (fonte web aplica dentro de `<img>`? não — por isso a troca), com seletor de fonte, seletor de formato e botão Baixar; snippets anunciam as dimensões reais do PNG após o cap.
- **i18n** — novas chaves `fields.font`, `fields.format`, `fonts.default` e `formats.*` em en, es, fr, pt_BR e pt_PT.
- **Testes** — cobertura de endpoint PNG (bytes, cache, 304, anexo, 503), catálogo de fontes, leitor TTF, poda, métricas por família, fixtures cross-renderer e testes de componente/render no front.
- **Docs/ADR** — ADR-073 e ADR-077, `docs/badgeforge/*`, guia de render, spec, glossário e critérios de aceite da fase.

## ⚠️ Impacto/Avisos

- **Dependência de ambiente:** a saída PNG exige a extensão PHP **`imagick`**. Sem ela o endpoint `.png` responde 503 — o SVG continua funcionando. É preciso garantir Imagick (e fontconfig com as TTF) no deploy.
- **Novas variáveis/chaves de config:** `badgeforge.fonts_path`, `badgeforge.fonts`, `badgeforge.png.max_width`, `badgeforge.png.cache_path` e `badgeforge.png.prune_after_hours` (default 48h).
- **Agendamento necessário:** `badgeforge:prune` precisa entrar no scheduler; sem isso o cache cresce indefinidamente (um arquivo por texto novo).
- **CI:** `badgeforge:font-metrics --check` deve rodar no pipeline — trocar um TTF sem regerar o JSON quebra o contrato entre os dois renderers.
- **Sem migração de banco.** O cache é 100% em disco e descartável.
- **Peso do repositório:** foram adicionados TTF (regulares e bold) em `resources/badgeforge/fonts` e um pacote completo de variantes em `docs/fonts`; o Vite passa a empacotar as seis TTF usadas via `@font-face`.
- **Mudança de comportamento no preview:** o `<img>` com data URI virou `<div>` com SVG inline; consumidores de testes/e2e que dependiam do `src` precisam ser ajustados.

close #124

---

[![GitPR](https://img.shields.io/badge/GitPR-0_errors_%C2%B7_40_warnings-yellow)](https://gitpr.natanfiuza.dev.br/)