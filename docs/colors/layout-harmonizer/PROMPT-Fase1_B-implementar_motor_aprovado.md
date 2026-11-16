# Implemente somente o motor da Fase 1, usando o contrato e a calibração que acabei de aprovar.

Antes de começar, resuma os parâmetros aprovados e registre-os na documentação.

Escopo:
- Funções puras, independentes de componentes Vue.
- Quatro estratégias.
- Dois temas.
- Tokens semânticos e famílias de feedback.
- Seed original e seed efetiva.
- Composição explícita de alpha sobre fundo escolhido.
- Sugestões de ajuste separadas dos valores aplicados.
- Diagnósticos de contraste.
- Versionamento de algoritmo e schema.
- Estrutura para overrides e bloqueios.
- Testes unitários e fixtures.

Não implemente interface, persistência ou exportadores.

Teste:
- Determinismo.
- Primary preservada até aceite.
- Cores extremas e acromáticas.
- Gamut mapping.
- Composição alpha.
- Variantes versus estados.
- Feedback estável.
- Pares de contraste e classificação sem arredondamento prévio.

Ao finalizar, informe os testes executados, seus resultados e pare.

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