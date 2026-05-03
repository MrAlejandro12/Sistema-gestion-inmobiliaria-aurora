<?php
// ============================================================
// config/db.php — Conexión a MySQL (PDO) v2.0
// Bienes Raíces Aurora — SGI-Aurora
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'sgi_aurora');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Default temp password for new users - change in production
define('DEFAULT_TEMP_PASSWORD', 'aurora123');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['error' => 'Error de conexión: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// ── Registrar en bitácora ──────────────────────────────────────
/**
 * Determina el nivel de auditoría según el contenido de la acción
 */
function resolveAuditLevel(string $accion): string {
    $a = strtoupper($accion);
    if (str_contains($a, 'CRITICO') || str_contains($a, 'BREACH')) return 'critico';
    if (str_contains($a, 'FAIL') || str_contains($a, 'ERROR') || str_contains($a, 'BLOCK')) return 'error';
    if (str_contains($a, 'WARN') || str_contains($a, 'RECHAZ') || str_contains($a, 'DENIEGA')) return 'warning';
    return 'info';
}

/**
 * Inserta un registro en la bitácora de auditoría (solo INSERT — Ley 164/393)
 * El nivel se detecta automáticamente si no se especifica.
 */
function auditLog(string $accion, string $tabla = '', int $registroId = 0): void {
    try {
        $pdo    = getDB();
        $userId = $_SESSION['user_id'] ?? null;
        $email  = $_SESSION['email']   ?? 'sistema';
        $ip     = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ua     = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $nivel  = resolveAuditLevel($accion);
        $regId  = $registroId ?: null;

        // v2.0: columna nivel incluida
        $stmt = $pdo->prepare(
            "INSERT INTO auditoria (usuario_id, email, accion, tabla_afectada, registro_id, ip, user_agent, nivel)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $email, $accion, $tabla, $regId, $ip, $ua, $nivel]);
    } catch (\Exception $e) {
        // Silenciado intencionalmente: el log no debe interrumpir el flujo
        error_log('[SGI-Aurora] auditLog error: ' . $e->getMessage());
    }
}

// ── Respuesta JSON ─────────────────────────────────────────────
function jsonResponse(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ── Verificar sesión y rol ─────────────────────────────────────
function requireAuth(array $roles = []): array {
    if (!isset($_SESSION['user_id'])) {
        if (isApiRequest()) jsonResponse(['error' => 'No autenticado'], 401);
        header('Location: /sgi_aurora/index.php'); exit;
    }
    if (!empty($roles) && !in_array($_SESSION['rol'], $roles)) {
        if (isApiRequest()) jsonResponse(['error' => 'Sin permisos'], 403);
        auditLog("ACCESO_DENEGADO: intento acceso sin rol requerido (".implode('|',$roles).") — rol actual: ".$_SESSION['rol']);
        header('Location: /sgi_aurora/index.php?error=sin_permisos'); exit;
    }
    return [
        'id'     => $_SESSION['user_id'],
        'nombre' => $_SESSION['nombre'],
        'email'  => $_SESSION['email'],
        'rol'    => $_SESSION['rol'],
    ];
}

// ── Iniciar sesión de forma segura ────────────────────────────
function secureSessionStart(): void {
    if (session_status() !== PHP_SESSION_NONE) return;
    // Cookie flags seguros (resuelve: Cookie HttpOnly, SameSite, Secure)
    session_set_cookie_params([
        'lifetime' => 0,              // Sesión expira al cerrar el navegador
        'path'     => '/',
        'domain'   => '',
        'secure'   => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'), // auto-detect HTTPS (S2092)
        'httponly' => true,           // No accesible desde JavaScript (XSS mitigation)
        'samesite' => 'Strict',       // Anti-CSRF: no envía cookie en requests cross-site
    ]);
    session_start();
    // Regenerar ID de sesión periódicamente (anti session fixation)
    if (!isset($_SESSION['_created'])) {
        $_SESSION['_created'] = time();
    } elseif (time() - $_SESSION['_created'] > 1800) { // cada 30 min
        session_regenerate_id(true);
        $_SESSION['_created'] = time();
    }
}

function isApiRequest(): bool {
    return (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
        || (isset($_GET['format']) && $_GET['format'] === 'json');
}
