# Formatos de exportação

## CSS Variables
:root { --color-primary-500: #...; }

## Tailwind v3
theme.extend.colors.primary = { 50: "#...", ... }

## Tailwind v4
@theme { --color-primary-500: #...; }

## Figma Tokens JSON
{
  "color": {
    "primary": {
      "500": { "$type": "color", "$value": "#..." }
    }
  }
}

## Style Dictionary JSON
{
  "color": {
    "primary": {
      "500": { "value": "#..." }
    }
  }
}
