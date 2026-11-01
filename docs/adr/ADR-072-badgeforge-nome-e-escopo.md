# ADR-072: BadgeForge — nome e escopo

Status: Aceito
Data: 2026-10-31
Relacionados: ADR-029 (página genérica da tool), ADR-071 (documentação pública), ADR-073 (formatos)

## Contexto

O site tem ferramentas de navegador para JSON, SQL, regex, tempo, texto e
imagens. Badges para README são um caso diferente: o produto não é a tela, é a
**imagem servida por URL** que um README externo carrega. A tela existe para
montar a URL e copiar o snippet; a imagem precisa funcionar sem JavaScript, sem
sessão e sem interação.

O mercado já tem o Shields.io como referência de formato visual (flat, logo,
cores no caminho e na query) e de contrato mental do usuário. A opção de
integrar o Shields como dependência foi descartada: seria serviço externo no
caminho crítico de uma imagem, com licença e disponibilidade de terceiros.

O nome "BadgeForge" (forja de badges) foi escolhido no registro inicial e é
mantido; o escopo precisa ser explícito para não crescer além das fases.

## Decisão

1. **Nome**: a ferramenta se chama **BadgeForge**, slug `badgeforge`, categoria
   `badges` ("Imagens"/"Images"), página `/{locale}/tools/badgeforge`.
2. **Escopo**: badges estáticas em SVG geradas e servidas pelo próprio devtools,
   a partir de label, message, estilo, cores, logo embutido, posição do logo e
   link. Cada badge é descrito inteiramente pela URL.
3. **Fronteira com o Shields**: apenas o formato visual é compatível o bastante
   para o público reconhecer. Código, SVG, métricas de fonte e desenhos de logo
   são autorais, gerados neste repositório; nenhuma linha é copiada do
   Shields.io.
4. **O que a ferramenta não é**: não é monitoramento, não é CI, não coleta
   dados de terceiros na Fase 1, não é encurtador de URL e não renderiza
   conteúdo arbitrário do usuário como HTML — a saída é um documento SVG
   fechado, com allowlist de tags e atributos.
5. **Fases**: F1 entrega o núcleo SVG estático e o builder; PNG, providers,
   badges pessoais, analytics e exportação ficam nas fases seguintes, cada uma
   com ADR próprio (067, 068, 064, 063/065 e 070).

## Consequências

### Positivas
- A imagem independe do navegador e de sessão — funciona em qualquer README.
- A ferramenta cabe na página genérica de tool, sem rota de tela própria.
- Escopo pequeno mantém superfície de segurança pequena.

### Negativas / Trade-offs
- Compatibilidade visual aproximada exige disciplina de fixtures: pequenas
  diferenças de largura ou de fonte aparecem e precisam ser aceitas.
- O produto cresce por fases; recursos de concorrentes (PNG imediato, badges de
  provider) ainda não existem na F1 e precisam ser comunicados como tal.
