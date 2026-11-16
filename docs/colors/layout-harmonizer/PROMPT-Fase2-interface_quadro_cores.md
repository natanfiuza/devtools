# Planeje a Fase 2 do Layout Harmonizer: integração visual com Colors.

Confirme que a Fase 1 está concluída e seus testes passam.

Escopo:
- Registrar a ferramenta no grupo Colors.
- Criar a página seguindo a convenção de rotas e idiomas existente.
- Reutilizar o Color Picker.
- Permitir selecionar as quatro estratégias.
- Mostrar claro e escuro, com alternância e comparação lado a lado.
- Exibir o quadro semântico completo.
- Mostrar valor, finalidade, proveniência e diagnóstico de cada token.
- Separar texto de interface da demonstração real das cores.
- Permitir copiar um valor individual, inclusive para anônimos.
- Preservar o último estado válido quando houver entrada inválida.
- Manter o tema da ferramenta independente do sistema gerado.
- Implementar estados vazios, mensagens, responsividade e acessibilidade.
- Traduzir toda a interface nos cinco idiomas.

Ainda não implemente:
- Edição manual e bloqueios completos.
- Persistência.
- Exportação MD/CSS/ZIP.
- Novos protótipos.

Não exiba botões funcionais para recursos ainda não implementados.
Use estados informativos quando necessário.

Apresente primeiro os componentes e testes.
Após aprovação, implemente e pare ao concluir.

## Instruções auxiliares

Leia CLAUDE.md e toda a documentação relevante em
docs/colors/layout-harmonizer/.

Considere a especificação v1.0 e os ADRs aprovados como referência de produto.
Inspecione a implementação real antes de propor alterações.

Regras:
- Execute somente a fase solicitada.
- Reutilize a infraestrutura existente do Colors.
- Não recrie picker, conversões, contraste, protótipos, autenticação ou planos.
- Preserve alterações locais e dados existentes.
- Não execute git reset, git clean, commit, push ou deploy sem autorização.
- Não rode migrations nem testes contra produção.
- Não adicione dependências sem apresentar necessidade, licença e alternativas.
- Toda interface deve suportar en, pt-BR, pt-PT, fr e es.
- Mantenha documentação e novos ADRs desta ferramenta em
  docs/colors/layout-harmonizer/.
- Confira os scripts reais de composer.json e package.json antes de executar
  testes, typecheck ou build.
- Não invente resultados de testes.

Antes de alterar código:
1. Verifique o estado da fase anterior.
2. Liste os arquivos que serão criados ou alterados.
3. Apresente dependências, riscos, testes e critérios de aceite.
4. Pare para minha aprovação.

Após a aprovação:
- Implemente em etapas pequenas.
- Execute as verificações pertinentes.
- Atualize a documentação.
- Informe resultados reais e pendências.
- Pare ao concluir a fase, sem iniciar a próxima.