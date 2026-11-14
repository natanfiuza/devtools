# ADR-112 - Curtida anônima
Status: Aceito

## Decisão
Curtidas podem ser anônimas por um identificador anônimo assinado de primeira parte; o IP é apenas sinal antiabuso, nunca a identidade. Seguir exige login.

## Consequências
Menos risco de inflação e de exposição de IP; exige identificador assinado e deduplicação transacional.

## Emenda 2026-11-07
Substituída a decisão "por IP hash", alinhada ao prompt da Fase 6.

## Emenda 2026-11-14

Implementada na F6 (ADR-135). O identificador de primeira parte é o cookie
cifrado `colors_anon` (32 bytes aleatórios em hex, 365 dias), emitido apenas
depois de uma curtida bem-sucedida; o IP só vira chave HMAC do limitador e
nunca é gravado. A deduplicação é transacional: linha permanente com
`liked_at`/`notified_at`, dois únicos separados (conta e anônimo),
`firstOrCreate` + releitura com lock e uma notificação por relação. No login,
`ClaimAnonymousLikesOnLogin` reivindica as curtidas para a conta, mescla
conflitos e descarta o identificador sem duplicar. Seguir continua exigindo
login (ADR-136).
