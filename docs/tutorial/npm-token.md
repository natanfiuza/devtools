# Token do npm para badge de pacote privado

Este guia é para **usuários com plano pago** que querem um badge de um pacote
**privado** no npm. Pacote público não precisa de token: o BadgeForge consulta o
registry público do npm de forma anônima.

O badge de item privado é uma **badge pessoal**: a URL pública leva apenas o
identificador opaco da badge (`/b/{username}/{ulid}.svg`), nunca o token.

## 1. Antes de começar

- Sua conta precisa estar em um plano pago e ter uma badge pessoal criada na
  tela do BadgeForge.
- Use um token dedicado ao BadgeForge, com o menor escopo que lê o pacote.

## 2. Crie o token

1. Acesse <https://www.npmjs.com/settings/~/tokens>.
2. Escolha **Granular Access Token** → **Generate New Token**.
3. Em **Packages and scopes**, marque **Read** apenas nos pacotes (ou escopo)
   usados nas badges.
4. Permissões do token: **read-only**.
5. Defina validade curta — 90 dias ou menos — e prefira renovar.
6. Gere e copie o token agora; o npm não mostra de novo.

## 3. Informe o token na sua conta

1. Na tela do BadgeForge, abra a badge pessoal do pacote privado.
2. Cole o token no campo de credencial do provider **npm** e salve.
3. O servidor grava o token **cifrado** (chave da aplicação), vinculado apenas a
   essa badge.

O token não aparece na URL do badge, não vai para log, não volta na tela e não
sai em exportação de dados.

## 4. Revogar ou trocar

- Para trocar: gere outro token, cole sobre o anterior e salve.
- Para revogar: apague a credencial na badge (ou apague a badge) e revogue em
  **npmjs.com → Access Tokens → Revoke**.
- Se a conta for encerrada/anonimizada, a credencial é apagada junto.

## 5. Se o badge parar de atualizar

- **401/403 do npm**: token revogado, expirado ou sem permissão de leitura no
  pacote.
- **404 do npm**: o escopo do token não alcança aquele pacote ou o nome está
  errado.
- **Limite de taxa**: o BadgeForge usa cache e mantém o último valor bom.
