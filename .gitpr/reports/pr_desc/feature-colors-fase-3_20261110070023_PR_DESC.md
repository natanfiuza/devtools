# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: adiciona harmonias e extração de imagem ao palette generator
```

---

## 🎯 Resumo

Entrega a fase 3 do Color Palette Generator: além da escala de 11 tons, a ferramenta passa a permitir explorar harmonias cromáticas e extrair as cores dominantes de uma imagem local ou de uma URL HTTPS. O processamento continua 100% no navegador — imagem e cores não são enviadas ao servidor —, com limites definidos pela configuração do servidor e repassados à página.

## 🛠️ Mudanças Técnicas

- Adiciona abas **Escala**, **Harmonias** e **Extração** ao Palette Generator.
- Implementa `buildHarmony` com harmonias complementar, análoga, tríada, tetrádica e monocromática nos métodos OKLCH e HSL, incluindo base, deduplicação, alpha, gamut e clipping.
- Implementa extração de cores dominantes com `color-thief`, executada em worker dedicado com fallback para a thread principal.
- Adiciona contrato próprio de extração (`extractContract`) para evitar carregar a biblioteca no chunk principal.
- Implementa cliente de worker com transferência de `ArrayBuffer`, cancelamento, descarte de respostas obsoletas e degradação controlada.
- Adiciona importação local e por URL HTTPS com CORS, sem proxy, com timeout, validação de MIME e limites de bytes, dimensões, pixels e lado de análise.
- Expõe os limites via `config/tools.php` → `ColorsToolProps` → Inertia, com novas variáveis de ambiente opcionais.
- Move o conta-gotas para `@/lib/eyedropper`, remove o `input[type=color]` nativo e exibe o botão de EyeDropper apenas quando suportado.
- Padroniza o feedback de cópia com ícone de check via `useCopyConfirmation`.
- Atualiza o CSP para permitir `blob:` em `img-src` e `https:` em `connect-src`, necessário para prévia local e busca direta da imagem.
- Atualiza traduções em EN, ES, FR, PT_BR e PT_PT, além de testes e documentação relacionada.

## ⚠️ Impacto/Avisos

- **Novas variáveis de ambiente opcionais:** `TOOLS_COLORS_IMAGE_MAX_BYTES`, `TOOLS_COLORS_IMAGE_MAX_SIDE`, `TOOLS_COLORS_IMAGE_MAX_PIXELS`, `TOOLS_COLORS_IMAGE_ANALYSIS_SIDE` e `TOOLS_COLORS_IMAGE_FETCH_TIMEOUT_MS`, com valores padrão em `config/tools.php`.
- **Nova dependência:** `colorthief`.
- **CSP ampliado:** `connect-src` passa a aceitar qualquer origem `https:` e `img-src` passa a aceitar `blob:`. A mudança é necessária para a importação direta de imagem por URL e para a prévia local, mas aumenta a superfície permitida pelo CSP e deve ser revisada conscientemente.
- **Sem migração de banco:** nenhuma alteração de schema. A paleta extraída fica apenas em memória na página e é perdida ao recarregar.
- **Privacidade:** nenhuma imagem ou paleta é enviada ao servidor; a busca por URL ocorre diretamente entre navegador e origem.
- **Dependência de browser:** a extração requer APIs como `createImageBitmap`; quando indisponíveis, a UI apresenta erro controlado. O EyeDropper só aparece em navegadores compatíveis.
- **CORS:** URLs remotas só funcionam se a origem permitir leitura direta; não há proxy de fallback, e a UI orienta o uso de arquivo local nesse caso.
- **UX:** a remoção do seletor nativo de cor altera o fluxo de escolha em navegadores sem EyeDropper; campo de texto, área 2D e sliders continuam disponíveis.

close #142

---

[![GitPR](https://img.shields.io/badge/GitPR-0_errors_%C2%B7_1_warning-yellow)](https://gitpr.natanfiuza.dev.br/)