<?php
// ============================================================
// includes/security.php — Cabeceras y tokens de seguridad
// Resuelve: CSRF, CSP, X-Frame-Options, HSTS, Cookie flags,
//           X-Content-Type-Options, X-Powered-By, Server leak
// Incluir al INICIO de cada PHP, antes de cualquier output
// ============================================================

// ── 1. Ocultar información de versión del servidor ───────────
header_remove('X-Powered-By');   // Quita PHP/8.x.x
header_remove('Server');         // Quita Apache/nginx versión
header('X-Powered-By: SGI-Aurora');  // Reemplaza con algo neutro

// ── 2. Content Security Policy (CSP) ─────────────────────────
// Solo permite recursos de fuentes confiables — bloquea XSS inline
header("Content-Security-Policy: " .
    "default-src 'self'; " .
    "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://fonts.googleapis.com; " .
    // 'unsafe-inline' necesario para los <script> existentes
    // En producción eliminar 'unsafe-inline' y usar nonces
    "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.gstatic.com; " .
    "font-src 'self' https://fonts.gstatic.com data:; " .
    "img-src 'self' data: https:; " .
    "connect-src 'self'; " .
    "frame-ancestors 'none'; " .   // Anti-Clickjacking
    "form-action 'self'; " .        // Forms solo a mismo dominio
    "base-uri 'self';"
);

// ── 3. Anti-Clickjacking ──────────────────────────────────────
header('X-Frame-Options: DENY');

// ── 4. X-Content-Type-Options ────────────────────────────────
header('X-Content-Type-Options: nosniff');

// ── 5. Referrer Policy ───────────────────────────────────────
header('Referrer-Policy: strict-origin-when-cross-origin');

// ── 6. Permissions Policy ────────────────────────────────────
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

// ── 7. HSTS (solo producción — quitar en localhost) ──────────
// header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

// ── 8. Configuración segura de sesión PHP ────────────────────
if (session_status() === PHP_SESSION_NONE) {
    // Flags de cookie seguros
    ini_set('session.cookie_httponly', 1);      // No accesible desde JS
    ini_set('session.cookie_samesite', 'Strict'); // Anti-CSRF
    ini_set('session.use_strict_mode', 1);      // Rechaza IDs de sesión no generados por el servidor
    ini_set('session.use_only_cookies', 1);     // No pasar session ID en URL
    // En producción con HTTPS descomentar:
    // ini_set('session.cookie_secure', 1);
}

// ── 9. Token CSRF ─────────────────────────────────────────────
// Llamar csrf_token() para obtener/generar el token
// Usar csrf_verify() para validar en POST
// Usar csrf_field() para insertar el campo oculto en formularios

function csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function csrf_verify(): bool {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $token = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $stored = $_SESSION['_csrf_token'] ?? '';
    if (!$stored || !$token) return false;
    $valid = hash_equals($stored, $token);
    if ($valid) {
        // Rotar token tras uso exitoso
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $valid;
}

// ── 10. Helpers de output seguro ─────────────────────────────
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function h_attr(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
}
