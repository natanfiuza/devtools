# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: conclui F6 do Layout Harmonizer e amplia logos SVG
```

---

## 🎯 Resumo
Consolida a F6 do Layout Harmonizer e amplia o suporte a logos SVG no BadgeForge. A mudança permite temas com cor secundária manual translúcida composta sobre fundo opaco, evolui o motor de geração para a versão 2 com controle de croma e torna a experiência de edição/regeneração mais previsível. Também padroniza a formatação dos componentes Vue para reduzir ruído de diff, sem alterar comportamento.

## 🛠️ Mudanças Técnicas
- **Layout Harmonizer F6:** adiciona suporte a secundária manual com alpha e fundo de composição próprio, validação de fundo opaco, snapshot da secundária e export com valores original/efetivo/fundo; mantém compatibilidade com estados legados sem canal alpha.
- **Motor v2:** atualiza `LAYOUT_ALGORITHM_VERSION` e `LAYOUT_PARAMS_VERSION` para 2, aplica tetos de croma em superfícies/textos (`C ≤ 0,012` no claro e `≤ 0,020` no escuro) e ajusta `VARIANT_DELTA` para `0.12`, com snapshots e ratios de contraste revalidados dentro da meta AA.
- **Estado e UX:** regeneração disponível em quadro congelado com diálogo informando edições sem bloqueio que serão descartadas; descarte explícito do rascunho anônimo; fechamento da importação não descarta mais o rascunho; confirmação inline de restauração e `useId` no editor de token.
- **BadgeForge/SVG:** parser aceita primitivas `rect`, `circle`, `ellipse`, `line`, `polyline` e `polygon`, convertendo-as em `path` normalizado; ignora metadados benignos, rejeita `style`/`transform`, valida números e propaga o elemento recusado no erro 422. O catálogo passa a ler múltiplos `path` com `fill-rule`/`clip-rule`, e o novo `LogoSelect` centraliza a seleção de logos.
- **i18n e rotas:** adiciona mensagens de validação/estado em en/es/fr/pt_BR/pt_PT e usa `Locales::slug()` para alinhar locales de rota, evitando divergência entre `pt_BR` e `pt-br`.
- **Infra/config:** inclui variáveis `COLORS_LAYOUT_SYSTEM_*` e `COLORS_TAGS_PER_LAYOUT_SYSTEM`, além do bump da versão do algoritmo em `config/colors.php`.
- **Testes:** cobre secundária com alpha, semântica fixa do quadro, regeneração, rascunho, acessibilidade, parser SVG e restauração de slug; adiciona teste de concorrência opt-in com MySQL/MariaDB e eleva `testTimeout` do Vitest para 20s.
- **Formatação:** reindentação de templates Vue de 4 para 2 espaços, quebra de atributos e normalização de tags void, sem alteração de props, rotas, i18n, `data-test` ou regras de negócio.

## ⚠️ Impacto/Avisos
- **Negócio:** melhora a flexibilidade dos temas, a qualidade visual dos logos personalizados e a segurança da UX ao regenerar/descartar rascunhos; a formatação é cosmética e sem impacto funcional esperado.
- **Compatibilidade:** o estado do layout permanece retrocompatível, mas o bump do algoritmo para v2 pode alterar pequenas cores e snapshots existentes; revalidar temas salvos e expectativas visuais.
- **Ambiente:** exige configuração das novas variáveis `COLORS_LAYOUT_SYSTEM_*`/`COLORS_TAGS_PER_LAYOUT_SYSTEM`; atenção ao comportamento documentado de `(int) ''`.
- **Banco/dependências:** não há migrações de banco nem novas dependências. O teste de concorrência é opt-in e requer MySQL/MariaDB com banco `*_test`.
- **Validação:** executar lint/build e testes E2E, especialmente pela normalização de tags void e pelos seletores `data-test` preservados.

close #174

---

[![GitPR](https://devtools.natanfiuza.dev.br/api/badges/static/GitPR/3_errors_%C2%B7_43_warnings.svg?color=red&logo=gitpr)](https://gitpr.natanfiuza.dev.br/)