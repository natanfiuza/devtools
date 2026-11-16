# Contrato MD/CSS/ZIP
## Autorização
Autenticados Free/pagos exportam snapshot salvo ou não. Anônimo copia apenas valor individual. Se houver endpoint, validar sessão/backend. Client-side é gate de produto, não DRM; não prometer impossibilidade de inspecionar cores. Não devolver export integral em preview oficial para visitante.
## MD
Documento UTF-8 autossuficiente: nome, idioma, finalidade, seed original/efetiva/aplicada, temas, estratégia/versões, tokens por função, regras de fundos/textos/botões/links/feedback/foco, pares de contraste, falhas, manuais/bloqueios, ajustes aceitos e limitações. Instruir IA a usar semântica e não inventar novas cores nem alterar bloqueios. Sem nomes/dados de origem privada. Avisos sempre presentes.
## CSS
Variáveis semânticas com prefixo lh editável. Somente :root ou classe simples como escopo. Seletores para data-theme light/dark controlados pela ferramenta, combinados ao escopo, sem media query automática obrigatória. Default explícito light recomendado, a documentar no export. Prefixo/escopo escapados/validados, rejeitar entrada CSS arbitrária.
Fallback HEX sRGB primeiro e declaração OKLCH compatível depois, sob mecanismo progressivo validado por testes na implementação. Não deixar variável contendo formato não suportado quebrar fallback. Comentar avisos com conteúdo sanitizado. Não usar valores de alpha sem preservar composição real aprovada.
## ZIP
Pelo menos design-instructions.md e colors.css, mesmo snapshot, UTF-8, nome sanitizado e versões em ambos. Gerar localmente sem servidor/IA. Biblioteca ZIP deve ser auditada.
Quadro, preview e export devem ser idênticos em intenção e estado; não executar outro algoritmo no exportador.
