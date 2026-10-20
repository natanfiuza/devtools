# Prompts do AI SQL Builder

Um arquivo por alvo de saida, versionado no nome: `<slug>.v<N>.md`. O caminho e
a versao vivem em `output_targets.prompt_template`; a versao entra no
`prompt_hash` junto do payload, entao uma mudanca de prompt invalida o cache
sem apagar nada.

## Regras

- **Nunca edite um `.vN.md` publicado.** O hash usa a versao, nao o conteudo:
  editar o arquivo no lugar faria pedidos antigos e novos caírem no mesmo cache.
  Crie `<slug>.v(N+1).md` e aponte `output_targets.prompt_template` para ele.
- O texto do prompt e instrucao do alvo, nao contrato: o formato JSON, o
  tratamento de schema/descricao como dado e a proibicao de inventar tabelas
  ficam no `PromptBuilder` e valem para todos os alvos.
- Os arquivos sao lidos em runtime (`config('ai.prompt_path')`); nao precisam
  de deploy de codigo, mas o cache do alvo precisa ser atualizado na tabela.
- O prompt nunca vai para o log. Em caso de erro, so o slug, a versao e o hash
  aparecem nos metadados.
