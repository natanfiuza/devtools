# Especificação de produto
## Objetivo
Layout Harmonizer, nova ferramenta do grupo Colors, transforma uma cor em sistema semântico de layout com quadro visual, temas claro/escuro e exportações Markdown para IA e CSS. Referência: captura fornecida pelo usuário; não copiar código ou afirmar aderência a uma versão de Material Design.
## Integração
Reutilizar Laravel/Inertia/Vue/TypeScript, i18n en/pt-BR/pt-PT/fr/es, picker, conversões, contraste, protótipos, planos e persistência Colors. Auditar existência real antes de criar. Rota recomendada /tools/colors/layout-harmonizer seguindo convenções de locale existentes.
## Geração aprovada
Seed original preservada. Primary usa exatamente seed efetiva nos dois temas até aceite explícito de ajuste. Sugestões separadas por tema, ação para aceitar ambos explícita. AA padrão; AAA selecionável para sugestões sem alterações automáticas. Alterar meta não reescreve cores.
Estratégias fixas e versionadas: análoga +30 graus, complementar +180, triádica +120/+240, monocromática mantém matiz e varia luminosidade/croma. Secondary manual opcional. Triádica inclui Accent, On Accent e estados próprios. Variantes separadas de hover/active. Feedback independente da seed com famílias estáveis adaptadas ao tema.
Edição e bloqueio por token e por tema independentes. Aceitar ajuste explícito em token bloqueado mantém bloqueio no novo valor. Restaurar automático deve ser ação explícita. Seed inicialmente opaca: alpha exige fundo escolhido e composição explícita; guardar original e efetiva.
## Acesso
Anônimo gera, inspeciona e copia uma cor individual. Não exporta MD/CSS/ZIP, não salva. Último rascunho local até limpar. Ao autenticar perguntar antes de importar; não salvar/consumir quota automaticamente.
Cadastrado exporta sem salvar. Free salva até 25 itens conjuntos com paletas Colors, incluindo arquivados. Pago sem limite funcional. Um sistema com dois temas é um item. Editar não consome vaga. Exclusão definitiva libera vaga. Downgrade preserva leitura/exportação e dados; impede novas criações acima da quota.
## Dados e visibilidade
Nome obrigatório com sugestão editável. Descrição e tags opcionais. Privado padrão. Compartilhar apenas itens salvos públicos. Reutilizar viewer público Colors com apresentação de sistema. Qualquer cadastrado pode duplicar público dentro da quota. Salvar da fonte cria outro item, sem alterar fonte. Registrar origem pública, não expor fontes privadas.
Arquivar retira acesso público/sitemap até restauração, mas continua na quota. Exclusão definitiva exige confirmação, URL indisponível e identificador não reutilizado.
## Entradas/protótipos
Cor nova, cor de paleta salva autorizada, cor extraída por recursos Colors existentes. Imagem original permanece local. Protótipos site/mobile existentes para cadastrados. Tema da ferramenta não muda com seed; tema gerado é independente.
## Exportações
MD no idioma escolhido, padrão idioma atual. CSS HEX e OKLCH com fallback, prefixo lh editável e escopo :root ou classe simples validada. Temas via data-theme light/dark controlado. ZIP conjunto local. Falhas de contraste não bloqueiam exportação; avisos explícitos em MD e CSS. Exportar snapshot efetivo, sem regenerar cores. Não chamar IA para produzir cores ou instruções.
