# Planeje a Fase 5 do Colors: organização pessoal e interoperabilidade.

Escopo:
- Histórico de cores no backend para cadastrados, sem limite funcional.
- Histórico local reduzido para anônimos.
- Favoritos de cores compartilhados entre Picker e Generator.
- Favoritar paletas públicas de outros usuários.
- Evitar favoritos duplicados.
- Configurações reutilizáveis de geração, chamadas presets.
- Antes de implementar presets, verificar se sua inclusão foi efetivamente
  aprovada; se não estiver clara, pedir minha confirmação.
- Não confundir preset com paleta salva.
- Não importar histórico anônimo para a conta sem confirmação do usuário.

Exportação:
- Tokens de cores primitivas e semânticas.
- Style Dictionary.
- Formato compatível com um fluxo de importação Figma explicitamente definido: W3C DTCG JSON (`$type`/`$value`), importável por Tokens Studio e Figma Variables (ADR-095).
- CSS e TypeScript.
- Reutilizar exportadores existentes.
- Registrar versão do formato.
- Preservar alpha e documentar conversões de gamut.

Importação:
- JSON nativo da ferramenta.
- Formatos de tokens que forem efetivamente suportados.
- Validação de estrutura, tamanho, profundidade, referências e ciclos.
- Nunca executar conteúdo importado.
- Exibir diagnóstico de incompatibilidade sem perder o estado atual.

Não criar integração direta com a API do Figma sem aprovação.
Inclua arquivos de referência para validar importação e exportação,
sem tratá-los como implementação da aplicação.

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

> Atenção: o prompt da proxima fase e: `docs\colors\PROMPT-Fase6-social_imagens.md` 
