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
<title>SysPortfolio</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header>
  <div class="logo">
    <div class="logo-icon">&#9889;</div>
    <div class="logo-name">SysPortfolio</div>
    <div class="logo-ver">v1.0</div>
  </div>
  <div class="tabs">
    <button id="tab-dashboard" class="tab active" onclick="setView('dashboard')">&#128202; Dashboard</button>
    <button id="tab-grid" class="tab" onclick="setView('grid')">&#9638; Grid</button>
    <button id="tab-lista" class="tab" onclick="setView('lista')">&#9776; Lista</button>
    <button id="tab-maquinas" class="tab" onclick="setView('maquinas')">&#128187; Maquinas</button>
    <button id="tab-arquivados" class="tab" onclick="setView('arquivados')">&#128230; Arquivados</button>
  </div>
  <div class="sp"></div>
  <div id="count" class="top-count"></div>
  <button class="btn-primary" onclick="openForm()">+ Novo Sistema</button>
</header>
<main>
  <div id="toolbar" class="toolbar">
    <input id="q" class="search" type="text" placeholder="&#128269; Buscar por nome, tecnologia..." oninput="renderCurrent()">
    <select id="cat" onchange="renderCurrent()"></select>
    <select id="st" onchange="renderCurrent()"></select>
    <select id="sort" onchange="renderCurrent()">
      <option value="name">Ordenar: Nome</option>
      <option value="category">Ordenar: Categoria</option>
      <option value="status">Ordenar: Status</option>
      <option value="criticality">Ordenar: Criticidade</option>
    </select>
    <span id="result-count" class="result-count"></span>
  </div>
  <div id="loading">Carregando sistemas...</div>
  <section id="view-dashboard" class="view active">
    <div id="stats" class="stats"></div>
    <div class="dash-grid">
      <div class="panel"><h3>Por Status</h3><div id="status-bars"></div></div>
      <div class="panel"><h3>Por Categoria</h3><div id="category-bars"></div></div>
      <div class="panel"><h3>Atencao Necessaria</h3><div id="attention-list"></div></div>
    </div>
  </section>
  <section id="view-grid" class="view"><div id="grid" class="cards"></div></section>
  <section id="view-lista" class="view">
    <div class="table-wrap">
      <table>
        <thead><tr><th>Nome</th><th>Sistema</th><th>Categoria</th><th>Status</th><th>Criticidade</th><th>Responsavel</th><th>Versao</th><th>VM Producao</th><th>IP Producao</th><th>VM Homologacao</th><th>IP Homologacao</th><th>URL</th><th>URL Homologacao</th><th>Tecnologias</th><th>Descricao</th><th>Observacoes</th><th style="width:98px">Acoes</th></tr></thead>
        <tbody id="list-body"></tbody>
      </table>
    </div>
    <div id="list-cards" class="list-mobile-cards"></div>
  </section>
  <section id="view-maquinas" class="view">
    <div class="dash-grid">
      <div class="panel" style="grid-column:1 / span 2">
        <div class="panel-head">
          <h3>Maquinas Virtuais</h3>
          <button class="btn btn-save" onclick="openVmForm()">+ Nova Maquina</button>
        </div>
        <div class="table-wrap">
          <table style="min-width:760px">
            <thead><tr><th>Nome da Maquina</th><th>IP</th><th>Sistemas em Producao</th><th>Sistemas em Homologacao</th><th>Total</th><th style="width:98px">Acoes</th></tr></thead>
            <tbody id="vm-body"></tbody>
          </table>
        </div>
        <div id="vm-cards" class="vm-mobile-cards"></div>
      </div>
      <div class="panel">
        <h3>Relatorio por Maquina</h3>
        <div id="vm-report" class="vm-report"></div>
      </div>
    </div>
  </section>
  <section id="view-arquivados" class="view">
    <div class="dash-grid">
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
        <div class="field"><label>Status</label><select id="fst"><option selected>Ativo</option><option>Manutenção</option><option>Depreciado</option><option>Implantação</option><option>Suspenso</option></select></div>
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
      <button id="dedit" class="btn">Editar</button>
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
      <div class="form-actions">
        <button class="btn" onclick="closeModal('mvm')">Cancelar</button>
        <button class="btn btn-save" onclick="saveVm()">Salvar Maquina</button>
      </div>
    </div>
  </div>
</div>
<script src="assets/app.js"></script>
</body>
</html>
