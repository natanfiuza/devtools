# ADR-172 — Crédito de origem congelado e fonte privada
Data: 2026-11-19
Status: Aceito

## Contexto

O sistema de layout pode nascer de uma paleta (CTA no editor ou na página
pública da paleta). Compartilhar o sistema deve creditar a paleta de origem,
mas a origem pode ser privada — e o público nunca pode receber nome, link ou
autor de uma paleta que o dono não publicou. Ao mesmo tempo, o crédito não
pode depender de a paleta continuar existindo: excluir a paleta não deveria
apagar a atribuição textual do sistema.

## Decisão

`LayoutSystemCredit::capture(?ColorPalette $palette, ?User $owner): ?array`
congela no salvamento:

```json
{ "palette_name": "...", "palette_slug": "...", "palette_public": true,
  "author_name": "...", "author_username": "...", "captured_at": "ISO" }
```

- O request envia apenas `source_palette_id` (ULID); o servidor valida dono
  **ou** paleta pública ativa e captura o crédito. O cliente nunca envia
  `source`.
- Se a paleta de origem sumir entre a página e o salvamento, o request falha
  com erro de validação em `source_palette_id`; o banner de origem oferece
  “remover origem” e o sistema pode ser salvo sem crédito. Nada é gravado com
  crédito inventado.
- Fonte privada: `palette_public=false`; o público nunca vê nome, link ou autor
  da origem.
- Fonte pública: o crédito textual é sempre exibido; o link só aparece quando a
  paleta ainda existe e continua `publiclyVisible()`. Se a paleta for
  excluída, o texto permanece e o link some.
- Duplicação de outra conta copia o `source` congelado, mas **zera**
  `source_palette_id` (a linhagem fica em `duplicated_from_id`); “salvar como
  novo” na mesma conta mantém o FK.
- O presenter só expõe a paleta de origem quando o viewer pode acessá-la; o
  presenter público nunca recebe `source_palette_id`.

O CTA da paleta abre `dashboard/colors/layout-systems/create?palette={ULID}`;
o intent valida (dono ou pública), preserva `palette` e `save` e redireciona ao
tool `colors/layout-harmonizer`. O tool usa a **cor base** (`colors.base`) como
seed editável, nunca o papel `primary`.

## Consequências

- Nenhuma tela pública recebe FK de paleta; o crédito público é um texto
  congelado, exibido só quando a origem era pública no momento do salvamento.
- Um sistema não perde a atribuição quando a paleta é excluída, mas perde o
  link.
- Remover a origem é uma ação explícita do formulário; não há crédito inferido
  pelo servidor.

