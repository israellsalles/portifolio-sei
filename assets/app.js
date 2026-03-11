const App = {
  items: [],
  vms: [],
  databases: [],
  archived: { systems: [], vms: [] },
  view: 'cards',
  auth: {
    authenticated: false,
    user: null
  }
};
const $ = (id) => document.getElementById(id);
const esc = (s) => String(s ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;');
const norm = (s) => String(s ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim().toLowerCase();
const roleRank = (role) => {
  const map = { leitura: 1, edicao: 2, admin: 3 };
  return map[String(role || '').trim().toLowerCase()] || 0;
};
const canEdit = () => roleRank(App.auth?.user?.role) >= roleRank('edicao');
const isAdmin = () => roleRank(App.auth?.user?.role) >= roleRank('admin');
const linkHtml = (url) => {
  const v = String(url ?? '').trim();
  if (!v) return '-';
  const safe = esc(v);
  return `<a href="${safe}" target="_blank" rel="noreferrer" onclick="event.stopPropagation()">${safe}</a>`;
};
const parseUrlList = (raw) => {
  const values = Array.isArray(raw)
    ? raw.map((entry) => String(entry ?? '').trim()).filter(Boolean)
    : String(raw ?? '')
      .replace(/\r/g, '\n')
      .split(/[\n,;]+/)
      .map((entry) => entry.trim())
      .filter(Boolean);
  const seen = new Set();
  return values.filter((entry) => {
    const key = entry.toLowerCase();
    if (seen.has(key)) return false;
    seen.add(key);
    return true;
  });
};
const joinUrlList = (raw) => parseUrlList(raw).join('\n');
const systemUrlList = (item, homolog=false) => {
  const listKey = homolog ? 'url_homolog_list' : 'url_list';
  if (Array.isArray(item?.[listKey])) return parseUrlList(item[listKey]);
  const rawKey = homolog ? 'url_homolog' : 'url';
  return parseUrlList(item?.[rawKey] || '');
};
const linkListHtml = (raw, options={}) => {
  const urls = parseUrlList(raw);
  if (!urls.length) return '-';
  if (urls.length === 1 && !options.forceList) return linkHtml(urls[0]);
  const classes = options.compact ? 'url-list url-list-compact' : 'url-list';
  return `<ul class="${classes}">${urls.map((url) => `<li>${linkHtml(url)}</li>`).join('')}</ul>`;
};

function statusKind(status){
  const s = norm(status);
  if (s === 'ativo') return 'active';
  if (s.includes('manutencao')) return 'maintenance';
  if (s.includes('depreciado')) return 'deprecated';
  if (s.includes('implantacao')) return 'implementation';
  if (s.includes('suspenso')) return 'suspended';
  return 'active';
}

function critKind(c){
  const k = norm(c);
  if (k === 'alta') return 'high';
  if (k === 'baixa') return 'low';
  return 'medium';
}

function categoryIcon(category){
  const c = norm(category);
  if (c === 'gis') return '&#128506;';
  if (c.includes('survey')) return '&#128221;';
  if (c.includes('coleta')) return '&#128248;';
  if (c.includes('publica')) return '&#128240;';
  if (c.includes('analise')) return '&#128202;';
  if (c.includes('infra')) return '&#127959;';
  if (c.includes('api')) return '&#9881;';
  return '&#128230;';
}

function badge(status){
  return `<span class="badge s-${statusKind(status)}"><span class="dot"></span>${esc(status || '-')}</span>`;
}

function toast(msg, err=false){
  const d = document.createElement('div');
  d.className = `toast${err ? ' err' : ''}`;
  d.textContent = msg;
  document.body.appendChild(d);
  setTimeout(() => d.remove(), 2800);
}

async function api(action, body=null){
  const csrfMeta = document.querySelector('meta[name="csrf-token"]');
  const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
  const opt = { headers:{'Content-Type':'application/json', 'X-CSRF-Token': csrfToken} };
  if (body !== null){
    opt.method='POST';
    opt.body=JSON.stringify(body);
  }
  const actionText = String(action || '').trim();
  const apiQuery = actionText.includes('&')
    ? `api=${encodeURIComponent(actionText.split('&')[0])}&${actionText.split('&').slice(1).join('&')}`
    : `api=${encodeURIComponent(actionText)}`;

  let response;
  try {
    response = await fetch(`?${apiQuery}`, opt);
  } catch {
    throw new Error('Falha de conexao com API. Rode php -S localhost:8000');
  }

  const txt = await response.text();
  let json;
  try {
    json = txt ? JSON.parse(txt) : null;
  } catch {
    throw new Error('Resposta JSON invalida da API');
  }

  if (!response.ok) throw new Error(json?.error || `HTTP ${response.status}`);
  if (!json || typeof json !== 'object') throw new Error('Resposta vazia da API');
  if (json.ok === false && /autenticacao/i.test(String(json.error || ''))) {
    setAuthState({ authenticated: false, user: null });
    applyAuthState();
  }
  return json;
}

function setAuthState(payload){
  App.auth.authenticated = Boolean(payload?.authenticated);
  App.auth.user = payload?.user || null;
}

function applyAuthState(){
  const authBox = $('auth-box');
  const authLabel = $('auth-label');
  const loginOpenBtn = $('auth-open-login');
  const loginModal = $('mauth');
  const topAction = $('top-action');
  const changePasswordBtn = $('auth-change-password');
  const exportBtn = $('btn-export');
  const backupBtn = $('btn-backup');
  const backupExportJsonBtn = $('backup-export-json');
  const backupImportBtn = $('backup-import-btn');
  const authenticated = App.auth.authenticated && App.auth.user;

  if (authenticated) {
    if (authLabel) {
      const role = String(App.auth.user.role || 'leitura');
      const roleLabel = role === 'admin' ? 'Admin' : role === 'edicao' ? 'Edicao' : 'Leitura';
      const name = String(App.auth.user.full_name || App.auth.user.username || '').trim() || 'Usuario';
      authLabel.textContent = `${name} (${roleLabel})`;
    }
    authBox?.classList.remove('hidden');
    loginOpenBtn?.classList.add('hidden');
    loginModal?.classList.add('hidden');
  } else {
    authBox?.classList.add('hidden');
    loginOpenBtn?.classList.remove('hidden');
    $('mpassword')?.classList.add('hidden');
  }

  const canEditNow = canEdit();
  const adminNow = isAdmin();
  if (topAction) {
    topAction.disabled = !canEditNow;
    topAction.classList.toggle('hidden', !canEditNow);
  }
  if (changePasswordBtn) changePasswordBtn.disabled = !authenticated;
  if (exportBtn) exportBtn.disabled = !authenticated;
  if (backupBtn) {
    backupBtn.disabled = !adminNow;
    backupBtn.classList.toggle('hidden', !adminNow);
  }
  if (backupExportJsonBtn) backupExportJsonBtn.classList.toggle('hidden', !adminNow);
  if (backupImportBtn) backupImportBtn.classList.toggle('hidden', !adminNow);
}

function ensureCanEdit(message='Perfil sem permissao de edicao.'){
  if (canEdit()) return true;
  toast(message, true);
  return false;
}

function ensureAdmin(message='Acao permitida apenas para admin.'){
  if (isAdmin()) return true;
  toast(message, true);
  return false;
}

async function fetchAuthStatus(){
  const result = await api('auth-status');
  if (!result.ok) throw new Error(result.error || 'Falha ao obter autenticacao');
  setAuthState(result.data || { authenticated: false, user: null });
  applyAuthState();
}

function openLoginModal(){
  if (App.auth.authenticated) return;
  if ($('auth-password')) $('auth-password').value = '';
  $('mauth')?.classList.remove('hidden');
  $('auth-username')?.focus();
}

async function login(){
  const username = String($('auth-username')?.value || '').trim();
  const password = String($('auth-password')?.value || '');
  if (!username || !password) {
    toast('Informe usuario e senha.', true);
    return;
  }
  try {
    const result = await api('login', { username, password });
    if (!result.ok) throw new Error(result.error || 'Falha no login');
    setAuthState(result.data || { authenticated: false, user: null });
    applyAuthState();
    $('auth-password').value = '';
    await refreshAll();
    $('loading').style.display = 'none';
    setView(App.view);
  } catch (error) {
    toast('Erro no login: ' + (error.message || '?'), true);
  }
}

async function logout(){
  try {
    await api('logout', {});
  } catch {}
  setAuthState({ authenticated: false, user: null });
  resetPasswordForm();
  applyAuthState();
  try {
    await refreshAll();
    setView(App.view);
  } catch (error) {
    toast('Erro ao atualizar dados apos logout: ' + (error.message || '?'), true);
  }
}

function resetPasswordForm(){
  if ($('pwd-current')) $('pwd-current').value = '';
  if ($('pwd-new')) $('pwd-new').value = '';
  if ($('pwd-confirm')) $('pwd-confirm').value = '';
}

function openPasswordModal(){
  if (!App.auth.authenticated) {
    toast('Faca login para alterar a senha.', true);
    return;
  }
  resetPasswordForm();
  $('mpassword')?.classList.remove('hidden');
  $('pwd-current')?.focus();
}

async function changePassword(){
  if (!App.auth.authenticated) {
    toast('Faca login para alterar a senha.', true);
    return;
  }
  const currentPassword = String($('pwd-current')?.value || '');
  const newPassword = String($('pwd-new')?.value || '');
  const confirmPassword = String($('pwd-confirm')?.value || '');

  if (!currentPassword || !newPassword || !confirmPassword) {
    toast('Preencha todos os campos de senha.', true);
    return;
  }
  if (newPassword.length < 8) {
    toast('A nova senha deve ter ao menos 8 caracteres.', true);
    return;
  }
  if (newPassword !== confirmPassword) {
    toast('A confirmacao da nova senha nao confere.', true);
    return;
  }

  try {
    const result = await api('change-password', {
      current_password: currentPassword,
      new_password: newPassword
    });
    if (!result.ok) throw new Error(result.error || 'Falha ao alterar senha');
    closeModal('mpassword');
    toast('Senha atualizada com sucesso.');
  } catch (error) {
    toast('Erro ao atualizar senha: ' + (error.message || '?'), true);
  }
}

function downloadTextFile(filename, content, mime='text/plain;charset=utf-8'){
  const blob = new Blob([content], { type: mime });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}

function openBackupModal(){
  if (!App.auth.authenticated) {
    toast('Faca login para exportar.', true);
    return;
  }
  $('mbackup')?.classList.remove('hidden');
}

async function exportCsv(scope){
  try {
    const result = await api(`export-csv&scope=${encodeURIComponent(scope)}`);
    if (!result.ok) throw new Error(result.error || 'Falha ao exportar CSV');
    const filename = String(result.data?.filename || `export_${scope}.csv`);
    const content = String(result.data?.content || '');
    const mime = String(result.data?.mime || 'text/csv;charset=utf-8');
    downloadTextFile(filename, content, mime);
    toast('CSV exportado com sucesso.');
  } catch (error) {
    toast('Erro ao exportar CSV: ' + (error.message || '?'), true);
  }
}

async function exportBackup(){
  if (!ensureAdmin('Apenas admin pode gerar backup completo.')) return;
  try {
    const result = await api('backup-export');
    if (!result.ok) throw new Error(result.error || 'Falha ao exportar backup');
    const filename = `sei_portfolio_backup_${new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19)}.json`;
    const content = JSON.stringify(result.data || {}, null, 2);
    downloadTextFile(filename, content, 'application/json;charset=utf-8');
    toast('Backup JSON exportado.');
  } catch (error) {
    toast('Erro ao exportar backup: ' + (error.message || '?'), true);
  }
}

function triggerBackupImport(){
  if (!ensureAdmin('Apenas admin pode restaurar backup.')) return;
  $('backup-file')?.click();
}

async function onBackupFileChange(ev){
  if (!ensureAdmin('Apenas admin pode restaurar backup.')) return;
  const file = ev?.target?.files?.[0];
  if (!file) return;
  try {
    const text = await file.text();
    let parsed = null;
    try { parsed = JSON.parse(text); }
    catch { throw new Error('Arquivo JSON invalido.'); }
    if (!confirm('Restaurar backup substituindo os dados atuais?')) return;
    const result = await api('backup-restore', { backup: parsed });
    if (!result.ok) throw new Error(result.error || 'Falha ao restaurar backup');
    await refreshAll();
    toast('Backup restaurado com sucesso.');
    closeModal('mbackup');
  } catch (error) {
    toast('Erro ao restaurar backup: ' + (error.message || '?'), true);
  } finally {
    if (ev?.target) ev.target.value = '';
  }
}

function closeModal(id){
  const el = $(id);
  if (!el) return;
  el.classList.add('hidden');
  if (id === 'mauth' && $('auth-password')) $('auth-password').value = '';
  if (id === 'mpassword') resetPasswordForm();
}

function closeBg(ev,id){
  return;
}

function vmName(item, role=false){
  const mode = role === true ? 'homolog' : String(role ?? '').trim().toLowerCase();
  const isDev = mode === 'dev' || mode.includes('desenv');
  const isHomolog = !isDev && (role === true || mode.includes('homo'));
  const key = isDev ? 'vm_dev_name' : (isHomolog ? 'vm_homolog_name' : 'vm_name');
  const legacyKey = isDev ? 'vm_dev' : (isHomolog ? 'vm_homolog' : 'vm');
  return String(item?.[key] || item?.[legacyKey] || '').trim() || '-';
}

function vmIp(item, role=false){
  const mode = role === true ? 'homolog' : String(role ?? '').trim().toLowerCase();
  const isDev = mode === 'dev' || mode.includes('desenv');
  const isHomolog = !isDev && (role === true || mode.includes('homo'));
  const key = isDev ? 'vm_dev_ip' : (isHomolog ? 'vm_homolog_ip' : 'vm_ip');
  const legacyKey = isDev ? 'ip_dev' : (isHomolog ? 'ip_homolog' : 'ip');
  return String(item?.[key] || item?.[legacyKey] || '').trim() || '-';
}

function vmById(id){
  const vmId = Number(id || 0);
  if (!vmId) return null;
  return App.vms.find((vm) => Number(vm.id) === vmId) || null;
}

function relationVmText(item, kind){
  const prod = vmById(item?.vm_id);
  const hml = vmById(item?.vm_homolog_id);
  const dev = vmById(item?.vm_dev_id);
  const prodText = prod ? (kind === 'access' ? vmAccessLabel(prod) : vmAdministrationLabel(prod)) : '';
  const hmlText = hml ? (kind === 'access' ? vmAccessLabel(hml) : vmAdministrationLabel(hml)) : '';
  const devText = dev ? (kind === 'access' ? vmAccessLabel(dev) : vmAdministrationLabel(dev)) : '';
  const unique = [...new Set([prodText, hmlText, devText].filter(Boolean))];
  if (!unique.length) return '-';
  if (unique.length === 1) return unique[0];
  return `Prod: ${prodText || '-'} | Hml: ${hmlText || '-'} | Dev: ${devText || '-'}`;
}

function vmLabel(vm){
  const name = String(vm?.name || '').trim();
  const ip = String(vm?.ip || '').trim();
  if (!name && !ip) return '-';
  if (name && ip) return `${name} (${ip})`;
  return name || ip;
}

function vmTechList(vm){
  if (Array.isArray(vm?.vm_tech_list)) return vm.vm_tech_list;
  const raw = String(vm?.vm_tech || '').trim();
  return raw ? raw.split(',').map((x)=>x.trim()).filter(Boolean) : [];
}

function vmLanguageList(vm){
  if (Array.isArray(vm?.vm_language_list)) return vm.vm_language_list;
  const raw = String(vm?.vm_language || '').trim();
  if (raw) return raw.split(',').map((x)=>x.trim()).filter(Boolean);

  // Compatibilidade: linguagens antigas salvas em vm_tech.
  const out = [];
  const seen = new Set();
  vmTechList(vm).forEach((item) => {
    const value = String(item || '').trim();
    if (!value) return;
    const lower = value.toLowerCase();
    if (lower.includes('php') && !seen.has('php')) {
      seen.add('php');
      out.push('PHP');
      return;
    }
    if ((lower === 'r' || /^r(?:[\s\-\/_.]|\d)/.test(lower)) && !seen.has('r')) {
      seen.add('r');
      out.push('R');
    }
  });
  return out;
}

function vmLanguageVersions(vm){
  if (!vm || typeof vm !== 'object') return { php: '', r: '' };
  const map = vm.vm_language_versions;
  return (map && typeof map === 'object')
    ? { php: String(map.php || ''), r: String(map.r || '') }
    : { php: '', r: '' };
}

function vmLanguageTagText(vm, language){
  const label = String(language || '').trim();
  if (!label) return '';
  const lower = label.toLowerCase();
  const versions = vmLanguageVersions(vm);
  const version = lower.includes('php')
    ? String(versions.php || '').trim()
    : ((lower === 'r' || /^r(?:[\s\-\/_.]|\d)/.test(lower)) ? String(versions.r || '').trim() : '');
  return version ? `${label} (${version})` : label;
}

function vmHasPhpTech(vm){
  return vmLanguageList(vm).some((item) => String(item || '').toLowerCase().includes('php'));
}

function vmHasRTech(vm){
  return vmLanguageList(vm).some((item) => {
    const value = String(item || '').trim().toLowerCase();
    if (!value || value === 'php') return false;
    if (value === 'r') return true;
    return /^r(?:[\s\-\/_.]|\d)/.test(value);
  });
}

function vmDiagnosticTechs(vm){
  const techs = [];
  if (vmHasPhpTech(vm)) techs.push('PHP');
  if (vmHasRTech(vm)) techs.push('R');
  return techs;
}

function vmSupportsDiagnostics(vm){
  if (!vm || vmTypeLabel(vm) !== 'Sistemas') return false;
  return vmHasPhpTech(vm) || vmHasRTech(vm);
}

function vmInstances(vm){
  if (Array.isArray(vm?.vm_instances_list)) {
    return vm.vm_instances_list
      .map((inst) => ({
        name: String(inst?.name || '').trim(),
        ip: String(inst?.ip || '').trim()
      }))
      .filter((inst) => inst.ip !== '');
  }
  const raw = String(vm?.vm_instances || '').trim();
  if (!raw) return [];
  try {
    const parsed = JSON.parse(raw);
    if (!Array.isArray(parsed)) return [];
    return parsed
      .map((inst) => ({
        name: String(inst?.name || '').trim(),
        ip: String(inst?.ip || '').trim()
      }))
      .filter((inst) => inst.ip !== '');
  } catch {
    return [];
  }
}

function vmInstancesText(vm){
  return vmInstances(vm).map((inst) => `${inst.name || 'Instancia'} - ${inst.ip}`).join('\n');
}

function parseVmInstancesInput(text){
  const lines = String(text || '')
    .replace(/,\s*/g, '\n')
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter(Boolean);
  const out = [];
  const seen = new Set();
  lines.forEach((line, idx) => {
    const m = line.match(/^(.*?)\s*[-:]\s*(.+)$/) || line.match(/^(.*?)\s+([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)$/);
    const name = String(m?.[1] || `Instancia ${idx + 1}`).trim();
    const ip = String(m?.[2] || '').trim();
    if (!ip) return;
    const key = `${name.toLowerCase()}|${ip.toLowerCase()}`;
    if (seen.has(key)) return;
    seen.add(key);
    out.push({ name, ip });
  });
  return out;
}

function encodeInstanceOption(instance){
  const name = encodeURIComponent(String(instance?.name || '').trim());
  const ip = encodeURIComponent(String(instance?.ip || '').trim());
  return `${name}|||${ip}`;
}

function decodeInstanceOption(value){
  const raw = String(value || '');
  if (!raw.includes('|||')) return { name: '', ip: '' };
  const [nameEnc, ipEnc] = raw.split('|||');
  return {
    name: decodeURIComponent(nameEnc || ''),
    ip: decodeURIComponent(ipEnc || '')
  };
}

function dbInstanceName(db, homolog=false){
  const key = homolog ? 'db_instance_homolog_name' : 'db_instance_name';
  const ip = dbInstanceIp(db, homolog);
  const value = String(db?.[key] || '').trim();
  if (value) return value;
  return ip && ip !== '-' ? 'Instancia principal' : '-';
}

function dbInstanceIp(db, homolog=false){
  const key = homolog ? 'db_instance_homolog_ip' : 'db_instance_ip';
  const fallback = homolog ? 'vm_homolog_ip' : 'vm_ip';
  return String(db?.[key] || db?.[fallback] || '').trim() || '-';
}

function dbVmInstanceLabel(db, homolog=false){
  const vmKey = homolog ? 'vm_homolog_name' : 'vm_name';
  const vm = String(db?.[vmKey] || '').trim();
  const inst = dbInstanceName(db, homolog);
  if (!vm && inst === '-') return '-';
  if (!vm) return inst;
  if (inst === '-' || inst === 'Instancia principal') return vm;
  return `${vm} - ${inst}`;
}

function dbVmInstanceWithIpLabel(db, homolog=false){
  const label = dbVmInstanceLabel(db, homolog);
  const ip = dbInstanceIp(db, homolog);
  if (label === '-' && ip === '-') return '-';
  if (ip === '-') return label;
  if (label === '-') return ip;
  return `${label} (${ip})`;
}

function dbEngineVersionLabel(db, homolog=false){
  const engine = String(db?.db_engine || '').trim();
  const version = String(homolog ? db?.db_engine_version_homolog : db?.db_engine_version || '').trim();
  const fallback = dbInstanceName(db, homolog);
  const base = engine || (fallback !== '-' ? fallback : '');
  if (!base) return '-';
  return version ? `${base} ${version}` : base;
}

function vmCategoryLabel(vm){
  const raw = String(vm?.vm_category || '').trim().toLowerCase();
  if (raw.includes('homo')) return 'Homologacao';
  if (raw.includes('desenv')) return 'Desenvolvimento';
  return 'Producao';
}

function vmTypeLabel(vm){
  const raw = String(vm?.vm_type || '').trim().toLowerCase();
  if (raw.includes('sgbd') || raw.includes('db') || raw.includes('banco')) return 'SGBD';
  return 'Sistemas';
}

function vmAccessLabel(vm){
  const raw = String(vm?.vm_access || '').trim().toLowerCase();
  if (raw.includes('extern')) return 'Externo';
  return 'Interno';
}

function vmAdministrationLabel(vm){
  const raw = String(vm?.vm_administration || '').trim().toLowerCase();
  if (raw.includes('prodeb')) return 'PRODEB';
  return 'SEI';
}

function vmCategoryOrder(vm){
  const label = vmCategoryLabel(vm);
  if (label === 'Producao') return 1;
  if (label === 'Homologacao') return 2;
  return 3;
}

function runPrimaryAction(){
  if (!ensureCanEdit()) return;
  if (App.view === 'bases') {
    openDbForm();
    return;
  }
  if (App.view === 'maquinas' || App.view === 'vm-relatorio') {
    openVmForm();
    return;
  }
  openForm();
}

function syncPrimaryAction(){
  const btn = $('top-action');
  if (!btn) return;
  btn.disabled = !canEdit();

  if (App.view === 'bases') {
    btn.textContent = '+ Nova Base';
    return;
  }

  if (App.view === 'maquinas' || App.view === 'vm-relatorio') {
    btn.textContent = '+ Nova Maquina';
    return;
  }

  btn.textContent = '+ Novo Sistema';
}

function openDiagramExternal(){
  const url = 'https://prodeboffice365.sharepoint.com/:u:/r/sites/Coinf/_layouts/15/Doc.aspx?sourcedoc=%7BF9A5D9F0-C2B4-41E3-96F7-12EBC1C88D82%7D&file=Diagrama%20dos%20Sistemas.vsdx&action=default&mobileredirect=true&DefaultItemOpen=1&ct=1772734821727&wdOrigin=OFFICECOM-WEB.START.REC&cid=7adfe313-87a7-4a80-bde8-94be444979fd&wdPreviousSessionSrc=HarmonyWeb&wdPreviousSession=1315c3ec-6a4e-4c8c-9723-58107f78e0c7';
  window.open(url, '_blank', 'noopener,noreferrer');
}

function setView(view){
  const nextView = view === 'grid' ? 'lista' : view;
  App.view = nextView;
  ['dashboard','lista','cards','dns','bases','maquinas','vm-relatorio','arquivados'].forEach((v) => {
    const viewEl = $('view-' + v);
    const tabEl = $('tab-' + v);
    if (viewEl) viewEl.classList.toggle('active', v === nextView);
    if (tabEl) tabEl.classList.toggle('active', v === nextView);
  });
  $('toolbar').style.display = (nextView === 'lista' || nextView === 'cards') ? 'flex' : 'none';
  syncPrimaryAction();
  renderCurrent();
}

function populateFilters(){
  const categories = [...new Set(App.items.map((i) => String(i.category || '').trim()).filter(Boolean))].sort((a,b)=>a.localeCompare(b));
  const groups = [...new Set(App.items.map((i) => String(i.system_group || '').trim()).filter(Boolean))].sort((a,b)=>a.localeCompare(b));
  const statuses = [...new Set(App.items.map((i) => String(i.status || '').trim()).filter(Boolean))].sort((a,b)=>a.localeCompare(b));
  const sectors = [...new Set(App.items.map((i) => String(i.responsible_sector || '').trim()).filter(Boolean))].sort((a,b)=>a.localeCompare(b));
  const fill = (id, first, list) => {
    const el = $(id);
    if (!el) return;
    const prev = el.value;
    el.innerHTML = `<option value="">${first}</option>` + list.map((x)=>`<option>${esc(x)}</option>`).join('');
    if (prev && list.includes(prev)) el.value = prev;
  };
  fill('cat','Categoria: Todas',categories);
  fill('groupf','Grupo: Todos',groups);
  fill('st','Status: Todos',statuses);
  fill('accessf','Acesso: Todos',['Interno','Externo']);
  fill('adminf','Administracao: Todos',['SEI','PRODEB']);
  fill('sectorf','Setor: Todos',sectors);

  const vmEl = $('vmf');
  if (vmEl) {
    const prev = vmEl.value;
    const vms = [...App.vms]
      .filter((vm) => vmTypeLabel(vm) !== 'SGBD')
      .sort((a,b)=>String(a.name || '').localeCompare(String(b.name || '')))
      .map((vm) => ({ id: String(vm.id), label: vmLabel(vm) }));
    vmEl.innerHTML = '<option value="">VM: Todas</option>' + vms.map((vm)=>`<option value="${esc(vm.id)}">${esc(vm.label)}</option>`).join('');
    if (prev && vms.some((vm)=>vm.id === prev)) vmEl.value = prev;
  }
}

function populateVmSelects(){
  const fillVm = (id, first, category) => {
    const el = $(id);
    if (!el) return;
    const prev = el.value;
    const vms = [...App.vms]
      .filter((vm) => vmTypeLabel(vm) !== 'SGBD')
      .filter((vm) => vmCategoryLabel(vm) === category)
      .sort((a,b)=>String(a.name || '').localeCompare(String(b.name || '')));
    el.innerHTML = `<option value="">${first}</option>` + vms.map((vm)=>`<option value="${vm.id}">${esc(vmLabel(vm))}</option>`).join('');
    if (prev && vms.some((vm)=>String(vm.id) === String(prev))) el.value = prev;
  };
  fillVm('fvm_id','Selecionar...','Producao');
  fillVm('fvm_homolog_id','Selecionar...','Homologacao');
  fillVm('fvm_dev_id','Selecionar...','Desenvolvimento');
}

function populateVmTabFilters(){
  const fill = (id, first, list) => {
    const el = $(id);
    if (!el) return;
    const prev = el.value;
    el.innerHTML = `<option value="">${first}</option>` + list.map((x)=>`<option>${esc(x)}</option>`).join('');
    if (prev && list.includes(prev)) el.value = prev;
  };
  fill('vmcatf', 'Categoria: Todas', ['Producao','Homologacao','Desenvolvimento']);
  fill('vmtypef', 'Tipo: Todos', ['Sistemas','SGBD']);
  fill('vmaccessf', 'Acesso: Todos', ['Interno','Externo']);
  fill('vmadminf', 'Administracao: Todos', ['SEI','PRODEB']);
  fill('vmrcatf', 'Categoria: Todas', ['Producao','Homologacao','Desenvolvimento']);
  fill('vmrtypef', 'Tipo: Todos', ['Sistemas','SGBD']);
  fill('vmraccessf', 'Acesso: Todos', ['Interno','Externo']);
  fill('vmradminf', 'Administracao: Todos', ['SEI','PRODEB']);
}

function populateDbSelects(){
  const systemEl = $('fdbsystem');
  if (systemEl) {
    const prev = systemEl.value;
    const systems = [...App.items].sort((a,b)=>String(a.name || '').localeCompare(String(b.name || '')));
    systemEl.innerHTML = '<option value="">Selecionar...</option>' + systems.map((item)=>`<option value="${item.id}">${esc(item.name || '-')}</option>`).join('');
    if (prev && [...systemEl.options].some((o)=>o.value === prev)) systemEl.value = prev;
  }

  const vmEl = $('fdbvm');
  if (vmEl) {
    const prev = vmEl.value;
    const vms = [...App.vms]
      .filter((vm) => vmTypeLabel(vm) === 'SGBD')
      .filter((vm) => vmCategoryLabel(vm) === 'Producao')
      .sort((a,b)=>String(a.name || '').localeCompare(String(b.name || '')));
    vmEl.innerHTML = '<option value="">Selecionar...</option>' + vms.map((vm)=>`<option value="${vm.id}">${esc(vmLabel(vm))}</option>`).join('');
    if (prev && vms.some((vm)=>String(vm.id) === String(prev))) vmEl.value = prev;
  }

  const vmHmlEl = $('fdbvmh');
  if (vmHmlEl) {
    const prev = vmHmlEl.value;
    const vms = [...App.vms]
      .filter((vm) => vmTypeLabel(vm) === 'SGBD')
      .filter((vm) => vmCategoryLabel(vm) === 'Homologacao')
      .sort((a,b)=>String(a.name || '').localeCompare(String(b.name || '')));
    vmHmlEl.innerHTML = '<option value="">Selecionar...</option>' + vms.map((vm)=>`<option value="${vm.id}">${esc(vmLabel(vm))}</option>`).join('');
    if (prev && vms.some((vm)=>String(vm.id) === String(prev))) vmHmlEl.value = prev;
  }

  syncDbInstanceOptions();
}

function vmInstanceOptionsByVmId(vmId){
  const vm = App.vms.find((x)=>Number(x.id) === Number(vmId));
  if (!vm) return [];
  const instances = vmInstances(vm);
  if (instances.length) return instances;
  const ip = String(vm?.ip || '').trim();
  if (!ip) return [];
  return [{ name: 'Instancia principal', ip }];
}

function fillDbInstanceSelect(selectId, vmId, selectedName='', selectedIp=''){
  const el = $(selectId);
  if (!el) return;
  const options = vmInstanceOptionsByVmId(vmId);
  const selectedValue = selectedName || selectedIp
    ? encodeInstanceOption({ name: selectedName || 'Instancia principal', ip: selectedIp })
    : '';

  el.innerHTML = '<option value="">Selecionar...</option>' + options.map((inst)=>`<option value="${esc(encodeInstanceOption(inst))}">${esc(`${inst.name} (${inst.ip})`)}</option>`).join('');
  if (selectedValue && [...el.options].some((o)=>o.value === selectedValue)) {
    el.value = selectedValue;
  } else if (selectedIp) {
    const byIp = [...el.options].find((o) => decodeInstanceOption(o.value).ip === selectedIp);
    if (byIp) el.value = byIp.value;
  } else if (options.length === 1) {
    el.value = encodeInstanceOption(options[0]);
  }
}

function selectedDbInstance(selectId){
  const raw = String($(selectId)?.value || '').trim();
  if (!raw) return { name: '', ip: '' };
  return decodeInstanceOption(raw);
}

function syncDbInstanceOptions(prodSelected=null, hmlSelected=null){
  const vmProdId = Number($('fdbvm')?.value || 0);
  const vmHmlId = Number($('fdbvmh')?.value || 0);
  const currentProd = prodSelected || selectedDbInstance('fdbinstance');
  const currentHml = hmlSelected || selectedDbInstance('fdbinstanceh');
  fillDbInstanceSelect('fdbinstance', vmProdId, currentProd.name, currentProd.ip);
  fillDbInstanceSelect('fdbinstanceh', vmHmlId, currentHml.name, currentHml.ip);
  syncDbHomologIp();
}

function syncDbHomologIp(){
  return;
}

function systemDatabases(systemId){
  return App.databases.filter((d) => Number(d.system_id) === Number(systemId));
}

function databaseNamesText(systemId){
  const dbs = systemDatabases(systemId);
  if (!dbs.length) return '-';
  return dbs.map((d) => String(d.db_name || '-').trim() || '-').join(', ');
}

function databaseHostsText(systemId){
  const dbs = systemDatabases(systemId);
  if (!dbs.length) return '-';
  const hosts = [...new Set(dbs.map((d) => {
    const vm = dbVmInstanceLabel(d, false);
    const ip = dbInstanceIp(d, false);
    if (vm && ip) return `${vm} (${ip})`;
    return vm || ip || '-';
  }))].filter(Boolean);
  return hosts.length ? hosts.join(', ') : '-';
}

function databaseEngineText(systemId){
  const dbs = systemDatabases(systemId);
  if (!dbs.length) return '-';
  return dbs.map((d) => {
    const engine = String(d.db_engine || '-').trim() || '-';
    const version = String(d.db_engine_version || '').trim();
    return version ? `${engine} ${version}` : engine;
  }).join(', ');
}

function databaseSearchBlob(systemId){
  const dbs = systemDatabases(systemId);
  if (!dbs.length) return '';
  return dbs.map((d) => [
    d.db_name,
    d.db_user,
    d.db_engine,
    d.db_engine_version,
    d.db_engine_version_homolog,
    d.vm_name,
    d.vm_ip,
    d.db_instance_name,
    d.db_instance_ip,
    d.vm_homolog_name,
    d.vm_homolog_ip,
    d.db_instance_homolog_name,
    d.db_instance_homolog_ip
  ].join(' ')).join(' ');
}

function filteredItems(){
  const q = $('q').value.toLowerCase();
  const cat = $('cat').value;
  const groupf = $('groupf')?.value || '';
  const st = $('st').value;
  const vmf = $('vmf')?.value || '';
  const accessf = $('accessf')?.value || '';
  const adminf = $('adminf')?.value || '';
  const sectorf = $('sectorf')?.value || '';
  const sort = $('sort').value;

  return [...App.items]
    .filter((i)=>!cat || norm(i.category)===norm(cat))
    .filter((i)=>!groupf || String(i.system_group || '').trim() === groupf)
    .filter((i)=>!st || norm(i.status)===norm(st))
    .filter((i)=>!vmf || Number(i.vm_id || 0) === Number(vmf) || Number(i.vm_homolog_id || 0) === Number(vmf) || Number(i.vm_dev_id || 0) === Number(vmf))
    .filter((i)=>{
      if (!accessf) return true;
      const prodVm = vmById(i.vm_id);
      const hmlVm = vmById(i.vm_homolog_id);
      const devVm = vmById(i.vm_dev_id);
      return [prodVm, hmlVm, devVm].filter(Boolean).some((vm)=>vmAccessLabel(vm) === accessf);
    })
    .filter((i)=>{
      if (!adminf) return true;
      const prodVm = vmById(i.vm_id);
      const hmlVm = vmById(i.vm_homolog_id);
      const devVm = vmById(i.vm_dev_id);
      return [prodVm, hmlVm, devVm].filter(Boolean).some((vm)=>vmAdministrationLabel(vm) === adminf);
    })
    .filter((i)=>!sectorf || String(i.responsible_sector || '').trim() === sectorf)
    .filter((i)=>!q || [
      i.name,
      i.system_name,
      i.description,
      i.responsible_sector,
      i.responsible_coordinator,
      i.extension_number,
      i.email,
      i.support,
      i.support_contact,
      i.analytics,
      i.ssl,
      i.waf,
      i.bundle,
      i.directory,
      i.size,
      i.repository,
      i.owner,
      i.category,
      i.system_group,
      i.status,
      systemUrlList(i, false).join(' '),
      systemUrlList(i, true).join(' '),
      vmName(i, false),
      vmName(i, true),
      vmName(i, 'dev'),
      vmIp(i, false),
      vmIp(i, true),
      vmIp(i, 'dev'),
      databaseSearchBlob(i.id),
      (i.tech||[]).join(' ')
    ].join(' ').toLowerCase().includes(q))
    .sort((a,b)=>String(a[sort] ?? '').localeCompare(String(b[sort] ?? '')));
}

function renderDashboard(){
  const totalSystems = App.items.length;
  const totalVms = App.vms.length;
  const totalDatabases = App.databases.length;
  const active = App.items.filter((i)=>statusKind(i.status)==='active').length;
  const maintenance = App.items.filter((i)=>statusKind(i.status)==='maintenance').length;
  const deprecated = App.items.filter((i)=>statusKind(i.status)==='deprecated').length;
  const categories = new Set(App.items.map((i)=>String(i.category || '').trim()).filter(Boolean)).size;
  const dbUsersFilled = App.databases.filter((d)=>String(d.db_user || '').trim() !== '').length;
  const systemsWithUrl = App.items.filter((i) => systemUrlList(i, false).length > 0).length;
  const systemsWithVm = App.items.filter((i) => Number(i.vm_id || 0) > 0).length;
  const vmOsFilled = App.vms.filter((vm)=>String(vm.os_name || '').trim() !== '').length;
  const vmResourcesFilled = App.vms.filter((vm)=>String(vm.vcpus || '').trim() !== '' || String(vm.ram || '').trim() !== '' || String(vm.disk || '').trim() !== '').length;
  const dbHomologFilled = App.databases.filter((d) => Number(d.vm_homolog_id || 0) > 0 && String(d.db_instance_homolog_ip || '').trim() !== '').length;
  const vmTechTotal = App.vms.reduce((acc, vm) => acc + vmTechList(vm).length, 0);

  $('stats').innerHTML = [
    ['Sistemas', totalSystems, '#67a6ff'],
    ['Maquinas Virtuais', totalVms, '#2cc7b0'],
    ['Bases de Dados', totalDatabases, '#e0a95a'],
    ['Ativos', active, '#4be989'],
    ['Em Manutenção', maintenance, '#ff9d4f'],
    ['Depreciados', deprecated, '#ff7070'],
    ['Categorias', categories, '#b08cff'],
    ['Tecnologias em VMs', vmTechTotal, '#6e9bff'],
  ].map(([label,val,color]) => `<div class="stat"><div class="stat-v" style="color:${color}">${val}</div><div class="stat-l">${label}</div></div>`).join('');

  const renderBars = (targetId, entries, total, colorFn, iconFn=null) => {
    const target = $(targetId);
    if (!target) return;
    if (!entries.length) {
      target.innerHTML = '<div class="attention-note">Sem dados.</div>';
      return;
    }
    target.innerHTML = entries.map(([label,count]) => {
      const pct = total ? Math.round((count / total) * 100) : 0;
      const color = colorFn(label);
      const prefix = iconFn ? `${iconFn(label)} ` : '';
      return `<div class="bar"><div class="bar-label"><span>${prefix}${esc(label)}</span><span>${count} (${pct}%)</span></div><div class="track"><div class="fill" style="width:${pct}%;background:${color}"></div></div></div>`;
    }).join('');
  };

  const byStatus = {};
  App.items.forEach((i) => {
    const key = String(i.status || 'Sem status');
    byStatus[key] = (byStatus[key] || 0) + 1;
  });
  renderBars(
    'status-bars',
    Object.entries(byStatus).sort((a,b)=>b[1]-a[1]),
    totalSystems,
    (label) => {
      const k = statusKind(label);
      return k==='active' ? '#22c55e' : k==='maintenance' ? '#f97316' : k==='deprecated' ? '#ef4444' : k==='implementation' ? '#4f8dfd' : '#8b5cf6';
    }
  );

  const byCategory = {};
  App.items.forEach((i) => {
    const key = String(i.category || 'Sem categoria');
    byCategory[key] = (byCategory[key] || 0) + 1;
  });
  renderBars(
    'category-bars',
    Object.entries(byCategory).sort((a,b)=>b[1]-a[1]),
    totalSystems,
    () => '#4f8dfd',
    (label) => categoryIcon(label)
  );

  const byVmCategory = {};
  App.vms.forEach((vm) => {
    const key = vmCategoryLabel(vm);
    byVmCategory[key] = (byVmCategory[key] || 0) + 1;
  });
  renderBars(
    'vm-category-bars',
    Object.entries(byVmCategory).sort((a,b)=>b[1]-a[1]),
    totalVms,
    (label) => label === 'Producao' ? '#10b981' : label === 'Homologacao' ? '#8b5cf6' : '#f59e0b'
  );

  const byDbEngine = {};
  App.databases.forEach((d) => {
    const engine = String(d.db_engine || 'SGBD nao informado').trim() || 'SGBD nao informado';
    const version = String(d.db_engine_version || '').trim();
    const key = version ? `${engine} ${version}` : engine;
    byDbEngine[key] = (byDbEngine[key] || 0) + 1;
  });
  renderBars(
    'db-engine-bars',
    Object.entries(byDbEngine).sort((a,b)=>b[1]-a[1]),
    totalDatabases,
    () => '#e1a64d'
  );

  const quality = $('quality-list');
  if (quality) {
    quality.innerHTML = `
      <div class="quality-item">
        <div><div class="quality-name">Sistema com URL de producao</div><div class="quality-note">${systemsWithUrl}/${totalSystems || 0} sistemas</div></div>
        <div class="quality-pct">${totalSystems ? Math.round((systemsWithUrl / totalSystems) * 100) : 0}%</div>
      </div>
      <div class="quality-item">
        <div><div class="quality-name">Sistema com VM de producao</div><div class="quality-note">${systemsWithVm}/${totalSystems || 0} sistemas</div></div>
        <div class="quality-pct">${totalSystems ? Math.round((systemsWithVm / totalSystems) * 100) : 0}%</div>
      </div>
      <div class="quality-item">
        <div><div class="quality-name">Usuario de banco cadastrado</div><div class="quality-note">${dbUsersFilled}/${totalDatabases || 0} bases</div></div>
        <div class="quality-pct">${totalDatabases ? Math.round((dbUsersFilled / totalDatabases) * 100) : 0}%</div>
      </div>
      <div class="quality-item">
        <div><div class="quality-name">Base com homologacao completa</div><div class="quality-note">${dbHomologFilled}/${totalDatabases || 0} bases</div></div>
        <div class="quality-pct">${totalDatabases ? Math.round((dbHomologFilled / totalDatabases) * 100) : 0}%</div>
      </div>
      <div class="quality-item">
        <div><div class="quality-name">VM com sistema operacional</div><div class="quality-note">${vmOsFilled}/${totalVms || 0} maquinas</div></div>
        <div class="quality-pct">${totalVms ? Math.round((vmOsFilled / totalVms) * 100) : 0}%</div>
      </div>
      <div class="quality-item">
        <div><div class="quality-name">VM com recursos informados</div><div class="quality-note">${vmResourcesFilled}/${totalVms || 0} maquinas</div></div>
        <div class="quality-pct">${totalVms ? Math.round((vmResourcesFilled / totalVms) * 100) : 0}%</div>
      </div>
    `;
  }

  const attention = [];
  App.items
    .filter((i) => statusKind(i.status) !== 'active')
    .forEach((i) => {
      attention.push({
        name: String(i.name || '-'),
        note: `Status: ${String(i.status || '-')}`,
        action: `openDetail(${Number(i.id)})`
      });
    });

  App.items
    .filter((i) => systemUrlList(i, false).length === 0)
    .forEach((i) => {
      attention.push({
        name: String(i.name || '-'),
        note: 'Sistema sem URL de producao',
        action: `openDetail(${Number(i.id)})`
      });
    });

  App.vms
    .filter((vm) => String(vm.os_name || '').trim() === '')
    .forEach((vm) => {
      const vmId = Number(vm.id || 0);
      attention.push({
        name: String(vm.name || '-'),
        note: 'VM sem sistema operacional informado',
        action: canEdit() ? `openVmFormById(${vmId})` : `openVmReadOnlyById(${vmId})`
      });
    });

  App.databases
    .filter((d) => Number(d.vm_homolog_id || 0) > 0 && String(d.db_instance_homolog_ip || '').trim() === '')
    .forEach((d) => {
      const dbId = Number(d.id || 0);
      attention.push({
        name: String(d.db_name || '-'),
        note: 'Base sem instancia de homologacao',
        action: canEdit() ? `openDbFormById(${dbId})` : `openDbReadOnlyById(${dbId})`
      });
    });

  if (!attention.length) {
    $('attention-list').innerHTML = '<div class="attention-note">Tudo em ordem. Sem alertas.</div>';
  } else {
    const unique = [];
    const seen = new Set();
    attention.forEach((item) => {
      const key = `${norm(item.name)}|${norm(item.note)}`;
      if (seen.has(key)) return;
      seen.add(key);
      unique.push(item);
    });

    $('attention-list').innerHTML = unique.slice(0, 20).map((item) => `
      <div class="attention-item" onclick="${item.action}">
        <div><div class="attention-name">${esc(item.name)}</div></div>
        <div class="attention-note">${esc(item.note)}</div>
      </div>
    `).join('');
  }
}

function renderList(list){
  $('result-count').textContent = `${list.length} resultado(s)`;
  if (!list.length) {
    $('list-main-body').innerHTML = '<tr><td colspan="7" style="color:var(--muted)">Nenhum sistema encontrado.</td></tr>';
    $('list-desc-body').innerHTML = '<tr><td colspan="4" style="color:var(--muted)">Nenhum sistema encontrado.</td></tr>';
    $('list-infra-body').innerHTML = '<tr><td colspan="11" style="color:var(--muted)">Nenhum sistema encontrado.</td></tr>';
    $('list-db-body').innerHTML = '<tr><td colspan="10" style="color:var(--muted)">Nenhuma base de dados encontrada.</td></tr>';
    $('list-support-body').innerHTML = '<tr><td colspan="8" style="color:var(--muted)">Nenhum contato cadastrado.</td></tr>';
    $('list-ops-body').innerHTML = '<tr><td colspan="8" style="color:var(--muted)">Nenhum dado de deploy cadastrado.</td></tr>';
    $('list-cards').innerHTML = '<div class="list-mobile-card"><div class="list-mobile-value" style="color:var(--muted)">Nenhum sistema encontrado.</div></div>';
    return;
  }

  $('list-main-body').innerHTML = list.map((i) => `
    <tr onclick="openDetail(${i.id})">
      <td><div class="list-name">${esc(i.name)}</div></td>
      <td>${esc(i.system_name || '-')}</td>
      <td>${esc(i.version || '-')}</td>
      <td>${esc(i.category || '-')}</td>
      <td>${esc(i.system_group || '-')}</td>
      <td class="crit-${critKind(i.criticality)}">${esc(i.criticality || '-')}</td>
      <td>${(i.tech || []).map((t) => `<span class="tag">${esc(t)}</span>`).join('')}</td>
    </tr>
  `).join('');

  $('list-desc-body').innerHTML = list.map((i) => `
    <tr onclick="openDetail(${i.id})">
      <td><div class="list-name">${esc(i.name)}</div></td>
      <td>${esc(i.description || '-')}</td>
      <td>${esc(i.notes || '-')}</td>
      <td>${badge(i.status)}</td>
    </tr>
  `).join('');

  $('list-infra-body').innerHTML = list.map((i) => `
    <tr onclick="openDetail(${i.id})">
      <td><div class="list-name">${esc(i.name)}</div></td>
      <td>${linkListHtml(systemUrlList(i, false), { compact:true })}</td>
      <td>${linkListHtml(systemUrlList(i, true), { compact:true })}</td>
      <td>${esc(vmName(i, false))}</td>
      <td>${esc(vmIp(i, false))}</td>
      <td>${esc(vmName(i, true))}</td>
      <td>${esc(vmIp(i, true))}</td>
      <td>${esc(vmName(i, 'dev'))}</td>
      <td>${esc(vmIp(i, 'dev'))}</td>
      <td>${esc(relationVmText(i, 'access'))}</td>
      <td>${esc(relationVmText(i, 'administration'))}</td>
    </tr>
  `).join('');

  const systemsById = new Map(list.map((i) => [Number(i.id), i]));
  const systemIds = new Set(systemsById.keys());
  const dbList = App.databases
    .filter((d) => systemIds.has(Number(d.system_id)))
    .sort((a,b) => String(a.db_name || '').localeCompare(String(b.db_name || '')));

  if (!dbList.length) {
    $('list-db-body').innerHTML = '<tr><td colspan="10" style="color:var(--muted)">Nenhuma base de dados encontrada para os filtros aplicados.</td></tr>';
  } else {
    $('list-db-body').innerHTML = dbList.map((d) => {
      const systemId = Number(d.system_id);
      const system = systemsById.get(systemId);
      const systemName = String(system?.name || d.system_name || '-');
      const clickable = Number.isFinite(systemId) && systemsById.has(systemId);
      return `
      <tr${clickable ? ` onclick="openDetail(${systemId})"` : ''}>
        <td><div class="list-name">${esc(systemName)}</div></td>
        <td>${esc(d.db_name || '-')}</td>
        <td>${esc(d.db_user || '-')}</td>
        <td>${esc(String(d.vm_name || '').trim() || '-')}</td>
        <td>${esc(dbInstanceIp(d, false))}</td>
        <td>${esc(dbInstanceName(d, false))}</td>
        <td>${esc(String(d.vm_homolog_name || '').trim() || '-')}</td>
        <td>${esc(dbInstanceIp(d, true))}</td>
        <td>${esc(dbInstanceName(d, true))}</td>
        <td>${esc(d.notes || '-')}</td>
      </tr>
    `;
    }).join('');
  }

  $('list-support-body').innerHTML = list.map((i) => `
    <tr onclick="openDetail(${i.id})">
      <td><div class="list-name">${esc(i.name)}</div></td>
      <td>${esc(i.owner || '-')}</td>
      <td>${esc(i.responsible_sector || '-')}</td>
      <td>${esc(i.responsible_coordinator || '-')}</td>
      <td>${esc(i.extension_number || '-')}</td>
      <td>${esc(i.email || '-')}</td>
      <td>${esc(i.support || '-')}</td>
      <td>${esc(i.support_contact || '-')}</td>
    </tr>
  `).join('');

  $('list-ops-body').innerHTML = list.map((i) => `
    <tr onclick="openDetail(${i.id})">
      <td><div class="list-name">${esc(i.name)}</div></td>
      <td>${esc(i.analytics || '-')}</td>
      <td>${esc(i.ssl || '-')}</td>
      <td>${esc(i.waf || '-')}</td>
      <td>${esc(i.bundle || '-')}</td>
      <td>${esc(i.directory || '-')}</td>
      <td>${esc(i.size || '-')}</td>
      <td>${esc(i.repository || '-')}</td>
    </tr>
  `).join('');

  $('list-cards').innerHTML = list.map((i) => `
    <div class="list-mobile-card" onclick="openDetail(${i.id})">
      <div class="list-mobile-head">
        <div class="list-name">${esc(i.name)}</div>
        ${badge(i.status)}
      </div>
      <div class="list-mobile-grid">
        <div class="list-mobile-item"><span class="list-mobile-label">Sistema</span><span class="list-mobile-value">${esc(i.system_name || '-')}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">Categoria</span><span class="list-mobile-value">${esc(i.category || '-')}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">Grupo</span><span class="list-mobile-value">${esc(i.system_group || '-')}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">Criticidade</span><span class="list-mobile-value crit-${critKind(i.criticality)}">${esc(i.criticality || '-')}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">Responsavel Tecnico</span><span class="list-mobile-value">${esc(i.owner || '-')}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">Versao</span><span class="list-mobile-value">${esc(i.version || '-')}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">VM Producao</span><span class="list-mobile-value">${esc(vmName(i, false))} | ${esc(vmIp(i, false))}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">VM Homologacao</span><span class="list-mobile-value">${esc(vmName(i, true))} | ${esc(vmIp(i, true))}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">VM Desenvolvimento</span><span class="list-mobile-value">${esc(vmName(i, 'dev'))} | ${esc(vmIp(i, 'dev'))}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">URL</span><span class="list-mobile-value">${linkListHtml(systemUrlList(i, false), { compact:true })}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">URL Homologacao</span><span class="list-mobile-value">${linkListHtml(systemUrlList(i, true), { compact:true })}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">Descricao</span><span class="list-mobile-value">${esc(i.description || '-')}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">Observacoes</span><span class="list-mobile-value">${esc(i.notes || '-')}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">Analytics</span><span class="list-mobile-value">${esc(i.analytics || '-')}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">SSL</span><span class="list-mobile-value">${esc(i.ssl || '-')}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">WAF</span><span class="list-mobile-value">${esc(i.waf || '-')}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">Bundle</span><span class="list-mobile-value">${esc(i.bundle || '-')}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">Diretorio</span><span class="list-mobile-value">${esc(i.directory || '-')}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">Tamanho</span><span class="list-mobile-value">${esc(i.size || '-')}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">Repositorio</span><span class="list-mobile-value">${esc(i.repository || '-')}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">Bases de Dados</span><span class="list-mobile-value">${esc(databaseNamesText(i.id))}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">Hospedagem das Bases</span><span class="list-mobile-value">${esc(databaseHostsText(i.id))}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">SGBD / Versao</span><span class="list-mobile-value">${esc(databaseEngineText(i.id))}</span></div>
      </div>
      <div class="tags">${(i.tech || []).map((t) => `<span class="tag">${esc(t)}</span>`).join('')}</div>
    </div>
  `).join('');
}

function renderSystemsCards(list){
  $('result-count').textContent = `${list.length} resultado(s)`;
  const box = $('systems-cards');
  if (!box) return;

  if (!list.length) {
    box.innerHTML = '<div class="vm-report-empty">Nenhum sistema encontrado.</div>';
    return;
  }

  box.innerHTML = list.map((i) => {
    const cardStatusClass = `status-${statusKind(i.status)}`;
    const dbs = systemDatabases(i.id)
      .sort((a,b)=>String(a.db_name || '').localeCompare(String(b.db_name || '')));
    const techMarkup = (i.tech || []).length
      ? (i.tech || []).map((t) => `<span class="tag">${esc(t)}</span>`).join('')
      : '<span class="system-info-empty">Sem linguagens cadastradas.</span>';

    const dbMarkup = dbs.map((d) => {
      return `
        <div class="system-db-item">
          <div class="system-db-meta-grid">
            <div class="system-db-meta"><span>Base de Dados</span><strong>${esc(d.db_name || '-')}</strong></div>
            <div class="system-db-meta"><span>Usuario do Banco</span><strong>${esc(d.db_user || '-')}</strong></div>
            <div class="system-db-meta"><span>VM Producao</span><strong>${esc(String(d.vm_name || '').trim() || '-')}</strong></div>
            <div class="system-db-meta"><span>IP da Instancia</span><strong>${esc(dbInstanceIp(d, false))}</strong></div>
            <div class="system-db-meta"><span>Instancia SGBD</span><strong>${esc(dbInstanceName(d, false))}</strong></div>
            <div class="system-db-meta"><span>VM Homologacao</span><strong>${esc(String(d.vm_homolog_name || '').trim() || '-')}</strong></div>
            <div class="system-db-meta"><span>IP da Instancia Homologacao</span><strong>${esc(dbInstanceIp(d, true))}</strong></div>
            <div class="system-db-meta"><span>Instancia SGBD Homologacao</span><strong>${esc(dbInstanceName(d, true))}</strong></div>
          </div>
        </div>
      `;
    }).join('');

    return `
      <article class="system-info-card ${cardStatusClass}" onclick="openDetail(${i.id})">
        <div class="system-info-head">
          <div>
            <div class="system-info-name">${esc(i.name || '-')}</div>
            <div class="system-info-sub">${[String(i.system_name || '').trim(), String(i.version || '').trim()].filter(Boolean).map((part) => esc(part)).join(' ') || '-'}</div>
          </div>
          <div class="system-info-head-side">
            ${techMarkup.startsWith('<span class="system-info-empty">')
              ? techMarkup
              : `<div class="tags">${techMarkup}</div>`}
          </div>
        </div>
        ${systemUrlList(i, false).length
          ? `<div class="system-info-url-banner">${linkListHtml(systemUrlList(i, false))}</div>`
          : ''}
        <div class="system-info-desc-footer">
          <strong class="system-info-text">${esc(i.description || '-')}</strong>
        </div>

        <section class="system-info-section system-info-section-tech">
          <div class="system-info-title">Informacoes Tecnicas</div>
          <div class="system-info-grid">
            <div class="system-info-field"><span>Categoria</span><strong>${esc(i.category || '-')}</strong></div>
            <div class="system-info-field"><span>Grupo</span><strong>${esc(i.system_group || '-')}</strong></div>
            <div class="system-info-field"><span>Criticidade</span><strong class="crit-${critKind(i.criticality)}">${esc(i.criticality || '-')}</strong></div>
            <div class="system-info-field system-info-field-full"><span>Observacoes</span><strong>${esc(i.notes || '-')}</strong></div>
          </div>
        </section>

        <section class="system-info-section system-info-section-infra">
          <div class="system-info-title">Infraestrutura</div>
          <div class="system-info-grid">
            <div class="system-info-field"><span>URL Homologacao</span><div class="system-info-link">${linkListHtml(systemUrlList(i, true), { compact:true })}</div></div>
            <div class="system-info-field"><span>VM Producao</span><strong>${esc(vmName(i, false))}</strong></div>
            <div class="system-info-field"><span>IP Producao</span><strong>${esc(vmIp(i, false))}</strong></div>
            <div class="system-info-field"><span>VM Homologacao</span><strong>${esc(vmName(i, true))}</strong></div>
            <div class="system-info-field"><span>IP Homologacao</span><strong>${esc(vmIp(i, true))}</strong></div>
            <div class="system-info-field"><span>VM Desenvolvimento</span><strong>${esc(vmName(i, 'dev'))}</strong></div>
            <div class="system-info-field"><span>IP Desenvolvimento</span><strong>${esc(vmIp(i, 'dev'))}</strong></div>
            <div class="system-info-field"><span>Acesso</span><strong>${esc(relationVmText(i, 'access'))}</strong></div>
            <div class="system-info-field"><span>Administracao</span><strong>${esc(relationVmText(i, 'administration'))}</strong></div>
          </div>
        </section>

        ${dbs.length ? `
        <section class="system-info-section system-info-section-db">
          <div class="system-info-title">Base de Dados</div>
          <div class="system-db-list">${dbMarkup}</div>
        </section>
        ` : ''}

        <section class="system-info-section system-info-section-support">
          <div class="system-info-title">Contatos e Suporte</div>
          <div class="system-info-grid">
            <div class="system-info-field"><span>Responsavel Tecnico</span><strong>${esc(i.owner || '-')}</strong></div>
            <div class="system-info-field"><span>Coordenador Responsavel</span><strong>${esc(i.responsible_coordinator || '-')}</strong></div>
            <div class="system-info-field"><span>Ramal</span><strong>${esc(i.extension_number || '-')}</strong></div>
            <div class="system-info-field"><span>Email</span><strong>${esc(i.email || '-')}</strong></div>
            <div class="system-info-field"><span>Suporte</span><strong>${esc(i.support || '-')}</strong></div>
            <div class="system-info-field"><span>Contato Suporte</span><strong>${esc(i.support_contact || '-')}</strong></div>
          </div>
        </section>

        <section class="system-info-section system-info-section-ops">
          <div class="system-info-title">Deploy e Empacotamento</div>
          <div class="system-info-grid">
            <div class="system-info-field"><span>Analytics</span><strong>${esc(i.analytics || '-')}</strong></div>
            <div class="system-info-field"><span>SSL</span><strong>${esc(i.ssl || '-')}</strong></div>
            <div class="system-info-field"><span>WAF</span><strong>${esc(i.waf || '-')}</strong></div>
            <div class="system-info-field"><span>Bundle</span><strong>${esc(i.bundle || '-')}</strong></div>
            <div class="system-info-field"><span>Diretorio</span><strong>${esc(i.directory || '-')}</strong></div>
            <div class="system-info-field"><span>Tamanho</span><strong>${esc(i.size || '-')}</strong></div>
            <div class="system-info-field"><span>Repositorio</span><strong>${esc(i.repository || '-')}</strong></div>
          </div>
        </section>

        <div class="system-info-foot" onclick="event.stopPropagation()">
          <div class="system-info-foot-bottom">
            <div class="system-info-foot-main">
            <div class="system-info-tech-footer">
            </div>
            </div>
            <div class="system-info-actions">
              <div class="system-info-footer-field">
                <strong>${esc(i.responsible_sector || '-')}</strong>
              </div>
            </div>
          </div>
        </div>
      </article>
    `;
  }).join('');
}

function vmUsage(vmId){
  const prod = App.items.filter((s) => Number(s.vm_id) === Number(vmId));
  const hml = App.items.filter((s) => Number(s.vm_homolog_id) === Number(vmId));
  const dev = App.items.filter((s) => Number(s.vm_dev_id) === Number(vmId));
  const uniq = new Set([...prod.map((s)=>Number(s.id)), ...hml.map((s)=>Number(s.id)), ...dev.map((s)=>Number(s.id))]);
  return { prod, hml, dev, total: uniq.size };
}

function dbEngineVersionForVm(db, vmId){
  const id = Number(vmId);
  const isHomologVm = Number(db?.vm_homolog_id || 0) === id;
  const prodVersion = String(db?.db_engine_version || '').trim();
  const homologVersion = String(db?.db_engine_version_homolog || '').trim();
  return isHomologVm ? (homologVersion || prodVersion) : prodVersion;
}

function vmDatabases(vmId){
  const id = Number(vmId);
  return App.databases
    .filter((d) => Number(d.vm_id) === id || Number(d.vm_homolog_id) === id)
    .map((d) => ({ ...d, vm_version: dbEngineVersionForVm(d, id) }));
}

function renderDatabases(){
  const editable = canEdit();
  const table = document.querySelector('#view-bases .bases-table');
  table?.classList.toggle('readonly', !editable);
  const list = [...App.databases].sort((a,b)=>String(a.db_name || '').localeCompare(String(b.db_name || '')));
  if (!list.length) {
    $('db-body').innerHTML = '<tr><td colspan="11" style="color:var(--muted)">Nenhuma base de dados cadastrada.</td></tr>';
    $('db-cards').innerHTML = '<div class="db-mobile-card"><div class="db-mobile-value" style="color:var(--muted)">Nenhuma base de dados cadastrada.</div></div>';
    return;
  }

  $('db-body').innerHTML = list.map((d) => `
    <tr>
      <td>${esc(d.system_name || '-')}</td>
      <td>${esc(d.db_name || '-')}</td>
      <td>${esc(d.db_user || '-')}</td>
      <td>${esc(String(d.vm_name || '').trim() || '-')}</td>
      <td>${esc(dbInstanceIp(d, false))}</td>
      <td>${esc(dbInstanceName(d, false))}</td>
      <td>${esc(String(d.vm_homolog_name || '').trim() || '-')}</td>
      <td>${esc(dbInstanceIp(d, true))}</td>
      <td>${esc(dbInstanceName(d, true))}</td>
      <td>${esc(d.notes || '-')}</td>
      <td>${editable ? `<div class="actions"><button class="act" onclick="openDbFormById(${d.id})">&#9998;</button><button class="act del" onclick="deleteDb(${d.id})">&#128465;</button></div>` : '-'}</td>
    </tr>
  `).join('');

  $('db-cards').innerHTML = list.map((d) => `
    <div class="db-mobile-card">
      <div class="db-mobile-head">
        <div class="db-mobile-title">${esc(d.db_name || '-')}</div>
        <div class="db-mobile-engine">${esc(dbEngineVersionLabel(d, false))}</div>
      </div>
      <div class="db-mobile-grid">
        <div class="db-mobile-item"><span class="db-mobile-label">Sistema</span><span class="db-mobile-value">${esc(d.system_name || '-')}</span></div>
        <div class="db-mobile-item"><span class="db-mobile-label">Usuario do Banco</span><span class="db-mobile-value">${esc(d.db_user || '-')}</span></div>
        <div class="db-mobile-item"><span class="db-mobile-label">SGBD Producao</span><span class="db-mobile-value">${esc(dbEngineVersionLabel(d, false))}</span></div>
        <div class="db-mobile-item"><span class="db-mobile-label">SGBD Homologacao</span><span class="db-mobile-value">${esc(dbEngineVersionLabel(d, true))}</span></div>
        <div class="db-mobile-item"><span class="db-mobile-label">Maquina</span><span class="db-mobile-value">${esc(String(d.vm_name || '').trim() || '-')}</span></div>
        <div class="db-mobile-item"><span class="db-mobile-label">Instancia SGBD</span><span class="db-mobile-value">${esc(dbInstanceName(d, false))}</span></div>
        <div class="db-mobile-item"><span class="db-mobile-label">VM Homologacao</span><span class="db-mobile-value">${esc(String(d.vm_homolog_name || '').trim() || '-')}</span></div>
        <div class="db-mobile-item"><span class="db-mobile-label">IP da Instancia</span><span class="db-mobile-value">${esc(dbInstanceIp(d, false))}</span></div>
        <div class="db-mobile-item"><span class="db-mobile-label">IP Instancia Homologacao</span><span class="db-mobile-value">${esc(dbInstanceIp(d, true))}</span></div>
        <div class="db-mobile-item"><span class="db-mobile-label">Instancia SGBD Homologacao</span><span class="db-mobile-value">${esc(dbInstanceName(d, true))}</span></div>
        <div class="db-mobile-item"><span class="db-mobile-label">Observacoes</span><span class="db-mobile-value">${esc(d.notes || '-')}</span></div>
      </div>
      ${editable ? `<div class="db-mobile-actions">
        <button class="act" onclick="openDbFormById(${d.id})">&#9998;</button>
        <button class="act del" onclick="deleteDb(${d.id})">&#128465;</button>
      </div>` : ''}
    </div>
  `).join('');
}

function renderDns(){
  const body = $('dns-body');
  const cards = $('dns-cards');
  if (!body || !cards) return;

  const systems = [...App.items].sort((a,b)=>String(a.name || '').localeCompare(String(b.name || '')));
  if (!systems.length) {
    body.innerHTML = '<tr><td colspan="2" style="color:var(--muted)">Nenhum sistema cadastrado.</td></tr>';
    cards.innerHTML = '<div class="db-mobile-card"><div class="db-mobile-value" style="color:var(--muted)">Nenhum sistema cadastrado.</div></div>';
    return;
  }

  const rows = systems.flatMap((i) => {
    const prodIp = vmIp(i, false);
    const hmlIp = vmIp(i, true);
    const prodRows = systemUrlList(i, false).map((url) => ({ id: Number(i.id), url, ip: prodIp }));
    const hmlRows = systemUrlList(i, true).map((url) => ({ id: Number(i.id), url, ip: hmlIp }));

    if (!prodRows.length && String(prodIp).trim() !== '' && String(prodIp).trim() !== '-') {
      prodRows.push({ id: Number(i.id), url: '', ip: prodIp });
    }
    if (!hmlRows.length && String(hmlIp).trim() !== '' && String(hmlIp).trim() !== '-') {
      hmlRows.push({ id: Number(i.id), url: '', ip: hmlIp });
    }

    return [...prodRows, ...hmlRows];
  }).filter((r) => {
    const url = String(r.url || '').trim();
    const ip = String(r.ip || '').trim();
    const hasUrl = url !== '' && url !== '-';
    const hasIp = ip !== '' && ip !== '-';
    return hasUrl || hasIp;
  });

  if (!rows.length) {
    body.innerHTML = '<tr><td colspan="2" style="color:var(--muted)">Nenhum registro DNS com URL/IP informado.</td></tr>';
    cards.innerHTML = '<div class="db-mobile-card"><div class="db-mobile-value" style="color:var(--muted)">Nenhum registro DNS com URL/IP informado.</div></div>';
    return;
  }

  body.innerHTML = rows.map((r) => `
    <tr onclick="openDetail(${r.id})">
      <td>${linkHtml(r.url)}</td>
      <td>${esc(r.ip || '-')}</td>
    </tr>
  `).join('');

  cards.innerHTML = rows.map((r) => `
    <div class="dns-mobile-card" onclick="openDetail(${r.id})">
      <div class="db-mobile-grid">
        <div class="db-mobile-item"><span class="db-mobile-label">URL</span><span class="db-mobile-value">${linkHtml(r.url)}</span></div>
        <div class="db-mobile-item"><span class="db-mobile-label">IP</span><span class="db-mobile-value">${esc(r.ip || '-')}</span></div>
      </div>
    </div>
  `).join('');
}

function renderVmReport(sourceVms = null){
  const box = $('vm-report');
  const list = Array.isArray(sourceVms) ? sourceVms : App.vms;
  if (!list.length) {
    box.innerHTML = `<div class="vm-report-empty">${App.vms.length ? 'Nenhuma maquina encontrada para os filtros.' : 'Nenhuma maquina cadastrada.'}</div>`;
    return;
  }

  const groups = ['Producao','Homologacao','Desenvolvimento'].map((category) => ({
    category,
    items: list.filter((vm) => vmCategoryLabel(vm) === category).sort((a,b)=>String(a.name || '').localeCompare(String(b.name || '')))
  })).filter((g)=>g.items.length > 0);

  box.innerHTML = groups.map((group) => {
    const groupClass = `vm-report-group-${norm(group.category).replace(/[^a-z0-9]+/g, '-')}`;
    const typeBlocks = ['Sistemas','SGBD'].map((type) => {
      const typeItems = group.items.filter((vm) => vmTypeLabel(vm) === type);
      if (!typeItems.length) return '';
      return `
      <div class="vm-report-type-group">
        <div class="vm-report-type-title">${esc(type)}</div>
        <div class="vm-report-group-grid">
          ${typeItems.map((vm) => {
            const use = vmUsage(vm.id);
            const dbs = vmDatabases(vm.id);
            const osLabel = String(vm.os_name || '').trim();
            const languages = vmLanguageList(vm);
            const languageTags = languages.map((item) => vmLanguageTagText(vm, item)).filter(Boolean);
            const tech = vmTechList(vm);
            const instances = vmInstances(vm);
            const instanceTags = instances.map((inst) => `${inst.name || 'Instancia'}`);
            const stackTags = [...languageTags, ...tech];
            const specs = [
              vm.vcpus ? `${vm.vcpus} vCPU` : '',
              vm.ram ? `RAM ${vm.ram}` : '',
              vm.disk ? `Disco ${vm.disk}` : ''
            ].filter(Boolean);
            const systemsLinked = [...new Set([
              ...use.prod.map((s) => s.name),
              ...use.hml.map((s) => s.name),
              ...use.dev.map((s) => s.name)
            ])];
            const dbRows = dbs.map((d) => {
              const version = String(d.vm_version || '').trim();
              const user = String(d.db_user || '').trim();
              const engineLabel = `${d.db_engine || '-'}${version ? ` ${version}` : ''}`;
              const isHomologVm = Number(d?.vm_homolog_id || 0) === Number(vm.id);
              return {
                dbName: d.db_name || '-',
                engine: engineLabel || '-',
                user: user || '-',
                system: d.system_name || '-',
                instance: dbInstanceName(d, isHomologVm),
                instanceIp: dbInstanceIp(d, isHomologVm)
              };
            });
            return `
            <article class="vm-report-item">
              <div class="vm-report-top">
                <div class="vm-report-title">${esc(vm.name)}</div>
                <div class="vm-report-ip">IP ${esc(vm.ip || '-')}</div>
              </div>
              ${osLabel ? `<div class="vm-report-sub">SO: ${esc(osLabel)}</div>` : ''}
              ${specs.length ? `<div class="tags">${specs.map((s)=>`<span class="tag">${esc(s)}</span>`).join('')}</div>` : ''}
              ${stackTags.length ? `<div class="tags">${stackTags.map((tag)=>`<span class="tag">${esc(tag)}</span>`).join('')}</div>` : ''}
              ${instanceTags.length ? `<div class="tags">${instanceTags.map((tag)=>`<span class="tag">${esc(tag)}</span>`).join('')}</div>` : ''}

              ${systemsLinked.length ? `
              <div class="vm-report-block">
                <div class="vm-report-block-title">Sistemas Vinculados</div>
                <ul class="vm-report-list">${systemsLinked.map((x)=>`<li>${esc(x)}</li>`).join('')}</ul>
              </div>
              ` : ''}

              ${dbRows.length ? `
              <div class="vm-report-block">
                <div class="vm-report-block-title">Bases Vinculadas</div>
                <div class="vm-report-table-wrap">
                  <table class="vm-report-table vm-report-db-table">
                    <thead>
                      <tr><th>Base</th><th>Usuario</th><th>Instancia</th><th>IP da Instancia</th><th>Sistema</th></tr>
                    </thead>
                    <tbody>
                      ${dbRows.map((r) => `
                        <tr>
                          <td>${esc(r.dbName)}</td>
                          <td>${esc(r.user)}</td>
                          <td>${esc(r.instance)}</td>
                          <td>${esc(r.instanceIp)}</td>
                          <td>${esc(r.system)}</td>
                        </tr>
                      `).join('')}
                    </tbody>
                  </table>
                </div>
              </div>
              ` : ''}
            </article>
          `;
          }).join('')}
        </div>
      </div>
      `;
    }).filter(Boolean).join('');
    return `
    <section class="vm-report-group ${groupClass}">
      <div class="vm-report-group-head">
        <div class="vm-report-group-title">${esc(group.category)}</div>
        <div class="vm-report-group-count">${group.items.length} maquina(s)</div>
      </div>
      ${typeBlocks}
    </section>
  `;
  }).join('');
}

function filterVmsByCriteria(vms, criteria={}){
  const vmq = String(criteria.q || '').toLowerCase();
  const vmcatf = String(criteria.category || '').trim();
  const vmtypef = String(criteria.type || '').trim();
  const vmaccessf = String(criteria.access || '').trim();
  const vmadminf = String(criteria.administration || '').trim();

  return vms.filter((vm) => {
    if (vmcatf && vmCategoryLabel(vm) !== vmcatf) return false;
    if (vmtypef && vmTypeLabel(vm) !== vmtypef) return false;
    if (vmaccessf && vmAccessLabel(vm) !== vmaccessf) return false;
    if (vmadminf && vmAdministrationLabel(vm) !== vmadminf) return false;
    if (!vmq) return true;
    const use = vmUsage(vm.id);
    const linkedSystems = [...new Set([...use.prod.map((s)=>s.name), ...use.hml.map((s)=>s.name), ...use.dev.map((s)=>s.name)])].join(' ');
    const instancesText = vmInstances(vm).map((inst) => `${inst.name || ''} ${inst.ip || ''}`).join(' ');
    const languageText = vmLanguageList(vm).join(' ');
    const languageVersionText = Object.values(vmLanguageVersions(vm)).map((v) => String(v || '').trim()).filter(Boolean).join(' ');
    return [
      vm.name,
      vm.ip,
      vm.os_name,
      vm.vcpus,
      vm.ram,
      vm.disk,
      vmCategoryLabel(vm),
      vmTypeLabel(vm),
      vmAccessLabel(vm),
      vmAdministrationLabel(vm),
      languageText,
      languageVersionText,
      vmTechList(vm).join(' '),
      instancesText,
      linkedSystems
    ].join(' ').toLowerCase().includes(vmq);
  });
}

function renderMachines(){
  const container = $('vm-sections');
  const vmCountEl = $('vm-result-count');
  const editable = canEdit();
  if (!App.vms.length) {
    if (vmCountEl) vmCountEl.textContent = '0 resultado(s)';
    container.innerHTML = '<div class="vm-section-empty">Nenhuma maquina cadastrada.</div>';
    return;
  }

  const filteredVms = filterVmsByCriteria(App.vms, {
    q: $('vmq')?.value || '',
    category: $('vmcatf')?.value || '',
    type: $('vmtypef')?.value || '',
    access: $('vmaccessf')?.value || '',
    administration: $('vmadminf')?.value || ''
  });
  if (vmCountEl) vmCountEl.textContent = `${filteredVms.length} resultado(s)`;

  if (!filteredVms.length) {
    container.innerHTML = '<div class="vm-section-empty">Nenhuma maquina encontrada para os filtros.</div>';
    return;
  }

  const categories = ['Producao','Homologacao','Desenvolvimento'];
  container.innerHTML = categories.map((category) => {
    const vms = filteredVms
      .filter((vm) => vmCategoryLabel(vm) === category)
      .sort((a,b)=>String(a.name || '').localeCompare(String(b.name || '')));
    if (!vms.length) return '';

    const relationHeader = category === 'Producao'
      ? 'Sistemas em Producao'
      : category === 'Homologacao'
        ? 'Sistemas em Homologacao'
        : 'Sistemas em Desenvolvimento';
    const relationLabelCard = category === 'Producao'
      ? 'Producao'
      : category === 'Homologacao'
        ? 'Homologacao'
        : 'Desenvolvimento';
    const typeBlocks = ['Sistemas','SGBD'].map((type) => {
      const typeVms = vms.filter((vm) => vmTypeLabel(vm) === type);
      if (!typeVms.length) return '';

      const rows = typeVms.map((vm) => {
        const use = vmUsage(vm.id);
        const dbs = vmDatabases(vm.id);
        const languages = vmLanguageList(vm);
        const tech = vmTechList(vm);
        const languageTags = languages.map((item) => vmLanguageTagText(vm, item)).filter(Boolean);
        const techTags = [...tech];
        const specs = [vm.vcpus ? `${vm.vcpus} vCPU` : '', vm.ram || '', vm.disk || ''].filter(Boolean).join(' | ');
        const relationCount = category === 'Producao'
          ? use.prod.length
          : category === 'Homologacao'
            ? use.hml.length
            : use.dev.length;
        const metricClass = type === 'SGBD' ? 'vm-db-col' : 'vm-rel-col';
        const metricValue = type === 'SGBD' ? dbs.length : relationCount;
        const diagBtn = vmSupportsDiagnostics(vm)
          ? `<button class="act diag" onclick="openVmDiagnosticPageById(${vm.id})" title="Diagnostico JSON (${esc(vmDiagnosticTechs(vm).join('/') || 'VM')})">&#128202;</button>`
          : '';
        return `
          <tr>
            <td class="vm-name-col">${esc(vm.name)}</td>
            <td class="vm-ip-col">${esc(vm.ip || '-')}</td>
            <td>${esc(vmCategoryLabel(vm))}</td>
            <td>${esc(vmTypeLabel(vm))}</td>
            <td>${esc(vmAccessLabel(vm))}</td>
            <td>${esc(vmAdministrationLabel(vm))}</td>
            <td class="vm-os-col">${esc(vm.os_name || '-')}</td>
            <td class="vm-res-col">${esc(specs || '-')}</td>
            <td class="vm-lang-col">${languageTags.length ? `<div class="vm-tech-tags">${languageTags.map((t)=>`<span class="tag">${esc(t)}</span>`).join('')}</div>` : '-'}</td>
            <td class="vm-tech-col">${techTags.length ? `<div class="vm-tech-tags">${techTags.map((t)=>`<span class="tag">${esc(t)}</span>`).join('')}</div>` : '-'}</td>
            <td class="${metricClass}">${metricValue}</td>
            <td class="vm-actions-col">${editable ? `<div class="actions">${diagBtn}<button class="act" onclick="openVmFormById(${vm.id})">&#9998;</button><button class="act del" onclick="archiveVm(${vm.id})">&#128230;</button></div>` : (diagBtn ? `<div class="actions">${diagBtn}</div>` : '-')}</td>
          </tr>
        `;
      }).join('');

      const cards = typeVms.map((vm) => {
        const use = vmUsage(vm.id);
        const dbs = vmDatabases(vm.id);
        const languages = vmLanguageList(vm);
        const tech = vmTechList(vm);
        const instances = vmInstances(vm);
        const languageTags = languages.map((item) => vmLanguageTagText(vm, item)).filter(Boolean);
        const specs = [vm.vcpus ? `${vm.vcpus} vCPU` : '', vm.ram || '', vm.disk || ''].filter(Boolean);
        const relationCount = category === 'Producao'
          ? use.prod.length
          : category === 'Homologacao'
            ? use.hml.length
            : use.dev.length;
        const diagBtn = vmSupportsDiagnostics(vm)
          ? `<button class="act diag" onclick="openVmDiagnosticPageById(${vm.id})" title="Diagnostico JSON (${esc(vmDiagnosticTechs(vm).join('/') || 'VM')})">&#128202;</button>`
          : '';
        return `
          <div class="vm-mobile-card">
            <div class="vm-mobile-title">${esc(vm.name)}</div>
            <div class="vm-mobile-ip">${esc(vm.ip || '-')}</div>
            <div class="vm-mobile-ip">Categoria: ${esc(vmCategoryLabel(vm))} | Tipo: ${esc(vmTypeLabel(vm))}</div>
            <div class="vm-mobile-ip">Acesso: ${esc(vmAccessLabel(vm))} | Administracao: ${esc(vmAdministrationLabel(vm))}</div>
            ${vm.os_name ? `<div class="vm-mobile-ip">SO: ${esc(vm.os_name)}</div>` : ''}
            ${specs.length ? `<div class="tags">${specs.map((s)=>`<span class="tag">${esc(s)}</span>`).join('')}</div>` : ''}
            ${languageTags.length ? `<div class="tags">${languageTags.map((t)=>`<span class="tag">${esc(t)}</span>`).join('')}</div>` : ''}
            ${tech.length ? `<div class="tags">${tech.map((t)=>`<span class="tag">${esc(t)}</span>`).join('')}</div>` : ''}
            ${instances.length ? `<div class="tags">${instances.map((inst)=>`<span class="tag">${esc(`${inst.name || 'Instancia'}`)}</span>`).join('')}</div>` : ''}
            <div class="vm-mobile-stats">
              <div class="vm-mobile-stat"><div class="vm-mobile-stat-label">${relationLabelCard}</div><div class="vm-mobile-stat-value">${relationCount}</div></div>
              <div class="vm-mobile-stat"><div class="vm-mobile-stat-label">Bases</div><div class="vm-mobile-stat-value">${dbs.length}</div></div>
              <div class="vm-mobile-stat"><div class="vm-mobile-stat-label">Total</div><div class="vm-mobile-stat-value">${use.total}</div></div>
            </div>
            ${(editable || diagBtn) ? `<div class="vm-mobile-actions">
              ${diagBtn}
              ${editable ? `<button class="act" onclick="openVmFormById(${vm.id})">&#9998;</button><button class="act del" onclick="archiveVm(${vm.id})">&#128230;</button>` : ''}
            </div>` : ''}
          </div>
        `;
      }).join('');

      return `
        <div class="vm-type-group">
        <div class="vm-type-title">${esc(type)}</div>
        <div class="table-wrap vm-desktop-table">
            <table class="vm-compact-table">
              <thead><tr><th class="vm-name-col">Nome da Maquina</th><th class="vm-ip-col">IP</th><th>Categoria</th><th>Tipo</th><th>Acesso</th><th>Administracao</th><th class="vm-os-col">Sistema Operacional</th><th class="vm-res-col">Recursos (vCPU | RAM | Disco)</th><th class="vm-lang-col">Linguagem</th><th class="vm-tech-col">Tecnologias</th><th class="${type === 'SGBD' ? 'vm-db-col' : 'vm-rel-col'}">${type === 'SGBD' ? 'Bases de Dados' : esc(relationHeader)}</th><th class="vm-actions-col">${editable ? 'Acoes' : 'Diagnostico'}</th></tr></thead>
              <tbody>${rows}</tbody>
            </table>
          </div>
          <div class="vm-mobile-cards vm-section-cards">${cards}</div>
        </div>
      `;
    }).filter(Boolean).join('');

    const categoryClass = `vm-section-${norm(category).replace(/[^a-z0-9]+/g, '-')}`;
    return `
      <div class="vm-section ${categoryClass}">
        <div class="vm-section-title">${esc(category)}</div>
        ${typeBlocks}
      </div>
    `;
  }).join('');
}

function renderVmReportTab(){
  const resultEl = $('vmr-result-count');
  const filteredVms = filterVmsByCriteria(App.vms, {
    q: $('vmrq')?.value || '',
    category: $('vmrcatf')?.value || '',
    type: $('vmrtypef')?.value || '',
    access: $('vmraccessf')?.value || '',
    administration: $('vmradminf')?.value || ''
  });
  if (resultEl) resultEl.textContent = `${filteredVms.length} resultado(s)`;
  renderVmReport(filteredVms);
}

function renderArchived(){
  const systems = App.archived.systems || [];
  const vms = App.archived.vms || [];
  const editable = canEdit();
  const admin = isAdmin();
  const archivedSystemsTable = document.querySelector('#view-arquivados .archived-systems-table');
  const archivedVmsTable = document.querySelector('#view-arquivados .archived-vms-table');
  archivedSystemsTable?.classList.toggle('readonly', !editable);
  archivedVmsTable?.classList.toggle('readonly', !editable);

  if (!systems.length) {
    $('archived-systems-body').innerHTML = '<tr><td colspan="7" style="color:var(--muted)">Nenhum sistema arquivado.</td></tr>';
  } else {
    $('archived-systems-body').innerHTML = systems.map((i) => `
      <tr>
        <td>${esc(i.name || '-')}</td>
        <td>${esc(i.system_name || '-')}</td>
        <td>${badge(i.status)}</td>
        <td>${esc(vmName(i, false))}</td>
        <td>${esc(vmName(i, true))}</td>
        <td>${esc(i.archived_at || '-')}</td>
        <td>${editable ? `<div class="actions"><button class="act" onclick="restoreSystem(${i.id})">&#8634;</button>${admin ? `<button class="act del" onclick="deleteSystemPermanent(${i.id})">&#128465;</button>` : ''}</div>` : '-'}</td>
      </tr>
    `).join('');
  }

  if (!vms.length) {
    $('archived-vms-body').innerHTML = '<tr><td colspan="5" style="color:var(--muted)">Nenhuma maquina arquivada.</td></tr>';
  } else {
    $('archived-vms-body').innerHTML = vms.map((vm) => `
      <tr>
        <td>${esc(vm.name || '-')}</td>
        <td>${esc(vm.ip || '-')}</td>
        <td>${Number(vm.system_count || 0)}</td>
        <td>${esc(vm.archived_at || '-')}</td>
        <td>${editable ? `<div class="actions"><button class="act" onclick="restoreVm(${vm.id})">&#8634;</button>${admin ? `<button class="act del" onclick="deleteVmPermanent(${vm.id})">&#128465;</button>` : ''}</div>` : '-'}</td>
      </tr>
    `).join('');
  }
}

function renderCurrent(){
  const active = App.items.filter((i)=>statusKind(i.status)==='active').length;
  const countEl = $('count');
  if (countEl) {
    countEl.innerHTML = `${App.items.length} sistemas &#8226; ${App.databases.length} bases &#8226; ${active} ativos`;
  }

  if (App.view === 'dashboard') {
    $('result-count').textContent = '';
    renderDashboard();
    return;
  }

  if (App.view === 'maquinas') {
    $('result-count').textContent = '';
    renderMachines();
    return;
  }

  if (App.view === 'vm-relatorio') {
    $('result-count').textContent = '';
    renderVmReportTab();
    return;
  }

  if (App.view === 'bases') {
    $('result-count').textContent = '';
    renderDatabases();
    return;
  }

  if (App.view === 'dns') {
    $('result-count').textContent = '';
    renderDns();
    return;
  }

  if (App.view === 'arquivados') {
    $('result-count').textContent = '';
    renderArchived();
    return;
  }

  const list = filteredItems();
  if (App.view === 'cards') {
    renderSystemsCards(list);
    return;
  }
  if (App.view === 'lista') renderList(list);
}

function openFormById(id){
  const item = App.items.find((x)=>Number(x.id)===Number(id));
  if (item) openForm(item);
}

function openForm(item=null){
  const editable = canEdit();
  const isEdit = Boolean(item?.id);
  $('ftitle').textContent = item ? (editable ? 'Editar Sistema' : 'Visualizar Sistema') : 'Novo Sistema';
  $('bsave').textContent = item ? 'Salvar Alteracoes' : 'Salvar';
  $('barchive-system').classList.toggle('hidden', !isEdit || !editable);
  $('fid').value = item?.id || '';
  $('fname').value = item?.name || '';
  $('fsystem').value = item?.system_name || '';
  $('fver').value = item?.version || '';
  $('fcat').value = item?.category || 'Outro';
  $('fgroup').value = item?.system_group || '';
  $('fst').value = item?.status || 'Ativo';
  $('fcrit').value = item?.criticality || 'Media';
  $('fowner').value = item?.owner || '';
  $('furl').value = joinUrlList(systemUrlList(item, false));
  $('furl_homolog').value = joinUrlList(systemUrlList(item, true));
  $('fdesc').value = item?.description || '';
  $('fsector').value = item?.responsible_sector || '';
  $('fcoordinator').value = item?.responsible_coordinator || '';
  $('fextension').value = item?.extension_number || '';
  $('femail').value = item?.email || '';
  $('fsupport').value = item?.support || '';
  $('fsupport_contact').value = item?.support_contact || '';
  $('fanalytics').value = item?.analytics || '';
  $('fssl').value = item?.ssl || '';
  $('fwaf').value = item?.waf || '';
  $('fbundle').value = item?.bundle || '';
  $('fdirectory').value = item?.directory || '';
  $('fsize').value = item?.size || '';
  $('frepository').value = item?.repository || '';
  $('ftech').value = (item?.tech || []).join(', ');
  $('fnotes').value = item?.notes || '';

  populateVmSelects();
  $('fvm_id').value = item?.vm_id ? String(item.vm_id) : '';
  $('fvm_homolog_id').value = item?.vm_homolog_id ? String(item.vm_homolog_id) : '';
  $('fvm_dev_id').value = item?.vm_dev_id ? String(item.vm_dev_id) : '';
  if ($('btn-manage-vms')) {
    $('btn-manage-vms').disabled = !editable;
    $('btn-manage-vms').classList.toggle('hidden', !editable);
  }

  const fields = document.querySelectorAll('#mform input, #mform select, #mform textarea');
  fields.forEach((el) => {
    if (el.id === 'fid') return;
    el.disabled = !editable;
  });
  $('bsave').classList.toggle('hidden', !editable);
  $('bsave').disabled = !editable || $('fname').value.trim() === '';

  toggleSave();
  $('mform').classList.remove('hidden');
}

function toggleSave(){
  if (!canEdit()) {
    $('bsave').disabled = true;
    return;
  }
  $('bsave').disabled = $('fname').value.trim() === '';
}

function openDetail(id){
  openFormById(id);
}

function openVmReadOnlyById(id){
  const vmId = Number(id || 0);
  if (!vmId) return;
  const vm = App.vms.find((x)=>Number(x.id)===vmId);
  setView('maquinas');
  const vmSearch = $('vmq');
  if (vmSearch) {
    vmSearch.value = vm ? `${vm.name} ${vm.ip || ''}`.trim() : String(vmId);
    renderMachines();
  }
}

function openDbReadOnlyById(id){
  const dbId = Number(id || 0);
  if (!dbId) return;
  const db = App.databases.find((x)=>Number(x.id)===dbId);
  const systemId = Number(db?.system_id || 0);
  if (systemId > 0) {
    openDetail(systemId);
    return;
  }
  setView('bases');
}

async function saveSystem(){
  if (!ensureCanEdit()) return;
  const data = {
    id:$('fid').value || null,
    name:$('fname').value.trim(),
    system_name:$('fsystem').value.trim(),
    version:$('fver').value.trim(),
    category:$('fcat').value.trim() || 'Outro',
    system_group:$('fgroup').value.trim(),
    status:$('fst').value.trim() || 'Ativo',
    criticality:$('fcrit').value.trim() || 'Media',
    owner:$('fowner').value.trim(),
    url:joinUrlList($('furl').value),
    vm_id:Number($('fvm_id').value) || null,
    vm_homolog_id:Number($('fvm_homolog_id').value) || null,
    vm_dev_id:Number($('fvm_dev_id').value) || null,
    url_homolog:joinUrlList($('furl_homolog').value),
    description:$('fdesc').value.trim(),
    responsible_sector:$('fsector').value.trim(),
    responsible_coordinator:$('fcoordinator').value.trim(),
    extension_number:$('fextension').value.trim(),
    email:$('femail').value.trim(),
    support:$('fsupport').value.trim(),
    support_contact:$('fsupport_contact').value.trim(),
    analytics:$('fanalytics').value.trim(),
    ssl:$('fssl').value.trim(),
    waf:$('fwaf').value.trim(),
    bundle:$('fbundle').value.trim(),
    directory:$('fdirectory').value.trim(),
    size:$('fsize').value.trim(),
    repository:$('frepository').value.trim(),
    notes:$('fnotes').value.trim(),
    tech:$('ftech').value.split(',').map((x)=>x.trim()).filter(Boolean),
  };

  try{
    const r = await api('save', data);
    if(!r.ok) throw new Error(r.error || 'Erro ao salvar');
    await refreshAll();
    closeModal('mform');
    toast(data.id ? 'Sistema atualizado' : 'Sistema adicionado');
  }catch(e){
    toast('Erro ao salvar: ' + (e.message || '?'), true);
  }
}

async function archiveSystem(id){
  if (!ensureCanEdit()) return;
  const item = App.items.find((x)=>Number(x.id)===Number(id));
  if(!confirm(`Arquivar ${item?.name || 'sistema'}?`)) return;
  try{
    const r = await api('archive', {id});
    if(!r.ok) throw new Error(r.error || 'Erro ao arquivar');
    await refreshAll();
    closeModal('mform');
    closeModal('mdetail');
    toast('Sistema arquivado');
  }catch(e){ toast('Erro ao arquivar: ' + (e.message || '?'), true); }
}

function archiveCurrentSystem(){
  const id = Number($('fid').value || 0);
  if (!id) return;
  archiveSystem(id);
}

async function restoreSystem(id){
  if (!ensureCanEdit()) return;
  if(!confirm('Restaurar sistema arquivado?')) return;
  try{
    const r = await api('restore', {id});
    if(!r.ok) throw new Error(r.error || 'Erro ao restaurar');
    await refreshAll();
    toast('Sistema restaurado');
  }catch(e){ toast('Erro ao restaurar: ' + (e.message || '?'), true); }
}

async function deleteSystemPermanent(id){
  if (!ensureAdmin()) return;
  if(!confirm('Excluir sistema definitivamente? Esta acao nao pode ser desfeita.')) return;
  try{
    const r = await api('delete', {id});
    if(!r.ok) throw new Error(r.error || 'Erro ao excluir');
    await refreshAll();
    toast('Sistema excluido definitivamente');
  }catch(e){ toast('Erro ao excluir: ' + (e.message || '?'), true); }
}

function openDbFormById(id){
  if (!ensureCanEdit()) return;
  const item = App.databases.find((x)=>Number(x.id)===Number(id));
  if (item) openDbForm(item);
}

function openDbForm(item=null){
  if (!ensureCanEdit()) return;
  $('dbtitle').textContent = item ? 'Editar Base de Dados' : 'Nova Base de Dados';
  $('fdbid').value = item?.id || '';
  $('fdbname').value = item?.db_name || '';
  $('fdbuser').value = item?.db_user || '';
  $('fdbnotes').value = item?.notes || '';

  populateDbSelects();
  $('fdbsystem').value = item?.system_id ? String(item.system_id) : '';
  $('fdbvm').value = item?.vm_id ? String(item.vm_id) : '';
  $('fdbvmh').value = item?.vm_homolog_id ? String(item.vm_homolog_id) : '';
  syncDbInstanceOptions(
    {
      name: String(item?.db_instance_name || '').trim(),
      ip: String(item?.db_instance_ip || '').trim()
    },
    {
      name: String(item?.db_instance_homolog_name || '').trim(),
      ip: String(item?.db_instance_homolog_ip || '').trim()
    }
  );
  syncDbHomologIp();
  $('mdb').classList.remove('hidden');
}

async function saveDb(){
  if (!ensureCanEdit()) return;
  const selectedInstance = selectedDbInstance('fdbinstance');
  const selectedHomologInstance = selectedDbInstance('fdbinstanceh');
  const data = {
    id: $('fdbid').value || null,
    system_id: Number($('fdbsystem').value) || null,
    vm_id: Number($('fdbvm').value) || null,
    vm_homolog_id: Number($('fdbvmh').value) || null,
    db_name: $('fdbname').value.trim(),
    db_user: $('fdbuser').value.trim(),
    db_engine: '',
    db_engine_version: '',
    db_engine_version_homolog: '',
    db_instance_name: selectedInstance.name,
    db_instance_ip: selectedInstance.ip,
    db_instance_homolog_name: selectedHomologInstance.name,
    db_instance_homolog_ip: selectedHomologInstance.ip,
    notes: $('fdbnotes').value.trim(),
  };

  if (!data.system_id || !data.vm_id || !data.db_name || !data.db_instance_ip) {
    toast('Informe sistema, maquina, instancia SGBD e nome da base.', true);
    return;
  }
  if (data.vm_homolog_id && !data.db_instance_homolog_ip) {
    toast('Selecione a instancia SGBD de homologacao.', true);
    return;
  }

  try{
    const r = await api('db-save', data);
    if(!r.ok) throw new Error(r.error || 'Erro ao salvar base');

    if(data.id) App.databases = App.databases.map((x)=>Number(x.id)===Number(data.id) ? r.data : x);
    else App.databases.push(r.data);

    closeModal('mdb');
    renderCurrent();
    toast(data.id ? 'Base atualizada' : 'Base cadastrada');
  }catch(e){
    toast('Erro ao salvar base: ' + (e.message || '?'), true);
  }
}

async function deleteDb(id){
  if (!ensureCanEdit()) return;
  const item = App.databases.find((x)=>Number(x.id)===Number(id));
  if(!confirm(`Excluir base ${item?.db_name || ''} definitivamente?`)) return;
  try{
    const r = await api('db-delete', {id});
    if(!r.ok) throw new Error(r.error || 'Erro ao excluir base');
    App.databases = App.databases.filter((x)=>Number(x.id)!==Number(id));
    renderCurrent();
    toast('Base excluida');
  }catch(e){ toast('Erro ao excluir base: ' + (e.message || '?'), true); }
}

function openVmFormById(id){
  if (!ensureCanEdit()) return;
  const vm = App.vms.find((x)=>Number(x.id)===Number(id));
  if (vm) openVmForm(vm);
}

function openVmForm(vm=null){
  if (!ensureCanEdit()) return;
  $('vmtitle').textContent = vm ? 'Editar Maquina' : 'Nova Maquina';
  $('fvmid').value = vm?.id || '';
  $('fvmname').value = vm?.name || '';
  $('fvmip').value = vm?.ip || '';
  $('fvmcategory').value = vmCategoryLabel(vm);
  $('fvmtype').value = vmTypeLabel(vm);
  $('fvmaccess').value = vmAccessLabel(vm);
  $('fvmadministration').value = vmAdministrationLabel(vm);
  $('fvmos').value = vm?.os_name || '';
  $('fvmvcpus').value = vm?.vcpus || '';
  $('fvmram').value = vm?.ram || '';
  $('fvmdisk').value = vm?.disk || '';
  $('fvmlanguage').value = vmLanguageList(vm).join(', ');
  $('fvmtech').value = vmTechList(vm).join(', ');
  $('fvminstances').value = vmInstancesText(vm);
  $('mvm').classList.remove('hidden');
}

async function saveVm(){
  if (!ensureCanEdit()) return;
  const instances = parseVmInstancesInput($('fvminstances').value);
  const data = {
    id: $('fvmid').value || null,
    name: $('fvmname').value.trim(),
    ip: $('fvmip').value.trim(),
    vm_category: $('fvmcategory').value.trim(),
    vm_type: $('fvmtype').value.trim(),
    vm_access: $('fvmaccess').value.trim(),
    vm_administration: $('fvmadministration').value.trim(),
    os_name: $('fvmos').value.trim(),
    vcpus: $('fvmvcpus').value.trim(),
    ram: $('fvmram').value.trim(),
    disk: $('fvmdisk').value.trim(),
    vm_instances: instances,
    vm_language: $('fvmlanguage').value.split(',').map((x)=>x.trim()).filter(Boolean),
    vm_tech: $('fvmtech').value.split(',').map((x)=>x.trim()).filter(Boolean),
  };

  if (!data.name || !data.ip) {
    toast('Informe nome e IP da maquina.', true);
    return;
  }
  if (data.vm_type === 'SGBD' && !data.vm_instances.length) {
    toast('Para VM do tipo SGBD informe ao menos uma instancia com IP.', true);
    return;
  }

  try {
    const r = await api('vm-save', data);
    if(!r.ok) throw new Error(r.error || 'Erro ao salvar maquina');
    await refreshAll();
    closeModal('mvm');
    toast(data.id ? 'Maquina atualizada' : 'Maquina cadastrada');
  } catch (e) {
    toast('Erro ao salvar maquina: ' + (e.message || '?'), true);
  }
}

function openVmDiagnosticPageById(id){
  if (!App.auth.authenticated) {
    toast('Faca login para acessar diagnosticos.', true);
    return;
  }
  const vm = App.vms.find((x)=>Number(x.id)===Number(id));
  if (!vm) {
    toast('Maquina nao encontrada.', true);
    return;
  }
  if (!vmSupportsDiagnostics(vm)) {
    toast('Diagnostico JSON habilitado para VMs de sistema com tecnologia PHP e/ou R.', true);
    return;
  }
  window.location.href = `vm_diagnostic.php?id=${encodeURIComponent(String(id))}`;
}

async function openVmDiagnosticById(id){
  openVmDiagnosticPageById(id);
}

async function archiveVm(id){
  if (!ensureCanEdit()) return;
  const vm = App.vms.find((x)=>Number(x.id)===Number(id));
  if(!confirm(`Arquivar maquina ${vm?.name || ''}?`)) return;

  try{
    const r = await api('vm-archive', {id});
    if(!r.ok) throw new Error(r.error || 'Erro ao arquivar maquina');
    await refreshAll();
    toast('Maquina arquivada');
  } catch (e) {
    toast('Erro ao arquivar maquina: ' + (e.message || '?'), true);
  }
}

async function restoreVm(id){
  if (!ensureCanEdit()) return;
  if(!confirm('Restaurar maquina arquivada?')) return;
  try{
    const r = await api('vm-restore', {id});
    if(!r.ok) throw new Error(r.error || 'Erro ao restaurar maquina');
    await refreshAll();
    toast('Maquina restaurada');
  }catch(e){ toast('Erro ao restaurar maquina: ' + (e.message || '?'), true); }
}

async function deleteVmPermanent(id){
  if (!ensureAdmin()) return;
  if(!confirm('Excluir maquina definitivamente? Esta acao nao pode ser desfeita.')) return;
  try{
    const r = await api('vm-delete', {id});
    if(!r.ok) throw new Error(r.error || 'Erro ao excluir maquina');
    await refreshAll();
    toast('Maquina excluida definitivamente');
  }catch(e){ toast('Erro ao excluir maquina: ' + (e.message || '?'), true); }
}

async function refreshArchived(){
  const archivedRes = await api('archived-list');
  if(!archivedRes.ok) throw new Error(archivedRes.error || 'Erro ao carregar arquivados');
  App.archived = archivedRes.data || { systems: [], vms: [] };
}

async function refreshAll(){
  const [systemsRes, vmRes, dbRes, archivedRes] = await Promise.all([api('list'), api('vm-list'), api('db-list'), api('archived-list')]);
  if(!systemsRes.ok) throw new Error(systemsRes.error || 'Erro ao carregar sistemas');
  if(!vmRes.ok) throw new Error(vmRes.error || 'Erro ao carregar maquinas');
  if(!dbRes.ok) throw new Error(dbRes.error || 'Erro ao carregar bases');
  if(!archivedRes.ok) throw new Error(archivedRes.error || 'Erro ao carregar arquivados');

  App.items = systemsRes.data || [];
  App.vms = vmRes.data || [];
  App.databases = dbRes.data || [];
  App.archived = archivedRes.data || { systems: [], vms: [] };
  App.vms.sort((a,b)=>String(a.name || '').localeCompare(String(b.name || '')));
  populateFilters();
  populateVmTabFilters();
  populateVmSelects();
  populateDbSelects();
  renderCurrent();
}

function activeModalId(){
  const ids = ['mauth','mpassword','mbackup','mdb','mvm','mform','mdetail'];
  for (const id of ids) {
    const el = $(id);
    if (el && !el.classList.contains('hidden')) return id;
  }
  return '';
}

function handleModalKeyboardShortcuts(ev){
  const modalId = activeModalId();
  if (!modalId) return;

  if (ev.key === 'Escape') {
    ev.preventDefault();
    closeModal(modalId);
    return;
  }

  if (ev.key !== 'Enter') return;
  if (ev.isComposing || ev.shiftKey || ev.ctrlKey || ev.altKey || ev.metaKey) return;

  const tag = String(ev.target?.tagName || '').toUpperCase();
  if (tag === 'TEXTAREA' || tag === 'SELECT' || tag === 'BUTTON') return;
  if (modalId === 'mdetail') return;
  if (modalId === 'mbackup') return;

  ev.preventDefault();
  if (modalId === 'mauth') { login(); return; }
  if (modalId === 'mpassword') { changePassword(); return; }
  if (modalId === 'mform') { saveSystem(); return; }
  if (modalId === 'mvm') { saveVm(); return; }
  if (modalId === 'mdb') { saveDb(); return; }
}

document.addEventListener('keydown', handleModalKeyboardShortcuts);

async function boot(){
  try{
    await fetchAuthStatus();
    await refreshAll();
    $('loading').style.display = 'none';
    setView(App.view);
  }catch(e){
    $('loading').textContent = 'Erro: ' + (e.message || 'Falha ao carregar dados');
    toast('Erro ao carregar: ' + (e.message || '?'), true);
  }
}

$('auth-login')?.addEventListener('click', () => login());
$('auth-open-login')?.addEventListener('click', () => openLoginModal());
$('auth-logout')?.addEventListener('click', () => logout());
$('auth-change-password')?.addEventListener('click', () => openPasswordModal());
$('pwd-save')?.addEventListener('click', () => changePassword());
$('btn-export')?.addEventListener('click', () => openBackupModal());
$('btn-backup')?.addEventListener('click', () => triggerBackupImport());
$('backup-file')?.addEventListener('change', (ev) => onBackupFileChange(ev));
$('backup-export-systems')?.addEventListener('click', () => exportCsv('systems'));
$('backup-export-vms')?.addEventListener('click', () => exportCsv('vms'));
$('backup-export-dbs')?.addEventListener('click', () => exportCsv('databases'));
$('backup-export-json')?.addEventListener('click', () => exportBackup());
$('backup-import-btn')?.addEventListener('click', () => triggerBackupImport());

boot();
