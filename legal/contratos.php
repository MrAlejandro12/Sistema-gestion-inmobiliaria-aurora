<?php
// legal/contratos.php — ACTUALIZADO con Ver y PDF funcionales
require_once __DIR__ . '/../config/db.php';
secureSessionStart();
requireAuth(['legal', 'admin']);
$pdo = getDB();

$contratos = $pdo->query("
    SELECT ct.*, u.nombre cliente_nombre, l.codigo, l.nombre lote_nombre, z.nombre zona_nombre,
           v.monto, v.modalidad
    FROM contratos ct
    JOIN ventas v    ON v.id  = ct.venta_id
    JOIN clientes c  ON c.id  = v.cliente_id
    JOIN usuarios u  ON u.id  = c.usuario_id
    JOIN lotes l     ON l.id  = v.lote_id
    JOIN zonas z     ON z.id  = l.zona_id
    ORDER BY ct.created_at DESC
")->fetchAll();

$pageTitle    = 'Contratos';
$pageSubtitle = 'Gestión de contratos legales (' . count($contratos) . ')';
$activeNav    = 'lg-con';
require_once __DIR__ . '/../includes/layout.php';
?>

<div class="alert alert-info" style="margin-bottom:16px">
    ⚖️ Todos los contratos son inmutables una vez firmados.
    Puedes ver el documento completo y descargarlo en PDF para el expediente físico.
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
                    <th>Monto</th>
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
                    <td><?= htmlspecialchars($c['cliente_nombre']) ?></td>
                    <td><?= htmlspecialchars($c['codigo']) ?></td>
                    <td>📍 <?= htmlspecialchars($c['zona_nombre']) ?></td>
                    <td><strong>$<?= number_format($c['monto'], 0, ',', '.') ?></strong></td>
                    <td>
                        <span class="badge <?= $bc[$c['estado']] ?? 'badge-gray' ?>">
                            <?= $c['estado'] ?>
                        </span>
                    </td>
                    <td style="font-size:12px">
                        <?= $c['fecha_gen'] ? date('d/m/Y', strtotime($c['fecha_gen'])) : '—' ?>
                    </td>
                    <td style="font-size:12px">
                        <?= $c['fecha_firma']
                            ? date('d/m/Y', strtotime($c['fecha_firma']))
                            : '<span style="color:var(--muted)">Pendiente</span>' ?>
                    </td>
                    <td style="display:flex;gap:6px;flex-wrap:wrap">
                        <a href="/sgi_aurora/legal/contrato_view.php?id=<?= $c['id'] ?>"
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
                        <td colspan="9" style="text-align:center;padding:24px;color:var(--muted)">
                            Sin contratos generados aún
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
