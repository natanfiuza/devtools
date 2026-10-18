# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**

```text
feat: entrega home com tools, busca, precos e consentimento
```

---

## 🎯 Resumo

A F1 tira a home do estado de esqueleto: ela passa a mostrar as oito ferramentas do MVP com filtro por categoria, a busca Cmd+K, o destaque do AI SQL Builder e a seção de preços. O porquê é comercial — a página precisa explicar o produto e abrir dois caminhos de conversão: cadastro no plano gratuito e contato por e-mail nos planos pagos (venda manual, ADR-027), sem preço nem limite escritos no código, já que tudo sai da tabela `plans`.

Na mesma entrega vão as duas obrigações que não dão para adiar: consentimento de cookies com medição GA4 só depois do aceite (ADR-013, LGPD/GDPR) e o pacote de indexação — sitemap, robots e metas Open Graph com imagem própria.

## 🛠️ Mudanças Técnicas

- `HomeController` substitui o `Route::inertia('/', 'Home')`: monta o catálogo de planos ativos na ordem de exibição, com preço e números formatados no idioma da requisição; novo `Format::bytes` (8192 vira `8 KB`) e desligamento de plano por `active` sem apagar linha.
- Registro de tools em `resources/js/tools/<slug>/manifest.json` como fonte única — lido pelo Vite (`import.meta.glob`, home e busca) e pelo PHP (`App\Support\Tools`, sitemap). Texto visível não mora no manifest: sai do i18n por convenção `tools.<slug>.*`.
- `CommandPalette` + `useCommandPalette`: combobox acessível (`role`, `aria-activedescendant`), atalhos Cmd/Ctrl+K e `/` (ignorado dentro de campos), busca sem acento e sem caixa com ranqueamento por relevância (nome > categoria/palavra-chave > descrição); tool `soon` aparece com selo e não navega. `SearchTrigger` deixa de ser botão morto e ganha variantes header/hero; o rótulo do atalho sai para `lib/platform.ts`.
- `ToolGrid`/`ToolCard`: grid de 3 colunas com chips de categoria montados só para categorias em uso; o card só vira link quando a tool for `available`.
- Consentimento: `App\Support\Consent` (apenas `granted`/`denied` válidos; qualquer outro valor volta a nulo), middleware `HandleConsent` apaga o cookie corrompido, cookie `consent` fora da criptografia de cookies, props compartilhadas `analytics` (`ga4Id`, `consent`, `cookie`, `days`), `useConsent` com a decisão do servidor como verdade a cada carregamento e `ConsentBanner` com detalhes, interruptor de medição e link para a política.
- GA4: `lib/analytics.ts` injeta a tag apenas no cliente e apenas após o aceite, com Consent Mode v2 (`analytics_storage` negado por padrão, publicidade negada nos dois estados); a recusa posterior usa `update` em vez de remover o script; sem id configurado nada é injetado.
- SEO: `SitemapController` + `App\Support\Sitemap` (páginas de conteúdo nos cinco idiomas, cada uma com as irmãs e o `x-default`; tools só quando `available`), `RobotsController` no lugar do arquivo estático, `Seo::openGraph()` consumido pela `app.blade.php`, `PageHead.vue` para `og:title`/`og:description` por página e `styleguide` marcado como `noindex`.
- `seo:og-image` (`GenerateOgImage`): desenha o PNG padrão com GD a partir do DESIGN, baixando as fontes para `storage/app/og-fonts`; o PNG vai comitado e o comando não roda em produção.
- i18n: novos `lang/*/tools.php`, `home.php` reescrito e chaves de busca, consentimento e rodapé nos cinco idiomas; categorias e textos de tool passam a ser traduzidos por slug.
- Testes: `HomeTest`, `ConsentTest`, `SitemapTest`, `RobotsTest`, `SeoMetaTest` e `ToolsRegistryTest` no PHP, mais vitest para palette, banner, home, cabeçalho e registro; fixtures em `tests/Fixtures/tools`.

## ⚠️ Impacto/Avisos

- Novas variáveis de ambiente: `ANALYTICS_GA4_ID` (vazio desliga a medição, inclusive com aceite) e `ANALYTICS_CONSENT_DAYS` (padrão 180).
- `config/legal.php` tem a data de revisão atualizada para 2026-10-14 e o texto de cookies foi reescrito nos cinco idiomas: alteração de conteúdo legal exige nova revisão.
- `public/robots.txt` foi removido do repositório de propósito — arquivo estático vence a rota no nginx e no `artisan serve`; há teste guardando a ausência.
- `public/og-default.png` passa a ser versionado; regenerá-lo exige GD e rede na máquina local (`php artisan seo:og-image`), nunca no VPS.
- O cookie `consent` entra no `encryptCookies(except:)` e trafega em claro, como tema e idioma: quem o grava é o cliente.
- Sem migrações de banco. A home depende de planos ativos na tabela `plans` e de `legal.contact_email` para o CTA pago — sem contato configurado o botão fica desabilitado em vez de virar link quebrado.
- `og:title` e `og:description` só aparecem no HTML com SSR habilitado; sem SSR ficam a cargo do `<Head>` do Inertia no cliente.

close #6

---

[![GitPR](https://img.shields.io/badge/GitPR-0_errors_%C2%B7_34_warnings-yellow)](https://gitpr.natanfiuza.dev.br/)
