# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: adiciona página pública de contato com envio de e-mail
```

---

## 🎯 Resumo

Cria um canal oficial de suporte e relacionamento com o usuário: uma página pública de contato (`/{locale}/contact`) indexável, disponível nos seis idiomas suportados. Antes, quem precisava reportar bug, tirar dúvida sobre as ferramentas ou tratar problemas de doação não tinha canal dentro do produto — a página de doações só mencionava "fale conosco" sem destino. A mudança fecha esse ciclo: a mensagem é persistida para histórico, notificada por e-mail à administração de forma assíncrona e respondida via `replyTo` direto ao remetente.

## 🛠️ Mudanças Técnicas

- Nova action `CreateContactMessage` que persiste a mensagem e enfileira o e-mail de notificação (`Mail::to(...)->queue(...)`), condicionada à existência de destinatário configurado.
- `ContactController` (index/store) com Inertia, pré-preenchimento de nome/e-mail para usuários autenticados e toast de sucesso.
- `StoreContactMessageRequest` com validação de campos, lista fechada de assuntos, sanitização (trim/normalização de vazio para `null`) e rate limit de 5 envios/minuto por IP com mensagem traduzida informando os segundos restantes.
- Proteção anti-spam por honeypot (`hp_website`) descartado silenciosamente — bot recebe sucesso falso, sem gravar no banco nem disparar e-mail.
- `ContactMessageReceived` (Mailable `ShouldQueue`) renderizando template Markdown, com `replyTo` do remetente e locale de fallback.
- Migration `contact_messages` com `user_id` opcional (`nullOnDelete`), assunto, mensagem, IP e user agent.
- Página Vue `Contact.vue` com contador de caracteres (limite 3000), estados de envio/sucesso e bloqueio de submit acima do limite.
- Traduções completas de `contact`, `mail.contact` e ajuste do rodapé em en, es, fr, pt_BR e pt_PT; página de doações passa a linkar para o contato.
- Inclusão da rota no `Sitemap`, metadados de SEO e no resolvedor de layout público.
- Testes: feature (renderização multi-idioma, pré-preenchimento, envio, validação, honeypot, rate limit) e unitário em Vitest para o componente.

## ⚠️ Impacto/Avisos

- **Banco de dados**: nova migration `create_contact_messages_table` — requer `php artisan migrate` em todos os ambientes.
- **Variável de ambiente**: `CONTACT_EMAIL` (fallback `devtools@natanfiuza.dev.br` em `config/contact.php`). Sem ela configurada, as mensagens continuam sendo salvas, mas nenhum e-mail é enviado.
- **Fila**: o Mailable implementa `ShouldQueue`; é obrigatório haver worker de queue ativo, caso contrário as notificações ficam pendentes e não chegam à administração.
- **Rate limit**: usa o store de `RateLimiter` — em ambientes com cache `array` o limite não é compartilhado entre processos.
- **Privacidade/LGPD**: passam a ser armazenados IP e user agent do remetente, além de dados de contato; recomenda-se cobrir esse tratamento na política de privacidade.
- Sem novas dependências de terceiros; nenhuma alteração em rotas existentes.

close #84

---

[![GitPR](https://img.shields.io/badge/GitPR-1_error_%C2%B7_15_warnings-red)](https://gitpr.natanfiuza.dev.br/)