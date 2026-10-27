# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: exibe retenção do histórico em dias para planos pagos
```

---

## 🎯 Resumo

O painel de conversão do Base64 Image exibia sempre a retenção do histórico em horas (`Histórico disponível por 720h`), o que dificultava a leitura para planos pagos com janelas longas. Esta mudança introduz um formatador que converte automaticamente a retenção para dias quando o valor passa de 24h, tornando a comunicação do benefício mais clara e compreensível para o usuário final.

## 🛠️ Mudanças Técnicas

- Adiciona `formatRetentionTime(hours, t)` em `lib/formatters.ts`, exibindo horas até 24h e dias acima disso, com chave de singular (`retention_info_day`) e plural (`retention_info_days`).
- Atualiza `EncoderPanel.vue` para usar o novo formatador no lugar da interpolação direta de `retentionHours`.
- Adiciona as chaves `retention_info_day` e `retention_info_days` nos arquivos de tradução `en`, `es`, `fr`, `pt_BR` e `pt_PT`.
- Inclui testes unitários para o formatador (1h, 12h, 24h, 25h, 48h e 720h) e testes de componente cobrindo os cenários Free (24h) e Pro (720h → 30 dias).
- Reorganiza/realinha o template do `EncoderPanel.vue` conforme formatação padrão do projeto (sem alteração funcional no layout).
- Atualiza a documentação de plano, relatório e survey referentes à feature.

## ⚠️ Impacto/Avisos

- **Banco de dados:** nenhuma migração ou alteração de schema.
- **Variáveis de ambiente:** nenhuma nova variável ou alteração de configuração.
- **Dependências:** nenhuma dependência adicionada ou atualizada.
- **i18n:** as novas chaves precisam existir em todos os locales; traduções faltantes resultam em fallback exibindo a chave crua.
- **Regra de exibição:** valores entre 24h e 36h são arredondados para "1 dia" (uso de `Math.round`); revisar caso o produto prefira arredondamento para baixo.

close #101

---

[![GitPR](https://img.shields.io/badge/GitPR-no_issues-brightgreen)](https://gitpr.natanfiuza.dev.br/)