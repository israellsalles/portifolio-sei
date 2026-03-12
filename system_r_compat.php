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
$systemId = (int)($_GET['id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
<title>Compatibilidade R do Sistema</title>
<style>
  :root{
    --bg:#f3f7ff;
    --panel:#ffffff;
    --line:#d4e0f6;
    --text:#1f2f4d;
    --muted:#5770a1;
    --ok:#2f8658;
    --warn:#a96c1f;
    --bad:#b34848;
    --na:#5f6d83;
    --mono:'Consolas','Courier New',monospace;
  }
  *{box-sizing:border-box}
  body{
    margin:0;
    background:var(--bg);
    color:var(--text);
    font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;
  }
  .wrap{
    max-width:1320px;
    margin:0 auto;
    padding:16px;
  }
  .head{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:12px;
    margin-bottom:12px;
  }
  .title{
    margin:0;
    font-size:30px;
    line-height:1.1;
  }
  .sub{
    margin-top:6px;
    color:var(--muted);
    font-size:14px;
  }
  .btn{
    border:1px solid var(--line);
    background:#ecf3ff;
    color:#274b90;
    padding:9px 12px;
    border-radius:10px;
    text-decoration:none;
    font-size:14px;
    font-weight:700;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    cursor:pointer;
  }
  .panel{
    border:1px solid var(--line);
    background:var(--panel);
    border-radius:14px;
    padding:12px;
    margin-bottom:12px;
  }
  .meta{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:10px;
  }
  .field label{
    display:block;
    font-size:12px;
    text-transform:uppercase;
    letter-spacing:.55px;
    color:var(--muted);
    margin-bottom:4px;
  }
  .field .value{
    border:1px solid var(--line);
    border-radius:10px;
    background:#f8fbff;
    padding:8px 10px;
    min-height:38px;
    font-size:14px;
  }
  .tag{
    border:1px solid #c6d7f7;
    border-radius:999px;
    padding:4px 10px;
    font-size:11px;
    font-weight:800;
    letter-spacing:.4px;
    text-transform:uppercase;
  }
  .tag.compatible{background:#e8f7ee;color:var(--ok);border-color:#bfe4ce}
  .tag.warning{background:#fff5e8;color:var(--warn);border-color:#f0d6b5}
  .tag.incompatible{background:#fdeeee;color:var(--bad);border-color:#efc7c7}
  .tag.no_vm,.tag.not_applicable{background:#eef1f6;color:var(--na);border-color:#d4dbe8}
  .status{
    min-height:20px;
    color:var(--muted);
    font-size:13px;
  }
  .status.err{color:var(--bad)}
  .status.ok{color:var(--ok)}
  .req-box{
    border:1px solid var(--line);
    border-radius:10px;
    background:#fbfdff;
    padding:10px;
  }
  .req-title{
    font-size:12px;
    text-transform:uppercase;
    letter-spacing:.55px;
    color:var(--muted);
    margin-bottom:8px;
    font-weight:700;
  }
  .req-empty{
    color:var(--muted);
    font-style:italic;
    font-size:13px;
  }
  .req-list{
    margin:0;
    padding-left:18px;
  }
  .req-list li{
    margin:3px 0;
    line-height:1.35;
  }
  .req-code{
    font-family:var(--mono);
    font-size:12px;
    background:#f1f6ff;
    border:1px solid #d9e5ff;
    border-radius:6px;
    padding:1px 5px;
  }
  .table-wrap{
    border:1px solid var(--line);
    border-radius:10px;
    overflow:auto;
    background:#fff;
  }
  table{
    width:100%;
    min-width:980px;
    border-collapse:collapse;
  }
  th,td{
    border-bottom:1px solid #e6eeff;
    text-align:left;
    vertical-align:top;
    padding:8px 10px;
    font-size:13px;
  }
  th{
    background:#f0f6ff;
    color:#3a5d98;
    text-transform:uppercase;
    letter-spacing:.5px;
    font-size:11px;
    position:sticky;
    top:0;
    z-index:2;
  }
  code{
    font-family:var(--mono);
    font-size:12px;
  }
  @media (max-width:1200px){
    .meta{grid-template-columns:repeat(2,minmax(0,1fr))}
  }
  @media (max-width:760px){
    .meta{grid-template-columns:1fr}
    .title{font-size:24px}
  }
</style>
</head>
<body>
  <div class="wrap">
    <div class="head">
      <div>
        <h1 class="title">Compatibilidade R</h1>
        <div class="sub">Validação de pacotes R por ambiente da VM do sistema.</div>
      </div>
      <a class="btn" href="index.php">Voltar</a>
    </div>

    <section class="panel">
      <div class="meta">
        <div class="field"><label>Sistema</label><div id="meta-name" class="value">-</div></div>
        <div class="field"><label>Alias</label><div id="meta-system" class="value">-</div></div>
        <div class="field"><label>Versao</label><div id="meta-version" class="value">-</div></div>
        <div class="field"><label>Status Geral</label><div id="meta-status" class="value"><span class="tag not_applicable">N/A</span></div></div>
      </div>
      <div id="status" class="status"></div>
    </section>

    <section class="panel">
      <div class="req-box">
        <div class="req-title">Pacotes R requeridos</div>
        <ul id="req-r-packages" class="req-list"><li class="req-empty">Sem requisitos.</li></ul>
      </div>
    </section>

    <section class="panel">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Ambiente</th>
              <th>VM</th>
              <th>Status</th>
              <th>Pacotes faltantes</th>
              <th>Observacoes</th>
            </tr>
          </thead>
          <tbody id="env-body">
            <tr><td colspan="5" class="req-empty">Carregando...</td></tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>

<script>
const SYSTEM_ID = <?php echo $systemId; ?>;

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

function splitCsv(raw){
  return String(raw || '')
    .split(',')
    .map((entry) => entry.trim())
    .filter(Boolean);
}

function requirementListFromSystem(system){
  const list = Array.isArray(system?.r_required_packages_list) ? system.r_required_packages_list : [];
  if (list.length) return list.map((item) => String(item || '').trim()).filter(Boolean);
  return splitCsv(system?.r_required_packages || '');
}

function renderPackageRequirements(items){
  const el = document.getElementById('req-r-packages');
  if (!el) return;
  if (!items.length) {
    el.innerHTML = '<li class="req-empty">Sem requisitos.</li>';
    return;
  }
  el.innerHTML = items.map((item) => `<li><span class="req-code">${escHtml(item)}</span></li>`).join('');
}

function statusLabelForTag(status){
  const map = {
    compatible: 'Compativel',
    warning: 'Parcial',
    incompatible: 'Incompativel',
    no_vm: 'Sem VM',
    not_applicable: 'N/A'
  };
  return map[String(status || 'not_applicable')] || 'N/A';
}

async function apiCall(action){
  const actionText = String(action || '').trim();
  const query = actionText.includes('&')
    ? `api=${encodeURIComponent(actionText.split('&')[0])}&${actionText.split('&').slice(1).join('&')}`
    : `api=${encodeURIComponent(actionText)}`;
  const response = await fetch(`index.php?${query}`);
  const text = await response.text();
  let json;
  try { json = text ? JSON.parse(text) : null; }
  catch { throw new Error('Resposta JSON invalida.'); }
  if (!response.ok) throw new Error(json?.error || `HTTP ${response.status}`);
  if (!json || typeof json !== 'object') throw new Error('Resposta vazia da API.');
  if (!json.ok) throw new Error(json.error || 'Falha na API.');
  return json.data;
}

function renderEnvironmentRows(compat){
  const body = document.getElementById('env-body');
  if (!body) return;
  const envs = Array.isArray(compat?.environments) ? compat.environments : [];
  if (!envs.length) {
    body.innerHTML = '<tr><td colspan="5" class="req-empty">Sem validações de ambiente.</td></tr>';
    return;
  }
  body.innerHTML = envs.map((env) => {
    const status = String(env?.status || 'not_applicable');
    const label = String(env?.label || statusLabelForTag(status));
    const vmName = String(env?.vm_name || '').trim() || '-';
    const missing = Array.isArray(env?.missing_required_packages) ? env.missing_required_packages : [];
    const notes = Array.isArray(env?.notes) ? env.notes : [];
    return `
      <tr>
        <td>${escHtml(String(env?.environment || '-'))}</td>
        <td>${escHtml(vmName)}</td>
        <td><span class="tag ${escHtml(status)}">${escHtml(label)}</span></td>
        <td>${missing.length ? `<ul class="req-list">${missing.map((item) => `<li><code>${escHtml(item)}</code></li>`).join('')}</ul>` : '<span class="req-empty">-</span>'}</td>
        <td>${notes.length ? `<ul class="req-list">${notes.map((item) => `<li>${escHtml(item)}</li>`).join('')}</ul>` : '<span class="req-empty">-</span>'}</td>
      </tr>
    `;
  }).join('');
}

async function loadSystemCompatibility(){
  if (!SYSTEM_ID) {
    setStatus('ID do sistema invalido.', 'err');
    return;
  }
  setStatus('Carregando validacao...');
  try {
    const system = await apiCall(`system-r-compat-get&id=${encodeURIComponent(String(SYSTEM_ID))}`);
    document.getElementById('meta-name').textContent = String(system?.name || '-');
    document.getElementById('meta-system').textContent = String(system?.system_name || '-');
    document.getElementById('meta-version').textContent = String(system?.version || '-');

    const compat = system?.r_compatibility || {};
    const status = String(compat?.status || 'not_applicable');
    const label = String(compat?.label || statusLabelForTag(status));
    const issues = Number(compat?.issues || 0);
    const statusSuffix = issues > 0 ? ` (${issues})` : '';
    document.getElementById('meta-status').innerHTML = `<span class="tag ${escHtml(status)}">${escHtml(label)}${escHtml(statusSuffix)}</span>`;

    renderPackageRequirements(requirementListFromSystem(system));
    renderEnvironmentRows(compat);
    setStatus('Validacao carregada com sucesso.', 'ok');
  } catch (error) {
    setStatus('Erro ao carregar validacao: ' + (error.message || '?'), 'err');
    const envBody = document.getElementById('env-body');
    if (envBody) envBody.innerHTML = '<tr><td colspan="5" class="req-empty">Falha ao carregar dados.</td></tr>';
  }
}

loadSystemCompatibility();
</script>
</body>
</html>
