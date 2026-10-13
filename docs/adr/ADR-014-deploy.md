# ADR-014-deploy
Status: Aceito
Data: 2026-10-05

## Decisao
Cron a cada minuto chama deploy.sh com flock; so age se HEAD mudou; migrations retrocompativeis; queue:restart. Pendente: estrategia de assets (build no servidor vs public/build versionado). Risco: pull parcial - usar --ff-only e sequencia atomica.

## Consequencias
Ver SPEC.md.
