# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: resolve metas de SEO no servidor e usa card OG quadrado
```

---

## 🎯 Resumo

Os cards de compartilhamento não apareciam corretamente em prévias de redes sociais, principalmente WhatsApp: o preview é exibido em área quadrada e cortava as imagens horizontais (1200x630), deixando o texto fora do enquadramento. Além disso, título e descrição das páginas eram escritos pelo `<Head>` do Inertia, ou seja, dependiam de JavaScript/SSR para existir no HTML — robôs de preview não executam JS e o SSR pode estar fora do ar, então as metas simplesmente não chegavam ao crawler.

Esta mudança torna o card quadrado e move a resolução das metas para o servidor, garantindo que `title`, `description`, `og:title` e `og:description` sempre estejam no HTML entregue, com o idioma correto da página.

## 🛠️ Mudanças Técnicas

- `GenerateOgImage`: canvas passa a 1200x1200 e o conteúdo é posicionado na faixa central (`CROP_BAND_HEIGHT = 628`) preservada pelo corte 1.91:1 de outras redes; baseline principal e espaçamento extraídos para constantes (`MAIN_BASELINE_OFFSET`, `LINES_OFFSET`) e centralizados em `mainBaseline()`.
- PNGs de `public/og/*` e `public/og-default.png` regenerados no novo formato quadrado.
- `config/seo.php`: `image.height` muda para 1200 e é adicionada `image.version` (atual `2`), usada como cache-busting na URL do `og:image`.
- `App\Support\Seo`: novo `pageMeta()` (fonte única de título/descrição por rota, com fallback do site e retorno vazio em páginas `noindex`), `documentTitle()`, `toolMeta()`, `legalIntro()` (interpola `{app}` como o vue-i18n) e `imageUrl()` (anexa `?v=`).
- `HandleInertiaRequests`: compartilha `seo.meta` com as props Inertia para a navegação client-side.
- `app.blade.php`: escreve `<title>`, `meta description`, `og:title` e `og:description` a partir de `Seo::pageMeta()`.
- `PageHead.vue`: simplificado — apenas aplica o título vindo das shared props; nenhuma meta é escrita no cliente (evita duplicação com o Blade no SSR).
- `Home.vue`, `Privacy.vue`, `Terms.vue`, `tools/Show.vue`: deixam de passar `title`/`description` por prop e de montar descrições com `useI18n`.
- Tipagem `global.d.ts` atualizada com `seo.meta`.
- Testes: `SeoMetaTest` cobre metas no HTML do servidor em todos os idiomas, ausência de `{app}` cru, card quadrado, versão de cache e shared props; testes de Vue ajustados para o novo contrato do `PageHead`.

## ⚠️ Impacto/Avisos

- **Cache de redes sociais:** o arquivo em disco é o mesmo caminho; a atualização depende do `seo.image.version`. Ao redesenhar os cards, incremente a versão e rode o comando de geração das imagens, senão as redes continuam servindo o PNG antigo.
- **Regeneração obrigatória:** os PNGs versionados precisam ser regerados (`GenerateOgImage`) — o diff já inclui os binários, mas novas tools exigem rodar o comando.
- **Config:** nova chave `seo.image.version` e alteração de `seo.image.height`. Sem migrations, sem novas variáveis de ambiente e sem mudanças de dependências.
- **SSR deixa de ser requisito** para SEO/preview: as metas agora vêm do Blade. O `<Head>` do Inertia atua apenas na navegação do cliente.
- Páginas `noindex` (login, tokens) não recebem `description`/`og:*` e mantêm o título do site.

".gitpr.commit.md": "gitpr.commit.md"


close #24
---

[![GitPR](https://img.shields.io/badge/GitPR-0_errors_%C2%B7_7_warnings-yellow)](https://gitpr.natanfiuza.dev.br/)