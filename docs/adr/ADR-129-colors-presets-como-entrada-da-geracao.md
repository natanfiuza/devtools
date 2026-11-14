# ADR-129 - Presets como entrada da geração
Status: Aceito
Data: 2026-11-14
Relacionados: ADR-093 (presets, emenda), ADR-096 (cota de paletas), ADR-123 (persistência de paletas)

## Contexto
A ADR-093 definiu preset como “configuração salva reutilizável (cor base, escala, harmonia, papéis, tema)”. Na prática, a escala é sempre recalculada da base (ADR-120), as harmonias são auxiliares (ADR-121) e papéis/tema são metadados da paleta no editor (ADR-126). Guardar tudo isso no preset criaria uma segunda forma de paleta — com cópia de cores, risco de divergência e confusão com a cota de Minhas Paletas.

A F5 precisava de um atalho reutilizável para o gerador: salvar uma configuração pequena da conta e reaplicá-la depois.

## Decisão

### 1. Preset é `{name, base}`, nunca as cores derivadas
`color_presets` guarda `user_id`, `name` (até `presets.name_max_length`, 60 por padrão), `base` (HEX6/HEX8) e timestamps, com `unique(user_id, name)`. Escala, harmonias, papéis e tema **não** são persistidos: reaplicar o preset define a base e o gerador recalcula a escala com a versão atual do algoritmo. Assim o preset continua sendo entrada, não retrato.

### 2. Base é imutável no mesmo preset
`UpdateColorPresetRequest` aceita apenas `name`. Para mudar a cor, o usuário cria outro preset ou aplica uma nova base e salva de novo. Isso evita que um preset “mude de identidade” enquanto está sendo usado em outra aba.

### 3. Preset pertence à conta e não consome cota de paleta
As rotas `GET/POST/PATCH/DELETE /api/tools/colors/presets` ficam sob conta ativa, leitura sem throttle e escrita em `throttle:colors-library`. Preset não entra em Minhas Paletas, não tem slug, não aparece no catálogo público, não conta “Uso” e não passa pela cota de paletas (ADR-096).

### 4. Teto operacional opcional, nunca fixo no código
`config/colors.php` expõe `presets.limit` (`COLORS_PRESETS_LIMIT`), com default `null` = sem limite funcional. `ColorPresetQuota` lê o teto, e `CreateColorPreset` trava a linha do usuário antes de contar, em transação, para duas abas não ultrapassarem o limite. Nome repetido vira erro de validação (`preset_name_taken`), não 500.

### 5. Aplicar é trocar a base
No gerador, o diálogo de presets parte da base atual; escolher um preset aplica `base` ao workspace e mostra o aviso `colors.presets.applied`. O histórico da conta registra a troca como qualquer outra base (ADR-127). Nada é salvo automaticamente: persistir continua sendo uma ação explícita em Minhas Paletas.

### 6. Presets só existem para quem tem conta
Visitante não vê o botão nem faz requisição. O preset não substitui o rascunho anônimo (ADR-123) nem cria um modo anônimo de configuração.

## Consequências
- O atalho é pequeno, determinístico e nunca fica defasado em relação ao algoritmo: a escala é sempre recalculada.
- Não há duplicação de cores nem competição com a cota de paletas.
- Renomear é seguro; trocar a cor exige um preset novo, o que mantém a identidade do atalho.
- O limite é operacional e ajustável por ambiente; o default não impõe teto funcional.

## Alternativas rejeitadas
- **Guardar a escala calculada no preset**: duplicaria as cores, envelheceria com a versão do algoritmo e se aproximaria de uma paleta sem os controles dela.
- **Guardar harmonias/papéis/tema**: essas escolhas pertencem à paleta salva e ao editor (ADR-126); no gerador não existe tema nem papel persistido.
- **Reutilizar a tabela de paletas com `kind=preset`**: misturaria cota, slug, visibilidade e “Uso” com uma configuração privada de geração.
- **Permitir editar a base no PATCH**: mudaria o significado do preset em uso e criaria corrida com abas abertas; criar outro é mais simples.
- **Preset anônimo**: o requisito é organização da conta; o anônimo já tem histórico local e rascunho.
