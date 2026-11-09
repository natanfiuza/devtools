# Planeje a Fase 6 do Colors: recursos sociais e exportação visual.

Social:
- Seguir e deixar de seguir usuários, exigindo login.
- Curtir e descurtir paletas públicas.
- Permitir curtidas anônimas.
- Reutilizar favoritos de paletas da Fase 5.
- Não criar recurso de bloqueio de usuários.

Curtidas anônimas:
- Não usar somente IP como identidade.
- Propor identificador anônimo first-party assinado e minimizado.
- Usar IP apenas como sinal antiabuso, com tratamento adequado de privacidade.
- Deduplicação transacional.
- Não alegar prevenção absoluta de fraude.
- Não expor identificadores anônimos na API pública.
- Definir o comportamento após login sem duplicar curtidas.

Exportação de paleta:
- PNG e SVG.
- Layouts horizontal, vertical e card.
- Composição tipográfica com nome e códigos das cores.
- Marca d'água fixa do devtools.
- Geração no navegador.
- Saída SVG sanitizada e sem referências externas.
- Reutilizar infraestrutura de exportação já existente.

Comentários:
Não publicar comentários nesta fase.
Eles dependem de moderação, denúncias e notificações da Fase 7.

Notificações:
Registrar os pontos de integração para curtidas e seguidores,
sem disparar e-mails provisórios ou duplicados.

Teste idempotência, concorrência, autorização, privacidade e exportação.

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

> Atenção: o prompt da proxima fase e: `docs\colors\PROMPT-Fase7-comentarios_deepseek.md` 
