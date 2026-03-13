const App = {
  items: [],
  vms: [],
  databases: [],
  tickets: [],
  dnsNatCache: {},
  dnsNatLoading: false,
  dnsSslCache: {},
  dnsSslLoading: false,
  dnsWafCache: {},
  dnsWafLoading: false,
  vmCsvPreview: null,
  ticketGroupsExpanded: {},
  archived: { systems: [], vms: [] },
  view: 'lista',
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
const safeHrefFromUrl = (rawUrl) => {
  const raw = String(rawUrl ?? '').trim();
  if (!raw) return '';
  const hasScheme = /^[a-zA-Z][a-zA-Z\d+\-.]*:/.test(raw);
  const candidate = hasScheme ? raw : `https://${raw}`;
  try {
    const parsed = new URL(candidate);
    const protocol = String(parsed.protocol || '').toLowerCase();
    if (!['http:', 'https:'].includes(protocol)) return '';
    if (!String(parsed.hostname || '').trim()) return '';
    return parsed.href;
  } catch {
    return '';
  }
};
const linkHtml = (url) => {
  const v = String(url ?? '').trim();
  if (!v) return '-';
  const href = safeHrefFromUrl(v);
  const safe = esc(v);
  if (!href) return safe;
  return `<a href="${esc(href)}" target="_blank" rel="noreferrer" onclick="event.stopPropagation()">${safe}</a>`;
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
const parseVmIpList = (raw) => {
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
const joinVmIpList = (raw) => parseVmIpList(raw).join(', ');
const dnsHostFromUrl = (rawUrl) => {
  const raw = String(rawUrl ?? '').trim();
  if (!raw || raw === '-') return '';
  const hasScheme = /^[a-zA-Z][a-zA-Z\d+\-.]*:/.test(raw);
  const candidate = hasScheme ? raw : `https://${raw}`;
  try {
    const parsed = new URL(candidate);
    const host = String(parsed.hostname || '').trim().toLowerCase();
    if (!host || /[^a-z0-9.-]/i.test(host)) return '';
    return host;
  } catch {
    return '';
  }
};
const dnsSslTargetKeyFromUrl = (rawUrl) => {
  const raw = String(rawUrl ?? '').trim();
  if (!raw || raw === '-') return '';
  const hasScheme = /^[a-zA-Z][a-zA-Z\d+\-.]*:/.test(raw);
  const candidate = hasScheme ? raw : `https://${raw}`;
  try {
    const parsed = new URL(candidate);
    const protocol = String(parsed.protocol || '').toLowerCase();
    if (protocol !== 'https:') return '';
    const host = String(parsed.hostname || '').trim().toLowerCase();
    if (!host || /[^a-z0-9.-]/i.test(host)) return '';
    const port = Number(parsed.port || 443);
    if (!Number.isFinite(port) || port < 1 || port > 65535) return '';
    return `${host}:${port}`;
  } catch {
    return '';
  }
};
const dnsSslFallbackText = (rawUrl) => {
  const raw = String(rawUrl ?? '').trim();
  if (!raw || raw === '-') return '-';
  if (/^http:\/\//i.test(raw)) return 'Sem SSL (HTTP)';
  return '-';
};
const dnsDomainWithoutFirstLabel = (host) => {
  const value = String(host || '').trim().toLowerCase();
  if (!value) return '';
  const parts = value.split('.').filter(Boolean);
  if (parts.length >= 3) return parts.slice(1).join('.');
  return value;
};
const setSelectOptions = (id, firstLabel, values) => {
  const el = $(id);
  if (!el) return;
  const prev = String(el.value || '');
  const normalized = [...new Set((values || []).map((x) => String(x || '').trim()).filter(Boolean))].sort((a,b)=>a.localeCompare(b));
  el.innerHTML = `<option value="">${esc(firstLabel)}</option>` + normalized.map((v)=>`<option value="${esc(v)}">${esc(v)}</option>`).join('');
  if (prev && normalized.includes(prev)) el.value = prev;
};
const populateDnsFilterSelects = (rows) => {
  const domainValues = [];
  const urlEnvValues = [];
  const internalIpValues = [];
  const publicIpValues = [];
  const sslValues = [];

  (rows || []).forEach((row) => {
    const host = String(row.host || '').trim().toLowerCase();
    const filteredDomain = dnsDomainWithoutFirstLabel(host);
    if (filteredDomain) domainValues.push(filteredDomain);

    const urlEnv = String(row.urlEnv || '').trim();
    if (urlEnv) urlEnvValues.push(urlEnv);

    const internalIp = String(row.ip || '').trim();
    if (internalIp && internalIp !== '-') internalIpValues.push(internalIp);

    const publicIpRaw = String(row.publicIp || '').trim();
    if (publicIpRaw && publicIpRaw !== '-' && publicIpRaw.toLowerCase() !== 'consultando...') {
      publicIpRaw.split(',').map((x) => x.trim()).filter(Boolean).forEach((ip) => publicIpValues.push(ip));
    }

    const sslValue = String(row.sslValidity || '').trim();
    if (sslValue && sslValue !== '-' && sslValue.toLowerCase() !== 'consultando...') sslValues.push(sslValue);
  });

  setSelectOptions('dnsf-domain', 'Dominio: Todos', domainValues);
  setSelectOptions('dnsf-url-env', 'URL Ambiente: Todas', urlEnvValues);
  setSelectOptions('dnsf-internal-ip', 'IP Interno: Todos', internalIpValues);
  setSelectOptions('dnsf-public-ip', 'IP Publico: Todos', publicIpValues);
  setSelectOptions('dnsf-ssl', 'Validade SSL: Todas', sslValues);
};
const dnsFilterValues = () => ({
  domain: norm($('dnsf-domain')?.value || ''),
  urlEnv: norm($('dnsf-url-env')?.value || ''),
  internalIp: norm($('dnsf-internal-ip')?.value || ''),
  publicIp: norm($('dnsf-public-ip')?.value || ''),
  ssl: norm($('dnsf-ssl')?.value || ''),
});
const dnsRowMatchesFilters = (row, filters) => {
  const domain = norm(row.host || '');
  const urlEnv = norm(row.urlEnv || '');
  const internalIp = norm(row.ip || '');
  const publicIp = norm(row.publicIp || '');
  const ssl = norm(row.sslValidity || '');
  if (filters.domain && !(domain === filters.domain || domain.endsWith(`.${filters.domain}`))) return false;
  if (filters.urlEnv && urlEnv !== filters.urlEnv) return false;
  if (filters.internalIp && internalIp !== filters.internalIp) return false;
  if (filters.publicIp && publicIp !== filters.publicIp) return false;
  if (filters.ssl && ssl !== filters.ssl) return false;
  return true;
};
async function resolveDnsPublicIpsForHosts(hosts){
  const uniqueHosts = [...new Set((hosts || [])
    .map((host) => dnsHostFromUrl(host) || String(host || '').trim().toLowerCase())
    .filter((host) => host && /^[a-z0-9.-]+$/i.test(host)))];
  const pendingHosts = uniqueHosts.filter((host) => typeof App.dnsNatCache[host] === 'undefined');
  if (!pendingHosts.length || App.dnsNatLoading) return;

  App.dnsNatLoading = true;
  try {
    const result = await api(`dns-public-ip-resolve&hosts=${encodeURIComponent(pendingHosts.join(','))}`);
    const payload = (result && result.ok && result.data && typeof result.data === 'object') ? result.data : {};
    pendingHosts.forEach((host) => {
      const value = String(payload[host] ?? '').trim();
      App.dnsNatCache[host] = value || '-';
    });
  } catch {
    pendingHosts.forEach((host) => {
      if (typeof App.dnsNatCache[host] === 'undefined') App.dnsNatCache[host] = '-';
    });
  } finally {
    App.dnsNatLoading = false;
    if (App.view === 'dns') renderDns();
  }
}
async function resolveDnsSslValidityForTargets(targets){
  const uniqueTargets = [...new Set((targets || [])
    .map((target) => String(target || '').trim().toLowerCase())
    .filter((target) => /^[a-z0-9.-]+:[0-9]{1,5}$/.test(target)))];
  const pendingTargets = uniqueTargets.filter((target) => typeof App.dnsSslCache[target] === 'undefined');
  if (!pendingTargets.length || App.dnsSslLoading) return;

  App.dnsSslLoading = true;
  try {
    const result = await api(`dns-ssl-validity-resolve&targets=${encodeURIComponent(pendingTargets.join(','))}`);
    const payload = (result && result.ok && result.data && typeof result.data === 'object') ? result.data : {};
    pendingTargets.forEach((target) => {
      const value = String(payload[target] ?? '').trim();
      App.dnsSslCache[target] = value || '-';
    });
  } catch {
    pendingTargets.forEach((target) => {
      if (typeof App.dnsSslCache[target] === 'undefined') App.dnsSslCache[target] = '-';
    });
  } finally {
    App.dnsSslLoading = false;
    if (App.view === 'dns') renderDns();
  }
}
async function resolveDnsInternalIpsForHosts(hosts){
  const uniqueHosts = [...new Set((hosts || [])
    .map((host) => dnsHostFromUrl(host) || String(host || '').trim().toLowerCase())
    .filter((host) => host && /^[a-z0-9.-]+$/i.test(host)))];
  const pendingHosts = uniqueHosts.filter((host) => typeof App.dnsWafCache[host] === 'undefined');
  if (!pendingHosts.length || App.dnsWafLoading) return;

  App.dnsWafLoading = true;
  try {
    const result = await api(`dns-internal-ip-resolve&hosts=${encodeURIComponent(pendingHosts.join(','))}`);
    const payload = (result && result.ok && result.data && typeof result.data === 'object') ? result.data : {};
    pendingHosts.forEach((host) => {
      const value = String(payload[host] ?? '').trim();
      App.dnsWafCache[host] = value || '-';
    });
  } catch {
    pendingHosts.forEach((host) => {
      if (typeof App.dnsWafCache[host] === 'undefined') App.dnsWafCache[host] = '-';
    });
  } finally {
    App.dnsWafLoading = false;
    if (App.view === 'dns') renderDns();
  }
}
const normalizePortInput = (raw) => {
  const source = Array.isArray(raw)
    ? raw.map((entry) => String(entry ?? '')).join(',')
    : String(raw ?? '');
  const tokens = source
    .replace(/\r/g, '\n')
    .split(/[\n,;\s]+/)
    .map((entry) => entry.trim())
    .filter(Boolean);
  const seen = new Set();
  const ports = [];
  for (const token of tokens) {
    if (!/^\d+$/.test(token)) {
      return { ok: false, value: '', error: `Porta de execucao invalida: ${token}.` };
    }
    const port = Number(token);
    if (port < 1 || port > 65535) {
      return { ok: false, value: '', error: `Porta de execucao fora da faixa: ${token}.` };
    }
    const normalized = String(port);
    if (seen.has(normalized)) continue;
    seen.add(normalized);
    ports.push(normalized);
  }
  return { ok: true, value: ports.join(','), ports };
};
const normalizeSinglePortInput = (raw) => {
  const value = String(raw ?? '').trim();
  if (!value) return { ok: true, value: '' };
  const normalized = normalizePortInput(value);
  if (!normalized.ok) {
    return { ok: false, value: '', error: 'Porta da instancia invalida. Use valor entre 1 e 65535.' };
  }
  if (!Array.isArray(normalized.ports) || normalized.ports.length !== 1) {
    return { ok: false, value: '', error: 'Informe apenas uma porta por instancia.' };
  }
  return { ok: true, value: String(normalized.ports[0] || '').trim() };
};
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
  const vmCsvExportBtn = $('vm-csv-export-btn');
  const vmCsvImportBtn = $('vm-csv-import-btn');
  const authenticated = App.auth.authenticated && App.auth.user;

  if (authenticated) {
    if (authLabel) {
      const login = String(App.auth.user.username || '').trim() || 'usuario';
      authLabel.textContent = login;
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
  if (exportBtn) {
    exportBtn.disabled = !authenticated;
    exportBtn.classList.toggle('hidden', !canEditNow);
  }
  if (backupBtn) {
    backupBtn.disabled = !adminNow;
    backupBtn.classList.toggle('hidden', !adminNow);
  }
  if (backupExportJsonBtn) backupExportJsonBtn.classList.toggle('hidden', !adminNow);
  if (backupImportBtn) backupImportBtn.classList.toggle('hidden', !adminNow);
  if (vmCsvExportBtn) vmCsvExportBtn.disabled = !canEditNow;
  if (vmCsvImportBtn) vmCsvImportBtn.disabled = !canEditNow;
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

function vmCsvActionMeta(action){
  const key = String(action || '').trim().toLowerCase();
  if (key === 'update') return { label: 'Atualizar', cls: 'vm-csv-action-update' };
  if (key === 'create') return { label: 'Criar', cls: 'vm-csv-action-create' };
  if (key === 'error') return { label: 'Erro', cls: 'vm-csv-action-error' };
  return { label: 'Ignorar', cls: 'vm-csv-action-skip' };
}

function csvEscape(value, delimiter=';'){
  const raw = String(value ?? '');
  const needsQuotes = raw.includes(delimiter) || raw.includes('"') || raw.includes('\n') || raw.includes('\r');
  if (!needsQuotes) return raw;
  return `"${raw.replaceAll('"', '""')}"`;
}

function exportDnsCsv(){
  const body = $('dns-body');
  if (!body) return;

  const rowEls = [...body.querySelectorAll('tr')]
    .filter((tr) => tr.querySelectorAll('td').length >= 6);
  if (!rowEls.length) {
    toast('Nenhum registro DNS para exportar.', true);
    return;
  }

  const headers = ['URL', 'IP Interno', 'WAF', 'IP Publico (NAT)', 'Validade SSL', 'Cert. Bundle'];
  const lines = [headers.map((value) => csvEscape(value, ';')).join(';')];

  rowEls.forEach((tr) => {
    const values = [...tr.querySelectorAll('td')]
      .slice(0, 6)
      .map((td) => String(td.textContent || '').replace(/\s+/g, ' ').trim());
    lines.push(values.map((value) => csvEscape(value, ';')).join(';'));
  });

  const content = `\uFEFF${lines.join('\r\n')}\r\n`;
  const filename = `dns_${new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19)}.csv`;
  downloadTextFile(filename, content, 'text/csv;charset=utf-8');
  toast('CSV DNS exportado.');
}

function exportDnsDomainIpCsv(){
  const body = $('dns-body');
  if (!body) return;

  const rowEls = [...body.querySelectorAll('tr')]
    .filter((tr) => tr.querySelectorAll('td').length >= 2);

  const rows = rowEls.map((tr) => {
    const urlCell = tr.querySelector('td:nth-child(1)');
    const linkEl = urlCell?.querySelector('a');
    const rawUrl = String(linkEl?.textContent || urlCell?.textContent || '').replace(/\s+/g, ' ').trim();
    const domain = dnsHostFromUrl(rawUrl);
    const ip = String(tr.querySelector('td:nth-child(2)')?.textContent || '').replace(/\s+/g, ' ').trim();
    return { domain, ip };
  }).filter((row) => row.domain && row.ip && row.ip !== '-');

  if (!rows.length) {
    toast('Nenhum dominio com IP para exportar.', true);
    return;
  }

  const headers = ['dominio', 'ip'];
  const lines = [headers.map((value) => csvEscape(value, ';')).join(';')];
  rows.forEach((row) => {
    lines.push([row.domain, row.ip].map((value) => csvEscape(value, ';')).join(';'));
  });

  const content = `\uFEFF${lines.join('\r\n')}\r\n`;
  const filename = `dns_dominio_ip_${new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19)}.csv`;
  downloadTextFile(filename, content, 'text/csv;charset=utf-8');
  toast('CSV de dominio e IP exportado.');
}

function dbHasHomologData(db){
  return Number(db?.vm_homolog_id || 0) > 0
    || String(db?.vm_homolog_name || '').trim() !== ''
    || String(db?.db_instance_homolog_ip || '').trim() !== '';
}

function dbVmEnvironmentLabel(db, homolog=false){
  const vm = vmById(homolog ? db?.vm_homolog_id : db?.vm_id);
  if (vm) return vmCategoryLabel(vm);
  const vmName = String(homolog ? (db?.vm_homolog_name || '') : (db?.vm_name || '')).trim();
  if (!vmName) return '-';
  return homolog ? 'Homologacao' : 'Producao';
}

function systemHasHomologData(item){
  return systemUrlList(item, true).length > 0
    || vmName(item, true) !== '-'
    || vmIp(item, true) !== '-';
}

function systemEnvironmentAdministration(item, homolog=false){
  const vm = vmById(homolog ? item?.vm_homolog_id : item?.vm_id);
  if (vm) return vmAdministrationLabel(vm);
  const fallback = relationVmText(item, 'administration');
  return fallback && fallback !== '-' ? fallback : '-';
}

function systemEnvironmentLabel(item, homolog=false){
  const vm = vmById(homolog ? item?.vm_homolog_id : item?.vm_id);
  if (vm) return vmCategoryLabel(vm);
  return homolog ? 'Homologacao' : 'Producao';
}

function exportSystemsCsv(environment='producao'){
  const mode = norm(environment).includes('homo') ? 'homologacao' : 'producao';
  const homolog = mode === 'homologacao';
  const list = [...App.items]
    .filter((item) => systemUrlList(item, homolog).length > 0)
    .sort((a,b)=>String(a.name || '').localeCompare(String(b.name || '')));

  if (!list.length) {
    toast(homolog ? 'Nenhum sistema com URL de homologacao para exportar.' : 'Nenhum sistema com URL de producao para exportar.', true);
    return;
  }

  const headers = ['url', 'acesso', 'servidor(maquina)', 'ip', 'administra\u00e7\u00e3o', 'ambiente'];
  const lines = [headers.map((value) => csvEscape(value, ';')).join(';')];

  list.forEach((item) => {
    const urls = systemUrlList(item, homolog);
    const access = systemAccessLabel(item);
    const server = vmName(item, homolog);
    const ip = vmIp(item, homolog);
    const administration = systemEnvironmentAdministration(item, homolog);
    const environmentLabel = systemEnvironmentLabel(item, homolog);
    const urlRows = urls;

    urlRows.forEach((url) => {
      const urlValue = String(url || '').trim() || '-';
      const values = [urlValue, access, server, ip, administration, environmentLabel];
      lines.push(values.map((value) => csvEscape(value, ';')).join(';'));
    });
  });

  const content = `\uFEFF${lines.join('\r\n')}\r\n`;
  const filename = `sistemas_${mode}_${new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19)}.csv`;
  downloadTextFile(filename, content, 'text/csv;charset=utf-8');
  toast(`CSV de sistemas (${mode}) exportado.`);
}

function exportDatabasesCsv(environment='producao'){
  const mode = norm(environment).includes('homo') ? 'homologacao' : 'producao';
  const homolog = mode === 'homologacao';
  const list = [...App.databases]
    .filter((d) => homolog ? dbHasHomologData(d) : true)
    .sort((a,b)=>String(a.db_name || '').localeCompare(String(b.db_name || '')));

  if (!list.length) {
    toast(homolog ? 'Nenhuma base de homologacao para exportar.' : 'Nenhuma base de producao para exportar.', true);
    return;
  }

  const headers = ['base de dados', 'servidor(maquina)', 'ip', 'administra\u00e7\u00e3o', 'ambiente (VM)', 'sgbd', 'usuario', 'porta'];
  const lines = [headers.map((value) => csvEscape(value, ';')).join(';')];

  list.forEach((d) => {
    const vmName = String(homolog ? (d.vm_homolog_name || '') : (d.vm_name || '')).trim() || '-';
    const ip = dbInstanceIp(d, homolog);
    const administration = homolog ? dbHomologAdministration(d) : dbProductionAdministration(d);
    const environmentLabel = dbVmEnvironmentLabel(d, homolog);
    const sgbd = dbEngineVersionLabel(d, homolog);
    const user = String(d.db_user || '').trim() || '-';
    const port = String(dbInstancePort(d, homolog) || '').trim() || '-';
    const values = [String(d.db_name || '-').trim() || '-', vmName, ip, administration, environmentLabel, sgbd, user, port];
    lines.push(values.map((value) => csvEscape(value, ';')).join(';'));
  });

  const content = `\uFEFF${lines.join('\r\n')}\r\n`;
  const filename = `bases_${mode}_${new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19)}.csv`;
  downloadTextFile(filename, content, 'text/csv;charset=utf-8');
  toast(`CSV de bases (${mode}) exportado.`);
}

const VM_CSV_CREATE_OPTIONS = {
  vm_category: ['Producao', 'Homologacao', 'Desenvolvimento'],
  vm_type: ['Sistemas', 'SGBD'],
  vm_administration: ['SEI', 'PRODEB']
};

function vmCsvNormalizedCreateValue(field, rawValue){
  const options = VM_CSV_CREATE_OPTIONS[field] || [];
  const value = String(rawValue || '').trim();
  if (options.includes(value)) return value;
  return options[0] || '';
}

function vmCsvCreateSelectHtml(field, selectedValue, rowNumber){
  const options = VM_CSV_CREATE_OPTIONS[field] || [];
  const normalized = vmCsvNormalizedCreateValue(field, selectedValue);
  return `
    <select data-vm-csv-row="${rowNumber}" data-vm-csv-field="${field}">
      ${options.map((option) => `<option value="${esc(option)}"${option === normalized ? ' selected' : ''}>${esc(option)}</option>`).join('')}
    </select>
  `;
}

function vmCsvCreateConfigCell(item, next){
  if (String(item?.action || '').toLowerCase() !== 'create') return '-';
  const rowNumber = Number(item?.row_number || 0);
  if (!rowNumber) return '-';
  const vmCategory = vmCsvNormalizedCreateValue('vm_category', next?.vm_category || 'Producao');
  const vmType = vmCsvNormalizedCreateValue('vm_type', next?.vm_type || 'Sistemas');
  const vmAdministration = vmCsvNormalizedCreateValue('vm_administration', next?.vm_administration || 'SEI');
  return `
    <div class="vm-csv-create-config">
      <label>Ambiente ${vmCsvCreateSelectHtml('vm_category', vmCategory, rowNumber)}</label>
      <label>Tipo ${vmCsvCreateSelectHtml('vm_type', vmType, rowNumber)}</label>
      <label>Administracao ${vmCsvCreateSelectHtml('vm_administration', vmAdministration, rowNumber)}</label>
    </div>
  `;
}

function collectVmCsvCreateOverrides(){
  const overrides = {};
  document.querySelectorAll('#vm-csv-preview-body select[data-vm-csv-row][data-vm-csv-field]').forEach((selectEl) => {
    const rowNumber = Number(selectEl.getAttribute('data-vm-csv-row') || 0);
    const field = String(selectEl.getAttribute('data-vm-csv-field') || '').trim();
    if (!rowNumber || !field) return;
    if (!overrides[rowNumber]) {
      overrides[rowNumber] = {};
    }
    overrides[rowNumber][field] = vmCsvNormalizedCreateValue(field, selectEl.value);
  });
  return overrides;
}

function vmCsvDetailHtml(item, changeDetails){
  const reason = String(item?.reason || '').trim();
  let lines = [];
  if (reason) {
    const splitLines = reason.split('|').map((part) => String(part || '').trim()).filter(Boolean);
    lines = splitLines.length > 1 ? splitLines : [reason];
  } else if (Array.isArray(changeDetails) && changeDetails.length) {
    lines = changeDetails;
  } else {
    lines = ['-'];
  }
  return `<div class="vm-csv-detail-lines">${lines.map((line) => `<div>${esc(line)}</div>`).join('')}</div>`;
}

function renderVmCsvPreview(data){
  const summaryEl = $('vm-csv-preview-summary');
  const bodyEl = $('vm-csv-preview-body');
  const applyBtn = $('vm-csv-apply-btn');
  if (!summaryEl || !bodyEl || !applyBtn) return;

  const summary = data?.summary || {};
  const rowsTotal = Number(summary.rows_total || 0);
  const updates = Number(summary.update || 0);
  const creates = Number(summary.create || 0);
  const skips = Number(summary.skip || 0);
  const errors = Number(summary.error || 0);

  summaryEl.innerHTML = `
    <div class="vm-csv-preview-stat"><span>Total de linhas</span><strong>${rowsTotal}</strong></div>
    <div class="vm-csv-preview-stat"><span>Atualizar</span><strong>${updates}</strong></div>
    <div class="vm-csv-preview-stat"><span>Criar</span><strong>${creates}</strong></div>
    <div class="vm-csv-preview-stat"><span>Ignorar</span><strong>${skips}</strong></div>
    <div class="vm-csv-preview-stat"><span>Erros</span><strong>${errors}</strong></div>
  `;

  const items = Array.isArray(data?.items) ? data.items : [];
  if (!items.length) {
    bodyEl.innerHTML = '<tr><td colspan="11" style="color:var(--muted)">Nenhuma linha válida encontrada no CSV.</td></tr>';
  } else {
    bodyEl.innerHTML = items.map((item) => {
      const actionMeta = vmCsvActionMeta(item?.action);
      const next = item?.next || {};
      const current = item?.current || {};
      const changedFields = Array.isArray(item?.changed_fields) ? item.changed_fields : [];
      const labels = {
        name: 'Nome',
        ip: 'IP',
        vm_administration: 'Administracao',
        os_name: 'SO',
        vcpus: 'vCPU',
        ram_csv: 'Memoria',
        disk_csv: 'Storage'
      };
      const changeDetails = changedFields.map((field) => {
        const oldValue = String(current?.[field] || '-');
        const newValue = String(next?.[field] || '-');
        return `${labels[field] || field}: ${oldValue} -> ${newValue}`;
      });
      const detailHtml = vmCsvDetailHtml(item, changeDetails);
      const createConfig = vmCsvCreateConfigCell(item, next);
      return `
        <tr>
          <td>${Number(item?.row_number || 0) || '-'}</td>
          <td><span class="vm-csv-action-pill ${actionMeta.cls}">${actionMeta.label}</span></td>
          <td>${createConfig}</td>
          <td>${esc(String(item?.name || '-'))}</td>
          <td>${esc(String(next.ip || '-'))}</td>
          <td>${esc(String(next.vm_administration || '-'))}</td>
          <td>${esc(String(next.os_name || '-'))}</td>
          <td>${esc(String(next.vcpus || '-'))}</td>
          <td>${esc(String(next.ram_csv || '-'))}</td>
          <td>${esc(String(next.disk_csv || '-'))}</td>
          <td>${detailHtml}</td>
        </tr>
      `;
    }).join('');
  }

  const applyRows = Array.isArray(data?.apply_rows) ? data.apply_rows : [];
  applyBtn.disabled = applyRows.length === 0;
}

async function exportMachinesCsv(){
  if (!ensureCanEdit()) return;
  try {
    const result = await api('vm-csv-export');
    if (!result.ok) throw new Error(result.error || 'Falha ao exportar CSV de maquinas');
    const filename = String(result?.data?.filename || `maquinas_${new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19)}.csv`);
    const mime = String(result?.data?.mime || 'text/csv;charset=utf-8');
    const content = String(result?.data?.content || '');
    downloadTextFile(filename, content, mime);
    toast('CSV de maquinas exportado.');
  } catch (error) {
    toast('Erro ao exportar CSV de maquinas: ' + (error.message || '?'), true);
  }
}

function triggerMachinesCsvImport(){
  if (!ensureCanEdit()) return;
  $('vm-csv-file')?.click();
}

async function onMachinesCsvFileChange(ev){
  if (!ensureCanEdit()) return;
  const file = ev?.target?.files?.[0];
  if (!file) return;
  try {
    const text = await file.text();
    if (!String(text || '').trim()) {
      throw new Error('Arquivo CSV vazio.');
    }
    const result = await api('vm-csv-import-preview', {
      filename: String(file.name || ''),
      csv_content: text
    });
    if (!result.ok) throw new Error(result.error || 'Falha ao gerar pré-visualizacao');
    App.vmCsvPreview = result.data || null;
    renderVmCsvPreview(App.vmCsvPreview);
    $('mvmcsvpreview')?.classList.remove('hidden');
  } catch (error) {
    App.vmCsvPreview = null;
    toast('Erro ao ler CSV de maquinas: ' + (error.message || '?'), true);
  } finally {
    if (ev?.target) ev.target.value = '';
  }
}

async function confirmMachinesCsvImport(){
  if (!ensureCanEdit()) return;
  const previewRows = Array.isArray(App.vmCsvPreview?.apply_rows) ? App.vmCsvPreview.apply_rows : [];
  const createOverrides = collectVmCsvCreateOverrides();
  const payloadRows = previewRows.map((row) => {
    if (!row || typeof row !== 'object') return row;
    if (String(row.action || '').toLowerCase() !== 'create') return { ...row };
    const rowNumber = Number(row.row_number || 0);
    const override = rowNumber ? (createOverrides[rowNumber] || null) : null;
    if (!override) return { ...row };
    return {
      ...row,
      vm_category: vmCsvNormalizedCreateValue('vm_category', override.vm_category || row.vm_category),
      vm_type: vmCsvNormalizedCreateValue('vm_type', override.vm_type || row.vm_type),
      vm_access: String(row.vm_access || 'Interno').trim() || 'Interno',
      vm_administration: vmCsvNormalizedCreateValue('vm_administration', override.vm_administration || row.vm_administration)
    };
  });
  if (!payloadRows.length) {
    toast('Nenhuma alteracao para aplicar.', true);
    return;
  }
  if (!confirm('Confirmar atualizacao das maquinas com base na pre-visualizacao?')) return;
  try {
    const result = await api('vm-csv-import-apply', { rows: payloadRows });
    if (!result.ok) throw new Error(result.error || 'Falha ao atualizar maquinas');
    const summary = result?.data?.summary || {};
    const updated = Number(summary.updated || 0);
    const created = Number(summary.created || 0);
    const skipped = Number(summary.skipped || 0);
    await refreshAll();
    App.vmCsvPreview = null;
    closeModal('mvmcsvpreview');
    toast(`Importacao concluida: ${updated} atualizada(s), ${created} criada(s), ${skipped} ignorada(s).`);
  } catch (error) {
    toast('Erro ao aplicar importacao CSV: ' + (error.message || '?'), true);
  }
}

function closeModal(id){
  const el = $(id);
  if (!el) return;
  el.classList.add('hidden');
  if (id === 'mauth' && $('auth-password')) $('auth-password').value = '';
  if (id === 'mpassword') resetPasswordForm();
  if (id === 'mvmcsvpreview') {
    App.vmCsvPreview = null;
    if ($('vm-csv-preview-summary')) $('vm-csv-preview-summary').innerHTML = '';
    if ($('vm-csv-preview-body')) $('vm-csv-preview-body').innerHTML = '';
    if ($('vm-csv-apply-btn')) $('vm-csv-apply-btn').disabled = true;
  }
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
  const raw = String(item?.[key] || item?.[legacyKey] || '').trim();
  if (!raw) return '-';
  const ips = parseVmIpList(raw);
  if (!ips.length) return raw;
  if (ips.length === 1) return ips[0];
  return `${ips[0]} (+${ips.length - 1})`;
}

function vmPublicIp(item, role=false){
  const mode = role === true ? 'homolog' : String(role ?? '').trim().toLowerCase();
  const isDev = mode === 'dev' || mode.includes('desenv');
  const isHomolog = !isDev && (role === true || mode.includes('homo'));
  const key = isDev ? 'vm_dev_public_ip' : (isHomolog ? 'vm_homolog_public_ip' : 'vm_public_ip');
  const legacyKey = isDev ? 'public_ip_dev' : (isHomolog ? 'public_ip_homolog' : 'public_ip');
  const raw = String(item?.[key] || item?.[legacyKey] || '').trim();
  if (!raw) return '-';
  const ips = parseVmIpList(raw);
  if (!ips.length) return raw;
  if (ips.length === 1) return ips[0];
  return `${ips[0]} (+${ips.length - 1})`;
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

function normalizeVmSummaryValue(value){
  if (Array.isArray(value)) {
    const list = [...new Set(value.map((x) => String(x || '').trim()).filter(Boolean))];
    return list.join(', ');
  }
  if (typeof value === 'boolean') return value ? 'Sim' : 'Nao';
  return String(value || '').trim();
}

function relationVmSummary(item, resolver){
  const prod = vmById(item?.vm_id);
  const hml = vmById(item?.vm_homolog_id);
  const dev = vmById(item?.vm_dev_id);
  const prodText = prod ? normalizeVmSummaryValue(resolver(prod)) : '';
  const hmlText = hml ? normalizeVmSummaryValue(resolver(hml)) : '';
  const devText = dev ? normalizeVmSummaryValue(resolver(dev)) : '';
  const unique = [...new Set([prodText, hmlText, devText].filter(Boolean))];
  if (!unique.length) return '-';
  if (unique.length === 1) return unique[0];
  return `Prod: ${prodText || '-'} | Hml: ${hmlText || '-'} | Dev: ${devText || '-'}`;
}

function vmIpList(vm){
  return parseVmIpList(vm?.ip || '');
}

function vmPublicIpList(vm){
  return parseVmIpList(vm?.public_ip || '');
}

function vmPrimaryIp(vm){
  const ips = vmIpList(vm);
  return ips.length ? ips[0] : '';
}

function vmIpSummary(vm){
  const ips = vmIpList(vm);
  if (!ips.length) return String(vm?.ip || '').trim() || '-';
  if (ips.length === 1) return ips[0];
  return `${ips[0]} (+${ips.length - 1})`;
}

function vmPublicIpSummary(vm){
  const ips = vmPublicIpList(vm);
  if (!ips.length) return String(vm?.public_ip || '').trim() || '-';
  if (ips.length === 1) return ips[0];
  return `${ips[0]} (+${ips.length - 1})`;
}

function vmLabel(vm){
  const name = String(vm?.name || '').trim();
  const ip = vmIpSummary(vm);
  if (!name && !ip) return '-';
  if (name && ip && ip !== '-') return `${name} (${ip})`;
  return name || ip;
}

function vmTechList(vm){
  if (Array.isArray(vm?.vm_app_server_list)) return vm.vm_app_server_list;
  const appRaw = String(vm?.vm_app_server || '').trim();
  if (appRaw) return appRaw.split(',').map((x)=>x.trim()).filter(Boolean);

  // Compatibilidade com payloads antigos.
  if (Array.isArray(vm?.vm_tech_list)) return vm.vm_tech_list;
  const raw = String(vm?.vm_tech || '').trim();
  return raw ? raw.split(',').map((x)=>x.trim()).filter(Boolean) : [];
}

function vmWebServerList(vm){
  if (Array.isArray(vm?.vm_web_server_list)) return vm.vm_web_server_list;
  const raw = String(vm?.vm_web_server || '').trim();
  return raw ? raw.split(',').map((x)=>x.trim()).filter(Boolean) : [];
}

function vmContainerToolList(vm){
  if (Array.isArray(vm?.vm_container_tool_list)) return vm.vm_container_tool_list;
  const raw = String(vm?.vm_container_tool || '').trim();
  return raw ? raw.split(',').map((x)=>x.trim()).filter(Boolean) : [];
}

function vmContainerizationEnabled(vm){
  const value = vm?.vm_containerization;
  if (typeof value === 'boolean') return value;
  const normalized = String(value ?? '').trim().toLowerCase();
  return normalized === '1' || normalized === 'true' || normalized === 'sim' || normalized === 'yes';
}

function vmTargetVersionText(vm){
  return String(vm?.vm_target_version || '').trim();
}

function vmRuntimePortText(vm){
  return String(vm?.vm_runtime_port || '').trim();
}

function vmRuntimePortList(vm){
  const normalized = normalizePortInput(vmRuntimePortText(vm));
  return normalized.ok ? normalized.ports : [];
}

function parseVmServiceItem(value){
  const text = String(value || '').trim();
  if (!text) return { name: '', port: '' };
  const match = text.match(/^(.*?):\s*([0-9]{1,5})$/);
  if (!match) return { name: text, port: '' };
  const name = String(match[1] || '').trim();
  const normalizedPort = normalizeSinglePortInput(String(match[2] || '').trim());
  if (!name || !normalizedPort.ok) return { name: text, port: '' };
  return { name, port: normalizedPort.value };
}

function vmServiceItemValue(name, port=''){
  const cleanName = String(name || '').trim();
  const normalizedPort = normalizeSinglePortInput(String(port || '').trim());
  if (!cleanName) return '';
  if (!normalizedPort.ok || !normalizedPort.value) return cleanName;
  return `${cleanName}:${normalizedPort.value}`;
}

function splitServiceValues(raw){
  return String(raw ?? '')
    .replace(/\r/g, '\n')
    .split(/[\n,;]+/)
    .map((entry) => String(entry || '').trim())
    .filter(Boolean);
}

function systemServiceEntries(raw){
  const out = [];
  const seen = new Set();
  splitServiceValues(raw).forEach((entry) => {
    const parsed = parseVmServiceItem(entry);
    const name = String(parsed?.name || '').trim();
    const port = String(parsed?.port || '').trim();
    if (!name) return;
    const key = `${name.toLowerCase()}|${port}`;
    if (seen.has(key)) return;
    seen.add(key);
    out.push({ name, port });
  });
  return out;
}

function systemServiceNamesText(raw){
  const names = [...new Set(systemServiceEntries(raw).map((entry) => entry.name).filter(Boolean))];
  return names.length ? names.join(', ') : '-';
}

function systemServicePortsText(raw){
  const out = [];
  const seen = new Set();
  systemServiceEntries(raw).forEach((entry) => {
    const port = String(entry?.port || '').trim();
    if (!port || seen.has(port)) return;
    seen.add(port);
    out.push(port);
  });
  return out.length ? out.join(', ') : '-';
}

function vmDeploymentTags(vm){
  const tags = [];
  const appServers = vmTechList(vm);
  const webServers = vmWebServerList(vm);
  const containerTools = vmContainerToolList(vm);
  const containerized = vmContainerizationEnabled(vm) || containerTools.length > 0;
  const runtimePort = vmRuntimePortText(vm);
  const runtimePorts = vmRuntimePortList(vm);
  const targetVersion = vmTargetVersionText(vm);

  appServers.forEach((item) => {
    const parsed = parseVmServiceItem(item);
    const label = parsed.name || String(item || '').trim();
    if (!label) return;
    tags.push(parsed.port ? `App: ${label} (${parsed.port})` : `App: ${label}`);
  });
  webServers.forEach((item) => {
    const parsed = parseVmServiceItem(item);
    const label = parsed.name || String(item || '').trim();
    if (!label) return;
    tags.push(parsed.port ? `Web: ${label} (${parsed.port})` : `Web: ${label}`);
  });
  if (containerized) {
    if (containerTools.length) containerTools.forEach((item) => tags.push(`Container: ${item}`));
    else tags.push('Containerizado');
  }
  if (runtimePorts.length) runtimePorts.forEach((item) => tags.push(`Porta: ${item}`));
  else if (runtimePort) tags.push(`Porta: ${runtimePort}`);
  if (targetVersion) tags.push(`Alvo: ${targetVersion}`);

  return tags;
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

function systemPhpCompatibility(item){
  const fallback = { has_requirements: false, status: 'not_applicable', label: 'N/A', issues: 0, environments: [] };
  if (!item || typeof item !== 'object') return fallback;
  const payload = item.php_compatibility;
  if (!payload || typeof payload !== 'object') return fallback;
  return {
    has_requirements: Boolean(payload.has_requirements),
    status: String(payload.status || 'not_applicable'),
    label: String(payload.label || 'N/A'),
    issues: Number(payload.issues || 0),
    environments: Array.isArray(payload.environments) ? payload.environments : []
  };
}

function systemHasPhpRequirements(item){
  const compat = systemPhpCompatibility(item);
  if (compat.has_requirements) return true;
  const req = item?.php_requirements;
  return Boolean(req && typeof req === 'object' && req.has_requirements);
}

function systemPhpCompatTag(item){
  const compat = systemPhpCompatibility(item);
  if (!systemHasPhpRequirements(item)) return { status: 'not_applicable', label: 'N/A', issues: 0 };
  const status = String(compat.status || 'warning');
  const label = String(compat.label || 'Parcial');
  const issues = Number(compat.issues || 0);
  return { status, label, issues };
}

function systemRCompatibility(item){
  const fallback = { has_requirements: false, status: 'not_applicable', label: 'N/A', issues: 0, environments: [] };
  if (!item || typeof item !== 'object') return fallback;
  const payload = item.r_compatibility;
  if (!payload || typeof payload !== 'object') return fallback;
  return {
    has_requirements: Boolean(payload.has_requirements),
    status: String(payload.status || 'not_applicable'),
    label: String(payload.label || 'N/A'),
    issues: Number(payload.issues || 0),
    environments: Array.isArray(payload.environments) ? payload.environments : []
  };
}

function systemHasRRequirements(item){
  const compat = systemRCompatibility(item);
  if (compat.has_requirements) return true;
  const req = item?.r_requirements;
  return Boolean(req && typeof req === 'object' && req.has_requirements);
}

function systemRCompatTag(item){
  const compat = systemRCompatibility(item);
  if (!systemHasRRequirements(item)) return { status: 'not_applicable', label: 'N/A', issues: 0 };
  const status = String(compat.status || 'warning');
  const label = String(compat.label || 'Parcial');
  const issues = Number(compat.issues || 0);
  return { status, label, issues };
}

function systemLanguageKeys(item){
  const out = [];
  const seen = new Set();
  const techList = Array.isArray(item?.tech) ? item.tech : [];

  techList.forEach((entry) => {
    const value = String(entry || '').trim().toLowerCase();
    if (!value) return;

    if (value.includes('php')) {
      if (!seen.has('php')) {
        seen.add('php');
        out.push('php');
      }
      return;
    }

    if (value === 'r' || /^r(?:[\s\-\/_.]|\d)/.test(value)) {
      if (!seen.has('r')) {
        seen.add('r');
        out.push('r');
      }
    }
  });

  if (!seen.has('php') && systemHasPhpRequirements(item)) {
    seen.add('php');
    out.push('php');
  }
  if (!seen.has('r') && systemHasRRequirements(item)) {
    seen.add('r');
    out.push('r');
  }

  return out;
}

function systemPrimaryCompatibility(item){
  const languages = systemLanguageKeys(item);
  if (!languages.length) return { language: '', status: 'not_applicable', label: 'N/A', issues: 0 };

  const entries = languages.map((language) => {
    const compat = language === 'r' ? systemRCompatTag(item) : systemPhpCompatTag(item);
    return { language, status: compat.status, label: compat.label, issues: compat.issues };
  });

  return entries.find((entry) => entry.status !== 'not_applicable') || entries[0];
}

function systemCompatibilityDisplayLabel(item){
  const compat = systemPrimaryCompatibility(item);
  if (!compat.language || compat.status === 'not_applicable') return 'N/A';
  const suffix = compat.issues > 0 ? ` (${compat.issues})` : '';
  return `${compat.label}${suffix}`;
}

function systemCompatibilityMarkup(item, options = {}){
  const withAction = Boolean(options?.withAction);
  const compat = systemPrimaryCompatibility(item);
  if (!compat.language || compat.status === 'not_applicable') return '<span class="compat-empty">N/A</span>';

  const suffix = compat.issues > 0 ? ` (${compat.issues})` : '';
  const pill = `<span class="compat-pill compat-${esc(compat.status)}">${esc(compat.label)}${esc(suffix)}</span>`;
  if (!withAction) return pill;

  const systemId = Number(item?.id || 0);
  const openFn = compat.language === 'r'
    ? `openSystemRCompatibilityPageById(${systemId})`
    : `openSystemPhpCompatibilityPageById(${systemId})`;
  const title = compat.language === 'r'
    ? 'Abrir validacao de compatibilidade R'
    : 'Abrir validacao de compatibilidade PHP';

  return `
    <div class="compat-cell">
      <button class="compat-icon compat-${esc(compat.status)}" onclick="event.stopPropagation();${openFn}" title="${title}">&#9432;</button>
      ${pill}
    </div>
  `;
}

function vmInstances(vm){
  if (Array.isArray(vm?.vm_instances_list)) {
    return vm.vm_instances_list
      .map((inst) => ({
        name: String(inst?.name || '').trim(),
        ip: String(inst?.ip || '').trim(),
        port: String(inst?.port || '').trim()
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
        ip: String(inst?.ip || '').trim(),
        port: String(inst?.port || '').trim()
      }))
      .filter((inst) => inst.ip !== '');
  } catch {
    return [];
  }
}

function vmInstancesText(vm){
  return vmInstances(vm).map((inst) => {
    const endpoint = inst.port ? `${inst.ip}:${inst.port}` : inst.ip;
    return `${inst.name || 'Instancia'} - ${endpoint}`;
  }).join('\n');
}

function vmInstalledInstanceNames(vm){
  const out = [];
  const seen = new Set();
  vmInstances(vm).forEach((inst) => {
    const name = String(inst?.name || '').trim() || 'Instancia principal';
    const key = name.toLowerCase();
    if (!key || seen.has(key)) return;
    seen.add(key);
    out.push(name);
  });
  return out;
}

function vmUsedPorts(vm){
  const out = [];
  const seen = new Set();

  vmInstances(vm).forEach((inst) => {
    const port = String(inst?.port || '').trim();
    if (!port || seen.has(port)) return;
    seen.add(port);
    out.push(port);
  });

  if (out.length) return out;

  vmRuntimePortList(vm).forEach((port) => {
    const value = String(port || '').trim();
    if (!value || seen.has(value)) return;
    seen.add(value);
    out.push(value);
  });

  return out;
}

function vmSgbdInstancePortPairs(vm){
  const out = [];
  const seen = new Set();
  const instances = vmInstances(vm);

  instances.forEach((inst, idx) => {
    const name = String(inst?.name || '').trim() || `Instancia ${idx + 1}`;
    const port = String(inst?.port || '').trim() || '-';
    const pair = `${name}: ${port}`;
    const key = pair.toLowerCase();
    if (seen.has(key)) return;
    seen.add(key);
    out.push(pair);
  });

  if (out.length) return out;

  const ports = vmUsedPorts(vm);
  if (!ports.length) return [];
  return ports.map((port) => `Instancia principal: ${port}`);
}

function vmSgbdInstancePortTags(vm){
  const out = [];
  const seen = new Set();

  vmSgbdInstancePortPairs(vm).forEach((pair) => {
    const text = String(pair || '').trim();
    if (!text) return;
    const [rawName, ...rawPortParts] = text.split(':');
    const name = String(rawName || '').trim();
    const port = String(rawPortParts.join(':') || '').trim();

    [name, port ? `Porta: ${port}` : ''].forEach((item) => {
      const value = String(item || '').trim();
      if (!value) return;
      const key = value.toLowerCase();
      if (seen.has(key)) return;
      seen.add(key);
      out.push(value);
    });
  });

  return out;
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
    const m = line.match(/^(.*?)\s*[-:]\s*([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)(?::([0-9]{1,5}))?$/) || line.match(/^(.*?)\s+([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)(?::([0-9]{1,5}))?$/);
    const name = String(m?.[1] || `Instancia ${idx + 1}`).trim();
    const ip = String(m?.[2] || '').trim();
    const portNorm = normalizeSinglePortInput(String(m?.[3] || '').trim());
    if (!ip) return;
    if (!portNorm.ok) return;
    const key = `${name.toLowerCase()}|${ip.toLowerCase()}|${portNorm.value}`;
    if (seen.has(key)) return;
    seen.add(key);
    out.push({ name, ip, port: portNorm.value });
  });
  return out;
}

function vmFormIpList(){
  return parseVmIpList($('fvmip')?.value || '');
}

function vmServiceRowsSelector(rowsId){
  return `#${rowsId} .vm-service-row`;
}

function vmServiceRowsDefault(rowsId){
  if (rowsId === 'fvmapp_rows') addVmAppServerRow();
  else if (rowsId === 'fvmweb_rows') addVmWebServerRow();
}

function addVmServiceRow(rowsId, service=null, namePlaceholder='Tecnologia'){
  const rowsEl = $(rowsId);
  if (!rowsEl) return;
  const name = String(service?.name || '').trim();
  const port = String(service?.port || '').trim();
  const row = document.createElement('div');
  row.className = 'vm-service-row';
  row.innerHTML = `
    <input class="vm-service-name" placeholder="${esc(namePlaceholder)}" value="${esc(name)}">
    <input class="vm-service-port" placeholder="Porta (Ex: 3306)" inputmode="numeric" value="${esc(port)}">
    <button type="button" class="btn" onclick="removeVmServiceRow(this, '${rowsId}')">Remover</button>
  `;
  rowsEl.appendChild(row);
}

function addVmAppServerRow(service=null){
  addVmServiceRow('fvmapp_rows', service, 'Servidor da aplicacao (Ex: PHP-FPM)');
}

function addVmWebServerRow(service=null){
  addVmServiceRow('fvmweb_rows', service, 'Web Server (Ex: Nginx)');
}

function removeVmServiceRow(trigger, rowsId){
  const row = trigger?.closest?.('.vm-service-row');
  if (!row) return;
  const rowsEl = $(rowsId);
  row.remove();
  if (rowsEl && !rowsEl.querySelector('.vm-service-row')) vmServiceRowsDefault(rowsId);
}

function setVmServiceRows(rowsId, services=[], addRowFn){
  const rowsEl = $(rowsId);
  if (!rowsEl) return;
  rowsEl.innerHTML = '';
  if (Array.isArray(services) && services.length) {
    services.forEach((item) => addRowFn(item));
    return;
  }
  addRowFn();
}

function vmServiceRowsFromList(list){
  if (!Array.isArray(list)) return [];
  return list
    .map((item) => parseVmServiceItem(item))
    .map((item) => ({ name: String(item?.name || '').trim(), port: String(item?.port || '').trim() }))
    .filter((item) => item.name !== '');
}

function setVmAppServerRows(services=[]){
  setVmServiceRows('fvmapp_rows', services, addVmAppServerRow);
}

function setVmWebServerRows(services=[]){
  setVmServiceRows('fvmweb_rows', services, addVmWebServerRow);
}

function readVmServiceRows(rowsId){
  const rows = [...document.querySelectorAll(vmServiceRowsSelector(rowsId))];
  const out = [];
  const seen = new Set();
  let invalidName = false;
  let invalidPort = '';

  rows.forEach((row) => {
    const nameInput = row.querySelector('.vm-service-name');
    const portInput = row.querySelector('.vm-service-port');
    const rawName = String(nameInput?.value || '').trim();
    const rawPort = String(portInput?.value || '').trim();
    if (!rawName && !rawPort) return;
    if (!rawName) {
      invalidName = true;
      return;
    }
    const normalizedPort = normalizeSinglePortInput(rawPort);
    if (!normalizedPort.ok) {
      invalidPort = normalizedPort.error || 'Porta invalida.';
      return;
    }
    const key = `${rawName.toLowerCase()}|${normalizedPort.value}`;
    if (seen.has(key)) return;
    seen.add(key);
    out.push({ name: rawName, port: normalizedPort.value });
  });

  return { items: out, invalidName, invalidPort };
}

function vmInstanceIpOptionsHtml(selectedIp=''){
  const selected = String(selectedIp || '').trim();
  const ips = vmFormIpList();
  if (!ips.length) {
    const fallback = selected
      ? `<option value="${esc(selected)}" selected>${esc(selected)} (fora da lista)</option>`
      : '<option value="">Cadastre IP da maquina...</option>';
    return fallback;
  }
  const hasSelected = selected !== '' && ips.some((ip) => ip === selected);
  let html = '<option value="">Selecionar IP...</option>';
  html += ips.map((ip) => `<option value="${esc(ip)}"${ip === selected ? ' selected' : ''}>${esc(ip)}</option>`).join('');
  if (selected && !hasSelected) {
    html += `<option value="${esc(selected)}" selected>${esc(selected)} (fora da lista)</option>`;
  }
  return html;
}

function addVmInstanceRow(instance=null){
  const rowsEl = $('fvminstances_rows');
  if (!rowsEl) return;
  const name = String(instance?.name || '').trim();
  const ip = String(instance?.ip || '').trim();
  const port = String(instance?.port || '').trim();
  const row = document.createElement('div');
  row.className = 'vm-instance-row';
  row.innerHTML = `
    <input class="vm-instance-name" placeholder="Tecnologia (Ex: MySQL)" value="${esc(name)}">
    <select class="vm-instance-ip">${vmInstanceIpOptionsHtml(ip)}</select>
    <input class="vm-instance-port" placeholder="Porta (Ex: 3306)" inputmode="numeric" value="${esc(port)}">
    <button type="button" class="btn" onclick="removeVmInstanceRow(this)">Remover</button>
  `;
  rowsEl.appendChild(row);
}

function removeVmInstanceRow(trigger){
  const row = trigger?.closest?.('.vm-instance-row');
  if (!row) return;
  const rowsEl = $('fvminstances_rows');
  row.remove();
  if (rowsEl && !rowsEl.querySelector('.vm-instance-row')) addVmInstanceRow();
}

function syncVmInstanceIpOptions(){
  const rowsEl = $('fvminstances_rows');
  if (!rowsEl) return;
  rowsEl.querySelectorAll('.vm-instance-ip').forEach((selectEl) => {
    const current = String(selectEl.value || '').trim();
    selectEl.innerHTML = vmInstanceIpOptionsHtml(current);
    if (current && [...selectEl.options].some((opt) => opt.value === current)) {
      selectEl.value = current;
    }
  });
}

function setVmInstanceRows(instances=[]){
  const rowsEl = $('fvminstances_rows');
  if (!rowsEl) return;
  rowsEl.innerHTML = '';
  if (Array.isArray(instances) && instances.length) {
    instances.forEach((inst) => addVmInstanceRow(inst));
    return;
  }
  addVmInstanceRow();
}

function readVmInstanceRows(){
  const rows = [...document.querySelectorAll('#fvminstances_rows .vm-instance-row')];
  const out = [];
  const seen = new Set();
  let invalidRow = false;
  let invalidPort = '';
  rows.forEach((row) => {
    const nameInput = row.querySelector('.vm-instance-name');
    const ipSelect = row.querySelector('.vm-instance-ip');
    const portInput = row.querySelector('.vm-instance-port');
    const rawName = String(nameInput?.value || '').trim();
    const rawIp = String(ipSelect?.value || '').trim();
    const rawPort = String(portInput?.value || '').trim();
    if (!rawName && !rawIp && !rawPort) return;
    if (!rawIp) {
      invalidRow = true;
      return;
    }
    const normalizedPort = normalizeSinglePortInput(rawPort);
    if (!normalizedPort.ok) {
      invalidPort = normalizedPort.error || 'Porta da instancia invalida.';
      return;
    }
    const name = rawName || `Instancia ${out.length + 1}`;
    const key = `${name.toLowerCase()}|${rawIp.toLowerCase()}|${normalizedPort.value}`;
    if (seen.has(key)) return;
    seen.add(key);
    out.push({ name, ip: rawIp, port: normalizedPort.value });
  });
  return { instances: out, invalidRow, invalidPort };
}

function encodeInstanceOption(instance){
  const name = encodeURIComponent(String(instance?.name || '').trim());
  const ip = encodeURIComponent(String(instance?.ip || '').trim());
  const port = encodeURIComponent(String(instance?.port || '').trim());
  return `${name}|||${ip}|||${port}`;
}

function decodeInstanceOption(value){
  const raw = String(value || '');
  if (!raw.includes('|||')) return { name: '', ip: '', port: '' };
  const [nameEnc, ipEnc, portEnc] = raw.split('|||');
  return {
    name: decodeURIComponent(nameEnc || ''),
    ip: decodeURIComponent(ipEnc || ''),
    port: decodeURIComponent(portEnc || '')
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

function dbInstancePort(db, homolog=false){
  const key = homolog ? 'db_instance_homolog_port' : 'db_instance_port';
  return String(db?.[key] || '').trim();
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
  const port = dbInstancePort(db, homolog);
  const endpoint = ip !== '-' ? (port ? `${ip}:${port}` : ip) : '-';
  if (label === '-' && ip === '-') return '-';
  if (endpoint === '-') return label;
  if (label === '-') return endpoint;
  return `${label} (${endpoint})`;
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

function systemAccessLabel(item){
  const raw = String(item?.system_access || '').trim().toLowerCase();
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
  if (App.view === 'chamados') {
    saveTicket();
    return;
  }
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

  if (App.view === 'chamados') {
    btn.textContent = '+ Registrar Chamado';
    return;
  }

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
  ['dashboard','lista','cards','dns','bases','chamados','maquinas','vm-relatorio','arquivados'].forEach((v) => {
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

function setDataListOptions(listId, values){
  const el = $(listId);
  if (!el) return;
  const normalized = [...new Set((values || []).map((x) => String(x || '').trim()).filter(Boolean))].sort((a,b)=>a.localeCompare(b));
  el.innerHTML = normalized.map((value) => `<option value="${esc(value)}"></option>`).join('');
}

function selectedSystemVms(){
  const ids = [
    Number($('fvm_id')?.value || 0),
    Number($('fvm_homolog_id')?.value || 0),
    Number($('fvm_dev_id')?.value || 0),
  ].filter((id) => id > 0);
  const seen = new Set();
  const out = [];
  ids.forEach((id) => {
    if (seen.has(id)) return;
    seen.add(id);
    const vm = vmById(id);
    if (vm) out.push(vm);
  });
  return out;
}

function syncSystemContainerFields(){
  const enabled = String($('fcontainerization')?.value || '0') === '1';
  const input = $('fcontainer_tool');
  if (!input) return;
  input.disabled = !enabled;
  if (!enabled) input.value = '';
}

function syncSystemTechFromVms(){
  const vms = selectedSystemVms();
  const languages = [];
  const appServers = [];
  const webServers = [];
  const containerTools = [];

  vms.forEach((vm) => {
    vmLanguageList(vm).forEach((item) => languages.push(item));
    vmTechList(vm).forEach((item) => appServers.push(item));
    vmWebServerList(vm).forEach((item) => webServers.push(item));
    vmContainerToolList(vm).forEach((item) => containerTools.push(item));
  });

  setDataListOptions('ftech-options', languages);
  setDataListOptions('fapp-server-options', appServers);
  setDataListOptions('fweb-server-options', webServers);
  setDataListOptions('fcontainer-tool-options', containerTools);
}

function populateVmTabFilters(){
  const fill = (id, first, list) => {
    const el = $(id);
    if (!el) return;
    const prev = el.value;
    el.innerHTML = `<option value="">${first}</option>` + list.map((x)=>`<option>${esc(x)}</option>`).join('');
    if (prev && list.includes(prev)) el.value = prev;
  };
  const vmOsList = [...new Set(
    App.vms
      .map((vm) => String(vm?.os_name || '').trim())
      .filter(Boolean)
  )].sort((a,b)=>a.localeCompare(b));
  fill('vmcatf', 'Ambiente: Todos', ['Producao','Homologacao','Desenvolvimento']);
  fill('vmtypef', 'Tipo: Todos', ['Sistemas','SGBD']);
  fill('vmosf', 'SO: Todos', vmOsList);
  fill('vmadminf', 'Administracao: Todos', ['SEI','PRODEB']);
  fill('vmrcatf', 'Ambiente: Todos', ['Producao','Homologacao','Desenvolvimento']);
  fill('vmrtypef', 'Tipo: Todos', ['Sistemas','SGBD']);
  fill('vmrosf', 'SO: Todos', vmOsList);
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

function populateTicketSelects(){
  const systemEl = $('fcall_system_id');
  if (systemEl) {
    const prev = systemEl.value;
    const systems = [...App.items].sort((a,b)=>String(a.name || '').localeCompare(String(b.name || '')));
    systemEl.innerHTML = '<option value="">Selecionar sistema...</option>' + systems.map((item)=>`<option value="${item.id}">${esc(item.name || '-')}</option>`).join('');
    if (prev && systems.some((item)=>String(item.id) === String(prev))) systemEl.value = prev;
  }

  const vmEl = $('fcall_vm_id');
  if (vmEl) {
    const prev = vmEl.value;
    const vms = [...App.vms].sort((a,b)=>String(a.name || '').localeCompare(String(b.name || '')));
    vmEl.innerHTML = '<option value="">Selecionar maquina...</option>' + vms.map((vm)=>`<option value="${vm.id}">${esc(vmLabel(vm))}</option>`).join('');
    if (prev && vms.some((vm)=>String(vm.id) === String(prev))) vmEl.value = prev;
  }
}

function syncCallTargetFields(){
  const target = String($('fcall_target_type')?.value || 'system').trim().toLowerCase();
  const systemField = $('call-system-field');
  const vmField = $('call-vm-field');
  const systemEl = $('fcall_system_id');
  const vmEl = $('fcall_vm_id');
  const useVm = target === 'vm';
  systemField?.classList.toggle('hidden', useVm);
  vmField?.classList.toggle('hidden', !useVm);
  if (systemEl) systemEl.disabled = useVm;
  if (vmEl) vmEl.disabled = !useVm;
}

function resetTicketForm(){
  if ($('fcall_id')) $('fcall_id').value = '';
  if ($('fcall_target_type')) $('fcall_target_type').value = 'system';
  if ($('fcall_system_id')) $('fcall_system_id').value = '';
  if ($('fcall_vm_id')) $('fcall_vm_id').value = '';
  if ($('fcall_number')) $('fcall_number').value = '';
  if ($('fcall_description')) $('fcall_description').value = '';
  syncCallTargetFields();
  if ($('bcall-save')) $('bcall-save').textContent = 'Registrar Chamado';
  $('bcall-cancel')?.classList.add('hidden');
}

function editTicketById(id){
  if (!ensureCanEdit()) return;
  const ticketId = Number(id || 0);
  if (ticketId <= 0) return;
  const ticket = App.tickets.find((item) => Number(item.id) === ticketId);
  if (!ticket) {
    toast('Chamado nao encontrado.', true);
    return;
  }

  populateTicketSelects();
  if ($('fcall_id')) $('fcall_id').value = String(ticket.id || '');
  if ($('fcall_target_type')) $('fcall_target_type').value = String(ticket.target_type || 'system') === 'vm' ? 'vm' : 'system';
  syncCallTargetFields();
  if ($('fcall_system_id')) $('fcall_system_id').value = String(ticket.system_id || '');
  if ($('fcall_vm_id')) $('fcall_vm_id').value = String(ticket.vm_id || '');
  if ($('fcall_number')) $('fcall_number').value = String(ticket.ticket_number || '');
  if ($('fcall_description')) $('fcall_description').value = String(ticket.description || '');
  if ($('bcall-save')) $('bcall-save').textContent = 'Salvar Edicao';
  $('bcall-cancel')?.classList.remove('hidden');
  $('fcall_number')?.focus();
}

async function deleteTicketById(id){
  if (!ensureCanEdit()) return;
  const ticketId = Number(id || 0);
  if (ticketId <= 0) return;
  if (!confirm('Excluir este chamado?')) return;
  try {
    const result = await api('ticket-delete', { id: ticketId });
    if (!result.ok) throw new Error(result.error || 'Erro ao excluir chamado');
    App.tickets = App.tickets.filter((item) => Number(item.id) !== ticketId);
    if (Number($('fcall_id')?.value || 0) === ticketId) resetTicketForm();
    renderCalls();
    toast('Chamado excluido com sucesso.');
  } catch (e) {
    toast('Erro ao excluir chamado: ' + (e.message || '?'), true);
  }
}

function ticketGroupStateKey(scope, groupKey){
  return `${String(scope || '').trim()}::${String(groupKey || '').trim()}`;
}

function isTicketGroupExpanded(scope, groupKey){
  const key = ticketGroupStateKey(scope, groupKey);
  if (!key) return false;
  if (!(key in App.ticketGroupsExpanded)) return false;
  return Boolean(App.ticketGroupsExpanded[key]);
}

function toggleTicketGroupFromRow(row){
  const scope = String(row?.dataset?.groupScope || '').trim();
  const groupKey = String(row?.dataset?.groupKey || '').trim();
  if (!scope || !groupKey) return;
  const key = ticketGroupStateKey(scope, groupKey);
  App.ticketGroupsExpanded[key] = !isTicketGroupExpanded(scope, groupKey);
  renderCalls();
}

function groupedTickets(tickets, scope){
  const groups = new Map();
  tickets.forEach((ticket) => {
    const targetId = scope === 'vm'
      ? Number(ticket.vm_id || 0)
      : Number(ticket.system_id || 0);
    const targetName = String(ticket.target_name || '-').trim() || '-';
    const key = targetId > 0 ? `id:${targetId}` : `name:${targetName.toLowerCase()}`;
    if (!groups.has(key)) {
      groups.set(key, {
        key,
        targetId,
        targetName,
        tickets: []
      });
    }
    groups.get(key).tickets.push(ticket);
  });
  return [...groups.values()];
}

function ticketGroupCountLabel(total){
  const value = Number(total || 0);
  return value === 1 ? '1 chamado' : `${value} chamados`;
}

function groupedTicketRows(tickets, scope, editable){
  const groups = groupedTickets(tickets, scope);
  if (!groups.length) return '';

  return groups.map((group) => {
    const expanded = isTicketGroupExpanded(scope, group.key);
    const arrow = expanded ? '&#9662;' : '&#9656;';
    const groupRow = `
      <tr
        class="calls-group-row"
        data-group-scope="${esc(scope)}"
        data-group-key="${esc(group.key)}"
        onclick="toggleTicketGroupFromRow(this)"
      >
        <td class="calls-group-cell" colspan="5">
          <span class="calls-group-toggle">${arrow}</span>
          <span class="calls-group-name">${esc(group.targetName)}</span>
          <span class="calls-group-count">${esc(ticketGroupCountLabel(group.tickets.length))}</span>
          <span class="calls-group-hint">${expanded ? 'Recolher' : 'Expandir'}</span>
        </td>
      </tr>
    `;

    const ticketRows = group.tickets.map((ticket) => {
      const targetId = scope === 'vm'
        ? Number(ticket.vm_id || 0)
        : Number(ticket.system_id || 0);
      const clickable = targetId > 0;
      const openFn = scope === 'vm'
        ? `openVmReadOnlyById(${targetId})`
        : `openDetail(${targetId})`;
      const rowClass = expanded ? 'calls-ticket-row' : 'calls-ticket-row calls-ticket-row-hidden';
      return `
        <tr class="${rowClass}"${clickable ? ` onclick="${openFn}"` : ''}>
          <td class="calls-ticket-target"><span class="calls-ticket-indent">&#8627;</span>${esc(ticket.target_name || '-')}</td>
          <td>${esc(ticket.ticket_number || '-')}</td>
          <td>${esc(ticket.description || '-')}</td>
          <td>${esc(ticket.created_at || '-')}</td>
          <td>${editable ? `<div class="actions" onclick="event.stopPropagation()"><button class="act" onclick="editTicketById(${Number(ticket.id)})">&#9998;</button><button class="act del" onclick="deleteTicketById(${Number(ticket.id)})">&#128465;</button></div>` : '-'}</td>
        </tr>
      `;
    }).join('');

    return `${groupRow}${ticketRows}`;
  }).join('');
}

function renderCalls(){
  const systemBody = $('calls-system-body');
  const vmBody = $('calls-vm-body');
  if (!systemBody || !vmBody) return;
  const editable = canEdit();

  const systemTickets = App.tickets.filter((item) => String(item.target_type || '') !== 'vm');
  const vmTickets = App.tickets.filter((item) => String(item.target_type || '') === 'vm');

  if (!systemTickets.length) {
    systemBody.innerHTML = '<tr><td colspan="5" style="color:var(--muted)">Nenhum chamado cadastrado para sistemas.</td></tr>';
  } else {
    systemBody.innerHTML = groupedTicketRows(systemTickets, 'system', editable);
  }

  if (!vmTickets.length) {
    vmBody.innerHTML = '<tr><td colspan="5" style="color:var(--muted)">Nenhum chamado cadastrado para maquinas.</td></tr>';
  } else {
    vmBody.innerHTML = groupedTicketRows(vmTickets, 'vm', editable);
  }
}

async function saveTicket(){
  if (!ensureCanEdit()) return;
  const editingId = Number($('fcall_id')?.value || 0);
  const isEditing = editingId > 0;
  const targetType = String($('fcall_target_type')?.value || 'system').trim().toLowerCase() === 'vm' ? 'vm' : 'system';
  const systemId = Number($('fcall_system_id')?.value || 0);
  const vmId = Number($('fcall_vm_id')?.value || 0);
  const ticketNumber = String($('fcall_number')?.value || '').trim();
  const description = String($('fcall_description')?.value || '').trim();

  if (targetType === 'system' && systemId <= 0) {
    toast('Selecione um sistema para registrar o chamado.', true);
    return;
  }
  if (targetType === 'vm' && vmId <= 0) {
    toast('Selecione uma maquina para registrar o chamado.', true);
    return;
  }
  if (!ticketNumber) {
    toast('Informe o numero do chamado.', true);
    return;
  }
  if (!description) {
    toast('Informe a descricao do chamado.', true);
    return;
  }

  const payload = {
    id: isEditing ? editingId : undefined,
    target_type: targetType,
    system_id: targetType === 'system' ? systemId : null,
    vm_id: targetType === 'vm' ? vmId : null,
    ticket_number: ticketNumber,
    description,
  };

  try {
    const result = await api(isEditing ? 'ticket-update' : 'ticket-save', payload);
    if (!result.ok) throw new Error(result.error || (isEditing ? 'Erro ao editar chamado' : 'Erro ao registrar chamado'));
    const saved = result.data || null;
    if (saved) {
      App.tickets = [saved, ...App.tickets.filter((item) => Number(item.id) !== Number(saved.id))];
    } else {
      const listRes = await api('ticket-list');
      App.tickets = listRes.ok ? (listRes.data || []) : App.tickets;
    }
    resetTicketForm();
    renderCalls();
    toast(isEditing ? 'Chamado atualizado com sucesso.' : 'Chamado registrado com sucesso.');
  } catch (e) {
    toast((isEditing ? 'Erro ao atualizar chamado: ' : 'Erro ao registrar chamado: ') + (e.message || '?'), true);
  }
}

function vmInstanceOptionsByVmId(vmId){
  const vm = App.vms.find((x)=>Number(x.id) === Number(vmId));
  if (!vm) return [];
  const instances = vmInstances(vm);
  if (instances.length) return instances;
  const ips = vmIpList(vm);
  if (!ips.length) return [];
  return ips.map((ip) => ({ name: 'Instancia principal', ip, port: '' }));
}

function fillDbInstanceSelect(selectId, vmId, selectedName='', selectedIp='', selectedPort=''){
  const el = $(selectId);
  if (!el) return;
  const options = vmInstanceOptionsByVmId(vmId);
  const selectedValue = selectedName || selectedIp
    ? encodeInstanceOption({ name: selectedName || 'Instancia principal', ip: selectedIp, port: selectedPort })
    : '';

  el.innerHTML = '<option value="">Selecionar...</option>' + options.map((inst)=>{
    const endpoint = inst.port ? `${inst.ip}:${inst.port}` : inst.ip;
    return `<option value="${esc(encodeInstanceOption(inst))}">${esc(`${inst.name} (${endpoint})`)}</option>`;
  }).join('');
  if (selectedValue && [...el.options].some((o)=>o.value === selectedValue)) {
    el.value = selectedValue;
  } else if (selectedIp) {
    const byIpAndPort = [...el.options].find((o) => {
      const inst = decodeInstanceOption(o.value);
      return inst.ip === selectedIp && String(inst.port || '') === String(selectedPort || '');
    });
    const byIp = byIpAndPort || [...el.options].find((o) => decodeInstanceOption(o.value).ip === selectedIp);
    if (byIp) el.value = byIp.value;
  } else if (options.length === 1) {
    el.value = encodeInstanceOption(options[0]);
  }
}

function selectedDbInstance(selectId){
  const raw = String($(selectId)?.value || '').trim();
  if (!raw) return { name: '', ip: '', port: '' };
  return decodeInstanceOption(raw);
}

function syncDbInstanceOptions(prodSelected=null, hmlSelected=null){
  const vmProdId = Number($('fdbvm')?.value || 0);
  const vmHmlId = Number($('fdbvmh')?.value || 0);
  const currentProd = prodSelected || selectedDbInstance('fdbinstance');
  const currentHml = hmlSelected || selectedDbInstance('fdbinstanceh');
  fillDbInstanceSelect('fdbinstance', vmProdId, currentProd.name, currentProd.ip, currentProd.port);
  fillDbInstanceSelect('fdbinstanceh', vmHmlId, currentHml.name, currentHml.ip, currentHml.port);
  syncDbHomologIp();
}

function syncDbHomologIp(){
  return;
}

function systemDatabases(systemId){
  return App.databases.filter((d) => Number(d.system_id) === Number(systemId));
}

function dbProductionAdministration(db){
  const prodVm = vmById(db?.vm_id);
  if (prodVm) return vmAdministrationLabel(prodVm);
  const raw = String(db?.vm_administration || '').trim();
  return raw || '-';
}

function dbHomologAdministration(db){
  const homologVm = vmById(db?.vm_homolog_id);
  if (homologVm) return vmAdministrationLabel(homologVm);
  const raw = String(db?.vm_administration || '').trim();
  return raw || '-';
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
    const port = dbInstancePort(d, false);
    const endpoint = ip !== '-' ? (port ? `${ip}:${port}` : ip) : '-';
    if (vm && endpoint && endpoint !== '-') return `${vm} (${endpoint})`;
    return vm || endpoint || '-';
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
    .filter((i)=>!accessf || systemAccessLabel(i) === accessf)
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
      i.directory,
      i.size,
      i.repository,
      i.target_version,
      i.app_server,
      i.web_server,
      Number(i.containerization || 0) > 0 ? 'sim' : 'nao',
      i.container_tool,
      i.runtime_port,
      i.php_required_extensions,
      i.php_required_ini,
      i.r_required_packages,
      systemPhpCompatibility(i).label,
      systemRCompatibility(i).label,
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
      systemAccessLabel(i),
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
    ['Servidores de Aplicacao em VMs', vmTechTotal, '#6e9bff'],
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
    $('list-main-body').innerHTML = '<tr><td colspan="12" style="color:var(--muted)">Nenhum sistema encontrado.</td></tr>';
    $('list-desc-body').innerHTML = '<tr><td colspan="7" style="color:var(--muted)">Nenhum sistema encontrado.</td></tr>';
    $('list-infra-body').innerHTML = '<tr><td colspan="11" style="color:var(--muted)">Nenhum sistema encontrado.</td></tr>';
    $('list-db-body').innerHTML = '<tr><td colspan="13" style="color:var(--muted)">Nenhuma base de dados encontrada.</td></tr>';
    $('list-support-body').innerHTML = '<tr><td colspan="8" style="color:var(--muted)">Nenhum contato cadastrado.</td></tr>';
    $('list-ops-body').innerHTML = '<tr><td colspan="5" style="color:var(--muted)">Nenhum dado de deploy cadastrado.</td></tr>';
    $('list-docs-body').innerHTML = '<tr><td colspan="5" style="color:var(--muted)">Nenhuma documentação cadastrada.</td></tr>';
    $('list-cards').innerHTML = '<div class="list-mobile-card"><div class="list-mobile-value" style="color:var(--muted)">Nenhum sistema encontrado.</div></div>';
    return;
  }

  $('list-main-body').innerHTML = list.map((i) => {
    const appServerRaw = String(i.app_server || '').trim();
    const webServerRaw = String(i.web_server || '').trim();
    const appServerNames = systemServiceNamesText(appServerRaw);
    const webServerNames = systemServiceNamesText(webServerRaw);
    const appServerPorts = systemServicePortsText(appServerRaw);
    const webServerPorts = systemServicePortsText(webServerRaw);
    const cells = [
      `<td><div class="list-name">${esc(i.name)}</div></td>`,
      `<td>${esc(i.system_name || '-')}</td>`,
      `<td>${esc(i.version || '-')}</td>`,
      `<td>${esc((i.tech || []).map((x) => String(x || '').trim()).filter(Boolean).join(', ') || '-')}</td>`,
      `<td>${esc(String(i.target_version || '').trim() || '-')}</td>`,
      `<td>${esc(appServerNames)}</td>`,
      `<td>${esc(webServerNames)}</td>`,
      `<td>${esc(Number(i.containerization || 0) > 0 ? 'Sim' : 'Nao')}</td>`,
      `<td>${esc(String(i.container_tool || '').trim() || '-')}</td>`,
      `<td>${esc(appServerPorts)}</td>`,
      `<td>${esc(webServerPorts)}</td>`,
      `<td>${systemCompatibilityMarkup(i, { withAction: true })}</td>`,
    ];
    return `
      <tr onclick="openDetail(${i.id})">
        ${cells.join('')}
      </tr>
    `;
  }).join('');

  $('list-desc-body').innerHTML = list.map((i) => `
    <tr onclick="openDetail(${i.id})">
      <td><div class="list-name">${esc(i.name)}</div></td>
      <td>${esc(i.category || '-')}</td>
      <td>${esc(i.system_group || '-')}</td>
      <td class="crit-${critKind(i.criticality)}">${esc(i.criticality || '-')}</td>
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
      <td>${esc(systemAccessLabel(i))}</td>
      <td>${esc(relationVmText(i, 'administration'))}</td>
    </tr>
  `).join('');

  const systemsById = new Map(list.map((i) => [Number(i.id), i]));
  const systemIds = new Set(systemsById.keys());
  const dbList = App.databases
    .filter((d) => systemIds.has(Number(d.system_id)))
    .sort((a,b) => String(a.db_name || '').localeCompare(String(b.db_name || '')));

  if (!dbList.length) {
    $('list-db-body').innerHTML = '<tr><td colspan="13" style="color:var(--muted)">Nenhuma base de dados encontrada para os filtros aplicados.</td></tr>';
  } else {
    $('list-db-body').innerHTML = dbList.map((d) => {
      const systemId = Number(d.system_id);
      const system = systemsById.get(systemId);
      const systemName = String(system?.name || d.system_name || '-');
      const clickable = Number.isFinite(systemId) && systemsById.has(systemId);
      const administration = dbProductionAdministration(d);
      const prodPort = String(dbInstancePort(d, false) || '').trim() || '-';
      const homologPort = String(dbInstancePort(d, true) || '').trim() || '-';
      return `
      <tr${clickable ? ` onclick="openDetail(${systemId})"` : ''}>
        <td><div class="list-name">${esc(systemName)}</div></td>
        <td>${esc(d.db_name || '-')}</td>
        <td>${esc(d.db_user || '-')}</td>
        <td>${esc(String(d.vm_name || '').trim() || '-')}</td>
        <td>${esc(administration)}</td>
        <td>${esc(dbInstanceIp(d, false))}</td>
        <td>${esc(prodPort)}</td>
        <td>${esc(dbInstanceName(d, false))}</td>
        <td>${esc(String(d.vm_homolog_name || '').trim() || '-')}</td>
        <td>${esc(dbInstanceIp(d, true))}</td>
        <td>${esc(homologPort)}</td>
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
      <td>${esc(i.directory || '-')}</td>
      <td>${esc(i.size || '-')}</td>
      <td>${esc(i.repository || '-')}</td>
    </tr>
  `).join('');

  const docsCell = (i, type) => {
    const ref = String(i?.[`doc_${type}_ref`] || '').trim();
    if (!ref) return '<span class="compat-empty">N/A</span>';
    return `<button class="btn" onclick="event.stopPropagation();openSystemDocFromList(${Number(i.id)},'${esc(type)}')">Visualizar</button>`;
  };

  $('list-docs-body').innerHTML = list.map((i) => `
    <tr onclick="openDetail(${i.id})">
      <td><div class="list-name">${esc(i.name)}</div></td>
      <td>${docsCell(i, 'installation')}</td>
      <td>${docsCell(i, 'maintenance')}</td>
      <td>${docsCell(i, 'security')}</td>
      <td>${docsCell(i, 'manual')}</td>
    </tr>
  `).join('');

  $('list-cards').innerHTML = list.map((i) => `
    <div class="list-mobile-card" onclick="openDetail(${i.id})">
      <div class="list-mobile-head">
        <div class="list-name">${esc(i.name)}</div>
        ${badge(i.status)}
      </div>
      <div class="list-mobile-grid">
        <div class="list-mobile-item"><span class="list-mobile-label">Porta App</span><span class="list-mobile-value">${esc(systemServicePortsText(i.app_server || ''))}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">Porta Web</span><span class="list-mobile-value">${esc(systemServicePortsText(i.web_server || ''))}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">Sistema</span><span class="list-mobile-value">${esc(i.system_name || '-')}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">Categoria</span><span class="list-mobile-value">${esc(i.category || '-')}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">Grupo</span><span class="list-mobile-value">${esc(i.system_group || '-')}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">Criticidade</span><span class="list-mobile-value crit-${critKind(i.criticality)}">${esc(i.criticality || '-')}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">Responsavel Tecnico</span><span class="list-mobile-value">${esc(i.owner || '-')}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">Versao</span><span class="list-mobile-value">${esc(i.version || '-')}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">Linguagem</span><span class="list-mobile-value">${esc((i.tech || []).map((x) => String(x || '').trim()).filter(Boolean).join(', ') || '-')}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">Versao Alvo</span><span class="list-mobile-value">${esc(String(i.target_version || '').trim() || '-')}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">Servidor Aplicacao</span><span class="list-mobile-value">${esc(systemServiceNamesText(i.app_server || ''))}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">Web Server</span><span class="list-mobile-value">${esc(systemServiceNamesText(i.web_server || ''))}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">Containerizacao</span><span class="list-mobile-value">${esc(Number(i.containerization || 0) > 0 ? 'Sim' : 'Nao')}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">Ferramenta Container</span><span class="list-mobile-value">${esc(String(i.container_tool || '').trim() || '-')}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">Compatibilidade</span><span class="list-mobile-value">${esc(systemCompatibilityDisplayLabel(i))}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">VM Producao</span><span class="list-mobile-value">${esc(vmName(i, false))} | ${esc(vmIp(i, false))}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">VM Homologacao</span><span class="list-mobile-value">${esc(vmName(i, true))} | ${esc(vmIp(i, true))}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">VM Desenvolvimento</span><span class="list-mobile-value">${esc(vmName(i, 'dev'))} | ${esc(vmIp(i, 'dev'))}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">URL</span><span class="list-mobile-value">${linkListHtml(systemUrlList(i, false), { compact:true })}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">URL Homologacao</span><span class="list-mobile-value">${linkListHtml(systemUrlList(i, true), { compact:true })}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">Descricao</span><span class="list-mobile-value">${esc(i.description || '-')}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">Observacoes</span><span class="list-mobile-value">${esc(i.notes || '-')}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">Analytics</span><span class="list-mobile-value">${esc(i.analytics || '-')}</span></div>
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

function openSystemDocFromList(systemId, docType){
  const id = Number(systemId || 0);
  if (id <= 0) return;
  const type = String(docType || '').trim().toLowerCase();
  if (!type) return;
  window.open(`?api=system-doc-view&id=${encodeURIComponent(String(id))}&doc_type=${encodeURIComponent(type)}`, '_blank');
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
    const compatMarkup = systemCompatibilityMarkup(i, { withAction: true });
    const techMarkup = (i.tech || []).length
      ? (i.tech || []).map((t) => `<span class="tag">${esc(t)}</span>`).join('')
      : '<span class="system-info-empty">Sem linguagens cadastradas.</span>';
    const docField = (type, label) => {
      const ref = String(i?.[`doc_${type}_ref`] || '').trim();
      if (!ref) {
        return `<div class="system-info-field"><span>${esc(label)}</span><strong>N/A</strong></div>`;
      }
      return `
        <div class="system-info-field" onclick="event.stopPropagation()">
          <span>${esc(label)}</span>
          <button class="btn" onclick="event.stopPropagation();openSystemDocFromList(${Number(i.id)},'${esc(type)}')">Visualizar PDF</button>
        </div>
      `;
    };

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
            <div class="system-info-footer-field">
              <strong>${esc(i.responsible_sector || '-')}</strong>
            </div>
          </div>
        </div>
        <div class="system-info-url-desc-row">
          <div class="system-info-url-banner">${linkListHtml(systemUrlList(i, false))}</div>
          <div class="system-info-desc-footer">
            <strong class="system-info-text">${esc(i.description || '-')}</strong>
          </div>
        </div>

        <section class="system-info-section system-info-section-tech">
          <div class="system-info-title">Informacoes Tecnicas</div>
          <div class="system-info-grid">
            <div class="system-info-field"><span>Linguagem</span>${techMarkup.startsWith('<span class="system-info-empty">') ? techMarkup : `<div class="tags">${techMarkup}</div>`}</div>
            <div class="system-info-field"><span>Categoria</span><strong>${esc(i.category || '-')}</strong></div>
            <div class="system-info-field"><span>Grupo</span><strong>${esc(i.system_group || '-')}</strong></div>
            <div class="system-info-field"><span>Criticidade</span><strong class="crit-${critKind(i.criticality)}">${esc(i.criticality || '-')}</strong></div>
            <div class="system-info-field"><span>Compatibilidade</span>${compatMarkup}</div>
            <div class="system-info-field"><span>Versao Alvo</span><strong>${esc(String(i.target_version || '').trim() || '-')}</strong></div>
            <div class="system-info-field"><span>Servidor Aplicacao</span><strong>${esc(systemServiceNamesText(i.app_server || ''))}</strong></div>
            <div class="system-info-field"><span>Web Server</span><strong>${esc(systemServiceNamesText(i.web_server || ''))}</strong></div>
            <div class="system-info-field"><span>Containerizacao</span><strong>${esc(Number(i.containerization || 0) > 0 ? 'Sim' : 'Nao')}</strong></div>
            <div class="system-info-field"><span>Ferramenta Container</span><strong>${esc(String(i.container_tool || '').trim() || '-')}</strong></div>
            <div class="system-info-field"><span>Porta App</span><strong>${esc(systemServicePortsText(i.app_server || ''))}</strong></div>
            <div class="system-info-field"><span>Porta Web</span><strong>${esc(systemServicePortsText(i.web_server || ''))}</strong></div>
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
            <div class="system-info-field"><span>Acesso</span><strong>${esc(systemAccessLabel(i))}</strong></div>
            <div class="system-info-field"><span>Administracao</span><strong>${esc(relationVmText(i, 'administration'))}</strong></div>
          </div>
        </section>

        ${dbs.length ? `
        <section class="system-info-section system-info-section-db">
          <div class="system-info-title">Base de Dados</div>
          <div class="system-db-list">${dbMarkup}</div>
        </section>
        ` : ''}

        <div class="system-info-bottom-row">
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
              <div class="system-info-field"><span>Diretorio</span><strong>${esc(i.directory || '-')}</strong></div>
              <div class="system-info-field"><span>Tamanho</span><strong>${esc(i.size || '-')}</strong></div>
              <div class="system-info-field"><span>Repositorio</span><strong>${esc(i.repository || '-')}</strong></div>
            </div>
          </section>

          <section class="system-info-section system-info-section-docs">
            <div class="system-info-title">Documentacao</div>
            <div class="system-info-grid">
              ${docField('installation', 'Instalacao')}
              ${docField('maintenance', 'Manutencao/Atualizacao')}
              ${docField('security', 'Seguranca')}
              ${docField('manual', 'Manual/Procedimentos')}
            </div>
          </section>
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
    $('db-body').innerHTML = '<tr><td colspan="13" style="color:var(--muted)">Nenhuma base de dados cadastrada.</td></tr>';
    $('db-cards').innerHTML = '<div class="db-mobile-card"><div class="db-mobile-value" style="color:var(--muted)">Nenhuma base de dados cadastrada.</div></div>';
    return;
  }

  $('db-body').innerHTML = list.map((d) => {
    const administration = dbProductionAdministration(d);
    const prodPort = String(dbInstancePort(d, false) || '').trim() || '-';
    const homologPort = String(dbInstancePort(d, true) || '').trim() || '-';
    return `
    <tr${editable ? ` onclick="openDbFormById(${d.id})"` : ''}>
      <td>${esc(d.system_name || '-')}</td>
      <td>${esc(d.db_name || '-')}</td>
      <td>${esc(d.db_user || '-')}</td>
      <td>${esc(String(d.vm_name || '').trim() || '-')}</td>
      <td>${esc(administration)}</td>
      <td>${esc(dbInstanceIp(d, false))}</td>
      <td>${esc(prodPort)}</td>
      <td>${esc(dbInstanceName(d, false))}</td>
      <td>${esc(String(d.vm_homolog_name || '').trim() || '-')}</td>
      <td>${esc(dbInstanceIp(d, true))}</td>
      <td>${esc(homologPort)}</td>
      <td>${esc(dbInstanceName(d, true))}</td>
      <td>${esc(d.notes || '-')}</td>
    </tr>
  `;
  }).join('');

  $('db-cards').innerHTML = list.map((d) => {
    const administration = dbProductionAdministration(d);
    return `
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
        <div class="db-mobile-item"><span class="db-mobile-label">Administracao</span><span class="db-mobile-value">${esc(administration)}</span></div>
        <div class="db-mobile-item"><span class="db-mobile-label">Instancia SGBD</span><span class="db-mobile-value">${esc(dbInstanceName(d, false))}</span></div>
        <div class="db-mobile-item"><span class="db-mobile-label">VM Homologacao</span><span class="db-mobile-value">${esc(String(d.vm_homolog_name || '').trim() || '-')}</span></div>
        <div class="db-mobile-item"><span class="db-mobile-label">IP da Instancia</span><span class="db-mobile-value">${esc(dbInstanceIp(d, false))}</span></div>
        <div class="db-mobile-item"><span class="db-mobile-label">Porta da Instancia</span><span class="db-mobile-value">${esc(String(dbInstancePort(d, false) || '').trim() || '-')}</span></div>
        <div class="db-mobile-item"><span class="db-mobile-label">IP Instancia Homologacao</span><span class="db-mobile-value">${esc(dbInstanceIp(d, true))}</span></div>
        <div class="db-mobile-item"><span class="db-mobile-label">Porta Instancia Homologacao</span><span class="db-mobile-value">${esc(String(dbInstancePort(d, true) || '').trim() || '-')}</span></div>
        <div class="db-mobile-item"><span class="db-mobile-label">Instancia SGBD Homologacao</span><span class="db-mobile-value">${esc(dbInstanceName(d, true))}</span></div>
        <div class="db-mobile-item"><span class="db-mobile-label">Observacoes</span><span class="db-mobile-value">${esc(d.notes || '-')}</span></div>
      </div>
      ${editable ? `<div class="db-mobile-actions">
        <button class="act" onclick="openDbFormById(${d.id})">&#9998;</button>
      </div>` : ''}
    </div>
  `;
  }).join('');
}

function renderDns(){
  const body = $('dns-body');
  const cards = $('dns-cards');
  if (!body || !cards) return;
  const filters = dnsFilterValues();

  const systems = [...App.items].sort((a,b)=>String(a.name || '').localeCompare(String(b.name || '')));
  if (!systems.length) {
    body.innerHTML = '<tr><td colspan="6" style="color:var(--muted)">Nenhum sistema cadastrado.</td></tr>';
    cards.innerHTML = '<div class="db-mobile-card"><div class="db-mobile-value" style="color:var(--muted)">Nenhum sistema cadastrado.</div></div>';
    return;
  }

  const allRows = systems.flatMap((i) => {
    const prodIp = vmIp(i, false);
    const prodPublicIp = vmPublicIp(i, false);
    const hmlIp = vmIp(i, true);
    const hmlPublicIp = vmPublicIp(i, true);
    const hasBundle = String(i.bundle || '').trim() !== '';
    const bundleStatus = hasBundle ? 'Ativo' : '-';
    const prodRows = systemUrlList(i, false).map((url) => ({ id: Number(i.id), url, ip: prodIp, manualPublicIp: prodPublicIp, bundleStatus, urlEnv: 'Producao' }));
    const hmlRows = systemUrlList(i, true).map((url) => ({ id: Number(i.id), url, ip: hmlIp, manualPublicIp: hmlPublicIp, bundleStatus, urlEnv: 'Homologacao' }));

    if (!prodRows.length && String(prodIp).trim() !== '' && String(prodIp).trim() !== '-') {
      prodRows.push({ id: Number(i.id), url: '', ip: prodIp, manualPublicIp: prodPublicIp, bundleStatus, urlEnv: 'Producao' });
    }
    if (!hmlRows.length && String(hmlIp).trim() !== '' && String(hmlIp).trim() !== '-') {
      hmlRows.push({ id: Number(i.id), url: '', ip: hmlIp, manualPublicIp: hmlPublicIp, bundleStatus, urlEnv: 'Homologacao' });
    }

    return [...prodRows, ...hmlRows];
  }).map((r) => {
    const host = dnsHostFromUrl(r.url);
    const sslTarget = dnsSslTargetKeyFromUrl(r.url);
    const manualPublicIp = String(r.manualPublicIp || '').trim();
    const hasManualNat = manualPublicIp !== '' && manualPublicIp !== '-';
    const cacheHasHost = Boolean(host) && Object.prototype.hasOwnProperty.call(App.dnsNatCache, host);
    const cacheHasSsl = Boolean(sslTarget) && Object.prototype.hasOwnProperty.call(App.dnsSslCache, sslTarget);
    const cacheHasWaf = Boolean(host) && Object.prototype.hasOwnProperty.call(App.dnsWafCache, host);
    const resolvedPublicIp = cacheHasHost ? String(App.dnsNatCache[host] || '').trim() : '';
    const resolvedSsl = cacheHasSsl ? String(App.dnsSslCache[sslTarget] || '').trim() : '';
    const resolvedWaf = cacheHasWaf ? String(App.dnsWafCache[host] || '').trim() : '';
    const internalIps = parseVmIpList(r.ip);
    const resolvedWafIps = parseVmIpList(resolvedWaf);
    const wafEqualsInternalIp = resolvedWafIps.length > 0
      && internalIps.length > 0
      && resolvedWafIps.some((ip) => internalIps.includes(ip));
    const publicIp = hasManualNat
      ? manualPublicIp
      : (cacheHasHost ? (resolvedPublicIp || '-') : (host ? 'Consultando...' : '-'));
    const sslValidity = cacheHasSsl ? (resolvedSsl || '-') : (sslTarget ? 'Consultando...' : dnsSslFallbackText(r.url));
    const wafInfo = !host
      ? '-'
      : (cacheHasWaf
        ? ((resolvedWaf && resolvedWaf !== '-')
          ? (wafEqualsInternalIp ? 'Nao configurado' : `Ativo (${resolvedWaf})`)
          : '-')
        : 'Consultando...');
    return { ...r, host, sslTarget, publicIp, manualPublicIp, sslValidity, wafInfo };
  }).filter((r) => {
    const url = String(r.url || '').trim();
    const ip = String(r.ip || '').trim();
    const publicIp = String(r.publicIp || '').trim();
    const wafInfo = String(r.wafInfo || '').trim();
    const sslValidity = String(r.sslValidity || '').trim();
    const hasUrl = url !== '' && url !== '-';
    const hasIp = ip !== '' && ip !== '-';
    const hasPublicIp = publicIp !== '' && publicIp !== '-';
    const hasWaf = wafInfo !== '' && wafInfo !== '-';
    const hasSsl = sslValidity !== '' && sslValidity !== '-';
    return hasUrl || hasIp || hasPublicIp || hasWaf || hasSsl;
  });
  populateDnsFilterSelects(allRows);

  const rows = allRows.filter((r) => dnsRowMatchesFilters(r, filters));

  if (!rows.length) {
    const hasFilter = Boolean(filters.domain || filters.urlEnv || filters.internalIp || filters.publicIp || filters.ssl);
    const msg = hasFilter
      ? 'Nenhum registro DNS encontrado para os filtros informados.'
      : 'Nenhum registro DNS com URL/IP informado.';
    body.innerHTML = `<tr><td colspan="6" style="color:var(--muted)">${esc(msg)}</td></tr>`;
    cards.innerHTML = `<div class="db-mobile-card"><div class="db-mobile-value" style="color:var(--muted)">${esc(msg)}</div></div>`;
    return;
  }

  const unresolvedHosts = [...new Set(allRows
    .filter((r) => {
      const manualPublicIp = String(r.manualPublicIp || '').trim();
      if (manualPublicIp !== '' && manualPublicIp !== '-') return false;
      return Boolean(r.host) && typeof App.dnsNatCache[r.host] === 'undefined';
    })
    .map((r) => r.host)
    .filter(Boolean)
  )];
  if (unresolvedHosts.length) { resolveDnsPublicIpsForHosts(unresolvedHosts); }
  const unresolvedSslTargets = [...new Set(allRows
    .filter((r) => Boolean(r.sslTarget) && typeof App.dnsSslCache[r.sslTarget] === 'undefined')
    .map((r) => r.sslTarget)
    .filter(Boolean)
  )];
  if (unresolvedSslTargets.length) { resolveDnsSslValidityForTargets(unresolvedSslTargets); }
  const unresolvedWafHosts = [...new Set(allRows
    .filter((r) => Boolean(r.host) && typeof App.dnsWafCache[r.host] === 'undefined')
    .map((r) => r.host)
    .filter(Boolean)
  )];
  if (unresolvedWafHosts.length) { resolveDnsInternalIpsForHosts(unresolvedWafHosts); }

  body.innerHTML = rows.map((r) => `
    <tr onclick="openDetail(${r.id})">
      <td>${linkHtml(r.url)}</td>
      <td>${esc(r.ip || '-')}</td>
      <td>${esc(r.wafInfo || '-')}</td>
      <td>${esc(r.publicIp || '-')}</td>
      <td>${esc(r.sslValidity || '-')}</td>
      <td>${esc(r.bundleStatus || '-')}</td>
    </tr>
  `).join('');

  cards.innerHTML = rows.map((r) => `
    <div class="dns-mobile-card" onclick="openDetail(${r.id})">
      <div class="db-mobile-grid">
        <div class="db-mobile-item"><span class="db-mobile-label">URL</span><span class="db-mobile-value">${linkHtml(r.url)}</span></div>
        <div class="db-mobile-item"><span class="db-mobile-label">IP Interno</span><span class="db-mobile-value">${esc(r.ip || '-')}</span></div>
        <div class="db-mobile-item"><span class="db-mobile-label">WAF</span><span class="db-mobile-value">${esc(r.wafInfo || '-')}</span></div>
        <div class="db-mobile-item"><span class="db-mobile-label">IP Publico (NAT)</span><span class="db-mobile-value">${esc(r.publicIp || '-')}</span></div>
        <div class="db-mobile-item"><span class="db-mobile-label">Validade SSL</span><span class="db-mobile-value">${esc(r.sslValidity || '-')}</span></div>
        <div class="db-mobile-item"><span class="db-mobile-label">Cert. Bundle</span><span class="db-mobile-value">${esc(r.bundleStatus || '-')}</span></div>
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
            const isSgbd = type === 'SGBD';
            const languages = vmLanguageList(vm);
            const languageTags = languages.map((item) => vmLanguageTagText(vm, item)).filter(Boolean);
            const deployTags = vmDeploymentTags(vm);
            const instances = vmInstances(vm);
            const instanceTags = instances.map((inst) => {
              const endpoint = inst.port ? `${inst.ip}:${inst.port}` : inst.ip;
              return endpoint ? `${inst.name || 'Instancia'} (${endpoint})` : `${inst.name || 'Instancia'}`;
            });
            const stackTags = isSgbd ? [] : [...languageTags, ...deployTags];
            const instancePortTags = vmSgbdInstancePortTags(vm);
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
                <div class="vm-report-ip">IP ${esc(vmIpSummary(vm))}</div>
              </div>
              ${osLabel ? `<div class="vm-report-sub">SO: ${esc(osLabel)}</div>` : ''}
              ${specs.length ? `<div class="tags">${specs.map((s)=>`<span class="tag">${esc(s)}</span>`).join('')}</div>` : ''}
              ${stackTags.length ? `<div class="tags">${stackTags.map((tag)=>`<span class="tag">${esc(tag)}</span>`).join('')}</div>` : ''}
              ${isSgbd ? `
              <div class="vm-report-block">
                <div class="vm-report-block-title">Instancias e Portas</div>
                ${instancePortTags.length
                  ? `<div class="tags vm-report-tags">${instancePortTags.map((tag)=>`<span class="tag">${esc(tag)}</span>`).join('')}</div>`
                  : `<div class="vm-report-sub">-</div>`}
              </div>
              ` : (instanceTags.length ? `<div class="tags">${instanceTags.map((tag)=>`<span class="tag">${esc(tag)}</span>`).join('')}</div>` : '')}

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
  const vmosf = String(criteria.os || '').trim();
  const vmadminf = String(criteria.administration || '').trim();

  return vms.filter((vm) => {
    if (vmcatf && vmCategoryLabel(vm) !== vmcatf) return false;
    if (vmtypef && vmTypeLabel(vm) !== vmtypef) return false;
    if (vmosf && norm(vm?.os_name || '') !== norm(vmosf)) return false;
    if (vmadminf && vmAdministrationLabel(vm) !== vmadminf) return false;
    if (!vmq) return true;
    const use = vmUsage(vm.id);
    const linkedSystems = [...new Set([...use.prod.map((s)=>s.name), ...use.hml.map((s)=>s.name), ...use.dev.map((s)=>s.name)])].join(' ');
    const instancesText = vmInstances(vm).map((inst) => `${inst.name || ''} ${inst.ip || ''} ${inst.port || ''}`).join(' ');
    const languageText = vmLanguageList(vm).join(' ');
    const languageVersionText = Object.values(vmLanguageVersions(vm)).map((v) => String(v || '').trim()).filter(Boolean).join(' ');
    const deploymentText = vmDeploymentTags(vm).join(' ');
    return [
      vm.name,
      vm.ip,
      vm.public_ip,
      vm.os_name,
      vm.vcpus,
      vm.ram,
      vm.disk,
      vmCategoryLabel(vm),
      vmTypeLabel(vm),
      vmAdministrationLabel(vm),
      languageText,
      languageVersionText,
      vmTechList(vm).join(' '),
      vmWebServerList(vm).join(' '),
      vmContainerToolList(vm).join(' '),
      vmTargetVersionText(vm),
      vmRuntimePortText(vm),
      deploymentText,
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
    os: $('vmosf')?.value || '',
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
        const languageTags = languages.map((item) => vmLanguageTagText(vm, item)).filter(Boolean);
        const techTags = vmDeploymentTags(vm);
        const isSgbd = type === 'SGBD';
        const sgbdPairs = vmSgbdInstancePortPairs(vm);
        const specs = [vm.vcpus ? `${vm.vcpus} vCPU` : '', vm.ram || '', vm.disk || ''].filter(Boolean).join(' | ');
        const relationCount = category === 'Producao'
          ? use.prod.length
          : category === 'Homologacao'
            ? use.hml.length
            : use.dev.length;
        const metricClass = type === 'SGBD' ? 'vm-db-col' : 'vm-rel-col';
        const metricValue = type === 'SGBD' ? dbs.length : relationCount;
        const diagBtn = vmSupportsDiagnostics(vm)
          ? `<button class="act diag" onclick="event.stopPropagation();openVmDiagnosticPageById(${vm.id})" title="Diagnostico JSON (${esc(vmDiagnosticTechs(vm).join('/') || 'VM')})">&#128202;</button>`
          : '';
        return `
          <tr${editable ? ` onclick="openVmFormById(${vm.id})"` : ''}>
            <td class="vm-name-col">${esc(vm.name)}</td>
            <td class="vm-ip-col">${esc(vmIpSummary(vm))}</td>
            <td>${esc(vmCategoryLabel(vm))}</td>
            <td>${esc(vmTypeLabel(vm))}</td>
            <td>${esc(vmAdministrationLabel(vm))}</td>
            <td class="vm-os-col">${esc(vm.os_name || '-')}</td>
            <td class="vm-res-col">${esc(specs || '-')}</td>
            ${isSgbd
              ? `<td class="vm-tech-col" colspan="2">${sgbdPairs.length ? `<div class="vm-tech-tags">${sgbdPairs.map((t)=>`<span class="tag">${esc(t)}</span>`).join('')}</div>` : '-'}</td>`
              : `<td class="vm-lang-col">${languageTags.length ? `<div class="vm-tech-tags">${languageTags.map((t)=>`<span class="tag">${esc(t)}</span>`).join('')}</div>` : '-'}</td>
            <td class="vm-tech-col">${techTags.length ? `<div class="vm-tech-tags">${techTags.map((t)=>`<span class="tag">${esc(t)}</span>`).join('')}</div>` : '-'}</td>`}
            <td class="${metricClass}">${metricValue}</td>
            <td class="vm-actions-col">${diagBtn ? `<div class="actions">${diagBtn}</div>` : '-'}</td>
          </tr>
        `;
      }).join('');

      const cards = typeVms.map((vm) => {
        const use = vmUsage(vm.id);
        const dbs = vmDatabases(vm.id);
        const languages = vmLanguageList(vm);
        const tech = vmDeploymentTags(vm);
        const instances = vmInstances(vm);
        const languageTags = languages.map((item) => vmLanguageTagText(vm, item)).filter(Boolean);
        const isSgbd = type === 'SGBD';
        const sgbdPairs = vmSgbdInstancePortPairs(vm);
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
            <div class="vm-mobile-ip">${esc(vmIpSummary(vm))}</div>
            <div class="vm-mobile-ip">Ambiente: ${esc(vmCategoryLabel(vm))} | Tipo: ${esc(vmTypeLabel(vm))}</div>
            <div class="vm-mobile-ip">Administracao: ${esc(vmAdministrationLabel(vm))}</div>
            ${vm.os_name ? `<div class="vm-mobile-ip">SO: ${esc(vm.os_name)}</div>` : ''}
            ${specs.length ? `<div class="tags">${specs.map((s)=>`<span class="tag">${esc(s)}</span>`).join('')}</div>` : ''}
            ${isSgbd ? (sgbdPairs.length ? `<div class="tags">${sgbdPairs.map((t)=>`<span class="tag">${esc(t)}</span>`).join('')}</div>` : `<div class="vm-mobile-ip">Instancias / Portas: -</div>`) : (languageTags.length ? `<div class="tags">${languageTags.map((t)=>`<span class="tag">${esc(t)}</span>`).join('')}</div>` : '')}
            ${!isSgbd && tech.length ? `<div class="tags">${tech.map((t)=>`<span class="tag">${esc(t)}</span>`).join('')}</div>` : ''}
            ${!isSgbd && instances.length ? `<div class="tags">${instances.map((inst)=>{ const endpoint = inst.port ? `${inst.ip}:${inst.port}` : inst.ip; return `<span class="tag">${esc(endpoint ? `${inst.name || 'Instancia'} (${endpoint})` : `${inst.name || 'Instancia'}`)}</span>`; }).join('')}</div>` : ''}
            <div class="vm-mobile-stats">
              <div class="vm-mobile-stat"><div class="vm-mobile-stat-label">${relationLabelCard}</div><div class="vm-mobile-stat-value">${relationCount}</div></div>
              <div class="vm-mobile-stat"><div class="vm-mobile-stat-label">Bases</div><div class="vm-mobile-stat-value">${dbs.length}</div></div>
              <div class="vm-mobile-stat"><div class="vm-mobile-stat-label">Total</div><div class="vm-mobile-stat-value">${use.total}</div></div>
            </div>
            ${diagBtn ? `<div class="vm-mobile-actions">${diagBtn}</div>` : ''}
          </div>
        `;
      }).join('');

      return `
        <div class="vm-type-group">
        <div class="vm-type-title">${esc(type)}</div>
        <div class="table-wrap vm-desktop-table">
            <table class="vm-compact-table">
              <thead><tr><th class="vm-name-col">Nome da Maquina</th><th class="vm-ip-col">IP</th><th>Ambiente</th><th>Tipo</th><th>Administracao</th><th class="vm-os-col">Sistema Operacional</th><th class="vm-res-col">Recursos (vCPU | RAM | Disco)</th>${type === 'SGBD' ? '<th class="vm-tech-col" colspan="2">Instancias / Portas</th>' : '<th class="vm-lang-col">Linguagens Instaladas</th><th class="vm-tech-col">Deploy</th>'}<th class="${type === 'SGBD' ? 'vm-db-col' : 'vm-rel-col'}">${type === 'SGBD' ? 'Bases de Dados' : esc(relationHeader)}</th><th class="vm-actions-col">Diagnostico</th></tr></thead>
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
    os: $('vmrosf')?.value || '',
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
        <td>${esc(vmIpSummary(vm))}</td>
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

  if (App.view === 'chamados') {
    $('result-count').textContent = '';
    renderCalls();
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

function systemDocTypeConfig(){
  return {
    installation: { label: 'Instalacao' },
    maintenance: { label: 'Manutencao / Atualizacao' },
    security: { label: 'Seguranca' },
    manual: { label: 'Manual de Uso / Procedimentos' },
  };
}

function systemDocRefInputId(type){ return `fdoc_${type}_ref`; }
function systemDocUpdatedInputId(type){ return `fdoc_${type}_updated_at`; }
function systemDocFileInputId(type){ return `fdoc_${type}_file`; }
function systemDocStatusId(type){ return `fdoc_${type}_status`; }
function systemDocViewButtonId(type){ return `fdoc_${type}_view`; }
function systemDocUploadButtonId(type){ return `fdoc_${type}_upload`; }
function systemDocRemoveButtonId(type){ return `fdoc_${type}_remove`; }

function systemDocFilenameFromReference(reference){
  const ref = String(reference || '').trim();
  if (!ref) return '';
  const parts = ref.split('/');
  return parts.length ? parts[parts.length - 1] : ref;
}

function setSystemDocFieldsFromItem(item){
  const config = systemDocTypeConfig();
  Object.keys(config).forEach((type) => {
    const refId = systemDocRefInputId(type);
    const updatedId = systemDocUpdatedInputId(type);
    const ref = String(item?.[`doc_${type}_ref`] || '').trim();
    const updatedAt = String(item?.[`doc_${type}_updated_at`] || '').trim();
    if ($(refId)) $(refId).value = ref;
    if ($(updatedId)) $(updatedId).value = updatedAt;
    if ($(systemDocFileInputId(type))) $(systemDocFileInputId(type)).value = '';
  });
}

function renderSystemDocSection(){
  const config = systemDocTypeConfig();
  const editable = canEdit();
  const systemId = Number($('fid')?.value || 0);
  const hasSystem = systemId > 0;

  Object.entries(config).forEach(([type]) => {
    const ref = String($(systemDocRefInputId(type))?.value || '').trim();
    const updatedAt = String($(systemDocUpdatedInputId(type))?.value || '').trim();
    const hasFile = ref !== '';
    const filename = systemDocFilenameFromReference(ref);
    const statusEl = $(systemDocStatusId(type));
    const fileInput = $(systemDocFileInputId(type));
    const viewBtn = $(systemDocViewButtonId(type));
    const uploadBtn = $(systemDocUploadButtonId(type));
    const removeBtn = $(systemDocRemoveButtonId(type));

    if (statusEl) {
      statusEl.textContent = hasFile
        ? `${filename}${updatedAt ? ` (Atualizado em ${updatedAt})` : ''}`
        : 'Nenhum arquivo enviado.';
    }
    if (fileInput) fileInput.disabled = !editable || !hasSystem;
    if (viewBtn) viewBtn.disabled = !hasSystem || !hasFile;
    if (uploadBtn) uploadBtn.disabled = !editable || !hasSystem;
    if (removeBtn) removeBtn.disabled = !editable || !hasSystem || !hasFile;
  });

  const hint = $('fdoc_hint');
  if (hint) {
    hint.textContent = hasSystem
      ? 'Use os botoes para visualizar, atualizar ou remover cada PDF.'
      : 'Salve o sistema para habilitar envio de PDFs.';
  }
}

async function fileToBase64(file){
  return await new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => {
      const result = String(reader.result || '');
      const base64 = result.includes(',') ? result.split(',')[1] : result;
      if (!base64) {
        reject(new Error('Falha ao ler arquivo PDF.'));
        return;
      }
      resolve(base64);
    };
    reader.onerror = () => reject(new Error('Falha ao ler arquivo PDF.'));
    reader.readAsDataURL(file);
  });
}

function applySystemDocRowUpdate(row){
  const id = Number(row?.id || 0);
  if (id > 0) {
    const idx = App.items.findIndex((item) => Number(item.id) === id);
    if (idx >= 0) App.items[idx] = row;
    else App.items.unshift(row);
  }
  setSystemDocFieldsFromItem(row || {});
  renderSystemDocSection();
}

function openSystemDocByType(type){
  const systemId = Number($('fid')?.value || 0);
  if (systemId <= 0) {
    toast('Salve o sistema antes de visualizar documentos.', true);
    return;
  }
  const ref = String($(systemDocRefInputId(type))?.value || '').trim();
  if (!ref) {
    toast('Nenhum PDF cadastrado para este tipo de documento.', true);
    return;
  }
  window.open(`?api=system-doc-view&id=${encodeURIComponent(String(systemId))}&doc_type=${encodeURIComponent(String(type))}`, '_blank');
}

async function uploadSystemDocByType(type){
  if (!ensureCanEdit()) return;
  const systemId = Number($('fid')?.value || 0);
  if (systemId <= 0) {
    toast('Salve o sistema antes de enviar documentos.', true);
    return;
  }
  const fileEl = $(systemDocFileInputId(type));
  const file = fileEl?.files?.[0];
  if (!file) {
    toast('Selecione um arquivo PDF para enviar.', true);
    return;
  }
  const filename = String(file.name || '').trim();
  if (!/\.pdf$/i.test(filename)) {
    toast('Envie apenas arquivo PDF.', true);
    return;
  }

  try {
    const contentBase64 = await fileToBase64(file);
    const result = await api('system-doc-upload', {
      system_id: systemId,
      doc_type: type,
      filename,
      content_base64: contentBase64,
    });
    if (!result.ok) throw new Error(result.error || 'Falha ao enviar PDF');
    applySystemDocRowUpdate(result.data || {});
    if (fileEl) fileEl.value = '';
    toast('Documento PDF atualizado com sucesso.');
  } catch (e) {
    toast('Erro ao enviar PDF: ' + (e.message || '?'), true);
  }
}

async function removeSystemDocByType(type){
  if (!ensureCanEdit()) return;
  const systemId = Number($('fid')?.value || 0);
  if (systemId <= 0) {
    toast('Salve o sistema antes de remover documentos.', true);
    return;
  }
  const ref = String($(systemDocRefInputId(type))?.value || '').trim();
  if (!ref) {
    toast('Nao existe documento para remover neste tipo.', true);
    return;
  }
  if (!confirm('Remover este PDF?')) return;

  try {
    const result = await api('system-doc-delete', {
      system_id: systemId,
      doc_type: type,
    });
    if (!result.ok) throw new Error(result.error || 'Falha ao remover PDF');
    applySystemDocRowUpdate(result.data || {});
    toast('Documento removido com sucesso.');
  } catch (e) {
    toast('Erro ao remover PDF: ' + (e.message || '?'), true);
  }
}

function openFormById(id){
  if (!ensureCanEdit('Apenas perfis Editor e Administrador podem abrir o modal de edicao.')) return;
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
  $('faccess').value = systemAccessLabel(item);
  $('fdesc').value = item?.description || '';
  $('fsector').value = item?.responsible_sector || '';
  $('fcoordinator').value = item?.responsible_coordinator || '';
  $('fextension').value = item?.extension_number || '';
  $('femail').value = item?.email || '';
  $('fsupport').value = item?.support || '';
  $('fsupport_contact').value = item?.support_contact || '';
  $('fanalytics').value = item?.analytics || '';
  $('fdirectory').value = item?.directory || '';
  $('fsize').value = item?.size || '';
  $('frepository').value = item?.repository || '';
  $('ftech').value = (item?.tech || []).join(', ');
  $('ftarget_version').value = item?.target_version || '';
  $('fapp_server').value = item?.app_server || '';
  $('fweb_server').value = item?.web_server || '';
  $('fcontainerization').value = Number(item?.containerization || 0) > 0 ? '1' : '0';
  $('fcontainer_tool').value = item?.container_tool || '';
  $('fphp_required_extensions').value = item?.php_required_extensions || '';
  $('fphp_required_ini').value = item?.php_required_ini || '';
  $('fr_required_packages').value = item?.r_required_packages || '';
  setSystemDocFieldsFromItem(item || {});
  syncSystemContainerFields();
  $('fnotes').value = item?.notes || '';

  populateVmSelects();
  $('fvm_id').value = item?.vm_id ? String(item.vm_id) : '';
  $('fvm_homolog_id').value = item?.vm_homolog_id ? String(item.vm_homolog_id) : '';
  $('fvm_dev_id').value = item?.vm_dev_id ? String(item.vm_dev_id) : '';
  syncSystemTechFromVms();
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
  renderSystemDocSection();
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
    vmSearch.value = vm ? `${vm.name} ${joinVmIpList(vm.ip || '')}`.trim() : String(vmId);
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
    system_access:$('faccess').value.trim(),
    url_homolog:joinUrlList($('furl_homolog').value),
    description:$('fdesc').value.trim(),
    responsible_sector:$('fsector').value.trim(),
    responsible_coordinator:$('fcoordinator').value.trim(),
    extension_number:$('fextension').value.trim(),
    email:$('femail').value.trim(),
    support:$('fsupport').value.trim(),
    support_contact:$('fsupport_contact').value.trim(),
    analytics:$('fanalytics').value.trim(),
    ssl:'',
    waf:'',
    bundle:'',
    directory:$('fdirectory').value.trim(),
    size:$('fsize').value.trim(),
    repository:$('frepository').value.trim(),
    notes:$('fnotes').value.trim(),
    tech:$('ftech').value.split(',').map((x)=>x.trim()).filter(Boolean),
    target_version:$('ftarget_version').value.trim(),
    app_server:$('fapp_server').value.trim(),
    web_server:$('fweb_server').value.trim(),
    containerization: String($('fcontainerization').value || '0') === '1' ? 1 : 0,
    container_tool:$('fcontainer_tool').value.trim(),
    php_required_extensions:$('fphp_required_extensions').value.trim(),
    php_required_ini:$('fphp_required_ini').value.trim(),
    r_required_packages:$('fr_required_packages').value.trim(),
  };

  if (!data.containerization) data.container_tool = '';

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
  const isEdit = Boolean(item?.id);
  $('dbtitle').textContent = item ? 'Editar Base de Dados' : 'Nova Base de Dados';
  $('bdelete-db')?.classList.toggle('hidden', !isEdit);
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
      ip: String(item?.db_instance_ip || '').trim(),
      port: String(item?.db_instance_port || '').trim()
    },
    {
      name: String(item?.db_instance_homolog_name || '').trim(),
      ip: String(item?.db_instance_homolog_ip || '').trim(),
      port: String(item?.db_instance_homolog_port || '').trim()
    }
  );
  syncDbHomologIp();
  $('mdb').classList.remove('hidden');
}

function deleteCurrentDb(){
  const id = Number($('fdbid')?.value || 0);
  if (!id) return;
  deleteDb(id);
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
    db_instance_port: selectedInstance.port,
    db_instance_homolog_name: selectedHomologInstance.name,
    db_instance_homolog_ip: selectedHomologInstance.ip,
    db_instance_homolog_port: selectedHomologInstance.port,
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
    closeModal('mdb');
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
  const isEdit = Boolean(vm?.id);
  $('vmtitle').textContent = vm ? 'Editar Maquina' : 'Nova Maquina';
  $('barchive-vm')?.classList.toggle('hidden', !isEdit);
  const vmInstList = vmInstances(vm);
  const mergedVmIps = [...new Set([
    ...vmIpList(vm),
    ...vmInstList.map((inst) => String(inst?.ip || '').trim()).filter(Boolean)
  ])];
  $('fvmid').value = vm?.id || '';
  $('fvmname').value = vm?.name || '';
  $('fvmip').value = mergedVmIps.join(', ');
  $('fvmpublicip').value = vmPublicIpList(vm).join(', ');
  $('fvmcategory').value = vmCategoryLabel(vm);
  $('fvmtype').value = vmTypeLabel(vm);
  $('fvmadministration').value = vmAdministrationLabel(vm);
  $('fvmos').value = vm?.os_name || '';
  $('fvmvcpus').value = vm?.vcpus || '';
  $('fvmram').value = vm?.ram || '';
  $('fvmdisk').value = vm?.disk || '';
  $('fvmlanguage').value = vmLanguageList(vm).join(', ');
  $('fvmcontainertool').value = vmContainerToolList(vm).join(', ');
  setVmAppServerRows(vmServiceRowsFromList(vmTechList(vm)));
  setVmWebServerRows(vmServiceRowsFromList(vmWebServerList(vm)));
  setVmInstanceRows(vmInstList);
  syncVmInstanceIpOptions();
  $('mvm').classList.remove('hidden');
}

function archiveCurrentVm(){
  const id = Number($('fvmid')?.value || 0);
  if (!id) return;
  archiveVm(id);
}

async function saveVm(){
  if (!ensureCanEdit()) return;
  const ipList = vmFormIpList();
  const appServerPayload = readVmServiceRows('fvmapp_rows');
  const webServerPayload = readVmServiceRows('fvmweb_rows');
  const vmInstancesPayload = readVmInstanceRows();
  const instances = vmInstancesPayload.instances;
  if (appServerPayload.invalidName) {
    toast('Informe o nome do servidor da aplicacao.', true);
    return;
  }
  if (appServerPayload.invalidPort) {
    toast(`Servidor da aplicacao: ${appServerPayload.invalidPort}`, true);
    return;
  }
  if (webServerPayload.invalidName) {
    toast('Informe o nome do reverse proxy / web server.', true);
    return;
  }
  if (webServerPayload.invalidPort) {
    toast(`Reverse proxy / web server: ${webServerPayload.invalidPort}`, true);
    return;
  }
  if (vmInstancesPayload.invalidRow) {
    toast('Preencha o IP de todas as instancias SGBD informadas.', true);
    return;
  }
  if (vmInstancesPayload.invalidPort) {
    toast(vmInstancesPayload.invalidPort, true);
    return;
  }
  const appServers = appServerPayload.items
    .map((item) => vmServiceItemValue(item.name, item.port))
    .filter(Boolean);
  const webServers = webServerPayload.items
    .map((item) => vmServiceItemValue(item.name, item.port))
    .filter(Boolean);
  const data = {
    id: $('fvmid').value || null,
    name: $('fvmname').value.trim(),
    ip: ipList.join(', '),
    public_ip: parseVmIpList($('fvmpublicip')?.value || '').join(', '),
    vm_category: $('fvmcategory').value.trim(),
    vm_type: $('fvmtype').value.trim(),
    vm_administration: $('fvmadministration').value.trim(),
    os_name: $('fvmos').value.trim(),
    vcpus: $('fvmvcpus').value.trim(),
    ram: $('fvmram').value.trim(),
    disk: $('fvmdisk').value.trim(),
    vm_instances: instances,
    vm_language: $('fvmlanguage').value.split(',').map((x)=>x.trim()).filter(Boolean),
    vm_app_server: appServers,
    vm_web_server: webServers,
    vm_container_tool: $('fvmcontainertool').value.split(',').map((x)=>x.trim()).filter(Boolean),
    vm_runtime_port: '',
    // Compatibilidade com backend legado.
    vm_tech: appServers,
  };

  if (!data.name || !ipList.length) {
    toast('Informe nome e ao menos um IP da maquina.', true);
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

function openSystemPhpCompatibilityPageById(id){
  if (!App.auth.authenticated) {
    toast('Faca login para acessar a validacao de compatibilidade.', true);
    return;
  }
  const system = App.items.find((x)=>Number(x.id)===Number(id));
  if (!system) {
    toast('Sistema nao encontrado.', true);
    return;
  }
  window.location.href = `system_php_compat.php?id=${encodeURIComponent(String(id))}`;
}

function openSystemRCompatibilityPageById(id){
  if (!App.auth.authenticated) {
    toast('Faca login para acessar a validacao de compatibilidade.', true);
    return;
  }
  const system = App.items.find((x)=>Number(x.id)===Number(id));
  if (!system) {
    toast('Sistema nao encontrado.', true);
    return;
  }
  window.location.href = `system_r_compat.php?id=${encodeURIComponent(String(id))}`;
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
    closeModal('mvm');
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
  const [systemsRes, vmRes, dbRes, archivedRes, ticketsRes] = await Promise.all([api('list'), api('vm-list'), api('db-list'), api('archived-list'), api('ticket-list')]);
  if(!systemsRes.ok) throw new Error(systemsRes.error || 'Erro ao carregar sistemas');
  if(!vmRes.ok) throw new Error(vmRes.error || 'Erro ao carregar maquinas');
  if(!dbRes.ok) throw new Error(dbRes.error || 'Erro ao carregar bases');
  if(!archivedRes.ok) throw new Error(archivedRes.error || 'Erro ao carregar arquivados');
  if(!ticketsRes.ok) throw new Error(ticketsRes.error || 'Erro ao carregar chamados');

  App.items = systemsRes.data || [];
  App.vms = vmRes.data || [];
  App.databases = dbRes.data || [];
  App.tickets = ticketsRes.data || [];
  App.archived = archivedRes.data || { systems: [], vms: [] };
  App.dnsNatCache = {};
  App.dnsNatLoading = false;
  App.dnsSslCache = {};
  App.dnsSslLoading = false;
  App.dnsWafCache = {};
  App.dnsWafLoading = false;
  App.vms.sort((a,b)=>String(a.name || '').localeCompare(String(b.name || '')));
  populateFilters();
  populateVmTabFilters();
  populateVmSelects();
  populateDbSelects();
  populateTicketSelects();
  syncCallTargetFields();
  renderCurrent();
}

function activeModalId(){
  const ids = ['mauth','mpassword','mbackup','mvmcsvpreview','mdb','mvm','mform','mdetail'];
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
  if (modalId === 'mvmcsvpreview') return;

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
$('vm-csv-file')?.addEventListener('change', (ev) => onMachinesCsvFileChange(ev));
$('backup-export-json')?.addEventListener('click', () => exportBackup());
$('backup-import-btn')?.addEventListener('click', () => triggerBackupImport());
$('bcall-save')?.addEventListener('click', () => saveTicket());
$('bcall-cancel')?.addEventListener('click', () => resetTicketForm());
$('fvmip')?.addEventListener('input', () => syncVmInstanceIpOptions());

boot();
