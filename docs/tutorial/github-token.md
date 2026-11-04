# PAT do GitHub para badge de repositório privado

Este guia é para **usuários com plano pago** que querem um badge de um
repositório **privado** do GitHub. Repositório público não precisa de token: o
BadgeForge consulta a API pública do GitHub de forma anônima.

O badge de item privado é uma **badge pessoal**: a URL pública que você coloca no
README leva apenas o identificador opaco da badge (`/b/{username}/{ulid}.svg`),
nunca o token.

A referência pública da ferramenta, com os exemplos de cada métrica e o campo de
credencial, fica em `/{idioma}/docs/badgeforge` (link **BadgeForge docs** no rodapé
do site).

## 1. Antes de começar

- Sua conta precisa estar em um plano pago e ter uma badge pessoal criada na
  tela do BadgeForge.
- O token pertence **a você**, não à plataforma: use um token só para o
  BadgeForge, restrito aos repositórios que aparecerão em badges.

## 2. Crie um fine-grained personal access token

1. Acesse <https://github.com/settings/tokens>.
2. Escolha **Fine-grained personal access token** → **Generate new token**.
3. Em **Repository access**, selecione **Only select repositories** e marque
   apenas os repositórios privados usados nas badges.
4. Em **Permissions → Repository permissions**, conceda o mínimo:
   - **Contents: Read-only** (estrelas, releases e arquivos do repositório);
   - **Metadata: Read-only** (obrigatório pelo GitHub para qualquer token).
5. Defina uma validade curta — 90 dias ou menos — e prefira renovar.
6. Gere o token e copie o valor agora: o GitHub não mostra de novo.

## 3. Informe o token na sua conta

1. Na tela do BadgeForge, abra a badge pessoal do item privado.
2. Cole o token no campo de credencial do provider **GitHub** e salve.
3. O servidor grava o token **cifrado** (chave da aplicação), vinculado apenas a
   essa badge.

O token não aparece na URL do badge, não vai para log, não volta na tela e não
sai em exportação de dados.

## 4. Revogar ou trocar

- Para trocar: gere outro token com as mesmas permissões, cole sobre o anterior
  e salve; o valor antigo é substituído.
- Para revogar: apague a badge (não existe "remover credencial" sem recriar) e
  revogue o token em **GitHub → Settings → Developer settings → Personal access
  tokens → Revoke**. Um token revogado só afeta badges que ainda o usem.
- Se a conta for encerrada/anonimizada, a credencial é apagada junto.

## 5. Se o badge parar de atualizar

- **401/403 do GitHub**: token revogado, expirado ou sem os escopos mínimos.
- **404 do GitHub**: o token não alcança aquele repositório (confira
  **Repository access**).
- **Limite de taxa**: o BadgeForge usa cache e mantém o último valor bom; espere
  a janela do GitHub passar.
