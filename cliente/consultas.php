<?php
ob_start();
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/db.php';
secureSessionStart();
requireAuth(['cliente']);
$pdo = getDB();
$uid = $_SESSION['user_id'];

// Obtener cliente
$cli = $pdo->prepare("SELECT c.*, u.nombre, u.email, ua.nombre asesor_nombre, ua.telefono asesor_tel
    FROM clientes c JOIN usuarios u ON u.id=c.usuario_id
    LEFT JOIN usuarios ua ON ua.id=c.asesor_id WHERE c.usuario_id=?");
$cli->execute([$uid]); $cli = $cli->fetch();

// POST — nueva consulta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    if (!csrf_verify()) { echo json_encode(['ok'=>false,'message'=>'Token CSRF inválido. Recarga la página.']); exit; }
    $asunto  = trim($_POST['asunto'] ?? '');
    $mensaje = trim($_POST['mensaje'] ?? '');
    $lote_id = (int)($_POST['lote_id'] ?? 0) ?: null;
    if (!$asunto || !$mensaje) { echo json_encode(['ok'=>false,'message'=>'Completa todos los campos']); exit; }
    if (!$cli) { echo json_encode(['ok'=>false,'message'=>'Perfil de cliente no encontrado']); exit; }
    $pdo->prepare("INSERT INTO consultas_cliente (cliente_id, asesor_id, lote_id, asunto, mensaje) VALUES (?,?,?,?,?)")
        ->execute([$cli['id'], $cli['asesor_id'], $lote_id, $asunto, $mensaje]);
    $newId = $pdo->lastInsertId();
    auditLog("Envió consulta #$newId: $asunto", 'consultas_cliente', $newId);
    echo json_encode(['ok'=>true,'message'=>'Consulta enviada. Tu asesor la verá en tiempo real ✅']);
    exit;
}

// GET — mis consultas
$consultas = $pdo->prepare("SELECT cc.*, l.codigo lote_codigo, l.nombre lote_nombre, u.nombre asesor_nombre
    FROM consultas_cliente cc
    JOIN clientes c ON c.id=cc.cliente_id
    LEFT JOIN lotes l ON l.id=cc.lote_id
    LEFT JOIN usuarios u ON u.id=cc.respondido_por
    WHERE c.usuario_id=? ORDER BY cc.created_at DESC");
$consultas->execute([$uid]); $consultas = $consultas->fetchAll();

$lotes = $pdo->query("SELECT id,codigo,nombre FROM lotes WHERE estado='disponible' AND verificado=1 ORDER BY codigo")->fetchAll();

$pageTitle='Mis Consultas'; $pageSubtitle='Comunícate con tu asesor en tiempo real'; $activeNav='cli-consultas';
require_once __DIR__ . '/../includes/layout.php';
?>

<div class="alert alert-info" style="margin-bottom:20px">
  💬 <strong>Tiempo real:</strong> Tu asesor verá tu consulta en segundos. Las respuestas también te llegarán al instante.
</div>

<!-- Nueva consulta -->
<div class="card" style="margin-bottom:24px">
  <div class="card-header"><h3>✉️ Nueva consulta</h3></div>
  <div class="card-body">
    <form id="form-consulta">
      <div class="form-row">
        <div class="field-group">
          <label>Asunto *</label>
          <input type="text" id="c-asunto" placeholder="¿Sobre qué quieres consultar?" required>
        </div>
        <div class="field-group">
          <label>Lote relacionado (opcional)</label>
          <select id="c-lote">
            <option value="">Sin lote específico</option>
            <?php foreach ($lotes as $l): ?><option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['codigo'].' — '.$l['nombre']) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="field-group" style="margin-bottom:16px">
        <label>Mensaje *</label>
        <textarea id="c-mensaje" rows="4" placeholder="Escribe tu consulta aquí. Tu asesor <?= htmlspecialchars($cli['asesor_nombre'] ?? 'asignado') ?> la verá de inmediato." required style="resize:vertical"></textarea>
      </div>
      <button type="submit" class="btn btn-primary">📤 Enviar consulta</button>
    </form>
  </div>
</div>

<!-- Historial en tiempo real -->
<div class="card">
  <div class="card-header">
    <h3>📋 Mis consultas</h3>
    <div style="display:flex;align-items:center;gap:8px">
      <div id="rt-dot" style="width:8px;height:8px;border-radius:50%;background:#27AE60;animation:pulse 2s infinite"></div>
      <span style="font-size:12px;color:var(--muted)">En vivo</span>
    </div>
  </div>
  <div id="consultas-list" class="card-body p0"></div>
</div>

<style>
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.3}}
.consulta-row{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;flex-direction:column;gap:8px}
.consulta-row:last-child{border-bottom:none}
.consulta-row.nueva{border-left:4px solid var(--blue)}
.consulta-row.respondida{border-left:4px solid var(--green)}
.consulta-top{display:flex;align-items:center;gap:10px;justify-content:space-between}
.consulta-asunto{font-size:14px;font-weight:600}
.consulta-msg{font-size:13px;color:var(--muted);line-height:1.5}
.consulta-resp{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:10px 14px;font-size:13px;color:var(--green)}
.consulta-meta{font-size:11px;color:var(--muted)}
</style>

<script>
// ── Enviar consulta ───────────────────────────────────────────
document.getElementById('form-consulta').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = this.querySelector('button[type=submit]');
  btn.disabled = true; btn.textContent = 'Enviando...';
  const fd = new FormData();
  fd.append('action','send');
  fd.append('asunto', document.getElementById('c-asunto').value);
  fd.append('mensaje', document.getElementById('c-mensaje').value);
  fd.append('lote_id', document.getElementById('c-lote').value);
  try {
    const r = await fetch('/sgi_aurora/cliente/consultas.php', {method:'POST', body:fd});
    const d = await r.json();
    showToast(d.message, d.ok?'ok':'err');
    if (d.ok) {
      document.getElementById('c-asunto').value = '';
      document.getElementById('c-mensaje').value = '';
      document.getElementById('c-lote').value = '';
      loadConsultas();
    }
  } catch(e){ showToast('Error de conexión','err'); }
  finally { btn.disabled=false; btn.textContent='📤 Enviar consulta'; }
});

// ── Cargar y mostrar consultas ────────────────────────────────
function formatDate(d) {
  if (!d) return '—';
  return new Date(d).toLocaleString('es-BO', {day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit'});
}

function renderConsultas(rows) {
  const container = document.getElementById('consultas-list');
  if (!rows.length) {
    container.innerHTML = '<div style="padding:24px;text-align:center;color:var(--muted);font-size:13px">Aún no tienes consultas enviadas</div>';
    return;
  }
  const stBadge = {
    nueva:      '<span class="badge badge-blue">Nueva</span>',
    leida:      '<span class="badge badge-gray">Leída</span>',
    respondida: '<span class="badge badge-green">✓ Respondida</span>',
    cerrada:    '<span class="badge badge-gray">Cerrada</span>',
  };
  container.innerHTML = rows.map(c => `
    <div class="consulta-row ${c.estado}">
      <div class="consulta-top">
        <span class="consulta-asunto">${c.asunto}</span>
        ${stBadge[c.estado]||''}
      </div>
      <div class="consulta-msg">${c.mensaje}</div>
      ${c.lote_codigo ? `<div class="consulta-meta">🏡 Lote: ${c.lote_codigo} — ${c.lote_nombre||''}</div>` : ''}
      <div class="consulta-meta">📅 Enviada: ${formatDate(c.created_at)}</div>
      ${c.estado==='respondida' ? `
        <div class="consulta-resp">
          💬 <strong>Respuesta de ${c.asesor_nombre||'tu asesor'}:</strong><br>${c.respuesta}
          <div style="font-size:11px;color:var(--muted);margin-top:4px">${formatDate(c.respondido_at)}</div>
        </div>
      ` : ''}
    </div>
  `).join('');
}

async function loadConsultas() {
  try {
    const r = await fetch('/sgi_aurora/api_realtime.php?action=mis_respuestas&since=2000-01-01 00:00:00');
    // Full list via dedicated endpoint
    const r2 = await fetch('/sgi_aurora/cliente/consultas_list.php');
    if (r2.ok) { const d = await r2.json(); renderConsultas(d); }
  } catch(e) {}
}

// Polling cada 5 segundos
loadConsultas();
let sinceTs = new Date(Date.now() - 3600*1000).toISOString().slice(0,19).replace('T',' ');
setInterval(async () => {
  try {
    const r = await fetch(`/sgi_aurora/api_realtime.php?action=badge_count`);
    const d = await r.json();
    // Si hay respuestas nuevas recargar lista
    loadConsultas();
  } catch(e) {}
}, 5000);

// Initial load
window.addEventListener('DOMContentLoaded', loadConsultas);
</script>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
