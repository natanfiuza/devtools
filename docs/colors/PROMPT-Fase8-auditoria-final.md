# Faça a auditoria final do grupo Colors.

Compare a implementação com os requisitos aprovados em docs/colors/.
Não declare concluído um requisito apenas porque existe uma tela.

Verifique:
- As duas tools e integração com o catálogo.
- Cinco idiomas.
- Acessibilidade por teclado e contraste.
- EyeDropper e fallback.
- Alpha, OKLCH/OKLab e gamut mapping.
- Exportação e importação.
- Privacidade das imagens originais.
- Persistência e limite de 25 paletas Free.
- Paletas privadas, arquivadas e excluídas.
- Protótipos PNG/SVG.
- SEO e OG estática versus dinâmica.
- Social e curtidas anônimas.
- Moderação DeepSeek e cache em `ai_cache` (sentinela de expiração definido na Fase 7).
- Notificações e silenciamento.
- Segurança de uploads e JSON importado.
- Ausência de regressões nas demais tools.

Execute os testes, typecheck e build existentes.
Não execute testes destrutivos contra produção.

Entregue:
1. Matriz requisito → implementação → teste.
2. Pendências classificadas por gravidade.
3. Resultado real dos comandos.
4. Checklist manual por navegador.
5. Instruções de deploy e rollback compatíveis com o projeto.
6. Lista de decisões ainda não aprovadas.

Não faça deploy nem push.

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
