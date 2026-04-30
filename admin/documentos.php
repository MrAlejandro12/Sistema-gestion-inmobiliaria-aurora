<?php // admin/documentos.php
require_once __DIR__ . '/../config/db.php';
secureSessionStart(); requireAuth(['admin','legal']);
$pageTitle='Documentos Legales'; $pageSubtitle='Marco normativo y plantillas'; $activeNav='ad-docs';
require_once __DIR__.'/../includes/layout.php'; ?>
<div class="grid2">
  <div class="card">
    <div class="card-header"><h3>🔏 Marco Legal Aplicable</h3></div>
    <div class="card-body" style="display:flex;flex-direction:column;gap:14px">
      <div class="alert alert-info">
        <div><strong>Ley 164 (2011)</strong> — Protección de Datos<br>
        <span style="font-size:12px">Establece principios de privacidad, acceso por roles, cifrado de datos personales y obligatoriedad de log de auditoría.</span></div>
      </div>
      <div class="alert alert-success">
        <div><strong>Ley 393 (2013)</strong> — Servicios Financieros<br>
        <span style="font-size:12px">Trazabilidad de todas las transacciones financieras. Comprobante obligatorio para montos superiores a USD 1,000. Log inmutable.</span></div>
      </div>
      <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:14px;font-size:13px;color:#166534">
        <strong>Ley 247 (2012)</strong> — Regularización del Derecho Propietario<br>
        <span style="font-size:12px">Verificación obligatoria en Derechos Reales (DDRR) antes de comercializar cualquier propiedad.</span>
      </div>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><h3>📁 Plantillas de Contratos</h3></div>
    <div class="card-body" style="display:flex;flex-direction:column;gap:10px">
      <?php foreach([['Contrato de Compraventa','Plantilla estándar v2026.1'],['Contrato de Apartado','Con cláusula de anticipo'],['Contrato Anticrético','Art. 409 Código Civil Bolivia'],['Acuerdo de Crédito Directo','Cuotas y tasas variables']] as [$t,$s]): ?>
      <div style="display:flex;align-items:center;gap:14px;padding:14px;border-radius:10px;border:1px solid var(--border)">
        <div style="width:40px;height:40px;background:#e8f0fe;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px">📄</div>
        <div style="flex:1"><div style="font-size:14px;font-weight:600"><?= $t ?></div><div style="font-size:12px;color:var(--muted)"><?= $s ?></div></div>
        <button class="btn btn-outline btn-sm" onclick="showToast('Descargando plantilla...','ok')">📥 PDF</button>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php require_once __DIR__.'/../includes/layout_end.php'; ?>
