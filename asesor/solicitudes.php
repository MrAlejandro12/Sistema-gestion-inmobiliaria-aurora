<?php // asesor/solicitudes.php
require_once __DIR__ . '/../config/db.php';
secureSessionStart(); requireAuth(['asesor','admin']);
$pdo=getDB();
$solic=$pdo->query("SELECT s.*,uc.nombre cliente_nombre,z.nombre zona_nombre,l.codigo lote_codigo,l.nombre lote_nombre FROM solicitudes s JOIN clientes c ON c.id=s.cliente_id JOIN usuarios uc ON uc.id=c.usuario_id LEFT JOIN zonas z ON z.id=s.zona_id LEFT JOIN lotes l ON l.id=s.lote_id ORDER BY s.created_at DESC")->fetchAll();
$pageTitle='Solicitudes'; $pageSubtitle='Gestión de solicitudes de clientes'; $activeNav='as-sol';
require_once __DIR__.'/../includes/layout.php'; ?>
<div class="card"><div class="card-body p0">
<table class="table"><thead><tr><th>#</th><th>Cliente</th><th>Zona</th><th>Presupuesto máx.</th><th>Lote asignado</th><th>Estado</th><th>Fecha</th></tr></thead>
<tbody>
<?php foreach($solic as $s):
  $bc=['pendiente'=>'badge-orange','en_proceso'=>'badge-blue','recomendacion_enviada'=>'badge-gold','cerrada'=>'badge-green','cancelada'=>'badge-red'];
?><tr>
<td><strong>S<?= str_pad($s['id'],3,'0',STR_PAD_LEFT) ?></strong></td>
<td><?= htmlspecialchars($s['cliente_nombre']) ?></td>
<td><?= htmlspecialchars($s['zona_nombre']??'—') ?></td>
<td><?= $s['presupuesto_max']?'$'.number_format($s['presupuesto_max'],0):'—' ?></td>
<td><?= $s['lote_codigo']?htmlspecialchars($s['lote_codigo'].' – '.$s['lote_nombre']):'<span style="color:var(--muted);font-size:12px">Sin asignar</span>' ?></td>
<td><span class="badge <?= $bc[$s['estado']]??'badge-gray' ?>"><?= $s['estado'] ?></span></td>
<td style="font-size:12px;color:var(--muted)"><?= date('d/m/Y',strtotime($s['created_at'])) ?></td>
</tr><?php endforeach;
if(empty($solic)): ?><tr><td colspan="7" style="text-align:center;padding:24px;color:var(--muted)">Sin solicitudes registradas</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php require_once __DIR__.'/../includes/layout_end.php'; ?>
