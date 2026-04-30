<?php
// asesor/contratos.php — ACTUALIZADO con Ver y PDF funcionales
require_once __DIR__ . '/../config/db.php';
secureSessionStart();
requireAuth(['asesor', 'admin']);
$pdo = getDB();
$uid = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT ct.*, uc.nombre cliente, l.codigo, l.nombre lote_n, z.nombre zona
    FROM contratos ct
    JOIN ventas v    ON v.id  = ct.venta_id
    JOIN clientes c  ON c.id  = v.cliente_id
    JOIN usuarios uc ON uc.id = c.usuario_id
    JOIN lotes l     ON l.id  = v.lote_id
    JOIN zonas z     ON z.id  = l.zona_id
    WHERE v.asesor_id = ?
    ORDER BY ct.created_at DESC
");
$stmt->execute([$uid]);
$contratos = $stmt->fetchAll();

$pageTitle    = 'Contratos';
$pageSubtitle = 'Mis contratos generados (' . count($contratos) . ')';
$activeNav    = 'as-con';
require_once __DIR__ . '/../includes/layout.php';
?>

<div class="alert alert-info" style="margin-bottom:16px">
    📋 Los contratos son generados automáticamente por el equipo Legal al aprobar la verificación DDRR.
    Puedes ver el detalle completo y descargar el PDF desde aquí.
</div>

<div class="card">
    <div class="card-body p0">
        <table class="table">
            <thead>
                <tr>
                    <th>Contrato</th>
                    <th>Cliente</th>
                    <th>Lote</th>
                    <th>Zona</th>
                    <th>Estado</th>
                    <th>Generado</th>
                    <th>Firmado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $bc = [
                    'borrador'  => 'badge-gray',
                    'generado'  => 'badge-blue',
                    'firmado'   => 'badge-green',
                    'cancelado' => 'badge-red',
                ];
                foreach ($contratos as $c):
                ?>
                <tr>
                    <td><strong>CT<?= str_pad($c['id'], 3, '0', STR_PAD_LEFT) ?></strong></td>
                    <td><?= htmlspecialchars($c['cliente']) ?></td>
                    <td><?= htmlspecialchars($c['codigo']) ?></td>
                    <td>📍 <?= htmlspecialchars($c['zona']) ?></td>
                    <td>
                        <span class="badge <?= $bc[$c['estado']] ?? 'badge-gray' ?>">
                            <?= $c['estado'] ?>
                        </span>
                    </td>
                    <td style="font-size:12px">
                        <?= $c['fecha_gen'] ? date('d/m/Y', strtotime($c['fecha_gen'])) : '—' ?>
                    </td>
                    <td style="font-size:12px">
                        <?= $c['fecha_firma'] ? date('d/m/Y', strtotime($c['fecha_firma'])) : '<span style="color:var(--muted)">Pendiente</span>' ?>
                    </td>
                    <td style="display:flex;gap:6px;flex-wrap:wrap">
                        <a href="/sgi_aurora/asesor/contrato_view.php?id=<?= $c['id'] ?>"
                           class="btn btn-outline btn-sm" target="_blank">
                            👁 Ver
                        </a>
                        <a href="/sgi_aurora/contrato_pdf.php?id=<?= $c['id'] ?>"
                           class="btn btn-primary btn-sm" target="_blank">
                            📥 PDF
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($contratos)): ?>
                    <tr>
                        <td colspan="8" style="text-align:center;padding:24px;color:var(--muted)">
                            Sin contratos generados aún
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
