# Prompt inicial para Claude Code - Fase 1 Colors

Leia primeiro:
- CLAUDE.md
- docs/colors/SPEC-COLORS.md
- docs/colors/glossario.md
- docs/adr/ADR-091-colors a ADR-119-colors
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

Não presuma que os arquivos citados em conversas anteriores existem.
Liste documentos ausentes, referências quebradas e requisitos contraditórios.

Resolva estas inconsistências na documentação antes de implementar novas fases:

1. Escalas:
   A última confirmação do usuário definiu 11 tons:
   50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950.
   A escolha de 5, 7, 9 ou 11 refere-se à extração de cores de imagens.
   Se houver outra interpretação no código, apresente a divergência para aprovação.

2. OG images:
   Imagens estáticas das páginas de tools reutilizam o comando existente
   app/Console/Commands/GenerateOgImage.php e podem ficar em public/og.
   Imagens dinâmicas de paletas devem ficar em
   storage/app/data/og_paleta_image.
   Não versione imagens geradas pelos usuários no Git.
   Não invente um novo comando antes de inspecionar o existente.

3. Histórico:
   Anônimos possuem persistência local reduzida.
   Cadastrados possuem histórico no backend sem limite funcional.
   A Fase 1 pode ter implementado apenas o histórico local; registre o restante
   como pendência da fase de persistência.

4. Limites:
   Free: 25 paletas salvas.
   Pago: sem limite funcional.
   Não declare paletas ilimitadas para usuários Free.

5. Arquivamento:
   Arquivar não deve ser confundido automaticamente com excluir.
   Apresente as consequências para URL pública, recuperação e cota.

6. Exportação Figma:
   Não afirme compatibilidade universal.
   Identifique o plugin ou fluxo de importação a ser suportado.

Audite também:
- Conversões e alpha.
- Contraste WCAG com linearização sRGB.
- Composição de transparência sobre um fundo explícito.
- Arredondamento apenas na exibição, nunca na classificação AA/AAA.
- Gamut mapping de OKLCH/OKLab.
- EyeDropper com fallback.
- i18n nos cinco idiomas.
- Regressões nas demais tools.

Não altere código nesta primeira resposta.
Apresente pendências, correções e plano de testes.
Após minha aprovação, corrija apenas a Fase 1 e a documentação.

----

> Atenção: o prompt da proxima fase e: `docs\colors\PROMPT-Fase2-gerador_paletas.md` 
