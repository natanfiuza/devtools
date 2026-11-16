# Contrato técnico recomendado para implementação
## Estado versionado
schemaVersion e algorithmVersion separados. Seed original, composição/fundo, seed efetiva, estratégia, secondary manual, valores por tema, overrides/bloqueios, ajustes aceitos, sugestões e diagnósticos. Exportações usam snapshot congelado, inclusive durante movimentação de sliders. Persistir valores efetivos; não recalcular salvos ao abrir.
## Motor
Funções puras, determinísticas e independentes de Vue/backend. Preferir biblioteca mantida de conversão OKLCH/OKLab e estratégia de mapeamento para sRGB documentada. Registrar biblioteca, licença e parâmetros concretos em ADR técnico antes do código. Não inventar números de luminosidade não documentados; criar tabela versionada e fixtures na primeira fase e apresentar ao usuário.
Parâmetros de matiz aprovados não definem toda a qualidade visual: croma/luminosidade de superfícies, estados e feedback exigem calibração técnica com preview e testes. Proposta manter seed Primary exata, feedback vindo das famílias existentes Colors; sem famílias existentes, propor valores explicitamente na F1.
Monocromática e seeds sem croma precisam de fallback neutro; não produzir hue aleatório. Secondary manual preservada ao trocar estratégia enquanto ativa; recomendar controle explícito para voltar ao automático.
## Dependências
Bloqueios prevalecem sobre regeneração automática; avisos em pares inviáveis. Aceitar Primary altera Primary e dependentes automáticos pertinentes, não bloqueados; mostrar consequências antes de confirmar. Não reescrever manuais silenciosamente. Restaurar automático remove override/bloqueio somente no escopo solicitado, com confirmação se houver perda manual.
## Contraste
Linearizar sRGB; compor alpha; calcular razão por par sem arredondamento para classificar. AA normal 4,5:1/grande 3:1; AAA 7:1/4,5:1. Mostrar 1–2 casas apenas na UI. Foco e controles avaliados conforme uso, não por selo global. Avisos nunca somem do export apenas porque usuário aceitou risco.
## Limites defensivos recomendados
Nome 120 caracteres, descrição 2000, prefixo ASCII validado até 32, classe simples até 64; validar contra padrões existentes, não alterar regras de projeto sem motivo. Sem CSS arbitrário, URLs, diretivas ou seletor composto fornecido pelo usuário. Metadados e cores não entram em logs/GA.
