# Planeje a Fase 3 do Colors: harmonias e extração de paletas.

Escopo:
- Harmonias complementar, análoga, triádica, tetrádica e monocromática.
- Métodos HSL e OKLCH, identificados na interface.
- Importação local de imagem sem envio ao servidor.
- Extração de 5, 7, 9 ou 11 cores, padrão 11.
- Separar claramente cores dominantes extraídas de uma escala 50–950.
- Selecionar uma cor extraída como base do gerador.
- Preservar a paleta extraída para salvamento direto na Fase 4.
- Importação por URL de imagem com HTTPS e CORS.
- Processamento pesado fora da thread principal quando necessário.
- Limites configuráveis de bytes, dimensões e pixels decodificados.
- Tratamento de transparência, imagens uniformes e quantidade insuficiente
  de cores distintas.
- Cancelamento e descarte de resultados obsoletos.
- Liberação de recursos após processamento.

Para URLs:
Não implemente proxy no backend nesta fase.
Se CORS impedir a leitura, explique a limitação e ofereça arquivo local.
Nunca burle CORS com serviços externos.

Algoritmo:
Avalie uma biblioteca mantida e sua licença.
Justifique a escolha entre quantização median cut e agrupamento perceptual.
Não prometa um método que a biblioteca escolhida não implemente.

Fora do escopo:
- CRUD de paletas.
- Protótipos.
- Recursos sociais.

Inclua testes de qualidade, determinismo quando aplicável,
erros de imagem, CORS e responsividade.


## Trabalhe no projeto devtools existente, sem recriar sua estrutura.

Leia CLAUDE.md e todos os documentos relevantes em docs/colors/.
Inspecione o código e as convenções existentes antes de propor alterações.

Regras:
- Implemente somente a fase solicitada.
- Não sobrescreva alterações locais do usuário.
- Não execute git reset, git clean ou descarte alterações.
- Não faça deploy, push ou commit sem autorização.
- Reutilize autenticação, planos, permissões, notificações e componentes existentes.
- Toda interface deve estar traduzida em en, pt-BR, pt-PT, fr e es.
- Mantenha a especificação do Colors em docs/colors/; as decisões ficam em docs/adr/ com o prefixo `colors` (ex.: `ADR-091-colors-espacos-de-cor.md`).
- Não reutilize números de ADR já existentes no repositório.
- Atualize os documentos sem apagar decisões anteriores.
- Não invente comandos de testes: confira composer.json e package.json.

Antes de codificar:
1. Verifique o estado da fase anterior.
2. Liste requisitos e documentos ausentes.
3. Apresente arquivos afetados, dependências, riscos e testes.
4. Pare para minha aprovação.

Após a aprovação:
- Implemente em etapas pequenas.
- Execute testes relevantes, typecheck e build disponíveis.
- Informe comandos executados e resultados reais.
- Registre pendências.
- Pare ao concluir a fase; não avance automaticamente.


----

> Atenção: o prompt da proxima fase e: `docs\colors\PROMPT-Fase4-persistencia_prototipos.md` 
