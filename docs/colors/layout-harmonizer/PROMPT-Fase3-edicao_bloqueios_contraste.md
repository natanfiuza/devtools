# Planeje a Fase 3 do Layout Harmonizer: controle manual e acessibilidade.

Escopo:
- Editar tokens individualmente.
- Bloquear e desbloquear por token e por tema.
- Restaurar geração automática com ação explícita.
- Configurar Secondary manual e retornar ao automático.
- Selecionar meta AA ou AAA para sugestões.
- Mostrar sugestões de ajuste sem aplicar automaticamente.
- Aceitar ajuste somente no tema escolhido.
- Oferecer ação explícita para aceitar nos dois temas.
- Manter bloqueio quando um ajuste for aceito em token bloqueado.
- Mostrar efeitos sobre dependentes automáticos antes da confirmação.
- Preservar tokens manuais e bloqueados.
- Mostrar avisos de combinações inviáveis.
- Garantir snapshot consistente do estado efetivo.

Alterar a meta AA/AAA não pode mudar as cores automaticamente.
Contraste insuficiente não deve impedir a continuidade.

Teste especialmente:
1. Trocar a seed não altera tokens bloqueados.
2. Bloqueio no claro não bloqueia o escuro.
3. Aceitar ajuste em um tema não altera o outro.
4. Aceitar ambos exige ação explícita.
5. Restaurar automático não apaga mudanças fora do escopo solicitado.
6. Primary permanece original até aceite.
7. Diagnósticos usam o fundo e o estado corretos.

Não implemente salvamento ou exportadores nesta fase.
Planeje primeiro, aguarde aprovação e pare ao concluir.

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