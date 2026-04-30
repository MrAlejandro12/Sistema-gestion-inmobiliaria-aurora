<?php
// cliente/dashboard.php
require_once __DIR__ . '/../config/db.php';
secureSessionStart();
requireAuth(['cliente']);
$pdo = getDB();
$uid = $_SESSION['user_id'];

$cliente = $pdo->prepare("SELECT c.*,u.nombre,u.email,u.telefono,u.ci,z.nombre zona_nombre,ua.nombre asesor_nombre FROM clientes c JOIN usuarios u ON u.id=c.usuario_id LEFT JOIN zonas z ON z.id=c.zona_preferida LEFT JOIN usuarios ua ON ua.id=c.asesor_id WHERE c.usuario_id=?");
$cliente->execute([$uid]); $cli = $cliente->fetch();

$pagos = [];
if ($cli) {
    $stmt=$pdo->prepare("SELECT p.*,l.codigo FROM pagos p JOIN ventas v ON v.id=p.venta_id JOIN lotes l ON l.id=v.lote_id WHERE p.cliente_id=? ORDER BY p.fecha_pago DESC");
    $stmt->execute([$cli['id']]); $pagos=$stmt->fetchAll();
}

$pageTitle='Mi Panel'; $pageSubtitle='Bienvenido, '.$_SESSION['nombre']; $activeNav='cli-dash';
require_once __DIR__ . '/../includes/layout.php';
?>
<div class="kpi-grid">
  <div class="kpi-card blue"><div class="kpi-icon">📋</div><div class="kpi-label">Zona de interés</div><div class="kpi-value" style="font-size:18px"><?= htmlspecialchars($cli['zona_nombre'] ?? '—') ?></div></div>
  <div class="kpi-card gold"><div class="kpi-icon">💰</div><div class="kpi-label">Presupuesto máx.</div><div class="kpi-value">$<?= number_format($cli['presupuesto_max'] ?? 0,0) ?></div></div>
  <div class="kpi-card green"><div class="kpi-icon">💳</div><div class="kpi-label">Pagos realizados</div><div class="kpi-value"><?= count($pagos) ?></div></div>
  <div class="kpi-card orange"><div class="kpi-icon">🤝</div><div class="kpi-label">Mi asesor</div><div class="kpi-value" style="font-size:16px"><?= htmlspecialchars($cli['asesor_nombre'] ?? '—') ?></div></div>
</div>
<div class="grid2">
  <div class="card">
    <div class="card-header"><h3>🏠 Buscar lotes</h3></div>
    <div class="card-body">
      <p style="font-size:13px;color:var(--muted);margin-bottom:16px">Encuentra el lote perfecto según tus preferencias</p>
      <a href="/sgi_aurora/cliente/catalogo.php" class="btn btn-primary" style="width:100%;justify-content:center">📋 Ir al Catálogo de Lotes</a>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><h3>💳 Últimos pagos</h3></div>
    <div class="card-body p0">
      <?php if(empty($pagos)): ?>
        <div style="padding:24px;text-align:center;color:var(--muted);font-size:13px">Sin pagos registrados aún</div>
      <?php else: ?>
        <table class="table">
          <thead><tr><th>Lote</th><th>Monto</th><th>Fecha</th></tr></thead>
          <tbody><?php foreach($pagos as $p): ?><tr>
            <td><?= htmlspecialchars($p['codigo']) ?></td>
            <td><strong>$<?= number_format($p['monto'],0,',','.') ?></strong></td>
            <td style="font-size:12px;color:var(--muted)"><?= date('d/m/Y',strtotime($p['fecha_pago'])) ?></td>
          </tr><?php endforeach; ?></tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
