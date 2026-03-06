<?php
$vmId = (int)($_GET['id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
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
  .section-head h2{
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
  @media(max-width:1200px){
    .grid{grid-template-columns:1fr}
  }
  @media(max-width:760px){
    .meta-grid{grid-template-columns:1fr}
    .title{font-size:25px}
  }
</style>
</head>
<body>
  <div class="wrap">
    <div class="head">
      <div>
        <h1 class="title">Diagnostico PHP/Apache</h1>
        <div class="sub">Visualizacao dedicada do JSON de diagnostico da VM</div>
      </div>
      <a class="btn" href="index.php">Voltar</a>
    </div>

    <section class="panel">
      <div class="meta-grid">
        <div class="field"><label>Maquina</label><input id="vm-name" readonly></div>
        <div class="field"><label>Atualizado em</label><input id="vm-updated" readonly></div>
      </div>
      <div class="controls">
        <div class="controls-row">
          <input id="file-import" type="file" accept=".json,application/json">
          <button id="btn-import" class="btn btn-save">Importar JSON</button>
          <button id="btn-update" class="btn">Atualizar JSON</button>
          <button id="btn-clear" class="btn">Limpar JSON</button>
        </div>
        <div class="controls-row">
          <select id="vm-compare-select" class="search" style="min-width:280px"></select>
          <button id="btn-compare-vm" class="btn">Comparar com outra VM</button>
          <button id="btn-reload" class="btn">Recarregar Referencia</button>
        </div>
        <div id="status" class="status"></div>
      </div>
    </section>

    <section class="panel">
      <div class="section-head">
        <h2>Comparacao</h2>
      </div>
      <pre id="compare-output" class="pre">Sem comparacao executada.</pre>
    </section>

    <section class="grid">
      <article class="panel">
        <div class="section-head">
          <h2>PHP</h2>
        </div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Chave</th><th>Valor</th></tr></thead>
            <tbody id="php-body"><tr><td colspan="2" class="empty">Sem dados.</td></tr></tbody>
          </table>
        </div>
      </article>
      <article class="panel">
        <div class="section-head">
          <h2>Extensions</h2>
          <div id="ext-count" class="count">0</div>
        </div>
        <div id="ext-list" class="chips">
          <span class="empty">Sem dados.</span>
        </div>
      </article>
    </section>

    <section class="panel">
      <div class="section-head">
        <h2>Diretivas INI</h2>
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
  </div>

<script>
const VM_ID = <?php echo $vmId; ?>;
let currentPayload = null;
let currentIniList = [];
let vmOptionsLoaded = false;

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

function isValidDiagnosticPayload(payload){
  return Boolean(payload && typeof payload === 'object'
    && payload.php && typeof payload.php === 'object'
    && Array.isArray(payload.extensions)
    && Array.isArray(payload.ini));
}

function toPrettyJson(value){
  try { return JSON.stringify(value, null, 2); }
  catch { return 'Falha ao renderizar JSON.'; }
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

async function apiCall(action, body=null){
  const options = { headers: { 'Content-Type': 'application/json' } };
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

function renderPhpTable(payload){
  const body = document.getElementById('php-body');
  if (!body) return;
  const php = payload?.php || {};
  const keys = ['version', 'sapi', 'os', 'ini_file'];
  body.innerHTML = keys.map((key) => {
    const raw = php?.[key];
    const value = raw === undefined || raw === null || String(raw).trim() === '' ? '-' : raw;
    return `
    <tr>
      <td>${escHtml(key)}</td>
      <td><code>${escHtml(typeof value === 'string' ? value : toPrettyJson(value))}</code></td>
    </tr>
  `;
  }).join('');
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

  const rows = currentIniList.filter((item) => {
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

function renderPayload(payload){
  currentPayload = payload;
  if (!isValidDiagnosticPayload(payload)) {
    renderPhpTable({});
    renderExtensions({});
    currentIniList = [];
    renderIniRows();
    return;
  }
  renderPhpTable(payload);
  renderExtensions(payload);
  currentIniList = Array.isArray(payload.ini) ? payload.ini : [];
  renderIniRows();
}

function vmLabel(vm){
  const name = String(vm?.name || '').trim();
  const ip = String(vm?.ip || '').trim();
  if (!name && !ip) return '-';
  if (name && ip) return `${name} (${ip})`;
  return name || ip;
}

async function ensureVmCompareOptions(){
  if (vmOptionsLoaded) return;
  const select = document.getElementById('vm-compare-select');
  if (!select) return;
  select.innerHTML = '<option value="">Carregando VMs...</option>';
  try {
    const all = await listVms();
    const candidates = all
      .filter((vm) => Number(vm?.id || 0) > 0)
      .filter((vm) => Number(vm.id) !== Number(VM_ID))
      .filter((vm) => String(vm?.diagnostic_json_ref || '').trim() !== '')
      .sort((a,b)=>vmLabel(a).localeCompare(vmLabel(b)));
    select.innerHTML = '<option value="">Selecionar VM...</option>' + candidates
      .map((vm) => `<option value="${String(vm.id)}">${escHtml(vmLabel(vm))}</option>`)
      .join('');
    vmOptionsLoaded = true;
  } catch (error) {
    select.innerHTML = '<option value="">Falha ao carregar VMs</option>';
    setStatus('Erro ao carregar VMs para comparação: ' + (error.message || '?'), 'err');
  }
}

function comparePayload(basePayload, targetPayload){
  if (!isValidDiagnosticPayload(basePayload) || !isValidDiagnosticPayload(targetPayload)) {
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

async function loadReference(){
  if (!VM_ID) {
    setStatus('ID da VM invalido.', 'err');
    return;
  }
  setStatus('Carregando referencia...');
  try {
    const data = await getDiagnosticByVmId(VM_ID);
    document.getElementById('vm-name').value = data?.vm_label || data?.vm_name || `VM #${VM_ID}`;
    document.getElementById('vm-updated').value = String(data?.updated_at || '');

    if (data?.has_file && data?.json) {
      renderPayload(data.json);
      setStatus('Referencia carregada.', 'ok');
    } else {
      renderPayload(null);
      setStatus('Sem JSON referenciado para esta VM.');
    }
    document.getElementById('compare-output').textContent = 'Sem comparacao executada.';
  } catch (error) {
    renderPayload(null);
    setStatus('Erro ao carregar referencia: ' + (error.message || '?'), 'err');
  }
}

async function saveReference(successMessage){
  if (!VM_ID) {
    setStatus('ID da VM invalido.', 'err');
    return;
  }
  try {
    const { file, text } = await readFileInput('file-import');
    let payload;
    try { payload = JSON.parse(text); }
    catch { throw new Error('Arquivo JSON invalido.'); }
    if (!isValidDiagnosticPayload(payload)) {
      throw new Error('JSON fora do modelo esperado (php/extensions/ini).');
    }

    const saved = await apiCall('vm-diagnostic-save', {
      id: VM_ID,
      filename: file.name,
      json_text: text
    });

    document.getElementById('vm-updated').value = String(saved?.updated_at || '');
    renderPayload(saved?.json || payload);
    document.getElementById('compare-output').textContent = 'Sem comparacao executada.';
    document.getElementById('file-import').value = '';
    setStatus(successMessage, 'ok');
  } catch (error) {
    setStatus('Erro: ' + (error.message || '?'), 'err');
  }
}

async function compareWithVm(){
  if (!currentPayload) {
    setStatus('Carregue ou importe um JSON antes de comparar.', 'err');
    return;
  }
  const select = document.getElementById('vm-compare-select');
  const vmId = Number(select?.value || 0);
  if (!vmId) {
    setStatus('Selecione a VM para comparação.', 'err');
    return;
  }

  try {
    const data = await getDiagnosticByVmId(vmId);
    if (!data?.has_file || !isValidDiagnosticPayload(data?.json)) {
      throw new Error('A VM selecionada não possui JSON válido referenciado.');
    }
    document.getElementById('compare-output').textContent = comparePayload(currentPayload, data.json);
    setStatus(`Comparação executada com a VM: ${data?.vm_label || `#${vmId}`}.`, 'ok');
  } catch (error) {
    setStatus('Erro na comparação com VM: ' + (error.message || '?'), 'err');
  }
}

async function clearReference(){
  if (!VM_ID) {
    setStatus('ID da VM invalido.', 'err');
    return;
  }
  if (!confirm('Limpar o JSON referenciado desta VM? Esta acao remove a referencia e os dados do arquivo.')) {
    return;
  }
  try {
    await apiCall('vm-diagnostic-clear', { id: VM_ID });
    currentPayload = null;
    currentIniList = [];
    document.getElementById('vm-updated').value = '';
    document.getElementById('compare-output').textContent = 'Sem comparacao executada.';
    renderPayload(null);
    setStatus('JSON limpo com sucesso.', 'ok');
  } catch (error) {
    setStatus('Erro ao limpar JSON: ' + (error.message || '?'), 'err');
  }
}

document.getElementById('btn-import')?.addEventListener('click', () => saveReference('JSON importado e referenciado.'));
document.getElementById('btn-update')?.addEventListener('click', () => saveReference('Referencia JSON atualizada.'));
document.getElementById('btn-clear')?.addEventListener('click', () => clearReference());
document.getElementById('btn-reload')?.addEventListener('click', () => loadReference());
document.getElementById('btn-compare-vm')?.addEventListener('click', () => compareWithVm());
document.getElementById('ini-search')?.addEventListener('input', () => renderIniRows());

loadReference();
ensureVmCompareOptions();
</script>
</body>
</html>
