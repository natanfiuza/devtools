# Planeje a Fase 4 do Colors: paletas salvas, compartilhamento e protótipos.

Persistência:
- CRUD de paletas apenas para usuários autenticados.
- Free: 25 paletas; pago: sem limite funcional.
- Limite verificado no backend com proteção contra concorrência.
- Nome, descrição opcional, tags, visibilidade, cor base, cores,
  papéis semânticos e versão do algoritmo.
- Salvar diretamente uma paleta extraída de imagem.
- Persistir as cores efetivas, sem recalcular ao abrir uma paleta antiga.
- Tags renomeáveis e reordenáveis.
- Arquivar e restaurar.
- Definir exclusão separadamente do arquivamento.
- Propor regra explícita para contagem de arquivadas na cota e pedir aprovação.

Minhas Paletas:
- /dashboard/colors/palettes.
- Busca server-side por nome, descrição e tags.
- Paginação.
- Filtros e ordenação por data, nome, número de cores e uso.
- Definir precisamente a métrica "uso".

Compartilhamento:
- /colors/p/{slug}.
- Apenas paletas públicas disponíveis a visitantes.
- Paletas privadas protegidas por autorização no backend.
- Duplicação de paleta pública por usuário autenticado, respeitando a cota.
- Permitir escolher uma nova cor base sem modificar a paleta original.
- SEO, canonical e metadados disponíveis para crawlers.

OG:
- Reutilizar GenerateOgImage.php para páginas estáticas das tools.
- Imagem dinâmica da paleta gerada no cliente.
- Upload validado e armazenado em storage/app/data/og_paleta_image.
- URL pública controlada apenas para paletas públicas.
- Atualização e remoção de imagens obsoletas.
- Não versionar imagens de usuários no Git.

Protótipos, apenas cadastrados:
- Site: header, hero, botões, links, cards, formulário e footer.
- Mobile genérico com tabs, sem bottom navigation.
- Temas claro e escuro.
- Papéis de cor editáveis e persistidos.
- Exportação PNG 1x/2x e SVG.

Antes de escolher biblioteca de captura, avalie suporte a OKLCH, fontes,
SVG e qualidade de exportação.
Não prometa SVG vetorial se a saída apenas embutir uma captura raster.

Teste autorização, visibilidade, cota, concorrência, upload,
restauração e fidelidade dos protótipos.

## Trabalhe no projeto devtools existente, sem recriar sua estrutura.

Leia CLAUDE.md e todos os documentos relevantes em docs/colors/.
Inspecione o código e as convenções existentes antes de propor alterações.

Regras:
- Implemente somente a fase solicitada.
- Não sobrescreva alterações locais do usuário.
- Não execute git reset, git clean ou descarte alterações.
- Não faça deploy, push ou commit sem autorização.
- Reutilize autenticação, planos, permissões, notificações e componentes existentes.
- Toda interface deve estar traduzida em en, pt-BR, pt-PT, fr e es.
- Mantenha a especificação do Colors em docs/colors/; as decisões ficam em docs/adr/ com o prefixo `colors` (ex.: `ADR-091-colors-espacos-de-cor.md`).
- Não reutilize números de ADR já existentes no repositório.
- Atualize os documentos sem apagar decisões anteriores.
- Não invente comandos de testes: confira composer.json e package.json.

Antes de codificar:
1. Verifique o estado da fase anterior.
2. Liste requisitos e documentos ausentes.
3. Apresente arquivos afetados, dependências, riscos e testes.
4. Pare para minha aprovação.

Após a aprovação:
- Implemente em etapas pequenas.
- Execute testes relevantes, typecheck e build disponíveis.
- Informe comandos executados e resultados reais.
- Registre pendências.
- Pare ao concluir a fase; não avance automaticamente.


----

> Atenção: o prompt da proxima fase e: `docs\colors\PROMPT-Fase5-organizacao_desing_tokens.md` 
