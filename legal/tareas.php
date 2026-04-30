<?php
require_once __DIR__ . '/../config/db.php';
secureSessionStart();
requireAuth(['legal', 'admin']);
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'aprobar') {
        // Actualizar tarea
        $pdo->prepare("UPDATE tareas_legales SET estado='aprobado', doc_escritura='verificado', doc_ci='verificado', doc_catastral='verificado', asignado_a=?, fecha_resolucion=CURDATE() WHERE id=?")
            ->execute([$_SESSION['user_id'], $id]);
        // Lote → disponible + verificado
        $tarea = $pdo->prepare("SELECT lote_id, venta_id FROM tareas_legales WHERE id=?"); $tarea->execute([$id]); $t = $tarea->fetch();
        if ($t) {
            $pdo->prepare("UPDATE lotes SET estado='disponible', verificado=1 WHERE id=?")->execute([$t['lote_id']]);
            // Crear contrato si hay venta
            if ($t['venta_id']) {
                $exists = $pdo->prepare("SELECT id FROM contratos WHERE venta_id=?"); $exists->execute([$t['venta_id']]);
                if (!$exists->fetch()) {
                    $pdo->prepare("INSERT INTO contratos (venta_id,tarea_id,generado_por,estado) VALUES (?,?,?,'generado')")
                        ->execute([$t['venta_id'], $id, $_SESSION['user_id']]);
                    $pdo->prepare("UPDATE ventas SET estado='contrato' WHERE id=?")->execute([$t['venta_id']]);
                }
            }
        }
        auditLog("Aprobó verificación legal tarea #$id — lote habilitado", 'tareas_legales', $id);
        echo json_encode(['ok'=>true,'message'=>'Tarea aprobada — Lote habilitado en catálogo y contrato generado','reload'=>true]); exit;
    }

    if ($action === 'observar' || $action === 'rechazar') {
        $obs = trim($_POST['observaciones'] ?? '');
        $estado = $action === 'rechazar' ? 'rechazado' : 'observado';
        $pdo->prepare("UPDATE tareas_legales SET estado=?, observaciones=?, asignado_a=?, fecha_resolucion=CURDATE() WHERE id=?")
            ->execute([$estado, $obs, $_SESSION['user_id'], $id]);
        auditLog("$estado verificación legal tarea #$id: $obs", 'tareas_legales', $id);
        echo json_encode(['ok'=>true,'message'=>"Tarea $estado. Asesor notificado.",'reload'=>true]); exit;
    }

    if ($action === 'update_doc') {
        $doc   = $_POST['doc']   ?? '';
        $valor = $_POST['valor'] ?? 'pendiente';
        $cols  = ['escritura'=>'doc_escritura','ci'=>'doc_ci','catastral'=>'doc_catastral'];
        if (isset($cols[$doc])) {
            $pdo->prepare("UPDATE tareas_legales SET {$cols[$doc]}=? WHERE id=?")->execute([$valor, $id]);
            echo json_encode(['ok'=>true,'message'=>'Documento actualizado']); exit;
        }
    }
}

$tareas = $pdo->query("
  SELECT t.*, l.nombre lote_nombre, l.codigo, l.zona_id, l.superficie, l.precio,
         z.nombre zona_nombre, uc.nombre cliente_nombre, ua.nombre asesor_nombre
  FROM tareas_legales t
  JOIN lotes l ON l.id=t.lote_id
  JOIN zonas z ON z.id=l.zona_id
  JOIN usuarios ua ON ua.id=t.solicitado_por
  LEFT JOIN ventas v  ON v.id=t.venta_id
  LEFT JOIN clientes c ON c.id=v.cliente_id
  LEFT JOIN usuarios uc ON uc.id=c.usuario_id
  ORDER BY FIELD(t.estado,'pendiente','en_proceso','observado','aprobado','rechazado'), t.prioridad DESC, t.created_at ASC
")->fetchAll();

$pageTitle='Tareas de Verificación'; $pageSubtitle='Módulo Legal — DDRR'; $activeNav='lg-tar';
require_once __DIR__ . '/../includes/layout.php';
?>

<div class="alert alert-warn">
  ⚖️ <strong>Solo el rol LEGAL puede aprobar o rechazar verificaciones.</strong> El cambio a DISPONIBLE se ejecuta en la base de datos — no puede evadirse desde la interfaz.
</div>

<?php foreach ($tareas as $t):
  $pend = $t['estado'] === 'pendiente' || $t['estado'] === 'en_proceso';
  $colors = ['pendiente'=>'var(--orange)','en_proceso'=>'var(--blue)','aprobado'=>'var(--green)','observado'=>'var(--orange)','rechazado'=>'var(--red)'];
  $bc = ['pendiente'=>'badge-orange','en_proceso'=>'badge-blue','aprobado'=>'badge-green','observado'=>'badge-gold','rechazado'=>'badge-red'];
?>
<div style="background:#fff;border-radius:16px;border:1px solid var(--border);border-left:4px solid <?= $colors[$t['estado']] ?? 'var(--muted)' ?>;padding:20px 24px;margin-bottom:14px">
  <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px">
    <div>
      <div style="font-size:15px;font-weight:600">🔍 <?= htmlspecialchars($t['codigo'].' — '.$t['lote_nombre']) ?></div>
      <div style="font-size:13px;color:var(--muted);margin-top:4px;display:flex;gap:16px;flex-wrap:wrap">
        <span>📍 <?= htmlspecialchars($t['zona_nombre']) ?></span>
        <span>📐 <?= number_format($t['superficie'],0) ?> m²</span>
        <span>💰 $<?= number_format($t['precio'],0,',','.') ?></span>
        <span>📅 <?= date('d/m/Y', strtotime($t['created_at'])) ?></span>
        <span>🤝 <?= htmlspecialchars($t['asesor_nombre']) ?></span>
        <?php if ($t['cliente_nombre']): ?><span>👤 <?= htmlspecialchars($t['cliente_nombre']) ?></span><?php endif; ?>
      </div>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
      <span class="badge <?= $bc[$t['estado']] ?? 'badge-gray' ?>"><?= $t['estado'] ?></span>
      <span class="badge <?= $t['prioridad']==='alta'?'badge-red':($t['prioridad']==='urgente'?'badge-red':'badge-gray') ?>"><?= $t['prioridad'] ?></span>
    </div>
  </div>

  <!-- Documentos -->
  <div style="margin-bottom:14px">
    <div style="font-size:13px;font-weight:600;margin-bottom:8px">📎 Documentos requeridos:</div>
    <div style="display:flex;flex-direction:column;gap:6px">
      <?php foreach ([['escritura','📋 Escritura de propiedad'],['ci','🪪 CI del comprador'],['catastral','🏛️ Certificado catastral']] as [$field,$label]):
        $val = $t["doc_$field"];
      ?>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:#f8fafc;border-radius:8px;border:1px solid var(--border)">
        <span style="font-size:13px"><?= $label ?></span>
        <div style="display:flex;align-items:center;gap:8px">
          <span class="badge <?= $val==='verificado'?'badge-green':'badge-orange' ?>"><?= $val ?></span>
          <?php if ($pend): ?>
            <button class="btn btn-outline btn-sm" onclick="updateDoc(<?= $t['id'] ?>,'<?= $field ?>','<?= $val==='verificado'?'pendiente':'verificado' ?>')">
              <?= $val==='verificado' ? '↩ Revertir' : '✓ Marcar OK' ?>
            </button>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if ($t['observaciones']): ?>
    <div class="alert alert-warn" style="margin-bottom:14px">⚠️ <?= htmlspecialchars($t['observaciones']) ?></div>
  <?php endif; ?>

  <!-- Acciones -->
  <?php if ($pend): ?>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <button class="btn btn-green" onclick="aprobar(<?= $t['id'] ?>)">✅ Aprobar y generar contrato</button>
    <button class="btn btn-orange" onclick="observar(<?= $t['id'] ?>, 'observar')">⚠️ Observar</button>
    <button class="btn btn-red"    onclick="observar(<?= $t['id'] ?>, 'rechazar')">❌ Rechazar</button>
  </div>
  <?php else: ?>
  <div style="display:flex;gap:10px">
    <span class="badge <?= $bc[$t['estado']] ?? 'badge-gray' ?>" style="padding:8px 14px;font-size:12px">
      <?= $t['estado']==='aprobado' ? '✅ APROBADO' : strtoupper($t['estado']) ?> — <?= $t['fecha_resolucion'] ? date('d/m/Y', strtotime($t['fecha_resolucion'])) : '' ?>
    </span>
  </div>
  <?php endif; ?>
</div>
<?php endforeach;
if (empty($tareas)): ?>
  <div class="alert alert-success">✅ No hay tareas de verificación pendientes.</div>
<?php endif; ?>

<!-- Modal observación -->
<div class="modal-bg" id="modal-obs">
  <div class="modal">
    <div class="modal-header"><h3 id="modal-obs-title">Observar tarea</h3><button class="modal-close" onclick="closeModal('modal-obs')">×</button></div>
    <form id="form-obs" method="POST" action="/sgi_aurora/legal/tareas.php">
    <?= csrf_field() ?>
      <input type="hidden" name="id" id="obs-id">
      <input type="hidden" name="action" id="obs-action">
      <div class="modal-body">
        <div class="field-group"><label>Observaciones / Motivo *</label>
          <textarea name="observaciones" rows="4" placeholder="Detalla el problema encontrado en los documentos..." required></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-obs')">Cancelar</button>
        <button type="submit" class="btn btn-orange" data-label="Confirmar">Confirmar</button>
      </div>
    </form>
  </div>
</div>

<script>
submitForm('form-obs', 'Observación registrada. Asesor notificado.');

function aprobar(id) {
  if(!confirm('¿Aprobar esta verificación? El lote quedará DISPONIBLE en el catálogo.')) return;
  fetch('/sgi_aurora/legal/tareas.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'action=aprobar&id='+id})
    .then(r=>r.json()).then(d=>{ showToast(d.message, d.ok?'ok':'err'); if(d.ok) setTimeout(()=>location.reload(),900); });
}
function observar(id, tipo) {
  document.getElementById('obs-id').value = id;
  document.getElementById('obs-action').value = tipo;
  document.getElementById('modal-obs-title').textContent = tipo==='rechazar' ? '❌ Rechazar tarea' : '⚠️ Observar tarea';
  openModal('modal-obs');
}
function updateDoc(id, doc, valor) {
  fetch('/sgi_aurora/legal/tareas.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`action=update_doc&id=${id}&doc=${doc}&valor=${valor}`})
    .then(r=>r.json()).then(d=>{ showToast(d.message, d.ok?'ok':'err'); if(d.ok) setTimeout(()=>location.reload(),600); });
}
</script>
<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
