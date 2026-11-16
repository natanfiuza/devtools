# Inicie a Fase 1 do Layout Harmonizer, somente planejamento técnico.

Leia especialmente:
- SPEC.md
- TOKENS.md
- CONTRATO-TECNICO.md
- TESTES.md
- Os ADRs pertinentes em docs/colors/layout-harmonizer/adr/

Inspecione o motor de cores já existente no Colors.

Apresente:
1. Biblioteca de conversão a reutilizar ou adicionar, versão, licença e motivo.
2. Modelo de dados de entrada e saída do motor.
3. Catálogo completo de tokens por tema.
4. Tabela proposta de luminosidade e croma por família e tema.
5. Estratégia de gamut mapping para sRGB.
6. Regras de geração de superfícies, textos, variantes e estados.
7. Famílias de feedback existentes a reutilizar.
8. Grafo de dependências entre tokens.
9. Política para seeds sem croma, preto, branco e cores muito saturadas.
10. Fixtures de referência e critérios de comparação visual.

Requisitos aprovados:
- Primary mantém a seed efetiva até aceite explícito de ajuste.
- Claro e escuro são gerados simultaneamente.
- Análoga: deslocamento de matiz de 30 graus.
- Complementar: 180 graus.
- Triádica: 120 e 240 graus, com Accent e seus estados.
- Monocromática: mantém matiz e varia luminosidade/croma.
- Secondary manual opcional.
- Feedback independente da seed.
- Variantes diferentes de hover e active.
- Algoritmo determinístico e versionado.
- Nenhuma chamada de IA para gerar cores.

Para cores derivadas, detalhe gamut mapping.
Para Primary original, não faça ajuste silencioso de luminosidade ou croma.
Explique como representar entradas fora do gamut de saída preservando a origem.

A documentação recomenda catálogo estável para Accent nas demais estratégias.
Proponha essa regra explicitamente e peça minha aprovação antes de implementá-la.

Não altere código ainda.
Pare para aprovação dos parâmetros e das fixtures.


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