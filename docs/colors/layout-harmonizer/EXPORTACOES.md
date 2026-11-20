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

## Implementação (F5, 2026-11-20)

Contrato implementado 100% no navegador em `resources/js/tools/layout-harmonizer/export/` (`css.ts`, `markdown.ts`, `zip.ts`, `options.ts`, `filename.ts`, `messages.ts`), sem rota, action ou migration: o card `LayoutExportPanel.vue` lê exclusivamente o `snapshot` exibido (live, compatível, readonly ou arquivado) e nunca roda o motor de novo.

- MD: um dos cinco idiomas (padrão o ativo), com o subtree entregue pelo servidor (`LayoutSystemExportMessages`); avisos de contraste medidos contra `snapshot.target`; origem só do crédito público congelado.
- CSS: fallback HEX/`rgba()` e bloco `@supports (color: oklch(0 0 0))`; escopo `:root` ou uma classe, prefixo `lh` editável, ambos validados com tetos de `config/colors.php` (`layout_systems.export`).
- ZIP: `fflate ^0.8.3`, `design-instructions.md` + `colors.css` de nomes fixos, `mtime` e nível fixos; pacote `{slug}-layout.zip`.
- Permissões: anônimo vê o card bloqueado com CTA (`create?export=1`, que não abre o modal de salvar); a página pública não exporta para ninguém.
- Decisões em ADR-148/ADR-155 (implementação) e ADR-175/ADR-176/ADR-177; parâmetros em `PARAMETROS.md`.
