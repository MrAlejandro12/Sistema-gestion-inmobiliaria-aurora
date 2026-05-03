<?php
// admin/auditoria.php
require_once __DIR__ . '/../config/db.php';
secureSessionStart();
requireAuth(['admin']);
$pdo = getDB();

$fecha  = $_GET['fecha']  ?? date('Y-m-d');
$email  = trim($_GET['email'] ?? '');
$limit  = 100;

$sql = "SELECT * FROM auditoria WHERE DATE(created_at)=?";
$params = [$fecha];
if ($email) { $sql .= " AND email LIKE ?"; $params[] = "%$email%"; }
$sql .= " ORDER BY created_at DESC LIMIT $limit";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle='Auditoría del Sistema'; $pageSubtitle='Log inmutable — Ley 164 y Ley 393'; $activeNav='ad-audit';
require_once __DIR__ . '/../includes/layout.php';
?>
<div class="alert alert-info" style="margin-bottom:20px">
  🔒 <strong>Ley 164 + Ley 393:</strong> Este registro es inmutable. No puede modificarse ni eliminarse. Disponible para exportación ante supervisión de ASFI o fiscalización.
</div>
<form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;align-items:flex-end">
  <div class="field-group"><label>Fecha</label><input type="date" name="fecha" value="<?= $fecha ?>"></div>
  <div class="field-group" style="width:200px"><label>Email</label><input type="text" name="email" placeholder="Filtrar por email..." value="<?= htmlspecialchars($email) ?>"></div>
  <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end">🔍 Filtrar</button>
  <a href="?" class="btn btn-outline btn-sm" style="align-self:flex-end">Hoy</a>
</form>
<div class="card">
  <div class="card-header"><h3>📋 <?= count($logs) ?> registro(s) — <?= date('d/m/Y', strtotime($fecha)) ?></h3></div>
  <div class="card-body p0">
    <table class="table">
      <thead><tr><th>Timestamp</th><th>Usuario</th><th>Acción</th><th>Tabla</th><th>IP</th></tr></thead>
      <tbody>
        <?php foreach ($logs as $l): ?>
        <tr>
          <td style="font-family:monospace;font-size:12px;color:var(--muted)"><?= $l['created_at'] ?></td>
          <td style="font-size:12px"><?= htmlspecialchars($l['email'] ?? '—') ?></td>
          <td><span style="font-size:12px;background:#f1f5f9;padding:3px 8px;border-radius:6px"><?= htmlspecialchars($l['accion']) ?></span></td>
          <td style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($l['tabla_afectada'] ?? '—') ?></td>
          <td style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($l['ip'] ?? '—') ?></td>
        </tr>
        <?php endforeach; if(empty($logs)): ?>
          <tr><td colspan="5" style="text-align:center;padding:24px;color:var(--muted)">Sin registros para este filtro</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
