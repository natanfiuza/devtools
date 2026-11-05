# ADR-077: Fonte do badge, PNG rasterizado e download

Status: Aceito
Data: 2026-11-02
Relacionados: ADR-067 (Imagick), ADR-073 (formatos), ADR-075 (render PHP e espelho TS), ADR-076 (cache e endpoint stateless)

## Contexto

A F1 entregou o badge apenas em SVG, com a pilha de fontes do sistema
(`DejaVu Sans`) embutida no atributo `font-family`. Com PNG na F2 (ADR-073),
abriram-se três perguntas:

1. **Tipografia**: o usuário pediu fontes próprias (Fira Code, Libre Franklin,
   Space Grotesk). O navegador e o servidor precisam desenhar o texto com a
   mesma fonte, ou a largura calculada diverge do desenho — no SVG e,
   principalmente, no PNG rasterizado.
2. **Rasterização**: o renderer PHP produz vetor; o PNG exige ImageMagick e
   uma política de tamanho, qualidade e transparência.
3. **Entrega para download**: o consumidor quer o arquivo, não a imagem
   embutida em um `<img>`; o nome sugerido precisa sobreviver a acentos sem
   quebrar o cabeçalho.

## Decisão

### Fontes do badge (`?font=`)

1. **Três famílias empacotadas no repositório**, em
   `resources/badgeforge/fonts/<slug>/` (Regular + Bold, `OFL.txt` e
   `README.txt`): `fira-code`, `libre-franklin`, `space-grotesk`. O catálogo
   fica em `config/badgeforge.php` (`fonts`), na ordem em que aparece na UI.
2. **O padrão continua a pilha do estilo**; `?font=<slug>` troca a pilha do
   `font-family` e a família de métricas. Slug desconhecido, vazio ou com tipo
   errado cai no padrão silenciosamente — nunca 4xx por parâmetro cosmético.
3. **As métricas são derivadas dos próprios TTF**, não digitadas: o comando
   `badgeforge:font-metrics` lê `head.unitsPerEm`, `cmap` (formato 4) e `hmtx`,
   normaliza para 1000 unidades/em e gera `families.<slug>` para os mesmos 192
   code points de `families.sans`, mais `family_bold_percent`. O comando faz
   merge e preserva `sans`/`helvetica`; `--check` acusa drift entre JSON e TTF.
4. **A web usa as mesmas TTF** via `@font-face` em `resources/css/app.css`
   (pesos 400 e 700, `font-display: swap`), empacotadas pelo Vite.
5. **O preview vira SVG inline** (`v-html` de documento gerado e escapado pelo
   próprio renderer). Uma fonte carregada pela página **não** vale dentro de um
   `<img>`; manter o data URI faria o preview mentir sobre a tipografia. O
   conteúdo é nosso e já passou pelo escape do renderer.
6. **No PNG, a fonte precisa existir no fontconfig do servidor** (as TTF
   instaladas em `~/.local/share/fonts` + `fc-cache -f`); sem isso, o
   ImageMagick desenha em DejaVu e o texto pode estourar a largura calculada.
   O guia operacional é `docs/guides/badgeforge-render.md`.

### PNG rasterizado

1. **1× natural, fundo transparente** (`png32`), sem retina. Badges mais largos
   que `badgeforge.png.max_width` (600) são reduzidos **proporcionalmente** com
   Lanczos — o cap é um teto de largura, não um redimensionamento.
2. **Cache em disco content-addressed**: `storage/app/data/tools/badgeforge/static/{sha256(svg)}.png`,
   com escrita atômica (tmp + `rename`). Dois SVGs iguais geram o mesmo arquivo;
   a segunda requisição não rasteriza. Um arquivo apagado é gerado de novo.
3. **Sem Imagick → 503** com envelope `ApiError` (`image_unavailable`); o log
   registra apenas a ausência da extensão, nunca o conteúdo do badge.
4. **`badgeforge:prune`** remove `*.png` com mtime acima de
   `prune_after_hours` (48 h), com `--dry-run`; é rotina de operação, registrada
   em `task_schedules` como as demais (ver `docs/guides/cron.md`).

### Download

1. **`?download=1` vale para SVG e PNG** e acrescenta
   `Content-Disposition: attachment`.
2. O nome é `label-message.<ext>`, truncado em 80 code points e sem caracteres
   de controle; a variante `filename="..."` é ASCII sanitizado e a variante
   RFC 5987 (`filename*=UTF-8''...`) preserva acentos. Sem essa dupla, navegador
   antigo quebra ou nome acentuado vira lixo.
3. O ETag continua sendo o sha256 do corpo efetivamente servido — PNG e SVG
   têm hashes distintos, então `If-None-Match` não cruza os formatos.

## Alternativas consideradas

- **Pré-renderizar o PNG e embutir a fonte no SVG**: aumentaria o SVG em
  centenas de KB, dificultaria o cache por conteúdo e exigiria subsetting.
- **Preview via PNG base64 do servidor**: contraria a regra de a tool rodar
  100% no navegador e ainda não resolveria a fonte escolhida em tempo real.
- **Fonte apenas no navegador** (ignorar o `?font=` no servidor): o PNG sairia
  com tipografia diferente da preview — inaceitável para o mesmo badge.
- **Cache de PNG no banco**: desperdiça I/O e backup para arquivos derivados e
  descartáveis; o disco endereçado por hash já é idempotente.

## Consequências

### Positivas

- Mesma tipografia no preview, no SVG servido e no PNG, com largura previsível.
- PNG rápido a partir da segunda requisição e seguro em concorrência.
- Download com nome legível em qualquer navegador.
- Métricas auditáveis: o JSON não divergir dos TTF é verificável por comando.

### Negativas / Trade-offs

- O PNG depende de fontconfig **e** das TTF instaladas no servidor; o guia de
  deploy passa a ser obrigatório.
- O cache em disco é sem teto e precisa da rotina de poda.
- Os bytes do PNG podem variar entre versões de ImageMagick; o cache é por
  máquina e o ETag muda junto (aceitável para imagem derivada).
- Toda mudança no renderer continua exigindo regravar fixtures nos dois lados
  (ADR-075); a F2 só acrescentou campos, e a saída padrão da F1 não mudou.
