<?php
// cliente/catalogo.php
require_once __DIR__ . '/../config/db.php';
secureSessionStart();
requireAuth(['cliente','asesor','admin']);
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ob_end_clean();
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    if ($action === 'interes') {
        $lote_id = (int)($_POST['lote_id'] ?? 0);
        $cli = $pdo->prepare("SELECT c.id, c.asesor_id FROM clientes c WHERE c.usuario_id=?");
        $cli->execute([$_SESSION['user_id']]);
        $cli = $cli->fetch();
        if (!$cli) { echo json_encode(['ok'=>false,'message'=>'Perfil de cliente no encontrado']); exit; }
        $exists = $pdo->prepare("SELECT id FROM solicitudes WHERE cliente_id=? AND lote_id=? AND estado NOT IN ('cerrada','cancelada')");
        $exists->execute([$cli['id'], $lote_id]);
        if ($exists->fetch()) { echo json_encode(['ok'=>false,'message'=>'Ya enviaste interés en este lote']); exit; }
        $pdo->prepare("INSERT INTO solicitudes (cliente_id, lote_id, estado) VALUES (?,?,'pendiente')")->execute([$cli['id'], $lote_id]);
        $newId = $pdo->lastInsertId();
        auditLog("Envió interés en lote #$lote_id", 'solicitudes', $newId);
        echo json_encode(['ok'=>true,'message'=>'¡Solicitud enviada! Tu asesor la verá en breve.']);
        exit;
    }
    exit;
}


$filtroZona = (int)($_GET['zona'] ?? 0);
$filtroMax  = (float)($_GET['precio_max'] ?? 0);
$filtroMin  = (float)($_GET['precio_min'] ?? 0);
$filtroTipo = $_GET['tipo'] ?? '';

$sql = "SELECT l.*,z.nombre zona_nombre FROM lotes l JOIN zonas z ON z.id=l.zona_id WHERE l.estado='disponible' AND l.verificado=1";
$params = [];
if ($filtroZona) { $sql.=" AND l.zona_id=?"; $params[]=$filtroZona; }
if ($filtroMax)  { $sql.=" AND l.precio<=?";  $params[]=$filtroMax; }
if ($filtroMin)  { $sql.=" AND l.precio>=?";  $params[]=$filtroMin; }
if ($filtroTipo) { $sql.=" AND l.tipo=?";     $params[]=$filtroTipo; }
$sql.=" ORDER BY l.precio ASC";
$stmt=$pdo->prepare($sql); $stmt->execute($params);
$lotes=$stmt->fetchAll();
$zonas=$pdo->query("SELECT * FROM zonas ORDER BY nombre")->fetchAll();
$bgs=['bg1','bg2','bg3','bg4','bg5','bg6'];

$pageTitle='Catálogo de Lotes'; $pageSubtitle=count($lotes).' lote(s) disponible(s) con verificación legal'; $activeNav='cli-cat';
require_once __DIR__ . '/../includes/layout.php';
?>
<form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;background:#fff;padding:16px;border-radius:12px;border:1px solid var(--border);align-items:flex-end">
  <div class="field-group" style="width:150px"><label>Zona</label>
    <select name="zona"><option value="">Todas</option>
      <?php foreach($zonas as $z): ?><option value="<?= $z['id'] ?>" <?= $filtroZona==$z['id']?'selected':'' ?>><?= htmlspecialchars($z['nombre'] ?? '', ENT_QUOTES|ENT_HTML5, 'UTF-8') ?></option><?php endforeach; ?>
    </select></div>
  <div class="field-group" style="width:140px"><label>Precio mínimo $</label><input type="number" name="precio_min" placeholder="0" value="<?= $filtroMin ?: '' ?>"></div>
  <div class="field-group" style="width:140px"><label>Precio máximo $</label><input type="number" name="precio_max" placeholder="Sin límite" value="<?= $filtroMax ?: '' ?>"></div>
  <div class="field-group" style="width:130px"><label>Tipo</label>
    <select name="tipo"><option value="">Todos</option><option value="Residencial" <?= $filtroTipo==='Residencial'?'selected':'' ?>>Residencial</option><option value="Comercial" <?= $filtroTipo==='Comercial'?'selected':'' ?>>Comercial</option></select></div>
  <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end">🔍 Filtrar</button>
  <a href="?" class="btn btn-outline btn-sm" style="align-self:flex-end">Limpiar</a>
</form>

<?php if(empty($lotes)): ?>
  <div class="alert alert-info">Sin lotes que coincidan con los filtros aplicados. <a href="?" style="color:var(--navy);font-weight:600">Ver todos</a></div>
<?php else: ?>
<div class="lots-grid">
  <?php foreach($lotes as $i=>$l):
  $bg = $bgs[abs($i) % count($bgs)];
  $svcs = array_filter(explode(',', (string)($l['servicios'] ?? ''))); ?>
  <div class="lot-card" onclick="openModal('modal-lote-<?= $l['id'] ?>')">
    <div class="lot-img <?= $bg ?>">
      <span><?= $l['emoji'] ?: '🏡' ?></span>
      <div class="lot-badge"><span class="badge badge-green">Disponible</span></div>
    </div>
    <div class="lot-body">
      <div class="lot-name"><?= htmlspecialchars($l['codigo'].' — '.$l['nombre']) ?></div>
      <div class="lot-loc">📍 <?= htmlspecialchars($l['zona_nombre']) ?></div>
      <div class="lot-price">$<?= number_format($l['precio'],0,',','.') ?></div>
      <div class="lot-tags">
        <span class="lot-tag">📐 <?= number_format($l['superficie'],0) ?> m²</span>
        <span class="lot-tag"><?= $l['tipo'] ?></span>
        <?php foreach(array_slice($svcs,0,3) as $s): ?><span class="lot-tag"><?= trim($s) ?></span><?php endforeach; ?>
      </div>
      <span class="badge badge-green" style="font-size:11px">⚖️ Verificado DDRR</span>
    </div>
  </div>

  <!-- Modal detalle lote -->
  <div class="modal-bg" id="modal-lote-<?= $l['id'] ?>">
    <div class="modal modal-lg">
      <div class="modal-header"><h3>🏡 <?= htmlspecialchars($l['codigo'].' — '.$l['nombre']) ?></h3><button class="modal-close" onclick="closeModal('modal-lote-<?= $l['id'] ?>')">×</button></div>
      <div class="modal-body">
        <div style="height:200px;background:linear-gradient(135deg,#1a4a2e,#2d7a4f);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:80px;margin-bottom:20px"><?= $l['emoji'] ?: '🏡' ?></div>
        <div class="form-row triple">
          <div><div style="font-size:12px;color:var(--muted)">Zona</div><div style="font-weight:600">📍 <?= htmlspecialchars($l['zona_nombre']) ?></div></div>
          <div><div style="font-size:12px;color:var(--muted)">Superficie</div><div style="font-weight:600">📐 <?= number_format($l['superficie'],0) ?> m²</div></div>
          <div><div style="font-size:12px;color:var(--muted)">Precio</div><div style="font-weight:600;font-size:18px;color:var(--navy)">$<?= number_format($l['precio'],0,',','.') ?></div></div>
        </div>
        <?php if($l['direccion']): ?><div style="font-size:13px;color:var(--muted);margin-bottom:16px">📍 <?= htmlspecialchars($l['direccion']) ?></div><?php endif; ?>
        <div><div style="font-size:13px;font-weight:600;margin-bottom:8px">✅ Servicios disponibles</div>
          <div style="display:flex;gap:8px;flex-wrap:wrap"><?php foreach($svcs as $s): ?><span class="badge badge-green"><?= trim($s) ?></span><?php endforeach; ?></div></div>
        <div class="alert alert-success" style="margin-top:16px">⚖️ Lote verificado en Derechos Reales (DDRR) — Sin gravámenes</div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline" onclick="closeModal('modal-lote-<?= $l['id'] ?>')">Cerrar</button>
        <button class="btn btn-primary" onclick="enviarInteres(<?= $l['id'] ?>, this); closeModal('modal-lote-<?= $l['id'] ?>')">📤 Me interesa este lote</button>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<script>
async function enviarInteres(loteId, btn) {
  const fd = new FormData();
  fd.append('action', 'interes');
  fd.append('lote_id', loteId);
  try {
    const r = await fetch(window.location.pathname, {method:'POST', body:fd});
    const d = await r.json();
    showToast(d.message, d.ok?'ok':'err');
  } catch(e) { showToast('Error de conexión','err'); }
}
</script>
<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
