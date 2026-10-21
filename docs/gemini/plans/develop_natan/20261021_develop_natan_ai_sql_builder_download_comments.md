# Plano — AI SQL Builder: Download do Resultado com Comentarios de Metadados

## 1. Objetivo
Adicionar os textos de **Premissas** (`assumptions`), **Avisos** (`warnings`) e **Tabelas usadas** (`tables_used`) formatados como bloco de comentários no topo do código ao acionar o botão de **Download** (`downloadOutput`) no `ai-sql-builder`.

## 2. Decisoes Alinhadas (/grill-with-docs)
- **Delimitadores por alvo:**
  - SQL (`.sql`), PHP (`.php`), TypeScript (`.ts`), Prisma (`.prisma`): bloco `/* ... */`
  - Python (`.py`): bloco com `#` linha a linha ou `""" ... """` (padronizado com `#` ou `"""`, sendo `# ` altamente portável para scripts).
- **Posição:** Topo do arquivo, seguido de uma quebra de linha dupla antes do código.
- **Seções vazias:** Omitidas se a lista correspondente estiver vazia. Se todas estiverem vazias, o código original é mantido intacto.
- **Internacionalização:** Títulos das seções usam as traduções existentes `ai.result.assumptions`, `ai.result.warnings` e `ai.result.tables`.
- **Botão Copiar:** Inalterado (copia apenas o código original sem os comentários, facilitando colar direto na IDE/editor).

## 3. Arquitetura e Implementacao

### 3.1. Utilitário de Formatação de Saída
Criar `resources/js/tools/ai-sql-builder/formatDownload.ts` contendo:
```typescript
interface FormatDownloadOptions {
    target: string;
    code: string;
    assumptions: string[];
    warnings: string[];
    tablesUsed: string[];
    labels: {
        assumptions: string;
        warnings: string;
        tables: string;
    };
}

export function formatDownloadContent(options: FormatDownloadOptions): string;
```
- Lógica pura, testável com Vitest sem necessidade de montar componentes Vue.
- Determina os delimitadores de comentário pelo `target` / extensão do arquivo.
- Formata cada lista com marcador (ex: `- item`).
- Junta com o código original `code`.

### 3.2. Integração no `ResultPanel.vue`
No componente `ResultPanel.vue`:
- Importar `formatDownloadContent`.
- Atualizar `downloadOutput()` para compor o texto formatado antes de chamar `downloadText`:
  ```typescript
  const content = formatDownloadContent({
      target: activeSlug.value,
      code: activeText.value,
      assumptions: props.result?.assumptions ?? [],
      warnings: props.result?.warnings ?? [],
      tablesUsed: props.result?.tables_used ?? [],
      labels: {
          assumptions: t('ai.result.assumptions'),
          warnings: t('ai.result.warnings'),
          tables: t('ai.result.tables'),
      },
  });
  ```

### 3.3. Testes Automatizados
- Criar `resources/js/__tests__/ai-sql-builder-download.test.ts` (ou adicionar em `ai-sql-builder.test.ts`) cobrindo:
  - Formatação com SQL (bloco `/* ... */`).
  - Formatação com Python (`#`).
  - Formatação com PHP e TypeScript.
  - Omissão de seções vazias.
  - Caso onde não há metadados (nenhuma alteração no código).
  - Teste de integração simulando o clique em `downloadOutput` no `ResultPanel`.

## 4. Checklist de Verificação
- [ ] `npm run test` (todos os testes passando sem regressão)
- [ ] `npm run types:check` (sem erros de TypeScript)
- [ ] `npm run lint` e `npm run format`
- [ ] `composer test`
- [ ] Geração do relatório final em `docs/gemini/reports/develop_natan/...`
