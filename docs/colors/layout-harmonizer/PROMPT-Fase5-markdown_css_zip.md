# Planeje a Fase 5 do Layout Harmonizer: exportações.

Permissões:
- Anônimos podem copiar cor individual, mas não exportar MD/CSS/ZIP.
- Cadastrados Free e pagos podem exportar sem salvar.
- Reutilize capacidades de autenticação existentes.
- Se houver endpoints, aplique autorização no backend.
- Não descreva proteção client-side como DRM.

Markdown:
- Idioma selecionável entre os cinco existentes.
- Padrão no idioma atual.
- Documento autossuficiente de instruções de design para IA.
- Incluir seed original/efetiva, temas, estratégia e versões.
- Tabela de tokens, finalidades, estados e regras de aplicação.
- Contrastes, avisos, bloqueios e ajustes aceitos.
- Orientar a IA a usar tokens semânticos e não inventar novas cores.
- Não expor metadados de fontes privadas.

CSS:
- HEX sRGB de fallback e OKLCH progressivo.
- Prefixo padrão lh, editável e validado.
- Escopo :root ou classe simples.
- Rejeitar CSS arbitrário e seletores não permitidos.
- Temas controlados por data-theme light/dark.
- Documentar como ativar os temas.
- Validar fallback em ambiente sem suporte a OKLCH.
- Incluir avisos em comentários sanitizados.

ZIP:
- Markdown e CSS do mesmo snapshot.
- Nomes sanitizados e UTF-8.
- Geração local.
- Nenhuma chamada de IA.
- Não exigir salvamento.

Testes:
- Quadro, protótipos e arquivos representam o mesmo snapshot.
- Exportar não regenera cores.
- Falha de contraste não bloqueia, mas aparece nos arquivos.
- Prefixos e escopos inválidos são rejeitados.
- Permissões corretas.
- ZIP válido e conteúdo consistente.

Apresente contratos e biblioteca ZIP antes de implementar.
Após aprovação, implemente, teste e pare.

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