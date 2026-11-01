# ADR-075: Render em PHP e espelho TypeScript para o preview

Status: Aceito
Data: 2026-10-31
Relacionados: ADR-002 (processamento local), ADR-072 (nome e escopo), ADR-074 (estilos)

## Contexto

O SVG que vale é o que o servidor entrega na URL. A página da ferramenta, porém,
precisa mostrar o badge enquanto o usuário digita. Havia três caminhos:

1. **Preview chamando o endpoint**: uma requisição por tecla, contra a regra de
   que as tools do cliente rodam 100% no navegador, e um atraso visível.
2. **Preview aproximado em HTML/CSS**: aparência divergente do SVG real — o
   preview mentiria sobre largura, corte de texto e gradiente.
3. **Espelho TypeScript do renderer**: o mesmo algoritmo em duas linguagens, com
   a mesma entrada e a mesma saída, comparados byte a byte.

A opção 3 foi escolhida. O custo é manter duas implementações; o benefício é um
preview instantâneo, offline e exatamente igual à imagem publicada.

## Decisão

1. **A autoridade é o PHP.** `App\Support\BadgeForge\StaticBadgeRenderer::render()`
   gera o documento. O TypeScript (`resources/js/tools/badgeforge/lib/render.ts`)
   é cópia comportamental, não fonte.
2. **Mesma entrada**: os dois lados recebem o `BadgeSpec` já normalizado
   (label, message, estilo, cores resolvidas, logo, posição, link, TTL). A
   normalização (decode do texto, truncagem, fallbacks, clamps) é espelhada em
   `BadgeRequest` (PHP) e `builder.ts` (TS).
3. **Mesmos ativos**: `styles.json`, `font-metrics.json` e `colors.json` em
   `resources/badgeforge/` são lidos pelo PHP e importados pelo TypeScript. Os
   SVGs de logo são lidos do disco no servidor e importados cru via
   `import.meta.glob` no cliente.
4. **Mesma saída, byte a byte**: documento de uma linha, atributos na mesma
   ordem, mesmos separadores e mesmas casas decimais. As 21 fixtures de
   `tests/Fixtures/badgeforge/` são comparadas pelo Pest (renderer PHP) e pelo
   Vitest (espelho TS).
5. **Armadilhas de paridade tratadas explicitamente**:
   - truncar e iterar por **code points** (`mb_str_split`/`Array.from`), nunca
     por bytes ou unidades UTF-16;
   - `intdiv` do PHP ↔ `Math.trunc(a / b)`;
   - `round()` do PHP ↔ `Math.round`;
   - alpha formatado com até 3 decimais sem zeros à direita;
   - escape XML na ordem `&`, `<`, `>`, `"`, `'` → `&#039;`;
   - chaves snake_case dos JSONs de ativos.
6. **Nenhuma requisição do preview**: a página é 100% navegador (ADR-002); o
   endpoint só é exercitado pelo consumidor final do badge.
7. **Mudança no renderer é mudança dupla**: PHP, TS e fixtures juntos no mesmo
   commit; a fixture que não bate é o aviso.

## Consequências

### Positivas
- Preview instantâneo e offline, sem custo de servidor por tecla.
- O preview e a imagem publicada não divergem — há teste para isso.
- O catálogo (estilos, logos, limites, endpoint) chega numa prop só
  (`BadgeForgeToolProps`) e um fixture comum impede divergência de defaults.

### Negativas / Trade-offs
- Duas implementações para manter; cada correção exige disciplina de espelho.
- A paridade byte a byte engessa formatação: refatorar o template exige regravar
  fixtures.
- Bibliotecas de fonte do navegador não são consultadas; a medição é por tabela
  própria, com pequena folga visual aceita.
