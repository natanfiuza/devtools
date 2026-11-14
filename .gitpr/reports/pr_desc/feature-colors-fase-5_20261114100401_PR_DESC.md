# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: adiciona importação validada de tokens de design
```

---

## 🎯 Resumo

Permite importar paletas a partir de arquivos de design tokens (JSON nativo da ferramenta, W3C DTCG / Tokens Studio / Figma Variables e Style Dictionary) diretamente no gerador e no editor de paletas. O "porquê": hoje o usuário só consegue recriar manualmente uma paleta já existente em outra ferramenta; com esta etapa ele reaplica o arquivo fielmente, incluindo a base, os tons com croma cortado e os papéis semânticos.

A leitura e a validação acontecem 100% no navegador — o arquivo nunca é enviado ao servidor. A paleta atual só muda quando o usuário clica em **Aplicar**, e só é possível aplicar quando a leitura termina sem erros.

## 🛠️ Mudanças Técnicas

- **Parser puro e determinístico** em `resources/js/tools/palette-generator/lib/tokenImport.ts`: detecta formato, resolve aliases `{caminho}` com detecção de ciclo e profundidade, valida HEX, aplica limites de bytes/tokens/profundidade e devolve diagnósticos por código (sem i18n acoplado).
- **Diálogo de importação** `TokenImportDialog.vue` com dropzone, prévia (formato, tipo, base, cores, papéis) e lista de diagnósticos limitada por `maxDiagnostics` — o restante é exibido como contagem.
- **Workspace do gerador**: novo `applyImported()` congela a escala importada (sobrevive ao watcher da base) e amarra os papéis importados a uma `paletteSignature`; a assinatura invalida os papéis ao mudar base, editar a extração ou trocar de aba.
- **Rascunho (`saveDraft.ts`)**: passa a carregar `scale` (itens + `algorithmVersion` + `clippedTones`) e `roles`, para a importação sobreviver ao login. Campos novos têm validação tolerante: rascunhos antigos e escalas/papéis corrompidos continuam restaurando a base.
- **Salvamento**: `PaletteSaveSource` aceita `roles` opcionais; `SavePaletteDialog` usa os papéis importados em vez dos defaults. `paletteSignature()` nova identifica o snapshot importado.
- **Backend**: `ColorsToolProps::tokenImportLimits()` e `sourceLabelMaxLength` expostos via Inertia; todos os tetos vêm de `config/colors.php` (nenhum número fixado no cliente). `PaletteRules` agora usa `colors.palettes.source_label_max_length`.
- **Exportação nativa**: JSON passou a incluir o campo aditivo `base` (formato continua v1) para permitir reimportação fiel.
- **Testes**: Pest valida os payloads esperados das fixtures contra as regras do servidor; Vitest cobre parser (5 formatos/erros), diálogo, workspace, editor, assinatura e paridade i18n nos 5 idiomas.

## ⚠️ Impacto/Avisos

- **Novas variáveis de ambiente** (todas com default seguro, nenhuma obrigatória): `COLORS_SOURCE_LABEL_MAX_LENGTH`, `COLORS_TOKEN_IMPORT_MAX_BYTES` (512 KB), `COLORS_TOKEN_IMPORT_MAX_TOKENS` (5000), `COLORS_TOKEN_IMPORT_MAX_DEPTH` (40), `COLORS_TOKEN_IMPORT_MAX_REFERENCE_DEPTH` (10), `COLORS_TOKEN_IMPORT_MAX_DIAGNOSTICS` (20). Em produção, ajuste via `.env`.
- **Alteração de contrato de props Inertia**: `palette` agora inclui `sourceLabelMaxLength` e uma nova prop `tokens` é enviada ao editor e ao gerador; páginas que dependam do shape anterior devem ser atualizadas (já cobertas por testes de feature).
- **Formato de exportação nativo**: o JSON gerado ganhou o campo `base`. Consumidores externos que façam parsing estrito do arquivo devem ser verificados — `formatVersion` permanece 1 e a leitura é tolerante a arquivos antigos.
- **Formato de rascunho estendido** (sessionStorage): aditivo e retrocompatível; rascunhos existentes continuam válidos.
- **Sem migrações de banco** e **sem novas dependências**.

close #155

---

[![GitPR](https://img.shields.io/badge/GitPR-0_errors_%C2%B7_7_warnings-yellow)](https://gitpr.natanfiuza.dev.br/)