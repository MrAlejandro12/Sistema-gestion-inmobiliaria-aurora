<?php
require_once __DIR__ . '/includes/security.php';
// ============================================================
// api_realtime.php — Polling API para actualizaciones en tiempo real
// Usado por: bitácora admin, consultas asesor, notificaciones
// ============================================================
require_once __DIR__ . '/config/db.php';
secureSessionStart();
if (!isset($_SESSION['user_id'])) { jsonResponse(['error' => 'No auth'], 401); }

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store');

$pdo    = getDB();
$rol    = $_SESSION['rol'];
$uid    = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';
$since  = $_GET['since'] ?? date('Y-m-d H:i:s', time() - 3600); // último 1h por defecto

// Validar formato de fecha
if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $since)) {
    $since = date('Y-m-d H:i:s', time() - 3600);
}

switch ($action) {

    // ── Bitácora completa (solo admin) ────────────────────────
    case 'bitacora':
        if ($rol !== 'admin') { jsonResponse(['error' => 'Sin permiso'], 403); }
        $limit = min((int)($_GET['limit'] ?? 50), 200);
        $stmt = $pdo->prepare("
            SELECT a.*, u.nombre as user_nombre, u.rol as user_rol
            FROM auditoria a
            LEFT JOIN usuarios u ON u.id = a.usuario_id
            WHERE a.created_at > ?
            ORDER BY a.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$since, $limit]);
        $rows = $stmt->fetchAll();
        jsonResponse([
            'ok' => true,
            'data' => $rows,
            'server_time' => date('Y-m-d H:i:s'),
            'count' => count($rows)
        ]);

    // ── Consultas del cliente → asesor ────────────────────────
    case 'consultas_nuevas':
        if (!in_array($rol, ['asesor','admin'])) { jsonResponse(['error' => 'Sin permiso'], 403); }
        $stmt = $pdo->prepare("
            SELECT cc.*, u.nombre cliente_nombre, u.email cliente_email,
                   l.codigo lote_codigo, l.nombre lote_nombre
            FROM consultas_cliente cc
            JOIN clientes c ON c.id = cc.cliente_id
            JOIN usuarios u ON u.id = c.usuario_id
            LEFT JOIN lotes l ON l.id = cc.lote_id
            WHERE cc.created_at > ?
              AND (cc.asesor_id = ? OR ? = 'admin')
              AND cc.estado = 'nueva'
            ORDER BY cc.created_at DESC
        ");
        $stmt->execute([$since, $uid, $rol]);
        jsonResponse(['ok' => true, 'data' => $stmt->fetchAll(), 'server_time' => date('Y-m-d H:i:s')]);

    // ── Todas las consultas para el asesor ────────────────────
    case 'mis_consultas':
        if (!in_array($rol, ['asesor','admin'])) { jsonResponse(['error' => 'Sin permiso'], 403); }
        $stmt = $pdo->prepare("
            SELECT cc.*, u.nombre cliente_nombre, u.email cliente_email, u.telefono cliente_tel,
                   l.codigo lote_codigo, l.nombre lote_nombre
            FROM consultas_cliente cc
            JOIN clientes c ON c.id = cc.cliente_id
            JOIN usuarios u ON u.id = c.usuario_id
            LEFT JOIN lotes l ON l.id = cc.lote_id
            WHERE (cc.asesor_id = ? OR ? = 'admin')
            ORDER BY cc.estado ASC, cc.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$uid, $rol]);
        jsonResponse(['ok' => true, 'data' => $stmt->fetchAll(), 'server_time' => date('Y-m-d H:i:s')]);

    // ── Responder una consulta (asesor) ───────────────────────
    case 'responder_consulta':
        if (!in_array($rol, ['asesor','admin'])) { jsonResponse(['error' => 'Sin permiso'], 403); }
        $id = (int)($_POST['id'] ?? 0);
        $resp = trim($_POST['respuesta'] ?? '');
        if (!$id || !$resp) { jsonResponse(['ok' => false, 'message' => 'Datos incompletos']); }
        $pdo->prepare("UPDATE consultas_cliente SET estado='respondida', respuesta=?, respondido_por=?, respondido_at=NOW() WHERE id=?")
            ->execute([$resp, $uid, $id]);
        auditLog("Respondió consulta #$id", 'consultas_cliente', $id);
        jsonResponse(['ok' => true, 'message' => 'Respuesta enviada al cliente']);

    // ── Notificaciones del cliente (sus respuestas) ───────────
    case 'mis_respuestas':
        if ($rol !== 'cliente') { jsonResponse(['error' => 'Sin permiso'], 403); }
        $stmt = $pdo->prepare("
            SELECT cc.*, u.nombre asesor_nombre, l.codigo lote_codigo
            FROM consultas_cliente cc
            JOIN clientes c ON c.id = cc.cliente_id
            LEFT JOIN usuarios u ON u.id = cc.respondido_por
            LEFT JOIN lotes l ON l.id = cc.lote_id
            WHERE c.usuario_id = ? AND cc.estado = 'respondida' AND cc.created_at > ?
            ORDER BY cc.respondido_at DESC
        ");
        $stmt->execute([$uid, $since]);
        jsonResponse(['ok' => true, 'data' => $stmt->fetchAll()]);

    // ── Contar notificaciones sin leer ────────────────────────
    case 'badge_count':
        $count = 0;
        if (in_array($rol, ['asesor','admin'])) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM consultas_cliente cc
                JOIN clientes c ON c.id=cc.cliente_id
                WHERE (cc.asesor_id=? OR ?='admin') AND cc.estado='nueva'");
            $stmt->execute([$uid, $rol]);
            $count = (int)$stmt->fetchColumn();
        } elseif ($rol === 'cliente') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM consultas_cliente cc
                JOIN clientes c ON c.id=cc.cliente_id WHERE c.usuario_id=? AND cc.estado='respondida' AND cc.respondido_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            $stmt->execute([$uid]);
            $count = (int)$stmt->fetchColumn();
        }
        jsonResponse(['ok' => true, 'count' => $count, 'server_time' => date('Y-m-d H:i:s')]);

    default:
        jsonResponse(['error' => 'Acción no válida'], 400);
}
