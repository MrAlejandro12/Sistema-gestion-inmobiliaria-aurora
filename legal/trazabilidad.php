<?php
// legal/trazabilidad.php
require_once __DIR__ . '/../config/db.php';
secureSessionStart();
requireAuth(['legal', 'admin']);
$pdo = getDB();

$operaciones = $pdo->query("
    SELECT v.id, v.monto, v.modalidad, v.estado, v.fecha_venta, v.created_at,
           u.nombre cliente_nombre, l.codigo, l.nombre lote_nombre, z.nombre zona_nombre,
           ct.estado contrato_estado, ct.fecha_firma
    FROM ventas v
    JOIN clientes c ON c.id = v.cliente_id
    JOIN usuarios u ON u.id = c.usuario_id
    JOIN lotes l ON l.id = v.lote_id
    JOIN zonas z ON z.id = l.zona_id
    LEFT JOIN contratos ct ON ct.venta_id = v.id
    ORDER BY v.created_at DESC
")->fetchAll();

$pageTitle = 'Trazabilidad';
$pageSubtitle = 'Historial de operaciones — Ley 393';
$activeNav = 'lg-traz';
require_once __DIR__ . '/../includes/layout.php';
?>

<div class="alert alert-info" style="margin-bottom:20px">
    🔗 <strong>Ley 393 — Trazabilidad Financiera:</strong> Todos los eventos quedan registrados de forma
    inmutable. La tabla de pagos no permite UPDATE ni DELETE. Exportable para supervisión ASFI.
</div>

<div class="card">
    <div class="card-header">
        <h3>📊 Historial de Operaciones</h3>
        <span class="badge badge-blue">Ley 393</span>
    </div>
    <div class="card-body p0">
        <table class="table">
            <thead>
                <tr>
                    <th>Venta</th><th>Cliente</th><th>Lote</th><th>Zona</th>
                    <th>Monto</th><th>Modalidad</th><th>Estado</th>
                    <th>Contrato</th><th>Fecha</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($operaciones as $op):
                    $bcVenta = ['cerrada'=>'badge-green','en_legal'=>'badge-orange','pendiente'=>'badge-blue','contrato'=>'badge-gold','cancelada'=>'badge-red'];
                    $bcCt = ['firmado'=>'badge-green','generado'=>'badge-blue','borrador'=>'badge-gray'];
                ?>
                <tr>
                    <td><strong>V<?= str_pad($op['id'],3,'0',STR_PAD_LEFT) ?></strong></td>
                    <td><?= htmlspecialchars($op['cliente_nombre']) ?></td>
                    <td style="font-size:12px"><?= htmlspecialchars($op['codigo']) ?></td>
                    <td>📍 <?= htmlspecialchars($op['zona_nombre']) ?></td>
                    <td><strong>$<?= number_format($op['monto'],0,',','.') ?></strong></td>
                    <td style="font-size:12px"><?= $op['modalidad'] ?></td>
                    <td><span class="badge <?= $bcVenta[$op['estado']] ?? 'badge-gray' ?>"><?= $op['estado'] ?></span></td>
                    <td>
                        <?php if ($op['contrato_estado']): ?>
                            <span class="badge <?= $bcCt[$op['contrato_estado']] ?? 'badge-gray' ?>"><?= $op['contrato_estado'] ?></span>
                            <?php if ($op['fecha_firma']): ?><br><span style="font-size:11px;color:var(--muted)"><?= $op['fecha_firma'] ?></span><?php endif; ?>
                        <?php else: ?>
                            <span style="color:var(--muted);font-size:12px">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:12px;color:var(--muted)"><?= date('d/m/Y', strtotime($op['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($operaciones)): ?>
                    <tr><td colspan="9" style="text-align:center;padding:24px;color:var(--muted)">Sin operaciones registradas</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
