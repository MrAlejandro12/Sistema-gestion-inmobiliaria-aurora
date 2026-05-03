<?php
// asesor/dashboard.php
require_once __DIR__ . '/../config/db.php';
secureSessionStart();
requireAuth(['asesor','admin']);
$pdo = getDB();
$uid = $_SESSION['user_id'];

// SonarQube S2077: use prepared statements — no user input in query strings
$stmtCli = $pdo->prepare("SELECT COUNT(*) FROM clientes WHERE asesor_id = ?");
$stmtCli->execute([$uid]);
$stmtVen = $pdo->prepare("SELECT COUNT(*) FROM ventas WHERE asesor_id = ? AND MONTH(created_at)=MONTH(NOW())");
$stmtVen->execute([$uid]);
$stmtCom = $pdo->prepare("SELECT COALESCE(SUM(monto)*0.05,0) FROM ventas WHERE asesor_id = ? AND estado='cerrada'");
$stmtCom->execute([$uid]);
$kpis = [
  'clientes'   => (int)$stmtCli->fetchColumn(),
  'ventas_mes' => (int)$stmtVen->fetchColumn(),
  'comisiones' => (float)$stmtCom->fetchColumn(),
];

$stmtV = $pdo->prepare("
  SELECT v.*, u.nombre cliente_nombre, l.codigo, l.nombre lote_nombre
  FROM ventas v JOIN clientes c ON c.id=v.cliente_id JOIN usuarios u ON u.id=c.usuario_id
  JOIN lotes l ON l.id=v.lote_id WHERE v.asesor_id=? ORDER BY v.created_at DESC LIMIT 5
");
$stmtV->execute([$uid]);
$ventas = $stmtV->fetchAll();

$pageTitle='Dashboard Asesor'; $pageSubtitle='Panel del Asesor — '.$_SESSION['nombre']; $activeNav='as-dash';
require_once __DIR__ . '/../includes/layout.php';
?>
<div class="kpi-grid">
  <div class="kpi-card blue"><div class="kpi-icon">👥</div><div class="kpi-label">Clientes activos</div><div class="kpi-value"><?= $kpis['clientes'] ?></div></div>
  <div class="kpi-card gold"><div class="kpi-icon">📋</div><div class="kpi-label">Ventas del mes</div><div class="kpi-value"><?= $kpis['ventas_mes'] ?></div></div>
  <div class="kpi-card green"><div class="kpi-icon">💰</div><div class="kpi-label">Comisiones USD</div><div class="kpi-value">$<?= number_format($kpis['comisiones'],0) ?></div><div class="kpi-sub">5% acumulado</div></div>
</div>
<div class="card">
  <div class="card-header"><h3>📈 Mis últimas ventas</h3><a href="/sgi_aurora/asesor/ventas.php" class="btn btn-outline btn-sm">Ver todas</a></div>
  <div class="card-body p0">
    <table class="table">
      <thead><tr><th>Cliente</th><th>Lote</th><th>Monto</th><th>Modalidad</th><th>Estado</th></tr></thead>
      <tbody>
        <?php foreach ($ventas as $v):
          $bc=['cerrada'=>'badge-green','en_legal'=>'badge-orange','pendiente'=>'badge-blue','contrato'=>'badge-gold'];
        ?><tr>
          <td><?= htmlspecialchars($v['cliente_nombre']) ?></td>
          <td><?= htmlspecialchars($v['codigo'].' – '.$v['lote_nombre']) ?></td>
          <td><strong>$<?= number_format($v['monto'],0,',','.') ?></strong></td>
          <td><?= $v['modalidad'] ?></td>
          <td><span class="badge <?= $bc[$v['estado']] ?? 'badge-gray' ?>"><?= $v['estado'] ?></span></td>
        </tr><?php endforeach;
        if(empty($ventas)): ?><tr><td colspan="5" style="text-align:center;padding:24px;color:var(--muted)">Sin ventas registradas aún</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
