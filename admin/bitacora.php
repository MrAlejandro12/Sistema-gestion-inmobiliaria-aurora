<?php
require_once __DIR__ . '/../config/db.php';
secureSessionStart();
requireAuth(['admin']);
$pdo = getDB();

$pageTitle    = 'Bitácora del Sistema';
$pageSubtitle = 'Registro en tiempo real de todas las acciones · Ley 164';
$activeNav    = 'ad-audit';
require_once __DIR__ . '/../includes/layout.php';
?>

<!-- KPIs en vivo -->
<div class="kpi-grid" style="margin-bottom:24px">
  <div class="kpi-card blue">  <div class="kpi-icon">📋</div><div class="kpi-label">Acciones hoy</div>    <div class="kpi-value" id="kpi-hoy">—</div></div>
  <div class="kpi-card red">   <div class="kpi-icon">⚠️</div><div class="kpi-label">Errores / Alertas</div><div class="kpi-value" id="kpi-warn">—</div></div>
  <div class="kpi-card green">  <div class="kpi-icon">🔑</div><div class="kpi-label">Logins exitosos</div>  <div class="kpi-value" id="kpi-login">—</div></div>
  <div class="kpi-card orange"><div class="kpi-icon">🚫</div><div class="kpi-label">Intentos fallidos</div><div class="kpi-value" id="kpi-fail">—</div></div>
</div>

<!-- Filtros -->
<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;align-items:flex-end;background:#fff;padding:16px;border-radius:12px;border:1px solid var(--border)">
  <div class="field-group"><label>Desde</label><input type="datetime-local" id="f-desde" value="<?= date('Y-m-d') ?>T00:00"></div>
  <div class="field-group" style="width:180px"><label>Usuario / Email</label><input type="text" id="f-email" placeholder="Filtrar por email..."></div>
  <div class="field-group" style="width:140px"><label>Nivel</label>
    <select id="f-nivel">
      <option value="">Todos</option>
      <option value="info">Info</option>
      <option value="warning">Advertencia</option>
      <option value="error">Error</option>
      <option value="critico">Crítico</option>
    </select></div>
  <div class="field-group" style="width:160px"><label>Acción contiene</label><input type="text" id="f-accion" placeholder="LOGIN, VENTA..."></div>
  <button onclick="applyFilters()" class="btn btn-primary btn-sm" style="align-self:flex-end">🔍 Filtrar</button>
  <button onclick="clearFilters()" class="btn btn-outline btn-sm" style="align-self:flex-end">Limpiar</button>
  <div style="margin-left:auto;display:flex;align-items:center;gap:8px">
    <div id="rt-dot" style="width:8px;height:8px;border-radius:50%;background:#27AE60;animation:pulse 2s infinite"></div>
    <span style="font-size:12px;color:var(--muted)">En vivo</span>
    <span id="last-ts" style="font-size:11px;color:var(--muted)"></span>
    <label style="display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer;color:var(--muted)">
      <input type="checkbox" id="auto-scroll" checked> Auto-scroll
    </label>
    <button onclick="exportCSV()" class="btn btn-outline btn-sm">📥 CSV</button>
  </div>
</div>

<!-- Bitácora en tiempo real -->
<div class="card">
  <div class="card-header">
    <h3>📜 Bitácora — Eventos del sistema</h3>
    <span id="row-count" class="badge badge-blue">0 registros</span>
  </div>
  <div id="bitacora-wrap" style="max-height:600px;overflow-y:auto;border-radius:0 0 16px 16px">
    <table class="table" id="bitacora-table">
      <thead style="position:sticky;top:0;z-index:5">
        <tr>
          <th style="width:140px">⏱ Timestamp</th>
          <th style="width:80px">Nivel</th>
          <th style="width:180px">Usuario</th>
          <th>Acción</th>
          <th style="width:90px">Tabla</th>
          <th style="width:100px">IP</th>
        </tr>
      </thead>
      <tbody id="bitacora-body">
        <tr><td colspan="6" style="text-align:center;padding:32px;color:var(--muted)">Cargando bitácora...</td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Panel lateral de detalle -->
<div id="detail-panel" style="display:none;position:fixed;right:0;top:0;bottom:0;width:380px;background:#fff;border-left:1px solid var(--border);z-index:200;overflow-y:auto;padding:24px;box-shadow:-8px 0 32px rgba(0,0,0,.1)">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
    <h3 style="font-size:15px;font-weight:600">Detalle del evento</h3>
    <button onclick="document.getElementById('detail-panel').style.display='none'" style="background:none;border:none;cursor:pointer;font-size:20px;color:var(--muted)">×</button>
  </div>
  <div id="detail-content"></div>
</div>

<style>
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.3}}
@keyframes flash{0%{background:rgba(59,130,246,.2)}100%{background:transparent}}
.log-row{cursor:pointer;transition:background .1s}
.log-row:hover td{background:#f8fafc}
.log-row.is-new{animation:flash .8s ease}
.lvl{display:inline-flex;align-items:center;padding:2px 8px;border-radius:12px;font-size:10px;font-weight:700;letter-spacing:.04em}
.lvl-info   {background:#dbeafe;color:#1d4ed8}
.lvl-warning{background:#fef3c7;color:#92400e}
.lvl-error  {background:#fee2e2;color:#991b1b}
.lvl-critico{background:#7f1d1d;color:#fff}
.action-text{font-size:12px;font-family:monospace;color:var(--gray)}
.action-text.login_ok   {color:#065f46}
.action-text.login_fail {color:#991b1b;font-weight:600}
.action-text.login_block{color:#92400e;font-weight:600}
.ts-cell{font-size:11px;font-family:monospace;color:var(--muted)}
.user-cell{font-size:12px}
.ip-cell{font-size:11px;font-family:monospace;color:var(--muted)}
</style>

<script>
let allRows = [];
let knownIds = new Set();
let sinceTs  = new Date(Date.now() - 24*3600*1000).toISOString().slice(0,19).replace('T',' ');
let filters  = { email:'', nivel:'', accion:'' };
let firstLoad = true;

// ── Helpers ──────────────────────────────────────────────────
function formatTs(d) {
  if (!d) return '—';
  const dt = new Date(d);
  return dt.toLocaleDateString('es-BO',{day:'2-digit',month:'2-digit'}) + ' ' +
         dt.toLocaleTimeString('es-BO',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
}

function levelClass(accion) {
  const a = (accion||'').toUpperCase();
  if (a.includes('FAIL') || a.includes('ERROR') || a.includes('BLOCK')) return 'error';
  if (a.includes('WARN') || a.includes('INTENTO') || a.includes('RECHAZ')) return 'warning';
  if (a.includes('CRITICO') || a.includes('BREACH')) return 'critico';
  return 'info';
}

function actionClass(accion) {
  const a = (accion||'').toUpperCase();
  if (a.startsWith('LOGIN_OK')) return 'login_ok';
  if (a.startsWith('LOGIN_FAIL')) return 'login_fail';
  if (a.startsWith('LOGIN_BLOCK')) return 'login_block';
  return '';
}

function rowIcon(accion) {
  const a = (accion||'').toUpperCase();
  if (a.includes('LOGIN_OK'))    return '🔑';
  if (a.includes('LOGIN_FAIL'))  return '⛔';
  if (a.includes('LOGIN_BLOCK')) return '🚫';
  if (a.includes('REGISTR'))     return '✏️';
  if (a.includes('ELIMIN'))      return '🗑️';
  if (a.includes('PAGO'))        return '💳';
  if (a.includes('CONTRATO'))    return '📄';
  if (a.includes('VENTA'))       return '🏡';
  if (a.includes('VERIFIC'))     return '✅';
  if (a.includes('LOGOUT'))      return '🔓';
  if (a.includes('CONSUL'))      return '💬';
  return '📋';
}

// ── Render bitácora ───────────────────────────────────────────
function renderBitacora(rows) {
  const tbody = document.getElementById('bitacora-body');
  const newIds = new Set(rows.map(r => r.id));
  const added  = !firstLoad && [...newIds].some(id => !knownIds.has(id));

  // Update KPIs
  const hoy     = rows.filter(r => r.created_at?.slice(0,10) === new Date().toISOString().slice(0,10));
  const warns   = hoy.filter(r => levelClass(r.accion) !== 'info');
  const logins  = hoy.filter(r => r.accion?.toUpperCase().includes('LOGIN_OK'));
  const fails   = hoy.filter(r => r.accion?.toUpperCase().includes('LOGIN_FAIL'));
  document.getElementById('kpi-hoy').textContent   = hoy.length;
  document.getElementById('kpi-warn').textContent  = warns.length;
  document.getElementById('kpi-login').textContent = logins.length;
  document.getElementById('kpi-fail').textContent  = fails.length;
  document.getElementById('row-count').textContent = rows.length + ' registros';

  // Apply client-side filters
  let filtered = rows;
  if (filters.email)  filtered = filtered.filter(r => (r.email||'').toLowerCase().includes(filters.email.toLowerCase()));
  if (filters.nivel)  filtered = filtered.filter(r => levelClass(r.accion) === filters.nivel);
  if (filters.accion) filtered = filtered.filter(r => (r.accion||'').toLowerCase().includes(filters.accion.toLowerCase()));

  if (!filtered.length) {
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:24px;color:var(--muted)">Sin registros para los filtros actuales</td></tr>';
    knownIds = newIds; firstLoad = false; return;
  }

  tbody.innerHTML = filtered.map(r => {
    const isNew = !knownIds.has(r.id) && !firstLoad;
    const lvl   = levelClass(r.accion);
    const aC    = actionClass(r.accion);
    const icon  = rowIcon(r.accion);
    return `<tr class="log-row ${isNew?'is-new':''}" onclick="showDetail(${JSON.stringify(r).replace(/"/g,'&quot;')})">
      <td class="ts-cell">${formatTs(r.created_at)}</td>
      <td><span class="lvl lvl-${lvl}">${lvl.toUpperCase()}</span></td>
      <td class="user-cell">
        ${r.user_nombre ? `<div style="font-weight:500;font-size:12px">${r.user_nombre}</div>` : ''}
        <div style="font-size:11px;color:var(--muted)">${r.email||'sistema'}</div>
        ${r.user_rol ? `<div style="font-size:10px;color:var(--muted)">${r.user_rol}</div>` : ''}
      </td>
      <td><span class="action-text ${aC}">${icon} ${r.accion||''}</span></td>
      <td style="font-size:11px;color:var(--muted)">${r.tabla_afectada||'—'}</td>
      <td class="ip-cell">${r.ip||'—'}</td>
    </tr>`;
  }).join('');

  if (added) {
    showToast('Nueva acción registrada en bitácora', '');
    if (document.getElementById('auto-scroll').checked) {
      const wrap = document.getElementById('bitacora-wrap');
      wrap.scrollTop = 0; // newest on top
    }
  }
  knownIds = newIds;
  firstLoad = false;
  allRows = rows;
  document.getElementById('last-ts').textContent = 'Actualizado: ' + new Date().toLocaleTimeString('es-BO');
}

function showDetail(row) {
  const panel = document.getElementById('detail-panel');
  const cont  = document.getElementById('detail-content');
  const lvl   = levelClass(row.accion);
  cont.innerHTML = `
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px">
      <span class="lvl lvl-${lvl}" style="padding:4px 12px;font-size:12px">${lvl.toUpperCase()}</span>
      <span style="font-size:20px">${rowIcon(row.accion)}</span>
    </div>
    <table style="width:100%;font-size:13px;border-collapse:collapse">
      ${[
        ['ID', '#'+row.id],
        ['Timestamp', formatTs(row.created_at)],
        ['Usuario', row.user_nombre || '—'],
        ['Email', row.email || '—'],
        ['Rol', row.user_rol || '—'],
        ['Acción', row.accion],
        ['Tabla', row.tabla_afectada || '—'],
        ['Registro ID', row.registro_id || '—'],
        ['IP', row.ip || '—'],
        ['User Agent', (row.user_agent||'—').slice(0,60)+'...'],
      ].map(([k,v]) => `<tr style="border-bottom:1px solid var(--border)">
        <td style="padding:8px 0;color:var(--muted);font-weight:500;width:120px">${k}</td>
        <td style="padding:8px 0;font-family:monospace;font-size:12px;word-break:break-all">${v}</td>
      </tr>`).join('')}
    </table>
  `;
  panel.style.display = 'block';
}

// ── Polling ───────────────────────────────────────────────────
async function poll() {
  try {
    const desde = document.getElementById('f-desde').value.replace('T',' ') + ':00';
    const r = await fetch(`/sgi_aurora/api_realtime.php?action=bitacora&since=${encodeURIComponent(desde)}&limit=200`);
    const d = await r.json();
    if (d.ok) renderBitacora(d.data);
    document.getElementById('rt-dot').style.background = '#27AE60';
  } catch(e) {
    document.getElementById('rt-dot').style.background = '#E74C3C';
    document.getElementById('rt-dot').style.animation = 'none';
  }
}

function applyFilters() {
  filters.email  = document.getElementById('f-email').value;
  filters.nivel  = document.getElementById('f-nivel').value;
  filters.accion = document.getElementById('f-accion').value;
  renderBitacora(allRows);
}

function clearFilters() {
  document.getElementById('f-email').value  = '';
  document.getElementById('f-nivel').value  = '';
  document.getElementById('f-accion').value = '';
  filters = {email:'',nivel:'',accion:''};
  renderBitacora(allRows);
}

function exportCSV() {
  const rows = allRows;
  if (!rows.length) return showToast('Sin datos para exportar','err');
  const header = 'ID,Timestamp,Email,Rol,Accion,Tabla,IP\n';
  const body = rows.map(r =>
    [r.id, r.created_at, r.email, r.user_rol, r.accion, r.tabla_afectada, r.ip]
    .map(v => '"'+(v||'').toString().replace(/"/g,'""')+'"').join(',')
  ).join('\n');
  const blob = new Blob([header+body], {type:'text/csv;charset=utf-8;'});
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url; a.download = 'bitacora_'+new Date().toISOString().slice(0,10)+'.csv';
  a.click(); URL.revokeObjectURL(url);
}

poll();
setInterval(poll, 5000);
</script>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
