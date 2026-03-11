<?php
$sessionName = 'SEIPORTFOLIOSESSID';
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_name($sessionName);
  session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure'   => !empty($_SERVER['HTTPS']),
  ]);
}
if (!is_array($_SESSION['auth_user'] ?? null)) {
  header('Location: index.php');
  exit;
}
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = (string)($_SESSION['csrf_token'] ?? '');
$vmId = (int)($_GET['id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
<title>Diagnostico da VM</title>
<style>
  :root{
    --bg:#f3f7ff;
    --panel:#ffffff;
    --line:#d3e1ff;
    --txt:#1f2f4d;
    --muted:#5875a8;
    --blue:#4f8dfd;
    --blue2:#325fbe;
    --err:#b94d4d;
    --ok:#3b8f61;
    --mono:'Consolas','Courier New',monospace
  }
  *{box-sizing:border-box}
  body{
    margin:0;
    background:var(--bg);
    color:var(--txt);
    font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif
  }
  .wrap{
    max-width:1480px;
    margin:0 auto;
    padding:16px
  }
  .head{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:12px;
    margin-bottom:12px
  }
  .title{
    margin:0;
    font-size:30px;
    line-height:1.1
  }
  .sub{
    margin-top:6px;
    color:var(--muted);
    font-size:14px
  }
  .btn{
    border:1px solid var(--line);
    background:#edf4ff;
    color:#214a96;
    padding:9px 12px;
    border-radius:10px;
    font-size:14px;
    font-weight:700;
    cursor:pointer;
    text-decoration:none;
    display:inline-flex;
    align-items:center;
    justify-content:center
  }
  .btn:hover{filter:brightness(1.03)}
  .btn-save{
    border-color:transparent;
    background:var(--blue);
    color:#fff
  }
  .panel{
    border:1px solid var(--line);
    background:var(--panel);
    border-radius:14px;
    padding:12px;
    margin-bottom:12px
  }
  .meta-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:10px
  }
  .field label{
    display:block;
    font-size:12px;
    text-transform:uppercase;
    letter-spacing:.6px;
    color:var(--muted);
    margin-bottom:4px
  }
  .field input{
    width:100%;
    border:1px solid var(--line);
    border-radius:10px;
    background:#f9fbff;
    color:var(--txt);
    padding:8px 10px;
    font-size:14px
  }
  .tech-badges{
    min-height:40px;
    border:1px solid var(--line);
    border-radius:10px;
    background:#f9fbff;
    padding:8px;
    display:flex;
    flex-wrap:wrap;
    gap:8px;
    align-items:center
  }
  .tech-pill{
    border:1px solid #b8cdf8;
    border-radius:999px;
    padding:4px 10px;
    font-size:12px;
    color:#2e538f;
    background:#eef5ff;
    font-family:var(--mono)
  }
  .controls{
    margin-top:10px;
    display:grid;
    gap:8px
  }
  .controls-row{
    display:flex;
    gap:8px;
    align-items:center;
    flex-wrap:wrap
  }
  .controls-row input[type=file]{
    flex:1 1 320px;
    min-width:220px
  }
  .status{
    min-height:20px;
    font-size:13px;
    color:var(--muted)
  }
  .status.ok{color:var(--ok)}
  .status.err{color:var(--err)}
  .grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:12px
  }
  .section-head{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    margin-bottom:8px
  }
  .section-head h2,
  .section-head h3{
    margin:0;
    font-size:18px
  }
  .count{
    color:var(--muted);
    font-size:13px
  }
  .chips{
    display:flex;
    flex-wrap:wrap;
    gap:8px
  }
  .chip{
    border:1px solid var(--line);
    border-radius:999px;
    padding:4px 10px;
    font-size:12px;
    background:#f5f9ff;
    color:#2e538f;
    font-family:var(--mono)
  }
  .table-wrap{
    border:1px solid var(--line);
    border-radius:10px;
    overflow:auto;
    background:#fff;
    max-height:420px
  }
  table{
    width:100%;
    border-collapse:collapse;
    min-width:700px
  }
  th,td{
    border-bottom:1px solid #e6efff;
    text-align:left;
    padding:8px 10px;
    vertical-align:top;
    font-size:13px
  }
  th{
    position:sticky;
    top:0;
    z-index:2;
    background:#f0f6ff;
    color:#3a5d98;
    text-transform:uppercase;
    letter-spacing:.5px;
    font-size:11px
  }
  td code{
    font-family:var(--mono);
    font-size:12px;
    white-space:pre-wrap;
    word-break:break-word
  }
  .search{
    border:1px solid var(--line);
    border-radius:10px;
    padding:8px 10px;
    min-width:280px;
    max-width:100%;
    font-size:14px
  }
  .pre{
    margin:0;
    border:1px solid var(--line);
    border-radius:10px;
    background:#f9fbff;
    color:var(--txt);
    padding:10px;
    font-family:var(--mono);
    font-size:12px;
    white-space:pre-wrap;
    word-break:break-word;
    max-height:460px;
    overflow:auto
  }
  .empty{
    color:var(--muted);
    font-size:13px
  }
  .hidden{display:none !important}
  .tech-section-title{
    display:flex;
    align-items:center;
    gap:8px;
    margin-bottom:10px
  }
  .tech-tag{
    font-size:11px;
    text-transform:uppercase;
    letter-spacing:.5px;
    color:#2f5aa3;
    background:#edf4ff;
    border:1px solid #c6d8fb;
    border-radius:999px;
    padding:3px 9px;
    font-weight:700
  }
  .note-panel{
    color:var(--muted);
    font-size:14px
  }
  @media(max-width:1200px){
    .grid{grid-template-columns:1fr}
  }
  @media(max-width:760px){
    .meta-grid{grid-template-columns:1fr}
    .title{font-size:25px}
    table{min-width:520px}
  }
</style>
</head>
<body>
  <div class="wrap">
    <div class="head">
      <div>
        <h1 class="title">Diagnostico da VM</h1>
        <div class="sub">Importacao e visualizacao de diagnosticos por tecnologia (PHP e R)</div>
      </div>
      <a class="btn" href="index.php">Voltar</a>
    </div>

    <section class="panel">
      <div class="meta-grid">
        <div class="field"><label>Maquina</label><input id="vm-name" readonly></div>
        <div class="field">
          <label>Linguagem</label>
          <div id="vm-language-tags" class="tech-badges"><span class="empty">Sem linguagem informada.</span></div>
        </div>
        <div class="field">
          <label>Tecnologias da VM</label>
          <div id="vm-tech-tags" class="tech-badges"><span class="empty">Sem tecnologias informadas.</span></div>
        </div>
        <div id="meta-php-updated-wrap" class="field hidden"><label>PHP atualizado em</label><input id="vm-updated-php" readonly></div>
        <div id="meta-r-updated-wrap" class="field hidden"><label>R atualizado em</label><input id="vm-updated-r" readonly></div>
      </div>
      <div class="controls">
        <div class="controls-row">
          <button id="btn-reload" class="btn">Recarregar Referencias</button>
        </div>
        <div id="status" class="status"></div>
      </div>
    </section>

    <section id="no-supported-tech" class="panel note-panel hidden">
      A VM nao possui tecnologia suportada para diagnostico nesta tela (PHP e/ou R).
    </section>

    <section id="section-php" class="panel hidden">
      <div class="tech-section-title">
        <h2 style="margin:0">Diagnostico PHP</h2>
        <span class="tech-tag">PHP</span>
      </div>

      <div class="controls">
        <div class="controls-row">
          <input id="file-import-php" type="file" accept=".json,application/json">
          <button id="btn-import-php" class="btn btn-save">Importar JSON</button>
          <button id="btn-update-php" class="btn">Atualizar JSON</button>
          <button id="btn-clear-php" class="btn">Limpar JSON</button>
        </div>
      </div>

      <section class="panel" style="margin-top:12px">
        <div class="section-head">
          <h3>Comparacao PHP</h3>
        </div>
        <div class="controls-row" style="margin-bottom:8px">
          <select id="vm-compare-select-php" class="search" style="min-width:280px"></select>
          <button id="btn-compare-vm-php" class="btn">Comparar com outra VM</button>
        </div>
        <pre id="compare-output-php" class="pre">Sem comparacao executada.</pre>
      </section>

      <section class="panel" style="margin-top:12px">
        <div class="section-head">
          <h3>Extensions</h3>
          <div id="ext-count" class="count">0</div>
        </div>
        <div id="ext-list" class="chips">
          <span class="empty">Sem dados.</span>
        </div>
      </section>

      <section class="panel" style="margin-top:12px;margin-bottom:0">
        <div class="section-head">
          <h3>Diretivas INI</h3>
          <input id="ini-search" class="search" type="text" placeholder="Buscar por diretiva/valor...">
        </div>
        <div class="section-head">
          <div id="ini-count" class="count">0</div>
        </div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Directive</th><th>Local</th><th>Global</th><th>Access</th></tr></thead>
            <tbody id="ini-body"><tr><td colspan="4" class="empty">Sem dados.</td></tr></tbody>
          </table>
        </div>
      </section>
    </section>

    <section id="section-r" class="panel hidden">
      <div class="tech-section-title">
        <h2 style="margin:0">Diagnostico R</h2>
        <span class="tech-tag">R</span>
      </div>

      <div class="controls">
        <div class="controls-row">
          <input id="file-import-r" type="file" accept=".json,application/json">
          <button id="btn-import-r" class="btn btn-save">Importar JSON</button>
          <button id="btn-update-r" class="btn">Atualizar JSON</button>
          <button id="btn-clear-r" class="btn">Limpar JSON</button>
        </div>
      </div>

      <section class="panel" style="margin-top:12px">
        <div class="section-head">
          <h3>Comparacao R</h3>
        </div>
        <div class="controls-row" style="margin-bottom:8px">
          <select id="vm-compare-select-r" class="search" style="min-width:280px"></select>
          <button id="btn-compare-vm-r" class="btn">Comparar com outra VM</button>
        </div>
        <pre id="compare-output-r" class="pre">Sem comparacao executada.</pre>
      </section>

      <section class="panel" style="margin-top:12px;margin-bottom:0">
        <div class="section-head">
          <h3>Pacotes R</h3>
          <input id="r-search" class="search" type="text" placeholder="Buscar por pacote/versao...">
        </div>
        <div class="section-head">
          <div id="r-count" class="count">0 pacote(s)</div>
        </div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Package</th><th>Version</th></tr></thead>
            <tbody id="r-body"><tr><td colspan="2" class="empty">Sem dados.</td></tr></tbody>
          </table>
        </div>
      </section>
    </section>
  </div>

<script>
const VM_ID = <?php echo $vmId; ?>;
let vmOptionsLoaded = { php: false, r: false };
let vmSupports = { php: false, r: false };
let currentPhpPayload = null;
let currentPhpIniList = [];
let currentRPayload = null;
let currentRPackages = [];
let currentVmLanguages = [];

function escHtml(value){
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;');
}

function setStatus(message, type=''){
  const el = document.getElementById('status');
  if (!el) return;
  el.textContent = message || '';
  el.className = `status ${type}`.trim();
}

function normalizeTechText(value){
  return String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .trim()
    .toLowerCase();
}

function isPhpLabel(value){
  return normalizeTechText(value).includes('php');
}

function isRLabel(value){
  const normalized = normalizeTechText(value);
  if (!normalized || normalized === 'php') return false;
  if (normalized === 'r') return true;
  return /^r(?:[\s\-\/_.]|\d)/.test(normalized);
}

function hasPhpTech(list){
  return (Array.isArray(list) ? list : []).some((item) => isPhpLabel(item));
}

function hasRTech(list){
  return (Array.isArray(list) ? list : []).some((item) => isRLabel(item));
}

function normalizeLanguageList(languageList, techFallback=[]){
  const source = Array.isArray(languageList) ? languageList : [];
  const out = [];
  const seen = new Set();
  source.forEach((item) => {
    const value = String(item || '').trim();
    if (!value) return;
    const key = normalizeTechText(value);
    if (!key || seen.has(key)) return;
    seen.add(key);
    out.push(value);
  });
  if (out.length) return out;

  const fallback = Array.isArray(techFallback) ? techFallback : [];
  fallback.forEach((item) => {
    if (isPhpLabel(item) && !seen.has('php')) {
      seen.add('php');
      out.push('PHP');
      return;
    }
    if (isRLabel(item) && !seen.has('r')) {
      seen.add('r');
      out.push('R');
    }
  });
  return out;
}

function filterNonLanguageTech(techList){
  const source = Array.isArray(techList) ? techList : [];
  return source.filter((item) => !isPhpLabel(item) && !isRLabel(item));
}

function extractPhpVersion(payload){
  return String(payload?.php?.version ?? '').trim();
}

function isValidPhpPayload(payload){
  return Boolean(payload && typeof payload === 'object'
    && payload.php && typeof payload.php === 'object'
    && Array.isArray(payload.extensions)
    && Array.isArray(payload.ini));
}

function normalizeRPackages(payload){
  const rows = Array.isArray(payload)
    ? payload
    : (Array.isArray(payload?.packages) ? payload.packages : []);
  const out = [];
  const seen = new Set();
  rows.forEach((row) => {
    if (!row || typeof row !== 'object') return;
    const name = String(row.Package ?? row.package ?? row.name ?? row._row ?? '').trim();
    if (!name) return;
    const key = name.toLowerCase();
    if (seen.has(key)) return;
    seen.add(key);
    out.push({
      Package: name,
      Version: String(row.Version ?? row.version ?? '').trim(),
    });
  });
  return out;
}

function firstTextFromMixed(value){
  if (Array.isArray(value)) {
    for (const item of value) {
      const text = firstTextFromMixed(item);
      if (text) return text;
    }
    return '';
  }
  if (value === null || value === undefined) return '';
  return String(value).trim();
}

function extractRVersion(payload, normalizedPackages=null){
  const source = (payload && typeof payload === 'object' && !Array.isArray(payload)) ? payload : {};
  const declared = firstTextFromMixed(source.r_version ?? source.rVersion ?? source['R.version.string'] ?? source.version ?? '');
  if (declared) return declared;

  const list = Array.isArray(normalizedPackages) ? normalizedPackages : normalizeRPackages(payload);
  const base = list.find((row) => String(row?.Package || '').trim().toLowerCase() === 'base');
  return String(base?.Version || '').trim();
}

function currentLanguageVersionMap(){
  return {
    php: extractPhpVersion(currentPhpPayload),
    r: extractRVersion(currentRPayload, currentRPackages),
  };
}

function normalizeRVersionForCompare(versionText){
  const text = String(versionText || '').trim();
  if (!text) return '';
  const match = text.match(/\d+\.\d+(?:\.\d+)?(?:[-.][0-9A-Za-z]+)*/);
  return match ? match[0] : text.toLowerCase();
}

function isValidRPayload(payload){
  return normalizeRPackages(payload).length > 0;
}

function mapExtensions(payload){
  const map = new Map();
  (payload?.extensions || []).forEach((ext) => {
    const name = String(ext?.name || '').trim();
    if (!name) return;
    map.set(name.toLowerCase(), name);
  });
  return map;
}

function mapIni(payload){
  const map = new Map();
  (payload?.ini || []).forEach((item) => {
    const key = String(item?.directive || '').trim();
    if (!key) return;
    map.set(key.toLowerCase(), String(item?.local_value ?? ''));
  });
  return map;
}

function vmLabel(vm){
  const name = String(vm?.name || '').trim();
  const ip = String(vm?.ip || '').trim();
  if (!name && !ip) return '-';
  if (name && ip) return `${name} (${ip})`;
  return name || ip;
}

function renderTechBadges(techList){
  const el = document.getElementById('vm-tech-tags');
  if (!el) return;
  const list = Array.isArray(techList) ? techList : [];
  if (!list.length) {
    el.innerHTML = '<span class="empty">Sem tecnologias informadas.</span>';
    return;
  }
  el.innerHTML = list
    .map((item) => `<span class="tech-pill">${escHtml(String(item || '').trim())}</span>`)
    .join('');
}

function renderLanguageBadges(){
  const el = document.getElementById('vm-language-tags');
  if (!el) return;
  const versions = currentLanguageVersionMap();
  if (!currentVmLanguages.length) {
    el.innerHTML = '<span class="empty">Sem linguagem informada.</span>';
    return;
  }
  el.innerHTML = currentVmLanguages.map((item) => {
    const label = String(item || '').trim();
    if (!label) return '';
    const key = normalizeTechText(label);
    const version = key.includes('php')
      ? String(versions.php || '').trim()
      : ((key === 'r' || /^r(?:[\s\-\/_.]|\d)/.test(key)) ? String(versions.r || '').trim() : '');
    const text = version ? `${label} (${version})` : label;
    return `<span class="tech-pill">${escHtml(text)}</span>`;
  }).join('');
}

function setVisible(id, visible){
  const el = document.getElementById(id);
  if (!el) return;
  el.classList.toggle('hidden', !visible);
}

function applySupportVisibility(){
  setVisible('section-php', vmSupports.php);
  setVisible('section-r', vmSupports.r);
  setVisible('meta-php-updated-wrap', vmSupports.php);
  setVisible('meta-r-updated-wrap', vmSupports.r);
  setVisible('no-supported-tech', !vmSupports.php && !vmSupports.r);
}

function renderExtensions(payload){
  const listEl = document.getElementById('ext-list');
  const countEl = document.getElementById('ext-count');
  if (!listEl || !countEl) return;
  const names = (payload?.extensions || [])
    .map((ext) => String(ext?.name || '').trim())
    .filter(Boolean)
    .sort((a,b)=>a.localeCompare(b));
  countEl.textContent = String(names.length);
  if (!names.length) {
    listEl.innerHTML = '<span class="empty">Sem dados.</span>';
    return;
  }
  listEl.innerHTML = names.map((name) => `<span class="chip">${escHtml(name)}</span>`).join('');
}

function renderIniRows(){
  const body = document.getElementById('ini-body');
  const countEl = document.getElementById('ini-count');
  const q = String(document.getElementById('ini-search')?.value || '').toLowerCase().trim();
  if (!body || !countEl) return;

  const rows = currentPhpIniList.filter((item) => {
    const blob = [
      item?.directive,
      item?.local_value,
      item?.global_value,
      item?.access
    ].map((v)=>String(v ?? '')).join(' ').toLowerCase();
    return !q || blob.includes(q);
  });

  countEl.textContent = `${rows.length} diretiva(s)`;
  if (!rows.length) {
    body.innerHTML = '<tr><td colspan="4" class="empty">Nenhuma diretiva encontrada.</td></tr>';
    return;
  }
  body.innerHTML = rows.map((item) => `
    <tr>
      <td><code>${escHtml(item?.directive ?? '-')}</code></td>
      <td><code>${escHtml(item?.local_value ?? '-')}</code></td>
      <td><code>${escHtml(item?.global_value ?? '-')}</code></td>
      <td><code>${escHtml(item?.access ?? '-')}</code></td>
    </tr>
  `).join('');
}

function renderPhpPayload(payload){
  currentPhpPayload = payload;
  if (!isValidPhpPayload(payload)) {
    renderExtensions({});
    currentPhpIniList = [];
    renderIniRows();
    renderLanguageBadges();
    return;
  }
  renderExtensions(payload);
  currentPhpIniList = Array.isArray(payload.ini) ? payload.ini : [];
  renderIniRows();
  renderLanguageBadges();
}

function renderRRows(){
  const body = document.getElementById('r-body');
  const countEl = document.getElementById('r-count');
  const q = String(document.getElementById('r-search')?.value || '').toLowerCase().trim();
  if (!body || !countEl) return;

  const rows = currentRPackages
    .filter((pkg) => {
      const blob = `${String(pkg?.Package || '')} ${String(pkg?.Version || '')}`.toLowerCase();
      return !q || blob.includes(q);
    })
    .sort((a,b)=>String(a?.Package || '').localeCompare(String(b?.Package || '')));

  countEl.textContent = `${rows.length} pacote(s)`;
  if (!rows.length) {
    body.innerHTML = '<tr><td colspan="2" class="empty">Nenhum pacote encontrado.</td></tr>';
    return;
  }

  body.innerHTML = rows.map((pkg) => `
    <tr>
      <td><code>${escHtml(pkg?.Package || '-')}</code></td>
      <td><code>${escHtml(pkg?.Version || '-')}</code></td>
    </tr>
  `).join('');
}

function renderRPayload(payload){
  currentRPayload = payload;
  currentRPackages = normalizeRPackages(payload);
  renderRRows();
  renderLanguageBadges();
}

async function apiCall(action, body=null){
  const csrfMeta = document.querySelector('meta[name="csrf-token"]');
  const csrfToken = csrfMeta ? String(csrfMeta.getAttribute('content') || '') : '';
  const headers = { 'Content-Type': 'application/json' };
  if (csrfToken) headers['X-CSRF-Token'] = csrfToken;
  const options = { headers };
  if (body !== null) {
    options.method = 'POST';
    options.body = JSON.stringify(body);
  }
  const response = await fetch(`index.php?api=${encodeURIComponent(action)}`, options);
  const text = await response.text();
  let data = null;
  try { data = text ? JSON.parse(text) : null; }
  catch { throw new Error('Resposta JSON invalida da API.'); }
  if (!response.ok || !data?.ok) throw new Error(data?.error || `HTTP ${response.status}`);
  return data.data;
}

async function getDiagnosticByVmId(vmId){
  const response = await fetch(`index.php?api=vm-diagnostic-get&id=${encodeURIComponent(String(vmId))}`);
  const text = await response.text();
  let data = null;
  try { data = text ? JSON.parse(text) : null; }
  catch { throw new Error('Resposta JSON invalida da API.'); }
  if (!response.ok || !data?.ok) throw new Error(data?.error || `HTTP ${response.status}`);
  return data.data || null;
}

async function listVms(){
  const response = await fetch('index.php?api=vm-list');
  const text = await response.text();
  let data = null;
  try { data = text ? JSON.parse(text) : null; }
  catch { throw new Error('Resposta JSON invalida da API.'); }
  if (!response.ok || !data?.ok) throw new Error(data?.error || `HTTP ${response.status}`);
  return Array.isArray(data.data) ? data.data : [];
}

function comparePhpPayload(basePayload, targetPayload){
  if (!isValidPhpPayload(basePayload) || !isValidPhpPayload(targetPayload)) {
    return 'Comparacao indisponivel: um dos JSONs esta fora do modelo esperado.';
  }

  const lines = [];
  const basePhp = basePayload.php || {};
  const targetPhp = targetPayload.php || {};
  const phpKeys = [...new Set([...Object.keys(basePhp), ...Object.keys(targetPhp)])].sort();
  const phpChanges = phpKeys.filter((key) => String(basePhp[key] ?? '') !== String(targetPhp[key] ?? ''));
  if (phpChanges.length) {
    lines.push('PHP');
    phpChanges.forEach((key) => {
      lines.push(`- ${key}: "${String(basePhp[key] ?? '')}" -> "${String(targetPhp[key] ?? '')}"`);
    });
  }

  const baseExt = mapExtensions(basePayload);
  const targetExt = mapExtensions(targetPayload);
  const extAdded = [...targetExt.keys()].filter((k) => !baseExt.has(k)).map((k) => targetExt.get(k));
  const extRemoved = [...baseExt.keys()].filter((k) => !targetExt.has(k)).map((k) => baseExt.get(k));
  if (extAdded.length || extRemoved.length) {
    lines.push('Extensions');
    if (extAdded.length) lines.push(`- Adicionadas: ${extAdded.join(', ')}`);
    if (extRemoved.length) lines.push(`- Removidas: ${extRemoved.join(', ')}`);
  }

  const baseIni = mapIni(basePayload);
  const targetIni = mapIni(targetPayload);
  const iniAdded = [...targetIni.keys()].filter((k) => !baseIni.has(k));
  const iniRemoved = [...baseIni.keys()].filter((k) => !targetIni.has(k));
  const iniChanged = [...baseIni.keys()].filter((k) => targetIni.has(k) && baseIni.get(k) !== targetIni.get(k));
  if (iniAdded.length || iniRemoved.length || iniChanged.length) {
    lines.push('INI');
    if (iniAdded.length) lines.push(`- Adicionadas: ${iniAdded.length}`);
    if (iniRemoved.length) lines.push(`- Removidas: ${iniRemoved.length}`);
    if (iniChanged.length) {
      lines.push(`- Alteradas: ${iniChanged.length}`);
      iniChanged.slice(0, 60).forEach((key) => {
        lines.push(`  * ${key}: "${baseIni.get(key)}" -> "${targetIni.get(key)}"`);
      });
      if (iniChanged.length > 60) lines.push(`  * ... mais ${iniChanged.length - 60} alteracao(oes)`);
    }
  }

  return lines.length ? lines.join('\n') : 'Nenhuma diferenca encontrada entre os JSONs.';
}

function compareRPayload(basePayload, targetPayload){
  const baseRows = normalizeRPackages(basePayload);
  const targetRows = normalizeRPackages(targetPayload);
  if (!baseRows.length || !targetRows.length) {
    return 'Comparacao indisponivel: um dos JSONs de R esta fora do modelo esperado.';
  }

  const baseVersion = extractRVersion(basePayload, baseRows);
  const targetVersion = extractRVersion(targetPayload, targetRows);
  const baseVersionKey = normalizeRVersionForCompare(baseVersion);
  const targetVersionKey = normalizeRVersionForCompare(targetVersion);

  const toMap = (rows) => {
    const map = new Map();
    rows.forEach((row) => {
      const name = String(row?.Package || '').trim();
      if (!name) return;
      map.set(name.toLowerCase(), {
        name,
        version: String(row?.Version || '').trim()
      });
    });
    return map;
  };

  const baseMap = toMap(baseRows);
  const targetMap = toMap(targetRows);
  const lines = [];

  const added = [...targetMap.keys()]
    .filter((key) => !baseMap.has(key))
    .map((key) => targetMap.get(key))
    .filter(Boolean)
    .sort((a,b)=>a.name.localeCompare(b.name));
  const removed = [...baseMap.keys()]
    .filter((key) => !targetMap.has(key))
    .map((key) => baseMap.get(key))
    .filter(Boolean)
    .sort((a,b)=>a.name.localeCompare(b.name));
  const changed = [...baseMap.keys()]
    .filter((key) => targetMap.has(key) && String(baseMap.get(key)?.version || '') !== String(targetMap.get(key)?.version || ''))
    .map((key) => ({
      name: String(baseMap.get(key)?.name || ''),
      from: String(baseMap.get(key)?.version || ''),
      to: String(targetMap.get(key)?.version || '')
    }))
    .sort((a,b)=>a.name.localeCompare(b.name));

  if ((baseVersion || targetVersion) && baseVersionKey !== targetVersionKey) {
    lines.push('Versao do R');
    lines.push(`- "${baseVersion || '-'}" -> "${targetVersion || '-'}"`);
  }

  if (added.length) {
    lines.push(`Pacotes adicionados (${added.length})`);
    added.slice(0, 120).forEach((pkg) => {
      lines.push(`- ${pkg.name}${pkg.version ? ` (${pkg.version})` : ''}`);
    });
    if (added.length > 120) lines.push(`- ... mais ${added.length - 120}`);
  }
  if (removed.length) {
    lines.push(`Pacotes removidos (${removed.length})`);
    removed.slice(0, 120).forEach((pkg) => {
      lines.push(`- ${pkg.name}${pkg.version ? ` (${pkg.version})` : ''}`);
    });
    if (removed.length > 120) lines.push(`- ... mais ${removed.length - 120}`);
  }
  if (changed.length) {
    lines.push(`Pacotes com versao alterada (${changed.length})`);
    changed.slice(0, 120).forEach((pkg) => {
      lines.push(`- ${pkg.name}: \"${pkg.from}\" -> \"${pkg.to}\"`);
    });
    if (changed.length > 120) lines.push(`- ... mais ${changed.length - 120}`);
  }

  return lines.length ? lines.join('\n') : 'Nenhuma diferenca encontrada entre os JSONs de R.';
}

function readFileInput(inputId){
  const file = document.getElementById(inputId)?.files?.[0] || null;
  if (!file) return Promise.reject(new Error('Selecione um arquivo JSON.'));
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onerror = () => reject(new Error('Falha ao ler arquivo.'));
    reader.onload = () => resolve({ file, text: String(reader.result || '') });
    reader.readAsText(file);
  });
}

function clearTechView(tech){
  if (tech === 'php') {
    renderPhpPayload(null);
    document.getElementById('vm-updated-php').value = '';
    document.getElementById('compare-output-php').textContent = 'Sem comparacao executada.';
    return;
  }
  renderRPayload(null);
  document.getElementById('vm-updated-r').value = '';
  document.getElementById('compare-output-r').textContent = 'Sem comparacao executada.';
}

function compareSelectIdByTech(tech){
  return tech === 'r' ? 'vm-compare-select-r' : 'vm-compare-select-php';
}

function compareOutputIdByTech(tech){
  return tech === 'r' ? 'compare-output-r' : 'compare-output-php';
}

function compareRefFieldByTech(tech){
  return tech === 'r' ? 'diagnostic_json_ref_r' : 'diagnostic_json_ref';
}

async function ensureVmCompareOptions(tech){
  if (!vmSupports?.[tech] || vmOptionsLoaded?.[tech]) return;
  const select = document.getElementById(compareSelectIdByTech(tech));
  if (!select) return;
  select.innerHTML = '<option value="">Carregando VMs...</option>';
  try {
    const all = await listVms();
    const refField = compareRefFieldByTech(tech);
    const candidates = all
      .filter((vm) => Number(vm?.id || 0) > 0)
      .filter((vm) => Number(vm.id) !== Number(VM_ID))
      .filter((vm) => String(vm?.[refField] || '').trim() !== '')
      .sort((a,b)=>vmLabel(a).localeCompare(vmLabel(b)));
    select.innerHTML = '<option value="">Selecionar VM...</option>' + candidates
      .map((vm) => `<option value="${String(vm.id)}">${escHtml(vmLabel(vm))}</option>`)
      .join('');
    vmOptionsLoaded[tech] = true;
  } catch (error) {
    select.innerHTML = '<option value="">Falha ao carregar VMs</option>';
    setStatus(`Erro ao carregar VMs para comparacao de ${tech.toUpperCase()}: ` + (error.message || '?'), 'err');
  }
}

async function loadReference(){
  if (!VM_ID) {
    setStatus('ID da VM invalido.', 'err');
    return;
  }
  setStatus('Carregando referencias...');
  try {
    const data = await getDiagnosticByVmId(VM_ID);
    const languageList = Array.isArray(data?.vm_language_list) ? data.vm_language_list : [];
    const techList = Array.isArray(data?.vm_tech_list) ? data.vm_tech_list : [];
    const phpDiagnostic = data?.diagnostics?.php || {
      has_file: Boolean(data?.has_file),
      json: data?.json || null,
      updated_at: String(data?.updated_at || ''),
      reference: String(data?.reference || ''),
      filename: String(data?.filename || ''),
      summary: data?.summary || null,
    };
    const rDiagnostic = data?.diagnostics?.r || {
      has_file: false,
      json: null,
      updated_at: '',
      reference: '',
      filename: '',
      summary: null,
    };

    currentVmLanguages = normalizeLanguageList(languageList, techList);
    vmSupports.php = Boolean(data?.supports?.php) || hasPhpTech(currentVmLanguages) || hasPhpTech(techList) || Boolean(phpDiagnostic?.has_file);
    vmSupports.r = Boolean(data?.supports?.r) || hasRTech(currentVmLanguages) || hasRTech(techList) || Boolean(rDiagnostic?.has_file);

    applySupportVisibility();

    document.getElementById('vm-name').value = data?.vm_label || data?.vm_name || `VM #${VM_ID}`;
    renderTechBadges(filterNonLanguageTech(techList));
    renderLanguageBadges();

    document.getElementById('vm-updated-php').value = vmSupports.php ? String(phpDiagnostic?.updated_at || '') : '';
    document.getElementById('vm-updated-r').value = vmSupports.r ? String(rDiagnostic?.updated_at || '') : '';

    if (vmSupports.php) {
      if (phpDiagnostic?.has_file && isValidPhpPayload(phpDiagnostic?.json)) {
        renderPhpPayload(phpDiagnostic.json);
      } else {
        renderPhpPayload(null);
      }
      document.getElementById(compareOutputIdByTech('php')).textContent = 'Sem comparacao executada.';
    } else {
      renderPhpPayload(null);
    }

    if (vmSupports.r) {
      if (rDiagnostic?.has_file && isValidRPayload(rDiagnostic?.json)) {
        renderRPayload(rDiagnostic.json);
      } else {
        renderRPayload(null);
      }
      document.getElementById(compareOutputIdByTech('r')).textContent = 'Sem comparacao executada.';
    } else {
      renderRPayload(null);
    }

    vmOptionsLoaded = { php: false, r: false };
    ensureVmCompareOptions('php');
    ensureVmCompareOptions('r');

    const hasAny = (vmSupports.php && phpDiagnostic?.has_file) || (vmSupports.r && rDiagnostic?.has_file);
    if (hasAny) setStatus('Referencias carregadas.', 'ok');
    else setStatus('Sem JSON referenciado para esta VM.');
  } catch (error) {
    currentVmLanguages = [];
    renderPhpPayload(null);
    renderRPayload(null);
    renderLanguageBadges();
    setStatus('Erro ao carregar referencia: ' + (error.message || '?'), 'err');
  }
}

async function saveReference(tech, successMessage){
  if (!VM_ID) {
    setStatus('ID da VM invalido.', 'err');
    return;
  }
  if (!vmSupports?.[tech]) {
    setStatus(`Tecnologia ${tech.toUpperCase()} nao habilitada para esta VM.`, 'err');
    return;
  }

  const inputId = tech === 'php' ? 'file-import-php' : 'file-import-r';

  try {
    const { file, text } = await readFileInput(inputId);
    let payload;
    try { payload = JSON.parse(text); }
    catch { throw new Error('Arquivo JSON invalido.'); }

    if (tech === 'php' && !isValidPhpPayload(payload)) {
      throw new Error('JSON fora do modelo esperado para PHP (php/extensions/ini).');
    }
    if (tech === 'r' && !isValidRPayload(payload)) {
      throw new Error('JSON fora do modelo esperado para R (objeto com "packages" ou lista de pacotes).');
    }

    const saved = await apiCall('vm-diagnostic-save', {
      id: VM_ID,
      tech,
      filename: file.name,
      json_text: text
    });

    if (tech === 'php') {
      document.getElementById('vm-updated-php').value = String(saved?.updated_at || '');
      renderPhpPayload(saved?.json || payload);
      document.getElementById(compareOutputIdByTech('php')).textContent = 'Sem comparacao executada.';
    } else {
      document.getElementById('vm-updated-r').value = String(saved?.updated_at || '');
      renderRPayload(saved?.json || payload);
      document.getElementById(compareOutputIdByTech('r')).textContent = 'Sem comparacao executada.';
    }

    document.getElementById(inputId).value = '';
    setStatus(successMessage, 'ok');
  } catch (error) {
    setStatus('Erro: ' + (error.message || '?'), 'err');
  }
}

async function clearReference(tech){
  if (!VM_ID) {
    setStatus('ID da VM invalido.', 'err');
    return;
  }
  if (!vmSupports?.[tech]) {
    setStatus(`Tecnologia ${tech.toUpperCase()} nao habilitada para esta VM.`, 'err');
    return;
  }

  const label = tech.toUpperCase();
  if (!confirm(`Limpar o JSON referenciado de ${label} desta VM? Esta acao remove a referencia e os dados do arquivo.`)) {
    return;
  }

  try {
    await apiCall('vm-diagnostic-clear', { id: VM_ID, tech });
    clearTechView(tech);
    setStatus(`JSON de ${label} limpo com sucesso.`, 'ok');
  } catch (error) {
    setStatus('Erro ao limpar JSON: ' + (error.message || '?'), 'err');
  }
}

async function compareWithVm(tech){
  if (!vmSupports?.[tech]) {
    setStatus(`Comparacao indisponivel para ${tech.toUpperCase()} nesta VM.`, 'err');
    return;
  }

  if (tech === 'php' && (!currentPhpPayload || !isValidPhpPayload(currentPhpPayload))) {
    setStatus('Carregue ou importe um JSON PHP antes de comparar.', 'err');
    return;
  }
  if (tech === 'r' && !currentRPackages.length) {
    setStatus('Carregue ou importe um JSON R antes de comparar.', 'err');
    return;
  }

  const select = document.getElementById(compareSelectIdByTech(tech));
  const output = document.getElementById(compareOutputIdByTech(tech));
  const vmId = Number(select?.value || 0);
  if (!vmId) {
    setStatus('Selecione a VM para comparacao.', 'err');
    return;
  }

  try {
    const data = await getDiagnosticByVmId(vmId);
    if (tech === 'php') {
      const targetPhp = data?.diagnostics?.php || {
        has_file: Boolean(data?.has_file),
        json: data?.json || null,
      };
      if (!targetPhp?.has_file || !isValidPhpPayload(targetPhp?.json)) {
        throw new Error('A VM selecionada nao possui JSON PHP valido referenciado.');
      }
      if (output) output.textContent = comparePhpPayload(currentPhpPayload, targetPhp.json);
      setStatus(`Comparacao PHP executada com a VM: ${data?.vm_label || `#${vmId}`}.`, 'ok');
      return;
    }

    const targetR = data?.diagnostics?.r || {
      has_file: false,
      json: null,
    };
    if (!targetR?.has_file || !isValidRPayload(targetR?.json)) {
      throw new Error('A VM selecionada nao possui JSON R valido referenciado.');
    }
    if (output) output.textContent = compareRPayload(currentRPayload, targetR.json);
    setStatus(`Comparacao R executada com a VM: ${data?.vm_label || `#${vmId}`}.`, 'ok');
  } catch (error) {
    setStatus(`Erro na comparacao de ${tech.toUpperCase()}: ` + (error.message || '?'), 'err');
  }
}

document.getElementById('btn-reload')?.addEventListener('click', () => loadReference());

document.getElementById('btn-import-php')?.addEventListener('click', () => saveReference('php', 'JSON PHP importado e referenciado.'));
document.getElementById('btn-update-php')?.addEventListener('click', () => saveReference('php', 'Referencia JSON PHP atualizada.'));
document.getElementById('btn-clear-php')?.addEventListener('click', () => clearReference('php'));

document.getElementById('btn-import-r')?.addEventListener('click', () => saveReference('r', 'JSON R importado e referenciado.'));
document.getElementById('btn-update-r')?.addEventListener('click', () => saveReference('r', 'Referencia JSON R atualizada.'));
document.getElementById('btn-clear-r')?.addEventListener('click', () => clearReference('r'));

document.getElementById('btn-compare-vm-php')?.addEventListener('click', () => compareWithVm('php'));
document.getElementById('btn-compare-vm-r')?.addEventListener('click', () => compareWithVm('r'));
document.getElementById('ini-search')?.addEventListener('input', () => renderIniRows());
document.getElementById('r-search')?.addEventListener('input', () => renderRRows());

loadReference();
</script>
</body>
</html>
