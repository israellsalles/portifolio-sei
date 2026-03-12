# Melhorias do Catalogo Baseadas em Claude.md e GPT.md

## Objetivo

Extrair informacoes operacionais dos documentos de referencia e transformar em melhorias praticas para o sistema **Catalogo de Sistemas SEI**.

Fontes analisadas:

- `C:\Users\israelsantos\Documents\MEGA\Obsidian Vault\DOCUMENTAÇÃO\Claude.md`
- `C:\Users\israelsantos\Documents\MEGA\Obsidian Vault\DOCUMENTAÇÃO\GPT.md`

## 1) Informacoes extraidas (insumos de negocio e operacao)

### 1.1 Sistemas-alvo e familias

Os documentos consolidam os grupos de sistemas mais relevantes:

- Catalogo e metadados: GeoNetwork
- Editorial e bibliografico: OJS, PHL
- Coleta e pesquisa: ODK Central, LimeSurvey
- Dashboards e analise: R Shiny
- Portais CMS: WordPress/Drupal
- Apps institucionais PHP
- Infra de apoio: bancos, proxy reverso, SSL

### 1.2 Padrao de infraestrutura e stack

- SO base: Oracle Linux (pacotes via `dnf`)
- Hospedagem: PRODEB
- Banco: MySQL e PostgreSQL
- Containerizacao: Docker / Docker Compose
- Publicacao externa: proxy reverso + HTTPS
- Rede: VPN para acessos internos

### 1.3 Operacao recorrente

Padroes recorrentes nos dois documentos:

- Rotina diaria/semanal/mensal
- Backup + restore com frequencia definida
- Processo de atualizacao com validacao e rollback
- Observabilidade por logs e healthcheck
- Gestao de credenciais em cofre e rotacao periodica
- Escalonamento de incidentes (infra, banco, fornecedor, PRODEB)

### 1.4 Governanca e qualidade de documentacao

Padrao recomendado por sistema:

- identificacao completa (responsaveis, fornecedor, criticidade, ambiente)
- arquitetura (componentes, dependencias, integracoes)
- acessos/perfis e procedimento de concessao/revogacao
- procedimentos operacionais (subir/parar/publicar)
- banco e scripts uteis
- seguranca, vulnerabilidades e mitigacoes
- troubleshooting + historico de mudancas

## 2) Gap entre o catalogo atual e os insumos

Ja coberto no sistema atual:

- cadastro de sistemas, VMs e bases
- vinculo por ambiente (prod/hml/dev)
- status, criticidade, owner e campos de suporte
- backup/exportacao e arquivamento
- diagnostico tecnico de VM (PHP/R)

Nao coberto (ou coberto parcialmente):

- finalidade de negocio e area demandante por sistema
- fornecedor/mantenedor e contatos de escalonamento
- dependencias e integracoes internas/externas
- runbook operacional (start/stop/deploy/rollback) por sistema
- rotina operacional com periodicidade (diaria/semanal/mensal)
- controle de backup por sistema (ultimo sucesso, evidencias, RPO/RTO)
- inventario de seguranca (2FA, firewall, SSL, auditoria, vulnerabilidades)
- historico formal de mudancas e incidentes por sistema

## 3) Melhorias recomendadas no Catalogo

## 3.1 Modelo de dados (prioridade alta)

Adicionar entidades:

1. `system_operation_profiles`
   - finalidade_negocio
   - area_demandante
   - fornecedor_mantenedor
   - criticidade_negocio
   - data_ultima_revisao_documentacao
2. `system_dependencies`
   - tipo (`interna`, `externa`, `integracao`, `biblioteca_critica`)
   - descricao
   - sistema_relacionado (opcional)
3. `system_runbooks`
   - procedimento_subida
   - procedimento_parada
   - procedimento_publicacao
   - plano_rollback
   - validacao_pos_deploy
4. `system_backups`
   - tipo_backup
   - periodicidade
   - local_armazenamento
   - ultima_execucao
   - ultimo_status
5. `system_security_controls`
   - ssl_https
   - 2fa_admin
   - firewall
   - auditoria_logs
   - vulnerabilidades_conhecidas
   - mitigacoes
6. `system_incidents`
   - data_hora
   - sintoma
   - causa
   - acao
   - escalonamento
   - impacto

## 3.2 Interface (prioridade alta/media)

Adicionar abas no detalhe de sistema:

1. `Arquitetura e Dependencias`
2. `Operacao (Runbook)`
3. `Backup e Restore`
4. `Seguranca`
5. `Incidentes e Mudancas`

Adicionar indicadores no dashboard:

- % sistemas com runbook completo
- % sistemas com backup validado nos ultimos N dias
- % sistemas com owner + escalonamento definidos
- % sistemas sem evidencia de teste de restore

## 3.3 Automacoes (prioridade media)

1. Job de verificacao de URL/healthcheck por sistema
2. Job de validade de certificado SSL por dominio
3. Alertas de desatualizacao de revisao documental
4. Alertas para sistemas criticos sem backup recente

## 3.4 Governanca (prioridade media)

1. Workflow de revisao trimestral por sistema
2. Campo `responsavel_revisao` + `data_revisao`
3. Exportacao de relatorio de conformidade operacional (CSV/PDF)

## 4) Backlog priorizado (pronto para execucao)

P0 (curto prazo):

1. Criar campos de negocio/operacao essenciais em sistema
2. Criar aba Runbook com texto estruturado
3. Criar aba Backup com status e periodicidade
4. Criar relatorio "sistemas sem documentacao minima"

P1 (medio prazo):

1. Modulo de dependencias e integracoes
2. Modulo de seguranca e vulnerabilidades
3. Modulo de incidentes e historico de mudancas
4. Indicadores de conformidade no dashboard

P2 (evolutivo):

1. Healthchecks automatizados e alarmes
2. Controle de SLA por criticidade
3. Integracao com chamados/escalonamento (PRODEB)

## 5) Seeds recomendados para iniciar o cadastro

Criar sistemas-base no catalogo (se ainda nao existirem):

- GeoNetwork
- OJS
- ODK Central
- LimeSurvey
- R Shiny Server
- Apps PHP Institucionais
- Plataformas CMS (WordPress/Drupal)

Com campos padrao preenchidos:

- stack principal
- banco principal
- modelo de backup
- rotina operacional minima
- contatos de escalonamento

## 6) Criterio minimo de "sistema bem documentado"

Um sistema so deve ser considerado completo quando tiver:

1. identificacao funcional + responsaveis
2. arquitetura e dependencias
3. runbook de operacao e deploy/rollback
4. backup/restore com ultima evidencia
5. seguranca e vulnerabilidades
6. troubleshooting e escalonamento

---

Este documento pode ser usado como base para abrir issues e planejar sprints de melhoria do Catalogo de Sistemas SEI.
