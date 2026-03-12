# Guia de Revisao de Codigo

Objetivo: oferecer um mapa tecnico da base para revisao humana (arquitetura, fluxo de dados, seguranca, integridade e checklist de validacao).

## 1) Visao Geral da Arquitetura

Fluxo principal:

1. `index.php` renderiza a interface e roteia chamadas API via `?api=<acao>`.
2. `assets/app.js` controla estado em memoria, renderizacao e chamadas `fetch`.
3. `app/api.php` valida entrada/permissoes e executa regras de negocio.
4. `app/bootstrap.php` abre SQLite e garante schema/migracoes automaticamente.
5. Banco de dados persistido em `data/bd_sei_catalogosistema.db`.

Arquivos chave:

- `index.php`: ponto de entrada HTTP e shell da UI.
- `assets/app.js`: camada de apresentacao + orquestracao de dados.
- `app/api.php`: endpoints JSON e normalizacao de payload.
- `app/bootstrap.php`: inicializacao de banco e migracao estrutural.
- `app/constants.php`: papeis, enums e limites de rate-limit.
- `vm_diagnostic.php`: pagina especializada de diagnostico PHP/R por VM.

## 2) Mapa de Responsabilidades por Arquivo

### `index.php`

- Inclui `bootstrap.php` e `api.php`.
- Quando `$_GET['api']` existe, chama `handleApiRequest()` e retorna JSON.
- Quando nao existe, entrega HTML + carrega `assets/app.js`.
- Gera token CSRF em `meta[name="csrf-token"]`.

Referencias:
- `index.php:8`
- `index.php:21`
- `index.php:427`

### `assets/app.js`

- Estado global da aplicacao:
  - `App.items` (sistemas)
  - `App.vms` (maquinas)
  - `App.databases` (bases)
  - `App.archived` (arquivados)
  - `App.auth` (sessao atual)
- Funcao `api(action, body)` centraliza chamadas HTTP.
- `boot()` carrega autenticacao e dados iniciais.
- `refreshAll()` faz carga paralela de listas ativas/arquivadas.
- Funcoes de escrita:
  - `saveSystem()`
  - `saveVm()`
  - `saveDb()`
  - `archive/restore/delete` para sistemas e VMs.

Referencias:
- `assets/app.js:1`
- `assets/app.js:116`
- `assets/app.js:2062`
- `assets/app.js:2190`
- `assets/app.js:2283`
- `assets/app.js:2388`
- `assets/app.js:2444`

### `app/api.php`

- Implementa `handleApiRequest()` com roteamento por acao.
- Leitura de JSON via `php://input` para rotas POST.
- Controle de permissao por grupos:
  - admin-only: `delete`, `vm-delete`, `backup-export`, `backup-restore`
  - edicao+: `save`, `archive`, `restore`, `vm-save`, `vm-archive`, `vm-restore`, `db-save`, `db-delete`, `vm-diagnostic-save`, `vm-diagnostic-clear`
- Endpoints de leitura:
  - `list`, `vm-list`, `db-list`, `archived-list`
- Endpoints de escrita:
  - `save`, `archive`, `restore`, `delete`
  - `vm-save`, `vm-archive`, `vm-restore`, `vm-delete`
  - `db-save`, `db-delete`
- Funcoes de normalizacao relevantes:
  - `normalizeUtf8Text()`
  - `normalizeUrlListValue()` / `packUrlListValue()`
  - `normalizeVmInstancesValue()` / `resolveVmInstance()`
  - `normalizeSystemRow()`
  - `normalizeDatabaseRow()`

Referencias:
- `app/api.php:31`
- `app/api.php:72`
- `app/api.php:101`
- `app/api.php:105`
- `app/api.php:139`
- `app/api.php:169`
- `app/api.php:822`
- `app/api.php:1104`
- `app/api.php:1288`
- `app/api.php:1294`
- `app/api.php:1300`
- `app/api.php:1306`
- `app/api.php:1312`
- `app/api.php:1318`
- `app/api.php:1764`
- `app/api.php:1784`
- `app/api.php:1804`
- `app/api.php:2098`
- `app/api.php:2129`
- `app/api.php:2145`
- `app/api.php:2189`
- `app/api.php:2349`
- `app/api.php:2366`
- `app/api.php:2413`

### `app/bootstrap.php`

- Resolve caminho de banco (`DB_PATH`) com fallback para `data/` ou pasta temporaria.
- Cria/atualiza tabelas:
  - `systems`
  - `virtual_machines`
  - `system_databases`
  - `users`
  - `login_attempts`
- Migra dados legados (VM textual para VM relacional).
- Garante chaves estrangeiras com migracao de tabela quando necessario.
- Garante indices relacionais.
- Seed inicial de usuarios (se tabela vazia).

Referencias:
- `app/bootstrap.php:20`
- `app/bootstrap.php:112`
- `app/bootstrap.php:210`
- `app/bootstrap.php:440`
- `app/bootstrap.php:486`
- `app/bootstrap.php:548`
- `app/bootstrap.php:637`
- `app/bootstrap.php:763`
- `app/bootstrap.php:805`

### `vm_diagnostic.php`

- Exige sessao autenticada para abrir a pagina.
- Le/usa token CSRF de sessao.
- Fluxo JS proprio para:
  - carregar diagnostico por VM
  - salvar/limpar referencia por tecnologia (`php` ou `r`)
  - comparar diagnostico entre VMs

Referencias:
- `vm_diagnostic.php:1`
- `vm_diagnostic.php:751`
- `vm_diagnostic.php:770`
- `vm_diagnostic.php:1052`
- `vm_diagnostic.php:1101`

## 3) Fluxos de Dados (Passo a Passo)

### 3.1 Carga inicial da tela principal

1. `boot()` chama `fetchAuthStatus()`.
2. Em seguida chama `refreshAll()`.
3. `refreshAll()` executa em paralelo:
   - `list`
   - `vm-list`
   - `db-list`
   - `archived-list`
4. Frontend armazena em `App.*` e renderiza a view ativa.

### 3.2 Escrita de sistema

1. Formulario monta objeto em `saveSystem()`.
2. URL(s) sao normalizadas no frontend (`joinUrlList`).
3. API `save` valida `name`, normaliza status/URLs e persiste.
4. Retorno contem registro normalizado (incluindo listas de URL).
5. Frontend executa `refreshAll()` para atualizar telas/filtros.

### 3.3 Escrita de VM

1. `saveVm()` valida nome/IP e regras para VM `SGBD`.
2. Instancias (`Nome - IP`) viram lista estruturada.
3. API `vm-save` valida e persiste.
4. Frontend recarrega listas com `refreshAll()`.

### 3.4 Escrita de base

1. `saveDb()` exige sistema + VM + nome base + instancia principal.
2. API `db-save` revalida IDs e resolve instancia contra VM selecionada.
3. Persistencia com `INSERT`/`UPDATE`.
4. Frontend atualiza estado local de bases.

### 3.5 Arquivamento e exclusao

- `archive/restore` em sistemas alteram tambem `system_databases` relacionadas.
- `vm-archive` bloqueia arquivo de VM em uso por sistemas/bases ativos.
- `delete` de sistema so e permitido se estiver arquivado.
- `vm-delete` so e permitido se VM estiver arquivada; referencias sao limpas.

## 4) Seguranca e Controle de Acesso

### Sessao e CSRF

- Sessao iniciada com cookie `httponly`, `samesite=Lax`.
- CSRF token de sessao obrigatorio para POST (exceto `login`).
- Frontend injeta token no header `X-CSRF-Token`.

Referencias:
- `app/api.php:884`
- `app/api.php:892`
- `app/api.php:1111`
- `assets/app.js:116`

### Autenticacao e autorizacao

- Login com validacao de senha hash (`password_verify`).
- Rate-limit por IP:
  - max tentativas: `LOGIN_MAX_ATTEMPTS` (10)
  - janela: `LOGIN_WINDOW_SECS` (300s)
- Roles:
  - `leitura`
  - `edicao`
  - `admin`
- Backend sempre revalida permissao por endpoint.

Referencias:
- `app/constants.php:4`
- `app/constants.php:38`
- `app/api.php:1153`
- `app/api.php:1172`
- `app/api.php:1288`
- `app/api.php:1294`

## 5) Integridade de Dados

- Schema relacional com FK:
  - `systems.vm_* -> virtual_machines.id` (`ON DELETE SET NULL`)
  - `system_databases.system_id -> systems.id` (`ON DELETE CASCADE`)
  - `system_databases.vm_* -> virtual_machines.id` (`ON DELETE SET NULL`)
- `ensureRelationalSchema*` reconstrui tabelas quando FK esperadas nao existem.
- `normalizeRelationalData*` limpa referencias invalidas antes da migracao.

Referencias:
- `app/bootstrap.php:440`
- `app/bootstrap.php:486`
- `app/bootstrap.php:512`
- `app/bootstrap.php:548`

## 6) Checklist de Revisao Humana

Use esta lista no code review funcional:

1. Inicializacao
   - Aplicacao sobe com `php -S localhost:8000`.
   - Banco e tabelas sao criados sem erro.
2. Seguranca
   - POST sem CSRF retorna erro.
   - Usuario `leitura` nao consegue editar.
   - `admin` consegue backup/export e exclusao definitiva.
3. CRUD Sistemas
   - Criar, editar, arquivar, restaurar e excluir (apos arquivar).
   - Validar persistencia de URLs multiplas.
4. CRUD VMs
   - Regra de VM `SGBD` exigindo instancia.
   - Bloqueio de arquivamento quando VM esta em uso ativo.
5. CRUD Bases
   - Validacao de instancia vinculada a VM selecionada.
   - VM homolog exige instancia homolog.
6. Diagnostico VM
   - Upload/validacao JSON PHP.
   - Upload/validacao JSON R.
   - Limpar referencia e comparar com outra VM.
7. Backup/restore
   - Export JSON completo.
   - Restore mantendo coerencia relacional.

## 7) Pontos de Atencao Tecnica (Nao Bloqueantes)

1. `assets/app.js` e `app/api.php` concentram muitas responsabilidades (alto acoplamento).
2. Duplicacao de logica SQLite3/PDO aumenta custo de manutencao.
3. `vm_diagnostic.php` contem CSS + JS inline, sem modularizacao.
4. Cobertura de testes automatizados nao esta presente no repositorio.

Esses itens nao impedem operacao, mas devem ser considerados no roadmap tecnico.
