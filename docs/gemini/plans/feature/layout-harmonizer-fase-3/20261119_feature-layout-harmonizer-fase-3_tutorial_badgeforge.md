# Plano de Implementação — Tutorial do BadgeForge (`docs/tutorial/badgeforge.md`)

## Goal Description

Criar um tutorial completo e prático em [docs/tutorial/badgeforge.md](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/docs/tutorial/badgeforge.md) explicando como usar o **BadgeForge** de ponta a ponta — desde a criação rápida de badges estáticas e de providers públicos na interface web até recursos de conta gratuita (logos personalizados, presets, Endpoint JSON e analytics de 30 dias), plano pago (badges pessoais com PAT para itens privados, providers customizados e analytics completo com CSV) e uma introdução à API REST v1.

O levantamento prévio foi concluído via `/grill-with-docs` e registrado em [20261119_tutorial_badgeforge_surveyfacts.md](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/docs/survey/20261119_tutorial_badgeforge_surveyfacts.md).

## Proposed Changes

### Documentação (`docs/tutorial/`)

#### [NEW] [badgeforge.md](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/docs/tutorial/badgeforge.md)

Documento único em português com acentuação correta, usando `https://devtools.natanfiuza.dev.br` nos exemplos (com nota inicial indicando substituição pela `APP_URL` em instâncias locais/self-hosted) e vocabulário alinhado a [glossario.md](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/docs/glossario.md).

Estrutura planejada do tutorial:

1. **Introdução e visão geral por perfil**
   - O que é o BadgeForge e onde acessá-lo (`/{idioma}/tools/badgeforge`, painel `/dashboard/badgeforge` e referência pública `/{idioma}/docs/badgeforge`).
   - Diagrama Mermaid (`flowchart TD`) para ajudar o usuário a escolher o tipo certo de badge (*Estática*, *Provider público*, *Badge pessoal*, *Endpoint JSON* ou *Provider customizado*).
   - Tabela comparativa rápida por perfil (*Anônimo*, *Conta gratuita / Free*, *Plano pago*).

2. **Primeiros passos: criando uma badge estática (sem login)**
   - Passo a passo pela interface usando os rótulos reais em PT-BR (`Conteúdo`, `Cores`, `Logo`, `Avançado`, `Prévia` com alternância `Fundo claro` / `Fundo escuro`, botões `Exemplo` e `Limpar`).
   - Explicação dos 5 estilos (`flat`, `flat-square`, `plastic`, `for-the-badge`, `social` — destacando que `social` fixa suas próprias cores).
   - Como copiar e incorporar via abas `Incorporar` (`URL`, `Markdown`, `HTML`, `reStructuredText`) e baixar em `SVG` ou `PNG`.
   - **Anatomia da URL estática**: `/api/badges/static/{label}/{message}.{svg|png}`, regras de escape (`_` → espaço, `__` → `_`, `/` → `%252F`, limite de 100 caracteres) e tabela de query params (`style`, `color`, `labelColor`, `logo`, `logoColor`, `logoSize`, `logoPosition`, `link`, `cacheSeconds`, `font`, `download`).

3. **Badges dinâmicas com Providers públicos**
   - Como usar a aba **Provider** na interface.
   - Tabela dos **9 providers e 15 definições** suportadas (`github`, `npm`, `packagist`, `docker`, `codecov`, `coveralls`, `laravel`, `php`, `python`) com o formato esperado de `path` (inclusive o literal `latest` para `laravel`, `php` e `python`).
   - Anatomia da URL `/api/badges/{provider}/{definition}/{path}.{svg|png}` e comportamento de cache (300 s padrão, *stale-if-error*, parâmetros `cacheSeconds` e `refresh` ignorados na rota pública).

4. **Recursos da conta gratuita (Free)**
   - **Logos personalizados (`Meus logos`)**: limite de 2 no Free (ilimitado no pago), regras de upload (SVG até 256 KiB, sanitizado apenas para `<svg>`, `<g>` e `<path>`), uso via `?logo=u_<ULID>` e teto de cache de 3600 s.
   - **Presets (`Presets`)**: salvamento ilimitado de snapshots do formulário e reabertura via `?preset=<id>`.
   - **Endpoint JSON (`/api/badges/endpoint/{ulid}.{svg|png}`)**: cadastro em `/dashboard/badgeforge`, formato JSON obrigatório (contrato shields v1: `schemaVersion: 1`, `label`, `message`, opcionais `color`/`labelColor`), requisitos de segurança (HTTPS porta 443, sem redirects, proteção SSRF) e rate limit por dono (300/min Free, 3000/min Pago).
   - **Analytics básico**: janela fixa de 30 dias em `/dashboard/badgeforge` para badges com dono (pessoal, endpoint JSON, provider customizado).

5. **Recursos do plano pago**
   - **Badges pessoais (`/b/{username}/{ulid}.{svg|png}`) e itens privados**: criação na interface/painel, por que usar badge pessoal para repositórios/pacotes/imagens privadas (URL opaca sem expor token), atualização manual (*refresh*, 10 req/min) e links para os guias de token:
     - [github-token.md](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/docs/tutorial/github-token.md)
     - [npm-token.md](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/docs/tutorial/npm-token.md)
     - [docker-token.md](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/docs/tutorial/docker-token.md)
   - **Providers customizados (`/api/badges/custom/{ulid}/{definition}/{path}.{svg|png}`)**: limite padrão de 10 por conta, template HTTPS com placeholders no path, extração de métricas via JSON Pointer (RFC 6901) com formatadores `text`, `number` e `percent`, validação inicial com `example_path` e o que é imutável vs. editável após a criação.
   - **Analytics completo**: janelas de 7, 30 e 90 dias, série diária, top referrers, países, formatos, taxa de cache e exportação CSV sem dados pessoais.

6. **Automação via API REST v1 (visão rápida)**
   - Autenticação com API tokens (`Settings → API Tokens`), base `/api/v1/badgeforge/*` e exemplo rápido de `curl` para listar/criar recursos.
   - Links para a especificação completa em [API.md](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/docs/badgeforge/API.md), [OPENAPI.md](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/docs/badgeforge/OPENAPI.md) e `/openapi/badges.yaml`.

7. **Solução de problemas comuns**
   - Tabela curta cobrindo: texto com `/` quebrando (`%252F`), cores ignoradas no estilo `social`, PNG retornando 503 (`imagick` — com link para [instalar-imagick-ubuntu.md](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/docs/tutorial/instalar-imagick-ubuntu.md)), logo customizado que não aparece (`200` sem logo), badge de provider/endpoint mostrando `unavailable` (fallback 200 / cache stale) e erro `422` em Endpoint JSON (HTTPS/SSRF).

## Verification Plan

### Automated Tests
- Como a mudança é estritamente documental (`docs/tutorial/badgeforge.md`), nenhum código PHP/TypeScript é alterado; ainda assim, validaremos:
  - Existência e validade dos links relativos referenciados em `docs/tutorial/badgeforge.md`.
  - Conferência de que os endpoints, limites e nomes de campos citados correspondem exatamente a `config/badgeforge.php`, `docs/SPEC-BADGEFORGE.md`, `docs/badgeforge/API.md` e `lang/pt_BR/tools.php`.

### Manual Verification
- Revisar a renderização Markdown (incluindo o bloco `mermaid` `flowchart TD`, tabelas e blocos de código) e a acentuação em português.
- Confirmar que nenhum arquivo existente de guia de token foi alterado (conforme Q6).
