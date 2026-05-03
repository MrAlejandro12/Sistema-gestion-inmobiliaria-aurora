<?php
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
secureSessionStart();

if (isset($_SESSION['user_id'])) {
    header('Location: /sgi_aurora/dashboard.php'); exit;
}

$error = '';
$blocked_until = null;
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// ── Rate limiting: bloqueo por IP tras 5 intentos fallidos ───
function checkBrute(PDO $pdo, string $ip): ?string {
    $stmt = $pdo->prepare("SELECT COUNT(*) as c, MAX(created_at) as last FROM auditoria
        WHERE accion LIKE 'LOGIN_FAIL%' AND ip = ? AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $stmt->execute([$ip]);
    $r = $stmt->fetch();
    if ($r['c'] >= 5) {
        $unlockAt = date('H:i', strtotime($r['last']) + 15*60);
        return "Demasiados intentos fallidos. Bloqueado hasta las $unlockAt. Inténtalo más tarde.";
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar token CSRF antes de procesar
    if (!csrf_verify()) {
        $error = 'Solicitud no válida. Por favor recarga la página e intenta de nuevo.';
    } else {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if (empty($email) || empty($pass)) {
        $error = 'Ingresa tu correo y contraseña.';
    } else {
        $pdo = getDB();

        // Check brute force
        $bruteMsg = checkBrute($pdo, $ip);
        if ($bruteMsg) {
            $error = $bruteMsg;
        } else {
            $stmt = $pdo->prepare("SELECT id, nombre, email, password, rol, estado FROM usuarios WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($pass, $user['password'])) {
                $error = 'Credenciales incorrectas. Verifica tu correo y contraseña.';
                auditLog("LOGIN_FAIL: intento fallido — email: $email", 'usuarios', 0);
            } elseif ($user['estado'] !== 'activo') {
                $error = 'Tu cuenta está desactivada. Contacta al administrador.';
                auditLog("LOGIN_BLOCKED: cuenta inactiva — $email", 'usuarios', $user['id']);
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['nombre']  = $user['nombre'];
                $_SESSION['email']   = $user['email'];
                $_SESSION['rol']     = $user['rol'];
                $pdo->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?")->execute([$user['id']]);
                auditLog("LOGIN_OK: sesión iniciada — rol: {$user['rol']}", 'usuarios', $user['id']);
                header('Location: /sgi_aurora/dashboard.php'); exit;
            }
        }
    }
    } // end csrf_verify else
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SGI Aurora — Iniciar Sesión</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--navy:#0A2647;--navy-l:#1a3a6b;--navy-d:#061830;--gold:#D4AF37;--gold-l:#e8c84a;--green:#27AE60;--red:#E74C3C}
body{font-family:'Poppins',sans-serif;background:linear-gradient(135deg,var(--navy-d),var(--navy) 50%,var(--navy-l));min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;position:relative;overflow:hidden}
body::before{content:'';position:absolute;inset:0;background:radial-gradient(circle at 30% 50%,rgba(212,175,55,.15),transparent 60%),radial-gradient(circle at 80% 80%,rgba(39,174,96,.1),transparent 50%)}
.card{background:rgba(255,255,255,.06);backdrop-filter:blur(20px);border:1px solid rgba(212,175,55,.3);border-radius:24px;padding:48px 40px;width:440px;position:relative;z-index:1;box-shadow:0 24px 64px rgba(0,0,0,.4)}
.logo{text-align:center;margin-bottom:28px}
.logo-icon{width:64px;height:64px;background:linear-gradient(135deg,var(--gold),var(--gold-l));border-radius:16px;margin:0 auto 14px;display:flex;align-items:center;justify-content:center;font-size:28px;box-shadow:0 8px 24px rgba(212,175,55,.35)}
.logo h1{color:#fff;font-size:24px;font-weight:700}
.logo p{color:rgba(255,255,255,.55);font-size:13px;margin-top:3px}
.security-badge{display:flex;align-items:center;gap:6px;background:rgba(39,174,96,.12);border:1px solid rgba(39,174,96,.3);border-radius:8px;padding:7px 12px;margin-bottom:20px;font-size:11px;color:#6ee7b7}
.field{margin-bottom:18px}
.field label{display:block;color:rgba(255,255,255,.8);font-size:13px;font-weight:500;margin-bottom:7px}
.input-wrap{position:relative}
.field input{width:100%;padding:12px 16px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);border-radius:10px;color:#fff;font-size:14px;font-family:'Poppins',sans-serif;transition:border-color .2s,box-shadow .2s;outline:none}
.field input:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(212,175,55,.15)}
.field input::placeholder{color:rgba(255,255,255,.3)}
.toggle-pw{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;color:rgba(255,255,255,.4);cursor:pointer;font-size:16px;padding:2px}
.toggle-pw:hover{color:rgba(255,255,255,.8)}
.error-msg{background:rgba(231,76,60,.15);border:1px solid rgba(231,76,60,.4);border-radius:10px;padding:12px 16px;color:#ff8a80;font-size:13px;margin-bottom:16px;display:flex;align-items:flex-start;gap:8px}
.btn-login{width:100%;padding:13px;background:linear-gradient(135deg,var(--gold),var(--gold-l));color:var(--navy-d);border:none;border-radius:10px;font-size:15px;font-weight:600;cursor:pointer;font-family:'Poppins',sans-serif;transition:all .2s;position:relative;overflow:hidden}
.btn-login:hover{transform:translateY(-1px);box-shadow:0 8px 24px rgba(212,175,55,.4)}
.btn-login:active{transform:none}
/* Quick access */
.quick{margin-top:24px;border-top:1px solid rgba(255,255,255,.08);padding-top:20px}
.quick-label{color:rgba(255,255,255,.4);font-size:11px;text-align:center;margin-bottom:12px;letter-spacing:.04em}
.quick-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.qbtn{padding:10px 8px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:9px;color:rgba(255,255,255,.65);font-size:12px;cursor:pointer;font-family:'Poppins',sans-serif;transition:all .18s;text-align:center;line-height:1.4}
.qbtn:hover{background:rgba(212,175,55,.12);border-color:rgba(212,175,55,.4);color:var(--gold)}
.qbtn span{display:block;font-size:10px;color:rgba(255,255,255,.35);margin-top:2px}
/* Hint box */
.hint-box{background:rgba(212,175,55,.08);border:1px solid rgba(212,175,55,.25);border-radius:10px;padding:12px 16px;margin-top:14px;font-size:11.5px;color:rgba(255,255,255,.6);line-height:1.6}
.hint-box strong{color:var(--gold)}
.version{position:fixed;bottom:14px;left:0;right:0;text-align:center;font-size:10px;color:rgba(255,255,255,.2)}
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <div class="logo-icon">🏠</div>
    <h1>SGI Aurora</h1>
    <p>Sistema de Gestión Inmobiliaria</p>
  </div>

  <div class="security-badge">
    🔒 <span>Conexión segura · Sesión cifrada · Ley 164</span>
  </div>

  <?php if ($error): ?>
    <div class="error-msg">⚠️ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" action="" autocomplete="off">
    <?= csrf_field() ?>
    <div class="field">
      <label>Correo electrónico</label>
      <input type="email" name="email" id="email"
             placeholder="correo@aurora.com"
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
             autocomplete="username"
             required autofocus>
    </div>
    <div class="field">
      <label>Contraseña</label>
      <div class="input-wrap">
        <input type="password" name="password" id="password"
               placeholder="Ingresa tu contraseña"
               autocomplete="current-password"
               required>
        <button type="button" class="toggle-pw" onclick="togglePw()" id="pwToggle" title="Mostrar/ocultar">👁</button>
      </div>
    </div>
    <button type="submit" class="btn-login">🔐 Iniciar sesión</button>
  </form>

  <div class="quick">
    <div class="quick-label">ACCESO RÁPIDO — selecciona tu rol e ingresa la contraseña manualmente</div>
    <div class="quick-grid">
      <button type="button" class="qbtn" onclick="fillEmail('cliente@aurora.com','👤 Cliente')">
        👤 Cliente<span>cliente@aurora.com</span>
      </button>
      <button type="button" class="qbtn" onclick="fillEmail('asesor@aurora.com','🤝 Asesor')">
        🤝 Asesor<span>asesor@aurora.com</span>
      </button>
      <button type="button" class="qbtn" onclick="fillEmail('legal@aurora.com','⚖️ Legal')">
        ⚖️ Legal<span>legal@aurora.com</span>
      </button>
      <button type="button" class="qbtn" onclick="fillEmail('admin@aurora.com','⚙️ Admin')">
        ⚙️ Admin<span>admin@aurora.com</span>
      </button>
    </div>
    <div class="hint-box">
      El campo de contraseña <strong>no se autocompleta</strong> por seguridad — ingresa tu contraseña manualmente.
    </div>
  </div>
</div>
<div class="version">SGI Aurora v2.0 · Bienes Raíces Aurora · Santa Cruz de la Sierra, Bolivia · Ley 164</div>

<script>
// Solo rellena el email, la contraseña siempre se deja vacía
function fillEmail(email, label) {
  const emailField = document.getElementById('email');
  const pwField = document.getElementById('password');
  emailField.value = email;
  pwField.value = '';      // NUNCA autocompleta la contraseña
  pwField.focus();
  pwField.placeholder = 'Ingresa tu contraseña para ' + label;
}

function togglePw() {
  const pw = document.getElementById('password');
  const btn = document.getElementById('pwToggle');
  if (pw.type === 'password') {
    pw.type = 'text'; btn.textContent = '🙈';
  } else {
    pw.type = 'password'; btn.textContent = '👁';
  }
}
</script>
</body>
</html>
