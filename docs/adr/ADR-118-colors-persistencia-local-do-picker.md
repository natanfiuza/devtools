# ADR-118 - Persistência local do picker
Status: Aceito
Data: 2026-11-07

## Decisão
Na Fase 1 o picker grava só em `localStorage`: histórico com 50 cores (mais recente primeiro, dedupe por cor canônica incluindo alpha), 50 favoritos e a última cor. Não há estado em query string; a URL não carrega a cor. Falha de leitura/escrita (cota, SSR, modo privado) nunca quebra a ferramenta.

## Consequências
Anônimo tem persistência reduzida e local. Histórico de cadastrado no backend fica para a Fase 5. A SPEC deixa de prometer histórico ilimitado na Fase 1.
