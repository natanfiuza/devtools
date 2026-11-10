# ADR-108 - Importar paleta de URL
Status: Aceito

## Decisão
Permitir importar paleta a partir de URL de imagem. Validação de URL, CORS e tamanho.

## Consequências
Risco de SSRF/CORS; validar e limitar.

## Emenda 2026-11-09
A importação por URL da Fase 3 (ADR-122) fixa as regras: somente HTTPS e sem userinfo; `fetch` CORS direto na origem (`credentials: 'omit'`, `referrerPolicy: 'no-referrer'`) com timeout configurado; `content-type` precisa ser JPEG, PNG, WebP, GIF ou AVIF (SVG recusado); `Content-Length` é checado antes da leitura e os bytes são contados pelo stream e cortados em `max_bytes`. Bloqueio de CORS nunca é contornado por proxy ou serviço externo: a mensagem explica e oferece o arquivo local. Do endereço, só o host fica no modelo.
