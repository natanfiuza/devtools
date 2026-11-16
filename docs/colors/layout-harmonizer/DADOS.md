# Persistência e quota
Auditar esquema real; proposta discriminador layout-system no armazenamento Colors existente, não criar tabela paralela automaticamente. Snapshot, dono, nome/descrição/tags, privado padrão, origem opcional, versão, datas e arquivamento separado de exclusão.
Quota total conjunta paletas+sistemas, arquivados incluídos. Criar/duplicar protege concorrência transacional entre ambos os tipos. Pago ilimitado funcional, não implica ausência de proteção de infraestrutura. Edição não cria item; save-as e cópia criam.
Ao downgrade acima de 25, manter visualização/edição sem nova criação, leitura/exportação e dados; criação só com capacidade. Nenhuma exclusão automática.
Origem privada nunca revelada no viewer/export público; fonte pública pode ter crédito. Ausência de fonte não bloqueia abrir snapshot. Links não reutilizam identificador após exclusão, mesmo se nome igual; auditar slug/ULID existentes e garantir sem reuso.
Arquivamento retira público e sitemap até restaurar. Mudar privado revoga viewer e recursos derivados públicos. Status HTTP 404 ou 410 e revalidação de cache devem seguir padrões reais, sem revelar privado.
Rascunho local único, limpar explicitamente; login pede importar para estado da ferramenta sem salvar automaticamente.
Não gerar novas OGs/armazenamentos por padrão: reutilizar estratégia Colors distinguindo estáticos e dados de usuário. Nada gerado por usuário vai ao Git.
