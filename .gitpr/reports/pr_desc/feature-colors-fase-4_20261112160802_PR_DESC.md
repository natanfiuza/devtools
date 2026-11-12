# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
fix: adiciona chave i18n do conta-gotas e teste de guarda
```

---

## 🎯 Resumo

O seletor de cores compartilhado exibia o rótulo literal `tools.shared.color_picker.preview.eyedropper` na interface: o vue-i18n devolve a própria chave quando ela não existe, então a ausência da tradução passava silenciosamente pela paridade entre idiomas e pela compilação. Este PR adiciona o texto nos cinco idiomas suportados e cria uma guarda automatizada que compara as chaves estáticas usadas nos componentes com o que os arquivos de `lang/` realmente entregam, evitando que o problema volte a passar despercebido.

## 🛠️ Mudanças Técnicas

- Adiciona `preview.eyedropper` em `tools.shared.color_picker` nos locales `en`, `es`, `fr`, `pt_BR` e `pt_PT`.
- Extrai a leitura de `lang/` (via `config/locales.php` + `php -r`, sem bootstrap do Laravel) para o helper reutilizável `resources/js/__tests__/support/langMessages.ts`, exportando `exportMessages`, `NamespaceMessages` e `ExportedMessages`.
- Refatora `i18n-messages.test.ts` para consumir o helper, removendo o script PHP e a tipagem duplicados.
- Novo `shared-i18n-keys.test.ts`: carrega os `.vue` de `components/colors/` como texto bruto (`import.meta.glob` com `?raw`) e valida que toda chave estática `tools.shared.color_picker.*` existe e é string não vazia nos cinco idiomas.
- Chaves montadas dinamicamente em template (`...formats.${format}`) ficam explicitamente fora do escopo da guarda.

## ⚠️ Impacto/Avisos

- **Sem mudanças em banco de dados, variáveis de ambiente ou dependências.**
- Os testes de i18n continuam exigindo o binário `php` disponível no `PATH` do ambiente de CI/desenvolvimento.
- A guarda cobre apenas chaves literais; inclusões futuras com interpolação dinâmica não são verificadas por este teste.
- Nenhuma alteração de contrato de API ou de comportamento funcional além da correção do rótulo exibido.

close #147

---

[![GitPR](https://img.shields.io/badge/GitPR-no_issues-brightgreen)](https://gitpr.natanfiuza.dev.br/)