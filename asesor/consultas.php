<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/db.php';
secureSessionStart();
requireAuth(['asesor','admin']);
$pdo = getDB();
$uid = $_SESSION['user_id'];
$rol = $_SESSION['rol'];

// POST — responder consulta
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!csrf_verify()) { echo json_encode(['ok'=>false,'message'=>'Token CSRF inválido']); exit; }
    $id   = (int)($_POST['id'] ?? 0);
    $resp = trim($_POST['respuesta'] ?? '');
    if (!$id || !$resp) { echo json_encode(['ok'=>false,'message'=>'Datos incompletos']); exit; }
    // Verificar que la consulta es de un cliente del asesor
    $check = $pdo->prepare("SELECT cc.id FROM consultas_cliente cc JOIN clientes c ON c.id=cc.cliente_id WHERE cc.id=? AND (c.asesor_id=? OR ?='admin')");
    $check->execute([$id,$uid,$rol]); 
    if (!$check->fetch()) { echo json_encode(['ok'=>false,'message'=>'Sin permiso']); exit; }
    $pdo->prepare("UPDATE consultas_cliente SET estado='respondida', respuesta=?, respondido_por=?, respondido_at=NOW() WHERE id=?")
        ->execute([$resp, $uid, $id]);
    auditLog("Respondió consulta de cliente #$id", 'consultas_cliente', $id);
    echo json_encode(['ok'=>true,'message'=>'Respuesta enviada al cliente ✅','reload'=>true]); exit;
}

$pageTitle = 'Consultas de Clientes';
$pageSubtitle = 'Bandeja en tiempo real';
$activeNav  = 'as-consultas';
require_once __DIR__ . '/../includes/layout.php';
?>

<div class="alert alert-info" style="margin-bottom:20px">
  🔴 <strong>Tiempo real:</strong> Las consultas nuevas aparecen automáticamente cada 5 segundos, sin recargar la página.
</div>

<!-- Stats -->
<div class="kpi-grid" style="margin-bottom:24px">
  <div class="kpi-card blue"><div class="kpi-icon">📬</div><div class="kpi-label">Nuevas sin leer</div><div class="kpi-value" id="kpi-nuevas">—</div></div>
  <div class="kpi-card green"><div class="kpi-icon">✅</div><div class="kpi-label">Respondidas hoy</div><div class="kpi-value" id="kpi-resp">—</div></div>
  <div class="kpi-card gold"><div class="kpi-icon">👥</div><div class="kpi-label">Clientes activos</div><div class="kpi-value" id="kpi-cli">—</div></div>
</div>

<!-- Consultas en tiempo real -->
<div class="card">
  <div class="card-header">
    <h3>📨 Consultas de mis clientes</h3>
    <div style="display:flex;align-items:center;gap:10px">
      <div id="rt-indicator" style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--muted)">
        <div style="width:8px;height:8px;border-radius:50%;background:#27AE60;animation:pulse 2s infinite"></div>
        En vivo
      </div>
      <span id="last-update" style="font-size:11px;color:var(--muted)"></span>
    </div>
  </div>
  <div id="consultas-board" class="card-body p0">
    <div style="padding:24px;text-align:center;color:var(--muted);font-size:13px">Cargando consultas...</div>
  </div>
</div>

<!-- Modal responder -->
<div class="modal-bg" id="modal-respuesta">
  <div class="modal">
    <div class="modal-header">
      <h3>💬 Responder consulta</h3>
      <button class="modal-close" onclick="closeModal('modal-respuesta')">×</button>
    </div>
    <form id="form-respuesta" method="POST" action="/sgi_aurora/asesor/consultas.php">
    <?= csrf_field() ?>
      <input type="hidden" name="id" id="resp-id">
      <div class="modal-body">
        <div id="resp-preview" style="background:var(--bg);border-radius:10px;padding:14px;margin-bottom:16px;font-size:13px;color:var(--muted)"></div>
        <div class="field-group">
          <label>Tu respuesta *</label>
          <textarea name="respuesta" rows="4" placeholder="Escribe tu respuesta al cliente..." required style="resize:vertical"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-respuesta')">Cancelar</button>
        <button type="submit" class="btn btn-primary" data-label="Enviar respuesta">📤 Enviar respuesta</button>
      </div>
    </form>
  </div>
</div>

<style>
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.3}}
@keyframes slideIn{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:none}}
.consulta-card{padding:18px 22px;border-bottom:1px solid var(--border);display:grid;grid-template-columns:1fr auto;gap:14px;align-items:start;transition:background .15s}
.consulta-card:last-child{border-bottom:none}
.consulta-card:hover{background:#fafbfc}
.consulta-card.nueva{border-left:4px solid var(--blue);background:rgba(30,144,255,.03)}
.consulta-card.nueva .consulta-asunto::before{content:'🆕 '}
.consulta-card.respondida{border-left:4px solid var(--green)}
.consulta-card.is-new-anim{animation:slideIn .4s ease}
.consulta-asunto{font-size:14px;font-weight:600;color:var(--navy);margin-bottom:4px}
.consulta-cliente{font-size:13px;color:var(--blue);font-weight:500;margin-bottom:4px}
.consulta-msg{font-size:13px;color:var(--muted);line-height:1.5;margin-bottom:6px}
.consulta-meta{font-size:11px;color:var(--muted)}
.consulta-resp-preview{background:#f0fdf4;border-radius:6px;padding:8px 10px;font-size:12px;color:var(--green);margin-top:6px}
</style>

<script>
let knownIds = new Set();
let isFirst = true;
let consultasData = [];

function formatDate(d) {
  if (!d) return '-';
  const dt = new Date(d);
  const now = new Date();
  const diff = (now - dt) / 1000;
  if (diff < 60) return 'hace ' + Math.floor(diff) + 's';
  if (diff < 3600) return 'hace ' + Math.floor(diff/60) + 'min';
  return dt.toLocaleString('es-BO',{day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit'});
}

function openRespuesta(id) {
  const c = consultasData.find(x => x.id === id);
  if (!c) return;
  document.getElementById('resp-id').value = id;
  const preview = document.getElementById('resp-preview');
  while (preview.firstChild) preview.removeChild(preview.firstChild);
  const strong = document.createElement('strong');
  strong.textContent = c.cliente_nombre;
  const br = document.createElement('br');
  const span = document.createElement('span');
  span.textContent = c.asunto + ': ' + c.mensaje;
  preview.appendChild(strong);
  preview.appendChild(br);
  preview.appendChild(span);
  openModal('modal-respuesta');
}

function renderBoard(rows) {
  consultasData = rows;
  const board = document.getElementById('consultas-board');
  const newIds = new Set(rows.map(r => r.id));
  const addedNew = !isFirst && [...newIds].some(id => !knownIds.has(id));

  if (!rows.length) {
    board.innerHTML = '<div style="padding:32px;text-align:center;color:var(--muted);font-size:13px">Sin consultas pendientes</div>';
    knownIds = newIds; isFirst = false; return;
  }

  const nuevas = rows.filter(r=>r.estado==='nueva').length;
  const respHoy = rows.filter(r=>r.estado==='respondida' && r.respondido_at && r.respondido_at.slice(0,10)===new Date().toISOString().slice(0,10)).length;
  const clis = new Set(rows.map(r=>r.cliente_id)).size;
  document.getElementById('kpi-nuevas').textContent = nuevas;
  document.getElementById('kpi-resp').textContent   = respHoy;
  document.getElementById('kpi-cli').textContent    = clis;

  board.innerHTML = '';
  rows.forEach(function(c) {
    const isNew = !knownIds.has(c.id);
    const card = document.createElement('div');
    card.className = 'consulta-card ' + c.estado + (isNew && !isFirst ? ' is-new-anim' : '');

    const left = document.createElement('div');

    const cli = document.createElement('div');
    cli.className = 'consulta-cliente';
    cli.textContent = c.cliente_nombre;
    const emailSpan = document.createElement('span');
    emailSpan.style.cssText = 'font-size:11px;color:var(--muted);margin-left:6px';
    emailSpan.textContent = c.cliente_email || '';
    cli.appendChild(emailSpan);

    const asuntoEl = document.createElement('div');
    asuntoEl.className = 'consulta-asunto';
    asuntoEl.textContent = c.asunto;

    const msgEl = document.createElement('div');
    msgEl.className = 'consulta-msg';
    msgEl.textContent = c.mensaje;

    const metaEl = document.createElement('div');
    metaEl.className = 'consulta-meta';
    metaEl.textContent = formatDate(c.created_at) + ' #' + c.id;

    left.appendChild(cli);
    left.appendChild(asuntoEl);
    left.appendChild(msgEl);

    if (c.lote_codigo) {
      const loteEl = document.createElement('div');
      loteEl.className = 'consulta-meta';
      loteEl.textContent = 'Lote: ' + c.lote_codigo + ' - ' + (c.lote_nombre || '');
      left.appendChild(loteEl);
    }

    left.appendChild(metaEl);

    if (c.estado === 'respondida' && c.respuesta) {
      const respEl = document.createElement('div');
      respEl.className = 'consulta-resp-preview';
      respEl.textContent = 'Respondida: ' + c.respuesta.slice(0, 80) + (c.respuesta.length > 80 ? '...' : '');
      left.appendChild(respEl);
    }

    const right = document.createElement('div');
    right.style.cssText = 'flex-shrink:0;display:flex;flex-direction:column;gap:6px;align-items:flex-end';

    const badge = document.createElement('span');
    badge.className = 'badge ' + (c.estado === 'nueva' ? 'badge-blue' : 'badge-green');
    badge.textContent = c.estado;
    right.appendChild(badge);

    if (c.estado === 'nueva') {
      const btn = document.createElement('button');
      btn.className = 'btn btn-primary btn-sm';
      btn.textContent = 'Responder';
      btn.addEventListener('click', function() { openRespuesta(c.id); });
      right.appendChild(btn);
    }

    card.appendChild(left);
    card.appendChild(right);
    board.appendChild(card);
  });

  if (addedNew) showToast('Nueva consulta de un cliente!', '');
  knownIds = newIds;
  isFirst = false;
  document.getElementById('last-update').textContent = 'Actualizado: ' + new Date().toLocaleTimeString('es-BO');
}

async function poll() {
  try {
    const r = await fetch('/sgi_aurora/api_realtime.php?action=mis_consultas&since=2000-01-01 00:00:00');
    const d = await r.json();
    if (d.ok) renderBoard(d.data);
  } catch(e) {
    document.getElementById('rt-indicator').innerHTML = '<span style="color:var(--red)">Sin conexion</span>';
  }
}

document.addEventListener('DOMContentLoaded', function() {
  submitForm('form-respuesta', 'Respuesta enviada al cliente');
  poll();
  setInterval(poll, 5000);
});
</script>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
