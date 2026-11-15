# ADR-111 - Comentários em threads
Status: Aceito

## Decisão
Comentários suportam respostas (threads), profundidade máxima 2 níveis. Apenas logados. Limite 255 caracteres. Editar e excluir próprio comentário permitido.

## Consequências
Estrutura parent_id; UI recolhível.

## Emenda 2026-11-07
Profundidade máxima de 2 níveis confirmada; o prompt da F7 deixa de pedir verificação.

## Emenda 2026-11-15

Implementado na F7 (ADR-139): responder a uma resposta responde 422 no servidor, comentário `pending` aparece com selo "em análise" (nunca como aprovado) e a edição guarda `approved_body` para voltar à última versão aprovada quando a revisão é rejeitada. Excluir é soft delete; raiz com respostas vira tombstone e o texto permanece para o admin. A escrita exige login, conta verificada e ativa, e a superfície inteira é desligável por flag (ADR-143).
