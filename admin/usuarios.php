<?php
ob_start();
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/db.php';
secureSessionStart();
requireAuth(['admin']);
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') { 
    ob_end_clean();
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $nombre = trim($_POST['nombre'] ?? '');
        $email  = trim($_POST['email']  ?? '');
        $pass   = trim($_POST['password'] ?? DEFAULT_TEMP_PASSWORD);
        $rol    = $_POST['rol'] ?? 'cliente';
        $tel    = trim($_POST['telefono'] ?? '');
        $ci     = trim($_POST['ci'] ?? '');
        if (!$nombre || !$email) { echo json_encode(['ok'=>false,'message'=>'Nombre y email obligatorios']); exit; }
        // Check email unique
        $stmtExists = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmtExists->execute([$email]);
        if ($stmtExists->fetch() !== false) {
            echo json_encode(['ok'=>false,'message'=>'El email ya está registrado']); exit;
        }
        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO usuarios (nombre,email,password,rol,telefono,ci) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$nombre,$email,$hash,$rol,$tel,$ci]);
        $uid = $pdo->lastInsertId();
        // Si es cliente, crear perfil
        if ($rol === 'cliente') {
            $pdo->prepare("INSERT INTO clientes (usuario_id,consentimiento_ley164) VALUES (?,1)")->execute([$uid]);
        }
        auditLog("Creó usuario: $email ($rol)", 'usuarios', $uid);
        echo json_encode([
            'ok'      => true,
            'message' => "Usuario $nombre creado correctamente",
            'credentials' => ['nombre'=>$nombre,'email'=>$email,'password'=>$pass,'rol'=>$rol],
            'reload'  => false,
        ]); exit;
    }

    if ($action === 'toggle') {
        $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
        if ($id === $_SESSION['user_id']) { echo json_encode(['ok'=>false,'message'=>'No puedes desactivar tu propia cuenta']); exit; }
        $stmtU = $pdo->prepare("SELECT estado, email FROM usuarios WHERE id = ?");
        $stmtU->execute([$id]);
        $u = $stmtU->fetch();
        if ($u === false) { echo json_encode(['ok'=>false,'message'=>'Usuario no encontrado']); exit; }
        $nuevo = $u['estado'] === 'activo' ? 'inactivo' : 'activo';
        $pdo->prepare("UPDATE usuarios SET estado=? WHERE id=?")->execute([$nuevo,$id]);
        auditLog("Cambió estado usuario {$u['email']} a $nuevo", 'usuarios', $id);
        echo json_encode(['ok'=>true,'message'=>"Usuario $nuevo",'reload'=>true]); exit;
    }
}

$usuarios = $pdo->query("SELECT * FROM usuarios ORDER BY rol, nombre")->fetchAll();

$pageTitle = 'Gestión de Usuarios'; $pageSubtitle = count($usuarios).' usuario(s) registrado(s)'; $activeNav = 'ad-users';
require_once __DIR__ . '/../includes/layout.php';
?>

<div style="display:flex;justify-content:flex-end;margin-bottom:16px">
  <button onclick="openModal('modal-usuario')" class="btn btn-primary">➕ Crear usuario</button>
</div>

<div class="card">
  <div class="card-body p0">
    <table class="table">
      <thead><tr><th>Usuario</th><th>Email</th><th>Rol</th><th>CI</th><th>Teléfono</th><th>Estado</th><th>Último acceso</th><th>Acción</th></tr></thead>
      <tbody>
        <?php foreach ($usuarios as $u):
          $initials = strtoupper(substr($u['nombre'],0,2));
          $rolColor = ['admin'=>'badge-red','legal'=>'badge-orange','asesor'=>'badge-green','cliente'=>'badge-blue'];
        ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div class="avatar" style="width:32px;height:32px;font-size:11px"><?= $initials ?></div>
              <span style="font-weight:500"><?= htmlspecialchars($u['nombre']) ?></span>
            </div>
          </td>
          <td style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($u['email']) ?></td>
          <td><span class="badge <?= $rolColor[$u['rol']] ?? 'badge-gray' ?>"><?= $u['rol'] ?></span></td>
          <td style="font-size:12px"><?= htmlspecialchars($u['ci'] ?? '—') ?></td>
          <td style="font-size:12px"><?= htmlspecialchars($u['telefono'] ?? '—') ?></td>
          <td><span class="badge <?= $u['estado']==='activo'?'badge-green':'badge-gray' ?>"><?= $u['estado'] ?></span></td>
          <td style="font-size:11px;color:var(--muted)"><?= $u['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($u['ultimo_acceso'])) : '—' ?></td>
          <td>
            <button class="btn btn-outline btn-sm" onclick="toggleUser(<?= $u['id'] ?>, '<?= $u['estado'] ?>')">
              <?= $u['estado']==='activo' ? '🔒 Desactivar' : '🔓 Activar' ?>
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-bg" id="modal-usuario">
  <div class="modal">
    <div class="modal-header"><h3>👤 Crear nuevo usuario</h3><button class="modal-close" onclick="closeModal('modal-usuario')">×</button></div>
    <form id="form-usuario" method="POST" action="usuarios.php">
    <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <div class="modal-body">
        <div class="form-row">
          <div class="field-group"><label>Nombre completo *</label><input type="text" name="nombre" placeholder="Ana López Vaca" required></div>
          <div class="field-group"><label>Email *</label><input type="email" name="email" placeholder="usuario@aurora.com" required></div>
        </div>
        <div class="form-row">
          <div class="field-group"><label>Rol *</label>
            <select name="rol"><option value="cliente">Cliente</option><option value="asesor">Asesor</option><option value="legal">Legal</option><option value="admin">Admin</option></select></div>
          <div class="field-group"><label>Contraseña</label><input type="text" name="password" placeholder="Contraseña temporal" value="<?= htmlspecialchars(DEFAULT_TEMP_PASSWORD) ?>"></div>
        </div>
        <div class="form-row">
          <div class="field-group"><label>Teléfono</label><input type="text" name="telefono" placeholder="+591 7X XXXXXX"></div>
          <div class="field-group"><label>CI</label><input type="text" name="ci" placeholder="7890123 SC"></div>
        </div>
        <div class="alert alert-info">🔒 Datos protegidos conforme a <strong>Ley 164</strong> de Bolivia.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-usuario')">Cancelar</button>
        <button type="submit" class="btn btn-primary" data-label="Crear usuario">💾 Crear usuario</button>
      </div>
    </form>
  </div>
</div>

<script>
// Override submitForm para mostrar credenciales
document.getElementById('form-usuario').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = this.querySelector('[type=submit]');
  if(btn){ btn.disabled=true; btn.textContent='Creando...'; }
  try {
    const res = await fetch(window.location.pathname, {method:'POST', body:new FormData(this)});
    const data = await res.json();
    if(data.ok && data.credentials) {
      closeModal('modal-usuario');
      document.getElementById('au-nombre').textContent = data.credentials.nombre;
      document.getElementById('au-email').textContent  = data.credentials.email;
      document.getElementById('au-pass').textContent   = data.credentials.password;
      document.getElementById('au-rol').textContent    = data.credentials.rol;
      openModal('modal-cred-admin');
    } else if(data.ok) {
      showToast(data.message||'Usuario creado', 'ok');
      closeModal('modal-usuario');
      setTimeout(()=>location.reload(), 800);
    } else {
      showToast(data.message||'Error', 'err');
    }
  } catch(e){ showToast('Error de conexión','err'); }
  finally { if(btn){ btn.disabled=false; btn.textContent=btn.dataset.label||'Crear usuario'; } }
});

function toggleUser(id, estado) {
  const msg = estado==='activo' ? '¿Desactivar este usuario?' : '¿Activar este usuario?';
  if(confirm(msg)) {
    fetch('usuarios.php',{
      method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:'action=toggle&id='+id
    }).then(r=>r.json()).then(d=>{ showToast(d.message,d.ok?'ok':'err'); if(d.ok) setTimeout(()=>location.reload(),800); });
  }
}
function copiarAdmin(elementId) {
  const txt = document.getElementById(elementId).textContent.trim();
  navigator.clipboard.writeText(txt).then(()=>showToast('Copiado','ok')).catch(()=>{
    const el=document.createElement('textarea'); el.value=txt; document.body.appendChild(el);
    el.select(); document.execCommand('copy'); document.body.removeChild(el); showToast('Copiado','ok');
  });
}
</script>

<!-- Modal credenciales admin -->
<div class="modal-bg" id="modal-cred-admin">
  <div class="modal" style="max-width:460px">
    <div class="modal-header" style="background:var(--navy);border-radius:20px 20px 0 0">
      <h3 style="color:#fff">✅ Usuario creado exitosamente</h3>
      <button class="modal-close" style="border-color:rgba(255,255,255,.3);color:#fff" onclick="closeModal('modal-cred-admin');location.reload()">×</button>
    </div>
    <div class="modal-body">
      <div style="text-align:center;margin-bottom:20px">
        <div style="font-size:48px;margin-bottom:8px">🎉</div>
        <div style="font-size:15px;font-weight:600;color:var(--navy)" id="au-nombre">—</div>
        <div style="font-size:13px;color:var(--muted)">Rol: <span id="au-rol" style="font-weight:600">—</span> — ya puede iniciar sesión</div>
      </div>
      <div style="background:#f8fafc;border-radius:12px;border:1px solid var(--border);padding:20px;margin-bottom:16px">
        <div style="font-size:12px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:14px">Credenciales de acceso</div>
        <div style="margin-bottom:14px">
          <div style="font-size:12px;color:var(--muted);margin-bottom:4px">📧 Correo electrónico</div>
          <div style="display:flex;align-items:center;gap:8px">
            <div id="au-email" style="flex:1;font-size:13px;font-weight:600;color:var(--navy);background:#fff;padding:10px 14px;border-radius:8px;border:1px solid var(--border)">—</div>
            <button class="btn btn-outline btn-sm" onclick="copiarAdmin('au-email')">📋</button>
          </div>
        </div>
        <div>
          <div style="font-size:12px;color:var(--muted);margin-bottom:4px">🔑 Contraseña temporal</div>
          <div style="display:flex;align-items:center;gap:8px">
            <div id="au-pass" style="flex:1;font-size:16px;font-weight:700;color:var(--navy);background:#fff;padding:10px 14px;border-radius:8px;border:2px solid var(--gold);font-family:monospace;letter-spacing:.15em">—</div>
            <button class="btn btn-outline btn-sm" onclick="copiarAdmin('au-pass')">📋</button>
          </div>
        </div>
      </div>
      <div class="alert alert-warn" style="font-size:12px">
        ⚠️ Entrega estas credenciales al usuario. Puede cambiar su contraseña después del primer acceso.
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-primary" style="width:100%;justify-content:center" onclick="closeModal('modal-cred-admin');location.reload()">✅ Entendido</button>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
