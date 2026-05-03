<?php
// ============================================================
// includes/contrato_shared.php
// Funciones compartidas para visualización de contratos
// Reduce duplicación entre asesor/contrato_view.php y legal/contrato_view.php
// ============================================================

/**
 * Obtiene los datos completos de un contrato por ID
 * Verifica que el usuario tenga permiso de acceso
 */
function getContratoData(PDO $pdo, int $contratoId, int $userId, string $rol): array|false {
    $stmt = $pdo->prepare("
        SELECT ct.*, v.monto, v.modalidad, v.fecha_venta,
               uc.nombre cliente_nombre, uc.ci cliente_ci, uc.email cliente_email, uc.telefono cliente_tel,
               l.codigo lote_codigo, l.nombre lote_nombre, l.direccion lote_dir,
               l.superficie, l.precio, l.tipo lote_tipo,
               z.nombre zona_nombre,
               ua.nombre asesor_nombre, ua.email asesor_email, ua.telefono asesor_tel,
               ug.nombre generado_nombre
        FROM contratos ct
        JOIN ventas v       ON v.id  = ct.venta_id
        JOIN clientes c     ON c.id  = v.cliente_id
        JOIN usuarios uc    ON uc.id = c.usuario_id
        JOIN lotes l        ON l.id  = v.lote_id
        JOIN zonas z        ON z.id  = l.zona_id
        LEFT JOIN usuarios ua ON ua.id = v.asesor_id
        LEFT JOIN usuarios ug ON ug.id = ct.generado_por
        WHERE ct.id = ?
    ");
    $stmt->execute([$contratoId]);
    $contrato = $stmt->fetch();
    if ($contrato === false) return false;
    
    // Verificar permiso: asesor solo ve sus contratos, legal y admin ven todos
    $allowedRoles = ['legal', 'admin'];
    if (!in_array($rol, $allowedRoles)) {
        // Para asesor: verificar que la venta es suya
        $stmtCheck = $pdo->prepare("SELECT id FROM ventas WHERE id = ? AND asesor_id = ?");
        $stmtCheck->execute([$contrato['venta_id'], $userId]);
        if ($stmtCheck->fetch() === false) return false;
    }
    return $contrato;
}

/**
 * Renderiza el CSS del contrato (compartido entre vistas)
 */
function contratoCSS(): void {
    echo '<style>
:root{--navy:#0A2647;--gold:#D4AF37;--text:#1a202c}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:"Times New Roman",serif;background:#f5f5f5;padding:20px}
.contrato{background:#fff;max-width:850px;margin:0 auto;padding:60px;border:1px solid #ddd;box-shadow:0 2px 12px rgba(0,0,0,.1)}
.header-doc{text-align:center;border-bottom:3px double var(--navy);padding-bottom:20px;margin-bottom:30px}
.header-doc h1{color:var(--navy);font-size:20px;letter-spacing:2px;margin-bottom:4px}
.header-doc h2{color:var(--navy);font-size:15px;font-weight:normal}
.doc-number{font-size:13px;color:#666;margin-top:8px}
.seccion{margin-bottom:28px}
.seccion h3{color:var(--navy);font-size:14px;border-bottom:1px solid #ccc;padding-bottom:4px;margin-bottom:12px;text-transform:uppercase;letter-spacing:1px}
.datos-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px 20px;margin-bottom:12px}
.dato{font-size:13px;line-height:1.6}
.dato strong{color:var(--navy)}
.clausula{margin-bottom:16px;font-size:13px;line-height:1.7;text-align:justify}
.clausula strong{color:var(--navy)}
.firmas{display:grid;grid-template-columns:1fr 1fr;gap:40px;margin-top:50px}
.firma-bloque{text-align:center}
.firma-linea{border-top:1px solid #333;padding-top:8px;font-size:12px}
.footer-doc{text-align:center;font-size:11px;color:#999;border-top:1px solid #eee;margin-top:30px;padding-top:14px}
.estado-badge{display:inline-block;padding:4px 16px;border-radius:20px;font-size:11px;font-weight:700;margin-top:6px}
.badge-firmado{background:#d1fae5;color:#065f46}
.badge-generado{background:#dbeafe;color:#1d4ed8}
.badge-borrador{background:#f3f4f6;color:#374151}
.no-print{margin:0 auto 20px;max-width:850px;display:flex;gap:10px;justify-content:flex-end}
@media print{.no-print{display:none}body{background:white;padding:0}.contrato{box-shadow:none;border:none;padding:40px}}
</style>';
}

/**
 * Botones de acción (compartidos, sin javascript: protocol)
 */
function contratoAcciones(array $contrato, string $rol): void {
    echo '<div class="no-print">';
    echo '<button type="button" onclick="window.history.back()" style="padding:8px 18px;border:1px solid #ccc;border-radius:6px;cursor:pointer;background:#fff">← Volver</button>';
    echo '<button type="button" onclick="window.print()" style="padding:8px 18px;background:#0A2647;color:#fff;border:none;border-radius:6px;cursor:pointer">🖨 Imprimir / PDF</button>';
    echo '</div>';
}
