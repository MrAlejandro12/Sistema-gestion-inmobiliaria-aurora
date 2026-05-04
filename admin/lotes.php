<?php
require_once __DIR__ . '/../config/db.php';
secureSessionStart();
requireAuth(['admin', 'asesor']);

$pdo = getDB();

// ── API (POST) ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
        $codigo   = trim($_POST['codigo'] ?? '');
        $nombre   = trim($_POST['nombre'] ?? '');
        $zona_id  = (int)($_POST['zona_id'] ?? 1);
        $dir      = trim($_POST['direccion'] ?? '');
        $sup      = (float)($_POST['superficie'] ?? 0);
        $precio   = (float)($_POST['precio'] ?? 0);
        $tipo     = $_POST['tipo'] ?? 'Residencial';
        $servicios= implode(',', $_POST['servicios'] ?? []);

        if (!$codigo || !$nombre || $sup <= 0 || $precio <= 0) {
            echo json_encode(['ok'=>false,'message'=>'Campos obligatorios incompletos']); exit;
        }
        try {
            if ($action === 'create') {
                $stmt = $pdo->prepare("INSERT INTO lotes (codigo,nombre,zona_id,direccion,superficie,precio,tipo,servicios,estado) VALUES (?,?,?,?,?,?,?,?,'revision')");
                $stmt->execute([$codigo,$nombre,$zona_id,$dir,$sup,$precio,$tipo,$servicios]);
                $newId = $pdo->lastInsertId();
                // Crear tarea de verificación legal automáticamente
                $uid_reg = $_SESSION['user_id'] ?? 1;
                $pdo->prepare("INSERT INTO tareas_legales (lote_id, solicitado_por, estado, prioridad) VALUES (?,?,'pendiente','normal')")
                    ->execute([$newId, $uid_reg]);
                auditLog("Registró lote $codigo: $nombre", 'lotes', $newId);
                echo json_encode(['ok'=>true,'message'=>"Lote $codigo registrado — enviado a verificación legal",'reload'=>true]);
            } else {
                $stmt = $pdo->prepare("UPDATE lotes SET codigo=?,nombre=?,zona_id=?,direccion=?,superficie=?,precio=?,tipo=?,servicios=? WHERE id=?");
                $stmt->execute([$codigo,$nombre,$zona_id,$dir,$sup,$precio,$tipo,$servicios,$id]);
                auditLog("Actualizó lote $codigo", 'lotes', $id);
                echo json_encode(['ok'=>true,'message'=>"Lote actualizado",'reload'=>true]);
            }
        } catch (PDOException $e) {
            echo json_encode(['ok'=>false,'message'=>'Error: '.$e->getMessage()]);
        }
        exit;
    }

    if ($action === 'delete') {
        $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
        $stmtLote = $pdo->prepare("SELECT codigo, estado FROM lotes WHERE id = ?");
        $stmtLote->execute([$id]);
        $l = $stmtLote->fetch();
        if ($l !== false && in_array($l['estado'], ['disponible','revision'])) {
            $pdo->prepare("DELETE FROM lotes WHERE id=?")->execute([$id]);
            auditLog("Eliminó lote {$l['codigo']}", 'lotes', $id);
            echo json_encode(['ok'=>true,'message'=>"Lote {$l['codigo']} eliminado"]);
        } else {
            echo json_encode(['ok'=>false,'message'=>'No se puede eliminar un lote vendido o apartado']);
        }
        exit;
    }

    if ($action === 'cambiar_estado') {
        $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
        $estado = $_POST['estado'] ?? '';
        $allowed = ['disponible','apartado','revision'];
        if (in_array($estado, $allowed)) {
            $pdo->prepare("UPDATE lotes SET estado=?, verificado=? WHERE id=?")->execute([$estado, $estado==='disponible'?1:0, $id]);
            auditLog("Cambió estado del lote #$id a $estado", 'lotes', $id);
            echo json_encode(['ok'=>true,'message'=>"Estado actualizado a: $estado",'reload'=>true]);
        } else {
            echo json_encode(['ok'=>false,'message'=>'Estado no permitido']);
        }
        exit;
    }
}

// ── GET: listar lotes ────────────────────────────────────────
$filtroZona   = (int)($_GET['zona'] ?? 0);
$filtroEstado = $_GET['estado'] ?? '';
$q            = trim($_GET['q'] ?? '');

$sql = "SELECT l.*, z.nombre zona_nombre, u.nombre asesor_nombre
        FROM lotes l
        JOIN zonas z ON z.id=l.zona_id
        LEFT JOIN usuarios u ON u.id=l.asesor_id
        WHERE 1=1";
$params = [];
if ($filtroZona)   { $sql .= " AND l.zona_id=?";      $params[] = $filtroZona; }
if ($filtroEstado) { $sql .= " AND l.estado=?";        $params[] = $filtroEstado; }
if ($q)            { $sql .= " AND (l.codigo LIKE ? OR l.nombre LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; }
$sql .= " ORDER BY l.created_at DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$lotes = $stmt->fetchAll();

$zonas = $pdo->query("SELECT * FROM zonas ORDER BY nombre")->fetchAll();

$pageTitle    = 'Gestión de Lotes';
$pageSubtitle = count($lotes) . ' lote(s) encontrado(s)';
$activeNav    = 'ad-lotes';
require_once __DIR__ . '/../includes/layout.php';
?>

<!-- Filtros -->
<form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;align-items:flex-end">
  <div class="field-group" style="width:160px"><label>Zona</label>
    <select name="zona"><option value="">Todas</option>
      <?php foreach ($zonas as $z): ?><option value="<?= $z['id'] ?>" <?= $filtroZona==$z['id']?'selected':'' ?>><?= htmlspecialchars($z['nombre'] ?? '', ENT_QUOTES|ENT_HTML5, 'UTF-8') ?></option><?php endforeach; ?>
    </select></div>
  <div class="field-group" style="width:140px"><label>Estado</label>
    <select name="estado">
      <option value="">Todos</option>
      <option value="disponible" <?= $filtroEstado==='disponible'?'selected':'' ?>>Disponible</option>
      <option value="apartado"   <?= $filtroEstado==='apartado'?'selected':'' ?>>Apartado</option>
      <option value="vendido"    <?= $filtroEstado==='vendido'?'selected':'' ?>>Vendido</option>
      <option value="revision"   <?= $filtroEstado==='revision'?'selected':'' ?>>En revisión</option>
    </select></div>
  <div class="field-group" style="width:200px"><label>Buscar</label>
    <input type="text" name="q" placeholder="Código o nombre..." value="<?= htmlspecialchars($q) ?>"></div>
  <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end">🔍 Filtrar</button>
  <a href="?" class="btn btn-outline btn-sm" style="align-self:flex-end">Limpiar</a>
  <button type="button" onclick="openModal('modal-lote')" class="btn btn-gold btn-sm" style="align-self:flex-end;margin-left:auto">➕ Nuevo lote</button>
</form>

<!-- Tabla -->
<div class="card">
  <div class="card-body p0">
    <table class="table">
      <thead><tr><th>Código</th><th>Nombre</th><th>Zona</th><th>Sup.</th><th>Precio</th><th>Tipo</th><th>Estado</th><th>Legal</th><th>Asesor</th><th>Acciones</th></tr></thead>
      <tbody>
        <?php foreach ($lotes as $l):
          $bc = ['disponible'=>'badge-green','apartado'=>'badge-orange','vendido'=>'badge-blue','revision'=>'badge-gold'];
        ?>
        <tr>
          <td><strong><?= htmlspecialchars($l['codigo']) ?></strong></td>
          <td><?= htmlspecialchars($l['nombre']) ?></td>
          <td>📍 <?= htmlspecialchars($l['zona_nombre']) ?></td>
          <td><?= number_format($l['superficie'],0) ?> m²</td>
          <td><strong>$<?= number_format($l['precio'],0,',','.') ?></strong></td>
          <td><?= $l['tipo'] ?></td>
          <td><span class="badge <?= $bc[$l['estado']] ?? 'badge-gray' ?>"><?= $l['estado'] ?></span></td>
          <td><?= $l['verificado'] ? '<span class="badge badge-green">✓ DDRR</span>' : '<span class="badge badge-orange">Pendiente</span>' ?></td>
          <td style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($l['asesor_nombre'] ?? '—') ?></td>
          <td>
            <button class="btn btn-outline btn-sm" onclick="editLote(<?= htmlspecialchars(json_encode($l)) ?>)">✏️</button>
            <?php if (in_array($l['estado'], ['disponible','revision'])): ?>
              <button class="btn btn-red btn-sm" onclick="confirmDelete('/sgi_aurora/admin/lotes.php','¿Eliminar lote <?= addslashes($l['codigo']) ?>?')" data-id="<?= $l['id'] ?>">🗑️</button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($lotes)): ?>
          <tr><td colspan="10" style="text-align:center;padding:24px;color:var(--muted)">No hay lotes con esos filtros</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal registrar/editar lote -->
<div class="modal-bg" id="modal-lote">
  <div class="modal">
    <div class="modal-header">
      <h3 id="modal-lote-title">🏡 Registrar nuevo lote</h3>
      <button class="modal-close" onclick="closeModal('modal-lote')">×</button>
    </div>
    <form id="form-lote" method="POST" action="/sgi_aurora/admin/lotes.php">
    <?= csrf_field() ?>
      <div class="modal-body">
        <input type="hidden" name="action" id="lote-action" value="create">
        <input type="hidden" name="id"     id="lote-id"     value="0">
        <div class="form-row">
          <div class="field-group"><label>Código *</label><input type="text" name="codigo" id="lote-codigo" placeholder="EQ-L007" required></div>
          <div class="field-group"><label>Nombre *</label><input type="text" name="nombre" id="lote-nombre" placeholder="Lote Residencial ..." required></div>
        </div>
        <div class="form-row">
          <div class="field-group"><label>Zona *</label>
            <select name="zona_id" id="lote-zona">
              <?php foreach ($zonas as $z): ?><option value="<?= $z['id'] ?>"><?= htmlspecialchars($z['nombre'] ?? '', ENT_QUOTES|ENT_HTML5, 'UTF-8') ?></option><?php endforeach; ?>
            </select></div>
          <div class="field-group"><label>Tipo</label>
            <select name="tipo" id="lote-tipo"><option>Residencial</option><option>Comercial</option><option>Industrial</option></select></div>
        </div>
        <div class="form-row">
          <div class="field-group"><label>Superficie (m²) *</label><input type="number" name="superficie" id="lote-sup" placeholder="350" min="1" required></div>
          <div class="field-group"><label>Precio (USD) *</label><input type="number" name="precio" id="lote-precio" placeholder="32000" min="1" required></div>
        </div>
        <div class="form-row single">
          <div class="field-group"><label>Dirección / Referencia</label><input type="text" name="direccion" id="lote-dir" placeholder="Calle 5, entre Av. Beni y Brasil"></div>
        </div>
        <div class="field-group">
          <label>Servicios disponibles</label>
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:6px" id="svc-checks">
            <?php foreach (['Agua','Luz','Gas','Drenaje','Internet','Seguridad'] as $svc): ?>
              <label style="display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:8px;background:var(--bg);border:1px solid var(--border);cursor:pointer;font-size:13px">
                <input type="checkbox" name="servicios[]" value="<?= $svc ?>"> <?= $svc ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="alert alert-info" style="margin-top:16px">ℹ️ El lote iniciará en estado <strong>EN_REVISIÓN</strong> hasta que Legal lo verifique en DDRR.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-lote')">Cancelar</button>
        <button type="submit" class="btn btn-primary" data-label="Guardar lote">💾 Guardar lote</button>
      </div>
    </form>
  </div>
</div>

<script>
document.getElementById('form-lote').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = this.querySelector('[type=submit]');
  if(btn){ btn.disabled=true; btn.textContent='Guardando...'; }
  try {
    const res = await fetch(window.location.pathname, {method:'POST', body:new FormData(this)});
    const data = await res.json();
    if(data.ok) {
      showToast(data.message||'Lote guardado', 'ok');
      closeModal('modal-lote');
      setTimeout(()=>location.reload(), 900);
    } else {
      showToast(data.message||'Error', 'err');
    }
  } catch(e){ showToast('Error de conexión','err'); }
  finally { if(btn){ btn.disabled=false; btn.textContent=btn.dataset.label||'Guardar lote'; } }
});

function editLote(l) {
  document.getElementById('modal-lote-title').textContent = '✏️ Editar lote ' + l.codigo;
  document.getElementById('lote-action').value  = 'update';
  document.getElementById('lote-id').value      = l.id;
  document.getElementById('lote-codigo').value  = l.codigo;
  document.getElementById('lote-nombre').value  = l.nombre;
  document.getElementById('lote-zona').value    = l.zona_id;
  document.getElementById('lote-tipo').value    = l.tipo;
  document.getElementById('lote-sup').value     = l.superficie;
  document.getElementById('lote-precio').value  = l.precio;
  document.getElementById('lote-dir').value     = l.direccion || '';
  // servicios
  const svcs = (l.servicios || '').split(',');
  document.querySelectorAll('#svc-checks input[type=checkbox]').forEach(cb => {
    cb.checked = svcs.includes(cb.value);
  });
  openModal('modal-lote');
}

// override confirmDelete para pasar el id
document.querySelectorAll('.btn-red[data-id]').forEach(btn => {
  btn.addEventListener('click', function(e) {
    e.preventDefault();
    if(confirm(this.title || '¿Eliminar este lote?')) {
      fetch('/sgi_aurora/admin/lotes.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: 'action=delete&id=' + this.dataset.id
      }).then(r=>r.json()).then(d=>{
        showToast(d.message, d.ok?'ok':'err');
        if(d.ok) setTimeout(()=>location.reload(), 800);
      });
    }
  });
});
</script>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
