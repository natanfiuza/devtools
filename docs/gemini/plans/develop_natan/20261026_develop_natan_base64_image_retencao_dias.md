# Plano de Implementação — Exibição de Retenção em Dias no Base64 Image

- **Data:** 2026-10-26
- **Branch:** `develop_natan`
- **Tarefa:** `base64_image_retencao_dias`
- **Survey de Referência:** `docs/survey/20261026_base64_image_retencao_dias_surveyfacts.md`

## 1. Contexto e Objetivo
Ao converter uma imagem no componente Base64 Image Encoder (`EncoderPanel.vue`), o rodapé exibe o período de retenção do histórico. No plano pago, o backend injeta `retentionHours: 720`, resultando na mensagem `"Histórico disponível por 720h"`.
O objetivo é exibir o tempo em dias quando a duração for superior a 24 horas (ex.: `"Histórico disponível por 30 dias"`), mantendo a exibição em horas quando for menor ou igual a 24 horas (ex.: `"Histórico disponível por 24h"`).

## 2. Detalhamento das Alterações

### A. Internacionalização (`lang/{en,es,fr,pt_BR,pt_PT}/tools.php`)
Adicionar as chaves `retention_info_day` e `retention_info_days` em `tools.base64-image` nos 5 arquivos de idioma:

1. **`lang/pt_BR/tools.php`**:
   - `'retention_info_day' => 'Histórico disponível por {days} dia',`
   - `'retention_info_days' => 'Histórico disponível por {days} dias',`
2. **`lang/pt_PT/tools.php`**:
   - `'retention_info_day' => 'Histórico disponível por {days} dia',`
   - `'retention_info_days' => 'Histórico disponível por {days} dias',`
3. **`lang/en/tools.php`**:
   - `'retention_info_day' => 'History retained for {days} day',`
   - `'retention_info_days' => 'History retained for {days} days',`
4. **`lang/es/tools.php`**:
   - `'retention_info_day' => 'Historial disponible por {days} día',`
   - `'retention_info_days' => 'Historial disponible por {days} días',`
5. **`lang/fr/tools.php`**:
   - `'retention_info_day' => 'Historique conservé pendant {days} jour',`
   - `'retention_info_days' => 'Historique conservé pendant {days} jours',`

### B. Função Utilitária `formatRetentionTime` (`resources/js/tools/base64-image/lib/formatters.ts`)
Adicionar e exportar a função de formatação de retenção:
```ts
export function formatRetentionTime(
    hours: number,
    t: (key: string, params?: Record<string, unknown>) => string,
): string {
    if (hours <= 24) {
        return t('tools.base64-image.retention_info', { hours });
    }

    const days = Math.round(hours / 24);
    if (days === 1) {
        return t('tools.base64-image.retention_info_day', { days });
    }

    return t('tools.base64-image.retention_info_days', { days });
}
```

### C. Atualização do Componente (`resources/js/tools/base64-image/EncoderPanel.vue`)
Importar `formatRetentionTime` de `./lib/formatters` e substituir a linha 298:
```html
<span>
    {{ formatRetentionTime(toolProps?.retentionHours ?? 24, t) }}
</span>
```

### D. Testes Unitários e de Integração Frontend
1. **`resources/js/__tests__/base64-image-formatters.test.ts`**:
   - Testar `formatRetentionTime`:
     - Retorno com 1h, 12h, 24h utilizando chave `retention_info`.
     - Retorno com 25h..36h (arredondado para 1 dia se `Math.round(hours / 24) === 1`) utilizando chave `retention_info_day`.
     - Retorno com 48h (2 dias) e 720h (30 dias) utilizando chave `retention_info_days`.
2. **`resources/js/__tests__/base64-image-component.test.ts`**:
   - Atualizar o dicionário mock de traduções para incluir `retention_info_day` e `retention_info_days`.
   - Adicionar asserções de renderização de resultado convertido no cenário Free (garantindo que exibe `24h`) e no cenário Pago (garantindo que exibe `30 dias`).

## 3. Critérios de Aceite e Verificação
- [ ] `npm run test` (todos os testes do frontend passando com Vitest).
- [ ] `npm run types:check` (verificação estrita de TypeScript sem erros).
- [ ] `composer test` (Pint, PHPStan e Pest passando sem erros).
- [ ] Relatório final salvo em `docs/gemini/reports/develop_natan/20261026_develop_natan_base64_image_retencao_dias.md`.
