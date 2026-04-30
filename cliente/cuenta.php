<?php // cliente/cuenta.php
require_once __DIR__ . '/../config/db.php';
secureSessionStart(); requireAuth(['cliente']);
$pdo=getDB(); $uid=$_SESSION['user_id'];
$cli=$pdo->prepare("SELECT c.*,u.nombre,u.email,u.telefono,u.ci,z.nombre zona_nombre,ua.nombre asesor_nombre FROM clientes c JOIN usuarios u ON u.id=c.usuario_id LEFT JOIN zonas z ON z.id=c.zona_preferida LEFT JOIN usuarios ua ON ua.id=c.asesor_id WHERE c.usuario_id=?");
$cli->execute([$uid]); $cli=$cli->fetch();
$pagos=[];
if($cli){ $s=$pdo->prepare("SELECT p.*,l.codigo,l.nombre ln,l.precio FROM pagos p JOIN ventas v ON v.id=p.venta_id JOIN lotes l ON l.id=v.lote_id WHERE p.cliente_id=? ORDER BY p.fecha_pago DESC"); $s->execute([$cli['id']]); $pagos=$s->fetchAll(); }
$total=array_sum(array_column($pagos,'monto'));
$pageTitle='Mi Cuenta'; $pageSubtitle='Estado financiero de tu cuenta'; $activeNav='cli-cuenta';
require_once __DIR__.'/../includes/layout.php'; ?>
<div class="grid2">
  <div class="card">
    <div class="card-header"><h3>👤 Mi perfil</h3></div>
    <div class="card-body">
      <?php foreach([['Nombre',$cli['nombre']??'—'],['Email',$cli['email']??'—'],['Teléfono',$cli['telefono']??'—'],['C.I.',$cli['ci']??'—'],['Zona preferida',$cli['zona_nombre']??'—'],['Asesor asignado',$cli['asesor_nombre']??'Sin asesor'],['Presupuesto','$'.number_format($cli['presupuesto_min']??0,0).' – $'.number_format($cli['presupuesto_max']??0,0)]] as [$k,$v]): ?>
      <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border)"><span style="font-size:13px;color:var(--muted)"><?= $k ?></span><span style="font-size:13px;font-weight:500"><?= htmlspecialchars($v) ?></span></div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><h3>💳 Mis pagos realizados</h3></div>
    <div class="card-body p0">
      <?php if(empty($pagos)): ?><div style="padding:24px;text-align:center;color:var(--muted);font-size:13px">Sin pagos registrados aún</div>
      <?php else: ?>
      <table class="table">
        <thead><tr><th>Lote</th><th>Monto</th><th>Método</th><th>Fecha</th></tr></thead>
        <tbody>
          <?php foreach($pagos as $p): ?><tr>
            <td><?= htmlspecialchars($p['codigo']) ?></td>
            <td><strong>$<?= number_format($p['monto'],0,',','.') ?></strong></td>
            <td style="font-size:12px"><?= $p['metodo'] ?></td>
            <td style="font-size:12px;color:var(--muted)"><?= date('d/m/Y',strtotime($p['fecha_pago'])) ?></td>
          </tr><?php endforeach; ?>
        </tbody>
      </table>
      <div style="background:linear-gradient(135deg,var(--navy),var(--navy-l));border-radius:12px;padding:16px 20px;margin:16px;display:flex;justify-content:space-between;align-items:center">
        <span style="font-size:13px;color:rgba(255,255,255,.8)">💰 Total pagado</span>
        <span style="font-size:22px;font-weight:700;color:var(--gold)">$<?= number_format($total,0,',','.') ?></span>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require_once __DIR__.'/../includes/layout_end.php'; ?>
