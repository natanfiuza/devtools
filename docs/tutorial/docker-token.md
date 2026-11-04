# PAT do Docker Hub para badge de imagem privada

Este guia é para **usuários com plano pago** que querem um badge de uma imagem
**privada** no Docker Hub. Imagem pública não precisa de token: o BadgeForge
consulta a API pública do Docker Hub de forma anônima.

O badge de item privado é uma **badge pessoal**: a URL pública leva apenas o
identificador opaco da badge (`/b/{username}/{ulid}.svg`), nunca o token.

A referência pública da ferramenta, com os exemplos de cada métrica e o campo de
credencial, fica em `/{idioma}/docs/badgeforge` (link **BadgeForge docs** no rodapé
do site).

## 1. Antes de começar

- Sua conta precisa estar em um plano pago e ter uma badge pessoal criada na
  tela do BadgeForge.
- Use um PAT dedicado ao BadgeForge, com o menor escopo que lê a imagem.

## 2. Crie o Personal Access Token

1. Acesse <https://hub.docker.com/settings/security>.
2. Clique em **New Access Token**.
3. Permissões: **Read-only** (leitura de repositórios; nada de escrita).
4. Dê um nome que identifique o uso (ex.: `badgeforge`) e defina validade curta
   quando o plano do Docker permitir.
5. Gere e copie o token agora; o Docker Hub não mostra de novo.

## 3. Informe o token na sua conta

1. Na tela do BadgeForge, abra a badge pessoal da imagem privada.
2. Cole o token no campo de credencial do provider **Docker** e preencha o
   **username** da conta do Docker Hub (ele não é secreto e acompanha o token
   porque a API do Hub usa Basic auth).
3. O servidor grava o token **cifrado** (chave da aplicação), vinculado apenas a
   essa badge.

O token não aparece na URL do badge, não vai para log, não volta na tela e não
sai em exportação de dados.

## 4. Revogar ou trocar

- Para trocar: gere outro PAT, cole sobre o anterior e salve.
- Para revogar: apague a badge (não existe "remover credencial" sem recriar) e
  revogue em **Docker Hub → Account settings → Security → Revoke**.
- Se a conta for encerrada/anonimizada, a credencial é apagada junto.

## 5. Se o badge parar de atualizar

- **401 do Docker Hub**: token revogado ou expirado.
- **403/404 do Docker Hub**: o token não tem leitura naquele repositório ou o
  nome da imagem está errado.
- **Limite de taxa**: o BadgeForge usa cache e mantém o último valor bom.
