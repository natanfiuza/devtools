# Execute a auditoria final do Layout Harmonizer.

Compare implementação, SPEC.md, TOKENS.md, ADRs e TESTES.md.
Não considere um requisito concluído somente porque existe uma tela.

Verifique:
- Integração no grupo Colors.
- Cinco idiomas.
- Picker e entradas reutilizadas.
- Quatro estratégias e algoritmo versionado.
- Dois temas e comparação.
- Primary preservada até aceite.
- Variantes distintas de estados.
- Feedback independente da seed.
- Accent triádica.
- Bloqueios e ajustes por tema.
- AA/AAA e avisos.
- Quota conjunta de 25, incluindo arquivados.
- Concorrência, downgrade e exclusão.
- Privacidade, autorização e compartilhamento.
- Rascunho e consentimento de importação.
- Protótipos existentes.
- Exportação MD/CSS/ZIP, fallback e data-theme.
- Ausência de chamadas de IA desnecessárias.
- Ausência de regressões nas outras tools.

Execute os comandos reais de:
- Testes relevantes.
- Suíte de regressão disponível.
- Typecheck.
- Build.

Entregue:
1. Matriz requisito → arquivos → testes → situação.
2. Resultados reais dos comandos.
3. Pendências classificadas por gravidade.
4. Checklist manual de validação.
5. Instruções de deploy e rollback compatíveis com o projeto.
6. Lista de migrations e efeitos sobre dados.
7. Documentação atualizada.

Não faça commit, push ou deploy.
Se encontrar falhas, apresente um plano de correção antes de ampliar o escopo.

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