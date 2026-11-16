# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: adiciona motor do layout harmonizer com 33 tokens e contraste
```

---

## 🎯 Resumo

Entrega o núcleo de geração de paletas do Layout Harmonizer (Fase 1): um motor puro e determinístico que transforma uma cor semente em um quadro semântico completo de 33 tokens, nos temas claro e escuro, com contraste já medido. O objetivo é tirar do componente visual a responsabilidade de calcular cores — assim a mesma entrada produz sempre o mesmo resultado, o comportamento é testável isoladamente e a interface (fases seguintes) apenas consome e exibe.

A implementação segue as decisões de arquitetura já registradas (ADR-145 a ADR-166): identidade da marca preservada na seed exata, derivados sempre mapeados para sRGB, harmonia por deslocamento de matiz, composição de alpha sobre fundo explícito e classificação de contraste sem arredondamento.

## 🛠️ Mudanças Técnicas

- Cria o módulo `tools/layout-harmonizer/engine` com funções puras (sem Vue, DOM, i18n ou storage), exportadas por um `index.ts` único.
- `types.ts`: contrato do motor — 33 tokens em 5 famílias, temas `light`/`dark`, 4 estratégias de harmonia, proveniência do token (`seed`, `manual`, `rule`, `feedback`) e `LayoutEngineError` com códigos tipados.
- `params.ts`: centraliza a tabela numérica aprovada (superfícies, textos, feedback, variantes, estados, focus ring, candidatos de conteúdo e deslocamentos por estratégia), versionada por `LAYOUT_PARAMS_VERSION`.
- `tokens.ts`: catálogo ordenado de tokens e os 16 pares de contraste medidos (incluindo estados `hover`/`active` de marca, secundária e accent).
- `strategies.ts`: deslocamentos de matiz (análoga +30°, complementar +180°, triádica +240° na secundária) e Accent fixo em +120° em todas as estratégias, com normalização 0–360.
- `alpha.ts`: resolve a seed antes da geração — seed opaca é usada como está; seed com alpha exige fundo explícito e é composta (`compositeOver`), preservando o original.
- `generate.ts`: gera os dois temas preservando a seed exata no `primary`/`link`, derivando variantes e estados por passos de luminosidade e reduzindo croma da secundária a 85%.
- `contrast.ts`: mede cada par com a razão crua e delega a classificação AA/AAA ao núcleo de cor.
- Seed acromática (croma < 0.02) gera quadro neutro, sem inventar matiz.
- Testes em Vitest cobrindo: catálogo e versões, determinismo, seed exata, acromática, secundária manual, erros de entrada, alpha/fundo, gamut e clipping, deslocamentos de matiz e pares de contraste.
- Snapshot de fixtures congelando HEX, clipping e razões de contraste de 10 seeds × 4 estratégias × 2 temas (80 combinações).

## ⚠️ Impacto/Avisos

- **Sem migrações de banco, novas variáveis de ambiente ou novas dependências.** O motor é código puro e não faz I/O.
- **Contrato versionado:** `LAYOUT_SCHEMA_VERSION` e `LAYOUT_ALGORITHM_VERSION` acompanham o formato e a tabela. Qualquer alteração em `params.ts` que mude o resultado exige subir `LAYOUT_ALGORITHM_VERSION` e regenerar o snapshot de fixtures.
- **Snapshot é o contrato de regressão:** o arquivo `.snap` marca com `*` tokens que sofreram clipping de gamut e com `!` pares abaixo de AA. Os avisos de contraste são resultado esperado do motor (ex.: estados `hover`/`active` em tema claro); a decisão de exibir selo AA/AAA fica para a interface, não para o motor.
- **Seed com alpha passa a exigir fundo explícito:** não há fundo padrão por decisão de produto — chamadas sem `background` lançam `seed-alpha-without-background`.
- **Determinismo assumido como contrato:** os testes comparam duas execuções idênticas; introduzir qualquer fonte de aleatoriedade ou dependência de ambiente quebra a suíte.
- **Documentação extensa atualizada em paralelo** (SPEC, ADRs 145–166, PARAMETROS, TOKENS, TESTES e planos de fase) — revisar junto para garantir que os números da tabela documentada batem com `params.ts`.

close #164

---

[![GitPR](https://img.shields.io/badge/GitPR-0_errors_%C2%B7_4_warnings-yellow)](https://gitpr.natanfiuza.dev.br/)