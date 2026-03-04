const App = { items: [], vms: [], databases: [], archived: { systems: [], vms: [] }, view: 'dashboard' };
const $ = (id) => document.getElementById(id);
const esc = (s) => String(s ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;');
const norm = (s) => String(s ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim().toLowerCase();
const linkHtml = (url) => {
  const v = String(url ?? '').trim();
  if (!v) return '-';
  const safe = esc(v);
  return `<a href="${safe}" target="_blank" rel="noreferrer" onclick="event.stopPropagation()">${safe}</a>`;
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
  const opt = { headers:{'Content-Type':'application/json'} };
  if (body !== null){
    opt.method='POST';
    opt.body=JSON.stringify(body);
  }

  let response;
  try {
    response = await fetch(`?api=${encodeURIComponent(action)}`, opt);
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
  return json;
}

function closeModal(id){
  $(id).classList.add('hidden');
}

function closeBg(ev,id){
  if (ev.target === $(id)) closeModal(id);
}

function vmName(item, homolog=false){
  const key = homolog ? 'vm_homolog_name' : 'vm_name';
  const legacyKey = homolog ? 'vm_homolog' : 'vm';
  return String(item?.[key] || item?.[legacyKey] || '').trim() || '-';
}

function vmIp(item, homolog=false){
  const key = homolog ? 'vm_homolog_ip' : 'vm_ip';
  const legacyKey = homolog ? 'ip_homolog' : 'ip';
  return String(item?.[key] || item?.[legacyKey] || '').trim() || '-';
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

function vmCategoryLabel(vm){
  const raw = String(vm?.vm_category || '').trim().toLowerCase();
  if (raw.includes('homo')) return 'Homologacao';
  if (raw.includes('desenv')) return 'Desenvolvimento';
  return 'Producao';
}

function vmCategoryOrder(vm){
  const label = vmCategoryLabel(vm);
  if (label === 'Producao') return 1;
  if (label === 'Homologacao') return 2;
  return 3;
}

function runPrimaryAction(){
  if (App.view === 'bases') {
    openDbForm();
    return;
  }
  if (App.view === 'maquinas') {
    openVmForm();
    return;
  }
  openForm();
}

function syncPrimaryAction(){
  const btn = $('top-action');
  if (!btn) return;

  if (App.view === 'bases') {
    btn.textContent = '+ Nova Base';
    return;
  }

  if (App.view === 'maquinas') {
    btn.textContent = '+ Nova Maquina';
    return;
  }

  btn.textContent = '+ Novo Sistema';
}

function setView(view){
  const nextView = view === 'grid' ? 'lista' : view;
  App.view = nextView;
  ['dashboard','lista','bases','maquinas','arquivados'].forEach((v) => {
    const viewEl = $('view-' + v);
    const tabEl = $('tab-' + v);
    if (viewEl) viewEl.classList.toggle('active', v === nextView);
    if (tabEl) tabEl.classList.toggle('active', v === nextView);
  });
  $('toolbar').style.display = nextView === 'lista' ? 'flex' : 'none';
  syncPrimaryAction();
  renderCurrent();
}

function populateFilters(){
  const categories = [...new Set(App.items.map((i) => String(i.category || '').trim()).filter(Boolean))].sort((a,b)=>a.localeCompare(b));
  const statuses = [...new Set(App.items.map((i) => String(i.status || '').trim()).filter(Boolean))].sort((a,b)=>a.localeCompare(b));
  const fill = (id, first, list) => {
    const el = $(id);
    const prev = el.value;
    el.innerHTML = `<option value="">${first}</option>` + list.map((x)=>`<option>${esc(x)}</option>`).join('');
    if (prev && list.includes(prev)) el.value = prev;
  };
  fill('cat','Todas',categories);
  fill('st','Todos',statuses);
}

function populateVmSelects(){
  const fillVm = (id, first) => {
    const el = $(id);
    if (!el) return;
    const prev = el.value;
    el.innerHTML = `<option value="">${first}</option>` + App.vms.map((vm)=>`<option value="${vm.id}">${esc(vmLabel(vm))}</option>`).join('');
    if (prev && [...el.options].some((o)=>o.value === prev)) el.value = prev;
  };
  fillVm('fvm_id','Selecionar...');
  fillVm('fvm_homolog_id','Selecionar...');
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
    vmEl.innerHTML = '<option value="">Selecionar...</option>' + App.vms.map((vm)=>`<option value="${vm.id}">${esc(vmLabel(vm))}</option>`).join('');
    if (prev && [...vmEl.options].some((o)=>o.value === prev)) vmEl.value = prev;
  }
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
    const vm = String(d.vm_name || '').trim();
    const ip = String(d.vm_ip || '').trim();
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
    d.db_engine,
    d.db_engine_version,
    d.vm_name,
    d.vm_ip
  ].join(' ')).join(' ');
}

function filteredItems(){
  const q = $('q').value.toLowerCase();
  const cat = $('cat').value;
  const st = $('st').value;
  const sort = $('sort').value;

  return [...App.items]
    .filter((i)=>!cat || norm(i.category)===norm(cat))
    .filter((i)=>!st || norm(i.status)===norm(st))
    .filter((i)=>!q || [
      i.name,
      i.system_name,
      i.description,
      i.owner,
      i.category,
      i.status,
      i.url,
      i.url_homolog,
      vmName(i, false),
      vmName(i, true),
      vmIp(i, false),
      vmIp(i, true),
      databaseSearchBlob(i.id),
      (i.tech||[]).join(' ')
    ].join(' ').toLowerCase().includes(q))
    .sort((a,b)=>String(a[sort] ?? '').localeCompare(String(b[sort] ?? '')));
}

function renderDashboard(){
  const total = App.items.length;
  const active = App.items.filter((i)=>statusKind(i.status)==='active').length;
  const maintenance = App.items.filter((i)=>statusKind(i.status)==='maintenance').length;
  const deprecated = App.items.filter((i)=>statusKind(i.status)==='deprecated').length;
  const categories = new Set(App.items.map((i)=>i.category)).size;

  $('stats').innerHTML = [
    ['Total de Sistemas', total, '#67a6ff'],
    ['Ativos', active, '#4be989'],
    ['Em Manutencao', maintenance, '#ff9d4f'],
    ['Depreciados', deprecated, '#ff7070'],
    ['Categorias', categories, '#b08cff'],
  ].map(([label,val,color]) => `<div class="stat"><div class="stat-v" style="color:${color}">${val}</div><div class="stat-l">${label}</div></div>`).join('');

  const byStatus = {};
  App.items.forEach((i) => {
    const key = String(i.status || 'Sem status');
    byStatus[key] = (byStatus[key] || 0) + 1;
  });
  $('status-bars').innerHTML = Object.entries(byStatus).map(([label,count]) => {
    const pct = total ? Math.round((count / total) * 100) : 0;
    const k = statusKind(label);
    const color = k==='active' ? '#22c55e' : k==='maintenance' ? '#f97316' : k==='deprecated' ? '#ef4444' : k==='implementation' ? '#4f8dfd' : '#8b5cf6';
    return `<div class="bar"><div class="bar-label"><span>${esc(label)}</span><span>${count} (${pct}%)</span></div><div class="track"><div class="fill" style="width:${pct}%;background:${color}"></div></div></div>`;
  }).join('');

  const byCategory = {};
  App.items.forEach((i) => {
    const key = String(i.category || 'Sem categoria');
    byCategory[key] = (byCategory[key] || 0) + 1;
  });
  $('category-bars').innerHTML = Object.entries(byCategory).map(([label,count]) => {
    const pct = total ? Math.round((count / total) * 100) : 0;
    return `<div class="bar"><div class="bar-label"><span>${categoryIcon(label)} ${esc(label)}</span><span>${count}</span></div><div class="track"><div class="fill" style="width:${pct}%;background:#4f8dfd"></div></div></div>`;
  }).join('');

  const attention = App.items.filter((i)=>statusKind(i.status)!=='active');
  if (!attention.length) {
    $('attention-list').innerHTML = '<div class="attention-note">Tudo em ordem. Sem alertas.</div>';
    return;
  }

  $('attention-list').innerHTML = attention.map((i) => `
    <div class="attention-item" onclick="openDetail(${i.id})">
      <div><div class="attention-name">${esc(i.name)}</div><div class="attention-note">${badge(i.status)}</div></div>
      <div class="attention-note">${esc(i.notes || '')}</div>
    </div>
  `).join('');
}

function renderList(list){
  $('result-count').textContent = `${list.length} resultado(s)`;
  if (!list.length) {
    $('list-main-body').innerHTML = '<tr><td colspan="10" style="color:var(--muted)">Nenhum sistema encontrado.</td></tr>';
    $('list-infra-body').innerHTML = '<tr><td colspan="8" style="color:var(--muted)">Nenhum sistema encontrado.</td></tr>';
    $('list-db-body').innerHTML = '<tr><td colspan="7" style="color:var(--muted)">Nenhuma base de dados encontrada.</td></tr>';
    $('list-cards').innerHTML = '<div class="list-mobile-card"><div class="list-mobile-value" style="color:var(--muted)">Nenhum sistema encontrado.</div></div>';
    return;
  }

  $('list-main-body').innerHTML = list.map((i) => `
    <tr onclick="openDetail(${i.id})">
      <td><div class="list-name">${esc(i.name)}</div></td>
      <td>${esc(i.system_name || '-')}</td>
      <td>${esc(i.category || '-')}</td>
      <td>${badge(i.status)}</td>
      <td class="crit-${critKind(i.criticality)}">${esc(i.criticality || '-')}</td>
      <td>${esc(i.owner || '-')}</td>
      <td>${esc(i.version || '-')}</td>
      <td>${esc(i.description || '-')}</td>
      <td>${esc(i.notes || '-')}</td>
      <td onclick="event.stopPropagation()"><div class="actions"><button class="act" onclick="openFormById(${i.id})">&#9998;</button><button class="act del" onclick="archiveSystem(${i.id})">&#128230;</button></div></td>
    </tr>
  `).join('');

  $('list-infra-body').innerHTML = list.map((i) => `
    <tr onclick="openDetail(${i.id})">
      <td><div class="list-name">${esc(i.name)}</div></td>
      <td>${esc(vmName(i, false))}</td>
      <td>${esc(vmIp(i, false))}</td>
      <td>${esc(vmName(i, true))}</td>
      <td>${esc(vmIp(i, true))}</td>
      <td>${linkHtml(i.url)}</td>
      <td>${linkHtml(i.url_homolog)}</td>
      <td>${(i.tech || []).map((t) => `<span class="tag">${esc(t)}</span>`).join('')}</td>
    </tr>
  `).join('');

  const systemsById = new Map(list.map((i) => [Number(i.id), i]));
  const systemIds = new Set(systemsById.keys());
  const dbList = App.databases
    .filter((d) => systemIds.has(Number(d.system_id)))
    .sort((a,b) => String(a.db_name || '').localeCompare(String(b.db_name || '')));

  if (!dbList.length) {
    $('list-db-body').innerHTML = '<tr><td colspan="7" style="color:var(--muted)">Nenhuma base de dados encontrada para os filtros aplicados.</td></tr>';
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
        <td>${esc(d.db_engine || '-')}</td>
        <td>${esc(d.db_engine_version || '-')}</td>
        <td>${esc(d.vm_name || '-')}</td>
        <td>${esc(d.vm_ip || '-')}</td>
        <td>${esc(d.notes || '-')}</td>
      </tr>
    `;
    }).join('');
  }

  $('list-cards').innerHTML = list.map((i) => `
    <div class="list-mobile-card" onclick="openDetail(${i.id})">
      <div class="list-mobile-head">
        <div class="list-name">${esc(i.name)}</div>
        ${badge(i.status)}
      </div>
      <div class="list-mobile-grid">
        <div class="list-mobile-item"><span class="list-mobile-label">Sistema</span><span class="list-mobile-value">${esc(i.system_name || '-')}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">Categoria</span><span class="list-mobile-value">${esc(i.category || '-')}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">Criticidade</span><span class="list-mobile-value crit-${critKind(i.criticality)}">${esc(i.criticality || '-')}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">Responsavel</span><span class="list-mobile-value">${esc(i.owner || '-')}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">Versao</span><span class="list-mobile-value">${esc(i.version || '-')}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">VM Producao</span><span class="list-mobile-value">${esc(vmName(i, false))} | ${esc(vmIp(i, false))}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">VM Homologacao</span><span class="list-mobile-value">${esc(vmName(i, true))} | ${esc(vmIp(i, true))}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">URL</span><span class="list-mobile-value">${linkHtml(i.url)}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">URL Homologacao</span><span class="list-mobile-value">${linkHtml(i.url_homolog)}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">Descricao</span><span class="list-mobile-value">${esc(i.description || '-')}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">Observacoes</span><span class="list-mobile-value">${esc(i.notes || '-')}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">Bases de Dados</span><span class="list-mobile-value">${esc(databaseNamesText(i.id))}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">Hospedagem das Bases</span><span class="list-mobile-value">${esc(databaseHostsText(i.id))}</span></div>
        <div class="list-mobile-item"><span class="list-mobile-label">SGBD / Versao</span><span class="list-mobile-value">${esc(databaseEngineText(i.id))}</span></div>
      </div>
      <div class="tags">${(i.tech || []).map((t) => `<span class="tag">${esc(t)}</span>`).join('')}</div>
      <div class="list-mobile-actions" onclick="event.stopPropagation()">
        <button class="act" onclick="openFormById(${i.id})">&#9998;</button>
        <button class="act del" onclick="archiveSystem(${i.id})">&#128230;</button>
      </div>
    </div>
  `).join('');
}

function vmUsage(vmId){
  const prod = App.items.filter((s) => Number(s.vm_id) === Number(vmId));
  const hml = App.items.filter((s) => Number(s.vm_homolog_id) === Number(vmId));
  const uniq = new Set([...prod.map((s)=>Number(s.id)), ...hml.map((s)=>Number(s.id))]);
  return { prod, hml, total: uniq.size };
}

function vmDatabases(vmId){
  return App.databases.filter((d) => Number(d.vm_id) === Number(vmId));
}

function renderDatabases(){
  const list = [...App.databases].sort((a,b)=>String(a.db_name || '').localeCompare(String(b.db_name || '')));
  if (!list.length) {
    $('db-body').innerHTML = '<tr><td colspan="8" style="color:var(--muted)">Nenhuma base de dados cadastrada.</td></tr>';
    $('db-cards').innerHTML = '<div class="db-mobile-card"><div class="db-mobile-value" style="color:var(--muted)">Nenhuma base de dados cadastrada.</div></div>';
    return;
  }

  $('db-body').innerHTML = list.map((d) => `
    <tr>
      <td>${esc(d.system_name || '-')}</td>
      <td>${esc(d.db_name || '-')}</td>
      <td>${esc(d.db_engine || '-')}</td>
      <td>${esc(d.db_engine_version || '-')}</td>
      <td>${esc(d.vm_name || '-')}</td>
      <td>${esc(d.vm_ip || '-')}</td>
      <td>${esc(d.notes || '-')}</td>
      <td><div class="actions"><button class="act" onclick="openDbFormById(${d.id})">&#9998;</button><button class="act del" onclick="deleteDb(${d.id})">&#128465;</button></div></td>
    </tr>
  `).join('');

  $('db-cards').innerHTML = list.map((d) => `
    <div class="db-mobile-card">
      <div class="db-mobile-head">
        <div class="db-mobile-title">${esc(d.db_name || '-')}</div>
        <div class="db-mobile-engine">${esc((`${String(d.db_engine || '-').trim()} ${String(d.db_engine_version || '').trim()}`).trim())}</div>
      </div>
      <div class="db-mobile-grid">
        <div class="db-mobile-item"><span class="db-mobile-label">Sistema</span><span class="db-mobile-value">${esc(d.system_name || '-')}</span></div>
        <div class="db-mobile-item"><span class="db-mobile-label">Maquina</span><span class="db-mobile-value">${esc(d.vm_name || '-')}</span></div>
        <div class="db-mobile-item"><span class="db-mobile-label">Versao SGBD</span><span class="db-mobile-value">${esc(d.db_engine_version || '-')}</span></div>
        <div class="db-mobile-item"><span class="db-mobile-label">IP</span><span class="db-mobile-value">${esc(d.vm_ip || '-')}</span></div>
        <div class="db-mobile-item"><span class="db-mobile-label">Observacoes</span><span class="db-mobile-value">${esc(d.notes || '-')}</span></div>
      </div>
      <div class="db-mobile-actions">
        <button class="act" onclick="openDbFormById(${d.id})">&#9998;</button>
        <button class="act del" onclick="deleteDb(${d.id})">&#128465;</button>
      </div>
    </div>
  `).join('');
}

function renderVmReport(){
  const box = $('vm-report');
  if (!App.vms.length) {
    box.innerHTML = '<div class="vm-report-empty">Nenhuma maquina cadastrada.</div>';
    return;
  }

  const groups = ['Producao','Homologacao','Desenvolvimento'].map((category) => ({
    category,
    items: App.vms.filter((vm) => vmCategoryLabel(vm) === category).sort((a,b)=>String(a.name || '').localeCompare(String(b.name || '')))
  })).filter((g)=>g.items.length > 0);

  box.innerHTML = groups.map((group) => `
    <div class="vm-report-item">
      <div class="vm-report-title">${esc(group.category)}</div>
    </div>
    ${group.items.map((vm) => {
    const use = vmUsage(vm.id);
    const dbs = vmDatabases(vm.id);
    const tech = vmTechList(vm);
    const lines = [];
    use.prod.forEach((s) => lines.push(`${s.name} (producao)`));
    use.hml.forEach((s) => lines.push(`${s.name} (homologacao)`));
    const dbLines = dbs.map((d) => `${d.db_name} [${d.db_engine}${d.db_engine_version ? ' ' + d.db_engine_version : ''}] - ${d.system_name || '-'}`);
    return `
      <div class="vm-report-item">
        <div class="vm-report-title">${esc(vm.name)}</div>
        <div class="vm-report-sub">IP ${esc(vm.ip || '-')} &#8226; ${use.total} sistema(s) &#8226; ${dbs.length} base(s)</div>
        ${tech.length ? `<div class="tags">${tech.map((t)=>`<span class="tag">${esc(t)}</span>`).join('')}</div>` : '<div class="vm-report-empty">Sem tecnologias cadastradas.</div>'}
        ${lines.length ? `<ul class="vm-report-list">${lines.map((x)=>`<li>${esc(x)}</li>`).join('')}</ul>` : '<div class="vm-report-empty">Sem sistemas vinculados.</div>'}
        ${dbLines.length ? `<ul class="vm-report-list vm-report-db">${dbLines.map((x)=>`<li>${esc(x)}</li>`).join('')}</ul>` : '<div class="vm-report-empty">Sem bases vinculadas.</div>'}
      </div>
    `;
  }).join('')}
  `).join('');
}

function renderMachines(){
  const container = $('vm-sections');
  if (!App.vms.length) {
    container.innerHTML = '<div class="vm-section-empty">Nenhuma maquina cadastrada.</div>';
    renderVmReport();
    return;
  }

  const categories = ['Producao','Homologacao','Desenvolvimento'];
  container.innerHTML = categories.map((category) => {
    const vms = App.vms
      .filter((vm) => vmCategoryLabel(vm) === category)
      .sort((a,b)=>String(a.name || '').localeCompare(String(b.name || '')));
    if (!vms.length) return '';

    const rows = vms.map((vm) => {
      const use = vmUsage(vm.id);
      const dbs = vmDatabases(vm.id);
      const tech = vmTechList(vm);
      return `
        <tr>
          <td>${esc(vm.name)}</td>
          <td>${esc(vm.ip || '-')}</td>
          <td>${tech.length ? tech.map((t)=>`<span class="tag">${esc(t)}</span>`).join('') : '-'}</td>
          <td>${use.prod.length}</td>
          <td>${use.hml.length}</td>
          <td>${dbs.length}</td>
          <td>${use.total}</td>
          <td><div class="actions"><button class="act" onclick="openVmFormById(${vm.id})">&#9998;</button><button class="act del" onclick="archiveVm(${vm.id})">&#128230;</button></div></td>
        </tr>
      `;
    }).join('');

    const cards = vms.map((vm) => {
      const use = vmUsage(vm.id);
      const dbs = vmDatabases(vm.id);
      const tech = vmTechList(vm);
      return `
        <div class="vm-mobile-card">
          <div class="vm-mobile-title">${esc(vm.name)}</div>
          <div class="vm-mobile-ip">${esc(vm.ip || '-')}</div>
          ${tech.length ? `<div class="tags">${tech.map((t)=>`<span class="tag">${esc(t)}</span>`).join('')}</div>` : ''}
          <div class="vm-mobile-stats">
            <div class="vm-mobile-stat"><div class="vm-mobile-stat-label">Producao</div><div class="vm-mobile-stat-value">${use.prod.length}</div></div>
            <div class="vm-mobile-stat"><div class="vm-mobile-stat-label">Homologacao</div><div class="vm-mobile-stat-value">${use.hml.length}</div></div>
            <div class="vm-mobile-stat"><div class="vm-mobile-stat-label">Bases</div><div class="vm-mobile-stat-value">${dbs.length}</div></div>
            <div class="vm-mobile-stat"><div class="vm-mobile-stat-label">Total</div><div class="vm-mobile-stat-value">${use.total}</div></div>
          </div>
          <div class="vm-mobile-actions">
            <button class="act" onclick="openVmFormById(${vm.id})">&#9998;</button>
            <button class="act del" onclick="archiveVm(${vm.id})">&#128230;</button>
          </div>
        </div>
      `;
    }).join('');

    const categoryClass = `vm-section-${norm(category).replace(/[^a-z0-9]+/g, '-')}`;
    return `
      <div class="vm-section ${categoryClass}">
        <div class="vm-section-title">${esc(category)}</div>
        <div class="table-wrap vm-desktop-table">
          <table style="min-width:760px">
            <thead><tr><th>Nome da Maquina</th><th>IP</th><th>Tecnologias / Versoes</th><th>Sistemas em Producao</th><th>Sistemas em Homologacao</th><th>Bases de Dados</th><th>Total Sistemas</th><th style="width:98px">Acoes</th></tr></thead>
            <tbody>${rows}</tbody>
          </table>
        </div>
        <div class="vm-mobile-cards vm-section-cards">${cards}</div>
      </div>
    `;
  }).join('');

  renderVmReport();
}

function renderArchived(){
  const systems = App.archived.systems || [];
  const vms = App.archived.vms || [];

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
        <td><div class="actions"><button class="act" onclick="restoreSystem(${i.id})">&#8634;</button><button class="act del" onclick="deleteSystemPermanent(${i.id})">&#128465;</button></div></td>
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
        <td><div class="actions"><button class="act" onclick="restoreVm(${vm.id})">&#8634;</button><button class="act del" onclick="deleteVmPermanent(${vm.id})">&#128465;</button></div></td>
      </tr>
    `).join('');
  }
}

function renderCurrent(){
  const active = App.items.filter((i)=>statusKind(i.status)==='active').length;
  $('count').innerHTML = `${App.items.length} sistemas &#8226; ${App.databases.length} bases &#8226; ${active} ativos`;

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

  if (App.view === 'bases') {
    $('result-count').textContent = '';
    renderDatabases();
    return;
  }

  if (App.view === 'arquivados') {
    $('result-count').textContent = '';
    renderArchived();
    return;
  }

  const list = filteredItems();
  if (App.view === 'lista') renderList(list);
}

function openFormById(id){
  const item = App.items.find((x)=>Number(x.id)===Number(id));
  if (item) openForm(item);
}

function openForm(item=null){
  $('ftitle').textContent = item ? 'Editar Sistema' : 'Novo Sistema';
  $('bsave').textContent = item ? 'Salvar Alteracoes' : 'Salvar';
  $('fid').value = item?.id || '';
  $('fname').value = item?.name || '';
  $('fsystem').value = item?.system_name || '';
  $('fver').value = item?.version || '';
  $('fcat').value = item?.category || 'Outro';
  $('fst').value = item?.status || 'Ativo';
  $('fcrit').value = item?.criticality || 'Media';
  $('fowner').value = item?.owner || '';
  $('furl').value = item?.url || '';
  $('furl_homolog').value = item?.url_homolog || '';
  $('fdesc').value = item?.description || '';
  $('ftech').value = (item?.tech || []).join(', ');
  $('fnotes').value = item?.notes || '';

  populateVmSelects();
  $('fvm_id').value = item?.vm_id ? String(item.vm_id) : '';
  $('fvm_homolog_id').value = item?.vm_homolog_id ? String(item.vm_homolog_id) : '';

  toggleSave();
  $('mform').classList.remove('hidden');
}

function toggleSave(){
  $('bsave').disabled = $('fname').value.trim() === '';
}

function openDetail(id){
  const i = App.items.find((x)=>Number(x.id)===Number(id));
  if (!i) return;

  $('dtitle').textContent = i.name;
  $('dbody').innerHTML = `
    <div>${badge(i.status)}</div>
    <p class="card-desc">${esc(i.description || 'Sem descricao')}</p>
    <div class="tags">${(i.tech || []).map((t) => `<span class="tag">${esc(t)}</span>`).join('')}</div>
    <div class="card-foot"><span>Sistema: ${esc(i.system_name || '-')}</span><span>Categoria: ${esc(i.category || '-')}</span></div>
    <div class="card-foot"><span>VM Producao: ${esc(vmName(i, false))}</span><span>IP: ${esc(vmIp(i, false))}</span></div>
    <div class="card-foot"><span>VM Homologacao: ${esc(vmName(i, true))}</span><span>IP Homologacao: ${esc(vmIp(i, true))}</span></div>
    <div class="card-foot"><span>Responsavel: ${esc(i.owner || '-')}</span><span>Versao: ${esc(i.version || '-')}</span></div>
    <div class="card-foot"><span>Criticidade: <span class="crit-${critKind(i.criticality)}">${esc(i.criticality || '-')}</span></span><span></span></div>
    <div class="card-foot"><span>URL: ${linkHtml(i.url)}</span><span>URL Homologacao: ${linkHtml(i.url_homolog)}</span></div>
    ${i.notes ? `<p class="card-desc">Observacoes: ${esc(i.notes)}</p>` : ''}
  `;

  $('dedit').onclick = () => { closeModal('mdetail'); openForm(i); };
  $('ddel').onclick = () => archiveSystem(i.id);
  $('mdetail').classList.remove('hidden');
}

async function saveSystem(){
  const data = {
    id:$('fid').value || null,
    name:$('fname').value.trim(),
    system_name:$('fsystem').value.trim(),
    version:$('fver').value.trim(),
    category:$('fcat').value.trim() || 'Outro',
    status:$('fst').value.trim() || 'Ativo',
    criticality:$('fcrit').value.trim() || 'Media',
    owner:$('fowner').value.trim(),
    url:$('furl').value.trim(),
    vm_id:Number($('fvm_id').value) || null,
    vm_homolog_id:Number($('fvm_homolog_id').value) || null,
    url_homolog:$('furl_homolog').value.trim(),
    description:$('fdesc').value.trim(),
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
  const item = App.items.find((x)=>Number(x.id)===Number(id));
  if(!confirm(`Arquivar ${item?.name || 'sistema'}?`)) return;
  try{
    const r = await api('archive', {id});
    if(!r.ok) throw new Error(r.error || 'Erro ao arquivar');
    await refreshAll();
    closeModal('mdetail');
    toast('Sistema arquivado');
  }catch(e){ toast('Erro ao arquivar: ' + (e.message || '?'), true); }
}

async function restoreSystem(id){
  if(!confirm('Restaurar sistema arquivado?')) return;
  try{
    const r = await api('restore', {id});
    if(!r.ok) throw new Error(r.error || 'Erro ao restaurar');
    await refreshAll();
    toast('Sistema restaurado');
  }catch(e){ toast('Erro ao restaurar: ' + (e.message || '?'), true); }
}

async function deleteSystemPermanent(id){
  if(!confirm('Excluir sistema definitivamente? Esta acao nao pode ser desfeita.')) return;
  try{
    const r = await api('delete', {id});
    if(!r.ok) throw new Error(r.error || 'Erro ao excluir');
    await refreshAll();
    toast('Sistema excluido definitivamente');
  }catch(e){ toast('Erro ao excluir: ' + (e.message || '?'), true); }
}

function openDbFormById(id){
  const item = App.databases.find((x)=>Number(x.id)===Number(id));
  if (item) openDbForm(item);
}

function openDbForm(item=null){
  $('dbtitle').textContent = item ? 'Editar Base de Dados' : 'Nova Base de Dados';
  $('fdbid').value = item?.id || '';
  $('fdbname').value = item?.db_name || '';
  $('fdbengine').value = item?.db_engine || '';
  $('fdbenginever').value = item?.db_engine_version || '';
  $('fdbnotes').value = item?.notes || '';

  populateDbSelects();
  $('fdbsystem').value = item?.system_id ? String(item.system_id) : '';
  $('fdbvm').value = item?.vm_id ? String(item.vm_id) : '';
  $('mdb').classList.remove('hidden');
}

async function saveDb(){
  const data = {
    id: $('fdbid').value || null,
    system_id: Number($('fdbsystem').value) || null,
    vm_id: Number($('fdbvm').value) || null,
    db_name: $('fdbname').value.trim(),
    db_engine: $('fdbengine').value.trim(),
    db_engine_version: $('fdbenginever').value.trim(),
    notes: $('fdbnotes').value.trim(),
  };

  if (!data.system_id || !data.vm_id || !data.db_name || !data.db_engine) {
    toast('Informe sistema, maquina, nome da base e SGBD.', true);
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
  const vm = App.vms.find((x)=>Number(x.id)===Number(id));
  if (vm) openVmForm(vm);
}

function openVmForm(vm=null){
  $('vmtitle').textContent = vm ? 'Editar Maquina' : 'Nova Maquina';
  $('fvmid').value = vm?.id || '';
  $('fvmname').value = vm?.name || '';
  $('fvmip').value = vm?.ip || '';
  $('fvmcategory').value = vmCategoryLabel(vm);
  $('fvmtech').value = vmTechList(vm).join(', ');
  $('mvm').classList.remove('hidden');
}

async function saveVm(){
  const data = {
    id: $('fvmid').value || null,
    name: $('fvmname').value.trim(),
    ip: $('fvmip').value.trim(),
    vm_category: $('fvmcategory').value.trim(),
    vm_tech: $('fvmtech').value.split(',').map((x)=>x.trim()).filter(Boolean),
  };

  if (!data.name || !data.ip) {
    toast('Informe nome e IP da maquina.', true);
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

async function archiveVm(id){
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
  if(!confirm('Restaurar maquina arquivada?')) return;
  try{
    const r = await api('vm-restore', {id});
    if(!r.ok) throw new Error(r.error || 'Erro ao restaurar maquina');
    await refreshAll();
    toast('Maquina restaurada');
  }catch(e){ toast('Erro ao restaurar maquina: ' + (e.message || '?'), true); }
}

async function deleteVmPermanent(id){
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
  populateVmSelects();
  populateDbSelects();
  renderCurrent();
}

async function boot(){
  try{
    await refreshAll();
    $('loading').style.display = 'none';
    setView(App.view);
  }catch(e){
    $('loading').textContent = 'Erro: ' + (e.message || 'Falha ao carregar dados');
    toast('Erro ao carregar: ' + (e.message || '?'), true);
  }
}

boot();
