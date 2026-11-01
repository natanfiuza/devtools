# ADR-074: Estilos — cinco formas de uma mesma geometria

Status: Aceito
Data: 2026-10-31
Relacionados: ADR-072 (nome e escopo), ADR-075 (render PHP e espelho TS)

## Contexto

O público de badges reconhece um pequeno conjunto de estilos: o chapado
arredondado, o quadrado, o plástico com brilho, o "for the badge" em caixa alta e
o "social" dos links de perfil. Faltar com qualquer um deles quebra a
compatibilidade visual que justifica a ferramenta.

A geometria não pode morar em dois lugares: o servidor gera o SVG de verdade e o
navegador desenha o preview. Se altura, padding, fonte ou gradiente divergirem,
o preview mente.

## Decisão

1. **Cinco estilos fixos**: `flat` (padrão), `flat-square`, `plastic`,
   `for-the-badge` e `social`. Estilo desconhecido cai em `flat`; não há 400.
2. **A geometria é dado, não código**: altura, raio, padding, vão do logo, fonte,
   peso, tamanho, letter-spacing, baseline, gradientes e cores fixas vivem em
   `resources/badgeforge/styles.json`, lido pelo PHP (`StyleCatalog`) e
   importado pelo TypeScript. Nenhum estilo tem números duplicados no renderer.
3. **Diferenças intencionais entre estilos**:
   - `flat`: 20 px, raio 3, padding 6, gradiente branco→preto translúcido;
   - `flat-square`: igual ao flat, sem raio e sem gradiente;
   - `plastic`: raio 3, gradiente mais forte e sombra inferior de 1 px;
   - `for-the-badge`: 28 px, sem raio, 10 px em caixa alta, peso 700,
     letter-spacing 1 px, logo 14 px e padding 10;
   - `social`: 20 px, raio 3, padding 5, fonte Helvetica e **cores fixas**
     (`#555555` no label e `#007ec6` na message), que ignoram `color` e
     `labelColor`.
4. **As métricas de texto acompanham o estilo**: `font-family` visível e a
   família usada para medir (`metrics_family`) vêm do mesmo registro.
5. **Logo**: tamanho padrão por estilo (`logo_size`), sempre sobrescrito por
   `logoSize` dentro do clamp global de 8–20 px.

## Consequências

### Positivas
- Um lugar só para mudar geometria; preview e servidor leem o mesmo JSON.
- Fixtures-ouro cobrem os cinco estilos e quebram na primeira divergência.
- A compatibilidade visual com o público de README é preservada.

### Negativas / Trade-offs
- O `styles.json` vira contrato público de fato: mudanças regravam fixtures.
- Estilos futuros (ex.: `flat-square` com gradiente) exigem revisão dos
  testes de paridade, não só uma linha de JSON.
- O estilo `social` ignora duas cores na URL, o que surpreende quem tenta
  sobrescrevê-las; a UI e a documentação dizem isso explicitamente.
