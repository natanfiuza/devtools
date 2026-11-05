# Render do BadgeForge (SVG, PNG e fontes)

Guia operacional da F2 do BadgeForge: o que o servidor precisa ter para
rasterizar PNGs e desenhar as fontes do badge, como verificar e como podar o
cache estático. Decisões: `ADR-067`, `ADR-073` e `ADR-077`.

## Requisitos do servidor

| Item | Por quê |
|---|---|
| PHP com a extensão `imagick` | Conversão SVG -> PNG. Sem ela o endpoint `.png` responde 503 `image_unavailable` (o `.svg` continua funcionando). |
| ImageMagick com delegate SVG (librsvg) e pango | Desenhar o SVG rasterizado. O renderer emite texto com `font-family`, que o pango resolve via fontconfig. |
| fontconfig com as TTF do badge | Sem as fontes instaladas, o pango cai em DejaVu e a largura do texto pode estourar a calculada. |
| `storage/app/data/tools/badgeforge/static` gravável | Cache content-addressed dos PNGs. |

Em Debian/Ubuntu a base costuma ser:

```bash
sudo apt install imagemagick fontconfig php8.3-imagick
php -m | grep -i imagick
```

O servidor não precisa de fonte instalada para o SVG: ele apenas carrega o nome
da família no atributo `font-family`; quem desenha é o cliente.

## Instalar as fontes do badge

As seis TTF versionadas estão em `resources/badgeforge/fonts/<slug>/`
(Regular + Bold). Copie-as para o fontconfig do usuário que roda o PHP-FPM e
reconstrua o cache:

```bash
mkdir -p ~/.local/share/fonts/badgeforge
cp resources/badgeforge/fonts/*/*.ttf ~/.local/share/fonts/badgeforge/
fc-cache -f
fc-match "Fira Code"
fc-match "Libre Franklin"
fc-match "Space Grotesk"
```

O `fc-match` deve devolver o arquivo de `~/.local/share/fonts/badgeforge`; se
devolver `DejaVu`, o fontconfig não viu a cópia (confira o usuário do PHP-FPM,
não o seu).

O mesmo diretório `resources/badgeforge/fonts` é usado pelo `@font-face` em
`resources/css/app.css`; o Vite versiona os arquivos no build da página.

## Verificar a rasterização

Com o app no ar:

```bash
curl -sI 'https://devtools.natanfiuza.dev.br/api/badges/static/build/passing.png'
# HTTP/2 200, content-type: image/png

curl -s 'https://devtools.natanfiuza.dev.br/api/badges/static/build/passing.png' \
  -o /tmp/badge.png && file /tmp/badge.png
# PNG image data, 98 x 20, 8-bit/color RGBA, non-interlaced

curl -s 'https://devtools.natanfiuza.dev.br/api/badges/static/build/passing.png?font=space-grotesk' \
  -o /tmp/badge-font.png && identify /tmp/badge-font.png
```

O PNG tem **1× natural** e fundo transparente. Um badge cuja largura natural
passa de 600 px sai reduzido proporcionalmente (`badgeforge.png.max_width`); a
altura muda junto.

```bash
curl -sI 'https://devtools.natanfiuza.dev.br/api/badges/static/build/passing.png?download=1'
# content-disposition: attachment; filename="build-passing.png"; filename*=UTF-8''build-passing.png
```

Sem Imagick a resposta é `503` com o envelope `ApiError` (`image_unavailable`) e
o log da aplicação registra apenas `badgeforge: extensao imagick ausente; PNG
indisponivel.` — nunca o texto do badge.

## Métricas das fontes

Os avanços por família são gerados dos próprios TTF:

```bash
php artisan badgeforge:font-metrics --check
```

`--check` não escreve nada e falha se `resources/badgeforge/font-metrics.json`
divergir dos TTF. Se você trocar um TTF, rode o comando sem `--check`, revise o
diff e regrave as fixtures (ver `ADR-075`).

## Cache e poda

Cada SVG único gera `storage/app/data/tools/badgeforge/static/{sha256(svg)}.png`.
O mesmo SVG reusa o arquivo; a escrita é atômica (tmp + `rename`).

```bash
php artisan badgeforge:prune --dry-run   # quantos PNGs seriam removidos
php artisan badgeforge:prune             # remove os que passaram de 48 h de mtime
```

O comando só apaga `*.png` do diretório do cache; um arquivo removido é gerado
de novo sob demanda. Ajuste a janela em `badgeforge.png.prune_after_hours`.

A rotina roda pelo agendador do projeto. O `bootstrap/app.php` registra o que
estiver ativo em `task_schedules`; cadastre `badgeforge:prune` como as demais
rotinas (frequência sugerida: diária, de madrugada):

```sql
insert into task_schedules (command, expression, enabled, created_at, updated_at)
values ('badgeforge:prune', '17 3 * * *', 1, now(), now());
```

Ver `docs/guides/cron.md` para o formato da tabela, `schedule:list` e
diagnóstico.

## Solução de problemas

| Sintoma | Causa provável | O que fazer |
|---|---|---|
| `.png` responde 503 | extensão `imagick` ausente no PHP-FPM | instalar e reiniciar o PHP-FPM; conferir `php -m` com o mesmo binário do FPM |
| PNG usa DejaVu apesar do `?font=` | TTF não instaladas no fontconfig do usuário do FPM | copiar as TTF para `~/.local/share/fonts` desse usuário e rodar `fc-cache -f` |
| Texto do PNG passa da largura | fonte ausente no fontconfig (ver acima) | instalar as TTF; a largura calculada assume a família pedida |
| `Invalid UTF-8 string passed to pango_layout_set_text()` no log | aviso do librsvg/pango em alguns renders | benigno; não contém o conteúdo do badge. Não é erro da aplicação |
| PNG antigo não é substituído | cache por sha256 do SVG | o conteúdo mudou? se sim, o hash muda; para limpar tudo, `rm storage/app/data/tools/badgeforge/static/*.png` |
| Erro de permissão na escrita do cache | diretório sem permissão para o usuário do FPM | ajustar o dono/permissão de `storage/app/data/tools/badgeforge` |

## Segurança

- O conteúdo do badge vem da URL e não entra em log da aplicação; o access log
  do proxy, por natureza, registra o caminho.
- O `Content-Disposition` é montado no servidor: caracteres de controle são
  removidos, o nome ASCII é sanitizado e a variante UTF-8 vai em
  `filename*` (RFC 5987).
- O PNG é derivado e público; nenhuma credencial de provider participa da
  rasterização.
