# ADR-048: Doacoes com Pix dinamico e fallback estatico

Status: Aceito
Data: 2026-10-21
Relaciona: ADR-017 (PaymentGateway), ADR-027 (venda manual), ADR-045 (Checkout Pro e Preapproval), ADR-047 (webhooks)

## Contexto

A pagina de doacao existia como promessa: o rodape e a home apontavam para um Pix estatico copiado e colado, sem registro, sem confirmacao e sem como o admin saber quanto entrou. A F6 trouxe o gateway, e a doacao passou a ser um caso de uso real: pessoa fisica, qualquer valor, sem cadastro, Pix.

Duas realidades precisavam caber na mesma pagina:

- **com credenciais**: o provedor cria o Pix dinamico, com QR proprio, expiracao e confirmacao por webhook;
- **sem credenciais** (dev, ambiente novo, provedor fora): ainda precisa ser possivel doar.

Alem disso, a doacao nao pode virar receita na fatura nem consumir cota - ela e dinheiro de entrada, nao plano.

## Decisao

1. **Pagina publica `/{locale}/donate`**: sem `auth`, indexavel, com valores sugeridos e valor livre. Sugeridos, minimo, maximo, moeda e tempo de expiracao vem de `config/donations.php` (env), nunca do codigo (CLAUDE.md). Default: R$ 5, 10, 25 e 50; livre entre R$ 1 e R$ 1.000. Sugerido fora do intervalo e descartado na leitura.
2. **Doador anonimo permitido**: nome e e-mail sao opcionais; a conta logada pre-preenche os dois, mas nao e exigida. Doacao sem nome aparece como "Anonimo" no admin.
3. **Pix dinamico quando o gateway esta disponivel**: `POST /v1/payments` com `payment_method_id=pix`, `external_reference = don_<ULID>`, `notification_url` e e-mail do pagador (o do doador ou o fallback `DONATIONS_PAYER_EMAIL`, porque o provedor exige um). A doacao nasce `pending` com `ulid` publico, `qr_code`, `qr_code_base64`, `ticket_url` e `expires_at` (default 60 min). A resposta redireciona para a pagina de status daquela doacao.
4. **Pix estatico como fallback**: sem credenciais e com chave configurada, o QR e o copia-e-cola sao montados localmente por `PixPayload` (EMV + CRC16-CCITT) com o `ulid` como txid e o QR em SVG (`bacon/bacon-qr-code`, ja presente via Fortify, declarado no `composer.json`), entregue como data URI. A tela diz, em texto, que **nao ha confirmacao automatica**. `gateway = manual`.
5. **Sem gateway e sem chave**: a pagina fica em modo informativo; o POST recusa com mensagem traduzida (`DonationException::unavailable`) e nao cria linha.
6. **Status publico por ULID**: `GET /donate/{donation:ulid}` mostra valor, estado, datas e o QR **apenas enquanto pendente** (pago ou vencido nao tem mais QR). Nao mostra nome nem e-mail do doador; e `noindex` e entra no sitemap do lado da doacao, nao do status.
7. **Confirmacao so por webhook**: `payment` aprovado com referencia `don_<ulid>` (ou `gateway_ref` gravado na criacao) chama `ConfirmDonation`, que confere valor e moeda com a doacao gravada e so transita `pending -> paid` (lock + checagem dentro da transacao). Repetir nao muda nada. Recusado/cancelado marca `failed`; estornado/`charged_back` marca `refunded`.
8. **Recibo opcional**: `DonationReceipt` (`ShouldQueue`) sai **somente** na transicao para pago, **somente** com e-mail e **somente** com `DONATIONS_SEND_RECEIPTS` ligado. Usa o locale gravado na doacao, nao o locale da requisicao.
9. **Vencimento**: `expires_at` e resolvido na leitura (`effectiveStatus()`: pendente vencido aparece `expired`) e gravado por `payments:expire` a cada cinco minutos. Um Pix aprovado depois do vencimento local ainda confirma: o dinheiro entrou, e a expiracao local e so conveniencia de tela.
10. **Admin ve tudo**: aba Doacoes em `admin/payments` com busca por nome, e-mail e ULID, filtro por status **efetivo** (um pendente vencido filtra como `expired`, e o Pix estatico nunca expira) e detalhe com QR e ticket. A doacao nao entra em `payments` nem em fatura e nao libera cota.

## Por que

- **Mesma pagina, dois modos**: um fallback quebrado em dev e em ambiente sem credencial faria a funcionalidade parecer morta; o estatico cobre o intervalo ate o provedor estar configurado.
- **Pix dinamico quando possivel**: o QR do provedor confirma sozinho, expira e concilia valor; o estatico nao tem como avisar que alguem pagou.
- **Doacao separada de `payments`**: `payments` e registro fiscal de plano, ligada a fatura e a cota; misturar doacao faria as duas mentirem. `donations` tem o proprio ciclo de vida.
- **Valor congelado no registro**: confirmar contra o valor gravado, e nao contra o configurado, impede que uma mudanca de preco no meio do caminho altere o que a pessoa doou.
- **ULID publico**: a pagina de status nao expoe id sequencial nem dados do doador; quem tem o link ve a propria doacao, e mais ninguem precisa.
- **Recibo condicionado**: sem e-mail nao ha para quem mandar; anonimo continua valido. Um recibo repetido por webhook duplicado seria ruido.
- **Sem confirmacao automatica no estatico**: prometer confirmacao seria mentir para quem doou; o texto deixa claro que o comprovante vem do banco.

## Consequencias

- `donations` guarda nome, e-mail e valor: e dado pessoal e precisa entrar na rotina de anonimizacao/exclusao da conta quando houver `user_id`.
- A pagina de status e publica por link: quem tem o ULID ve a doacao; nao ha listagem publica.
- `payments:expire` e o unico job que troca `pending -> expired`; nada mais regride.
- Sem segredo de webhook o Pix dinamico nao confirma automaticamente; a tela continua mostrando pendente e o admin investiga.
- O QR estatico embute o ULID como txid; um QR antigo continua valido no banco do doador, mas a tela ja o escondeu.
- SEO: `/donate` e indexavel com hreflang; o status nao e.

## Alternativas descartadas

- **So Pix dinamico**: quebra em dev e em qualquer ambiente sem credencial; a pagina ficaria inutil justamente quando alguem quer doar.
- **So Pix estatico**: sem confirmacao e sem registro de quem pagou; o admin teria que conciliar extrato manualmente.
- **Gravar doacao em `payments`**: contaminaria fatura, cota e relatorios fiscais.
- **Exigir conta para doar**: reduziria a conversao e contraria a ideia de doacao aberta.
- **Exigir nome e e-mail**: impossibilitaria a doacao anonima, que foi decisao de produto.
- **Confirmar pelo corpo do webhook**: contrario ao ADR-047; valor e moeda vem da API.
- **Biblioteca JS de QR**: nova dependencia no front; o QR ja podia ser gerado no servidor com o que estava instalado.

## Emenda (ADR-059, 2026-10-27)

- A transicao de confirmacao e `pending|expired -> paid` (o §9 ja dizia que um Pix aprovado depois do vencimento local confirma; o codigo agora concorda).
- `donations` guarda `gateway_status` e `gateway_status_detail`, gravados na criacao, no webhook e na consulta sob demanda; o motivo da recusa chega ao doador traduzido e o codigo cru fica no admin.
- A pagina de status se atualiza a cada `DONATIONS_STATUS_POLL_MS` (so banco local) e oferece "Ja paguei" (`POST /donate/{ulid}/check`), com limite por IP e `donations:check {ulid}` para o operador.
- O prazo do QR ficou `max(30, (int) (env('DONATIONS_EXPIRES_MINUTES') ?: 60))`: chave vazia cai em 60 e o piso subiu de 5 para 30 minutos.
