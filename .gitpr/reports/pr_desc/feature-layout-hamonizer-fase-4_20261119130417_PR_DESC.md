# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: adiciona persistência e ciclo de vida do Layout Harmonizer (F4)
```

---

## 🎯 Resumo

Esta entrega implementa a Fase 4 do Layout Harmonizer: o gerador deixa de ser um protótipo em memória e passa a ser um recurso persistente, versionado e publicável. Usuários autenticados podem salvar, atualizar, duplicar, arquivar/restaurar e excluir sistemas de layout, além de compartilhá-los via slug público somente leitura. O snapshot é congelado no ato do salvamento (sem regeneração silenciosa do motor), o crédito da paleta de origem é preservado e a cota passa a ser compartilhada com as paletas, garantindo previsibilidade de uso e continuidade para rascunhos anônimos e snapshots de versões anteriores.

## 🛠️ Mudanças Técnicas

- **Backend — domínio:** model `LayoutSystem` (ULID, `state` JSON com snapshot/versões, `slug`/`archived_slug`, crédito congelado, contadores de tokens e duplicações) com scopes `forUser`/`active`/`archived`/`publiclyVisible` e relações com `User` e `ColorTag`.
- **Migrations:** `layout_systems`, `layout_system_retired_slugs` e pivô `color_tag_layout_system`.
- **Actions transacionais:** `Create`/`Update`/`Archive`/`Restore`/`Delete`/`DuplicateLayoutSystem`, com lock da linha do usuário, checagem de cota, retry em colisão de slug (até 3 tentativas), reserva/aposentadoria de slug e preservação do crédito original.
- **Suporte dedicado:** `LayoutSystemCatalog` (33 tokens, 16 pares, estratégias), `LayoutSystemState` (validação estrutural do snapshot e derivação de versões/`tokens_count`), `LayoutSystemSlug` (`base`/`unique`/`claimOrGenerate`/`taken`/`retire`), `LayoutSystemCredit`, `LayoutSystemData`, `LayoutSystemPresenter` (shapes `row`/`editor`/`public` sem expor FK da origem), `LayoutSystemToolProps` e enum `LayoutStrategy`.
- **HTTP:** controllers para dashboard CRUD, duplicação, intenções pós-login e página pública; FormRequests com autorização 404, confirmação por nome na exclusão e normalização de campos vazios; filtros, ordenação e paginação server-side.
- **Cota e configuração:** cota conjunta paletas+sistemas (`PaletteQuota`), generalização de `PaletteTags` para os dois tipos, `config/colors.php` (limites, versões, teto do state) e factory com estado v1 válido; SEO e Sitemap integrados à nova rota pública.
- **Frontend — workspace:** passa a consumir um `LayoutController` injetado (`live` ou `frozen`), hidratando o snapshot gravado sem regenerar e distinguindo os modos `live`/`compatible`/`readonly`.
- **LayoutHarmonizerPanel:** guarda de alterações não salvas (bloqueio de navegação Inertia e `beforeunload`, liberação no salvamento), rascunho local para anônimos, verificação de cota, regeneração explícita e modal de salvamento (criar, atualizar e "salvar como novo") com metadados, visibilidade, tags e origem opcional da paleta.
- **Páginas:** Meus Sistemas de Layout (filtros server-side com debounce, cota conjunta, estados vazios, duplicar/arquivar/restaurar/excluir com confirmação pelo nome exato), editor de sistema (metadados, quadro read-only quando arquivado ou versão desconhecida, PATCH pelo modal, exclusão e restauração) e página pública somente leitura (snapshot congelado, crédito de origem, cópia de HEX e duplicação com intent de login via `?duplicate=1`).
- **Componentes:** `LayoutSystemCard`, `LayoutSystemActions`, `DeleteLayoutSystemDialog`, `LayoutSystemMetaForm`, `ImportDraftDialog`, `LayoutSystemOriginBanner`, `LayoutSystemPrototypePanel`, além do CTA "Criar sistema de layout" nas paletas própria e pública e entrada de navegação da conta.
- **Composables e lib:** `useFrozenLayout`, `useLayoutDraft`, `useLayoutSystem` (aceita `initial` e `seedText`, com latch `skipNext`) e `useUnsavedGuard`; módulos `controller.ts`, `draft.ts`, `frozen.ts`, `inputs.ts`, `layoutRoleMap.ts`, `regenerate.ts`, `save.ts`, `state.ts` e `types/layoutSystem.ts`.
- **Protótipo:** movido de `tools/palette-generator/prototype` para `lib/prototype`, com texto de exemplo traduzido em `copy.ts` e reaproveitamento do `PrototypePreview`.
- **Qualidade:** traduções em en/es/fr/pt_BR/pt_PT, testes JS (draft, frozen, hydration, prototype, save/guarda, editor, listagem, página pública), testes PHP de persistência/ações, HTTP da conta e público, cota (ADR-170), SEO, paridade de catálogo PHP↔TS, slug e validação do estado; remoção do binário `docs/assets/img/composer_ico`.

## ⚠️ Impacto/Avisos

- **Banco de dados:** três novas migrations (`layout_systems`, `layout_system_retired_slugs`, `color_tag_layout_system`) — requer `php artisan migrate`.
- **Cota compartilhada:** o limite agora é conjunto entre paletas e sistemas; ajustes em `config/colors.php` afetam ambos os recursos e podem bloquear criações já em uso.
- **Rotas novas:** recurso autenticado na área da conta e página pública em `/colors/s/{slug}` (somente leitura), com metas/OG, hreflang e sitemap — revisar cache e indexação.
- **Contrato de estado:** envelope v1 versionado entre cliente e servidor (`live`/`compatible`/`readonly`) e chaves de `localStorage` versionadas; snapshots antigos abrem em modo compatível/read-only.
- **Concorrência:** actions dependem de lock da linha do usuário e podem gerar retry de slug, aposentando slugs em `retired_slugs`.
- **Frontend:** requer rebuild dos assets (`npm run build`); a remoção de `docs/assets/img/composer_ico` pode quebrar referências antigas em documentação.

close #170

---

[![GitPR](https://img.shields.io/badge/GitPR-3_errors_%C2%B7_150_warnings-red)](https://gitpr.natanfiuza.dev.br/)