const App = { items: [], vms: [], databases: [], archived: { systems: [], vms: [] }, view: 'cards', diagramFocusSystemId: 0 };
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

function vmTypeLabel(vm){
  const raw = String(vm?.vm_type || '').trim().toLowerCase();
  if (raw.includes('sgbd') || raw.includes('db') || raw.includes('banco')) return 'SGBD';
  return 'Sistemas';
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
  ['dashboard','lista','cards','dns','bases','maquinas','diagrama','arquivados'].forEach((v) => {
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
  const statuses = [...new Set(App.items.map((i) => String(i.status || '').trim()).filter(Boolean))].sort((a,b)=>a.localeCompare(b));
  const fill = (id, first, list) => {
    const el = $(id);
    if (!el) return;
    const prev = el.value;
    el.innerHTML = `<option value="">${first}</option>` + list.map((x)=>`<option>${esc(x)}</option>`).join('');
    if (prev && list.includes(prev)) el.value = prev;
  };
  fill('cat','Todas',categories);
  fill('st','Todos',statuses);

  const vmEl = $('vmf');
  if (vmEl) {
    const prev = vmEl.value;
    const vms = [...App.vms]
      .filter((vm) => vmTypeLabel(vm) !== 'SGBD')
      .sort((a,b)=>String(a.name || '').localeCompare(String(b.name || '')))
      .map((vm) => ({ id: String(vm.id), label: vmLabel(vm) }));
    vmEl.innerHTML = '<option value="">Todas VMs</option>' + vms.map((vm)=>`<option value="${esc(vm.id)}">${esc(vm.label)}</option>`).join('');
    if (prev && vms.some((vm)=>vm.id === prev)) vmEl.value = prev;
  }
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

  const vmHmlEl = $('fdbvmh');
  if (vmHmlEl) {
    const prev = vmHmlEl.value;
    vmHmlEl.innerHTML = '<option value="">Selecionar...</option>' + App.vms.map((vm)=>`<option value="${vm.id}">${esc(vmLabel(vm))}</option>`).join('');
    if (prev && [...vmHmlEl.options].some((o)=>o.value === prev)) vmHmlEl.value = prev;
  }
}

function syncDbHomologIp(){
  const vmId = Number($('fdbvmh')?.value || 0);
  const vm = App.vms.find((x)=>Number(x.id) === vmId);
  const ip = String(vm?.ip || '').trim();
  const ipEl = $('fdbvmhip');
  if (ipEl) ipEl.value = ip || '';
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
    d.db_user,
    d.db_engine,
    d.db_engine_version,
    d.db_engine_version_homolog,
    d.vm_name,
    d.vm_ip,
    d.vm_homolog_name,
    d.vm_homolog_ip
  ].join(' ')).join(' ');
}

function filteredItems(){
  const q = $('q').value.toLowerCase();
  const cat = $('cat').value;
  const st = $('st').value;
  const vmf = $('vmf')?.value || '';
  const sort = $('sort').value;

  return [...App.items]
    .filter((i)=>!cat || norm(i.category)===norm(cat))
    .filter((i)=>!st || norm(i.status)===norm(st))
    .filter((i)=>!vmf || Number(i.vm_id || 0) === Number(vmf) || Number(i.vm_homolog_id || 0) === Number(vmf))
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
  const totalSystems = App.items.length;
  const totalVms = App.vms.length;
  const totalDatabases = App.databases.length;
  const active = App.items.filter((i)=>statusKind(i.status)==='active').length;
  const maintenance = App.items.filter((i)=>statusKind(i.status)==='maintenance').length;
  const deprecated = App.items.filter((i)=>statusKind(i.status)==='deprecated').length;
  const categories = new Set(App.items.map((i)=>String(i.category || '').trim()).filter(Boolean)).size;
  const dbUsersFilled = App.databases.filter((d)=>String(d.db_user || '').trim() !== '').length;
  const vmOsFilled = App.vms.filter((vm)=>String(vm.os_name || '').trim() !== '').length;
  const vmResourcesFilled = App.vms.filter((vm)=>String(vm.vcpus || '').trim() !== '' || String(vm.ram || '').trim() !== '' || String(vm.disk || '').trim() !== '').length;
  const vmTechTotal = App.vms.reduce((acc, vm) => acc + vmTechList(vm).length, 0);

  $('stats').innerHTML = [
    ['Sistemas', totalSystems, '#67a6ff'],
    ['Maquinas Virtuais', totalVms, '#2cc7b0'],
    ['Bases de Dados', totalDatabases, '#e0a95a'],
    ['Ativos', active, '#4be989'],
    ['Em Manutencao', maintenance, '#ff9d4f'],
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
        <div><div class="quality-name">Usuario de banco cadastrado</div><div class="quality-note">${dbUsersFilled}/${totalDatabases || 0} bases</div></div>
        <div class="quality-pct">${totalDatabases ? Math.round((dbUsersFilled / totalDatabases) * 100) : 0}%</div>
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

  const attention = App.items.filter((i)=>statusKind(i.status)!=='active');
  if (!attention.length) {
    $('attention-list').innerHTML = '<div class="attention-note">Tudo em ordem. Sem alertas.</div>';
  } else {
    $('attention-list').innerHTML = attention.map((i) => `
      <div class="attention-item" onclick="openDetail(${i.id})">
        <div><div class="attention-name">${esc(i.name)}</div><div class="attention-note">${badge(i.status)}</div></div>
        <div class="attention-note">${esc(i.notes || '')}</div>
      </div>
    `).join('');
  }
}

function renderList(list){
  $('result-count').textContent = `${list.length} resultado(s)`;
  if (!list.length) {
    $('list-main-body').innerHTML = '<tr><td colspan="6" style="color:var(--muted)">Nenhum sistema encontrado.</td></tr>';
    $('list-desc-body').innerHTML = '<tr><td colspan="5" style="color:var(--muted)">Nenhum sistema encontrado.</td></tr>';
    $('list-infra-body').innerHTML = '<tr><td colspan="7" style="color:var(--muted)">Nenhum sistema encontrado.</td></tr>';
    $('list-db-body').innerHTML = '<tr><td colspan="11" style="color:var(--muted)">Nenhuma base de dados encontrada.</td></tr>';
    $('list-support-body').innerHTML = '<tr><td colspan="8" style="color:var(--muted)">Nenhum contato cadastrado.</td></tr>';
    $('list-cards').innerHTML = '<div class="list-mobile-card"><div class="list-mobile-value" style="color:var(--muted)">Nenhum sistema encontrado.</div></div>';
    return;
  }

  $('list-main-body').innerHTML = list.map((i) => `
    <tr onclick="openDetail(${i.id})">
      <td><div class="list-name">${esc(i.name)}</div></td>
      <td>${esc(i.system_name || '-')}</td>
      <td>${esc(i.version || '-')}</td>
      <td>${esc(i.category || '-')}</td>
      <td class="crit-${critKind(i.criticality)}">${esc(i.criticality || '-')}</td>
      <td>${esc(i.notes || '-')}</td>
    </tr>
  `).join('');

  $('list-desc-body').innerHTML = list.map((i) => `
    <tr onclick="openDetail(${i.id})">
      <td><div class="list-name">${esc(i.name)}</div></td>
      <td>${linkHtml(i.url)}</td>
      <td>${esc(i.description || '-')}</td>
      <td>${badge(i.status)}</td>
      <td onclick="event.stopPropagation()"><div class="actions"><button class="act del" onclick="archiveSystem(${i.id})">&#128230;</button></div></td>
    </tr>
  `).join('');

  $('list-infra-body').innerHTML = list.map((i) => `
    <tr onclick="openDetail(${i.id})">
      <td><div class="list-name">${esc(i.name)}</div></td>
      <td>${linkHtml(i.url_homolog)}</td>
      <td>${esc(vmName(i, false))}</td>
      <td>${esc(vmIp(i, false))}</td>
      <td>${esc(vmName(i, true))}</td>
      <td>${esc(vmIp(i, true))}</td>
      <td>${(i.tech || []).map((t) => `<span class="tag">${esc(t)}</span>`).join('')}</td>
    </tr>
  `).join('');

  const systemsById = new Map(list.map((i) => [Number(i.id), i]));
  const systemIds = new Set(systemsById.keys());
  const dbList = App.databases
    .filter((d) => systemIds.has(Number(d.system_id)))
    .sort((a,b) => String(a.db_name || '').localeCompare(String(b.db_name || '')));

  if (!dbList.length) {
    $('list-db-body').innerHTML = '<tr><td colspan="11" style="color:var(--muted)">Nenhuma base de dados encontrada para os filtros aplicados.</td></tr>';
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
        <td>${esc(d.db_engine || '-')}</td>
        <td>${esc(d.db_engine_version || '-')}</td>
        <td>${esc(d.vm_name || '-')}</td>
        <td>${esc(d.vm_ip || '-')}</td>
        <td>${esc(d.vm_homolog_name || '-')}</td>
        <td>${esc(d.vm_homolog_ip || '-')}</td>
        <td>${esc(d.db_engine_version_homolog || '-')}</td>
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
        <div class="list-mobile-item"><span class="list-mobile-label">Responsavel Tecnico</span><span class="list-mobile-value">${esc(i.owner || '-')}</span></div>
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
        <button class="act del" onclick="archiveSystem(${i.id})">&#128230;</button>
      </div>
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
    const dbs = systemDatabases(i.id)
      .sort((a,b)=>String(a.db_name || '').localeCompare(String(b.db_name || '')));
    const techMarkup = (i.tech || []).length
      ? (i.tech || []).map((t) => `<span class="tag">${esc(t)}</span>`).join('')
      : '<span class="system-info-empty">Sem tecnologias cadastradas.</span>';

    const dbMarkup = dbs.length
      ? dbs.map((d) => {
        const engine = `${d.db_engine || '-'}${d.db_engine_version ? ` ${d.db_engine_version}` : ''}`;
        const engineHml = `${d.db_engine || '-'}${d.db_engine_version_homolog ? ` ${d.db_engine_version_homolog}` : ''}`;
        return `
          <div class="system-db-item">
            <div class="system-db-name">${esc(d.db_name || '-')}</div>
            <div class="system-db-meta">Usuario: ${esc(d.db_user || '-')} | SGBD Producao: ${esc(engine)}</div>
            <div class="system-db-meta">VM Producao: ${esc(d.vm_name || '-')} (${esc(d.vm_ip || '-')})</div>
            <div class="system-db-meta">VM Homologacao: ${esc(d.vm_homolog_name || '-')} (${esc(d.vm_homolog_ip || '-')}) | SGBD Homologacao: ${esc(engineHml)}</div>
            ${d.notes ? `<div class="system-db-note">Obs: ${esc(d.notes)}</div>` : ''}
          </div>
        `;
      }).join('')
      : '<div class="system-info-empty">Sem bases de dados vinculadas.</div>';

    return `
      <article class="system-info-card" onclick="openDetail(${i.id})">
        <div class="system-info-head">
          <div>
            <div class="system-info-name">${esc(i.name || '-')}</div>
            <div class="system-info-sub">${esc(i.system_name || '-')} | Versao ${esc(i.version || '-')}</div>
          </div>
          ${badge(i.status)}
        </div>

        <section class="system-info-section">
          <div class="system-info-title">Informacoes Tecnicas</div>
          <div class="system-info-grid">
            <div class="system-info-field"><span>Categoria</span><strong>${esc(i.category || '-')}</strong></div>
            <div class="system-info-field"><span>Criticidade</span><strong class="crit-${critKind(i.criticality)}">${esc(i.criticality || '-')}</strong></div>
            <div class="system-info-field"><span>Observacoes</span><strong>${esc(i.notes || '-')}</strong></div>
          </div>
        </section>

        <section class="system-info-section">
          <div class="system-info-title">Descricao e Infraestrutura</div>
          <div class="system-info-grid system-info-grid-wide">
            <div class="system-info-field system-info-field-full"><span>Descricao</span><strong class="system-info-text">${esc(i.description || '-')}</strong></div>
            <div class="system-info-field"><span>URL</span><div class="system-info-link">${linkHtml(i.url)}</div></div>
            <div class="system-info-field"><span>URL Homologacao</span><div class="system-info-link">${linkHtml(i.url_homolog)}</div></div>
            <div class="system-info-field"><span>VM Producao</span><strong>${esc(vmName(i, false))} (${esc(vmIp(i, false))})</strong></div>
            <div class="system-info-field"><span>VM Homologacao</span><strong>${esc(vmName(i, true))} (${esc(vmIp(i, true))})</strong></div>
          </div>
          <div class="tags">${techMarkup}</div>
        </section>

        <section class="system-info-section">
          <div class="system-info-title">Bases de Dados</div>
          <div class="system-db-list">${dbMarkup}</div>
        </section>

        <section class="system-info-section">
          <div class="system-info-title">Contatos e Suporte</div>
          <div class="system-info-grid">
            <div class="system-info-field"><span>Responsavel Tecnico</span><strong>${esc(i.owner || '-')}</strong></div>
            <div class="system-info-field"><span>Setor Responsavel</span><strong>${esc(i.responsible_sector || '-')}</strong></div>
            <div class="system-info-field"><span>Coordenador Responsavel</span><strong>${esc(i.responsible_coordinator || '-')}</strong></div>
            <div class="system-info-field"><span>Ramal</span><strong>${esc(i.extension_number || '-')}</strong></div>
            <div class="system-info-field"><span>Email</span><strong>${esc(i.email || '-')}</strong></div>
            <div class="system-info-field"><span>Suporte</span><strong>${esc(i.support || '-')}</strong></div>
            <div class="system-info-field"><span>Contato Suporte</span><strong>${esc(i.support_contact || '-')}</strong></div>
          </div>
        </section>

        <div class="system-info-actions" onclick="event.stopPropagation()">
          <button class="act del" onclick="archiveSystem(${i.id})">&#128230;</button>
        </div>
      </article>
    `;
  }).join('');
}

function vmUsage(vmId){
  const prod = App.items.filter((s) => Number(s.vm_id) === Number(vmId));
  const hml = App.items.filter((s) => Number(s.vm_homolog_id) === Number(vmId));
  const uniq = new Set([...prod.map((s)=>Number(s.id)), ...hml.map((s)=>Number(s.id))]);
  return { prod, hml, total: uniq.size };
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
  const list = [...App.databases].sort((a,b)=>String(a.db_name || '').localeCompare(String(b.db_name || '')));
  if (!list.length) {
    $('db-body').innerHTML = '<tr><td colspan="12" style="color:var(--muted)">Nenhuma base de dados cadastrada.</td></tr>';
    $('db-cards').innerHTML = '<div class="db-mobile-card"><div class="db-mobile-value" style="color:var(--muted)">Nenhuma base de dados cadastrada.</div></div>';
    return;
  }

  $('db-body').innerHTML = list.map((d) => `
    <tr>
      <td>${esc(d.system_name || '-')}</td>
      <td>${esc(d.db_name || '-')}</td>
      <td>${esc(d.db_user || '-')}</td>
      <td>${esc(d.db_engine || '-')}</td>
      <td>${esc(d.db_engine_version || '-')}</td>
      <td>${esc(d.vm_name || '-')}</td>
      <td>${esc(d.vm_ip || '-')}</td>
      <td>${esc(d.vm_homolog_name || '-')}</td>
      <td>${esc(d.vm_homolog_ip || '-')}</td>
      <td>${esc(d.db_engine_version_homolog || '-')}</td>
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
        <div class="db-mobile-item"><span class="db-mobile-label">Usuario do Banco</span><span class="db-mobile-value">${esc(d.db_user || '-')}</span></div>
        <div class="db-mobile-item"><span class="db-mobile-label">Maquina</span><span class="db-mobile-value">${esc(d.vm_name || '-')}</span></div>
        <div class="db-mobile-item"><span class="db-mobile-label">VM Homologacao</span><span class="db-mobile-value">${esc(d.vm_homolog_name || '-')}</span></div>
        <div class="db-mobile-item"><span class="db-mobile-label">Versao SGBD</span><span class="db-mobile-value">${esc(d.db_engine_version || '-')}</span></div>
        <div class="db-mobile-item"><span class="db-mobile-label">Versao SGBD Homologacao</span><span class="db-mobile-value">${esc(d.db_engine_version_homolog || '-')}</span></div>
        <div class="db-mobile-item"><span class="db-mobile-label">IP</span><span class="db-mobile-value">${esc(d.vm_ip || '-')}</span></div>
        <div class="db-mobile-item"><span class="db-mobile-label">IP Homologacao</span><span class="db-mobile-value">${esc(d.vm_homolog_ip || '-')}</span></div>
        <div class="db-mobile-item"><span class="db-mobile-label">Observacoes</span><span class="db-mobile-value">${esc(d.notes || '-')}</span></div>
      </div>
      <div class="db-mobile-actions">
        <button class="act" onclick="openDbFormById(${d.id})">&#9998;</button>
        <button class="act del" onclick="deleteDb(${d.id})">&#128465;</button>
      </div>
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

  const rows = systems.flatMap((i) => ([
    {
      id: Number(i.id),
      url: i.url || '',
      ip: vmIp(i, false)
    },
    {
      id: Number(i.id),
      url: i.url_homolog || '',
      ip: vmIp(i, true)
    }
  ])).filter((r) => {
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
            const specs = [
              vm.vcpus ? `${vm.vcpus} vCPU` : '',
              vm.ram ? `RAM ${vm.ram}` : '',
              vm.disk ? `Disco ${vm.disk}` : ''
            ].filter(Boolean);
            const systemsLinked = [...new Set([
              ...use.prod.map((s) => s.name),
              ...use.hml.map((s) => s.name)
            ])];
            const dbRows = dbs.map((d) => {
              const version = String(d.vm_version || '').trim();
              const user = String(d.db_user || '').trim();
              const engineLabel = `${d.db_engine || '-'}${version ? ` ${version}` : ''}`;
              return {
                dbName: d.db_name || '-',
                engine: engineLabel || '-',
                user: user || '-',
                system: d.system_name || '-'
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
                      <tr><th>Base</th><th>SGBD</th><th>Usuario</th><th>Sistema</th></tr>
                    </thead>
                    <tbody>
                      ${dbRows.map((r) => `
                        <tr>
                          <td>${esc(r.dbName)}</td>
                          <td>${esc(r.engine)}</td>
                          <td>${esc(r.user)}</td>
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

    const relationHeader = category === 'Producao'
      ? 'Sistemas em Producao'
      : category === 'Homologacao'
        ? 'Sistemas em Homologacao'
        : 'Sistemas Vinculados';
    const relationLabelCard = category === 'Producao'
      ? 'Producao'
      : category === 'Homologacao'
        ? 'Homologacao'
        : 'Sistemas';
    const typeBlocks = ['Sistemas','SGBD'].map((type) => {
      const typeVms = vms.filter((vm) => vmTypeLabel(vm) === type);
      if (!typeVms.length) return '';

      const rows = typeVms.map((vm) => {
        const use = vmUsage(vm.id);
        const dbs = vmDatabases(vm.id);
        const tech = vmTechList(vm);
        const specs = [vm.vcpus ? `${vm.vcpus} vCPU` : '', vm.ram || '', vm.disk || ''].filter(Boolean).join(' | ');
        const relationCount = category === 'Producao'
          ? use.prod.length
          : category === 'Homologacao'
            ? use.hml.length
            : use.total;
        const metricClass = type === 'SGBD' ? 'vm-db-col' : 'vm-rel-col';
        const metricValue = type === 'SGBD' ? dbs.length : relationCount;
        return `
          <tr>
            <td class="vm-name-col">${esc(vm.name)}</td>
            <td class="vm-ip-col">${esc(vm.ip || '-')}</td>
            <td class="vm-os-col">${esc(vm.os_name || '-')}</td>
            <td class="vm-res-col">${esc(specs || '-')}</td>
            <td class="vm-tech-col">${tech.length ? `<div class="vm-tech-tags">${tech.map((t)=>`<span class="tag">${esc(t)}</span>`).join('')}</div>` : '-'}</td>
            <td class="${metricClass}">${metricValue}</td>
            <td class="vm-actions-col"><div class="actions"><button class="act" onclick="openVmFormById(${vm.id})">&#9998;</button><button class="act del" onclick="archiveVm(${vm.id})">&#128230;</button></div></td>
          </tr>
        `;
      }).join('');

      const cards = typeVms.map((vm) => {
        const use = vmUsage(vm.id);
        const dbs = vmDatabases(vm.id);
        const tech = vmTechList(vm);
        const specs = [vm.vcpus ? `${vm.vcpus} vCPU` : '', vm.ram || '', vm.disk || ''].filter(Boolean);
        const relationCount = category === 'Producao'
          ? use.prod.length
          : category === 'Homologacao'
            ? use.hml.length
            : use.total;
        return `
          <div class="vm-mobile-card">
            <div class="vm-mobile-title">${esc(vm.name)}</div>
            <div class="vm-mobile-ip">${esc(vm.ip || '-')}</div>
            ${vm.os_name ? `<div class="vm-mobile-ip">SO: ${esc(vm.os_name)}</div>` : ''}
            ${specs.length ? `<div class="tags">${specs.map((s)=>`<span class="tag">${esc(s)}</span>`).join('')}</div>` : ''}
            ${tech.length ? `<div class="tags">${tech.map((t)=>`<span class="tag">${esc(t)}</span>`).join('')}</div>` : ''}
            <div class="vm-mobile-stats">
              <div class="vm-mobile-stat"><div class="vm-mobile-stat-label">${relationLabelCard}</div><div class="vm-mobile-stat-value">${relationCount}</div></div>
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

      return `
        <div class="vm-type-group">
          <div class="vm-type-title">${esc(type)}</div>
          <div class="table-wrap vm-desktop-table">
            <table style="min-width:1240px">
              <thead><tr><th class="vm-name-col">Nome da Maquina</th><th class="vm-ip-col">IP</th><th class="vm-os-col">Sistema Operacional</th><th class="vm-res-col">Recursos (vCPU | RAM | Disco)</th><th class="vm-tech-col">Tecnologias / Versoes</th><th class="${type === 'SGBD' ? 'vm-db-col' : 'vm-rel-col'}">${type === 'SGBD' ? 'Bases de Dados' : esc(relationHeader)}</th><th class="vm-actions-col">Acoes</th></tr></thead>
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

function shortLabel(value, max=36){
  const s = String(value || '-').trim() || '-';
  return s.length > max ? `${s.slice(0, max - 3)}...` : s;
}

function wrapLabel(value, max=70){
  const text = String(value || '').trim();
  if (!text) return ['-'];
  const tokens = text.split(/\s+/).filter(Boolean);
  if (!tokens.length) return ['-'];

  const lines = [];
  let current = '';

  tokens.forEach((token) => {
    if (token.length > max) {
      if (current) {
        lines.push(current);
        current = '';
      }
      let rest = token;
      while (rest.length > max) {
        lines.push(rest.slice(0, max));
        rest = rest.slice(max);
      }
      current = rest;
      return;
    }

    const candidate = current ? `${current} ${token}` : token;
    if (candidate.length <= max) {
      current = candidate;
    } else {
      lines.push(current);
      current = token;
    }
  });

  if (current) lines.push(current);
  return lines;
}

function estimateTextWidth(value, fontSize=12, mono=false){
  const text = String(value || '');
  const factor = mono ? 0.62 : 0.56;
  return Math.ceil(text.length * fontSize * factor);
}

function elbowPath(x1, y1, x2, y2, viaX=null){
  const dir = x2 >= x1 ? 1 : -1;
  const span = Math.abs(x2 - x1);
  const autoElbow = x1 + (Math.max(26, Math.min(120, span * 0.44)) * dir);
  const elbow = Number.isFinite(viaX) ? Number(viaX) : autoElbow;
  return `M ${x1} ${y1} L ${elbow} ${y1} L ${elbow} ${y2} L ${x2} ${y2}`;
}

function dbEngineLabel(db, versionOverride=''){
  const engine = String(db?.db_engine || '').trim() || 'SGBD nao informado';
  const version = String(versionOverride || db?.vm_version || db?.db_engine_version || '').trim();
  return version ? `${engine} ${version}` : engine;
}

function groupVmDatabases(vmId){
  const vmDbs = vmDatabases(vmId)
    .sort((a, b) => {
      const ag = dbEngineLabel(a, a.vm_version);
      const bg = dbEngineLabel(b, b.vm_version);
      if (ag !== bg) return ag.localeCompare(bg);
      return String(a.db_name || '').localeCompare(String(b.db_name || ''));
    });

  const groups = new Map();
  vmDbs.forEach((d) => {
    const key = dbEngineLabel(d, d.vm_version);
    if (!groups.has(key)) groups.set(key, []);
    groups.get(key).push(d);
  });

  return [...groups.entries()].map(([label, items]) => ({ label, items }));
}

function onDiagramSystemClick(ev, systemId){
  if (ev) ev.stopPropagation();
  const sid = Number(systemId || 0);
  if (!sid) return;
  App.diagramFocusSystemId = Number(App.diagramFocusSystemId) === sid ? 0 : sid;
  renderDiagram();
}

function clearDiagramFocus(){
  App.diagramFocusSystemId = 0;
  renderDiagram();
}

function renderDiagram(){
  const wrap = $('diagram-wrap');
  if (!wrap) return;
  const clearBtn = $('diagram-clear-btn');
  const focusInfo = $('diagram-focus-info');

  const systems = [...App.items].sort((a,b)=>String(a.name || '').localeCompare(String(b.name || '')));
  const dbs = [...App.databases].sort((a,b)=>String(a.db_name || '').localeCompare(String(b.db_name || '')));
  const requestedFocusId = Number(App.diagramFocusSystemId || 0);
  const focusedSystemId = systems.some((s)=>Number(s.id) === requestedFocusId) ? requestedFocusId : 0;
  if (requestedFocusId && !focusedSystemId) App.diagramFocusSystemId = 0;
  const focusedSystem = focusedSystemId ? systems.find((s)=>Number(s.id) === focusedSystemId) : null;

  if (!systems.length && !dbs.length) {
    if (clearBtn) clearBtn.style.display = 'none';
    if (focusInfo) focusInfo.textContent = '';
    wrap.innerHTML = '<div class="vm-report-empty">Nenhum dado cadastrado para gerar o diagrama.</div>';
    return;
  }

  if (clearBtn) clearBtn.style.display = focusedSystemId ? 'inline-flex' : 'none';
  if (focusInfo) {
    focusInfo.textContent = focusedSystem
      ? `Filtro ativo: ${focusedSystem.name || '-'}`
      : 'Dica: clique no sistema para mostrar somente as ligacoes dele.';
  }

  const top = 54;
  const colSystem = {
    x: 40,
    h: 96,
    gap: 14,
    minW: 360,
    maxW: 620,
    pad: 12
  };
  const colDb = {
    gap: 8,
    rowHMin: 26,
    minW: 360,
    maxW: 700,
    padX: 10,
    padY: 8
  };
  const colGap = 86;

  const systemRows = systems.map((system, idx) => {
    const line1 = shortLabel(system.name || '-', 40);
    const line2 = shortLabel([system.system_name || '-', system.version || '-'].join(' | '), 56);
    const line3 = shortLabel(`Prod: ${vmName(system, false)} (${vmIp(system, false)})`, 64);
    const line4 = shortLabel(`Hml: ${vmName(system, true)} (${vmIp(system, true)})`, 64);
    const textW = Math.max(
      estimateTextWidth(line1, 15, false),
      estimateTextWidth(line2, 12, true),
      estimateTextWidth(line3, 12, true),
      estimateTextWidth(line4, 12, true)
    );
    const w = Math.max(colSystem.minW, Math.min(colSystem.maxW, textW + (colSystem.pad * 2)));
    return {
      system,
      line1,
      line2,
      line3,
      line4,
      w,
      y: top + (idx * (colSystem.h + colSystem.gap))
    };
  });

  const systemMaxRight = systemRows.length
    ? Math.max(...systemRows.map((row) => colSystem.x + row.w))
    : (colSystem.x + colSystem.minW);
  const dbX = systemMaxRight + colGap;

  const dbRows = dbs
    .filter((d) => !focusedSystemId || Number(d.system_id) === focusedSystemId)
    .sort((a,b) => String(a.db_name || '').localeCompare(String(b.db_name || '')))
    .map((db) => {
      const user = String(db.db_user || '').trim();
      const version = String(db.db_engine_version || '').trim();
      const engine = String(db.db_engine || '-').trim() || '-';
      const vmProdName = String(db.vm_name || '').trim();
      const vmProdIp = String(db.vm_ip || '').trim();
      const vmHmlName = String(db.vm_homolog_name || '').trim();
      const vmHmlIp = String(db.vm_homolog_ip || '').trim();
      const prodHost = vmProdName || vmProdIp ? `${vmProdName || '-'} (${vmProdIp || '-'})` : '';
      const hmlHost = vmHmlName || vmHmlIp ? `${vmHmlName || '-'} (${vmHmlIp || '-'})` : '';

      const parts = [
        `${db.db_name || '-'}${user ? ` [${user}]` : ''} [${engine}${version ? ` ${version}` : ''}]`,
        prodHost ? `VM: ${prodHost}` : '',
        hmlHost ? `Hml: ${hmlHost}` : ''
      ].filter(Boolean);

      let lines = parts.flatMap((part) => wrapLabel(part, 72));
      if (lines.length > 5) {
        lines = lines.slice(0, 5);
        lines[4] = `${shortLabel(lines[4], 69)}...`;
      }

      const textW = Math.max(...lines.map((line) => estimateTextWidth(line, 11, true)));
      const w = Math.max(colDb.minW, Math.min(colDb.maxW, textW + (colDb.padX * 2) + 4));
      const rowH = Math.max(colDb.rowHMin, (colDb.padY * 2) + (lines.length * 13));
      return { db, lines, rowH, w };
    });

  let dbCursorY = top + 6;
  dbRows.forEach((row) => {
    row.y = dbCursorY;
    dbCursorY += row.rowH + colDb.gap;
  });

  const dbMaxRight = dbRows.length
    ? Math.max(...dbRows.map((row) => dbX + row.w))
    : (dbX + colDb.minW);
  const width = Math.max(1240, dbMaxRight + 30);

  const dbAnchorById = new Map();
  dbRows.forEach((row) => {
    dbAnchorById.set(Number(row.db.id), { x: dbX + 10, y: row.y + (row.rowH / 2) });
  });

  const height = Math.max(
    top + (systemRows.length * (colSystem.h + colSystem.gap)) + 24,
    dbCursorY + 24,
    420
  );

  const railX = systemMaxRight + 24;
  const systemAnchorById = new Map();
  systemRows.forEach((row) => {
    systemAnchorById.set(Number(row.system.id), {
      x: colSystem.x + row.w - 8,
      y: row.y + (colSystem.h / 2)
    });
  });

  const edges = dbRows.map((row) => {
    const sid = Number(row.db.system_id || 0);
    const src = systemAnchorById.get(sid);
    const dst = dbAnchorById.get(Number(row.db.id || 0));
    if (!src || !dst) return '';
    return `<path class="diagram-edge rel-db" d="${elbowPath(src.x, src.y, dst.x, dst.y, railX)}"></path>`;
  }).filter(Boolean).join('');

  const systemNodes = systemRows.map((row) => {
    const s = row.system;
    const sid = Number(s.id);
    const stateClass = focusedSystemId
      ? (sid === focusedSystemId ? ' selected' : ' dimmed')
      : '';
    const prodHost = `${vmName(s, false)} (${vmIp(s, false)})`;
    const hmlHost = `${vmName(s, true)} (${vmIp(s, true)})`;
    const line1 = row.line1;
    const line2 = row.line2;
    const line3 = row.line3;
    const line4 = row.line4;
    return `
      <g class="diagram-node node-system${stateClass}" onclick="onDiagramSystemClick(event, ${sid})" ondblclick="openDetail(${sid})">
        <rect x="${colSystem.x}" y="${row.y}" width="${row.w}" height="${colSystem.h}" rx="6" ry="6"></rect>
        <text x="${colSystem.x + 12}" y="${row.y + 24}" class="diagram-node-title">${esc(line1)}</text>
        <text x="${colSystem.x + 12}" y="${row.y + 44}" class="diagram-node-sub">${esc(line2)}</text>
        <text x="${colSystem.x + 12}" y="${row.y + 64}" class="diagram-node-sub subtle">${esc(line3)}</text>
        <text x="${colSystem.x + 12}" y="${row.y + 82}" class="diagram-node-sub subtle">${esc(line4)}</text>
      </g>
    `;
  }).join('');

  const dbNodes = dbRows.map((row) => {
    const d = row.db;
    const sid = Number(d.system_id || 0);
    const rowClass = focusedSystemId && sid !== focusedSystemId ? ' dimmed' : '';
    const textY = row.y + 16;
    const tspans = row.lines.map((line, idx) => `<tspan x="${dbX + 10}" dy="${idx === 0 ? 0 : 13}">${esc(line)}</tspan>`).join('');
    return `
      <g class="diagram-db-row${rowClass}">
        <rect x="${dbX}" y="${row.y}" width="${row.w}" height="${row.rowH}" rx="4" ry="4" class="diagram-db-item${rowClass}"></rect>
        <text x="${dbX + 10}" y="${textY}" class="diagram-db-item-text${rowClass}">${tspans}</text>
      </g>
    `;
  }).join('');

  wrap.innerHTML = `
    <svg class="diagram-svg" viewBox="0 0 ${width} ${height}" preserveAspectRatio="xMinYMin meet">
      <g class="diagram-links">${edges}</g>
      <g class="diagram-nodes">${systemNodes}${dbNodes}</g>
    </svg>
  `;
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

  if (App.view === 'diagrama') {
    $('result-count').textContent = '';
    renderDiagram();
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
  $('fsector').value = item?.responsible_sector || '';
  $('fcoordinator').value = item?.responsible_coordinator || '';
  $('fextension').value = item?.extension_number || '';
  $('femail').value = item?.email || '';
  $('fsupport').value = item?.support || '';
  $('fsupport_contact').value = item?.support_contact || '';
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
  openFormById(id);
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
    responsible_sector:$('fsector').value.trim(),
    responsible_coordinator:$('fcoordinator').value.trim(),
    extension_number:$('fextension').value.trim(),
    email:$('femail').value.trim(),
    support:$('fsupport').value.trim(),
    support_contact:$('fsupport_contact').value.trim(),
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
  $('fdbuser').value = item?.db_user || '';
  $('fdbengine').value = item?.db_engine || '';
  $('fdbenginever').value = item?.db_engine_version || '';
  $('fdbengineverh').value = item?.db_engine_version_homolog || '';
  $('fdbvmhip').value = item?.vm_homolog_ip || '';
  $('fdbnotes').value = item?.notes || '';

  populateDbSelects();
  $('fdbsystem').value = item?.system_id ? String(item.system_id) : '';
  $('fdbvm').value = item?.vm_id ? String(item.vm_id) : '';
  $('fdbvmh').value = item?.vm_homolog_id ? String(item.vm_homolog_id) : '';
  syncDbHomologIp();
  $('mdb').classList.remove('hidden');
}

async function saveDb(){
  const data = {
    id: $('fdbid').value || null,
    system_id: Number($('fdbsystem').value) || null,
    vm_id: Number($('fdbvm').value) || null,
    vm_homolog_id: Number($('fdbvmh').value) || null,
    db_name: $('fdbname').value.trim(),
    db_user: $('fdbuser').value.trim(),
    db_engine: $('fdbengine').value.trim(),
    db_engine_version: $('fdbenginever').value.trim(),
    db_engine_version_homolog: $('fdbengineverh').value.trim(),
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
  $('fvmtype').value = vmTypeLabel(vm);
  $('fvmos').value = vm?.os_name || '';
  $('fvmvcpus').value = vm?.vcpus || '';
  $('fvmram').value = vm?.ram || '';
  $('fvmdisk').value = vm?.disk || '';
  $('fvmtech').value = vmTechList(vm).join(', ');
  $('mvm').classList.remove('hidden');
}

async function saveVm(){
  const data = {
    id: $('fvmid').value || null,
    name: $('fvmname').value.trim(),
    ip: $('fvmip').value.trim(),
    vm_category: $('fvmcategory').value.trim(),
    vm_type: $('fvmtype').value.trim(),
    os_name: $('fvmos').value.trim(),
    vcpus: $('fvmvcpus').value.trim(),
    ram: $('fvmram').value.trim(),
    disk: $('fvmdisk').value.trim(),
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
