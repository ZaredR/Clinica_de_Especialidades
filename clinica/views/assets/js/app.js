// ═══════════════════════════════════════════════════
// CLÍNICA DE ESPECIALIDADES — SPA Frontend
// ═══════════════════════════════════════════════════

const API = 'http://localhost/Clinica_de_Especialidades/clinica/'; // Ajusta si XAMPP está en otro path
let currentUser = null;
let currentPage = 'dashboard';
const módulosPorRol = {
  'administrador': ['dashboard', 'medicos', 'pacientes', 'citas', 'consultas', 'medicamentos', 'pagos', 'reportes', 'catalogos'],
  'recepcionista': ['dashboard', 'pacientes', 'citas', 'medicamentos', 'pagos'],
  'medico':        ['dashboard', 'pacientes', 'citas', 'consultas', 'medicamentos'],
  'paciente':      ['dashboard', 'citas', 'consultas']
};

// ── Utilidades ──────────────────────────────────────────────────────

const $ = id => document.getElementById(id);
const loader = (show) => {
  $('loader').style.display = show ? 'flex' : 'none';
};

async function api(method, endpoint, body = null) {
  loader(true);
  try {
    const opts = {
      method,
      headers: {'Content-Type': 'application/json'},
      credentials: 'include',
    };
    if (body) opts.body = JSON.stringify(body);
    const res = await fetch(`${API}${endpoint}`, opts);
    const json = await res.json();
    if (!res.ok) throw new Error(json.message || 'Error del servidor');
    return json.data;
  } finally {
    loader(false);
  }
}

function fmt(val, type = 'text') {
  if (val === null || val === undefined || val === '') return '<span style="color:#aaa">—</span>';
  if (type === 'money') return '$' + Number(val).toLocaleString('es-MX', {minimumFractionDigits: 2});
  if (type === 'date') return new Date(val + 'T00:00:00').toLocaleDateString('es-MX',{day:'2-digit',month:'short',year:'numeric'});
  return val;
}

function badge(text, color = 'gray') {
  const map = {
    programada:'blue', confirmada:'green', cancelada:'red',
    completada:'green', reprogramada:'amber', pendiente:'amber',
    pagado:'green', Vigente:'green', Caducado:'red', 'Por caducar':'amber',
  };
  const c = map[text] || color;
  return `<span class="badge badge-${c}">${text}</span>`;
}

function showToast(msg, type = 'success') {
  const t = document.createElement('div');
  t.className = `alert alert-${type}`;
  t.style.cssText = 'position:fixed;top:80px;right:24px;z-index:999;min-width:280px;animation:fadeIn .3s';
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 3500);
}

// ── Modal ────────────────────────────────────────────────────────────

function openModal(title, bodyHtml, footerHtml = '') {
  $('modal-title').textContent = title;
  $('modal-body').innerHTML = bodyHtml;
  $('modal-footer').innerHTML = footerHtml;
  $('modal-overlay').classList.add('open');
}
function closeModal() {
  $('modal-overlay').classList.remove('open');
}
$('modal-close').onclick = closeModal;
$('modal-overlay').onclick = e => { if (e.target === $('modal-overlay')) closeModal(); };

// ── Autenticación ────────────────────────────────────────────────────

async function doLogin() {
  const username = $('inp-username').value.trim();
  const password = $('inp-password').value;
  const err = $('login-err');
  err.style.display = 'none';
  try {
    currentUser = await api('POST', '/auth/login', {username, password});
    showApp();
  } catch(e) {
    err.textContent = e.message;
    err.style.display = 'block';
  }
}

$('btn-login').onclick = doLogin;
$('inp-password').onkeydown = e => { if (e.key === 'Enter') doLogin(); };

$('btn-logout').onclick = async () => {
  await api('POST', '/auth/logout');
  currentUser = null;
  $('app').style.display = 'none';
  $('login-page').style.display = 'flex';
};

function showApp() {
  $('login-page').style.display = 'none';
  $('app').style.display = '';
  $('sidebar-username').textContent = currentUser.username;
  $('sidebar-rol').textContent = currentUser.rol;
  $('sidebar-avatar').textContent = currentUser.username[0].toUpperCase();

  // Filtrar menú lateral por rol
  const rolActual = currentUser.rol.toLowerCase();
  const permitidos = módulosPorRol[rolActual] || ['dashboard'];

  document.querySelectorAll('.nav-item[data-page]').forEach(item => {
    const pagina = item.dataset.page;
    if (permitidos.includes(pagina)) {
      item.style.display = 'flex'; // Mostrar si tiene permiso
    } else {
      item.style.display = 'none'; // Ocultar si está bloqueado por RBAC
    }
  });

  navigate('dashboard');
}

// ── Navegación ───────────────────────────────────────────────────────

document.querySelectorAll('.nav-item[data-page]').forEach(a => {
  a.onclick = () => navigate(a.dataset.page);
});

const pageTitles = {
  dashboard:'Dashboard', medicos:'Médicos', pacientes:'Pacientes',
  citas:'Agenda de Citas', consultas:'Consultas', medicamentos:'Inventario de Medicamentos',
  pagos:'Registro de Pagos', reportes:'Reportes', catalogos:'Catálogos del Sistema',
};

function navigate(page) {
  const rolActual = currentUser.rol.toLowerCase();
  const permitidos = módulosPorRol[rolActual] || ['dashboard'];
  
  if (!permitidos.includes(page)) {
    showToast('Acceso denegado a este módulo', 'danger');
    return;
  }
  currentPage = page;
  document.querySelectorAll('.nav-item').forEach(a => a.classList.remove('active'));
  document.querySelector(`.nav-item[data-page="${page}"]`)?.classList.add('active');
  $('page-title').textContent = pageTitles[page] || page;
  $('topbar-actions').innerHTML = '';
  const renders = {
    dashboard, medicos, pacientes, citas,
    consultas, medicamentos, pagos, reportes, catalogos,
  };
  (renders[page] || (() => {}))();
}

// ══════════════════════════════════════════════════════════════════════
// MÓDULO: Dashboard
// ══════════════════════════════════════════════════════════════════════

async function dashboard() {
  const content = $('content');
  content.innerHTML = '<div style="color:var(--text-muted);padding:20px;">Cargando...</div>';
  try {
    const [citasHoy, pacientes, medicos, pagos] = await Promise.all([
      api('GET', '/citas/hoy'),
      api('GET', '/pacientes'),
      api('GET', '/medicos'),
      api('GET', '/pagos'),
    ]);

    const ingresoHoy = pagos.filter(p => {
      const d = new Date(p.fecha);
      const hoy = new Date();
      return p.estado === 'pagado' && d.toDateString() === hoy.toDateString();
    }).reduce((s, p) => s + parseFloat(p.monto_total || 0), 0);

    content.innerHTML = `
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon si-blue">📅</div>
        <div class="stat-label">Citas Hoy</div>
        <div class="stat-value">${citasHoy.length}</div>
        <div class="stat-sub">${citasHoy.filter(c=>c.estado==='programada').length} pendientes</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon si-green">👥</div>
        <div class="stat-label">Pacientes</div>
        <div class="stat-value">${pacientes.length}</div>
        <div class="stat-sub">Total registrados</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon si-amber">🩺</div>
        <div class="stat-label">Médicos Activos</div>
        <div class="stat-value">${medicos.length}</div>
        <div class="stat-sub">En servicio</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon si-red">💰</div>
        <div class="stat-label">Ingresos Hoy</div>
        <div class="stat-value">${fmt(ingresoHoy,'money')}</div>
        <div class="stat-sub">Pagos cobrados</div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <span class="card-title">Citas de Hoy — ${new Date().toLocaleDateString('es-MX',{weekday:'long',day:'numeric',month:'long'})}</span>
      </div>
      <div class="card-body">
        ${citasHoy.length === 0
          ? `<div class="empty-state"><p>No hay citas programadas para hoy</p></div>`
          : `<div class="table-wrapper"><table>
            <thead><tr><th>Hora</th><th>Paciente</th><th>Médico</th><th>Especialidad</th><th>Estado</th></tr></thead>
            <tbody>${citasHoy.map(c=>`<tr>
              <td><strong>${c.hora}</strong></td>
              <td>${c.paciente}</td>
              <td>${c.medico}</td>
              <td>${c.especialidad}</td>
              <td>${badge(c.estado)}</td>
            </tr>`).join('')}</tbody>
          </table></div>`
        }
      </div>
    </div>`;
  } catch(e) {
    content.innerHTML = `<div class="alert alert-danger">${e.message}</div>`;
  }
}

// ══════════════════════════════════════════════════════════════════════
// MÓDULO: Médicos
// ══════════════════════════════════════════════════════════════════════

async function medicos() {
  $('topbar-actions').innerHTML = `<button class="btn btn-primary" id="btn-nuevo-medico">+ Nuevo Médico</button>`;
  $('btn-nuevo-medico').onclick = () => formMedico();

  const content = $('content');
  try {
    const [data, especialidades] = await Promise.all([
      api('GET', '/medicos'),
      api('GET', '/catalogos/especialidades'),
    ]);

    content.innerHTML = `
    <div class="card">
      <div class="card-header" style="margin-bottom:16px;">
        <span class="card-title">Médicos registrados (${data.length})</span>
        <div class="search-bar">
          <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
          <input type="text" id="search-medico" placeholder="Buscar médico...">
        </div>
      </div>
      <div class="table-wrapper">
        <table id="tbl-medicos">
          <thead><tr><th>Nombre</th><th>Especialidad</th><th>Cédula</th><th>Teléfono</th><th>Horario</th><th>Acciones</th></tr></thead>
          <tbody>${renderMedicos(data)}</tbody>
        </table>
      </div>
    </div>`;

    $('search-medico').oninput = async function() {
      const q = this.value.trim();
      const res = q.length > 1 ? await api('GET', `/medicos/buscar?q=${encodeURIComponent(q)}`) : data;
      document.querySelector('#tbl-medicos tbody').innerHTML = renderMedicos(res);
      attachMedicoActions();
    };
    attachMedicoActions();

    window._especialidades = especialidades;
  } catch(e) {
    content.innerHTML = `<div class="alert alert-danger">${e.message}</div>`;
  }
}

function renderMedicos(data) {
  if (!data.length) return `<tr><td colspan="6"><div class="empty-state"><p>Sin médicos registrados</p></div></td></tr>`;
  return data.map(m => `<tr>
    <td><strong>${m.nombre} ${m.apellidos}</strong></td>
    <td>${badge(m.especialidad,'blue')}</td>
    <td>${fmt(m.cedula)}</td>
    <td>${fmt(m.telefono)}</td>
    <td>${fmt(m.horario_inicio)} — ${fmt(m.horario_salida)}</td>
    <td>
      <button class="btn btn-secondary btn-sm edit-medico" data-id="${m.medico_id}">Editar</button>
      <button class="btn btn-danger btn-sm del-medico" data-id="${m.medico_id}" style="margin-left:6px">Eliminar</button>
    </td>
  </tr>`).join('');
}

function attachMedicoActions() {
  document.querySelectorAll('.edit-medico').forEach(b => b.onclick = () => formMedico(b.dataset.id));
  document.querySelectorAll('.del-medico').forEach(b => b.onclick = async () => {
    if (!confirm('¿Eliminar este médico?')) return;
    await api('DELETE', `/medicos/${b.dataset.id}`);
    showToast('Médico eliminado');
    medicos();
  });
}

async function formMedico(id = null) {
  const esp = window._especialidades || await api('GET', '/catalogos/especialidades');
  let m = {};
  if (id) m = await api('GET', `/medicos/${id}`) || {};

  const espOpts = esp.map(e => `<option value="${e.especialidad_id}" ${m.especialidad_id==e.especialidad_id?'selected':''}>${e.especialidad}</option>`).join('');
  const isEdit = !!id;

  openModal(isEdit ? 'Editar Médico' : 'Nuevo Médico', `
  <div class="form-grid">
    <div class="form-group"><label>Nombre *</label><input id="fm-nombre" value="${m.nombre||''}"></div>
    <div class="form-group"><label>Apellidos *</label><input id="fm-apellidos" value="${m.apellidos||''}"></div>
    <div class="form-group"><label>Cédula *</label><input id="fm-cedula" value="${m.cedula||''}"></div>
    <div class="form-group"><label>Especialidad *</label><select id="fm-esp"><option value="">Seleccionar</option>${espOpts}</select></div>
    <div class="form-group"><label>Teléfono</label><input id="fm-tel" value="${m.telefono||''}"></div>
    <div class="form-group"><label>Correo</label><input id="fm-correo" type="email" value="${m.correo||''}"></div>
    <div class="form-group"><label>Fecha Nacimiento</label><input id="fm-fn" type="date" value="${m.fecha_nacimiento||''}"></div>
    <div class="form-group"><label>Horario Inicio</label><input id="fm-hi" type="time" value="${m.horario_inicio?.substring(0,5)||'08:00'}"></div>
    <div class="form-group"><label>Horario Salida</label><input id="fm-hs" type="time" value="${m.horario_salida?.substring(0,5)||'17:00'}"></div>
    ${!isEdit ? `
    <div class="form-group"><label>Username *</label><input id="fm-user" placeholder="usuario para login"></div>
    <div class="form-group"><label>Contraseña *</label><input id="fm-pass" type="password" placeholder="mínimo 8 caracteres"></div>
    ` : ''}
  </div>`,
  `<button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
   <button class="btn btn-primary" id="btn-save-medico">Guardar</button>`);

  $('btn-save-medico').onclick = async () => {
    const data = {
      nombre: $('fm-nombre').value, apellidos: $('fm-apellidos').value,
      cedula: $('fm-cedula').value, especialidad_id: $('fm-esp').value,
      telefono: $('fm-tel').value, correo: $('fm-correo').value,
      fecha_nacimiento: $('fm-fn').value,
      horario_inicio: $('fm-hi').value, horario_salida: $('fm-hs').value,
    };
    if (!isEdit) { data.username = $('fm-user').value; data.password = $('fm-pass').value; }
    try {
      if (isEdit) await api('PUT', `/medicos/${id}`, data);
      else await api('POST', '/medicos', data);
      closeModal();
      showToast(isEdit ? 'Médico actualizado' : 'Médico registrado');
      medicos();
    } catch(e) { showToast(e.message, 'danger'); }
  };
}

// ══════════════════════════════════════════════════════════════════════
// MÓDULO: Pacientes
// ══════════════════════════════════════════════════════════════════════

async function pacientes() {
  $('topbar-actions').innerHTML = `<button class="btn btn-primary" id="btn-nuevo-pac">+ Nuevo Paciente</button>`;
  $('btn-nuevo-pac').onclick = () => formPaciente();
  const content = $('content');
  try {
    const [data, sangre] = await Promise.all([
      api('GET', '/pacientes'),
      api('GET', '/catalogos/tipo-sangre'),
    ]);
    window._tipoSangre = sangre;

    content.innerHTML = `
    <div class="card">
      <div class="card-header" style="margin-bottom:16px;">
        <span class="card-title">Pacientes registrados (${data.length})</span>
        <div class="search-bar">
          <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
          <input type="text" id="search-pac" placeholder="Buscar paciente...">
        </div>
      </div>
      <div class="table-wrapper">
        <table id="tbl-pacs">
          <thead><tr><th>Nombre</th><th>Edad</th><th>Sexo</th><th>Teléfono</th><th>Correo</th><th>Acciones</th></tr></thead>
          <tbody>${renderPacientes(data)}</tbody>
        </table>
      </div>
    </div>`;

    $('search-pac').oninput = async function() {
      const q = this.value.trim();
      const res = q.length > 1 ? await api('GET', `/pacientes/buscar?q=${encodeURIComponent(q)}`) : data;
      document.querySelector('#tbl-pacs tbody').innerHTML = renderPacientes(res);
      attachPacActions();
    };
    attachPacActions();
  } catch(e) { content.innerHTML = `<div class="alert alert-danger">${e.message}</div>`; }
}

function renderPacientes(data) {
  if (!data.length) return `<tr><td colspan="6"><div class="empty-state"><p>Sin pacientes registrados</p></div></td></tr>`;
  return data.map(p => `<tr>
    <td><strong>${p.nombre} ${p.apellidos}</strong></td>
    <td>${p.edad} años</td>
    <td>${p.sexo === 'M' ? '♂ Masculino' : '♀ Femenino'}</td>
    <td>${fmt(p.telefono)}</td>
    <td>${fmt(p.correo)}</td>
    <td>
      <button class="btn btn-secondary btn-sm ver-pac" data-id="${p.paciente_id}">Ver</button>
      <button class="btn btn-secondary btn-sm edit-pac" data-id="${p.paciente_id}" style="margin-left:4px">Editar</button>
    </td>
  </tr>`).join('');
}

function attachPacActions() {
  document.querySelectorAll('.ver-pac').forEach(b => b.onclick = () => verPaciente(b.dataset.id));
  document.querySelectorAll('.edit-pac').forEach(b => b.onclick = () => formPaciente(b.dataset.id));
}

async function verPaciente(id) {
  const p = await api('GET', `/pacientes/${id}`);
  const hist = await api('GET', `/pacientes/${id}/historial`);
  openModal(`${p.nombre} ${p.apellidos}`, `
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px;">
      <div><small style="color:var(--text-muted)">Edad</small><p><strong>${p.edad} años</strong></p></div>
      <div><small style="color:var(--text-muted)">Sexo</small><p>${p.sexo==='M'?'Masculino':'Femenino'}</p></div>
      <div><small style="color:var(--text-muted)">Teléfono</small><p>${fmt(p.telefono)}</p></div>
      <div><small style="color:var(--text-muted)">Correo</small><p>${fmt(p.correo)}</p></div>
      <div><small style="color:var(--text-muted)">Tipo de Sangre</small><p>${fmt(p.tipo_sangre)}</p></div>
      <div><small style="color:var(--text-muted)">Peso / Altura</small><p>${fmt(p.peso_actual)} kg / ${fmt(p.altura)} m</p></div>
    </div>
    <h4 style="margin-bottom:12px;font-size:.9rem;">Historial de Citas</h4>
    ${hist.length === 0 ? '<p style="color:var(--text-muted);font-size:.85rem;">Sin citas previas</p>'
    : `<div class="table-wrapper"><table>
        <thead><tr><th>Fecha</th><th>Médico</th><th>Especialidad</th><th>Estado</th></tr></thead>
        <tbody>${hist.map(c=>`<tr>
          <td>${fmt(c.fecha,'date')} ${c.hora}</td>
          <td>${c.medico}</td><td>${c.especialidad}</td><td>${badge(c.estado)}</td>
        </tr>`).join('')}</tbody>
      </table></div>`}
  `);
}

async function formPaciente(id = null) {
  let p = {};
  if (id) p = await api('GET', `/pacientes/${id}`) || {};
  const sangre = window._tipoSangre || await api('GET', '/catalogos/tipo-sangre');
  const sOpts = sangre.map(s => `<option value="${s.id_tipo_sangre}" ${p.id_tipo_sangre==s.id_tipo_sangre?'selected':''}>${s.descripcion}</option>`).join('');
  const isEdit = !!id;

  openModal(isEdit ? 'Editar Paciente' : 'Nuevo Paciente', `
  <div class="form-grid">
    <div class="form-group"><label>Nombre *</label><input id="pp-nom" value="${p.nombre||''}"></div>
    <div class="form-group"><label>Apellidos *</label><input id="pp-ape" value="${p.apellidos||''}"></div>
    <div class="form-group"><label>Fecha Nacimiento *</label><input id="pp-fn" type="date" value="${p.fecha_nacimiento||''}"></div>
    <div class="form-group"><label>Sexo *</label>
      <select id="pp-sex">
        <option value="">Seleccionar</option>
        <option value="M" ${p.sexo==='M'?'selected':''}>Masculino</option>
        <option value="F" ${p.sexo==='F'?'selected':''}>Femenino</option>
      </select>
    </div>
    <div class="form-group"><label>Teléfono</label><input id="pp-tel" value="${p.telefono||''}"></div>
    <div class="form-group"><label>Correo</label><input id="pp-cor" type="email" value="${p.correo||''}"></div>
    <div class="form-group"><label>Tipo de Sangre</label><select id="pp-ts"><option value="">—</option>${sOpts}</select></div>
    <div class="form-group full"><label>Observaciones</label><textarea id="pp-obs">${p.observaciones_generales||''}</textarea></div>
  </div>`,
  `<button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
   <button class="btn btn-primary" id="btn-save-pac">Guardar</button>`);

  $('btn-save-pac').onclick = async () => {
    const data = {
      nombre:$('pp-nom').value, apellidos:$('pp-ape').value,
      fecha_nacimiento:$('pp-fn').value, sexo:$('pp-sex').value,
      telefono:$('pp-tel').value, correo:$('pp-cor').value,
      id_tipo_sangre:$('pp-ts').value||null,
      observaciones_generales:$('pp-obs').value,
    };
    try {
      if (isEdit) await api('PUT', `/pacientes/${id}`, data);
      else await api('POST', '/pacientes', data);
      closeModal(); showToast('Paciente guardado'); pacientes();
    } catch(e) { showToast(e.message,'danger'); }
  };
}

// ══════════════════════════════════════════════════════════════════════
// MÓDULO: Citas
// ══════════════════════════════════════════════════════════════════════

async function citas() {
  $('topbar-actions').innerHTML = `<button class="btn btn-primary" id="btn-nueva-cita">+ Nueva Cita</button>`;
  $('btn-nueva-cita').onclick = () => formCita();
  const content = $('content');
  try {
    const filtroFecha = new Date().toISOString().split('T')[0];
    const data = await api('GET', `/citas?fecha=${filtroFecha}`);

    content.innerHTML = `
    <div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;align-items:center;">
      <div class="form-group" style="margin:0;">
        <label style="font-size:.75rem;">Fecha</label>
        <input type="date" id="filtro-fecha" value="${filtroFecha}">
      </div>
      <div class="form-group" style="margin:0;">
        <label style="font-size:.75rem;">Estado</label>
        <select id="filtro-estado">
          <option value="">Todos</option>
          <option value="programada">Programadas</option>
          <option value="confirmada">Confirmadas</option>
          <option value="cancelada">Canceladas</option>
          <option value="completada">Completadas</option>
        </select>
      </div>
      <button class="btn btn-secondary" id="btn-filtrar-citas" style="align-self:flex-end">Filtrar</button>
    </div>
    <div class="card">
      <div class="card-body" id="citas-body">
        ${renderCitas(data)}
      </div>
    </div>`;

    $('btn-filtrar-citas').onclick = async () => {
      const params = new URLSearchParams();
      const f = $('filtro-fecha').value; const e = $('filtro-estado').value;
      if (f) params.append('fecha', f);
      if (e) params.append('estado', e);
      const res = await api('GET', `/citas?${params}`);
      $('citas-body').innerHTML = renderCitas(res);
      attachCitaActions();
    };
    attachCitaActions();
  } catch(e) { content.innerHTML = `<div class="alert alert-danger">${e.message}</div>`; }
}

function renderCitas(data) {
  if (!data.length) return `<div class="empty-state"><p>Sin citas en este período</p></div>`;
  return `<div class="table-wrapper"><table>
    <thead><tr><th>Fecha</th><th>Hora</th><th>Paciente</th><th>Médico</th><th>Especialidad</th><th>Motivo</th><th>Estado</th><th>Acciones</th></tr></thead>
    <tbody>${data.map(c=>`<tr>
      <td>${fmt(c.fecha,'date')}</td><td><strong>${c.hora}</strong></td>
      <td>${c.paciente}</td><td>${c.medico}</td><td>${c.especialidad}</td>
      <td>${fmt(c.motivo)}</td><td>${badge(c.estado)}</td>
      <td style="white-space:nowrap;">
        ${c.estado==='programada'?`<button class="btn btn-success btn-sm confirmar-cita" data-id="${c.cita_id}" style="margin-right:4px">✓</button>`:''}
        ${c.estado!=='cancelada'&&c.estado!=='completada'?`<button class="btn btn-danger btn-sm cancelar-cita" data-id="${c.cita_id}">✗</button>`:''}
      </td>
    </tr>`).join('')}</tbody>
  </table></div>`;
}

function attachCitaActions() {
  document.querySelectorAll('.confirmar-cita').forEach(b => b.onclick = async () => {
    await api('PUT', `/citas/${b.dataset.id}`, {estado:'confirmada'});
    showToast('Cita confirmada'); $('btn-filtrar-citas').click();
  });
  document.querySelectorAll('.cancelar-cita').forEach(b => b.onclick = async () => {
    if (!confirm('¿Cancelar esta cita?')) return;
    await api('DELETE', `/citas/${b.dataset.id}`);
    showToast('Cita cancelada','danger'); $('btn-filtrar-citas').click();
  });
}

async function formCita() {
  const [medList, pacList] = await Promise.all([
    api('GET', '/medicos'),
    api('GET', '/pacientes'),
  ]);
  const mOpts = medList.map(m=>`<option value="${m.medico_id}">${m.nombre} ${m.apellidos} — ${m.especialidad}</option>`).join('');
  const pOpts = pacList.map(p=>`<option value="${p.paciente_id}">${p.nombre} ${p.apellidos}</option>`).join('');

  openModal('Nueva Cita', `
  <div class="form-grid">
    <div class="form-group full"><label>Paciente *</label><select id="fc-pac"><option value="">Seleccionar</option>${pOpts}</select></div>
    <div class="form-group full"><label>Médico *</label><select id="fc-med"><option value="">Seleccionar</option>${mOpts}</select></div>
    <div class="form-group"><label>Fecha *</label><input id="fc-fecha" type="date" min="${new Date().toISOString().split('T')[0]}"></div>
    <div class="form-group"><label>Hora *</label><select id="fc-hora"><option value="">— selecciona médico y fecha —</option></select></div>
    <div class="form-group full"><label>Motivo</label><input id="fc-motivo" placeholder="Motivo de la cita"></div>
  </div>`,
  `<button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
   <button class="btn btn-primary" id="btn-save-cita">Agendar</button>`);

  async function loadSlots() {
    const mid = $('fc-med').value, fecha = $('fc-fecha').value;
    if (!mid || !fecha) return;
    const slots = await api('GET', `/citas/disponibilidad?medico_id=${mid}&fecha=${fecha}`);
    $('fc-hora').innerHTML = slots.filter(s=>s.disponible).map(s=>`<option value="${s.hora}">${s.hora.substring(0,5)}</option>`).join('') || '<option>Sin horarios disponibles</option>';
  }
  $('fc-med').onchange = loadSlots;
  $('fc-fecha').onchange = loadSlots;

  $('btn-save-cita').onclick = async () => {
    const data = {
      medico_id:$('fc-med').value, paciente_id:$('fc-pac').value,
      fecha:$('fc-fecha').value, hora:$('fc-hora').value, motivo:$('fc-motivo').value,
    };
    try {
      await api('POST', '/citas', data);
      closeModal(); showToast('Cita agendada'); citas();
    } catch(e) { showToast(e.message,'danger'); }
  };
}

// ══════════════════════════════════════════════════════════════════════
// MÓDULO: Consultas
// ══════════════════════════════════════════════════════════════════════

async function consultas() {
  $('topbar-actions').innerHTML = `<button class="btn btn-primary" id="btn-nueva-cons">+ Nueva Consulta</button>`;
  $('btn-nueva-cons').onclick = () => formConsulta();
  const content = $('content');
  try {
    const data = await api('GET', '/consultas');
    content.innerHTML = `
    <div class="card">
      <div class="card-body">
        ${data.length===0 ? '<div class="empty-state"><p>Sin consultas registradas</p></div>'
        : `<div class="table-wrapper"><table>
          <thead><tr><th>Fecha</th><th>Paciente</th><th>Médico</th><th>Tipo</th><th>Precio</th><th>Consultorio</th><th>Acciones</th></tr></thead>
          <tbody>${data.map(c=>`<tr>
            <td>${fmt(c.fecha,'date')}</td><td>${c.paciente}</td><td>${c.medico}</td>
            <td>${fmt(c.tipo_consulta)}</td><td>${fmt(c.precio,'money')}</td><td>${fmt(c.consultorio)}</td>
            <td><button class="btn btn-secondary btn-sm ver-cons" data-id="${c.id_consulta}">Ver detalle</button></td>
          </tr>`).join('')}</tbody>
        </table></div>`}
      </div>
    </div>`;

    document.querySelectorAll('.ver-cons').forEach(b => b.onclick = () => verConsulta(b.dataset.id));
  } catch(e) { content.innerHTML = `<div class="alert alert-danger">${e.message}</div>`; }
}

async function verConsulta(id) {
  const [c, receta] = await Promise.all([
    api('GET', `/consultas/${id}`),
    api('GET', `/consultas/${id}/receta`),
  ]);
  openModal('Detalle de Consulta', `
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
      <div><small style="color:var(--text-muted)">Paciente</small><p><strong>${c.paciente}</strong></p></div>
      <div><small style="color:var(--text-muted)">Médico</small><p>${c.medico}</p></div>
      <div><small style="color:var(--text-muted)">Tipo</small><p>${fmt(c.tipo_consulta)}</p></div>
      <div><small style="color:var(--text-muted)">Consultorio</small><p>${fmt(c.consultorio)}</p></div>
    </div>
    <div style="margin-bottom:16px;"><small style="color:var(--text-muted)">Diagnóstico</small><p>${fmt(c.diagnostico)}</p></div>
    <div style="margin-bottom:16px;"><small style="color:var(--text-muted)">Observaciones</small><p>${fmt(c.observaciones)}</p></div>
    ${receta && receta.length ? `
    <h4 style="font-size:.9rem;margin-bottom:10px;">Receta Médica</h4>
    <table style="width:100%;font-size:.82rem;">
      <thead><tr style="background:#f8fafc;"><th style="padding:6px 10px;">Medicamento</th><th style="padding:6px 10px;">Frecuencia</th><th style="padding:6px 10px;">Duración</th></tr></thead>
      <tbody>${receta.map(r=>`<tr><td style="padding:8px 10px;">${r.nombre}</td><td style="padding:8px 10px;">${fmt(r.frecuencia)}</td><td style="padding:8px 10px;">${fmt(r.duracion)}</td></tr>`).join('')}</tbody>
    </table>` : ''}
  `);
}

async function formConsulta() {
  const [medList, pacList, tiposC, consultList, medMeds] = await Promise.all([
    api('GET', '/medicos'), api('GET', '/pacientes'),
    api('GET', '/catalogos/tipo-consulta'), api('GET', '/catalogos/consultorios'),
    api('GET', '/medicamentos/catalogo'),
  ]);

  const mOpts = medList.map(m=>`<option value="${m.medico_id}">${m.nombre} ${m.apellidos}</option>`).join('');
  const pOpts = pacList.map(p=>`<option value="${p.paciente_id}">${p.nombre} ${p.apellidos}</option>`).join('');
  const tOpts = tiposC.map(t=>`<option value="${t.id_tipo_consulta}">${t.descripcion} — $${t.precio}</option>`).join('');
  const cOpts = consultList.map(c=>`<option value="${c.id_consultorio}">${c.numero} — Piso ${c.piso}</option>`).join('');
  const medMedsOpts = medMeds.map(m=>`<option value="${m.id_medicamento}">${m.nombre}</option>`).join('');

  openModal('Nueva Consulta', `
  <div class="form-grid">
    <div class="form-group full"><label>Paciente *</label><select id="cons-pac"><option value="">Seleccionar</option>${pOpts}</select></div>
    <div class="form-group full"><label>Médico *</label><select id="cons-med"><option value="">Seleccionar</option>${mOpts}</select></div>
    <div class="form-group"><label>Tipo de Consulta</label><select id="cons-tipo"><option value="">Seleccionar</option>${tOpts}</select></div>
    <div class="form-group"><label>Consultorio</label><select id="cons-cons"><option value="">Seleccionar</option>${cOpts}</select></div>
    <div class="form-group full"><label>Diagnóstico</label><textarea id="cons-dx" placeholder="Diagnóstico del paciente"></textarea></div>
    <div class="form-group full"><label>Observaciones</label><textarea id="cons-obs" placeholder="Observaciones y notas"></textarea></div>
    <div class="form-group full">
      <label>Medicamentos en receta</label>
      <select id="cons-med-add" style="margin-bottom:8px;"><option value="">Agregar medicamento</option>${medMedsOpts}</select>
      <div id="meds-lista" style="display:flex;flex-wrap:wrap;gap:8px;"></div>
    </div>
  </div>`,
  `<button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
   <button class="btn btn-primary" id="btn-save-cons">Guardar Consulta</button>`);

  const selectedMeds = [];
  $('cons-med-add').onchange = function() {
    const opt = this.options[this.selectedIndex];
    if (!opt.value) return;
    if (!selectedMeds.find(m=>m.id===opt.value)) {
      selectedMeds.push({id:opt.value, nombre:opt.text, frecuencia:'', duracion:''});
      renderSelectedMeds();
    }
    this.value = '';
  };

  function renderSelectedMeds() {
    $('meds-lista').innerHTML = selectedMeds.map((m,i)=>`
      <div style="background:#f8fafc;border:1px solid var(--border);padding:10px;border-radius:8px;width:100%;">
        <strong style="font-size:.83rem;">${m.nombre}</strong>
        <div style="display:grid;grid-template-columns:1fr 1fr auto;gap:8px;margin-top:6px;align-items:center;">
          <input placeholder="Frecuencia (ej: cada 8h)" style="font-size:.78rem;padding:6px 8px;" oninput="window.__sm[${i}].frecuencia=this.value">
          <input placeholder="Duración (ej: 7 días)" style="font-size:.78rem;padding:6px 8px;" oninput="window.__sm[${i}].duracion=this.value">
          <button onclick="window.__sm.splice(${i},1);renderSelectedMeds()" style="background:none;border:none;color:var(--danger);cursor:pointer;font-size:1.1rem;">✕</button>
        </div>
      </div>`).join('');
    window.__sm = selectedMeds;
  }
  window.__sm = selectedMeds;

  $('btn-save-cons').onclick = async () => {
    const data = {
      medico_id:$('cons-med').value, paciente_id:$('cons-pac').value,
      id_tipo_consulta:$('cons-tipo').value||null,
      id_consultorio:$('cons-cons').value||null,
      diagnostico:$('cons-dx').value, observaciones:$('cons-obs').value,
      medicamentos:selectedMeds.map(m=>({id_medicamento:m.id,frecuencia:m.frecuencia,duracion:m.duracion})),
    };
    try {
      await api('POST', '/consultas', data);
      closeModal(); showToast('Consulta registrada'); consultas();
    } catch(e) { showToast(e.message,'danger'); }
  };
}

// ══════════════════════════════════════════════════════════════════════
// MÓDULO: Medicamentos / Inventario
// ══════════════════════════════════════════════════════════════════════

async function medicamentos() {
  $('topbar-actions').innerHTML = `
    <button class="btn btn-secondary" id="btn-caducos">⚠ Caducos</button>
    <button class="btn btn-primary" id="btn-nuevo-med" style="margin-left:8px">+ Medicamento</button>`;
  $('btn-nuevo-med').onclick = () => formMedicamento();
  $('btn-caducos').onclick = async () => {
    const data = await api('GET', '/medicamentos/caducos');
    openModal('Medicamentos Caducos / Por Caducar', `
    <div class="table-wrapper"><table>
      <thead><tr><th>Medicamento</th><th>Stock</th><th>Caducidad</th><th>Estado</th></tr></thead>
      <tbody>${data.length===0?'<tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Sin alertas</td></tr>':data.map(m=>`<tr>
        <td>${m.medicamento}</td><td>${m.stock}</td><td>${fmt(m.fecha_caducidad,'date')}</td><td>${badge(m.estado_caducidad)}</td>
      </tr>`).join('')}</tbody>
    </table></div>`);
  };

  const content = $('content');
  try {
    const data = await api('GET', '/medicamentos');
    content.innerHTML = `
    <div class="card">
      <div class="card-header" style="margin-bottom:16px;">
        <span class="card-title">Inventario de Medicamentos</span>
      </div>
      <div class="card-body">
        <div class="table-wrapper"><table>
          <thead><tr><th>Medicamento</th><th>Precio</th><th>Stock</th><th>Caducidad</th><th>Estado</th><th>Acciones</th></tr></thead>
          <tbody>${data.map(m=>`<tr>
            <td><strong>${m.medicamento}</strong></td>
            <td>${fmt(m.precio,'money')}</td>
            <td><strong>${m.stock}</strong></td>
            <td>${fmt(m.fecha_caducidad,'date')}</td>
            <td>${badge(m.estado_caducidad)}</td>
            <td>
              <button class="btn btn-success btn-sm entrada-stock" data-id="${m.id_inventario}" style="margin-right:4px">+ Stock</button>
              <button class="btn btn-secondary btn-sm edit-med" data-id="${m.id_medicamento}">Editar</button>
            </td>
          </tr>`).join('')}</tbody>
        </table></div>
      </div>
    </div>`;

    document.querySelectorAll('.entrada-stock').forEach(b => b.onclick = () => entradaStock(b.dataset.id));
    document.querySelectorAll('.edit-med').forEach(b => b.onclick = () => formMedicamento(b.dataset.id));
  } catch(e) { content.innerHTML = `<div class="alert alert-danger">${e.message}</div>`; }
}

function entradaStock(id_inventario) {
  openModal('Entrada de Stock', `
  <div class="form-group"><label>Cantidad a ingresar</label><input id="entrada-cant" type="number" min="1" value="1"></div>`,
  `<button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
   <button class="btn btn-success" id="btn-confirm-entrada">Ingresar</button>`);
  $('btn-confirm-entrada').onclick = async () => {
    await api('POST', '/medicamentos/entrada', {id_inventario:parseInt(id_inventario),cantidad:parseInt($('entrada-cant').value)});
    closeModal(); showToast('Stock actualizado'); medicamentos();
  };
}

async function formMedicamento(id = null) {
  openModal(id ? 'Editar Medicamento' : 'Nuevo Medicamento', `
  <div class="form-grid">
    <div class="form-group full"><label>Nombre *</label><input id="med-nom" placeholder="Nombre del medicamento"></div>
    <div class="form-group full"><label>Descripción</label><textarea id="med-desc" rows="2"></textarea></div>
    <div class="form-group"><label>Precio unitario</label><input id="med-precio" type="number" step="0.01" value="0"></div>
    ${!id ? `
    <div class="form-group"><label>Stock inicial</label><input id="med-stock" type="number" min="0" value="0"></div>
    <div class="form-group"><label>Fecha de caducidad</label><input id="med-cad" type="date"></div>` : ''}
  </div>`,
  `<button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
   <button class="btn btn-primary" id="btn-save-med">Guardar</button>`);

  $('btn-save-med').onclick = async () => {
    const data = {
      nombre:$('med-nom').value, descripcion:$('med-desc').value,
      precio:parseFloat($('med-precio').value)||0,
    };
    if (!id) { data.stock=parseInt($('med-stock').value)||0; data.fecha_caducidad=$('med-cad').value||null; }
    try {
      if (id) await api('PUT', `/medicamentos/${id}`, data);
      else await api('POST', '/medicamentos', data);
      closeModal(); showToast('Medicamento guardado'); medicamentos();
    } catch(e) { showToast(e.message,'danger'); }
  };
}

// ══════════════════════════════════════════════════════════════════════
// MÓDULO: Pagos
// ══════════════════════════════════════════════════════════════════════

async function pagos() {
  const content = $('content');
  try {
    const data = await api('GET', '/pagos');
    const total = data.filter(p=>p.estado==='pagado').reduce((s,p)=>s+parseFloat(p.monto_total||0),0);
    const pendiente = data.filter(p=>p.estado==='pendiente').reduce((s,p)=>s+parseFloat(p.monto_total||0),0);

    content.innerHTML = `
    <div class="stats-grid" style="margin-bottom:24px;">
      <div class="stat-card"><div class="stat-label">Total Cobrado</div><div class="stat-value" style="color:var(--success)">${fmt(total,'money')}</div></div>
      <div class="stat-card"><div class="stat-label">Pendiente de Cobro</div><div class="stat-value" style="color:var(--warning)">${fmt(pendiente,'money')}</div></div>
      <div class="stat-card"><div class="stat-label">Total Registros</div><div class="stat-value">${data.length}</div></div>
    </div>
    <div class="card">
      <div class="card-body">
        <div class="table-wrapper"><table>
          <thead><tr><th>Fecha</th><th>Paciente</th><th>Médico</th><th>Tipo</th><th>Monto</th><th>Estado</th><th>Acción</th></tr></thead>
          <tbody>${data.map(p=>`<tr>
            <td>${fmt(p.fecha,'date')}</td>
            <td>${fmt(p.paciente)}</td><td>${fmt(p.medico)}</td><td>${fmt(p.tipo)}</td>
            <td><strong>${fmt(p.monto_total,'money')}</strong></td>
            <td>${badge(p.estado)}</td>
            <td>${p.estado==='pendiente'?`<button class="btn btn-success btn-sm pagar-btn" data-id="${p.id_pago}">Cobrar</button>`:'—'}</td>
          </tr>`).join('')}</tbody>
        </table></div>
      </div>
    </div>`;

    document.querySelectorAll('.pagar-btn').forEach(b => b.onclick = async () => {
      await api('PUT', `/pagos/${b.dataset.id}/pagar`);
      showToast('Pago registrado'); pagos();
    });
  } catch(e) { content.innerHTML = `<div class="alert alert-danger">${e.message}</div>`; }
}

// ══════════════════════════════════════════════════════════════════════
// MÓDULO: Reportes
// ══════════════════════════════════════════════════════════════════════

async function reportes() {
  const content = $('content');
  const desde = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];
  const hasta = new Date().toISOString().split('T')[0];

  content.innerHTML = `
  <div style="display:flex;gap:12px;margin-bottom:24px;flex-wrap:wrap;align-items:flex-end;">
    <div class="form-group" style="margin:0;"><label>Desde</label><input type="date" id="rep-desde" value="${desde}"></div>
    <div class="form-group" style="margin:0;"><label>Hasta</label><input type="date" id="rep-hasta" value="${hasta}"></div>
    <button class="btn btn-primary" id="btn-gen-rep">Generar Reportes</button>
  </div>
  <div id="rep-content" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;"></div>`;

  async function generarReportes() {
    const d = $('rep-desde').value, h = $('rep-hasta').value;
    const [ing, pGe, cPe, mEsp, bit] = await Promise.all([
      api('GET', `/reportes/ingresos?desde=${d}&hasta=${h}`),
      api('GET', '/reportes/pacientes-genero'),
      api('GET', `/reportes/consultas-periodo?desde=${d}&hasta=${h}`),
      api('GET', '/reportes/medicos-especialidad'),
      api('GET', '/reportes/bitacora?limit=20'),
    ]);

    const totalIng = ing.reduce((s,r)=>s+parseFloat(r.total||0),0);
    $('rep-content').innerHTML = `
    <!-- Ingresos -->
    <div class="card"><div class="card-header"><span class="card-title">Ingresos del Período</span></div><div class="card-body">
      <div style="font-size:1.8rem;font-weight:700;color:var(--success);margin-bottom:12px;">${fmt(totalIng,'money')}</div>
      <table style="width:100%;font-size:.82rem;">
        <thead><tr><th style="padding:4px 0;">Fecha</th><th>Pagos</th><th>Total</th></tr></thead>
        <tbody>${ing.map(r=>`<tr><td style="padding:4px 0;">${r.fecha}</td><td>${r.pagos}</td><td>${fmt(r.total,'money')}</td></tr>`).join('')}</tbody>
      </table>
    </div></div>

    <!-- Pacientes por género -->
    <div class="card"><div class="card-header"><span class="card-title">Pacientes por Género</span></div><div class="card-body">
      ${pGe.map(g=>{
        const total=pGe.reduce((s,x)=>s+parseInt(x.total),0);
        const pct=Math.round(parseInt(g.total)/total*100);
        return `<div style="margin-bottom:14px;">
          <div style="display:flex;justify-content:space-between;margin-bottom:4px;font-size:.85rem;">
            <span>${g.sexo==='M'?'♂ Masculino':'♀ Femenino'}</span>
            <strong>${g.total} (${pct}%)</strong>
          </div>
          <div style="background:#f1f5f9;border-radius:4px;height:8px;">
            <div style="background:var(--accent);height:8px;border-radius:4px;width:${pct}%;"></div>
          </div>
        </div>`;
      }).join('')}
    </div></div>

    <!-- Médicos por especialidad -->
    <div class="card"><div class="card-header"><span class="card-title">Médicos por Especialidad</span></div><div class="card-body">
      <table style="width:100%;font-size:.83rem;">
        <thead><tr><th style="padding:5px 0;">Especialidad</th><th>Médicos</th></tr></thead>
        <tbody>${mEsp.map(e=>`<tr><td style="padding:5px 0;">${e.especialidad}</td><td>${e.total}</td></tr>`).join('')}</tbody>
      </table>
    </div></div>

    <!-- Bitácora -->
    <div class="card" style="grid-column:1/-1;"><div class="card-header"><span class="card-title">Bitácora de Operaciones (últimas 20)</span></div><div class="card-body">
      <div class="table-wrapper"><table style="font-size:.8rem;">
        <thead><tr><th>Fecha/Hora</th><th>Acción</th><th>Tabla</th><th>Registro</th><th>IP</th><th>Descripción</th></tr></thead>
        <tbody>${bit.map(b=>`<tr>
          <td>${b.fecha_hora}</td>
          <td>${badge(b.accion,'blue')}</td>
          <td>${b.tabla_afectada}</td>
          <td>${fmt(b.registro_id)}</td>
          <td>${fmt(b.ip)}</td>
          <td>${fmt(b.descripcion)}</td>
        </tr>`).join('')}</tbody>
      </table></div>
    </div></div>`;
  }

  $('btn-gen-rep').onclick = generarReportes;
  generarReportes();
}

// ══════════════════════════════════════════════════════════════════════
// MÓDULO: Catálogos
// ══════════════════════════════════════════════════════════════════════

async function catalogos() {
  $('topbar-actions').innerHTML = `
    <button class="btn btn-primary" id="btn-nueva-esp">+ Especialidad</button>
    <button class="btn btn-secondary" id="btn-nuevo-cons" style="margin-left:8px">+ Consultorio</button>`;
  const content = $('content');
  try {
    const [esp, cons, tipos, sangre] = await Promise.all([
      api('GET', '/catalogos/especialidades'),
      api('GET', '/catalogos/consultorios'),
      api('GET', '/catalogos/tipo-consulta'),
      api('GET', '/catalogos/tipo-sangre'),
    ]);

    content.innerHTML = `
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;">
      <div class="card"><div class="card-header"><span class="card-title">Especialidades Médicas</span></div>
      <div class="card-body">
        ${esp.map(e=>`<div style="padding:8px 0;border-bottom:1px solid var(--border);font-size:.875rem;">${e.especialidad}</div>`).join('')}
      </div></div>

      <div class="card"><div class="card-header"><span class="card-title">Consultorios</span></div>
      <div class="card-body"><table style="width:100%;font-size:.83rem;">
        <thead><tr><th>No.</th><th>Piso</th><th>Descripción</th></tr></thead>
        <tbody>${cons.map(c=>`<tr><td>${c.numero}</td><td>${c.piso}</td><td>${fmt(c.descripcion)}</td></tr>`).join('')}</tbody>
      </table></div></div>

      <div class="card"><div class="card-header"><span class="card-title">Tipos de Consulta</span></div>
      <div class="card-body"><table style="width:100%;font-size:.83rem;">
        <thead><tr><th>Tipo</th><th>Precio</th></tr></thead>
        <tbody>${tipos.map(t=>`<tr><td>${t.descripcion}</td><td>${fmt(t.precio,'money')}</td></tr>`).join('')}</tbody>
      </table></div></div>

      <div class="card"><div class="card-header"><span class="card-title">Tipos de Sangre</span></div>
      <div class="card-body" style="display:flex;flex-wrap:wrap;gap:8px;">
        ${sangre.map(s=>`<span class="badge badge-red">${s.descripcion}</span>`).join('')}
      </div></div>
    </div>`;

    $('btn-nueva-esp').onclick = () => {
      openModal('Nueva Especialidad', `<div class="form-group"><label>Nombre *</label><input id="esp-nom" placeholder="Ej: Cardiología"></div>`,
      `<button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
       <button class="btn btn-primary" id="btn-save-esp">Guardar</button>`);
      $('btn-save-esp').onclick = async () => {
        await api('POST', '/catalogos/especialidades', {especialidad:$('esp-nom').value});
        closeModal(); showToast('Especialidad creada'); catalogos();
      };
    };

    $('btn-nuevo-cons').onclick = () => {
      openModal('Nuevo Consultorio', `
      <div class="form-grid">
        <div class="form-group"><label>Número *</label><input id="cons-num" placeholder="C-101"></div>
        <div class="form-group"><label>Piso *</label><input id="cons-piso" placeholder="1"></div>
        <div class="form-group full"><label>Descripción</label><input id="cons-desc"></div>
      </div>`,
      `<button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
       <button class="btn btn-primary" id="btn-save-cons-cat">Guardar</button>`);
      $('btn-save-cons-cat').onclick = async () => {
        await api('POST', '/catalogos/consultorios', {numero:$('cons-num').value,piso:$('cons-piso').value,descripcion:$('cons-desc').value});
        closeModal(); showToast('Consultorio creado'); catalogos();
      };
    };
  } catch(e) { content.innerHTML = `<div class="alert alert-danger">${e.message}</div>`; }
}

// ── Init ─────────────────────────────────────────────────────────────
(async () => {
  try {
    currentUser = await api('GET', '/auth/me');
    if (currentUser) showApp();
  } catch { /* No hay sesión, mostrar login */ }
})();
