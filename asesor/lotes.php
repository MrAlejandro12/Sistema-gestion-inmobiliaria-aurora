<?php // asesor/lotes.php
require_once __DIR__ . '/../config/db.php';
secureSessionStart(); requireAuth(['asesor','admin']);
$pdo=getDB();
$lotes=$pdo->query("SELECT l.*,z.nombre zona_nombre FROM lotes l JOIN zonas z ON z.id=l.zona_id WHERE l.estado IN('disponible','revision') ORDER BY l.estado,l.precio")->fetchAll();
$bgs=['bg1','bg2','bg3','bg4','bg5','bg6'];
$pageTitle='Lotes'; $pageSubtitle='Lotes disponibles y en revisión'; $activeNav='as-lotes';
require_once __DIR__.'/../includes/layout.php'; ?>
<div class="lots-grid">
<?php foreach($lotes as $i=>$l): $bg=$bgs[$i%6]; $svcs=array_filter(explode(',',$l['servicios']??'')); ?>
<div class="lot-card">
  <div class="lot-img <?= $bg ?>"><span><?= $l['emoji']?:'🏡' ?></span>
    <div class="lot-badge"><span class="badge <?= $l['estado']==='disponible'?'badge-green':'badge-gold' ?>"><?= $l['estado'] ?></span></div>
  </div>
  <div class="lot-body">
    <div class="lot-name"><?= htmlspecialchars($l['codigo'].' — '.$l['nombre']) ?></div>
    <div class="lot-loc">📍 <?= htmlspecialchars($l['zona_nombre']) ?></div>
    <div class="lot-price">$<?= number_format($l['precio'],0,',','.') ?></div>
    <div class="lot-tags"><span class="lot-tag">📐 <?= $l['superficie'] ?> m²</span>
      <?php foreach(array_slice($svcs,0,3) as $s): ?><span class="lot-tag"><?= trim($s) ?></span><?php endforeach; ?>
    </div>
    <div style="display:flex;gap:8px;margin-top:10px">
      <?php if($l['estado']==='disponible'&&$l['verificado']): ?>
        <button class="btn btn-primary btn-sm" onclick="showToast('Lote <?= $l['codigo'] ?> listo para asignar a cliente','ok')">Asignar cliente</button>
      <?php elseif(!$l['verificado']): ?>
        <span class="badge badge-orange">⏳ Sin verificar DDRR</span>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endforeach; if(empty($lotes)): ?>
<div class="alert alert-info">Sin lotes disponibles en este momento.</div>
<?php endif; ?>
</div>
<?php require_once __DIR__.'/../includes/layout_end.php'; ?>
