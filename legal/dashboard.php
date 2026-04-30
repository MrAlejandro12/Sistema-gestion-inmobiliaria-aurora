<?php
// legal/dashboard.php
require_once __DIR__ . '/../config/db.php';
secureSessionStart();
requireAuth(['legal','admin']);
$pdo = getDB();

$kpis=[
  'pendientes' => $pdo->query("SELECT COUNT(*) FROM tareas_legales WHERE estado='pendiente'")->fetchColumn(),
  'aprobados_mes' => $pdo->query("SELECT COUNT(*) FROM tareas_legales WHERE estado='aprobado' AND MONTH(created_at)=MONTH(NOW())")->fetchColumn(),
  'contratos' => $pdo->query("SELECT COUNT(*) FROM contratos WHERE MONTH(created_at)=MONTH(NOW())")->fetchColumn(),
];

$pageTitle='Panel Legal'; $pageSubtitle='Dirección Legal — '.$_SESSION['nombre']; $activeNav='lg-dash';
require_once __DIR__ . '/../includes/layout.php';
?>
<div class="alert alert-warn" style="margin-bottom:20px">
  ⚖️ <strong>Recordatorio Legal:</strong> <strong>Ley 164</strong> — Toda verificación debe registrar quién aprobó y cuándo. <strong>Ley 393</strong> — Los contratos deben incluir trazabilidad completa.
</div>
<div class="kpi-grid">
  <div class="kpi-card orange"><div class="kpi-icon">⚠️</div><div class="kpi-label">Verificaciones pendientes</div><div class="kpi-value"><?= $kpis['pendientes'] ?></div></div>
  <div class="kpi-card green"><div class="kpi-icon">✅</div><div class="kpi-label">Aprobadas este mes</div><div class="kpi-value"><?= $kpis['aprobados_mes'] ?></div></div>
  <div class="kpi-card blue"><div class="kpi-icon">📄</div><div class="kpi-label">Contratos generados</div><div class="kpi-value"><?= $kpis['contratos'] ?></div></div>
</div>
<div class="card">
  <div class="card-header"><h3>📋 Acceso rápido</h3></div>
  <div class="card-body" style="display:flex;gap:12px;flex-wrap:wrap">
    <a href="/sgi_aurora/legal/tareas.php" class="btn btn-primary">✅ Ver tareas pendientes</a>
    <a href="/sgi_aurora/legal/contratos.php" class="btn btn-outline">📄 Ver contratos</a>
    <a href="/sgi_aurora/legal/trazabilidad.php" class="btn btn-outline">🔗 Trazabilidad</a>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
