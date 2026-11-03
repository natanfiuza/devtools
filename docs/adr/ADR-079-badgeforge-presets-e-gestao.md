# ADR-079: Presets e gestão autenticada do BadgeForge

Status: Aceito
Data: 2026-11-02
Relacionados: ADR-002 (processamento local), ADR-043 (props da página), ADR-066 (presets), ADR-068 (acesso a providers), ADR-078 (logos personalizados), ADR-075 (render PHP e espelho TS)

## Contexto

A ADR-066 registrou a intenção de presets, mas a F1/F2 não tinha conta nem
tabela: a tool era anônima e o formulário vivia só na memória da página. A F3
precisa que a conta:

1. reaproveite uma configuração de badge sem publicá-la;
2. gerencie os próprios logos e presets tanto no builder quanto no painel;
3. continue com a geração e o preview **no navegador** — a regra da ADR-002
   vale para as tools determinísticas.

Havia ainda a pergunta de contrato: preset é badge publicado ou snapshot de
formulário? E o que acontece quando o logo referenciado por um preset é
excluído?

## Decisão

### Preset é snapshot, não badge

1. `badge_presets` guarda `user_id`, `name` e `payload` — o snapshot
   normalizado do formulário (`label`, `message`, `style`, `font`, `color`,
   `labelColor`, `logo`, `logoColor`, `logoSize`, `logoPosition`, `link`,
   `cacheSeconds`). Não há URL pública, ETag, cache ou registro de acesso.
2. O `logo` do snapshot é o slug escolhido (`none`, embutido ou `u_<ULID>`),
   dentro do próprio `payload`. Não há FK nem coluna duplicada.
3. A normalização no servidor reusa as regras do `BadgeRequest`
   (`PresetSpecNormalizer`): truncamento, cores, `logoSize`, `cacheSeconds` e
   link. Um `logo=u_<ULID>` só é aceito se pertencer ao dono.
4. **Órfão é calculado na leitura**: `logoOrphaned = true` quando o logo custom
   referenciado não existe mais (ou o embutido saiu da config). A UI avisa e
   aplica o preset com `logo = none` — nunca falha ao carregar.

### Conta, limites e rotas

1. Limites em `config/badgeforge.php`: `presets.limit = null` (ilimitado para
   qualquer conta verificada) e a checagem centralizada em `BadgeForgeQuota`.
2. Rotas web/Inertia no grupo `auth, verified, active`, sem prefixo de locale:
   - `GET /dashboard/badgeforge` — página de gestão;
   - `POST /badgeforge/logos`, `GET|PATCH|DELETE /badgeforge/logos/{id}`;
   - `POST /badgeforge/presets`, `PATCH|DELETE /badgeforge/presets/{id}`.
   As escritas ficam sob `throttle:badgeforge-management`; a leitura do logo
   fica fora do throttle de escrita.
3. **Dono errado é 404, nunca 403** — não se revela a existência do registro
   de outra conta.
4. Mutações respondem `back()` com `Inertia::flash('toast', ...)`; o
   formulário usa `preserveState: true` para não perder o que estava na tela.
5. Gestão em dois lugares com o mesmo contrato (`BadgeForgeToolProps`):
   diálogos no builder (`LogoLibraryDialog`, `PresetDialog`) e a página
   `/dashboard/badgeforge`, com item em `useAccountNav`. A página reusa
   `toolProps`; os paths continuam sob demanda.
6. `?preset=<ulid>` abre o builder com o snapshot aplicado. O parâmetro é
   removido com `history.replaceState` antes de aplicar, para um recarregamento
   não sobrescrever o que o usuário editou depois.

### Exceção documentada à ADR-002

1. A **geração, o preview, os snippets e o download** continuam 100% no
   navegador: nenhuma digitação no formulário faz requisição.
2. O servidor é chamado **apenas em ação explícita de conta**:
   - buscar os paths do logo personalizado escolhido;
   - enviar, renomear ou excluir logo;
   - salvar, renomear ou excluir preset;
   - carregar/gerenciar a página da conta.
3. Não há endpoint de render, preview, snippet ou validação de SVG: o parser
   só roda no upload, no servidor, e o cliente nunca reenvia geometria para
   desenhar.
4. Um teste de guarda garante que digitar no formulário não dispara `fetch`;
   a única requisição do builder comum é a do logo escolhido, uma vez por
   seleção.
5. Esta é uma emenda pontual, como a da F5 registrada na ADR-002: as demais
   tools determinísticas continuam sem backend.

## Alternativas consideradas

- **Preset como badge publicada** (com URL e cache): mistura dois conceitos —
  snapshot de formulário e badge servido — e exigiria analytics, expiração e
  política de exclusão próprias. Não é o que o usuário pediu.
- **Preset como lista de parâmetros de URL**: perderia o formato estruturado e
  a validação por campo; a URL não é a fonte de verdade do formulário.
- **Guardar o logo como FK**: a exclusão do logo viraria cascade silencioso ou
  restrict; sem FK, o órfão é explícito e a leitura decide.
- **Gestão apenas no painel**: obrigaria sair do builder para cada ajuste; os
  diálogos mantêm o contexto do formulário.
- **Validar o SVG no navegador antes do upload**: a autoridade é o parser do
  servidor (allowlist e limites); duplicar a regra no cliente criaria duas
  verdades e não impediria um POST manual.

## Consequências

### Positivas

- Reaproveitar configuração sem publicar nada e sem custo de render público.
- Órfão é um estado explícito, não um erro de carregamento.
- Mesmo contrato para builder e painel: uma alteração de tipo aparece nos dois.
- A regra “tool local” continua auditável por teste.

### Negativas / Trade-offs

- O builder ganhou componentes que falam com o servidor; a exceção precisa ser
  lembrada em ADR-002 e nos guias.
- Presets ilimitados podem crescer; o teto ficou em config para ser mudado sem
  deploy de código.
- Um preset que usa logo excluído aplica sem logo; o usuário precisa escolher
  outro conscientemente (a UI avisa).
- A página da conta duplica parte da marcação dos diálogos (lista/renomear/
  excluir); a duplicação é aceita para manter cada superfície simples.
