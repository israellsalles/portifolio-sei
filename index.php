<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'constants.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'api.php';

if (isset($_GET['api'])) {
  handleApiRequest();
  exit;
}

// Iniciar sessao para gerar/ler token CSRF para a pagina HTML
startAppSession();
$csrfToken = $_SESSION['csrf_token'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
<title>Catálogo de Sistemas SEI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'style.css') ?>">
</head>
<body>
<header>
  <a href="index.php" class="logo">
    <div class="logo-name">Catálogo de Sistemas<br>SEI</div>
  </a>
  <div class="tabs">
    <button id="tab-lista" class="tab active" onclick="setView('lista')">&#9776; Sistemas</button>
    <button id="tab-cards" class="tab" onclick="setView('cards')">&#128451; Card</button>
    <button id="tab-dns" class="tab" onclick="setView('dns')">&#127760; DNS</button>
    <button id="tab-maquinas" class="tab" onclick="setView('maquinas')">&#128187; Maquinas</button>
    <button id="tab-bases" class="tab" onclick="setView('bases')">&#128187; Bases</button>
    <button id="tab-vm-relatorio" class="tab" onclick="setView('vm-relatorio')">&#128202; Relatório VM</button>
    <button id="tab-chamados" class="tab" onclick="setView('chamados')">&#128221; Chamados</button>
    <button id="tab-arquivados" class="tab" onclick="setView('arquivados')">&#128230; Arquivados</button>
    <button id="tab-usuarios" class="tab hidden" onclick="setView('usuarios')">&#128100; Usuarios</button>
    <button id="tab-dashboard" class="tab" onclick="setView('dashboard')">&#128202; Dashboard</button>
    <button id="tab-diagrama" class="tab" onclick="openDiagramExternal()">&#128279; Diagrama</button>
  </div>
  <div class="sp"></div>
  <div id="auth-box" class="auth-box hidden">
    <span id="auth-label" class="auth-label"></span>
    <button id="auth-change-password" class="btn btn-header" type="button">Senha</button>
    <button id="auth-logout" class="btn btn-header" type="button">Sair</button>
  </div>
  <button id="auth-open-login" class="btn btn-header" type="button">Login</button>
  <button id="btn-export" class="btn btn-header" type="button">Exportar</button>
  <button id="btn-backup" class="btn btn-header" type="button">Restaurar</button>
  <button id="top-action" class="btn-primary hidden" onclick="runPrimaryAction()">+ Novo Sistema</button>
</header>
<main>
  <div id="toolbar" class="toolbar">
    <input id="q" class="search" type="text" placeholder="&#128269; Buscar por nome, linguagem..." oninput="renderCurrent()">
    <select id="cat" onchange="renderCurrent()"></select>
    <select id="groupf" onchange="renderCurrent()"></select>
    <select id="st" onchange="renderCurrent()"></select>
    <select id="vmf" onchange="renderCurrent()"></select>
    <select id="accessf" onchange="renderCurrent()"></select>
    <select id="adminf" onchange="renderCurrent()"></select>
    <select id="sectorf" onchange="renderCurrent()"></select>
    <select id="sort" onchange="renderCurrent()">
      <option value="name">Ordenar: Nome</option>
      <option value="category">Ordenar: Categoria</option>
      <option value="status">Ordenar: Status</option>
      <option value="criticality">Ordenar: Criticidade</option>
    </select>
    <div class="form-actions systems-toolbar-actions">
      <button id="sys-csv-export-prod-btn" class="btn" type="button" onclick="exportSystemsCsv('producao')">Exportar CSV Producao</button>
      <button id="sys-csv-export-hml-btn" class="btn" type="button" onclick="exportSystemsCsv('homologacao')">Exportar CSV Homologacao</button>
    </div>
    <span id="result-count" class="result-count"></span>
  </div>
  <div id="loading">Carregando sistemas...</div>
  <section id="view-dashboard" class="view">
    <div id="stats" class="stats"></div>
    <div class="dash-grid">
      <div class="panel"><h3>Por Status</h3><div id="status-bars"></div></div>
      <div class="panel"><h3>Por Categoria</h3><div id="category-bars"></div></div>
      <div class="panel"><h3>VMs por Ambiente</h3><div id="vm-category-bars"></div></div>
      <div class="panel"><h3>SGBD / Versão</h3><div id="db-engine-bars"></div></div>
      <div class="panel"><h3>Qualidade do Cadastro</h3><div id="quality-list"></div></div>
      <div class="panel"><h3>Atenção Necessária</h3><div id="attention-list"></div></div>
    </div>
  </section>
  <section id="view-lista" class="view">
    <div class="list-sections">
      <div class="list-section">
        <div class="list-section-title">1. Sistemas</div>
        <div class="table-wrap">
          <table class="list-desc-table">
            <thead><tr><th>Nome</th><th>Categoria</th><th>Grupo</th><th>Criticidade</th><th>Descricao</th><th>Observacoes</th><th>Status</th></tr></thead>
            <tbody id="list-desc-body"></tbody>
          </table>
        </div>
      </div>
      <div class="list-section">
        <div class="list-section-title">2. Informações Técnicas</div>
        <div class="table-wrap">
          <table class="list-main-table list-compact-table">
            <thead><tr><th>Nome</th><th>Sistema</th><th>Versão</th><th>Linguagem</th><th>Versão Alvo</th><th>Servidor Aplicação</th><th>Web Server</th><th>Containerização</th><th>Ferramenta Container</th><th>Porta App</th><th>Porta Web</th><th>Compatibilidade</th></tr></thead>
            <tbody id="list-main-body"></tbody>
          </table>
        </div>
      </div>
      <div class="list-section">
        <div class="list-section-title">3. Infraestrutura</div>
        <div class="table-wrap">
          <table class="list-infra-table list-compact-table">
            <thead><tr><th>Nome</th><th>URL</th><th>URL Homologacao</th><th>VM Producao</th><th>IP Producao</th><th>VM Homologacao</th><th>IP Homologacao</th><th>VM Desenvolvimento</th><th>IP Desenvolvimento</th><th>Acesso</th><th>Administracao</th></tr></thead>
            <tbody id="list-infra-body"></tbody>
          </table>
        </div>
      </div>
      <div class="list-section">
        <div class="list-section-title">4. Bases de Dados</div>
        <div class="table-wrap">
          <table class="list-db-table list-compact-table">
            <thead><tr><th>Nome</th><th>Base de Dados</th><th>Usuario do Banco</th><th>Maquina</th><th>Administracao</th><th>IP da Instancia</th><th>Porta da Instancia</th><th>Instancia SGBD</th><th>VM Homologacao</th><th>IP da Instancia Homologacao</th><th>Porta da Instancia Homologacao</th><th>Instancia SGBD Homologacao</th><th>Observacoes</th></tr></thead>
            <tbody id="list-db-body"></tbody>
          </table>
        </div>
      </div>
      <div class="list-section">
        <div class="list-section-title">5. Contatos e Suporte</div>
        <div class="table-wrap">
          <table class="list-support-table list-compact-table">
            <thead><tr><th>Nome</th><th>Responsavel Tecnico</th><th>Setor Responsavel</th><th>Coordenador Responsavel</th><th>Ramal</th><th>Email</th><th>Suporte</th><th>Contato Suporte</th></tr></thead>
            <tbody id="list-support-body"></tbody>
          </table>
        </div>
      </div>
      <div class="list-section">
        <div class="list-section-title">6. Deploy e Empacotamento</div>
        <div class="table-wrap">
          <table class="list-ops-table list-compact-table">
            <thead><tr><th>Nome</th><th>Analytics</th><th>Diretorio</th><th>Tamanho</th><th>Repositorio</th></tr></thead>
            <tbody id="list-ops-body"></tbody>
          </table>
        </div>
      </div>
      <div class="list-section">
        <div class="list-section-title">7. Documentação</div>
        <div class="table-wrap">
          <table class="list-docs-table list-compact-table">
            <thead><tr><th>Nome</th><th>Instalação</th><th>Manutenção</th><th>Segurança</th><th>Manual/Procedimentos</th></tr></thead>
            <tbody id="list-docs-body"></tbody>
          </table>
        </div>
      </div>
    </div>
    <div id="list-cards" class="list-mobile-cards"></div>
  </section>
  <section id="view-cards" class="view active">
    <div class="panel">
      <div class="panel-head">
        <h3>Sistemas</h3>
        <div class="status-legend" aria-label="Legenda de status dos cards">
          <span class="status-legend-item"><span class="status-legend-dot status-legend-active"></span>Ativo</span>
          <span class="status-legend-item"><span class="status-legend-dot status-legend-maintenance"></span>Manutenção</span>
          <span class="status-legend-item"><span class="status-legend-dot status-legend-deprecated"></span>Depreciado</span>
          <span class="status-legend-item"><span class="status-legend-dot status-legend-implementation"></span>Implantação</span>
          <span class="status-legend-item"><span class="status-legend-dot status-legend-suspended"></span>Suspenso</span>
        </div>
      </div>
      <div id="systems-cards" class="systems-cards-grid"></div>
    </div>
  </section>
  <section id="view-dns" class="view">
    <div class="panel">
      <div class="panel-head">
        <h3>DNS (URL x IP)</h3>
        <div class="form-actions dns-actions">
          <button id="dns-csv-export-btn" class="btn" type="button" onclick="exportDnsCsv()">Exportar CSV</button>
          <button id="dns-csv-domain-ip-btn" class="btn" type="button" onclick="exportDnsDomainIpCsv()">Exportar Dominio/IP</button>
        </div>
      </div>
      <div class="dns-filters">
        <div class="field dns-filter-field">
          <label>Dominio</label>
          <select id="dnsf-domain" onchange="renderDns()">
            <option value="">Dominio: Todos</option>
          </select>
        </div>
        <div class="field dns-filter-field">
          <label>URL Ambiente</label>
          <select id="dnsf-url-env" onchange="renderDns()">
            <option value="">URL Ambiente: Todas</option>
          </select>
        </div>
        <div class="field dns-filter-field">
          <label>IP Interno</label>
          <select id="dnsf-internal-ip" onchange="renderDns()">
            <option value="">IP Interno: Todos</option>
          </select>
        </div>
        <div class="field dns-filter-field">
          <label>IP Publico</label>
          <select id="dnsf-public-ip" onchange="renderDns()">
            <option value="">IP Publico: Todos</option>
          </select>
        </div>
        <div class="field dns-filter-field">
          <label>Validade SSL</label>
          <select id="dnsf-ssl" onchange="renderDns()">
            <option value="">Validade SSL: Todas</option>
          </select>
        </div>
      </div>
        <div class="table-wrap">
          <table class="dns-table compact-table">
          <thead><tr><th>URL</th><th>IP Interno</th><th>WAF</th><th>IP Publico (NAT)</th><th>Validade SSL</th><th>Cert. Bundle</th></tr></thead>
          <tbody id="dns-body"></tbody>
          </table>
        </div>
      <div id="dns-cards" class="dns-mobile-cards"></div>
    </div>
  </section>
  <section id="view-bases" class="view">
    <div class="panel">
      <div class="panel-head">
        <h3>Bases de Dados</h3>
        <div class="form-actions db-export-actions">
          <button id="db-csv-export-prod-btn" class="btn" type="button" onclick="exportDatabasesCsv('producao')">Exportar CSV Producao</button>
          <button id="db-csv-export-hml-btn" class="btn" type="button" onclick="exportDatabasesCsv('homologacao')">Exportar CSV Homologacao</button>
        </div>
      </div>
      <div class="table-wrap">
        <table class="bases-table compact-table">
          <thead><tr><th>Sistema</th><th>Base de Dados</th><th>Usuario do Banco</th><th>Maquina</th><th>Administracao</th><th>IP da Instancia</th><th>Porta da Instancia</th><th>Instancia SGBD</th><th>VM Homologacao</th><th>IP da Instancia Homologacao</th><th>Porta da Instancia Homologacao</th><th>Instancia SGBD Homologacao</th><th>Observacoes</th></tr></thead>
          <tbody id="db-body"></tbody>
        </table>
      </div>
      <div id="db-cards" class="db-mobile-cards"></div>
    </div>
  </section>
  <section id="view-chamados" class="view">
    <div class="panel">
      <div class="panel-head">
        <h3>Cadastro de Chamados</h3>
      </div>
      <div class="calls-form-grid">
        <div class="field">
          <label>Tipo *</label>
          <select id="fcall_target_type" onchange="syncCallTargetFields()">
            <option value="system" selected>Sistema</option>
            <option value="vm">Maquina</option>
          </select>
        </div>
        <div id="call-system-field" class="field">
          <label>Sistema *</label>
          <select id="fcall_system_id"></select>
        </div>
        <div id="call-vm-field" class="field hidden">
          <label>Maquina *</label>
          <select id="fcall_vm_id"></select>
        </div>
        <div class="field">
          <label>Numero do Chamado *</label>
          <input id="fcall_number" placeholder="Ex: 2026-12345">
        </div>
        <div class="field calls-desc">
          <label>Descricao *</label>
          <textarea id="fcall_description" placeholder="Descreva a solicitacao"></textarea>
        </div>
        <div class="form-actions calls-actions">
          <input id="fcall_id" type="hidden" value="">
          <button id="bcall-save" class="btn btn-save" type="button">Registrar Chamado</button>
          <button id="bcall-cancel" class="btn hidden" type="button">Cancelar Edicao</button>
        </div>
      </div>
    </div>

    <div class="dash-grid calls-history-grid">
      <div class="panel">
        <h3>Histórico de Chamados - Sistemas</h3>
        <div class="table-wrap">
          <table class="calls-table compact-table">
            <thead><tr><th>Sistema</th><th>Numero</th><th>Descricao</th><th>Registrado em</th><th>Acoes</th></tr></thead>
            <tbody id="calls-system-body"></tbody>
          </table>
        </div>
      </div>
      <div class="panel">
        <h3>Histórico de Chamados - Maquinas</h3>
        <div class="table-wrap">
          <table class="calls-table compact-table">
            <thead><tr><th>Maquina</th><th>Numero</th><th>Descricao</th><th>Registrado em</th><th>Acoes</th></tr></thead>
            <tbody id="calls-vm-body"></tbody>
          </table>
        </div>
      </div>
    </div>
  </section>
  <section id="view-maquinas" class="view">
    <div class="panel">
      <div class="panel-head">
        <h3>Maquinas Virtuais</h3>
        <div class="form-actions vm-csv-actions">
          <button id="vm-csv-export-btn" class="btn" type="button" onclick="exportMachinesCsv()">Exportar CSV</button>
          <button id="vm-csv-import-btn" class="btn btn-save" type="button" onclick="triggerMachinesCsvImport()">Importar CSV</button>
          <div class="vm-csv-note">Nota: ao importar CSV, use exatamente estes cabecalhos e nesta ordem: Nome de Servidor;Administra&ccedil;&atilde;o;Sistema Operacional;Endere&ccedil;o IP;vCPU;Mem&oacute;ria (GB);Storage (GB).</div>
        </div>
      </div>
      <div class="toolbar vm-filters">
        <input id="vmq" class="search vm-filter-search" type="text" placeholder="Buscar maquina, IP, SO..." oninput="renderMachines()">
        <select id="vmcatf" onchange="renderMachines()"></select>
        <select id="vmtypef" onchange="renderMachines()"></select>
        <select id="vmosf" onchange="renderMachines()"></select>
        <select id="vmadminf" onchange="renderMachines()"></select>
        <span id="vm-result-count" class="result-count"></span>
      </div>
      <div id="vm-sections" class="vm-sections"></div>
    </div>
  </section>
  <section id="view-vm-relatorio" class="view">
    <div class="panel">
      <div class="panel-head">
        <h3>Relatório por Máquina</h3>
      </div>
      <div class="toolbar vm-filters">
        <input id="vmrq" class="search vm-filter-search" type="text" placeholder="Buscar maquina, IP, SO..." oninput="renderVmReportTab()">
        <select id="vmrcatf" onchange="renderVmReportTab()"></select>
        <select id="vmrtypef" onchange="renderVmReportTab()"></select>
        <select id="vmrosf" onchange="renderVmReportTab()"></select>
        <select id="vmradminf" onchange="renderVmReportTab()"></select>
        <span id="vmr-result-count" class="result-count"></span>
      </div>
      <div id="vm-report" class="vm-report"></div>
    </div>
  </section>
  <section id="view-arquivados" class="view">
    <div class="dash-grid archived-grid">
      <div class="panel">
        <h3>Sistemas Arquivados</h3>
        <div class="table-wrap">
          <table class="archived-systems-table compact-table">
            <thead><tr><th>Nome</th><th>Sistema</th><th>Status</th><th>VM Producao</th><th>VM Homologacao</th><th>Arquivado em</th><th style="width:150px">Acoes</th></tr></thead>
            <tbody id="archived-systems-body"></tbody>
          </table>
        </div>
      </div>
      <div class="panel">
        <h3>Maquinas Arquivadas</h3>
        <div class="table-wrap">
          <table class="archived-vms-table compact-table">
            <thead><tr><th>Nome da Maquina</th><th>IP</th><th>Total Sistemas</th><th>Arquivado em</th><th style="width:150px">Acoes</th></tr></thead>
            <tbody id="archived-vms-body"></tbody>
          </table>
        </div>
      </div>
    </div>
  </section>
  <section id="view-usuarios" class="view">
    <div class="panel">
      <div class="panel-head">
        <h3>Gerenciamento de Usuarios</h3>
        <div class="form-actions users-head-actions">
          <button id="users-new-btn" class="btn btn-save" type="button" onclick="openUserForm()">Novo Usuario</button>
        </div>
      </div>
      <div class="table-wrap">
        <table class="users-table compact-table">
          <thead>
            <tr>
              <th>Usuario</th>
              <th>Nome Completo</th>
              <th>Perfil</th>
              <th>Status</th>
              <th>Atualizado em</th>
              <th style="width:140px">Acoes</th>
            </tr>
          </thead>
          <tbody id="users-body"></tbody>
        </table>
      </div>
    </div>
  </section>
</main>
<input id="backup-file" type="file" accept=".json,application/json" class="hidden">
<input id="vm-csv-file" type="file" accept=".csv,text/csv" class="hidden">
<div id="mauth" class="modal-bg hidden" onclick="closeModal('mauth')">
  <div class="modal auth-modal" onclick="event.stopPropagation()">
    <div class="modal-head">
    <div class="modal-title">Acesso ao Sistema</div>
    <button class="close" onclick="closeModal('mauth')">&#10005;</button>
    </div>
    <div class="form">
      <div class="field"><label>Usuario</label><input id="auth-username" autocomplete="username" placeholder="admin"></div>
      <div class="field"><label>Senha</label><input id="auth-password" type="password" autocomplete="current-password" placeholder="••••••••"></div>
      <div class="form-actions">
        <button id="auth-login" class="btn btn-save" type="button">Entrar</button>
      </div>
      <div class="auth-hint">Login para perfis com edicao: editor e admin.</div>
    </div>
  </div>
</div>
<div id="mvmcsvpreview" class="modal-bg hidden">
  <div class="modal modal-vm-csv-preview" onclick="event.stopPropagation()">
    <div class="modal-head">
      <div class="modal-title">Pré-visualização de Importação de Máquinas</div>
      <button class="close" onclick="closeModal('mvmcsvpreview')">&#10005;</button>
    </div>
    <div class="form">
      <div id="vm-csv-preview-summary" class="vm-csv-preview-summary"></div>
      <div class="table-wrap">
        <table class="vm-csv-preview-table compact-table">
          <thead>
            <tr>
              <th>Linha</th>
              <th>Ação</th>
              <th>Novo Cadastro</th>
              <th>Máquina</th>
              <th>IPs</th>
              <th>Administração</th>
              <th>Sistema Operacional</th>
              <th>vCPU</th>
              <th>Memória<br>(GB)</th>
              <th>Storage<br>(GB)</th>
              <th>Detalhe</th>
            </tr>
          </thead>
          <tbody id="vm-csv-preview-body"></tbody>
        </table>
      </div>
      <div class="form-actions">
        <button class="btn" type="button" onclick="closeModal('mvmcsvpreview')">Cancelar</button>
        <button id="vm-csv-apply-btn" class="btn btn-save" type="button" onclick="confirmMachinesCsvImport()">Confirmar Atualização</button>
      </div>
    </div>
  </div>
</div>
<div id="mpassword" class="modal-bg hidden">
  <div class="modal auth-modal" onclick="event.stopPropagation()">
    <div class="modal-head"><div class="modal-title">Trocar Senha</div><button class="close" onclick="closeModal('mpassword')">&#10005;</button></div>
    <div class="form">
      <div class="field"><label>Senha Atual</label><input id="pwd-current" type="password" autocomplete="current-password" placeholder="••••••••"></div>
      <div class="field"><label>Nova Senha</label><input id="pwd-new" type="password" autocomplete="new-password" placeholder="••••••••"></div>
      <div class="field"><label>Confirmar Nova Senha</label><input id="pwd-confirm" type="password" autocomplete="new-password" placeholder="••••••••"></div>
      <div class="form-actions">
        <button class="btn" type="button" onclick="closeModal('mpassword')">Cancelar</button>
        <button id="pwd-save" class="btn btn-save" type="button">Atualizar Senha</button>
      </div>
      <div class="auth-hint">Use pelo menos 8 caracteres.</div>
    </div>
  </div>
</div>
<div id="muser" class="modal-bg hidden" onclick="closeModal('muser')">
  <div class="modal auth-modal" onclick="event.stopPropagation()">
    <div class="modal-head">
      <div id="user-form-title" class="modal-title">Novo Usuario</div>
      <button class="close" onclick="closeModal('muser')">&#10005;</button>
    </div>
    <div class="form">
      <input id="fuser_id" type="hidden">
      <div class="row2">
        <div class="field"><label>Usuario *</label><input id="fuser_username" placeholder="ex: novo.usuario"></div>
        <div class="field"><label>Nome Completo</label><input id="fuser_full_name" placeholder="Nome do usuario"></div>
      </div>
      <div class="row2">
        <div class="field">
          <label>Perfil *</label>
          <select id="fuser_role">
            <option value="edicao">Edicao</option>
            <option value="admin">Admin</option>
          </select>
        </div>
        <div class="field">
          <label>Status *</label>
          <select id="fuser_active">
            <option value="1">Ativo</option>
            <option value="0">Inativo</option>
          </select>
        </div>
      </div>
      <div class="row2">
        <div class="field"><label>Nova Senha</label><input id="fuser_password" type="password" autocomplete="new-password" placeholder="********"></div>
        <div class="field"><label>Confirmar Nova Senha</label><input id="fuser_password_confirm" type="password" autocomplete="new-password" placeholder="********"></div>
      </div>
      <div id="fuser_password_hint" class="auth-hint">Senha obrigatoria para novo usuario (minimo 8 caracteres).</div>
      <div class="form-actions">
        <button class="btn" type="button" onclick="closeModal('muser')">Cancelar</button>
        <button id="fuser_delete" class="btn btn-danger hidden" type="button" onclick="deleteCurrentUser()">Excluir Usuario</button>
        <button id="fuser_save" class="btn btn-save" type="button" onclick="saveUser()">Salvar Usuario</button>
      </div>
    </div>
  </div>
</div>
<div id="mbackup" class="modal-bg hidden">
  <div class="modal" onclick="event.stopPropagation()">
    <div class="modal-head"><div class="modal-title">Exportação e Backup</div><button class="close" onclick="closeModal('mbackup')">&#10005;</button></div>
    <div class="form">
      <div class="field"><label>Backup completo</label></div>
      <div class="form-actions backup-actions">
        <button id="backup-export-json" class="btn btn-save" type="button">Exportar Backup JSON</button>
        <button id="backup-import-btn" class="btn btn-danger" type="button">Restaurar Backup JSON</button>
      </div>
    </div>
  </div>
</div>
<div id="mform" class="modal-bg hidden">
  <div class="modal" onclick="event.stopPropagation()">
    <div class="modal-head"><div id="ftitle" class="modal-title">Novo Sistema</div><button class="close" onclick="closeModal('mform')">&#10005;</button></div>
    <div class="form">
      <input id="fid" type="hidden">
      <div class="row3">
        <div class="field"><label>Nome *</label><input id="fname" oninput="toggleSave()" placeholder="Ex: Projeto"></div>
        <div class="field"><label>Sistema</label><input id="fsystem" placeholder="Framework"></div>
        <div class="field"><label>Versão</label><input id="fver" placeholder="Ex: 4.2.x"></div>
      </div>
      <div class="row3">
        <div class="field"><label>Categoria</label><input id="fcat" placeholder="Ex: GIS"></div>
        <div class="field"><label>Grupo</label><input id="fgroup" placeholder="Ex: Corporativo"></div>
        <div class="field"><label>Status</label><select id="fst"><option selected>Ativo</option><option>Manutenção</option><option>Depreciado</option><option>Implantação</option><option>Suspenso</option></select></div>
      </div>
      <div class="row3">
        <div class="field"><label>Criticidade</label><select id="fcrit"><option>Alta</option><option selected>Media</option><option>Baixa</option></select></div>
        <div class="field"><label>Responsavel</label><input id="fowner"></div>
        <div class="field"><label>URLs (uma por linha)</label><textarea id="furl" placeholder="https://site-a...&#10;https://site-b..."></textarea></div>
      </div>
      <div class="row3">
        <div class="field"><label>Maquina (Producao)</label><select id="fvm_id" onchange="syncSystemTechFromVms()"></select></div>
        <div class="field"><label>Maquina (Homologacao)</label><select id="fvm_homolog_id" onchange="syncSystemTechFromVms()"></select></div>
        <div class="field"><label>Maquina (Desenvolvimento)</label><select id="fvm_dev_id" onchange="syncSystemTechFromVms()"></select></div>
      </div>
      <div class="row3">
        <div class="field"><label>Acesso</label><select id="faccess"><option>Interno</option><option>Externo</option></select></div>
        <div class="field"><label>&nbsp;</label></div>
        <div class="field"><label>&nbsp;</label></div>
      </div>
      <div class="row2">
        <div class="field"><label>URLs de Homologacao (uma por linha)</label><textarea id="furl_homolog" placeholder="https://hml-a...&#10;https://hml-b..."></textarea></div>
        <div class="field"><label>&nbsp;</label><button id="btn-manage-vms" type="button" class="btn" onclick="openVmForm()">Gerenciar Maquinas</button></div>
      </div>
      <div class="field"><label>Descricao</label><textarea id="fdesc"></textarea></div>
      <div class="row3">
        <div class="field"><label>Setor Responsavel</label><input id="fsector"></div>
        <div class="field"><label>Coordenador Responsavel</label><input id="fcoordinator"></div>
        <div class="field"><label>Ramal</label><input id="fextension"></div>
      </div>
      <div class="row3">
        <div class="field"><label>Email</label><input id="femail" type="email" placeholder="email@dominio.gov.br"></div>
        <div class="field"><label>Suporte</label><input id="fsupport"></div>
        <div class="field"><label>Contato Suporte</label><input id="fsupport_contact"></div>
      </div>
      <div class="row3">
        <div class="field"><label>Analytics</label><input id="fanalytics" placeholder="Ex: GA4 / Matomo"></div>
        <div class="field"><label>Diretorio</label><input id="fdirectory" placeholder="Ex: /var/www/sistema"></div>
        <div class="field"><label>Tamanho</label><input id="fsize" placeholder="Ex: 1.2 GB"></div>
      </div>
      <div class="row3">
        <div class="field"><label>Repositorio</label><input id="frepository" placeholder="Ex: github.com/org/repo"></div>
        <div class="field"><label>&nbsp;</label></div>
        <div class="field"><label>&nbsp;</label></div>
      </div>
      <div class="row3">
        <div class="field"><label>Linguagem</label><input id="ftech" list="ftech-options" placeholder="PHP, JavaScript, Python"></div>
        <div class="field"><label>Versao Alvo</label><input id="ftarget_version" placeholder="Ex: PHP 8.2, Java 21, Python 3.12"></div>
        <div class="field"><label>Servidor da Aplicacao</label><input id="fapp_server" list="fapp-server-options" placeholder="Ex: PHP-FPM:9000, Tomcat:8080"></div>
      </div>
      <div class="row3">
        <div class="field"><label>Reverse Proxy / Web Server</label><input id="fweb_server" list="fweb-server-options" placeholder="Ex: Nginx:443, Apache:80"></div>
        <div class="field">
          <label>Containerizacao</label>
          <select id="fcontainerization" onchange="syncSystemContainerFields()">
            <option value="0" selected>Nao</option>
            <option value="1">Sim</option>
          </select>
        </div>
        <div class="field"><label>Ferramenta de Container</label><input id="fcontainer_tool" list="fcontainer-tool-options" placeholder="Ex: Docker, Podman"></div>
      </div>
      <div class="row3">
        <div class="field"><label>PHP: Extensões Necessárias (vírgula)</label><textarea id="fphp_required_extensions" placeholder="Ex: curl, mbstring, xml, pdo_mysql"></textarea></div>
        <div class="field"><label>&nbsp;</label></div>
        <div class="field"><label>&nbsp;</label></div>
      </div>
      <div class="field"><label>PHP: Diretivas Requeridas (uma por linha)</label><textarea id="fphp_required_ini" placeholder="memory_limit >= 512M&#10;max_execution_time >= 300&#10;upload_max_filesize >= 500M"></textarea></div>
      <div class="field"><label>R: Pacotes Requeridos (vírgula)</label><textarea id="fr_required_packages" placeholder="Ex: shiny, dplyr, ggplot2, httr"></textarea></div>
      <div class="field">
        <label>Documentação do Sistema (PDF)</label>
        <div class="system-doc-grid">
          <div class="system-doc-card">
            <div class="system-doc-title">Instalação</div>
            <div id="fdoc_installation_status" class="system-doc-status">Nenhum arquivo enviado.</div>
            <input id="fdoc_installation_ref" type="hidden">
            <input id="fdoc_installation_updated_at" type="hidden">
            <input id="fdoc_installation_file" type="file" accept=".pdf,application/pdf">
            <div class="actions">
              <button id="fdoc_installation_view" type="button" class="btn" onclick="openSystemDocByType('installation')">Visualizar</button>
              <button id="fdoc_installation_upload" type="button" class="btn btn-save" onclick="uploadSystemDocByType('installation')">Enviar/Atualizar</button>
              <button id="fdoc_installation_remove" type="button" class="btn btn-danger" onclick="removeSystemDocByType('installation')">Remover</button>
            </div>
          </div>
          <div class="system-doc-card">
            <div class="system-doc-title">Manutenção / Atualização</div>
            <div id="fdoc_maintenance_status" class="system-doc-status">Nenhum arquivo enviado.</div>
            <input id="fdoc_maintenance_ref" type="hidden">
            <input id="fdoc_maintenance_updated_at" type="hidden">
            <input id="fdoc_maintenance_file" type="file" accept=".pdf,application/pdf">
            <div class="actions">
              <button id="fdoc_maintenance_view" type="button" class="btn" onclick="openSystemDocByType('maintenance')">Visualizar</button>
              <button id="fdoc_maintenance_upload" type="button" class="btn btn-save" onclick="uploadSystemDocByType('maintenance')">Enviar/Atualizar</button>
              <button id="fdoc_maintenance_remove" type="button" class="btn btn-danger" onclick="removeSystemDocByType('maintenance')">Remover</button>
            </div>
          </div>
          <div class="system-doc-card">
            <div class="system-doc-title">Segurança</div>
            <div id="fdoc_security_status" class="system-doc-status">Nenhum arquivo enviado.</div>
            <input id="fdoc_security_ref" type="hidden">
            <input id="fdoc_security_updated_at" type="hidden">
            <input id="fdoc_security_file" type="file" accept=".pdf,application/pdf">
            <div class="actions">
              <button id="fdoc_security_view" type="button" class="btn" onclick="openSystemDocByType('security')">Visualizar</button>
              <button id="fdoc_security_upload" type="button" class="btn btn-save" onclick="uploadSystemDocByType('security')">Enviar/Atualizar</button>
              <button id="fdoc_security_remove" type="button" class="btn btn-danger" onclick="removeSystemDocByType('security')">Remover</button>
            </div>
          </div>
          <div class="system-doc-card">
            <div class="system-doc-title">Manual de Uso / Procedimentos</div>
            <div id="fdoc_manual_status" class="system-doc-status">Nenhum arquivo enviado.</div>
            <input id="fdoc_manual_ref" type="hidden">
            <input id="fdoc_manual_updated_at" type="hidden">
            <input id="fdoc_manual_file" type="file" accept=".pdf,application/pdf">
            <div class="actions">
              <button id="fdoc_manual_view" type="button" class="btn" onclick="openSystemDocByType('manual')">Visualizar</button>
              <button id="fdoc_manual_upload" type="button" class="btn btn-save" onclick="uploadSystemDocByType('manual')">Enviar/Atualizar</button>
              <button id="fdoc_manual_remove" type="button" class="btn btn-danger" onclick="removeSystemDocByType('manual')">Remover</button>
            </div>
          </div>
        </div>
        <div id="fdoc_hint" class="auth-hint">Salve o sistema para habilitar envio de PDFs.</div>
      </div>
      <datalist id="ftech-options"></datalist>
      <datalist id="fapp-server-options"></datalist>
      <datalist id="fweb-server-options"></datalist>
      <datalist id="fcontainer-tool-options"></datalist>
      <div class="field"><label>Observacoes</label><textarea id="fnotes"></textarea></div>
      <div class="form-actions">
        <button class="btn" type="button" onclick="closeModal('mform')">Cancelar</button>
        <button id="bsave" class="btn btn-save" type="button" onclick="saveSystem()" disabled>Salvar</button>
        <button id="barchive-system" class="btn btn-danger hidden" type="button" onclick="archiveCurrentSystem()">Arquivar</button>
      </div>
    </div>
  </div>
</div>
<div id="mdetail" class="modal-bg hidden">
  <div class="modal" onclick="event.stopPropagation()">
    <div class="modal-head"><div id="dtitle" class="modal-title">Detalhes</div><button class="close" onclick="closeModal('mdetail')">&#10005;</button></div>
    <div id="dbody"></div>
    <div class="form-actions" style="margin-top:12px">
      <button id="ddel" class="btn">Arquivar</button>
    </div>
  </div>
</div>
<div id="mvm" class="modal-bg hidden">
  <div class="modal" onclick="event.stopPropagation()">
    <div class="modal-head"><div id="vmtitle" class="modal-title">Nova Maquina</div><button class="close" onclick="closeModal('mvm')">&#10005;</button></div>
    <div class="form">
      <input id="fvmid" type="hidden">
      <div class="row2">
        <div class="field"><label>Nome da Maquina *</label><input id="fvmname" placeholder="Ex: vm-sei-prod-01"></div>
        <div class="field"><label>IPs * (uma por linha ou virgula)</label><textarea id="fvmip" placeholder="Ex: 10.0.0.15&#10;10.0.0.16"></textarea></div>
      </div>
      <div class="row2">
        <div class="field"><label>IP Publico / NAT (opcional, uma por linha ou virgula)</label><textarea id="fvmpublicip" placeholder="Ex: 177.10.10.15&#10;177.10.10.16"></textarea></div>
        <div class="field"><label>&nbsp;</label></div>
      </div>
      <div class="row2">
        <div class="field"><label>Ambiente da VM *</label><select id="fvmcategory"><option>Producao</option><option>Homologacao</option><option>Desenvolvimento</option></select></div>
        <div class="field"><label>Tipo da VM *</label><select id="fvmtype"><option>Sistemas</option><option>SGBD</option></select></div>
      </div>
      <div class="row2">
        <div class="field"><label>Administracao *</label><select id="fvmadministration"><option>SEI</option><option>PRODEB</option></select></div>
        <div class="field"><label>&nbsp;</label></div>
      </div>
      <div class="row3">
        <div class="field"><label>Sistema Operacional</label><input id="fvmos" placeholder="Ex: Ubuntu Server"></div>
        <div class="field"><label>&nbsp;</label></div>
        <div class="field"><label>&nbsp;</label></div>
      </div>
      <div class="row3">
        <div class="field"><label>vCPUs</label><input id="fvmvcpus" placeholder="Ex: 4"></div>
        <div class="field"><label>RAM</label><input id="fvmram" placeholder="Ex: 16 GB"></div>
        <div class="field"><label>Disco</label><input id="fvmdisk" placeholder="Ex: 200 GB SSD"></div>
      </div>
      <div class="row2">
        <div class="field"><label>Linguagem (virgula)</label><input id="fvmlanguage" placeholder="Ex: PHP, R, Java, Python, Node.js"></div>
        <div class="field"><label>Ferramenta de Container</label><input id="fvmcontainertool" placeholder="Ex: Docker, Podman"></div>
      </div>
      <div class="field">
        <label>Servidor da Aplicacao</label>
        <div class="vm-service-editor">
          <div id="fvmapp_rows" class="vm-service-list"></div>
          <div class="form-actions">
            <button type="button" class="btn" onclick="addVmAppServerRow()">+ Servidor</button>
          </div>
        </div>
      </div>
      <div class="field">
        <label>Reverse Proxy / Web Server</label>
        <div class="vm-service-editor">
          <div id="fvmweb_rows" class="vm-service-list"></div>
          <div class="form-actions">
            <button type="button" class="btn" onclick="addVmWebServerRow()">+ Web Server</button>
          </div>
        </div>
      </div>
      <div class="field">
        <label>Instancias SGBD</label>
        <div class="vm-instance-editor">
          <div id="fvminstances_rows" class="vm-instance-list"></div>
          <div class="form-actions">
            <button type="button" class="btn" onclick="addVmInstanceRow()">+ Instancia</button>
          </div>
          <div class="auth-hint">Informe a tecnologia, selecione um IP cadastrado da maquina e, se necessario, a porta da instancia.</div>
        </div>
      </div>
      <div class="form-actions">
        <button class="btn" onclick="closeModal('mvm')">Cancelar</button>
        <button class="btn btn-save" onclick="saveVm()">Salvar Maquina</button>
        <button id="barchive-vm" class="btn btn-danger hidden" type="button" onclick="archiveCurrentVm()">Arquivar</button>
      </div>
    </div>
  </div>
</div>
<div id="mdb" class="modal-bg hidden">
  <div class="modal" onclick="event.stopPropagation()">
    <div class="modal-head"><div id="dbtitle" class="modal-title">Nova Base de Dados</div><button class="close" onclick="closeModal('mdb')">&#10005;</button></div>
    <div class="form">
      <input id="fdbid" type="hidden">
      <div class="row2">
        <div class="field"><label>Sistema *</label><select id="fdbsystem"></select></div>
        <div class="field"><label>Maquina *</label><select id="fdbvm" onchange="syncDbInstanceOptions()"></select></div>
      </div>
      <div class="row2">
        <div class="field"><label>Instancia SGBD *</label><select id="fdbinstance"></select></div>
        <div class="field"><label>Nome da Base *</label><input id="fdbname" placeholder="Ex: bd_sistema_a"></div>
      </div>
      <div class="row2">
        <div class="field"><label>Maquina Homologacao</label><select id="fdbvmh" onchange="syncDbInstanceOptions()"></select></div>
        <div class="field"><label>Instancia SGBD Homologacao</label><select id="fdbinstanceh" onchange="syncDbHomologIp()"></select></div>
      </div>
      <div class="field"><label>Usuario do Banco</label><input id="fdbuser" placeholder="Ex: app_user"></div>
      <div class="field"><label>Observacoes</label><textarea id="fdbnotes"></textarea></div>
      <div class="form-actions">
        <button class="btn" onclick="closeModal('mdb')">Cancelar</button>
        <button class="btn btn-save" onclick="saveDb()">Salvar Base</button>
        <button id="bdelete-db" class="btn btn-danger hidden" type="button" onclick="deleteCurrentDb()">Excluir</button>
      </div>
    </div>
  </div>
</div>
<script src="assets/app.js?v=<?= filemtime(__DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'app.js') ?>"></script>
</body>
</html>

