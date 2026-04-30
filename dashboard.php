<?php
// dashboard.php — Redirige al panel del rol correspondiente
require_once __DIR__ . '/config/db.php';
secureSessionStart();
requireAuth();

$rol = $_SESSION['rol'];
$destinos = [
  'cliente' => '/sgi_aurora/cliente/dashboard.php',
  'asesor'  => '/sgi_aurora/asesor/dashboard.php',
  'legal'   => '/sgi_aurora/legal/dashboard.php',
  'admin'   => '/sgi_aurora/admin/dashboard.php',
];
header('Location: ' . ($destinos[$rol] ?? '/sgi_aurora/index.php'));
exit;
