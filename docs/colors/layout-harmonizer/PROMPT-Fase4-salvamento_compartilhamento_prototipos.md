# Planeje a Fase 4 do Layout Harmonizer: persistência e integração com a conta.

Inspecione tabelas, planos, policies, viewer público e protótipos reais do Colors.
Não crie tabelas paralelas ou um segundo contador de quota sem necessidade.

Salvamento:
- Nome obrigatório com sugestão editável.
- Descrição e tags opcionais.
- Visibilidade privada por padrão.
- Persistir snapshot completo, cores efetivas e versões.
- Não recalcular sistemas antigos automaticamente ao abrir.
- Free: 25 itens conjuntos entre paletas e sistemas, incluindo arquivados.
- Pago: sem limite funcional.
- Proteger a quota contra criações concorrentes de ambos os tipos.
- Edição não consome vaga.
- Cópia e salvar como novo consomem vaga.
- Preservar a paleta de origem.

Ciclo de vida:
- Arquivar e restaurar.
- Arquivar retira acesso público e sitemap, mas mantém contagem na quota.
- Exclusão definitiva exige confirmação, libera vaga e não reutiliza identificador.
- Downgrade preserva os dados, leitura e exportação.
- Impedir novas criações quando não houver capacidade.
- Revogar acesso e recursos derivados ao tornar privado ou arquivar.

Compartilhamento:
- Reutilizar página pública Colors com apresentação específica.
- Somente itens salvos públicos compartilham URL.
- Cadastrados podem duplicar público dentro da quota.
- Preservar crédito da origem pública sem revelar fontes privadas.

Rascunho:
- Anônimo mantém somente o último rascunho local.
- Permitir limpar.
- Ao autenticar, perguntar antes de importar.
- Importar para o editor não salva nem consome quota automaticamente.

Protótipos:
- Reutilizar site e mobile existentes para cadastrados.
- Aplicar os tokens do mesmo snapshot exibido no quadro.
- Não reconstruir os protótipos nem ampliar seus componentes sem aprovação.

Apresente migrations, compatibilidade, autorização e testes antes de alterar código.
Após aprovação, implemente, valide e pare.

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
- 