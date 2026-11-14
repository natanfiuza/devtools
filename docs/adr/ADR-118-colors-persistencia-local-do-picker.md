# ADR-118 - Persistência local do picker
Status: Aceito
Data: 2026-11-07

## Decisão
Na Fase 1 o picker grava só em `localStorage`: histórico com 50 cores (mais recente primeiro, dedupe por cor canônica incluindo alpha), 50 favoritos e a última cor. Não há estado em query string; a URL não carrega a cor. Falha de leitura/escrita (cota, SSR, modo privado) nunca quebra a ferramenta.

## Consequências
Anônimo tem persistência reduzida e local. Histórico de cadastrado no backend fica para a Fase 5. A SPEC deixa de prometer histórico ilimitado na Fase 1.

## Emenda 2026-11-14
A F5 cumpre a promessa: para quem está logado, histórico e favoritos vêm da conta (`color_history_entries` e `color_favorites`, ADR-127) e são a mesma lista no HTML Color Picker e no Color Palette Generator (ADR-131). O anônimo continua 100% em `localStorage`, sem requisição, mas o histórico cai de 50 para 20 (favoritos seguem em 50), com tetos em `config/colors.php` (ADR-132). A chave continua sendo a cor canônica, agora também validada no servidor (`ColorKey`). O histórico local só entra na conta por ação explícita no painel, em lotes e sem limpar o navegador antes de todos os lotes passarem (ADR-132). A última cor escolhida continua local nos dois casos.
