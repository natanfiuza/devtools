# ADR-177 — Markdown de instruções para IA
Data: 2026-11-20
Status: Aceito

## Contexto

O contrato de exportação (ADR-148) pede um documento autossuficiente que
ensine uma IA a aplicar o quadro sem inventar cores. A F5 precisava fixar a
estrutura, a fonte das traduções, o tratamento dos avisos e o que da origem
pode aparecer no texto.

## Decisão

- **Seções, nesta ordem**: título e finalidade; metadados (idioma, estratégia,
  meta AA/AAA, seed original/efetiva/fundo de composição, secundária
  automática/manual, versões); origem condicional; instruções para a IA; temas;
  tabela de tokens por tema (33 linhas cada, com finalidade, HEX, OKLCH,
  proveniência e bloqueio); regras de aplicação (fundos, textos, botões,
  links, feedback, foco); contrastes por tema (16 pares com razão, AA e AAA);
  avisos; nota de geração.
- **Idioma**: o documento é gerado no idioma escolhido a partir do subtree que
  o servidor entrega para os cinco locales. Nomes técnicos (tokens, HEX,
  OKLCH, `data-theme`) não são traduzidos; o resto é. A razão de contraste usa
  o locale do documento.
- **Avisos sempre presentes**: falha de contraste medida contra
  `snapshot.target` (AA usa `aaNormal`; AAA usa `aaaNormal`, porque
  `diagnostics` é só AA), bloqueios, valores manuais, ajustes aceitos, tokens
  clipados, modo compatível/readonly e limitações. Lista vazia escreve
  “nenhum”; aceitar um ajuste não remove o aviso do histórico.
- **Texto do usuário escapado**: nome e origem passam por `escapeMarkdown`
  (barras, crases, `*`, `_`, `#`, `<`, `>` e `|`, com quebra colapsada), para
  não quebrar título nem célula de tabela.
- **Origem**: no tool, só quando o servidor marca a origem como pública; no
  editor, só quando o crédito congelado (`credit.source.palette_public`) era
  público, usando `palette_name`. Nunca a paleta ao vivo, nunca autor privado,
  nunca link.
- **Nota de geração**: registra que o documento saiu do snapshot exibido, no
  navegador, sem chamada de IA e sem envio ao servidor.

## Consequências

- O mesmo snapshot gera o quadro, o CSS e o Markdown com os mesmos valores;
  o teste de consistência troca um token no snapshot e verifica nos arquivos.
- O documento é reproduzível: sem data, sem variável de ambiente, sem
  requisição; dois builds da mesma entrada dão o mesmo texto.
- Uma tradução sem as chaves do grupo `export` quebraria o documento; por isso
  `Tools layout-harmonizer` exige as mesmas folhas nos cinco idiomas e o teste
  de placeholders garante `{state}`, `{target}` e companhia.
