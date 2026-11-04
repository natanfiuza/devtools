# Prompt inicial para Claude Code - Fase 1 Colors

Leia primeiro:
- CLAUDE.md
- docs/colors/SPEC-COLORS.md
- docs/colors/glossario.md
- docs/colors/adr/ADR-051 a ADR-086
- docs/colors/html-color-picker.md
- docs/colors/palette-generator.md

Implemente apenas a FASE 1 do grupo Colors:
- Criar grupo Colors no catálogo de tools.
- Criar HTML Color Picker completo em /tools/colors/html-color-picker.
- Funcionalidades: input color, EyeDropper (lupa) com fallback, preview, sliders RGB/RGBA e HSL/HSLA, contraste WCAG com preto/branco, shades/tints, histórico local, favoritos, copiar, alpha.
- Suporte a HEX, RGB, RGBA, HSL, HSLA, OKLCH, OKLab e nomes CSS.
- Contraste: exibir "Black text (X:1 👍/👎)" e "White text (X:1 👍/👎)" com indicadores AA/AAA.
- i18n completo em en, pt-BR, pt-PT, fr, es.
- Tema dark-first consistente.
- Testes Vitest para conversões, contraste, shades, EyeDropper fallback.
- Não implemente Palette Generator, backend, providers, moderação ou social nesta fase.

Restrições:
- Processamento 100% no navegador.
- Sem envio de cores ao servidor.
- Sem código do W3Schools ou Color Conjure.
- Trabalhe em etapas pequenas e pare para revisão.

Antes de codificar, apresente:
1. Lista de arquivos a criar/editar.
2. Estrutura de componentes.
3. Plano de testes.
4. Riscos.
