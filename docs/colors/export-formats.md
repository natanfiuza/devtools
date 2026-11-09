# Formatos de exportação

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
