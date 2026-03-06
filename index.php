<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'api.php';

if (isset($_GET['api'])) {
  handleApiRequest();
  exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SEI Portifólio</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header>
  <a href="index.php" class="logo">
    <div class="logo-icon">&#9889;</div>
    <div class="logo-name">SEI Portifólio</div>
    <div class="logo-ver">v1.0</div>
  </a>
  <div class="tabs">
    <button id="tab-cards" class="tab active" onclick="setView('cards')">&#128451; Sistemas</button>
    <button id="tab-lista" class="tab" onclick="setView('lista')">&#9776; Lista</button>
    <button id="tab-dns" class="tab" onclick="setView('dns')">&#127760; DNS</button>
    <button id="tab-bases" class="tab" onclick="setView('bases')">&#128187; Bases</button>
    <button id="tab-maquinas" class="tab" onclick="setView('maquinas')">&#128187; Maquinas</button>
    <button id="tab-diagrama" class="tab" onclick="openDiagramExternal()">&#128279; Diagrama</button>
    <button id="tab-arquivados" class="tab" onclick="setView('arquivados')">&#128230; Arquivados</button>
    <button id="tab-dashboard" class="tab" onclick="setView('dashboard')">&#128202; Dashboard</button>
  </div>
  <div class="sp"></div>
  <div id="count" class="top-count"></div>
  <button id="top-action" class="btn-primary" onclick="runPrimaryAction()">+ Novo Sistema</button>
</header>
<main>
  <div id="toolbar" class="toolbar">
    <input id="q" class="search" type="text" placeholder="&#128269; Buscar por nome, tecnologia..." oninput="renderCurrent()">
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
    <span id="result-count" class="result-count"></span>
  </div>
  <div id="loading">Carregando sistemas...</div>
  <section id="view-dashboard" class="view">
    <div id="stats" class="stats"></div>
    <div class="dash-grid">
      <div class="panel"><h3>Por Status</h3><div id="status-bars"></div></div>
      <div class="panel"><h3>Por Categoria</h3><div id="category-bars"></div></div>
      <div class="panel"><h3>VMs por Ambiente</h3><div id="vm-category-bars"></div></div>
      <div class="panel"><h3>SGBD / Versao</h3><div id="db-engine-bars"></div></div>
      <div class="panel"><h3>Qualidade do Cadastro</h3><div id="quality-list"></div></div>
      <div class="panel"><h3>Atencao Necessaria</h3><div id="attention-list"></div></div>
    </div>
  </section>
  <section id="view-lista" class="view">
    <div class="list-sections">
      <div class="list-section">
        <div class="list-section-title">1. Sistemas</div>
        <div class="table-wrap">
          <table class="list-desc-table">
            <thead><tr><th>Nome</th><th>Descricao</th><th>Observacoes</th><th>Status</th><th style="width:98px">Acoes</th></tr></thead>
            <tbody id="list-desc-body"></tbody>
          </table>
        </div>
      </div>
      <div class="list-section">
        <div class="list-section-title">2. Informacoes Tecnicas</div>
        <div class="table-wrap">
          <table class="list-main-table list-compact-table">
            <thead><tr><th>Nome</th><th>Sistema</th><th>Versao</th><th>Categoria</th><th>Grupo</th><th>Criticidade</th><th>Tecnologias</th></tr></thead>
            <tbody id="list-main-body"></tbody>
          </table>
        </div>
      </div>
      <div class="list-section">
        <div class="list-section-title">3. Infraestrutura</div>
        <div class="table-wrap">
          <table class="list-infra-table list-compact-table">
            <thead><tr><th>Nome</th><th>URL</th><th>URL Homologacao</th><th>VM Producao</th><th>IP Producao</th><th>VM Homologacao</th><th>IP Homologacao</th><th>Acesso</th><th>Administracao</th></tr></thead>
            <tbody id="list-infra-body"></tbody>
          </table>
        </div>
      </div>
      <div class="list-section">
        <div class="list-section-title">4. Bases de Dados</div>
        <div class="table-wrap">
          <table class="list-db-table list-compact-table">
            <thead><tr><th>Nome</th><th>Base de Dados</th><th>Usuario do Banco</th><th>Maquina</th><th>IP da Instancia</th><th>Instancia SGBD</th><th>VM Homologacao</th><th>IP da Instancia Homologacao</th><th>Instancia SGBD Homologacao</th><th>Observacoes</th></tr></thead>
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
            <thead><tr><th>Nome</th><th>Analytics</th><th>SSL</th><th>WAF</th><th>Bundle</th><th>Diretorio</th><th>Tamanho</th><th>Repositorio</th></tr></thead>
            <tbody id="list-ops-body"></tbody>
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
      </div>
      <div class="table-wrap">
        <table class="dns-table compact-table">
          <thead><tr><th>URL</th><th>IP</th></tr></thead>
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
      </div>
      <div class="table-wrap">
        <table class="bases-table compact-table">
          <thead><tr><th>Sistema</th><th>Base de Dados</th><th>Usuario do Banco</th><th>Maquina</th><th>IP da Instancia</th><th>Instancia SGBD</th><th>VM Homologacao</th><th>IP da Instancia Homologacao</th><th>Instancia SGBD Homologacao</th><th>Observacoes</th><th style="width:98px">Acoes</th></tr></thead>
          <tbody id="db-body"></tbody>
        </table>
      </div>
      <div id="db-cards" class="db-mobile-cards"></div>
    </div>
  </section>
  <section id="view-maquinas" class="view">
    <div class="dash-grid machines-grid">
      <div class="panel">
      <div class="panel-head">
        <h3>Maquinas Virtuais</h3>
      </div>
        <div class="toolbar vm-filters">
          <input id="vmq" class="search vm-filter-search" type="text" placeholder="Buscar maquina, IP, SO..." oninput="renderMachines()">
          <select id="vmcatf" onchange="renderMachines()"></select>
          <select id="vmtypef" onchange="renderMachines()"></select>
          <select id="vmaccessf" onchange="renderMachines()"></select>
          <select id="vmadminf" onchange="renderMachines()"></select>
          <span id="vm-result-count" class="result-count"></span>
        </div>
        <div id="vm-sections" class="vm-sections"></div>
      </div>
      <div class="panel">
        <h3>Relatorio por Maquina</h3>
        <div id="vm-report" class="vm-report"></div>
      </div>
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
</main>
<div id="mform" class="modal-bg hidden">
  <div class="modal" onclick="event.stopPropagation()">
    <div class="modal-head"><div id="ftitle" class="modal-title">Novo Sistema</div><button class="close" onclick="closeModal('mform')">&#10005;</button></div>
    <div class="form">
      <input id="fid" type="hidden">
      <div class="row3">
        <div class="field"><label>Nome *</label><input id="fname" oninput="toggleSave()" placeholder="Ex: GeoNetwork"></div>
        <div class="field"><label>Sistema</label><input id="fsystem" placeholder="Ex: Publicacoes SEI"></div>
        <div class="field"><label>Versao</label><input id="fver" placeholder="Ex: 4.2.x"></div>
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
      <div class="row2">
        <div class="field"><label>Maquina (Producao)</label><select id="fvm_id"></select></div>
        <div class="field"><label>Maquina (Homologacao)</label><select id="fvm_homolog_id"></select></div>
      </div>
      <div class="row2">
        <div class="field"><label>URLs de Homologacao (uma por linha)</label><textarea id="furl_homolog" placeholder="https://hml-a...&#10;https://hml-b..."></textarea></div>
        <div class="field"><label>&nbsp;</label><button type="button" class="btn" onclick="openVmForm()">Gerenciar Maquinas</button></div>
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
        <div class="field"><label>SSL</label><input id="fssl" placeholder="Ex: Let's Encrypt / Cert gov"></div>
        <div class="field"><label>WAF</label><input id="fwaf" placeholder="Ex: Cloudflare / ModSecurity"></div>
      </div>
      <div class="row3">
        <div class="field"><label>Bundle</label><input id="fbundle" placeholder="Ex: dist.zip / release.tar.gz"></div>
        <div class="field"><label>Diretorio</label><input id="fdirectory" placeholder="Ex: /var/www/sistema"></div>
        <div class="field"><label>Tamanho</label><input id="fsize" placeholder="Ex: 1.2 GB"></div>
      </div>
      <div class="row3">
        <div class="field"><label>Repositorio</label><input id="frepository" placeholder="Ex: github.com/org/repo"></div>
        <div class="field"><label>&nbsp;</label></div>
        <div class="field"><label>&nbsp;</label></div>
      </div>
      <div class="field"><label>Tecnologias (virgula)</label><input id="ftech" placeholder="PHP, JavaScript, SQLite"></div>
      <div class="field"><label>Observacoes</label><textarea id="fnotes"></textarea></div>
      <div class="form-actions">
        <button class="btn" onclick="closeModal('mform')">Cancelar</button>
        <button id="bsave" class="btn btn-save" onclick="saveSystem()" disabled>Salvar</button>
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
        <div class="field"><label>IP *</label><input id="fvmip" placeholder="Ex: 10.0.0.15"></div>
      </div>
      <div class="row2">
        <div class="field"><label>Categoria da VM *</label><select id="fvmcategory"><option>Producao</option><option>Homologacao</option><option>Desenvolvimento</option></select></div>
        <div class="field"><label>Tipo da VM *</label><select id="fvmtype"><option>Sistemas</option><option>SGBD</option></select></div>
      </div>
      <div class="row2">
        <div class="field"><label>Acesso *</label><select id="fvmaccess"><option>Interno</option><option>Externo</option></select></div>
        <div class="field"><label>Administracao *</label><select id="fvmadministration"><option>SEI</option><option>PRODEB</option></select></div>
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
      <div class="field"><label>Tecnologias e Versoes (virgula)</label><input id="fvmtech" placeholder="Ex: PHP 8.2, Apache 2.4, Tomcat 10, R 4.3"></div>
      <div class="field"><label>Instancias SGBD (uma por linha: Tecnologia - IP)</label><textarea id="fvminstances" placeholder="MySQL - 10.28.246.82&#10;PostgreSQL - 10.28.246.81"></textarea></div>
      <div class="form-actions">
        <button class="btn" onclick="closeModal('mvm')">Cancelar</button>
        <button class="btn btn-save" onclick="saveVm()">Salvar Maquina</button>
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
      </div>
    </div>
  </div>
</div>
<script src="assets/app.js"></script>
</body>
</html>


