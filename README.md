# Catálogo de Sistemas SEI

Aplicacao web em PHP para catalogar sistemas, maquinas virtuais (VMs), bases de dados e diagnosticos tecnicos.

## Sumario

1. [Visao geral](#visao-geral)
2. [Funcionalidades](#funcionalidades)
3. [Arquitetura e stack](#arquitetura-e-stack)
4. [Requisitos](#requisitos)
5. [Como executar localmente](#como-executar-localmente)
6. [Persistencia e migracao de banco](#persistencia-e-migracao-de-banco)
7. [Uso da aplicacao](#uso-da-aplicacao)
8. [API interna](#api-interna)
9. [Modelo do JSON de diagnostico](#modelo-do-json-de-diagnostico)
10. [Estrutura de pastas](#estrutura-de-pastas)
11. [Troubleshooting](#troubleshooting)
12. [Contribuicao](#contribuicao)
13. [Guia de revisao de codigo](#guia-de-revisao-de-codigo)
14. [Licenca](#licenca)

## Visao geral

O projeto centraliza informacoes de:

- Sistemas (cadastro funcional e tecnico)
- VMs de producao, homologacao e desenvolvimento
- Bases de dados vinculadas a sistemas e VMs
- Diagnosticos de ambiente por VM com suporte a JSON de PHP e R
- Itens arquivados (soft delete) com restauracao
- Acesso inicial publico em modo visualizacao (aba `Sistemas`, secoes 1 e 5)
- Login opcional para perfis com edicao: `editor` e `admin`
- Exportacoes CSV e backup completo em JSON com restauracao
- Normalizacao de texto UTF-8 para reduzir problemas de acentuacao legada

A interface oferece multiplas visoes: cards, lista detalhada, DNS, bases, maquinas, relatorio por VM, arquivados e dashboard.

## Funcionalidades

### Sistemas

- Cadastro e edicao de sistemas com campos tecnicos e operacionais
- Vinculo de 3 ambientes por sistema: producao, homologacao e desenvolvimento
- Multiplas URLs por ambiente (uma por linha)
- Filtros por nome, categoria, grupo, status, VM, acesso, administracao e setor
- Arquivamento e restauracao de sistemas
- Exclusao permanente apenas de sistemas ja arquivados

### Maquinas Virtuais (VMs)

- Cadastro de VM com:
  - Nome e IP
  - Categoria: `Producao`, `Homologacao`, `Desenvolvimento`
  - Tipo: `Sistemas` ou `SGBD`
  - Acesso: `Interno` ou `Externo`
  - Administracao: `SEI` ou `PRODEB`
  - SO, vCPU, RAM, Disco
  - Linguagem (ex.: `PHP`, `R`) separada de Tecnologias (ex.: `Apache`, `Shiny Server`)
- Para VMs tipo `SGBD`, exige ao menos uma instancia `Nome - IP`
- Aba `Maquinas` com coluna `Linguagem` exibindo versoes detectadas via diagnostico (quando houver JSON referenciado)
- Relatorio por VM com consolidacao de sistemas e bases vinculadas
- Arquivamento e restauracao de VMs
- Exclusao permanente apenas de VMs arquivadas e com desvinculo aplicado

### Bases de dados

- Cadastro de base vinculado a um sistema e VM principal
- Se houver VM de homologacao, exige instancia homolog correspondente
- Validacao de instancia com base nas instancias cadastradas na VM
- Edicao e exclusao permanente de base

### Documentacao de sistemas (PDF)

- Upload de documentos PDF por tipo:
  - `installation` (instalacao)
  - `maintenance` (manutencao)
  - `security` (seguranca)
  - `manual` (manual/procedimentos)
- Visualizacao inline do PDF salvo
- Exclusao de documento por tipo
- Controle de permissao no backend para upload/remocao

### Chamados

- Registro de chamados vinculados a:
  - sistema
  - maquina virtual
- Edicao e exclusao de chamados existentes
- Listagem de historico por tipo de alvo

### Importacao/Exportacao CSV de maquinas

- Exportacao de inventario de maquinas em CSV
- Pre-visualizacao de importacao CSV com validacoes
- Aplicacao em lote com resumo de:
  - registros criados
  - registros atualizados
  - registros ignorados
- Sinalizacao de maquinas ativas ausentes no CSV para revisao manual (sem exclusao automatica)

### Diagnostico JSON por VM

- Pagina dedicada: `vm_diagnostic.php?id=<vm_id>`
- Importar JSON por tecnologia e salvar referencia para a VM (`PHP` e/ou `R`)
- Atualizar JSON referenciado por tecnologia
- Limpar referencia e remover arquivo salvo por tecnologia
- Comparar JSON da VM atual com outra VM que tenha diagnostico salvo
- Resumo visual de PHP, extensoes e diretivas INI
- Campo de Linguagem com versoes detectadas (PHP/R) e tabela de pacotes/versoes para diagnostico de R

Observacao: na interface principal, o botao de diagnostico aparece para VMs do tipo `Sistemas` com linguagem contendo `PHP` e/ou `R`.
Observacao: o acesso a `vm_diagnostic.php` exige usuario autenticado.

### Dashboard e listas

- Dashboard com cards e metricas agregadas
- Alertas de qualidade de cadastro (ex.: sistema sem URL, VM sem SO, base sem homologacao)
- Lista tecnica por secoes (sistemas, infraestrutura, bases, suporte, operacao)
- Visao DNS (URL x IP)
- Visao de arquivados (sistemas e VMs)

### Controle de acesso

- Sem login, a aplicacao abre em modo publico de consulta:
  - somente aba `Sistemas`
  - somente secoes `1. Sistemas` e `5. Contatos e Suporte`
  - sem acesso a detalhes, cadastros, importacoes ou exportacoes
- O botao `Login` direciona para `login.php` para liberar recursos de edicao/admin
- Perfis com login:
  - `edicao`: cadastro/edicao/arquivamento/restauracao
  - `admin`: tudo do perfil edicao + exclusoes permanentes + backup completo (exportar/restaurar) + gerenciamento de usuarios
- Troca de senha do usuario autenticado pela interface
- Aba `Usuarios` exclusiva de admin para gerenciar contas:
  - criar usuario
  - editar login, nome, perfil e status
  - redefinir senha de outro usuario (esqueceu senha)
  - excluir usuario
- Salvaguardas de seguranca no backend:
  - nao permite excluir o proprio usuario
  - nao permite desativar o proprio usuario
  - nao permite remover/excluir o ultimo admin ativo
- O backend valida permissao por endpoint (nao e apenas bloqueio visual)

## Arquitetura e stack

- Backend: PHP (sem framework)
- Banco: SQLite (`SQLite3` ou fallback `PDO SQLite`)
- Frontend: HTML, CSS, JavaScript vanilla
- Armazenamento de diagnosticos: arquivos JSON em `data/vm_diagnostics/`

Arquitetura simplificada:

- `index.php`
  - Renderiza UI
  - Roteia chamadas para API via `?api=<acao>`
- `app/bootstrap.php`
  - Inicializa banco
  - Cria/migra schema automaticamente
  - Semeia dados iniciais quando vazio
- `app/api.php`
  - Regras de negocio e endpoints JSON
- `vm_diagnostic.php`
  - UI dedicada para diagnosticos por VM

## Requisitos

- PHP 8.0+ (usa funcoes como `str_ends_with`)
- Extensao `sqlite3` habilitada
  - Ou `pdo_sqlite` como alternativa
- Permissao de escrita em `data/`

## Como executar localmente

No diretorio raiz do projeto:

```bash
php -S localhost:8000
```

Abra no navegador:

- `http://localhost:8000/index.php`

## Persistencia e migracao de banco

### Arquivo principal

- Banco alvo: `data/bd_sei_catalogosistema.db`

### Comportamento de migracao automatica

Ao iniciar:

1. O sistema usa como alvo `data/bd_sei_catalogosistema.db`.
2. Se existir banco legado `sysportfolio.db` (na raiz ou em `data/`), ele pode ser migrado automaticamente para o novo nome.
3. Se `data/` nao puder ser criado/usado, o sistema tenta usar um diretorio temporario em `sys_get_temp_dir()/bd_sei_catalogosistema`.
4. Tabelas e colunas necessarias sao criadas/atualizadas automaticamente.

### Seed inicial

Se nao houver sistemas cadastrados, o app insere 3 exemplos iniciais automaticamente.

### Git ignore

`data/*.db` esta ignorado por padrao no repositorio.

## Uso da aplicacao

### Fluxo recomendado

1. Acesse `index.php` para consulta publica (modo visualizacao)
2. Clique em `Login` para autenticar como `editor` ou `admin` quando precisar editar
3. Cadastre VMs (aba `Maquinas`)
4. Cadastre sistemas vinculando VMs por ambiente
5. Cadastre bases vinculadas a sistemas/VMs
6. Use `Dashboard` e `Relatorio VM` para analise
7. Arquive itens antigos em vez de excluir diretamente

### Usuarios iniciais (seed)

Na primeira inicializacao, se a tabela `users` estiver vazia, o sistema cria:

- `admin` (perfil `admin`)
- `editor` (perfil `edicao`)

Senha inicial:

- se `SEI_ADMIN_PASSWORD` e `SEI_EDITOR_PASSWORD` estiverem definidas, esses valores sao usados;
- caso contrario, o sistema gera senhas aleatorias e registra no log do PHP no bootstrap.

Recomendacao: alterar as senhas iniciais no ambiente de producao.

### Arquivamento x exclusao

- Sistema:
  - `archive` marca sistema e bases vinculadas como arquivados
  - `restore` reativa sistema e bases vinculadas
  - `delete` so remove definitivamente se o sistema estiver arquivado
- VM:
  - nao pode ser arquivada se estiver vinculada a sistemas ou bases ativos
  - `delete` so remove definitivamente se a VM estiver arquivada
  - ao excluir VM arquivada, referencias em sistemas/bases sao limpas

## API interna

As rotas usam `index.php?api=<acao>` e retornam JSON no formato:

```json
{ "ok": true, "data": ... }
```

ou

```json
{ "ok": false, "error": "mensagem" }
```

Observacao de seguranca:

- Endpoints publicos: `auth-status`, `login`, `logout` e `list`.
- Todos os demais endpoints exigem autenticacao.

### Endpoints GET

- `auth-status` - status de autenticacao da sessao atual
- `list` - lista sistemas ativos (publico; sem login retorna apenas secoes 1 e 5)
- `vm-list` - lista VMs ativas (requer login)
- `db-list` - lista bases ativas (requer login)
- `archived-list` - lista sistemas e VMs arquivados (requer login)
- `ticket-list` - lista chamados (requer login)
- `dns-public-ip-resolve&hosts=<lista>` - resolve IP publico por host (requer login)
- `dns-ssl-validity-resolve&targets=<host:porta>` - consulta validade SSL (requer login)
- `dns-internal-ip-resolve&hosts=<lista>` - resolve IP interno por host (requer login)
- `system-doc-view&id=<system_id>&doc_type=<tipo>` - abre PDF de documentacao (requer login)
- `system-php-compat-get&id=<system_id>` - detalha compatibilidade PHP (requer login)
- `system-r-compat-get&id=<system_id>` - detalha compatibilidade R (requer login)
- `vm-csv-export` - exporta maquinas em CSV (edicao/admin)
- `vm-diagnostic-get&id=<vm_id>` - retorna referencia e conteudo do diagnostico da VM (requer login)
- `export-csv&scope=systems|vms|databases` - exportacao CSV (edicao/admin)
- `backup-export` - backup completo (admin)
- `user-list` - lista usuarios (admin)

### Endpoints POST

- `login` - autentica usuario
- `logout` - encerra sessao
- `change-password` - altera a senha do usuario autenticado
- `save` - cria/atualiza sistema (edicao/admin)
- `archive` - arquiva sistema (edicao/admin)
- `restore` - restaura sistema (edicao/admin)
- `delete` - exclui sistema definitivamente (somente arquivado, admin)
- `vm-save` - cria/atualiza VM (edicao/admin)
- `vm-archive` - arquiva VM (edicao/admin)
- `vm-restore` - restaura VM (edicao/admin)
- `vm-delete` - exclui VM definitivamente (somente arquivada, admin)
- `db-save` - cria/atualiza base de dados (edicao/admin)
- `db-delete` - exclui base de dados (edicao/admin)
- `vm-diagnostic-save` - salva JSON de diagnostico e vincula na VM (edicao/admin, aceita `tech=php|r`)
- `vm-diagnostic-clear` - remove referencia/arquivo de diagnostico da VM (edicao/admin, aceita `tech=php|r`)
- `ticket-save` - cria chamado (edicao/admin)
- `ticket-update` - atualiza chamado (edicao/admin)
- `ticket-delete` - remove chamado (edicao/admin)
- `system-doc-upload` - envia PDF de documentacao de sistema (edicao/admin)
- `system-doc-delete` - remove PDF de documentacao de sistema (edicao/admin)
- `vm-csv-import-preview` - pre-valida arquivo CSV de maquinas antes da aplicacao (edicao/admin)
- `vm-csv-import-apply` - aplica importacao CSV de maquinas (edicao/admin)
- `backup-restore` - restaura backup JSON completo (admin)
- `user-save` - cria/atualiza usuario e opcionalmente redefine senha (admin)
- `user-delete` - exclui usuario (admin, com protecao para ultimo admin ativo)

## Modelo do JSON de diagnostico

### JSON de PHP

O JSON de PHP deve conter obrigatoriamente:

- `php` (objeto)
- `extensions` (array)
- `ini` (array)

Exemplo minimo valido:

```json
{
  "php": {
    "version": "8.2.0",
    "sapi": "apache2handler",
    "os": "Linux"
  },
  "extensions": [
    { "name": "curl", "version": "8.2.0" },
    { "name": "openssl", "version": "8.2.0" }
  ],
  "ini": [
    {
      "directive": "memory_limit",
      "local_value": "256M",
      "global_value": "256M",
      "access": "ALL"
    }
  ]
}
```

### JSON de R

O JSON de R pode seguir o formato atualizado com metadados e lista de pacotes:

```json
{
  "r_version": ["R version 4.5.2 (2025-10-31)"],
  "platform": ["x86_64-redhat-linux-gnu"],
  "total_packages": [263],
  "packages": [
    { "Package": "base", "Version": "4.5.2" },
    { "Package": "dplyr", "Version": "1.2.0" }
  ]
}
```

Compatibilidade: o formato antigo (lista pura de pacotes) continua aceito.

Arquivos validados sao salvos em:

- `data/vm_diagnostics/vm_<id>_<tech>_<timestamp>_<nome_arquivo>.json`

## Estrutura de pastas

```text
.
|- index.php
|- vm_diagnostic.php
|- app/
|  |- bootstrap.php
|  |- api.php
|- assets/
|  |- style.css
|  |- app.js
|- data/
|  |- (bd_sei_catalogosistema.db e vm_diagnostics/ em runtime)
```

## Troubleshooting

### Erro de SQLite indisponivel

Mensagem tipica:

- `SQLite indisponivel neste PHP. Habilite sqlite3 ou pdo_sqlite no php.ini.`

Acao:

- habilitar `sqlite3` ou `pdo_sqlite` no `php.ini`
- reiniciar servidor PHP

### Erro de permissao ao criar banco ou diagnosticos

Acao:

- garantir permissao de escrita na pasta `data/`
- em ambiente restrito, verificar se o fallback para pasta temporaria foi aplicado

### API sem resposta JSON valida

Acao:

- confirmar que o servidor foi iniciado na raiz do projeto
- acessar por `http://localhost:8000/index.php`
- validar versao/extensoes do PHP em uso

## Contribuicao

Sugestao de fluxo:

1. Criar branch de trabalho
2. Implementar alteracoes
3. Testar fluxos principais (sistemas, VMs, bases e diagnostico)
4. Abrir PR com descricao objetiva

## Guia de revisao de codigo

Para revisao tecnica detalhada (mapa de codigo, fluxo de dados, seguranca, integridade e checklist manual), consulte:

- `docs/code-review-guide.md`

## Licenca

Este repositorio nao possui arquivo de licenca definido no momento.
