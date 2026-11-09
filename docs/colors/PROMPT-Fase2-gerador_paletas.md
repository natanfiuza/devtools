# Planeje a Fase 2 do Colors: núcleo do Color Palette Generator.

Objetivo:
Criar /{locale}/tools/colors/palette-generator, integrado ao grupo Colors.

Escopo:
- Entrada da cor base nos formatos suportados pelo Color Picker.
- Reutilização dos componentes e funções de conversão da Fase 1 (núcleo em `resources/js/lib/color/`, ADR-117).
- Geração determinística dos 11 tons:
  50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950.
- Modelo explícito de luminância, croma, matiz, alpha e gamut mapping.
- Exibição do contraste com preto e branco em cada tom.
- Identificação da cor base e dos valores convertidos.
- Copiar uma cor ou a escala inteira.
- Exportar CSS variables, SCSS, JSON, Tailwind v3 e Tailwind v4.
- Versionamento do algoritmo de geração.
- Estado vazio, entrada inválida e confirmação de cópia.
- Layout responsivo, acessível e consistente com o devtools.

Defina o comportamento quando a cor base não corresponder exatamente ao tom 500.
Não altere silenciosamente a cor fornecida pelo usuário.

Fora do escopo:
- Persistência.
- Protótipos.
- Importação de imagens.
- Social e moderação.
- Tokens Figma e Style Dictionary.

Testes:
- Determinismo.
- Ordenação de luminosidade.
- Cores extremas e sem saturação.
- Alpha.
- Cores fora de sRGB.
- Contraste.
- Exportadores.
- Integração da página.

Crie o plano e os critérios de aceite da Fase 2 em docs/colors/.

## Trabalhe no projeto devtools existente, sem recriar sua estrutura.

Leia CLAUDE.md e todos os documentos relevantes em docs/colors/.
Inspecione o código e as convenções existentes antes de propor alterações.

Regras:
- Implemente somente a fase solicitada.
- Não sobrescreva alterações locais do usuário.
- Não execute git reset, git clean ou descarte alterações.
- Não faça deploy, push ou commit sem autorização.
- Reutilize autenticação, planos, permissões, notificações e componentes existentes.
- Toda interface deve estar traduzida em en, pt-BR, pt-PT, fr e es.
- Mantenha a especificação do Colors em docs/colors/; as decisões ficam em docs/adr/ com o prefixo `colors` (ex.: `ADR-091-colors-espacos-de-cor.md`).
- Não reutilize números de ADR já existentes no repositório.
- Atualize os documentos sem apagar decisões anteriores.
- Não invente comandos de testes: confira composer.json e package.json.

Antes de codificar:
1. Verifique o estado da fase anterior.
2. Liste requisitos e documentos ausentes.
3. Apresente arquivos afetados, dependências, riscos e testes.
4. Pare para minha aprovação.

Após a aprovação:
- Implemente em etapas pequenas.
- Execute testes relevantes, typecheck e build disponíveis.
- Informe comandos executados e resultados reais.
- Registre pendências.
- Pare ao concluir a fase; não avance automaticamente.


----

> Atenção: o prompt da proxima fase e: `docs\colors\PROMPT-Fase3-harmonias_imagens.md` 
