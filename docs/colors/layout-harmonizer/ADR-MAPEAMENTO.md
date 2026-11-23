# Mapa de transferência dos ADRs — Layout Harmonizer

Data: 2026-11-15
Origem: `docs/colors/layout-harmonizer/adr/ADR-LH-001.md` … `ADR-LH-022.md`
Destino: `docs/adr/ADR-145-colors-lh-<escopo>.md` … `ADR-166-colors-lh-<escopo>.md`
Regra (grill de 2026-11-15): renumerar na **sequência global**, continuando
depois do último ADR existente (`ADR-144-colors-auditoria-final.md`), com
escopo em minúsculo, e **copiar um ADR por fase**, conforme a fase que o
implementa — nunca todos de uma vez.

## Como a cópia é feita

1. No início da fase, copiar o arquivo local correspondente para `docs/adr/`
   com o novo número e nome.
2. Manter o conteúdo; ajustar apenas o título/numeração e as referências
   cruzadas para os números novos.
3. Remover o original local **somente** depois de copiado e revisado.
4. O ADR só entra em `docs/adr/` quando a fase que ele descreve for
   implementada; o que ainda não tem implementação continua local.
5. Decisões novas tomadas no grill (tabela própria, rota dedicada, promoção dos
   protótipos, cota conjunta, `fflate`, rascunho em `localStorage`, pivot de
   tags) **não** têm ADR local: viram ADR-167 em diante na fase em que forem
   implementadas.

## Tabela

| Local | Tema | Novo arquivo | Fase |
|---|---|---|---|
| `ADR-LH-001.md` | Identidade | `ADR-145-colors-lh-identidade.md` | F0* |
| `ADR-LH-002.md` | Quadro semântico | `ADR-146-colors-lh-quadro-semantico.md` | F1 |
| `ADR-LH-003.md` | Geração | `ADR-147-colors-lh-geracao.md` | F1 |
| `ADR-LH-004.md` | Exportações | `ADR-148-colors-lh-exportacoes.md` | F5 |
| `ADR-LH-005.md` | Seed | `ADR-149-colors-lh-seed.md` | F1 |
| `ADR-LH-006.md` | Processo | `ADR-150-colors-lh-processo.md` | F0* |
| `ADR-LH-007.md` | Bloqueios | `ADR-151-colors-lh-bloqueios.md` | F3 |
| `ADR-LH-008.md` | Quota | `ADR-152-colors-lh-quota.md` | F4 |
| `ADR-LH-009.md` | Entradas | `ADR-153-colors-lh-entradas.md` | F2 |
| `ADR-LH-010.md` | Público | `ADR-154-colors-lh-publico.md` | F4 |
| `ADR-LH-011.md` | CSS | `ADR-155-colors-lh-css.md` | F5 |
| `ADR-LH-012.md` | Harmonias | `ADR-156-colors-lh-harmonias.md` | F1 |
| `ADR-LH-013.md` | Variantes | `ADR-157-colors-lh-variantes.md` | F1 |
| `ADR-LH-014.md` | Contraste | `ADR-158-colors-lh-contraste.md` | F1 (retomada na F3 para AA/AAA) |
| `ADR-LH-015.md` | Cadastro | `ADR-159-colors-lh-cadastro.md` | F4 |
| `ADR-LH-016.md` | Downgrade | `ADR-160-colors-lh-downgrade.md` | F4 |
| `ADR-LH-017.md` | Rascunho | `ADR-161-colors-lh-rascunho.md` | F4 |
| `ADR-LH-018.md` | Copiar cor | `ADR-162-colors-lh-copiar-cor.md` | F2 |
| `ADR-LH-019.md` | Accent | `ADR-163-colors-lh-accent.md` | F1 |
| `ADR-LH-020.md` | Ciclo de vida | `ADR-164-colors-lh-ciclo-de-vida.md` | F4 |
| `ADR-LH-021.md` | Duplicação | `ADR-165-colors-lh-duplicacao.md` | F4 |
| `ADR-LH-022.md` | Temas | `ADR-166-colors-lh-temas.md` | F1 |

\* A F0 foi somente auditoria, sem código e sem tocar nos ADRs do pacote. Os
dois ADRs mapeados para F0 (Identidade e Processo) são copiados no **início da
F1**, junto do primeiro código, para que a transferência acompanhe a fase
implementada e nenhum arquivo local seja removido sem revisão.

## Status da transferência

- **2026-11-16 (F1)**: copiados 145 (identidade), 146 (quadro semântico),
  147 (geração), 149 (seed), 150 (processo), 156 (harmônias), 157 (variantes),
  158 (contraste), 163 (Accent) e 166 (temas), com o mesmo conteúdo e apenas
  título/numeração ajustados. Os originais locais foram mantidos até a revisão
  da fase; a remoção acontece depois do aceite da F1.
- **2026-11-16 (F2)**: copiados 153 (entradas) e 162 (copiar cor), com o mesmo
  conteúdo e apenas título/numeração ajustados. O 161 (rascunho) foi adiado
  para a F4, porque a F2 não persiste nada no navegador. Os originais locais
  seguem em `adr/` até o aceite da fase.
- **2026-11-18 (F3)**: copiado 151 (bloqueios), com o mesmo conteúdo e apenas
  título/numeração ajustados, mais uma nota de implementação no fim. O ADR-158
  recebeu emenda datada (AA/AAA para sugestões, inviabilidade em AA) e o
  ADR-167, novo, registra o estado efetivo, os overrides por tema/origem, a
  assinatura de regeneração, a secundária manual, os parâmetros das sugestões e
  o snapshot. O original local do 151 segue em `adr/` até o aceite da fase.
- **2026-11-19 (F4)**: copiados 152 (quota), 154 (público), 159 (cadastro),
  160 (downgrade), 161 (rascunho), 164 (ciclo de vida) e 165 (duplicação), com
  o mesmo conteúdo, título/numeração ajustados e uma seção de implementação no
  fim de cada um. Novos: 168 (persistência e snapshot), 169 (edição
  compatível/congelada), 170 (cota conjunta: contador único e locks), 171 (slug
  aposentado), 172 (crédito de origem), 173 (protótipos no workspace) e 174
  (viewer público, rota e SEO). Os originais locais dos sete copiados seguem em
  `adr/` até o aceite da fase.
- **2026-11-20 (F5)**: copiados 148 (exportações) e 155 (CSS), com o mesmo
  conteúdo, título/numeração ajustados e uma seção “Implementação (F5)” no fim
  de cada um. Novos: 175 (exportação local: cliente, `fflate`, nomes fixos,
  permissões e snapshot único), 176 (fallback HEX/OKLCH, escopo e prefixo
  validados, comentários sanitizados) e 177 (estrutura do Markdown, idioma,
  avisos e origem pública). Os originais locais dos dois copiados seguem em
  `adr/` até o aceite da fase.
- **2026-11-22 (F6)**: novo 178 (auditoria final e entrega: teto de croma e
  passo da variante na versão 2, Accent fechado pelo TOKENS/ADR-163, secundária
  com alpha, rejeição do alpha no fundo da seed, teste de concorrência opt-in
  e remoção dos originais). No aceite, **os 22 originais locais de `adr/`
  foram removidos**: o conteúdo (com as emendas de implementação) está
  integralmente em `docs/adr/ADR-145` a `ADR-178`, que passa a ser a fonte
  única.

## Contagem por fase

| Fase | ADRs |
|---|---|
| F1 (inclui os dois da F0) | 145, 146, 147, 149, 150, 156, 157, 158, 163, 166 |
| F2 | 153, 162 |
| F3 | 151 (retomada de 158; novo 167) |
| F4 | 152, 154, 159, 160, 161, 164, 165; novos 168–174 |
| F5 | 148, 155; novos 175–177 |
| F6 | novo 178; remoção dos 22 originais locais |
| F0 | — (somente este mapeamento) |

**Total final**: 34 ADRs no track (145–178), todos em `docs/adr/`.
