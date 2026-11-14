# Importação e exportação

## Imagem (Fase 3, implementada)
- Arquivo local (Canvas + `createImageBitmap`) ou URL HTTPS; **nada** é enviado ao servidor e não há proxy para CORS.
- Usuário escolhe 5, 7, 9 ou 11 cores (padrão 11); só a extração tem essa escolha, a escala do gerador continua sempre com 11 tons.
- Quantização MMCQ com `colorSpace: 'oklch'` via `color-thief@3.5.0`, num worker; o worker é encerrado ao desmontar.
- Pesos e percentuais são recalculados sobre os pixels visíveis (alpha ≥ 0,5); cores ordenadas por peso decrescente.
- Imagem com menos cores que o pedido devolve as existentes com aviso; imagem toda transparente é erro.
- Limites de bytes, lado, pixels, tamanho de análise e timeout vêm de `config/tools.php` (`limits.colors_image`).
- Permitir salvar direto: **implementado na Fase 4** — a paleta extraída é salva como `kind=extracted` com base, 5/7/9/11 cores na ordem de peso e origem `{kind,label|host}`, mesmo com `sufficient: false` (com aviso); reabrir nunca re-quantiza (ADR-122, emenda; ADR-123).
- Importar por URL de imagem: só HTTPS, CORS direto na origem, `content-type` raster e corte por stream em `max_bytes`; bloqueio de CORS oferece o arquivo local (ADR-122).

## JSON e design tokens
- Exportar W3C DTCG, Style Dictionary, TypeScript e o JSON nativo da paleta — implementado na Etapa 5 da Fase 5 (ADR-095); o DTCG é importável pelo Tokens Studio e pelas Figma Variables, sem API do Figma.
- Importar tokens W3C DTCG (Tokens Studio / Figma Variables), Style Dictionary e JSON nativo — Etapa 6: validação de estrutura, tamanho, profundidade, referências e ciclos; nada é executado e um diagnóstico nunca descarta o estado atual.

### Importação validada (Fase 5, Etapa 6)

Disponível no gerador (sempre) e no editor (só quando a paleta não está arquivada). O
botão **Importar tokens** abre a leitura de um arquivo `.json`; escolher o arquivo
apenas valida e mostra a prévia. O estado da página só muda ao clicar em
**Aplicar**, e só quando a leitura terminou sem erro.

- **Formatos aceitos**: JSON nativo da ferramenta, W3C DTCG (`$type`/`$value`) e
  Style Dictionary (`value`/`type`). A detecção é estrutural; misturar estilos
  (`ambiguous_format`) ou não reconhecer nenhum token de cor é erro. TypeScript,
  CSS, SCSS e Tailwind continuam apenas exportação.
- **Nada é executado**: o arquivo é lido com `readTextFile` e `JSON.parse`
  (via `analyzeJson`); nunca `eval`, `Function` ou import dinâmico. Aliases
  `{caminho}` são resolvidos como dados, com limite de profundidade e detecção
  de ciclos.
- **Escolha da paleta**: um grupo com os 11 tons vira escala; um grupo com
  5/7/9/11 cores (fora de `semantic`) vira extraída. Dois candidatos válidos ou
  contagem inválida viram diagnóstico — nunca “pega o primeiro”.
- **Papéis semânticos**: lidos apenas de `semantic.{light,dark}.{papel}` com os
  8 papéis completos em HEX6/HEX8. Bloco incompleto é aviso e os papéis atuais
  são mantidos; no editor, papéis importados completos substituem a cópia
  editável e são enviados no próximo **Salvar**.
- **Base e metadados**: a base vem do JSON nativo (`base`) ou do DTCG
  (`$extensions["devtools.colors"].base`); Style Dictionary não tem slot e usa o
  tom 500. `formatVersion` diferente de 1 é erro; `algorithmVersion` diferente é
  aviso e a escala é regravada com a versão atual, porque as cores são literais.
  `clippedTones` é preservado.
- **Limites no servidor**: `config/colors.php` (`tokens.import`) entrega tamanho
  do arquivo, tokens/nós, profundidade, profundidade de referências e o teto de
  diagnósticos exibidos; nenhum número é fixado no cliente.
- **Assinatura**: os papéis importados valem enquanto tipo, base e cores forem
  os do arquivo. Trocar a base, aplicar um preset ou editar a extração os
  descarta; o rascunho anônimo carrega escala e papéis pelo login, e a mesma
  assinatura decide se ainda valem ao restaurar.
- **Persistência**: importar não salva. No gerador, quem está logado ainda passa
  pelo modal de salvar; o visitante leva o rascunho para o login. A base
  aplicada entra no histórico como qualquer troca de cor.
- **Privacidade**: o conteúdo do arquivo não sai do navegador; não existe
  endpoint de importação no servidor.

## Exportação
- Individual por cor.
- Paleta completa.
- Protótipo SVG vetorial (fonte única) e PNG 1x/2x rasterizado do **mesmo** SVG, com a fonte embutida na exportação — implementado na Fase 4 (ADR-126).
- Paleta como imagem tipográfica (horizontal, vertical, card).
