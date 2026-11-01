# ADR-062: Persistência em disco
Status: Aceito
Data: 2026-10-31

## Decisão
A primeira requisição gera SVG e PNG e grava em storage/app/data/tools/badgeforge/{username}/{ulid}.{svg|png}. Requisições seguintes servem o arquivo do disco.

## Consequências
Menor CPU, mas exige controle de expiração, invalidação e limpeza.
