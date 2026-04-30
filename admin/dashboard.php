<?php
require_once __DIR__ . '/../config/db.php';
secureSessionStart();
requireAuth(['admin']);

$pdo = getDB();

// KPIs
$kpis = [
  'ingresos'   => $pdo->query("SELECT COALESCE(SUM(monto),0) FROM pagos")->fetchColumn(),
  'usuarios'   => $pdo->query("SELECT COUNT(*) FROM usuarios WHERE estado='activo'")->fetchColumn(),
  'vendidos'   => $pdo->query("SELECT COUNT(*) FROM lotes WHERE estado='vendido'")->fetchColumn(),
  'disponibles'=> $pdo->query("SELECT COUNT(*) FROM lotes WHERE estado='disponible'")->fetchColumn(),
  'pendientes' => $pdo->query("SELECT COUNT(*) FROM tareas_legales WHERE estado='pendiente'")->fetchColumn(),
];

// Ventas por zona
$ventasZona = $pdo->query("
  SELECT z.nombre zona, SUM(v.monto) total
  FROM ventas v
  JOIN lotes l ON l.id=v.lote_id
  JOIN zonas z ON z.id=l.zona_id
  WHERE v.estado='cerrada'
  GROUP BY z.nombre ORDER BY total DESC LIMIT 6
")->fetchAll();

$maxTotal = max(array_column($ventasZona,'total') ?: [1]);

// Últimas operaciones
$ultOps = $pdo->query("
  SELECT v.id, u.nombre cliente, l.nombre lote, l.codigo, v.monto, v.estado, v.created_at
  FROM ventas v
  JOIN clientes c ON c.id=v.cliente_id
  JOIN usuarios u ON u.id=c.usuario_id
  JOIN lotes l ON l.id=v.lote_id
  ORDER BY v.created_at DESC LIMIT 6
")->fetchAll();

$pageTitle    = 'Dashboard';
$pageSubtitle = 'Panel de Administración — SGI Aurora';
$activeNav    = 'ad-dash';
require_once __DIR__ . '/../includes/layout.php';
?>

<div class="kpi-grid">
  <div class="kpi-card blue"><div class="kpi-icon">💵</div><div class="kpi-label">Ingresos totales</div><div class="kpi-value">$<?= number_format($kpis['ingresos'],0,',','.') ?></div><div class="kpi-sub">USD acumulado</div></div>
  <div class="kpi-card green"><div class="kpi-icon">👥</div><div class="kpi-label">Usuarios activos</div><div class="kpi-value"><?= $kpis['usuarios'] ?></div><div class="kpi-sub">En el sistema</div></div>
  <div class="kpi-card gold"><div class="kpi-icon">🏠</div><div class="kpi-label">Lotes vendidos</div><div class="kpi-value"><?= $kpis['vendidos'] ?></div><div class="kpi-sub">Acumulado</div></div>
  <div class="kpi-card orange"><div class="kpi-icon">📋</div><div class="kpi-label">Lotes disponibles</div><div class="kpi-value"><?= $kpis['disponibles'] ?></div><div class="kpi-sub">En catálogo</div></div>
</div>

<div class="grid2">
  <div class="card">
    <div class="card-header"><h3>📈 Ventas por zona</h3></div>
    <div class="card-body">
      <?php if (empty($ventasZona)): ?>
        <p style="color:var(--muted);font-size:13px">Sin datos de ventas aún.</p>
      <?php else: foreach ($ventasZona as $z): $pct = round($z['total']/$maxTotal*100); ?>
        <div style="margin-bottom:12px">
          <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
            <span><?= htmlspecialchars($z['zona']) ?></span>
            <span style="font-weight:600">$<?= number_format($z['total'],0,',','.') ?></span>
          </div>
          <div class="prog-track"><div class="prog-fill" style="width:<?= $pct ?>%;background:var(--navy)"></div></div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h3>🔔 Estado del sistema</h3></div>
    <div class="card-body" style="display:flex;flex-direction:column;gap:8px">
      <?php if ($kpis['pendientes'] > 0): ?>
        <div class="alert alert-warn">⚠️ <?= $kpis['pendientes'] ?> verificacion(es) legal(es) pendiente(s)</div>
      <?php else: ?>
        <div class="alert alert-success">✅ Sin verificaciones legales pendientes</div>
      <?php endif; ?>
      <div class="alert alert-info">ℹ️ Base de datos activa — <?= date('d/m/Y H:i') ?></div>
      <div class="alert alert-success">✅ Cumplimiento Ley 164 y Ley 393 activo</div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3>📋 Últimas operaciones</h3>
    <a href="/sgi_aurora/admin/reportes.php" class="btn btn-outline btn-sm">Ver todas</a>
  </div>
  <div class="card-body p0">
    <table class="table">
      <thead><tr><th>#</th><th>Cliente</th><th>Lote</th><th>Monto</th><th>Estado</th><th>Fecha</th></tr></thead>
      <tbody>
        <?php foreach ($ultOps as $op): ?>
        <tr>
          <td><strong>V<?= str_pad($op['id'],3,'0',STR_PAD_LEFT) ?></strong></td>
          <td><?= htmlspecialchars($op['cliente']) ?></td>
          <td><?= htmlspecialchars($op['codigo'].' — '.$op['lote']) ?></td>
          <td><strong>$<?= number_format($op['monto'],0,',','.') ?></strong></td>
          <td>
            <?php $bc=['cerrada'=>'badge-green','en_legal'=>'badge-orange','pendiente'=>'badge-blue','contrato'=>'badge-gold']; ?>
            <span class="badge <?= $bc[$op['estado']] ?? 'badge-gray' ?>"><?= $op['estado'] ?></span>
          </td>
          <td style="font-size:12px;color:var(--muted)"><?= date('d/m/Y', strtotime($op['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($ultOps)): ?>
          <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:24px">Sin operaciones registradas</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
