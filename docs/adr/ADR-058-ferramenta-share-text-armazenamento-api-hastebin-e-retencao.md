# ADR-058-ferramenta-share-text-armazenamento-api-hastebin-e-retencao
Status: Aceito
Data: 2026-10-26

## Contexto
O ecossistema de ferramentas do devtools contava com ferramentas de transformação de texto, codificação de mídia (Base64 Image no ADR-055) e geração de SQL assistida por IA (ADR-003, ADR-042).
Havia uma demanda recorrente por compartilhamento rápido de trechos de código, logs e notas via navegador e terminal (estilo Hastebin/Pastebin), exigindo:
1. Compatibilidade com a API clássica do Hastebin (`POST /documents`, `GET /documents/{id}`, `GET /raw/{id}`) para permitir uso direto por clientes CLI (`curl`, `haste-client`).
2. Persistência física em disco em `storage/app/data/sharetext/{user_id}/` (cadastrados) e `storage/app/data/sharetext/anonymous/` (visitantes).
3. Diferenciação de cotas e retenção:
   - Visitantes não cadastrados: limite de 1 MB e expiração automática em 72 horas.
   - Usuários cadastrados gratuitos: limite de 5 MB e permanência definitiva.
   - Usuários com plano Pro: limite de até 25 MB e permanência definitiva.
4. Identificação pública via ULID (26 caracteres alfanuméricos) com suporte opcional a extensão de arquivo para destaque de sintaxe (`/{id}.{ext}`).

## Decisao
1. **Armazenamento Híbrido com Tabela de Metadados (`shared_texts`)**:
   - Os textos são gravados fisicamente em arquivos `.txt` nos diretórios de disco correspondentes (`{user_id}` ou `anonymous`).
   - A tabela `shared_texts` indexa o `id` (ULID), `user_id`, `file_path`, `size_bytes`, `extension` e `expires_at`. Isso viabiliza busca O(1), conferência atômica de validade/expiração e listagem instantânea do histórico do usuário logado.
2. **Superfície de API Hastebin Universal**:
   - A rota `POST /documents` é isenta de verificação de CSRF em `bootstrap/app.php` para viabilizar automações de terminal.
   - O controller inspeciona se há sessão ativa web ou cabeçalho Sanctum `Authorization: Bearer <token>`. Em caso afirmativo, vincula o documento à conta do usuário; caso contrário, salva sob a pasta anônima com expiração de 72 horas.
   - `GET /raw/{id}` serve o conteúdo com cabeçalhos estritos `Content-Type: text/plain; charset=utf-8` e `X-Content-Type-Options: nosniff`.
3. **Poda Automática Horária**:
   - O comando `share-text:prune` é executado a cada hora via `TaskSchedule` para remover arquivos físicos em `storage/app/data/sharetext/anonymous/` e registros expirados do banco de dados.
4. **Interface e UX no Frontend**:
   - Componente `resources/js/tools/share-text/Tool.vue` implementa atalhos de teclado de produtividade: `Ctrl+S` (Salvar), `Ctrl+N` (Novo), `Ctrl+D` (Duplicar & Editar) e `Ctrl+R` (Raw).
   - O CodeMirror 6 aplica realce de sintaxe baseado na extensão informada ou selecionada.

## Consequencias
- Paridade completa com a especificação Hastebin, permitindo substituição transparente em fluxos de desenvolvimento.
- Proteção da infraestrutura contra esgotamento de disco através de poda automática de arquivos anônimos após 72 horas.
- Preservação da privacidade: exclusão solicitada pelo usuário no histórico remove física e imediatamente o arquivo em disco.
