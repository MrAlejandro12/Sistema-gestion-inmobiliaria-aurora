<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/db.php';
secureSessionStart();
requireAuth(['asesor','admin']);
$pdo = getDB();
$uid = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action==='create_venta') {
        $cid    = (int)($_POST['cliente_id'] ?? 0);
        $lid    = (int)($_POST['lote_id'] ?? 0);
        $monto  = (float)($_POST['monto'] ?? 0);
        $modal  = $_POST['modalidad'] ?? 'Contado';
        if (!$cid||!$lid||$monto<=0) { echo json_encode(['ok'=>false,'message'=>'Datos incompletos']); exit; }
        // Verificar lote disponible y verificado
        $stmtLote2 = $pdo->prepare("SELECT * FROM lotes WHERE id = ? AND estado = 'disponible' AND verificado = 1");
        $stmtLote2->execute([$lid]);
        $l = $stmtLote2->fetch();
        if ($l === false) { echo json_encode(['ok'=>false,'message'=>'El lote no está disponible o no tiene verificación legal']); exit; }
        $pdo->prepare("INSERT INTO ventas (cliente_id,lote_id,asesor_id,monto,modalidad,fecha_venta) VALUES (?,?,?,?,?,CURDATE())")
            ->execute([$cid,$lid,$_SESSION['user_id'],$monto,$modal]);
        $vid=$pdo->lastInsertId();
        $pdo->prepare("UPDATE lotes SET estado='apartado' WHERE id=?")->execute([$lid]);
        auditLog("Registró venta V$vid: $modal USD $monto", 'ventas', $vid);
        echo json_encode(['ok'=>true,'message'=>"Venta registrada — Lote apartado. Ahora puedes solicitar verificación legal.",'reload'=>true]); exit;
    }

    if ($action==='registrar_pago') {
        $vid   = (int)($_POST['venta_id'] ?? 0);
        $monto = (float)($_POST['monto'] ?? 0);
        $met   = $_POST['metodo'] ?? 'Transferencia';
        $comp  = trim($_POST['comprobante'] ?? '');
        if ($monto<=0) { echo json_encode(['ok'=>false,'message'=>'El monto es obligatorio']); exit; }
        if ($monto>1000 && !$comp) { echo json_encode(['ok'=>false,'message'=>'⚠️ Ley 393: Comprobante obligatorio para montos superiores a USD 1,000']); exit; }
        $stmtVenta = $pdo->prepare("SELECT cliente_id FROM ventas WHERE id = ?");
        $stmtVenta->execute([$vid]);
        $v = $stmtVenta->fetch();
        if ($v === false) { echo json_encode(['ok'=>false,'message'=>'Venta no encontrada']); exit; }
        $pdo->prepare("INSERT INTO pagos (venta_id,cliente_id,monto,metodo,comprobante,fecha_pago,registrado_por) VALUES (?,?,?,?,?,CURDATE(),?)")
            ->execute([$vid,$v['cliente_id'],$monto,$met,$comp,$_SESSION['user_id']]);
        $pid=$pdo->lastInsertId();
        auditLog("Registró pago P$pid: USD $monto — comp: $comp (Ley 393)", 'pagos', $pid);
        echo json_encode(['ok'=>true,'message'=>"Pago registrado con trazabilidad Ley 393",'reload'=>true]); exit;
    }

    if ($action==='solicitar_legal') {
        $vid = (int)($_POST['venta_id'] ?? 0);
        $prio = $_POST['prioridad'] ?? 'normal';
        $venta=$pdo->prepare("SELECT * FROM ventas WHERE id=?"); $venta->execute([$vid]); $v=$venta->fetch();
        if(!$v) { echo json_encode(['ok'=>false,'message'=>'Venta no encontrada']); exit; }
        $pdo->prepare("INSERT INTO tareas_legales (lote_id,venta_id,solicitado_por,prioridad) VALUES (?,?,?,?)")
            ->execute([$v['lote_id'],$vid,$_SESSION['user_id'],$prio]);
        $pdo->prepare("UPDATE ventas SET estado='en_legal' WHERE id=?")->execute([$vid]);
        auditLog("Solicitó verificación legal venta V$vid", 'tareas_legales');
        echo json_encode(['ok'=>true,'message'=>"Enviado al equipo Legal para verificación",'reload'=>true]); exit;
    }
}

$stmtVentas = $pdo->prepare("
    SELECT v.*, u.nombre cliente_nombre, l.codigo, l.nombre lote_nombre
    FROM ventas v JOIN clientes c ON c.id=v.cliente_id JOIN usuarios u ON u.id=c.usuario_id
    JOIN lotes l ON l.id=v.lote_id WHERE v.asesor_id=? ORDER BY v.created_at DESC
");
$stmtVentas->execute([$uid]);
$ventas = $stmtVentas->fetchAll();
$stmtCli2 = $pdo->prepare("SELECT c.id, u.nombre FROM clientes c JOIN usuarios u ON u.id=c.usuario_id WHERE c.asesor_id=?");
$stmtCli2->execute([$uid]);
$clientes = $stmtCli2->fetchAll();
$lotes    = $pdo->query("SELECT id,codigo,nombre,precio FROM lotes WHERE estado='disponible' AND verificado=1 ORDER BY codigo")->fetchAll();
$pagos    = $pdo->query("SELECT p.*, u.nombre cliente_nombre, l.codigo FROM pagos p JOIN clientes c ON c.id=p.cliente_id JOIN usuarios u ON u.id=c.usuario_id JOIN ventas v ON v.id=p.venta_id JOIN lotes l ON l.id=v.lote_id ORDER BY p.created_at DESC LIMIT 20")->fetchAll();

$pageTitle='Ventas y Pagos'; $pageSubtitle='Módulo de cierre — Ley 393'; $activeNav='as-ven';
require_once __DIR__ . '/../includes/layout.php';
?>

<div class="alert alert-info" style="margin-bottom:20px">
  📋 <strong>Ley 393:</strong> Toda transacción queda registrada de forma inmutable. Comprobante obligatorio para montos superiores a USD 1,000.
</div>

<!-- Acciones top -->
<div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap">
  <button onclick="openModal('modal-venta')" class="btn btn-primary">➕ Registrar venta</button>
  <button onclick="openModal('modal-pago')"  class="btn btn-green">💳 Registrar pago (Ley 393)</button>
</div>

<!-- Tabla ventas -->
<div class="card" style="margin-bottom:20px">
  <div class="card-header"><h3>📈 Pipeline de ventas</h3></div>
  <div class="card-body p0">
    <table class="table">
      <thead><tr><th>#</th><th>Cliente</th><th>Lote</th><th>Monto</th><th>Modalidad</th><th>Estado</th><th>Fecha</th><th>Acción</th></tr></thead>
      <tbody>
        <?php foreach($ventas as $v):
          $bc=['cerrada'=>'badge-green','en_legal'=>'badge-orange','pendiente'=>'badge-blue','contrato'=>'badge-gold','cancelada'=>'badge-red'];
        ?><tr>
          <td><strong>V<?= str_pad($v['id'],3,'0',STR_PAD_LEFT) ?></strong></td>
          <td><?= htmlspecialchars($v['cliente_nombre']) ?></td>
          <td><?= htmlspecialchars($v['codigo'].' – '.$v['lote_nombre']) ?></td>
          <td><strong>$<?= number_format($v['monto'],0,',','.') ?></strong></td>
          <td><?= $v['modalidad'] ?></td>
          <td><span class="badge <?= $bc[$v['estado']] ?? 'badge-gray' ?>"><?= $v['estado'] ?></span></td>
          <td style="font-size:12px;color:var(--muted)"><?= date('d/m/Y', strtotime($v['created_at'])) ?></td>
          <td>
            <?php if($v['estado']==='pendiente'||$v['estado']==='apartado'): ?>
              <button class="btn btn-orange btn-sm" onclick="solicitarLegal(<?= $v['id'] ?>)">⚖️ Enviar a Legal</button>
            <?php endif; ?>
          </td>
        </tr><?php endforeach;
        if(empty($ventas)): ?><tr><td colspan="8" style="text-align:center;padding:24px;color:var(--muted)">Sin ventas registradas</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Tabla pagos -->
<div class="card">
  <div class="card-header"><h3>💳 Pagos registrados — Trazabilidad Ley 393</h3></div>
  <div class="card-body p0">
    <table class="table">
      <thead><tr><th>ID</th><th>Cliente</th><th>Lote</th><th>Monto</th><th>Método</th><th>Comprobante</th><th>Fecha</th></tr></thead>
      <tbody>
        <?php foreach($pagos as $p): ?><tr>
          <td><strong>P<?= str_pad($p['id'],3,'0',STR_PAD_LEFT) ?></strong></td>
          <td><?= htmlspecialchars($p['cliente_nombre']) ?></td>
          <td><?= htmlspecialchars($p['codigo']) ?></td>
          <td><strong>$<?= number_format($p['monto'],0,',','.') ?></strong></td>
          <td><?= $p['metodo'] ?></td>
          <td style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($p['comprobante'] ?? '—') ?></td>
          <td style="font-size:12px;color:var(--muted)"><?= date('d/m/Y', strtotime($p['fecha_pago'])) ?></td>
        </tr><?php endforeach;
        if(empty($pagos)): ?><tr><td colspan="7" style="text-align:center;padding:24px;color:var(--muted)">Sin pagos registrados</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal venta -->
<div class="modal-bg" id="modal-venta">
  <div class="modal">
    <div class="modal-header"><h3>📈 Registrar nueva venta</h3><button class="modal-close" onclick="closeModal('modal-venta')">×</button></div>
    <form id="form-venta" method="POST" action="/sgi_aurora/asesor/ventas.php">
    <?= csrf_field() ?>
      <input type="hidden" name="action" value="create_venta">
      <div class="modal-body">
        <div class="alert alert-info" style="margin-bottom:16px">Solo se pueden vender lotes con <strong>sello verde (DDRR verificado)</strong>.</div>
        <div class="form-row">
          <div class="field-group"><label>Cliente *</label>
            <select name="cliente_id" required>
              <option value="">Seleccionar...</option>
              <?php foreach($clientes as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option><?php endforeach; ?>
            </select></div>
          <div class="field-group"><label>Lote disponible (verificado) *</label>
            <select name="lote_id" required>
              <option value="">Seleccionar...</option>
              <?php foreach($lotes as $l): ?><option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['codigo'].' – '.$l['nombre'].' ($'.number_format($l['precio'],0).')') ?></option><?php endforeach; ?>
            </select></div>
        </div>
        <div class="form-row">
          <div class="field-group"><label>Monto acordado (USD) *</label><input type="number" name="monto" placeholder="35000" required></div>
          <div class="field-group"><label>Modalidad *</label>
            <select name="modalidad"><option>Contado</option><option>Credito Bancario</option><option>Credito Directo</option><option>Anticretico</option></select></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-venta')">Cancelar</button>
        <button type="submit" class="btn btn-primary" data-label="Registrar venta">💾 Registrar venta</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal pago -->
<div class="modal-bg" id="modal-pago">
  <div class="modal">
    <div class="modal-header"><h3>💳 Registrar pago (Ley 393)</h3><button class="modal-close" onclick="closeModal('modal-pago')">×</button></div>
    <form id="form-pago" method="POST" action="/sgi_aurora/asesor/ventas.php">
    <?= csrf_field() ?>
      <input type="hidden" name="action" value="registrar_pago">
      <div class="modal-body">
        <div class="alert alert-warn" style="margin-bottom:16px">⚠️ <strong>Ley 393:</strong> Comprobante obligatorio para montos superiores a USD 1,000.</div>
        <div class="form-row">
          <div class="field-group"><label>Venta relacionada *</label>
            <select name="venta_id" required>
              <option value="">Seleccionar venta...</option>
              <?php foreach($ventas as $v): ?><option value="<?= $v['id'] ?>">V<?= str_pad($v['id'],3,'0',STR_PAD_LEFT) ?> – <?= htmlspecialchars($v['cliente_nombre']) ?> – $<?= number_format($v['monto'],0) ?></option><?php endforeach; ?>
            </select></div>
          <div class="field-group"><label>Monto a pagar (USD) *</label><input type="number" name="monto" placeholder="1500" id="pago-monto" required></div>
        </div>
        <div class="form-row">
          <div class="field-group"><label>Método de pago</label>
            <select name="metodo"><option>Transferencia</option><option>Efectivo</option><option>Cheque</option><option>QR</option></select></div>
          <div class="field-group"><label>Nro. comprobante</label>
            <input type="text" name="comprobante" id="pago-comp" placeholder="TRF-2026-04-XXXX"></div>
        </div>
        <div id="comp-warning" class="alert alert-warn" style="display:none">⚠️ Para montos &gt; USD 1,000 el comprobante es obligatorio (Ley 393).</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-pago')">Cancelar</button>
        <button type="submit" class="btn btn-green" data-label="Registrar pago">💾 Registrar pago</button>
      </div>
    </form>
  </div>
</div>

<script>
submitForm('form-venta','Venta registrada correctamente');
submitForm('form-pago','Pago registrado con trazabilidad Ley 393');

document.getElementById('pago-monto').addEventListener('input',function(){
  document.getElementById('comp-warning').style.display = Number(this.value)>1000 ? 'flex':'none';
});

function solicitarLegal(vid) {
  if(!confirm('¿Enviar esta venta al equipo Legal para verificación?')) return;
  fetch('/sgi_aurora/asesor/ventas.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=solicitar_legal&venta_id='+vid+'&prioridad=normal'})
    .then(r=>r.json()).then(d=>{showToast(d.message,d.ok?'ok':'err');if(d.ok)setTimeout(()=>location.reload(),900);});
}
</script>
<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
