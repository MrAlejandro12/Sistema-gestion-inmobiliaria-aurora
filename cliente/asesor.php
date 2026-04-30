<?php // cliente/asesor.php
require_once __DIR__ . '/../config/db.php';
secureSessionStart(); requireAuth(['cliente']);
$pdo=getDB(); $uid=$_SESSION['user_id'];
$cli=$pdo->prepare("SELECT c.*,ua.nombre asesor_nombre,ua.email asesor_email,ua.telefono asesor_tel FROM clientes c LEFT JOIN usuarios ua ON ua.id=c.asesor_id WHERE c.usuario_id=?");
$cli->execute([$uid]); $cli=$cli->fetch();
$pageTitle='Mi Asesor'; $pageSubtitle='Comunicación con tu asesor'; $activeNav='cli-asesor';
require_once __DIR__.'/../includes/layout.php'; ?>
<?php if($cli&&$cli['asesor_nombre']): ?>
<div class="card" style="max-width:500px">
  <div class="card-header"><h3>🤝 Tu asesor asignado</h3></div>
  <div class="card-body">
    <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px">
      <div class="avatar" style="width:56px;height:56px;font-size:18px"><?= strtoupper(substr($cli['asesor_nombre'],0,2)) ?></div>
      <div><div style="font-size:16px;font-weight:600"><?= htmlspecialchars($cli['asesor_nombre']) ?></div>
           <div style="font-size:13px;color:var(--muted)">Asesor de Ventas — Bienes Raíces Aurora</div></div>
    </div>
    <?php foreach([['📧 Email',$cli['asesor_email']],['📱 Teléfono',$cli['asesor_tel']??'—']] as [$k,$v]): ?>
    <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border)">
      <span style="font-size:13px;color:var(--muted)"><?= $k ?></span>
      <span style="font-size:13px;font-weight:500"><?= htmlspecialchars($v) ?></span>
    </div>
    <?php endforeach; ?>
    <div style="margin-top:16px;display:flex;gap:10px">
      <button class="btn btn-primary" onclick="showToast('Mensaje enviado a tu asesor','ok')">💬 Enviar mensaje</button>
      <a href="<?= 'https://wa.me/'.preg_replace('/[^0-9]/','',$cli['asesor_tel']??'') ?>" target="_blank" class="btn btn-green">📱 WhatsApp</a>
    </div>
  </div>
</div>
<?php else: ?>
<div class="alert alert-info">Aún no tienes un asesor asignado. El administrador te asignará uno pronto.</div>
<?php endif; ?>
<?php require_once __DIR__.'/../includes/layout_end.php'; ?>
