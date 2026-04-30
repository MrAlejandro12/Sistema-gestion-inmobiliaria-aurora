<?php
require_once __DIR__ . '/../config/db.php';
secureSessionStart();
requireAuth(['cliente']);
$pdo = getDB();
$uid = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT cc.*, l.codigo lote_codigo, l.nombre lote_nombre, u.nombre asesor_nombre, u.telefono asesor_tel
    FROM consultas_cliente cc JOIN clientes c ON c.id=cc.cliente_id
    LEFT JOIN lotes l ON l.id=cc.lote_id
    LEFT JOIN usuarios u ON u.id=cc.respondido_por
    WHERE c.usuario_id=? ORDER BY cc.created_at DESC LIMIT 50");
$stmt->execute([$uid]);
header('Content-Type: application/json');
echo json_encode($stmt->fetchAll());
