<?php
if(!function_exists('csrf_token')){
  require_once dirname(__DIR__).'/includes/security.php';
}
// includes/layout.php — Layout base compartido
// Uso: require_once __DIR__ . '/../includes/layout.php';
// Debe definir $pageTitle y $activeNav antes de incluirlo
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'SGI Aurora') ?> — SGI Aurora</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{
  --navy:#0A2647;--navy-l:#1a3a6b;--navy-d:#061830;
  --gold:#D4AF37;--gold-l:#e8c84a;
  --green:#27AE60;--orange:#F39C12;--red:#E74C3C;--blue:#2E86C1;
  --bg:#F5F7FA;--white:#fff;--text:#1a202c;--muted:#64748b;
  --border:#e2e8f0;--sidebar-w:240px;
}
body{font-family:'Poppins',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex}
/* SIDEBAR */
.sidebar{width:var(--sidebar-w);background:var(--navy);min-height:100vh;display:flex;flex-direction:column;flex-shrink:0;position:sticky;top:0;height:100vh;overflow-y:auto}
.sb-header{padding:20px 16px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:10px}
.sb-logo{width:36px;height:36px;flex-shrink:0;background:linear-gradient(135deg,var(--gold),var(--gold-l));border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px}
.sb-brand-name{color:#fff;font-size:15px;font-weight:600}
.sb-brand-sub{color:rgba(255,255,255,.45);font-size:10px}
.sb-nav{flex:1;padding:16px 8px;overflow-y:auto}
.nav-section{color:rgba(255,255,255,.3);font-size:10px;font-weight:600;letter-spacing:1px;text-transform:uppercase;padding:0 8px;margin:16px 0 6px}
.nav-item{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;cursor:pointer;transition:all .2s;margin-bottom:2px;text-decoration:none}
.nav-item:hover{background:rgba(255,255,255,.07)}
.nav-item.active{background:rgba(212,175,55,.15);border-left:3px solid var(--gold)}
.nav-icon{width:20px;flex-shrink:0;text-align:center;font-size:16px;opacity:.7}
.nav-item.active .nav-icon{opacity:1}
.nav-label{color:rgba(255,255,255,.75);font-size:13px;white-space:nowrap}
.nav-item.active .nav-label{color:var(--gold);font-weight:500}
.sb-footer{padding:16px;border-top:1px solid rgba(255,255,255,.08)}
.user-pill{display:flex;align-items:center;gap:10px}
.avatar{width:36px;height:36px;flex-shrink:0;border-radius:50%;background:linear-gradient(135deg,var(--gold),var(--gold-l));display:flex;align-items:center;justify-content:center;color:var(--navy-d);font-size:13px;font-weight:700}
.user-name{color:#fff;font-size:13px;font-weight:500;flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.user-role{color:rgba(255,255,255,.45);font-size:11px}
.logout-btn{background:none;border:none;color:rgba(255,255,255,.4);cursor:pointer;font-size:16px;padding:4px;transition:color .2s}
.logout-btn:hover{color:var(--red)}
/* MAIN */
.main{flex:1;display:flex;flex-direction:column;min-height:100vh;overflow:hidden}
.topbar{background:var(--white);padding:0 28px;height:64px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border);position:sticky;top:0;z-index:10;flex-shrink:0}
.topbar h2{font-size:18px;font-weight:600}
.topbar-sub{font-size:12px;color:var(--muted)}
.topbar-right{display:flex;align-items:center;gap:12px}
.page{padding:28px;flex:1;overflow-y:auto}
/* CARDS */
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:28px}
.kpi-card{background:var(--white);border-radius:16px;padding:20px 24px;border:1px solid var(--border);position:relative;overflow:hidden}
.kpi-card::after{content:'';position:absolute;right:-20px;bottom:-20px;width:80px;height:80px;border-radius:50%;opacity:.07}
.kpi-card.blue::after{background:var(--navy)}.kpi-card.gold::after{background:var(--gold)}.kpi-card.green::after{background:var(--green)}.kpi-card.orange::after{background:var(--orange)}
.kpi-icon{position:absolute;top:20px;right:20px;width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px}
.kpi-card.blue .kpi-icon{background:#e8f0fe}.kpi-card.gold .kpi-icon{background:#fdf9e8}.kpi-card.green .kpi-icon{background:#e8f8ef}.kpi-card.orange .kpi-icon{background:#fef6e8}
.kpi-label{font-size:12px;color:var(--muted);font-weight:500;margin-bottom:8px}
.kpi-value{font-size:28px;font-weight:700}.kpi-sub{font-size:12px;color:var(--muted);margin-top:4px}
.card{background:var(--white);border-radius:16px;border:1px solid var(--border);margin-bottom:20px}
.card-header{padding:20px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.card-header h3{font-size:15px;font-weight:600}
.card-body{padding:24px}
.card-body.p0{padding:0}
/* TABLE */
.table{width:100%;border-collapse:collapse}
.table th{padding:10px 14px;text-align:left;font-size:12px;font-weight:600;color:var(--muted);background:#f8fafc;border-bottom:1px solid var(--border);text-transform:uppercase;letter-spacing:.5px}
.table td{padding:12px 14px;font-size:13px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
.table tr:last-child td{border-bottom:none}
.table tbody tr:hover td{background:#fafbfc}
/* BADGES */
.badge{display:inline-flex;align-items:center;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:600}
.badge-green{background:#dcfce7;color:#15803d}.badge-orange{background:#fff7ed;color:#c2410c}
.badge-red{background:#fee2e2;color:#b91c1c}.badge-blue{background:#dbeafe;color:#1d4ed8}
.badge-gray{background:#f1f5f9;color:#475569}.badge-gold{background:#fef9c3;color:#854d0e}
/* BUTTONS */
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;font-family:'Poppins',sans-serif;transition:all .2s;border:none;text-decoration:none}
.btn-primary{background:var(--navy);color:#fff}.btn-primary:hover{background:var(--navy-l)}
.btn-gold{background:linear-gradient(135deg,var(--gold),var(--gold-l));color:var(--navy-d)}.btn-gold:hover{opacity:.9}
.btn-green{background:var(--green);color:#fff}.btn-green:hover{background:#219a52}
.btn-orange{background:var(--orange);color:#fff}
.btn-red{background:var(--red);color:#fff}.btn-red:hover{background:#c0392b}
.btn-outline{background:transparent;border:1px solid var(--border);color:var(--text)}.btn-outline:hover{border-color:var(--navy);color:var(--navy)}
.btn-sm{padding:6px 12px;font-size:12px}
/* FORM */
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
.form-row.single{grid-template-columns:1fr}.form-row.triple{grid-template-columns:1fr 1fr 1fr}
.field-group{display:flex;flex-direction:column;gap:6px}
.field-group label{font-size:13px;font-weight:500}
.field-group input,.field-group select,.field-group textarea{padding:10px 14px;border:1px solid var(--border);border-radius:8px;font-size:14px;font-family:'Poppins',sans-serif;color:var(--text);background:var(--white);transition:border-color .2s;outline:none}
.field-group input:focus,.field-group select:focus,.field-group textarea:focus{border-color:var(--navy)}
/* ALERT */
.alert{padding:14px 18px;border-radius:12px;font-size:13px;display:flex;align-items:flex-start;gap:10px;margin-bottom:16px}
.alert-info{background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af}
.alert-warn{background:#fffbeb;border:1px solid #fde68a;color:#92400e}
.alert-success{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534}
.alert-error{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
/* MODAL */
.modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;padding:20px}
.modal-bg.open{display:flex}
.modal{background:#fff;border-radius:20px;width:100%;max-width:600px;max-height:90vh;overflow-y:auto;animation:mIn .25s ease}
.modal-lg{max-width:800px}
@keyframes mIn{from{opacity:0;transform:scale(.95) translateY(20px)}to{opacity:1;transform:none}}
.modal-header{padding:24px 28px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:#fff}
.modal-header h3{font-size:16px;font-weight:600}
.modal-close{width:32px;height:32px;border-radius:50%;border:1px solid var(--border);background:none;cursor:pointer;font-size:18px;display:flex;align-items:center;justify-content:center;color:var(--muted)}
.modal-close:hover{background:var(--bg)}
.modal-body{padding:24px 28px}
.modal-footer{padding:16px 28px 24px;display:flex;justify-content:flex-end;gap:10px;border-top:1px solid var(--border)}
/* TOAST */
#toast-wrap{position:fixed;bottom:28px;right:28px;z-index:9999;display:flex;flex-direction:column;gap:10px}
.toast{background:var(--navy);color:#fff;padding:14px 20px;border-radius:12px;font-size:13px;font-weight:500;display:flex;align-items:center;gap:10px;box-shadow:0 8px 24px rgba(0,0,0,.2);animation:toastIn .3s ease;min-width:280px;border-left:4px solid var(--gold)}
.toast.ok{border-left-color:var(--green)}.toast.err{border-left-color:var(--red)}.toast.warn{border-left-color:var(--orange)}
@keyframes toastIn{from{opacity:0;transform:translateX(20px)}to{opacity:1;transform:none}}
/* LOT CARDS */
.lots-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:20px}
.lot-card{background:#fff;border-radius:16px;border:1px solid var(--border);overflow:hidden;transition:all .2s;cursor:pointer}
.lot-card:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(10,38,71,.12)}
.lot-img{height:160px;display:flex;align-items:center;justify-content:center;font-size:64px;position:relative}
.lot-img.bg1{background:linear-gradient(135deg,#1a4a2e,#2d7a4f)}.lot-img.bg2{background:linear-gradient(135deg,#2c1a4a,#4a2d7a)}.lot-img.bg3{background:linear-gradient(135deg,#4a2a1a,#7a4a2d)}.lot-img.bg4{background:linear-gradient(135deg,#1a3a4a,#2d6a7a)}.lot-img.bg5{background:linear-gradient(135deg,#3a4a1a,#6a7a2d)}.lot-img.bg6{background:linear-gradient(135deg,#4a3a1a,#7a6a2d)}
.lot-badge{position:absolute;top:12px;right:12px}
.lot-body{padding:16px}
.lot-name{font-size:14px;font-weight:600;margin-bottom:4px}
.lot-loc{font-size:12px;color:var(--muted);margin-bottom:10px}
.lot-price{font-size:18px;font-weight:700;color:var(--navy);margin-bottom:12px}
.lot-tags{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px}
.lot-tag{font-size:11px;color:var(--muted);background:#f8fafc;padding:3px 8px;border-radius:6px}
/* GRID 2 */
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:20px}
/* PROGRESS BAR */
.prog-track{height:8px;background:var(--border);border-radius:4px;overflow:hidden}
.prog-fill{height:100%;border-radius:4px;transition:width .4s}
/* RESPONSIVE */
@media(max-width:768px){
  .sidebar{width:64px}.sb-brand-name,.sb-brand-sub,.nav-label,.nav-section,.user-name,.user-role{display:none}
  .lots-grid{grid-template-columns:1fr}.form-row{grid-template-columns:1fr}.kpi-grid{grid-template-columns:repeat(2,1fr)}.page{padding:16px}
}
</style>
</head>
<body>

<?php
// Construir navegación según rol
$rol = $_SESSION['rol'] ?? '';
$navs = [
  'cliente' => [
    ['seccion'=>'MENÚ','items'=>[
      ['url'=>'/sgi_aurora/cliente/dashboard.php','icon'=>'🏠','label'=>'Inicio','key'=>'cli-dash'],
      ['url'=>'/sgi_aurora/cliente/catalogo.php', 'icon'=>'📋','label'=>'Catálogo','key'=>'cli-cat'],
      ['url'=>'/sgi_aurora/cliente/asesor.php',   'icon'=>'💬','label'=>'Mi Asesor','key'=>'cli-asesor'],
      ['url'=>'/sgi_aurora/cliente/cuenta.php',   'icon'=>'💳','label'=>'Mi Cuenta','key'=>'cli-cuenta'],
      ['url'=>'/sgi_aurora/cliente/consultas.php', 'icon'=>'💬','label'=>'Consultas <span class="nav-notification-badge" style="display:none">0</span>','key'=>'cli-consultas'],
    ]],
  ],
  'asesor' => [
    ['seccion'=>'PRINCIPAL','items'=>[
      ['url'=>'/sgi_aurora/asesor/dashboard.php',    'icon'=>'📊','label'=>'Dashboard','key'=>'as-dash'],
    ]],
    ['seccion'=>'OPERACIONES','items'=>[
      ['url'=>'/sgi_aurora/asesor/solicitudes.php',  'icon'=>'📋','label'=>'Solicitudes','key'=>'as-sol'],
      ['url'=>'/sgi_aurora/asesor/clientes.php',     'icon'=>'👥','label'=>'Clientes','key'=>'as-cli'],
      ['url'=>'/sgi_aurora/asesor/lotes.php',        'icon'=>'🏡','label'=>'Lotes','key'=>'as-lotes'],
    ]],
    ['seccion'=>'CLIENTES','items'=>[
      ['url'=>'/sgi_aurora/asesor/consultas.php','icon'=>'💬','label'=>'Consultas <span class="nav-notification-badge" style="display:none">0</span>','key'=>'as-consultas'],
    ]],
    ['seccion'=>'CIERRE','items'=>[
      ['url'=>'/sgi_aurora/asesor/ventas.php',       'icon'=>'📈','label'=>'Ventas','key'=>'as-ven'],
      ['url'=>'/sgi_aurora/asesor/contratos.php',    'icon'=>'📄','label'=>'Contratos','key'=>'as-con'],
    ]],
  ],
  'legal' => [
    ['seccion'=>'LEGAL','items'=>[
      ['url'=>'/sgi_aurora/legal/dashboard.php',     'icon'=>'📊','label'=>'Dashboard','key'=>'lg-dash'],
      ['url'=>'/sgi_aurora/legal/tareas.php',        'icon'=>'✅','label'=>'Tareas','key'=>'lg-tar'],
      ['url'=>'/sgi_aurora/legal/contratos.php',     'icon'=>'📄','label'=>'Contratos','key'=>'lg-con'],
      ['url'=>'/sgi_aurora/legal/trazabilidad.php',  'icon'=>'🔗','label'=>'Trazabilidad','key'=>'lg-traz'],
    ]],
  ],
  'admin' => [
    ['seccion'=>'PRINCIPAL','items'=>[
      ['url'=>'/sgi_aurora/admin/dashboard.php',     'icon'=>'📊','label'=>'Dashboard','key'=>'ad-dash'],
    ]],
    ['seccion'=>'GESTIÓN','items'=>[
      ['url'=>'/sgi_aurora/admin/lotes.php',         'icon'=>'🏡','label'=>'Lotes','key'=>'ad-lotes'],
      ['url'=>'/sgi_aurora/admin/usuarios.php',      'icon'=>'👥','label'=>'Usuarios','key'=>'ad-users'],
      ['url'=>'/sgi_aurora/admin/reportes.php',      'icon'=>'📈','label'=>'Reportes','key'=>'ad-rep'],
    ]],
    ['seccion'=>'SISTEMA','items'=>[
      ['url'=>'/sgi_aurora/admin/documentos.php',    'icon'=>'📜','label'=>'Documentos','key'=>'ad-docs'],
      ['url'=>'/sgi_aurora/admin/parametros.php',    'icon'=>'⚙️', 'label'=>'Parámetros','key'=>'ad-param'],
      ['url'=>'/sgi_aurora/admin/bitacora.php',       'icon'=>'📜','label'=>'Bitácora','key'=>'ad-audit'],
    ]],
  ],
];
$initials = strtoupper(substr($nombre_sb ?? $_SESSION['nombre'] ?? '?', 0, 2));
?>

<div class="sidebar">
  <div class="sb-header">
    <div class="sb-logo">🏠</div>
    <div>
      <div class="sb-brand-name">SGI Aurora</div>
      <div class="sb-brand-sub"><?= ucfirst($rol) ?></div>
    </div>
  </div>
  <nav class="sb-nav">
    <?php foreach (($navs[$rol] ?? []) as $sec): ?>
      <div class="nav-section"><?= $sec['seccion'] ?></div>
      <?php foreach ($sec['items'] as $item): ?>
        <a href="<?= $item['url'] ?>" class="nav-item <?= ($activeNav ?? '') === $item['key'] ? 'active' : '' ?>">
          <span class="nav-icon"><?= $item['icon'] ?></span>
          <span class="nav-label"><?= $item['label'] ?></span>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>
  <div class="sb-footer">
    <div class="user-pill">
      <div class="avatar"><?= $initials ?></div>
      <div style="flex:1;min-width:0">
        <div class="user-name"><?= htmlspecialchars($_SESSION['nombre'] ?? '') ?></div>
        <div class="user-role"><?= ucfirst($rol) ?></div>
      </div>
      <a href="/sgi_aurora/logout.php" class="logout-btn" title="Cerrar sesión">⏻</a>
    </div>
  </div>
</div>

<div class="main">
<!-- ── Notification badge in realtime ── -->
<style>
.nav-badge{
  display:inline-flex;align-items:center;justify-content:center;
  min-width:18px;height:18px;border-radius:9px;
  background:var(--red);color:#fff;
  font-size:10px;font-weight:700;margin-left:6px;
  animation:badgePop .3s ease;
}
@keyframes badgePop{from{transform:scale(0)}to{transform:scale(1)}}
</style>
<script>
(function() {
  let prevCount = 0;
  async function pollBadge() {
    try {
      const r = await fetch('/sgi_aurora/api_realtime.php?action=badge_count');
      const d = await r.json();
      if (d.ok) {
        const count = d.count || 0;
        // Update all nav badges
        document.querySelectorAll('.nav-notification-badge').forEach(el => {
          if (count > 0) {
            el.textContent = count;
            el.style.display = 'inline-flex';
          } else {
            el.style.display = 'none';
          }
        });
        if (count > prevCount && prevCount >= 0) {
          document.querySelectorAll('.nav-notification-badge').forEach(el => {
            el.style.animation = 'none';
            requestAnimationFrame(() => el.style.animation = 'badgePop .3s ease');
          });
        }
        prevCount = count;
      }
    } catch(e) {}
  }
  document.addEventListener('DOMContentLoaded', () => {
    pollBadge();
    setInterval(pollBadge, 7000);
  });
})();
</script>

  <div class="topbar">
    <div>
      <h2><?= htmlspecialchars($pageTitle ?? 'Panel') ?></h2>
      <div class="topbar-sub"><?= htmlspecialchars($pageSubtitle ?? '') ?></div>
    </div>
    <div class="topbar-right">
      <div class="avatar" style="cursor:default"><?= $initials ?></div>
    </div>
  </div>
  <div class="page">
  <!-- CONTENT STARTS -->
