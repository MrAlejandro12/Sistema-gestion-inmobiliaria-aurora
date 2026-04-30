<?php
require_once __DIR__ . '/../config/db.php';
secureSessionStart();
requireAuth(['asesor', 'admin']);
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $nombre   = trim($_POST['nombre'] ?? '');
        $email    = trim($_POST['email']  ?? '');
        $tel      = trim($_POST['telefono'] ?? '');
        $ci       = trim($_POST['ci'] ?? '');
        $zona_id  = (int)($_POST['zona_id'] ?? 1);
        $tipo     = $_POST['tipo'] ?? 'Residencial';
        $presMin  = (float)($_POST['presupuesto_min'] ?? 0);
        $presMax  = (float)($_POST['presupuesto_max'] ?? 0);
        $consent  = (int)($_POST['consent'] ?? 0);
        $svcs     = implode(',', $_POST['servicios'] ?? []);

        if (!$nombre || !$ci)   { echo json_encode(['ok'=>false,'message'=>'Nombre y CI son obligatorios']); exit; }
        if (!$email)            { echo json_encode(['ok'=>false,'message'=>'El correo electrónico es obligatorio — el cliente lo usará para iniciar sesión']); exit; }
        if (!$consent)          { echo json_encode(['ok'=>false,'message'=>'Debe confirmar la Ley 164 (consentimiento obligatorio)']); exit; }

        // Validar formato de email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['ok'=>false,'message'=>'El formato del correo electrónico no es válido']); exit;
        }

        // Verificar que el email no esté ya registrado
        $exists = $pdo->prepare("SELECT id FROM usuarios WHERE email=?");
        $exists->execute([$email]);
        if ($exists->fetch()) {
            echo json_encode(['ok'=>false,'message'=>"El correo $email ya está registrado en el sistema"]); exit;
        }

        // Contraseña por defecto: aurora123
        $passDefault = 'aurora123';
        $passHash    = password_hash($passDefault, PASSWORD_BCRYPT);

        $pdo->prepare("INSERT INTO usuarios (nombre,email,password,rol,telefono,ci) VALUES (?,?,?,'cliente',?,?)")
            ->execute([$nombre, $email, $passHash, $tel, $ci]);
        $uid = $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO clientes (usuario_id,zona_preferida,tipo_lote,presupuesto_min,presupuesto_max,servicios_req,asesor_id,consentimiento_ley164) VALUES (?,?,?,?,?,?,?,1)")
            ->execute([$uid, $zona_id, $tipo, $presMin, $presMax, $svcs, $_SESSION['user_id']]);
        auditLog("Registró cliente: $nombre — $email (Ley 164 aceptada)", 'clientes', $uid);

        echo json_encode([
            'ok'      => true,
            'message' => "✅ Cliente registrado correctamente",
            'credentials' => [
                'email'    => $email,
                'password' => $passDefault,
                'nombre'   => $nombre,
            ],
            'reload'  => false,   // No recargar todavía — mostrar credenciales primero
        ]);
        exit;
    }

    if ($action === 'update_crm') {
        $cliente_id = (int)($_POST['cliente_id'] ?? 0);
        $estado     = $_POST['estado_crm'] ?? 'prospecto';
        $pdo->prepare("UPDATE clientes SET estado_crm=? WHERE id=?")->execute([$estado,$cliente_id]);
        auditLog("Actualizó CRM cliente #$cliente_id a $estado", 'clientes', $cliente_id);
        echo json_encode(['ok'=>true,'message'=>"Estado CRM actualizado a: $estado"]); exit;
    }
}

$clientes = $pdo->query("
    SELECT c.*, u.nombre, u.email, u.telefono, u.ci, u.estado usuario_estado,
           z.nombre zona_nombre, ua.nombre asesor_nombre
    FROM clientes c
    JOIN usuarios u ON u.id=c.usuario_id
    LEFT JOIN zonas z ON z.id=c.zona_preferida
    LEFT JOIN usuarios ua ON ua.id=c.asesor_id
    ORDER BY c.created_at DESC
")->fetchAll();
$zonas = $pdo->query("SELECT * FROM zonas ORDER BY nombre")->fetchAll();

$pageTitle='Mis Clientes'; $pageSubtitle=count($clientes).' cliente(s) registrado(s)'; $activeNav='as-cli';
require_once __DIR__ . '/../includes/layout.php';
?>

<div class="alert alert-info" style="margin-bottom:20px">
  ⚖️ <strong>Ley 164:</strong> Los datos del cliente son confidenciales. El consentimiento es obligatorio al registrar. Solo el asesor asignado y roles superiores pueden acceder al expediente completo.
</div>

<div style="display:flex;justify-content:flex-end;margin-bottom:16px">
  <button onclick="openModal('modal-cliente')" class="btn btn-primary">➕ Nuevo cliente</button>
</div>

<div class="card">
  <div class="card-body p0">
    <table class="table">
      <thead><tr><th>Cliente</th><th>CI</th><th>Teléfono</th><th>Zona</th><th>Presupuesto</th><th>Estado CRM</th><th>Acciones</th></tr></thead>
      <tbody>
        <?php foreach ($clientes as $c):
          $crmColors=['prospecto'=>'badge-gray','contactado'=>'badge-blue','visita'=>'badge-gold','interesado'=>'badge-orange','propuesta'=>'badge-gold','cerrado'=>'badge-green','descartado'=>'badge-red'];
          $initials=strtoupper(substr($c['nombre'],0,2));
        ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div class="avatar" style="width:32px;height:32px;font-size:11px;background:linear-gradient(135deg,var(--navy),var(--navy-l))"><?= $initials ?></div>
              <div><div style="font-weight:500;font-size:13px"><?= htmlspecialchars($c['nombre']) ?></div>
                   <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($c['email']) ?></div></div>
            </div>
          </td>
          <td style="font-size:12px"><?= htmlspecialchars($c['ci'] ?? '—') ?></td>
          <td style="font-size:12px"><?= htmlspecialchars($c['telefono'] ?? '—') ?></td>
          <td><?= htmlspecialchars($c['zona_nombre'] ?? '—') ?></td>
          <td style="font-size:12px">$<?= number_format($c['presupuesto_min'],0) ?> – $<?= number_format($c['presupuesto_max'],0) ?></td>
          <td>
            <select class="badge <?= $crmColors[$c['estado_crm']] ?? 'badge-gray' ?>" onchange="updateCRM(<?= $c['id'] ?>, this.value)"
              style="border:none;font-size:11px;font-weight:600;cursor:pointer;background:transparent;padding:4px 8px">
              <?php foreach(['prospecto','contactado','visita','interesado','propuesta','cerrado','descartado'] as $est): ?>
                <option value="<?= $est ?>" <?= $c['estado_crm']===$est?'selected':'' ?>><?= $est ?></option>
              <?php endforeach; ?>
            </select>
          </td>
          <td><button class="btn btn-outline btn-sm" onclick="showToast('Ver ficha de <?= addslashes($c['nombre']) ?>','')">Ver</button></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($clientes)): ?><tr><td colspan="7" style="text-align:center;padding:24px;color:var(--muted)">Sin clientes registrados</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-bg" id="modal-cliente">
  <div class="modal">
    <div class="modal-header"><h3>👤 Registrar nuevo cliente</h3><button class="modal-close" onclick="closeModal('modal-cliente')">×</button></div>
    <form id="form-cliente" method="POST" action="/sgi_aurora/asesor/clientes.php">
    <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <div class="modal-body">
        <div class="form-row">
          <div class="field-group"><label>Nombre completo *</label><input type="text" name="nombre" placeholder="Ana Rodríguez Vaca" required></div>
          <div class="field-group"><label>Correo electrónico * <span style="color:var(--red);font-size:11px">(el cliente usará este email para ingresar)</span></label><input type="email" name="email" placeholder="ana@email.com" required></div>
        </div>
        <div class="form-row">
          <div class="field-group"><label>Teléfono</label><input type="text" name="telefono" placeholder="+591 7X XXXXXX"></div>
          <div class="field-group"><label>Carnet de Identidad *</label><input type="text" name="ci" placeholder="7890123 SC" required></div>
        </div>
        <div class="form-row">
          <div class="field-group"><label>Zona de interés</label>
            <select name="zona_id"><?php foreach($zonas as $z): ?><option value="<?= $z['id'] ?>"><?= $z['nombre'] ?></option><?php endforeach; ?></select></div>
          <div class="field-group"><label>Tipo de lote</label>
            <select name="tipo"><option>Residencial</option><option>Comercial</option><option>Industrial</option></select></div>
        </div>
        <div class="form-row">
          <div class="field-group"><label>Presupuesto mínimo ($)</label><input type="number" name="presupuesto_min" placeholder="15000"></div>
          <div class="field-group"><label>Presupuesto máximo ($)</label><input type="number" name="presupuesto_max" placeholder="60000"></div>
        </div>
        <div class="field-group" style="margin-bottom:16px"><label>Servicios requeridos</label>
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:6px">
            <?php foreach(['Agua','Luz','Gas','Drenaje','Internet','Seguridad'] as $s): ?>
              <label style="display:flex;align-items:center;gap:8px;padding:8px;border-radius:8px;background:var(--bg);border:1px solid var(--border);font-size:13px;cursor:pointer">
                <input type="checkbox" name="servicios[]" value="<?= $s ?>"> <?= $s ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
        <label style="display:flex;align-items:flex-start;gap:8px;font-size:13px;cursor:pointer">
          <input type="checkbox" name="consent" value="1" required style="margin-top:2px">
          <span>✅ He informado al cliente sobre la <strong>Ley 164 de protección de datos</strong> de Bolivia y acepta el tratamiento de su información personal (obligatorio).</span>
        </label>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-cliente')">Cancelar</button>
        <button type="submit" class="btn btn-primary" data-label="Registrar cliente">💾 Registrar cliente</button>
      </div>
    </form>
  </div>
</div>

<script>
submitForm('form-cliente', '');

// Override: capturar respuesta para mostrar credenciales
document.getElementById('form-cliente').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = this.querySelector('[type=submit]');
    if (btn) { btn.disabled = true; btn.textContent = 'Registrando...'; }
    try {
        const res  = await fetch(this.action || window.location.href, { method: 'POST', body: new FormData(this) });
        const data = await res.json();
        if (data.ok && data.credentials) {
            closeModal('modal-cliente');
            // Mostrar modal de credenciales
            document.getElementById('cred-nombre').textContent  = data.credentials.nombre;
            document.getElementById('cred-email').textContent   = data.credentials.email;
            document.getElementById('cred-pass').textContent    = data.credentials.password;
            openModal('modal-credenciales');
        } else if (data.ok) {
            showToast('Cliente registrado correctamente', 'ok');
            closeModal('modal-cliente');
            setTimeout(() => location.reload(), 900);
        } else {
            showToast(data.message || 'Error al registrar', 'err');
        }
    } catch(err) {
        showToast('Error de conexión', 'err');
    } finally {
        if (btn) { btn.disabled = false; btn.textContent = btn.dataset.label || 'Registrar cliente'; }
    }
}, true); // 'true' para que este handler corra antes del de submitForm
function updateCRM(id, estado) {
  fetch('/sgi_aurora/asesor/clientes.php', {
    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'action=update_crm&cliente_id='+id+'&estado_crm='+estado
  }).then(r=>r.json()).then(d=>showToast(d.message, d.ok?'ok':'err'));
}
function copiar(elementId) {
  const txt = document.getElementById(elementId).textContent.trim();
  navigator.clipboard.writeText(txt).then(()=>showToast('Copiado al portapapeles','ok')).catch(()=>{
    const el=document.createElement('textarea'); el.value=txt; document.body.appendChild(el);
    el.select(); document.execCommand('copy'); document.body.removeChild(el);
    showToast('Copiado','ok');
  });
}
</script>

<!-- Modal credenciales del nuevo cliente -->
<div class="modal-bg" id="modal-credenciales">
  <div class="modal" style="max-width:460px">
    <div class="modal-header" style="background:var(--navy);border-radius:20px 20px 0 0">
      <h3 style="color:#fff">✅ Cliente registrado exitosamente</h3>
      <button class="modal-close" style="border-color:rgba(255,255,255,.3);color:#fff" onclick="closeModal('modal-credenciales');location.reload()">×</button>
    </div>
    <div class="modal-body">
      <div style="text-align:center;margin-bottom:20px">
        <div style="font-size:48px;margin-bottom:8px">🎉</div>
        <div style="font-size:15px;font-weight:600;color:var(--navy)" id="cred-nombre">—</div>
        <div style="font-size:13px;color:var(--muted)">ya puede iniciar sesión en el sistema</div>
      </div>
      <div style="background:#f8fafc;border-radius:12px;border:1px solid var(--border);padding:20px;margin-bottom:16px">
        <div style="font-size:12px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:14px">Credenciales de acceso</div>
        <div style="margin-bottom:14px">
          <div style="font-size:12px;color:var(--muted);margin-bottom:4px">📧 Correo electrónico</div>
          <div style="display:flex;align-items:center;gap:8px">
            <div id="cred-email" style="flex:1;font-size:14px;font-weight:600;color:var(--navy);background:#fff;padding:10px 14px;border-radius:8px;border:1px solid var(--border)">—</div>
            <button class="btn btn-outline btn-sm" onclick="copiar('cred-email')">📋 Copiar</button>
          </div>
        </div>
        <div>
          <div style="font-size:12px;color:var(--muted);margin-bottom:4px">🔑 Contraseña temporal</div>
          <div style="display:flex;align-items:center;gap:8px">
            <div id="cred-pass" style="flex:1;font-size:16px;font-weight:700;color:var(--navy);background:#fff;padding:10px 14px;border-radius:8px;border:2px solid var(--gold);font-family:monospace;letter-spacing:.15em">—</div>
            <button class="btn btn-outline btn-sm" onclick="copiar('cred-pass')">📋 Copiar</button>
          </div>
        </div>
      </div>
      <div class="alert alert-warn" style="font-size:12px">
        ⚠️ <strong>Importante:</strong> Comparte estas credenciales con el cliente para que pueda acceder al portal. La contraseña es la misma para todos los clientes nuevos: <strong>aurora123</strong>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-primary" style="width:100%;justify-content:center" onclick="closeModal('modal-credenciales');location.reload()">
        ✅ Entendido — Continuar
      </button>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
