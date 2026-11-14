# Relatório — Fase 5, Etapa 4: presets do gerador

- Data: 2026-11-13
- Branch: `feature/colors-fase-5`
- Tarefa: presets reutilizáveis do Color Palette Generator (ADR-093), por conta,
  sem confundi-los com paletas salvas
- Plano: `docs/claude-code/plans/feature_colors-fase-5/20261113_feature_colors-fase-5_stage4_generator_presets.md`

## Decisão de domínio

A ADR-093 lista base, escala, harmonia, papéis e tema como conteúdo de um
preset. No gerador atual a escala e as harmonias são **derivadas** da base pelo
algoritmo, e papéis/tema são metadados da paleta no editor (ADR-126). Para não
duplicar uma paleta, o preset guarda **apenas a entrada da geração**:

- `name` (único por conta, renomeável);
- `base` (HEX6 ou HEX8, imutável).

Aplicar um preset repõe a base; a escala/harmonia voltam a ser calculadas pelo
algoritmo vigente e a troca entra no histórico da biblioteca. Um preset não tem
visibilidade, slug, OG, contador de uso nem consome a cota de paletas — é o que
mantém preset e paleta salva como coisas distintas.

## Entregue

### Servidor

- Migration `2026_11_13_000002_create_color_presets_table.php`:
  `ulid('id')->primary()`, FK `user_id` em cascata, `name` 60, `base` 9,
  `timestamps()`, unique `(user_id, name)` e índice `(user_id, updated_at)`.
  Só cria tabela — retrocompatível, sem downtime.
- `App\Models\ColorPreset` (`HasUlids`, `#[Fillable]`, `user()`, `scopeForUser`)
  e `User::colorPresets()`.
- `database/factories/ColorPresetFactory.php`.
- `App\Support\Colors\ColorPresetQuota`: teto opcional de
  `config('colors.presets.limit')` (`null` = sem limite funcional), contagem por
  conta e resumo `{limit, used}`.
- `StoreColorPresetRequest`: apara o nome, normaliza a base para minúsculas,
  nome único por conta e base em `PaletteRules::HEX` (HEX6/HEX8).
- `UpdateColorPresetRequest`: renomeação; nome único ignorando o próprio
  registro. A base não é aceita na alteração.
- `App\Actions\Colors\CreateColorPreset`: trava a linha do usuário antes da
  contagem (como na criação de paleta), converte corrida de nome único em erro
  de validação e recusa acima do teto com `validation.preset_limit_reached`.
- `App\Http\Controllers\Api\Colors\ColorPresetController`:
  - `GET /api/tools/colors/presets` → `{data, limits, form}`; lista da conta,
    `updated_at desc, id desc`; `form.name_max_length` vem da config para o
    cliente não fixar limite;
  - `POST /api/tools/colors/presets` → `201 {preset}`;
  - `PATCH /api/tools/colors/presets/{colorPreset}` → `{preset}` (só nome);
  - `DELETE /api/tools/colors/presets/{colorPreset}` → `{deleted: true}`;
  - 401 sem conta; recurso de outra conta ou ULID desconhecido em 404 com
    `error.code = not_found`; escrita sob `throttle:colors-library`.
- `routes/colors.php`: quatro rotas nomeadas
  `api.tools.colors.presets.index|store|update|destroy`, com `whereUlid`.
- `config/colors.php`: bloco `presets` com `name_max_length` (60) e `limit`
  (`null`), ambos por `.env`.

### Cliente

- `lib/colors/api.ts`: `PATCH` incluído no `ColorsApiMethod`.
- `types/colors.ts`: `ColorPreset`, `ColorPresetLimits` e `ColorPresetList`
  (`data`, `limits`, `form`).
- `components/colors/PresetDialog.vue`: carrega a lista ao abrir (estados de
  carregando, erro com "tentar de novo" e vazio), cria a partir da base atual,
  renomeia inline (o preset volta ao topo, como no servidor), exclui com
  confirmação inline, aplica emitindo a base, desabilita o que o servidor
  recusaria (sem base, nome vazio/duplicado sem diferenciar caixa, teto
  atingido) e explicita que preset não é paleta.
- `GeneratorWorkspace.vue`: expõe `baseHex()` e `applyBaseHex()`; aplicar usa a
  mesma porta de escrita da base (`setColor` + `finalize`), registrando no
  histórico.
- `tools/palette-generator/Tool.vue`: botão "Presets" só para quem tem conta,
  diálogo montado somente quando aberto, aplicação fecha o diálogo, troca a base
  e avisa via `workspace.notify`.
- i18n: `colors.presets.*` e
  `colors.validation.preset_name_required|preset_name_max|preset_name_taken|preset_base_required|preset_base_invalid|preset_limit_reached`
  em en, pt-BR, pt-PT, fr e es. Nenhuma string visível hardcoded.

## Testes

- `tests/Feature/Colors/ColorPresetHttpTest.php` (novo, 13 testes): visitante
  401 nas quatro rotas; criação normaliza nome/base e não cria paleta; HEX8;
  nome vazio/longo e base fora do HEX → 422; nome único por conta e repetível
  em outra conta; lista só da própria conta, ordem e `limits`/`form`; teto
  configurado publicado; renomear sem alterar a base; conflito de nome no
  rename; 404 para preset alheio/desconhecido; exclusão; teto liberado após
  excluir; limiter de escrita.
- `resources/js/__tests__/palette-presets.test.ts` (novo, 7 testes): carga ao
  abrir, criação com a base atual, bloqueio por duplicado/teto, aplicar emite e
  fecha, exclusão só após confirmação, rename traz para o topo e erro de leitura
  com retry.
- `palette-generator.test.ts`: dicionário i18n de presets; botão só para
  autenticado; aplicar um preset troca a base, fecha o diálogo e mostra
  "Preset aplicado.".

Nota de implementação: o diálogo é montado apenas quando aberto (`v-if`). Manter
um segundo `Dialog` fechado no mesmo DOM quebrava a limpeza entre testes
(Reka/Vue) no jsdom; montar sob demanda elimina o problema sem mudar o
comportamento.

## Comandos executados e resultados

| Comando | Resultado |
| --- | --- |
| `php artisan test --filter=ColorPreset` | 13 testes, 82 asserções — verde |
| `php artisan test --filter=Colors` | 100 testes, 712 asserções — verde |
| `composer test` | Pint ok; PHPStan 0 erros; Pest 1572 testes / 27937 asserções — verde (1 deprecação em vendor/Pest) |
| `npm run test` | 113 arquivos, 1387 testes — verde |
| `npm run types:check` | `wayfinder:generate` + `vue-tsc --noEmit` — verde |
| `npm run lint:check` | 0 erros; apenas avisos de indentação já presentes no padrão dos templates |
| `npm run build` | build concluído (apenas avisos de tamanho de chunk/plugins) |
| `php artisan route:list --path=api/tools/colors` | quatro rotas de preset com os nomes e métodos esperados |

## Pendências

- Nenhuma para esta etapa.
- Seguem: exportação de design tokens (5), importação validada (6) e ADRs/docs
  finais (7).
- Sem commit/push, conforme a regra do projeto.
