# ADR-125 - OG dinâmica de paleta
Status: Aceito
Data: 2026-11-11
Relacionados: ADR-105 (OG image storage, emenda), ADR-098 (paleta pública, emenda), ADR-124 (página pública e SEO), ADR-126 (protótipos e renderer), ADR-002 (processamento local)

## Contexto
A ADR-105 (emenda de 2026-11-07) já fixou que a OG de paleta é **dinâmica**, armazenada em `storage/app/data/og_paleta_image` e nunca versionada no Git. Faltava decidir como a imagem é produzida, como o upload é validado, como a URL pública sabe que a imagem está atual (`og_version`), quando ela é invalidada e o que acontece se a geração falhar logo depois de salvar.

## Decisão

### 1. O cliente desenha, o servidor valida e guarda

- O PNG 1200×630 é gerado **no navegador** pelo template `og` do renderizador de protótipos (ADR-126), alimentado com o nome, a descrição e as cores efetivas da paleta; é o mesmo SVG que o usuário veria, rasterizado em 1x com a fonte embutida. Nenhuma imagem sai da máquina a não ser que a pessoa salve/publicar uma paleta.
- O upload é multipart autenticado, `POST /dashboard/colors/palettes/{colorPalette}/og`, com CSRF pelo Inertia (`forceFormData`). O dono é conferido com `abort_unless(..., 404)`.
- `UploadPaletteOgRequest` exige `image` como **PNG** (`mimes:png`, `mimetypes:image/png`), dimensões exatas `width=1200,height=630` via regra `dimensions`, e `max` em KB; largura, altura e teto de bytes vêm de `colors.og` (`COLORS_OG_*`). Dimensões vêm da regra do Laravel; nada de confiar no nome do arquivo.

### 2. Storage atômico, versão monotônica

- `PaletteOgStorage` grava em `storage/app/data/og_paleta_image/{ulid}.png` com diretório criado sob demanda e escrita tmp + `File::replace` — quem lê nunca vê arquivo pela metade.
- O upload bem-sucedido faz `og_version + 1` (começa em 1) e responde o toast `colors.toasts.og_saved`.
- `DELETE .../og` apaga o arquivo e zera `og_version` (toast `og_removed`). Substituir apaga o anterior pelo mesmo caminho lógico.

### 3. Mudança visual invalida; o salvamento regenera

`UpdatePalette` compara `name`, `description`, `kind`, `algorithm_version`, `colors` e `roles`. Se algo mudou e a paleta tinha OG, apaga o arquivo e anula `og_version` — a próxima geração parte do estado novo sem servir imagem velha. Mudar só tags, visibilidade ou arquivamento não invalida.

No cliente, `usePaletteOg` regenera em dois momentos: ao **abrir** uma paleta pública sem OG (cobre as publicadas direto pelo gerador) e no `onSuccess` do PATCH de uma paleta pública, usando o payload recém-salvo (as props do editor ainda mostram o estado anterior). Um `Set` de módulo deduplica por paleta: a visita depois do PATCH remonta o editor enquanto o callback ainda corre, e sem isso o cartão subiria duas vezes e `og_version` pularia números.

### 4. Falha de imagem nunca bloqueia conta

Gerar/rasterizar pode falhar (canvas bloqueado, memória) e o upload pode ser recusado. O composable só acende `error` e devolve `false`: a paleta continua salva, o botão vira "Gerar imagem" de novo e o aviso (`role="alert"`) explica. Não há retry automático em laço; a próxima ação do usuário decide.

### 5. Serving só para paleta pública ativa e com arquivo

`GET /{locale}/colors/p/{slug}/og.png` (nome `colors.palettes.og`) responde 404 a menos que a paleta seja `publiclyVisible()`, tenha `og_version` e o arquivo exista — privada, arquivada, sem OG ou com arquivo perdido caem todos no 404. Quando serve, manda `Cache-Control: public, max-age=colors.og.cache_seconds` (86400) e `X-Content-Type-Options: nosniff`.

A URL anunciada nas metas leva `?v={og_version}` (ADR-124), então uma regeneração vence o cache do mensageiro sem depender do TTL. A página pública recebe `ogUrl` só quando a OG existe; senão usa a OG padrão.

### 6. Dois tipos de OG continuam separados

A OG **estática** das páginas de tool segue a ADR-105 original: `seo:og-image` → `public/og/<slug>.png` (1200×1200), versionada. A OG **dinâmica** de paleta é 1200×630, do cliente, não versionada, e não existe comando de servidor para ela — `colors:generate-og-image` não é criado.

## Consequências
- A imagem de compartilhamento é fiel à paleta e atualizada após edição, com cache correto (`?v=` + TTL) e sem servir arquivo inválido.
- O servidor não desenha nada: só valida assinatura/dimensões/tamanho, grava atômico e versiona — superfície pequena e testável.
- Falha de OG é um problema visível, não um bloqueio: salvar continua funcionando, o que mantém a promessa da ação principal.
- O custo é um endpoint de upload com throttle próprio (10/min), storage que precisa ser backupado/podado, e a duplicação de tema claro/corte de texto no cliente.

## Alternativas rejeitadas
- **Gerar a OG no servidor (Imagick/canvas)**: duplicaria o renderizador, exigiria fontes no servidor e contraria o processamento no cliente.
- **Salvar a OG em `public/og` e versionar**: a imagem muda por conta e por edição; versionar seria ruído no Git (ADR-105).
- **Servir sem verificar arquivo/versão**: quebraria a prévia social quando o arquivo fosse invalidado ou perdido.
- **Invalidar em qualquer mudança (inclusive tags)**: regeneraria à toa e não muda um pixel do cartão.
- **Bloquear o salvamento quando a OG falha**: transformaria um extra em bloqueio da ação principal.
- **Confiar só no `mimetype` do arquivo**: a regra `dimensions` + `mimes` + teto em config é o que garante o contrato do mensageiro.
