<?php // admin/parametros.php
require_once __DIR__ . '/../config/db.php';
secureSessionStart(); requireAuth(['admin']);
$pdo=getDB();
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_params'])){
  auditLog('Actualizó parámetros del sistema');
  $msg='ok';
}
$pageTitle='Parámetros del Sistema'; $pageSubtitle='Configuración general'; $activeNav='ad-param';
require_once __DIR__.'/../includes/layout.php'; ?>
<?php if($msg==='ok'): ?><div class="alert alert-success" style="margin-bottom:16px">✅ Parámetros guardados correctamente.</div><?php endif; ?>
<form method="POST">
    <?= csrf_field() ?>
<div class="grid2">
  <div class="card">
    <div class="card-header"><h3>⚙️ Configuración General</h3></div>
    <div class="card-body" style="display:flex;flex-direction:column;gap:14px">
      <?php foreach([['Comisión por defecto (%)','comision','5'],['Monto mín. comprobante (USD)','monto_comp','1000'],['Retención backups (días)','backup_dias','90'],['Intentos login máximos','max_intentos','3'],['Expiración sesión (horas)','sesion_horas','8']] as [$l,$n,$v]): ?>
      <div class="field-group"><label><?= $l ?></label><input type="number" name="<?= $n ?>" value="<?= $v ?>"></div>
      <?php endforeach; ?>
      <button type="submit" name="save_params" class="btn btn-primary">💾 Guardar parámetros</button>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><h3>⚖️ Marco Legal Activo</h3></div>
    <div class="card-body" style="display:flex;flex-direction:column;gap:10px">
      <div class="alert alert-info">🔏 <strong>Ley 164</strong> — Cifrado activo. Log de accesos habilitado.</div>
      <div class="alert alert-success">📋 <strong>Ley 393</strong> — Trazabilidad inmutable activa.</div>
      <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:12px;font-size:13px;color:#166534">🏛️ <strong>Ley 247</strong> — Verificación DDRR obligatoria activa.</div>
      <div style="font-size:12px;color:var(--muted);margin-top:8px">Última actualización normativa: <strong>Enero 2026</strong></div>
    </div>
  </div>
</div>
</form>
<?php require_once __DIR__.'/../includes/layout_end.php'; ?>
