# Formatos de exportação

## Fase 2 — escopo entregue (Palette Generator)
Exporta sempre os **11 tons** da escala, na ordem `50–950`, a partir do nome sanitizado da paleta (padrão `primary`; minúsculas, sem diacríticos, `-` no lugar do resto, até 32 caracteres). A base não é exportada como um tom extra.

Valor por tom:

- alpha `1` → `#rrggbb`;
- alpha `< 1` → `rgba(r, g, b, a)` (até 3 casas, sem zeros à direita) em CSS, SCSS e Tailwind v3; `#rrggbbaa` em JSON e Tailwind v4.

Arquivos gerados (`{name}` = slug):

| Formato | Conteúdo | Arquivo | MIME |
|---|---|---|---|
| CSS | comentário com nome e versão do algoritmo + `:root { --color-{name}-50: …; }` | `{name}.css` | `text/css;charset=utf-8` |
| SCSS | `$color-{name}-50: … !default;` | `{name}.scss` | `text/x-scss;charset=utf-8` |
| JSON | `{ "name", "algorithmVersion", "colors": { "50": … } }` indentado | `{name}.json` | `application/json;charset=utf-8` |
| Tailwind v3 | `module.exports = { theme: { extend: { colors: { {name}: { 50: '#…' } } } } }` | `tailwind.{name}.config.js` | `text/javascript;charset=utf-8` |
| Tailwind v4 | `@theme { --color-{name}-50: …; }` | `{name}.tailwind.css` | `text/css;charset=utf-8` |

Todos terminam com uma quebra de linha; o formato é determinístico (mesma escala → mesmos bytes). O download usa o MIME acima. W3C DTCG e Style Dictionary continuam fora da F2 (F5).

## CSS Variables
:root { --color-primary-500: #...; }

## Tailwind v3
theme.extend.colors.primary = { 50: "#...", ... }

## Tailwind v4
@theme { --color-primary-500: #...; }

## Tokens W3C DTCG JSON
{
  "color": {
    "primary": {
      "500": { "$type": "color", "$value": "#..." }
    }
  }
}

Fluxo de importação suportado: arquivo DTCG importado pelo plugin **Tokens Studio** no Figma ou pelas **Figma Variables** (import de JSON). Não há integração direta com a API do Figma e não se promete compatibilidade universal com qualquer plugin.

## Style Dictionary JSON
{
  "color": {
    "primary": {
      "500": { "value": "#..." }
    }
  }
}
