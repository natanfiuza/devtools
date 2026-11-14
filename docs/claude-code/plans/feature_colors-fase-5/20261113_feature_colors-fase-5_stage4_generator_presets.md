# Plano — Fase 5, Etapa 4: presets do gerador

- Data: 2026-11-13
- Branch: `feature/colors-fase-5`
- Pré-requisito: Etapas 1–3 concluídas e verdes.
- Aprovação: inclusão de presets aprovada na Fase 5 (ADR-093); o usuário
  autorizou a Etapa 4 com "pode seguir".

## Objetivo

Configurações reutilizáveis de geração, salvas pela conta e aplicadas com um
clique no Color Palette Generator:

- preset guarda **entradas** da geração (nome + cor base) e nada derivado: a
  escala e as harmonias continuam vindo do algoritmo, então preset não é uma
  paleta salva nem aparece em Minhas Paletas;
- lista, criação, renomeação e exclusão são da conta, com nome único por conta;
- preset não consome a cota de paletas, não tem URL pública, OG nem "uso";
- o anônimo continua sem presets e sem requisição (CLAUDE.md); aplicar um
  preset troca a base do workspace no navegador e registra no histórico.

## Decisão de domínio

`ADR-093` descreve preset como "cor base, escala, harmonia, papéis, tema". No
gerador atual a escala e a harmonia são **derivadas** da base pelo algoritmo, e
papéis/tema são um retrato da paleta no editor (ADR-126). Por isso o preset
grava apenas o que é configuração de entrada — a cor base — e reproduz o resto
pela versão vigente do algoritmo. Isso mantém a fronteira explícita com a
paleta: paleta persiste cores efetivas, preset persiste a entrada.

## Dados

- Migration `2026_11_13_000002_create_color_presets_table.php` (só criação de
  tabela): `ulid` PK, `user_id` com FK em cascata, `name` (limite da config),
  `base` (HEX6/HEX8), `timestamps`, unique `[user_id, name]` e índice
  `[user_id, updated_at]`.
- `App\Models\ColorPreset` (`HasUlids`, `#[Fillable]`, `scopeForUser`,
  `user()`) e `User::colorPresets()`.
- `database/factories/ColorPresetFactory.php`.
- `App\Support\Colors\ColorPresetQuota` (`limit`, `used`, `reached`,
  `summary`); `limit = null` (ilimitado) por padrão, configurável.
- `config/colors.php`: bloco `presets` (`name_max_length`, `limit`), sem
  números fixos no código.

## Contrato HTTP (JSON)

Dentro do grupo `api/tools/colors`, autenticado:

```
GET    /api/tools/colors/presets                     -> lista + limites
POST   /api/tools/colors/presets                     -> cria (nome + base)
PATCH  /api/tools/colors/presets/{colorPreset}       -> renomeia
DELETE /api/tools/colors/presets/{colorPreset}       -> exclui
```

- `GET` devolve `{data: [{id, name, base, created_at, updated_at}], limits:
  {limit, used}}`, ordenado por `updated_at` desc e `id` desc.
- `POST` valida `name` (obrigatório, limite da config, único na conta após
  trim) e `base` (`PaletteRules::HEX`, normalizada em minúsculas); devolve
  `{preset}` com 201. O limite configurado, se existir, responde 422.
- `PATCH` só renomeia (`name`); preset de outra conta/desconhecido responde
  404, nunca 403.
- `DELETE` devolve `{deleted: true}`; idem 404 para recurso alheio.
- Escritas sob `throttle:colors-library`; leitura sem throttle próprio, como
  histórico/favoritos.
- `CreateColorPreset` trava a linha do usuário e checa a cota; colisão de nome
  em corrida vira 422 (`preset_name_taken`).
- Preset nunca cria linha em `color_palettes`.

## Cliente

- `resources/js/lib/colors/api.ts`: aceita `PATCH`.
- `types/colors.ts`: `ColorPreset`, `ColorPresetList` e `ColorPresetLimits`.
- `components/colors/PresetDialog.vue`:
  - carrega a lista ao abrir (`GET`), com estados de carregando/vazio/erro;
  - formulário "salvar a base atual" (nome validado no cliente e no servidor;
    duplicata conhecida é avisada antes do envio; limite desabilita e avisa);
  - cada linha mostra amostra + HEX, data e ações **Usar**, **Renomear**
    (inline) e **Excluir** (confirmação inline);
  - "Usar" emite `apply` com o preset e fecha; erros de escrita ficam inline;
  - o texto deixa claro que preset não é paleta e não entra em Minhas Paletas.
- `tools/palette-generator/GeneratorWorkspace.vue`: expõe `baseHex()` e
  `applyBaseHex()` (parse + `useBase`, portanto registra no histórico).
- `tools/palette-generator/Tool.vue`: botão **Presets** só para conta logada
  (ao lado de "Salvar"), diálogo com a base atual; aplicar fecha o diálogo,
  troca a base e avisa no rodapé `role="status"`.
- i18n `colors.presets.*` e mensagens de validação de preset em en, pt-BR,
  pt-PT, fr e es. Nenhuma string visível fora do i18n.

## Testes

- Novo `tests/Feature/Colors/ColorPresetHttpTest.php`: visitante 401; criação
  normaliza nome/base (HEX6 e HEX8), recusa formatos inválidos e nome vazio/
  longo; nome único por conta e livre entre contas; renomear para nome
  existente 422; listagem só da conta, com ordem e `limits`; renomear/excluir
  próprios funcionam; recursos alheios/desconhecidos 404; limite configurado
  responde 422 e libera após excluir; preset não cria paleta; throttle.
- Novo `resources/js/__tests__/palette-presets.test.ts`: abertura carrega a
  lista, vazio, criação (POST), duplicata local, renomeação (PATCH), exclusão
  (DELETE), "Usar" emite `apply`, limite desabilita, base vazia desabilita e
  falhas de leitura/escrita mostram aviso.
- `palette-generator.test.ts`: botão ausente para visitante e presente para
  conta logada; aplicar um preset muda a base da escala e avisa.

## Verificação

1. `php artisan test --filter=ColorPreset`
2. `php artisan test --filter=Colors`
3. `composer test`
4. `npm run test`
5. `npm run types:check`
6. `npm run lint:check`
7. `npm run build`

## Fora desta etapa

- Aplicar preset no editor e capturar papéis/tema (permanecem na paleta,
  ADR-126). Exportação/importação de design tokens (Etapas 5–6), ADRs/docs
  finais (Etapa 7).
