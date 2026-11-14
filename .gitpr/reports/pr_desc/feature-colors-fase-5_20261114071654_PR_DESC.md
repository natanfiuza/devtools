# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: adiciona presets do gerador de paletas por conta
```

---

## 🎯 Resumo

O gerador de paletas não permitia reaproveitar uma configuração de cor base entre sessões: para repetir uma combinação, o usuário precisava redigitar o HEX. Esta entrega cria os **presets do gerador** (F5, ADR-093), que guardam apenas a entrada de geração (nome + cor base) de forma privada por conta, permitindo aplicar a base com um clique e regenerar a escala pelo algoritmo vigente.

A decisão central é que preset **não é paleta**: não tem URL pública, visibilidade, contador de uso nem consome a cota de paletas. Isso evita confundir os limites comerciais do produto e mantém o catálogo público inalterado.

## 🛠️ Mudanças Técnicas

- Cria o model `ColorPreset` (ULID), migration `color_presets` com chave única `(user_id, name)`, índice `(user_id, updated_at)` e FK com `cascadeOnDelete`, além de factory para testes.
- Adiciona `User::colorPresets()` e o scope `forUser` para isolar a leitura por conta.
- Implementa o CRUD em `ColorPresetController` (`GET/POST/PATCH/DELETE /api/tools/colors/presets`), com `index` retornando `data`, `limits` e `form.name_max_length` vindos da config — o cliente não fixa tetos.
- Extrai a criação para a action `CreateColorPreset`, que trava a linha do usuário (`lockForUpdate`) antes de contar, impedindo que duas abas simultâneas furem o teto; corrida de nome único (`UniqueConstraintViolationException`) vira erro de validação 422, não 500.
- Adiciona `ColorPresetQuota` com limite configurável (padrão `null` = sem limite funcional).
- FormRequests `StoreColorPresetRequest`/`UpdateColorPresetRequest`: normaliza nome (trim) e base (trim + lowercase), valida HEX6/HEX8, unicidade por conta e, no update, base permanece imutável.
- Recurso de outra conta responde **404** (não 403), evitando vazamento de existência.
- Front: novo `PresetDialog.vue` (listar, criar, renomear, excluir com confirmação, aplicar), integrado ao `Tool.vue` e exposto via `applyBaseHex` no `GeneratorWorkspace`, que repõe a base no histórico do gerador.
- Adiciona o método `PATCH` ao `ColorsApiMethod` e o tipo `ColorPreset` em `types/colors.ts`.
- Traduções completas em `en`, `es`, `fr`, `pt_BR` e `pt_PT` (UI e mensagens de validação).
- Cobertura: testes de feature (`ColorPresetHttpTest`) para autorização, normalização, unicidade por conta, ordenação, cota, limiter e 404; testes de componente em `palette-presets.test.ts` e integração em `palette-generator.test.ts`.

## ⚠️ Impacto/Avisos

- **Banco de dados**: nova migration `2026_11_13_000002_create_color_presets_table` cria apenas uma tabela nova; nada existente é alterado. Requer `php artisan migrate`.
- **Variáveis de ambiente**: novas chaves opcionais `COLORS_PRESET_NAME_MAX_LENGTH` (padrão 60) e `COLORS_PRESETS_LIMIT` (padrão sem limite). Sem elas, o comportamento é ilimitado e o nome limitado a 60 caracteres.
- **API**: quatro novos endpoints autenticados em `/api/tools/colors/presets`; leitura é pública-para-logados, escrita passa pelo limiter existente (`colors.rates.library_per_minute`).
- **Sem impacto em cotas de paletas**: presets não contam no limite de paletas nem aparecem em Minhas Paletas.
- **Dependências**: nenhuma adicionada; apenas reuso de componentes de UI existentes.

close #151

---

[![GitPR](https://img.shields.io/badge/GitPR-0_errors_%C2%B7_27_warnings-yellow)](https://gitpr.natanfiuza.dev.br/)