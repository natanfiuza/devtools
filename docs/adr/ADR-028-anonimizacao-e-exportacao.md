# ADR-028-anonimizacao-e-exportacao
Status: Aceito
Data: 2026-10-14

## Decisao
**Excluir a conta e anonimizar, e nao apagar a linha: o dado pessoal sai, o
registro da cobranca fica. E a pessoa leva os proprios dados antes, num JSON
que sai na hora.**

- `AnonymizeUser` (`DELETE /settings/profile` com a senha atual, e
  `DELETE /admin/users/{user}` no painel) faz, numa transacao:
  - **apaga o que identifica**: `name = ''`, senha e `remember_token` nulos,
    `email_verified_at` nulo, `locale` e `appearance` nulos, vinculos sociais
    apagados, sessoes abertas apagadas, `role` de volta a `user`;
  - o e-mail vira `removido+{id}@removido.invalid` - `removido.invalid` e
    dominio reservado pela RFC 2606: nunca resolve, nunca recebe e-mail, e o
    endereco antigo volta a ficar livre para outra conta;
  - encerra a assinatura vigente;
  - grava `anonymized_at` - e o que distingue "conta removida" de "conta com
    dados estranhos".
- **O que fica**: as linhas de `payments`, `subscriptions` e `usage_ledger`.
  Nota fiscal nao se apaga, e o livro-razao nao identifica ninguem. A garantia
  nao e disciplina de codigo: `payments.user_id` e `restrictOnDelete`, entao o
  banco **recusa** apagar a linha da conta enquanto houver cobranca.
- Depois de anonimizada a conta nao entra mais: sem senha (o login por senha
  recusa) e sem vinculo social (o login social nao encontra).
- A exclusao pelo painel administrativo passa pelo **mesmo** caminho - nao ha um
  segundo tipo de exclusao -, e a policy `manage` nunca deixa excluir a propria
  conta do admin.
- A tela oferece **baixar antes**: o dialogo de confirmacao tem a exportacao
  como primeiro passo. Nao bloqueia - quem quiser sair sem baixar, sai.
- **Exportacao** (`GET /settings/export`) e download imediato: o JSON e gerado
  na requisicao, vai em `Content-Disposition: attachment` como
  `devtools-dados-{id}-{Y-m-d}.json`, e **nada fica guardado no servidor**.
  O conteudo: `exported_at`, conta (`account`), contas sociais, inscricao na
  newsletter, assinaturas, pagamentos e o consumo - com as datas em **ISO 8601**
  (o arquivo e para outra maquina, nao para o idioma de quem baixou).
- Fora do arquivo: **credenciais** - senha (que nem existe em claro),
  `remember_token`, token de sessao e o token de gerenciamento da inscricao na
  newsletter. Exportar credencial seria criar uma copia dela fora do lugar onde
  ela e protegida.

## Por que
O direito de eliminar e o direito de levar os proprios dados sao um par, e a
LGPD cobra os dois. Mas o registro da cobranca tem guarda fiscal: apagar a
linha da conta em cascata levaria os pagamentos junto, e manter uma copia
paralela dos dados pessoais "para a contabilidade" seria manter exatamente o que
a pessoa pediu para apagar.

Anonimizar resolve os dois lados: o que identifica a pessoa sai de verdade
(nome, e-mail, senha, vinculos, preferencias), e o que a contabilidade precisa
continua intacto e amarrado a um identificador sem dono.

O download imediato, sem arquivo parado no servidor, e o mesmo raciocinio: uma
exportacao guardada "por alguns dias" e mais um lugar com dado pessoal para
proteger, e mais um lugar para esquecer de apagar.

## Consequencias
- Nao ha desfazer: a conta anonimizada nao volta (o e-mail antigo nao existe
  mais em lugar nenhum, e o teste prova isso). Quem quiser voltar, cria outra.
- A exclusao pelo admin e a mesma operacao - o que o admin ve depois e uma conta
  sem nome e sem e-mail, marcada pela data.
- Conta anonimizada continua contando nos totais do painel (a linha existe) e
  nas somas de faturamento (o pagamento existe); o que ela nao tem mais e dono
  identificavel.
- Assinaturas antigas continuam no historico; a vigente e encerrada no ato.
- O `usage_ledger` some junto se a conta for apagada de fato algum dia
  (`cascadeOnDelete`), mas isso nao acontece por este caminho.

## Alternativas descartadas
- **`DELETE` de verdade na linha**: quebraria a FK dos pagamentos ou levaria a
  cobranca junto; e o banco ja recusa.
- **Soft delete (`deleted_at`)**: a linha sai da tela e o dado pessoal continua
  no banco - nao atende ao pedido, so o esconde.
- **Arquivar antes de apagar** (tabela de contas removidas): um segundo banco de
  dados pessoais, com menos protecao que o primeiro.
- **Exportacao assincrona por e-mail**: mais um lugar com dado pessoal (a caixa
  de entrada) e um arquivo parado; o pedido e simples o bastante para caber numa
  requisicao.
- **Tela intermediaria de exportacao** (gerar, depois baixar): dois cliques e um
  arquivo no meio; o download direto e uma acao so.
- **Guardar o arquivo gerado por alguns dias**: mais um lugar para proteger e
  expirar, sem ganho nenhum.
- **Apagar so o que a pessoa pedir** ("excluir meus pagamentos tambem"): o
  registro fiscal nao e escolha da pessoa, e o modelo comercial precisa dele
  inteiro.
