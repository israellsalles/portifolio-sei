const App = { items: [], vms: [], view: 'dashboard' };
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

function setView(view){
  App.view = view;
  ['dashboard','grid','lista','maquinas'].forEach((v) => {
    $('view-' + v).classList.toggle('active', v === view);
    $('tab-' + v).classList.toggle('active', v === view);
  });
  $('toolbar').style.display = (view === 'grid' || view === 'lista') ? 'flex' : 'none';
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

function renderGrid(list){
  $('result-count').textContent = `${list.length} resultado(s)`;
  if (!list.length) {
    $('grid').innerHTML = '<div class="card"><div class="card-name">Nenhum sistema</div><p class="card-desc">Ajuste filtros ou adicione um novo.</p></div>';
    return;
  }

  $('grid').innerHTML = list.map((i) => `
    <div class="card" onclick="openDetail(${i.id})">
      <div class="card-head">
        <div class="card-id"><div class="card-icon">${categoryIcon(i.category)}</div><div><div class="card-name">${esc(i.name)}</div><div class="card-cat">${esc(i.category || 'Outro')}</div></div></div>
        ${badge(i.status)}
      </div>
      <p class="card-desc">${esc(i.description || 'Sem descricao')}</p>
      <div class="tags">${(i.tech || []).map((t) => `<span class="tag">${esc(t)}</span>`).join('')}</div>
      <div class="card-foot"><span>Sistema: ${esc(i.system_name || '-')}</span><span>Versao: ${esc(i.version || '-')}</span></div>
      <div class="card-foot"><span>Responsavel: ${esc(i.owner || '-')}</span><span>Criticidade: <span class="crit-${critKind(i.criticality)}">${esc(i.criticality || '-')}</span></span></div>
      <div class="card-foot"><span>VM Producao: ${esc(vmName(i, false))}</span><span>IP: ${esc(vmIp(i, false))}</span></div>
      <div class="card-foot"><span>VM Homologacao: ${esc(vmName(i, true))}</span><span>IP Homologacao: ${esc(vmIp(i, true))}</span></div>
      <div class="card-foot"><span>URL: ${linkHtml(i.url)}</span><span>URL Homologacao: ${linkHtml(i.url_homolog)}</span></div>
      <div class="card-foot"><span>Observacoes: ${esc(i.notes || '-')}</span><span></span></div>
    </div>
  `).join('');
}

function renderList(list){
  $('result-count').textContent = `${list.length} resultado(s)`;
  if (!list.length) {
    $('list-body').innerHTML = '<tr><td colspan="17" style="color:var(--muted)">Nenhum sistema encontrado.</td></tr>';
    return;
  }

  $('list-body').innerHTML = list.map((i) => `
    <tr onclick="openDetail(${i.id})">
      <td><div class="list-name">${esc(i.name)}</div></td>
      <td>${esc(i.system_name || '-')}</td>
      <td>${esc(i.category || '-')}</td>
      <td>${badge(i.status)}</td>
      <td class="crit-${critKind(i.criticality)}">${esc(i.criticality || '-')}</td>
      <td>${esc(i.owner || '-')}</td>
      <td>${esc(i.version || '-')}</td>
      <td>${esc(vmName(i, false))}</td>
      <td>${esc(vmIp(i, false))}</td>
      <td>${esc(vmName(i, true))}</td>
      <td>${esc(vmIp(i, true))}</td>
      <td>${linkHtml(i.url)}</td>
      <td>${linkHtml(i.url_homolog)}</td>
      <td>${(i.tech || []).map((t) => `<span class="tag">${esc(t)}</span>`).join('')}</td>
      <td>${esc(i.description || '-')}</td>
      <td>${esc(i.notes || '-')}</td>
      <td onclick="event.stopPropagation()"><div class="actions"><button class="act" onclick="openFormById(${i.id})">&#9998;</button><button class="act del" onclick="delSystem(${i.id})">&#128465;</button></div></td>
    </tr>
  `).join('');
}

function vmUsage(vmId){
  const prod = App.items.filter((s) => Number(s.vm_id) === Number(vmId));
  const hml = App.items.filter((s) => Number(s.vm_homolog_id) === Number(vmId));
  const uniq = new Set([...prod.map((s)=>Number(s.id)), ...hml.map((s)=>Number(s.id))]);
  return { prod, hml, total: uniq.size };
}

function renderVmReport(){
  const box = $('vm-report');
  if (!App.vms.length) {
    box.innerHTML = '<div class="vm-report-empty">Nenhuma maquina cadastrada.</div>';
    return;
  }

  box.innerHTML = App.vms.map((vm) => {
    const use = vmUsage(vm.id);
    const lines = [];
    use.prod.forEach((s) => lines.push(`${s.name} (producao)`));
    use.hml.forEach((s) => lines.push(`${s.name} (homologacao)`));
    return `
      <div class="vm-report-item">
        <div class="vm-report-title">${esc(vm.name)}</div>
        <div class="vm-report-sub">IP ${esc(vm.ip || '-')} • ${use.total} sistema(s)</div>
        ${lines.length ? `<ul class="vm-report-list">${lines.map((x)=>`<li>${esc(x)}</li>`).join('')}</ul>` : '<div class="vm-report-empty">Sem sistemas vinculados.</div>'}
      </div>
    `;
  }).join('');
}

function renderMachines(){
  if (!App.vms.length) {
    $('vm-body').innerHTML = '<tr><td colspan="6" style="color:var(--muted)">Nenhuma maquina cadastrada.</td></tr>';
    renderVmReport();
    return;
  }

  $('vm-body').innerHTML = App.vms.map((vm) => {
    const use = vmUsage(vm.id);
    return `
      <tr>
        <td>${esc(vm.name)}</td>
        <td>${esc(vm.ip || '-')}</td>
        <td>${use.prod.length}</td>
        <td>${use.hml.length}</td>
        <td>${use.total}</td>
        <td><div class="actions"><button class="act" onclick="openVmFormById(${vm.id})">&#9998;</button><button class="act del" onclick="delVm(${vm.id})">&#128465;</button></div></td>
      </tr>
    `;
  }).join('');

  renderVmReport();
}

function renderCurrent(){
  const active = App.items.filter((i)=>statusKind(i.status)==='active').length;
  $('count').innerHTML = `${App.items.length} sistemas &#8226; ${active} ativos`;

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

  const list = filteredItems();
  if (App.view === 'grid') renderGrid(list);
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
  $('ddel').onclick = () => delSystem(i.id);
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

    if(data.id) App.items = App.items.map((x)=>Number(x.id)===Number(data.id) ? r.data : x);
    else App.items.push(r.data);

    closeModal('mform');
    populateFilters();
    renderCurrent();
    toast(data.id ? 'Sistema atualizado' : 'Sistema adicionado');
  }catch(e){
    toast('Erro ao salvar: ' + (e.message || '?'), true);
  }
}

async function delSystem(id){
  const item = App.items.find((x)=>Number(x.id)===Number(id));
  if(!confirm(`Excluir ${item?.name || 'sistema'}? Esta acao nao pode ser desfeita.`)) return;

  try{
    const r = await api('delete', {id});
    if(!r.ok) throw new Error(r.error || 'Erro ao excluir');

    App.items = App.items.filter((x)=>Number(x.id)!==Number(id));
    closeModal('mdetail');
    populateFilters();
    renderCurrent();
    toast('Sistema excluido');
  }catch(e){
    toast('Erro ao excluir: ' + (e.message || '?'), true);
  }
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
  $('mvm').classList.remove('hidden');
}

async function saveVm(){
  const data = {
    id: $('fvmid').value || null,
    name: $('fvmname').value.trim(),
    ip: $('fvmip').value.trim(),
  };

  if (!data.name || !data.ip) {
    toast('Informe nome e IP da maquina.', true);
    return;
  }

  try {
    const r = await api('vm-save', data);
    if(!r.ok) throw new Error(r.error || 'Erro ao salvar maquina');

    if (data.id) App.vms = App.vms.map((x)=>Number(x.id)===Number(data.id) ? r.data : x);
    else App.vms.push(r.data);

    App.vms.sort((a,b)=>String(a.name || '').localeCompare(String(b.name || '')));
    closeModal('mvm');
    populateVmSelects();
    renderCurrent();
    toast(data.id ? 'Maquina atualizada' : 'Maquina cadastrada');
  } catch (e) {
    toast('Erro ao salvar maquina: ' + (e.message || '?'), true);
  }
}

async function delVm(id){
  const vm = App.vms.find((x)=>Number(x.id)===Number(id));
  if(!confirm(`Excluir maquina ${vm?.name || ''}?`)) return;

  try{
    const r = await api('vm-delete', {id});
    if(!r.ok) throw new Error(r.error || 'Erro ao excluir maquina');

    App.vms = App.vms.filter((x)=>Number(x.id)!==Number(id));
    populateVmSelects();
    renderCurrent();
    toast('Maquina excluida');
  } catch (e) {
    toast('Erro ao excluir maquina: ' + (e.message || '?'), true);
  }
}

async function boot(){
  try{
    const [systemsRes, vmRes] = await Promise.all([api('list'), api('vm-list')]);
    if(!systemsRes.ok) throw new Error(systemsRes.error || 'Erro ao carregar sistemas');
    if(!vmRes.ok) throw new Error(vmRes.error || 'Erro ao carregar maquinas');

    App.items = systemsRes.data || [];
    App.vms = vmRes.data || [];
    App.vms.sort((a,b)=>String(a.name || '').localeCompare(String(b.name || '')));

    $('loading').style.display = 'none';
    populateFilters();
    populateVmSelects();
    setView(App.view);
  }catch(e){
    $('loading').textContent = 'Erro: ' + (e.message || 'Falha ao carregar dados');
    toast('Erro ao carregar: ' + (e.message || '?'), true);
  }
}

boot();
