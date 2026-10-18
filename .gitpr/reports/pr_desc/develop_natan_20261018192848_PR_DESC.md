# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: adiciona traduções de mensagens de validação em cinco idiomas
```

---

## 🎯 Resumo

Usuários de diferentes regiões recebiam mensagens de validação do Laravel em inglês, já que o projeto não possuía arquivos de tradução para o namespace `validation`. Esta mudança adiciona os arquivos de tradução para pt-BR, pt-PT, en, es e fr, garantindo que erros de formulário (obrigatoriedade, tamanho mínimo, confirmação de senha, etc.) sejam exibidos no idioma selecionado pelo usuário — incluindo a tradução dos nomes dos atributos (ex.: `password` → `senha` / `palavra-passe` / `contraseña` / `mot de passe`).

Além disso, a cobertura de testes foi ampliada para validar o comportamento multilíngue, evitando regressões silenciosas quando novas regras de validação ou idiomas forem adicionados.

## 🛠️ Mudanças Técnicas

- Adiciona `lang/pt_BR/validation.php`, `lang/pt_PT/validation.php`, `lang/en/validation.php`, `lang/es/validation.php` e `lang/fr/validation.php` com o conjunto completo de mensagens de validação do Laravel (incluindo regras compostas como `between`, `gt`, `lt`, `size` e `password`).
- Inclui o bloco `attributes` em cada idioma, traduzindo nomes de campos de domínio (nome, e-mail, documento, forma de pagamento, código de recuperação, papel, etc.) para mensagens naturais ao usuário final.
- Ajusta `TranslationsParityTest` para incluir os namespaces `passwords` e `validation` na verificação de paridade entre locales.
- Amplia `RegistrationTest` com três novos cenários data-driven nos cinco idiomas: senha abaixo do tamanho mínimo, confirmação de senha divergente e campo obrigatório vazio.

## ⚠️ Impacto/Avisos

- **Sem alterações de banco de dados, migrations ou variáveis de ambiente.**
- **Sem novas dependências** — apenas arquivos de resource de idioma do Laravel.
- **Atenção ao teste de paridade:** o namespace `passwords` passou a ser exigido em todos os locales. Caso algum idioma ainda não possua `lang/<locale>/passwords.php`, o teste `TranslationsParityTest` falhará — verifique a existência desses arquivos antes do merge.
- **Manutenção:** ao adicionar novas regras de validação do Laravel, será necessário replicá-las nos cinco arquivos para manter a paridade.
- Mensagens em pt-PT usam variantes regionais (“aceite”, “carateres”, “palavra-passe”, “ficheiro”), distintas do pt-BR — revisar caso o produto opte por unificar a variante brasileira.


close #37

---

[![GitPR](https://img.shields.io/badge/GitPR-no_issues-brightgreen)](https://gitpr.natanfiuza.dev.br/)