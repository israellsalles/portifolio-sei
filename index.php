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
    <button id="tab-diagrama" class="tab" onclick="setView('diagrama')">&#128279; Diagrama</button>
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
    <select id="st" onchange="renderCurrent()"></select>
    <select id="vmf" onchange="renderCurrent()"></select>
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
            <thead><tr><th>Nome</th><th>URL</th><th>Descricao</th><th>Status</th><th style="width:98px">Acoes</th></tr></thead>
            <tbody id="list-desc-body"></tbody>
          </table>
        </div>
      </div>
      <div class="list-section">
        <div class="list-section-title">2. Informacoes Tecnicas</div>
        <div class="table-wrap">
          <table style="min-width:1400px">
            <thead><tr><th>Nome</th><th>Sistema</th><th>Versao</th><th>Categoria</th><th>Criticidade</th><th>Observacoes</th></tr></thead>
            <tbody id="list-main-body"></tbody>
          </table>
        </div>
      </div>
      <div class="list-section">
        <div class="list-section-title">3. Infraestrutura</div>
        <div class="table-wrap">
          <table style="min-width:1650px">
            <thead><tr><th>Nome</th><th>URL Homologacao</th><th>VM Producao</th><th>IP Producao</th><th>VM Homologacao</th><th>IP Homologacao</th><th>Tecnologias</th></tr></thead>
            <tbody id="list-infra-body"></tbody>
          </table>
        </div>
      </div>
      <div class="list-section">
        <div class="list-section-title">4. Bases de Dados</div>
        <div class="table-wrap">
          <table style="min-width:1700px">
            <thead><tr><th>Nome</th><th>Base de Dados</th><th>Usuario do Banco</th><th>SGBD</th><th>Versao SGBD</th><th>Maquina</th><th>IP</th><th>VM Homologacao</th><th>IP Homologacao</th><th>Versao SGBD Homologacao</th><th>Observacoes</th></tr></thead>
            <tbody id="list-db-body"></tbody>
          </table>
        </div>
      </div>
      <div class="list-section">
        <div class="list-section-title">5. Contatos e Suporte</div>
        <div class="table-wrap">
          <table style="min-width:1400px">
            <thead><tr><th>Nome</th><th>Responsavel Tecnico</th><th>Setor Responsavel</th><th>Coordenador Responsavel</th><th>Ramal</th><th>Email</th><th>Suporte</th><th>Contato Suporte</th></tr></thead>
            <tbody id="list-support-body"></tbody>
          </table>
        </div>
      </div>
    </div>
    <div id="list-cards" class="list-mobile-cards"></div>
  </section>
  <section id="view-cards" class="view active">
    <div class="panel">
      <div class="panel-head">
        <h3>Cards dos Sistemas</h3>
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
        <table style="min-width:720px">
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
        <button class="btn btn-save" onclick="openDbForm()">+ Nova Base</button>
      </div>
      <div class="table-wrap">
        <table style="min-width:1600px">
          <thead><tr><th>Sistema</th><th>Base de Dados</th><th>Usuario do Banco</th><th>SGBD</th><th>Versao SGBD</th><th>Maquina</th><th>IP</th><th>VM Homologacao</th><th>IP Homologacao</th><th>Versao SGBD Homologacao</th><th>Observacoes</th><th style="width:98px">Acoes</th></tr></thead>
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
          <button class="btn btn-save" onclick="openVmForm()">+ Nova Maquina</button>
        </div>
        <div id="vm-sections" class="vm-sections"></div>
      </div>
      <div class="panel">
        <h3>Relatorio por Maquina</h3>
        <div id="vm-report" class="vm-report"></div>
      </div>
    </div>
  </section>
  <section id="view-diagrama" class="view">
    <div class="panel">
      <div class="panel-head">
        <h3>Diagrama de Relacionamento</h3>
        <button id="diagram-clear-btn" class="btn" style="display:none" onclick="clearDiagramFocus()">Limpar selecao</button>
        <button class="btn" onclick="renderDiagram()">Atualizar Diagrama</button>
      </div>
      <div class="diagram-legend">
        <span class="legend-item"><span class="legend-dot legend-system"></span>Sistema</span>
        <span class="legend-item"><span class="legend-dot legend-db"></span>Base de Dados</span>
        <span class="legend-item"><span class="legend-line rel-db"></span>Sistema para Base de Dados</span>
        <span class="legend-item">Clique no sistema para filtrar linhas</span>
      </div>
      <div id="diagram-focus-info" class="diagram-focus-info"></div>
      <div id="diagram-wrap" class="diagram-wrap"></div>
    </div>
  </section>
  <section id="view-arquivados" class="view">
    <div class="dash-grid archived-grid">
      <div class="panel">
        <h3>Sistemas Arquivados</h3>
        <div class="table-wrap">
          <table style="min-width:900px">
            <thead><tr><th>Nome</th><th>Sistema</th><th>Status</th><th>VM Producao</th><th>VM Homologacao</th><th>Arquivado em</th><th style="width:150px">Acoes</th></tr></thead>
            <tbody id="archived-systems-body"></tbody>
          </table>
        </div>
      </div>
      <div class="panel">
        <h3>Maquinas Arquivadas</h3>
        <div class="table-wrap">
          <table style="min-width:760px">
            <thead><tr><th>Nome da Maquina</th><th>IP</th><th>Total Sistemas</th><th>Arquivado em</th><th style="width:150px">Acoes</th></tr></thead>
            <tbody id="archived-vms-body"></tbody>
          </table>
        </div>
      </div>
    </div>
  </section>
</main>
<div id="mform" class="modal-bg hidden" onclick="closeBg(event,'mform')">
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
        <div class="field"><label>Status</label><select id="fst"><option selected>Ativo</option><option>ManutenÃ§Ã£o</option><option>Depreciado</option><option>ImplantaÃ§Ã£o</option><option>Suspenso</option></select></div>
        <div class="field"><label>Criticidade</label><select id="fcrit"><option>Alta</option><option selected>Media</option><option>Baixa</option></select></div>
      </div>
      <div class="row2">
        <div class="field"><label>Responsavel</label><input id="fowner"></div>
        <div class="field"><label>URL</label><input id="furl" placeholder="https://..."></div>
      </div>
      <div class="row2">
        <div class="field"><label>Maquina (Producao)</label><select id="fvm_id"></select></div>
        <div class="field"><label>Maquina (Homologacao)</label><select id="fvm_homolog_id"></select></div>
      </div>
      <div class="row2">
        <div class="field"><label>URL de Homologacao</label><input id="furl_homolog" placeholder="https://hml..."></div>
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
      <div class="field"><label>Tecnologias (virgula)</label><input id="ftech" placeholder="PHP, JavaScript, SQLite"></div>
      <div class="field"><label>Observacoes</label><textarea id="fnotes"></textarea></div>
      <div class="form-actions">
        <button class="btn" onclick="closeModal('mform')">Cancelar</button>
        <button id="bsave" class="btn btn-save" onclick="saveSystem()" disabled>Salvar</button>
      </div>
    </div>
  </div>
</div>
<div id="mdetail" class="modal-bg hidden" onclick="closeBg(event,'mdetail')">
  <div class="modal" onclick="event.stopPropagation()">
    <div class="modal-head"><div id="dtitle" class="modal-title">Detalhes</div><button class="close" onclick="closeModal('mdetail')">&#10005;</button></div>
    <div id="dbody"></div>
    <div class="form-actions" style="margin-top:12px">
      <button id="ddel" class="btn">Arquivar</button>
    </div>
  </div>
</div>
<div id="mvm" class="modal-bg hidden" onclick="closeBg(event,'mvm')">
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
      <div class="form-actions">
        <button class="btn" onclick="closeModal('mvm')">Cancelar</button>
        <button class="btn btn-save" onclick="saveVm()">Salvar Maquina</button>
      </div>
    </div>
  </div>
</div>
<div id="mdb" class="modal-bg hidden" onclick="closeBg(event,'mdb')">
  <div class="modal" onclick="event.stopPropagation()">
    <div class="modal-head"><div id="dbtitle" class="modal-title">Nova Base de Dados</div><button class="close" onclick="closeModal('mdb')">&#10005;</button></div>
    <div class="form">
      <input id="fdbid" type="hidden">
      <div class="row2">
        <div class="field"><label>Sistema *</label><select id="fdbsystem"></select></div>
        <div class="field"><label>Maquina *</label><select id="fdbvm"></select></div>
      </div>
      <div class="row2">
        <div class="field"><label>Maquina Homologacao</label><select id="fdbvmh" onchange="syncDbHomologIp()"></select></div>
        <div class="field"><label>Versao SGBD Homologacao</label><input id="fdbengineverh" placeholder="Ex: 8.0, 16, 13c"></div>
      </div>
      <div class="row2">
        <div class="field"><label>IP da VM Homologacao</label><input id="fdbvmhip" readonly placeholder="Preenchido automaticamente"></div>
        <div class="field"><label>&nbsp;</label></div>
      </div>
      <div class="row2">
        <div class="field"><label>Nome da Base *</label><input id="fdbname" placeholder="Ex: bd_sistema_a"></div>
        <div class="field"><label>SGBD *</label><select id="fdbengine"><option value="">Selecionar...</option><option>MySQL</option><option>PostgreSQL</option><option>SQL Server</option><option>Oracle</option><option>MariaDB</option><option>SQLite</option></select></div>
      </div>
      <div class="row2">
        <div class="field"><label>Usuario do Banco</label><input id="fdbuser" placeholder="Ex: app_user"></div>
        <div class="field"><label>Versao do SGBD</label><input id="fdbenginever" placeholder="Ex: 8.0, 16, 13c"></div>
      </div>
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

