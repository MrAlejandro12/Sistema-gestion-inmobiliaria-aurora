<?php
// admin/reportes.php
require_once __DIR__ . '/../config/db.php';
secureSessionStart();
requireAuth(['admin']);
$pdo = getDB();

$fechaDesde = $_GET['desde'] ?? date('Y-01-01');
$fechaHasta = $_GET['hasta'] ?? date('Y-m-d');
$zonaFiltro = (int)($_GET['zona'] ?? 0);
$estadoFiltro = $_GET['estado'] ?? '';

// Ventas con filtros
$sql = "SELECT v.id, v.monto, v.modalidad, v.estado, v.fecha_venta,
               u.nombre cliente, l.codigo, l.nombre lote_nombre,
               z.nombre zona_nombre, ua.nombre asesor
        FROM ventas v
        JOIN clientes c  ON c.id = v.cliente_id
        JOIN usuarios u  ON u.id = c.usuario_id
        JOIN lotes l     ON l.id = v.lote_id
        JOIN zonas z     ON z.id = l.zona_id
        JOIN usuarios ua ON ua.id = v.asesor_id
        WHERE v.fecha_venta BETWEEN ? AND ?";
$params = [$fechaDesde, $fechaHasta];
if ($zonaFiltro)  { $sql .= " AND l.zona_id = ?"; $params[] = $zonaFiltro; }
if ($estadoFiltro){ $sql .= " AND v.estado = ?";  $params[] = $estadoFiltro; }
$sql .= " ORDER BY v.fecha_venta DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$ventas = $stmt->fetchAll();

// Totales
$totalIngresos = array_sum(array_column($ventas, 'monto'));
$totalVentas   = count($ventas);

// Ventas por zona (para barras)
$ventasPorZona = $pdo->query("
    SELECT z.nombre zona, COUNT(v.id) qty, COALESCE(SUM(v.monto),0) total
    FROM zonas z LEFT JOIN lotes l ON l.zona_id=z.id LEFT JOIN ventas v ON v.lote_id=l.id AND v.estado='cerrada'
    GROUP BY z.nombre ORDER BY total DESC
")->fetchAll();
$maxVZona = max(array_column($ventasPorZona, 'total') ?: [1]);

// Pagos totales
$totalPagos = $pdo->query("SELECT COALESCE(SUM(monto),0) FROM pagos")->fetchColumn();

$zonas = $pdo->query("SELECT * FROM zonas ORDER BY nombre")->fetchAll();

$pageTitle = 'Reportes';
$pageSubtitle = "Período: $fechaDesde → $fechaHasta";
$activeNav = 'ad-rep';
require_once __DIR__ . '/../includes/layout.php';
?>

<!-- Filtros -->
<form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:24px;background:#fff;padding:16px;border-radius:12px;border:1px solid var(--border);align-items:flex-end">
    <div class="field-group"><label>Desde</label><input type="date" name="desde" value="<?= $fechaDesde ?>"></div>
    <div class="field-group"><label>Hasta</label><input type="date" name="hasta" value="<?= $fechaHasta ?>"></div>
    <div class="field-group" style="width:140px"><label>Zona</label>
        <select name="zona"><option value="">Todas</option>
            <?php foreach ($zonas as $z): ?><option value="<?= $z['id'] ?>" <?= $zonaFiltro==$z['id']?'selected':'' ?>><?= htmlspecialchars($z['nombre'] ?? '', ENT_QUOTES|ENT_HTML5, 'UTF-8') ?></option><?php endforeach; ?>
        </select></div>
    <div class="field-group" style="width:140px"><label>Estado</label>
        <select name="estado">
            <option value="">Todos</option>
            <option value="cerrada" <?= $estadoFiltro==='cerrada'?'selected':'' ?>>Cerrada</option>
            <option value="en_legal" <?= $estadoFiltro==='en_legal'?'selected':'' ?>>En Legal</option>
            <option value="pendiente" <?= $estadoFiltro==='pendiente'?'selected':'' ?>>Pendiente</option>
        </select></div>
    <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end">📊 Generar</button>
    <a href="?" class="btn btn-outline btn-sm" style="align-self:flex-end">Limpiar</a>
    <button type="button" class="btn btn-outline btn-sm" style="align-self:flex-end" onclick="window.print()">📥 Imprimir/PDF</button>
</form>

<!-- KPIs del reporte -->
<div class="kpi-grid" style="margin-bottom:24px">
    <div class="kpi-card blue"><div class="kpi-icon">📋</div><div class="kpi-label">Ventas en el período</div><div class="kpi-value"><?= $totalVentas ?></div></div>
    <div class="kpi-card green"><div class="kpi-icon">💵</div><div class="kpi-label">Ingresos totales USD</div><div class="kpi-value">$<?= number_format($totalIngresos,0,',','.') ?></div></div>
    <div class="kpi-card gold"><div class="kpi-icon">💳</div><div class="kpi-label">Pagos cobrados USD</div><div class="kpi-value">$<?= number_format($totalPagos,0,',','.') ?></div></div>
    <div class="kpi-card orange"><div class="kpi-icon">💰</div><div class="kpi-label">Comisiones (5%)</div><div class="kpi-value">$<?= number_format($totalIngresos*0.05,0,',','.') ?></div></div>
</div>

<div class="grid2" style="margin-bottom:24px">
    <!-- Ventas por zona -->
    <div class="card">
        <div class="card-header"><h3>📈 Ventas por zona</h3></div>
        <div class="card-body">
            <?php foreach ($ventasPorZona as $vz): $pct = $maxVZona > 0 ? round($vz['total']/$maxVZona*100) : 0; ?>
            <div style="margin-bottom:12px">
                <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
                    <span><?= htmlspecialchars($vz['zona']) ?></span>
                    <span style="font-weight:600">$<?= number_format($vz['total'],0,',','.') ?> (<?= $vz['qty'] ?>)</span>
                </div>
                <div class="prog-track">
                    <div class="prog-fill" style="width:<?= $pct ?>%;background:var(--navy)"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Distribución por modalidad -->
    <div class="card">
        <div class="card-header"><h3>💳 Distribución por modalidad</h3></div>
        <div class="card-body">
            <?php
            $porModalidad = [];
            foreach ($ventas as $v) {
                $m = $v['modalidad'];
                if (!isset($porModalidad[$m])) $porModalidad[$m] = ['qty'=>0,'total'=>0];
                $porModalidad[$m]['qty']++;
                $porModalidad[$m]['total'] += $v['monto'];
            }
            $colores = ['Contado'=>'var(--green)','Credito Bancario'=>'var(--blue)','Credito Directo'=>'var(--orange)','Anticretico'=>'var(--navy)'];
            $maxM = max(array_column($porModalidad,'total') ?: [1]);
            foreach ($porModalidad as $mod => $data):
                $pct = round($data['total']/$maxM*100);
            ?>
            <div style="margin-bottom:12px">
                <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
                    <span><?= $mod ?></span>
                    <span style="font-weight:600"><?= $data['qty'] ?> — $<?= number_format($data['total'],0,',','.') ?></span>
                </div>
                <div class="prog-track">
                    <div class="prog-fill" style="width:<?= $pct ?>%;background:<?= $colores[$mod] ?? 'var(--navy)' ?>"></div>
                </div>
            </div>
            <?php endforeach; if(empty($porModalidad)): ?><p style="color:var(--muted);font-size:13px">Sin datos para el período</p><?php endif; ?>
        </div>
    </div>
</div>

<!-- Tabla detallada -->
<div class="card">
    <div class="card-header">
        <h3>📋 Detalle de ventas del período</h3>
        <span class="badge badge-blue"><?= $totalVentas ?> registros</span>
    </div>
    <div class="card-body p0">
        <table class="table">
            <thead><tr><th>#</th><th>Cliente</th><th>Lote</th><th>Zona</th><th>Asesor</th><th>Monto</th><th>Modalidad</th><th>Estado</th><th>Fecha</th></tr></thead>
            <tbody>
                <?php foreach ($ventas as $v):
                    $bc = ['cerrada'=>'badge-green','en_legal'=>'badge-orange','pendiente'=>'badge-blue','contrato'=>'badge-gold','cancelada'=>'badge-red'];
                ?>
                <tr>
                    <td><strong>V<?= str_pad($v['id'],3,'0',STR_PAD_LEFT) ?></strong></td>
                    <td><?= htmlspecialchars($v['cliente']) ?></td>
                    <td style="font-size:12px"><?= htmlspecialchars($v['codigo']) ?></td>
                    <td>📍 <?= htmlspecialchars($v['zona_nombre']) ?></td>
                    <td style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($v['asesor']) ?></td>
                    <td><strong>$<?= number_format($v['monto'],0,',','.') ?></strong></td>
                    <td style="font-size:12px"><?= $v['modalidad'] ?></td>
                    <td><span class="badge <?= $bc[$v['estado']] ?? 'badge-gray' ?>"><?= $v['estado'] ?></span></td>
                    <td style="font-size:12px;color:var(--muted)"><?= $v['fecha_venta'] ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($ventas)): ?>
                    <tr><td colspan="9" style="text-align:center;padding:24px;color:var(--muted)">Sin ventas en el período seleccionado</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
