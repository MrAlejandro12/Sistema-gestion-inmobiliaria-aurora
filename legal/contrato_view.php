<?php
// asesor/contrato_view.php  (también accesible desde legal/contrato_view.php)
require_once __DIR__ . '/../config/db.php';
secureSessionStart();
requireAuth(['asesor', 'legal', 'admin']);

$pdo = getDB();
$id  = (int)($_GET['id'] ?? 0);

// Traer contrato completo con todos los datos relacionados
$stmt = $pdo->prepare("
    SELECT
        ct.id            contrato_id,
        ct.estado        contrato_estado,
        ct.fecha_gen,
        ct.fecha_firma,
        ct.notario,
        -- Venta
        v.id             venta_id,
        v.monto,
        v.modalidad,
        v.fecha_venta,
        -- Cliente
        uc.nombre        cliente_nombre,
        uc.email         cliente_email,
        uc.telefono      cliente_tel,
        uc.ci            cliente_ci,
        -- Lote
        l.codigo         lote_codigo,
        l.nombre         lote_nombre,
        l.direccion      lote_direccion,
        l.superficie,
        l.precio         lote_precio,
        l.tipo           lote_tipo,
        l.servicios,
        -- Zona
        z.nombre         zona_nombre,
        -- Asesor
        ua.nombre        asesor_nombre,
        ua.email         asesor_email,
        ua.telefono      asesor_tel,
        ua.ci            asesor_ci
    FROM contratos ct
    JOIN ventas v    ON v.id  = ct.venta_id
    JOIN clientes c  ON c.id  = v.cliente_id
    JOIN usuarios uc ON uc.id = c.usuario_id
    JOIN lotes l     ON l.id  = v.lote_id
    JOIN zonas z     ON z.id  = l.zona_id
    JOIN usuarios ua ON ua.id = v.asesor_id
    WHERE ct.id = ?
");
$stmt->execute([$id]);
$ct = $stmt->fetch();

if (!$ct) {
    http_response_code(404);
    die('<div style="font-family:sans-serif;padding:40px;text-align:center"><h2>Contrato no encontrado</h2><a href="javascript:history.back()">← Volver</a></div>');
}

// Verificar acceso: asesor solo puede ver sus propios contratos
if ($_SESSION['rol'] === 'asesor') {
    $check = $pdo->prepare("SELECT v.asesor_id FROM ventas v JOIN contratos ct ON ct.venta_id=v.id WHERE ct.id=?");
    $check->execute([$id]);
    $row = $check->fetch();
    if (!$row || $row['asesor_id'] != $_SESSION['user_id']) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:40px;text-align:center"><h2>Sin permisos para ver este contrato</h2></div>');
    }
}

// Pagos de esta venta
$pagosStmt = $pdo->prepare("
    SELECT p.monto, p.metodo, p.comprobante, p.fecha_pago
    FROM pagos p WHERE p.venta_id = ? ORDER BY p.fecha_pago ASC
");
$pagosStmt->execute([$ct['venta_id']]);
$pagos = $pagosStmt->fetchAll();
$totalPagado = array_sum(array_column($pagos, 'monto'));
$saldo = $ct['monto'] - $totalPagado;

// Número de contrato formateado
$numContrato = 'CT-' . str_pad($ct['contrato_id'], 4, '0', STR_PAD_LEFT) . '-' . date('Y', strtotime($ct['fecha_gen']));

$pageTitle    = 'Contrato ' . $numContrato;
$pageSubtitle = 'Vista del contrato de compraventa';
$activeNav    = $_SESSION['rol'] === 'asesor' ? 'as-con' : 'lg-con';
require_once __DIR__ . '/../includes/layout.php';
?>

<style>
/* ── Barra de herramientas ── */
.ct-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 14px 20px;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 10px;
}
.ct-toolbar-left { display: flex; align-items: center; gap: 12px; }
.ct-toolbar-right { display: flex; gap: 10px; }

/* ── Documento contrato ── */
.contrato-doc {
    background: #fff;
    border: 1px solid #d1d5db;
    border-radius: 16px;
    max-width: 820px;
    margin: 0 auto;
    padding: 60px 70px;
    font-family: 'Georgia', 'Times New Roman', serif;
    font-size: 13.5px;
    line-height: 1.75;
    color: #1a1a2e;
    box-shadow: 0 4px 24px rgba(0,0,0,.07);
}

/* Membrete */
.ct-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    padding-bottom: 24px;
    border-bottom: 3px solid #0A2647;
    margin-bottom: 32px;
}
.ct-logo-block { display: flex; align-items: center; gap: 14px; }
.ct-logo-icon {
    width: 52px; height: 52px;
    background: linear-gradient(135deg, #0A2647, #1a3a6b);
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 24px;
}
.ct-empresa { font-family: 'Poppins', sans-serif; }
.ct-empresa-nombre { font-size: 17px; font-weight: 700; color: #0A2647; line-height: 1.2; }
.ct-empresa-sub { font-size: 11px; color: #6b7280; }
.ct-num-block { text-align: right; }
.ct-num { font-family: 'Poppins', sans-serif; font-size: 22px; font-weight: 700; color: #0A2647; }
.ct-fecha { font-size: 12px; color: #6b7280; margin-top: 2px; }

/* Estado badge grande */
.ct-estado-bar {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    background: #f0fdf4;
    border: 1.5px solid #86efac;
    border-radius: 10px;
    padding: 10px 20px;
    margin-bottom: 32px;
    font-family: 'Poppins', sans-serif;
    font-size: 13px;
    font-weight: 600;
    color: #166534;
}
.ct-estado-bar.borrador { background:#f8fafc; border-color:#cbd5e1; color:#475569; }
.ct-estado-bar.generado { background:#eff6ff; border-color:#93c5fd; color:#1d4ed8; }
.ct-estado-bar.cancelado { background:#fef2f2; border-color:#fca5a5; color:#991b1b; }

/* Título del contrato */
.ct-titulo {
    text-align: center;
    margin-bottom: 28px;
}
.ct-titulo h2 {
    font-family: 'Poppins', sans-serif;
    font-size: 20px;
    font-weight: 700;
    color: #0A2647;
    text-transform: uppercase;
    letter-spacing: .06em;
    margin-bottom: 4px;
}
.ct-titulo p { font-size: 12px; color: #6b7280; }

/* Secciones */
.ct-section { margin-bottom: 28px; }
.ct-section-title {
    font-family: 'Poppins', sans-serif;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .1em;
    color: #0A2647;
    background: #f1f5f9;
    padding: 6px 12px;
    border-radius: 6px;
    margin-bottom: 14px;
    border-left: 3px solid #0A2647;
}
.ct-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 24px; }
.ct-field { display: flex; gap: 6px; font-size: 13px; }
.ct-field-label { color: #6b7280; min-width: 130px; flex-shrink: 0; }
.ct-field-value { font-weight: 600; color: #111827; }

/* Cláusulas */
.ct-clausula { margin-bottom: 14px; }
.ct-clausula-num { font-family: 'Poppins', sans-serif; font-weight: 700; color: #0A2647; }

/* Tabla pagos */
.ct-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 10px; }
.ct-table th { background: #0A2647; color: #fff; padding: 8px 12px; text-align: left; font-family: 'Poppins', sans-serif; font-size: 11px; font-weight: 600; }
.ct-table td { padding: 8px 12px; border-bottom: 1px solid #e5e7eb; }
.ct-table tr:last-child td { border-bottom: none; }
.ct-table tfoot td { font-weight: 700; background: #f8fafc; }

/* Saldo */
.ct-saldo {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, #0A2647, #1a3a6b);
    border-radius: 10px;
    padding: 14px 20px;
    color: #fff;
    margin-top: 14px;
    font-family: 'Poppins', sans-serif;
}
.ct-saldo-label { font-size: 13px; opacity: .8; }
.ct-saldo-monto { font-size: 22px; font-weight: 700; color: #D4AF37; }

/* Firmas */
.ct-firmas {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
    margin-top: 48px;
    padding-top: 32px;
    border-top: 1px solid #e5e7eb;
}
.ct-firma { text-align: center; }
.ct-firma-linea { border-top: 1.5px solid #374151; margin-bottom: 8px; }
.ct-firma-nombre { font-weight: 700; font-size: 13px; }
.ct-firma-cargo { font-size: 11px; color: #6b7280; }
.ct-firma-ci { font-size: 11px; color: #6b7280; }

/* Pie del documento */
.ct-footer {
    margin-top: 32px;
    padding-top: 16px;
    border-top: 1px solid #e5e7eb;
    font-size: 10.5px;
    color: #9ca3af;
    text-align: center;
    line-height: 1.6;
}

@media print {
    .ct-toolbar, .sidebar, .topbar, .page-header { display: none !important; }
    .contrato-doc { box-shadow: none; border: none; padding: 20px; max-width: 100%; }
    body { background: #fff !important; }
}
</style>

<!-- Barra herramientas -->
<div class="ct-toolbar">
    <div class="ct-toolbar-left">
        <a href="javascript:history.back()" class="btn btn-outline btn-sm">← Volver</a>
        <div>
            <div style="font-family:'Poppins',sans-serif;font-weight:600;font-size:14px"><?= $numContrato ?></div>
            <div style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($ct['cliente_nombre']) ?> · <?= htmlspecialchars($ct['lote_codigo']) ?></div>
        </div>
    </div>
    <div class="ct-toolbar-right">
        <button onclick="window.print()" class="btn btn-outline btn-sm">🖨️ Imprimir</button>
        <a href="/sgi_aurora/contrato_pdf.php?id=<?= $id ?>" class="btn btn-primary btn-sm" target="_blank">📥 Descargar PDF</a>
    </div>
</div>

<!-- Documento -->
<div class="contrato-doc" id="contrato-imprimible">

    <!-- Membrete -->
    <div class="ct-header">
        <div class="ct-logo-block">
            <div class="ct-logo-icon">🏠</div>
            <div class="ct-empresa">
                <div class="ct-empresa-nombre">Bienes Raíces Aurora</div>
                <div class="ct-empresa-sub">Santa Cruz de la Sierra, Bolivia</div>
                <div class="ct-empresa-sub">NIT: 123456789 · Tel: +591 3 000-0000</div>
            </div>
        </div>
        <div class="ct-num-block">
            <div class="ct-num"><?= $numContrato ?></div>
            <div class="ct-fecha">Generado: <?= date('d/m/Y', strtotime($ct['fecha_gen'])) ?></div>
            <?php if ($ct['fecha_firma']): ?>
            <div class="ct-fecha">Firmado: <?= date('d/m/Y', strtotime($ct['fecha_firma'])) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Estado -->
    <?php
    $estadoClass = $ct['contrato_estado'];
    $estadoIcon  = ['borrador'=>'📝','generado'=>'📋','firmado'=>'✅','cancelado'=>'❌'][$estadoClass] ?? '📄';
    $estadoLabel = strtoupper($ct['contrato_estado']);
    ?>
    <div class="ct-estado-bar <?= $estadoClass ?>">
        <?= $estadoIcon ?> CONTRATO <?= $estadoLabel ?>
        <?php if ($ct['fecha_firma']): ?> · Firmado el <?= date('d \d\e F \d\e Y', strtotime($ct['fecha_firma'])) ?><?php endif; ?>
    </div>

    <!-- Título -->
    <div class="ct-titulo">
        <h2>Contrato de Compraventa de Bien Inmueble</h2>
        <p>Documento generado conforme a la Ley 247, Ley 393 y Ley 164 de Bolivia</p>
    </div>

    <!-- Introducción -->
    <div class="ct-section">
        <p>Conste por el presente documento el <strong>CONTRATO DE COMPRAVENTA DE BIEN INMUEBLE</strong>
        que celebran de una parte <strong><?= htmlspecialchars($ct['cliente_nombre']) ?></strong>,
        portador del C.I. <strong><?= htmlspecialchars($ct['cliente_ci'] ?? 'S/D') ?></strong>,
        en calidad de <strong>COMPRADOR</strong>;
        y de otra parte <strong>Bienes Raíces Aurora</strong>, representada por
        <strong><?= htmlspecialchars($ct['asesor_nombre']) ?></strong>, portador del C.I.
        <strong><?= htmlspecialchars($ct['asesor_ci'] ?? 'S/D') ?></strong>,
        en calidad de <strong>VENDEDOR</strong>, quienes acuerdan las siguientes cláusulas:</p>
    </div>

    <!-- CLÁUSULA 1: Partes -->
    <div class="ct-section">
        <div class="ct-section-title">Cláusula Primera — Datos de las Partes</div>
        <div style="margin-bottom:16px">
            <strong>COMPRADOR</strong>
            <div class="ct-grid" style="margin-top:8px">
                <div class="ct-field"><span class="ct-field-label">Nombre completo:</span><span class="ct-field-value"><?= htmlspecialchars($ct['cliente_nombre']) ?></span></div>
                <div class="ct-field"><span class="ct-field-label">C.I.:</span><span class="ct-field-value"><?= htmlspecialchars($ct['cliente_ci'] ?? '—') ?></span></div>
                <div class="ct-field"><span class="ct-field-label">Teléfono:</span><span class="ct-field-value"><?= htmlspecialchars($ct['cliente_tel'] ?? '—') ?></span></div>
                <div class="ct-field"><span class="ct-field-label">Email:</span><span class="ct-field-value"><?= htmlspecialchars($ct['cliente_email']) ?></span></div>
            </div>
        </div>
        <div>
            <strong>VENDEDOR / ASESOR</strong>
            <div class="ct-grid" style="margin-top:8px">
                <div class="ct-field"><span class="ct-field-label">Nombre completo:</span><span class="ct-field-value"><?= htmlspecialchars($ct['asesor_nombre']) ?></span></div>
                <div class="ct-field"><span class="ct-field-label">C.I.:</span><span class="ct-field-value"><?= htmlspecialchars($ct['asesor_ci'] ?? '—') ?></span></div>
                <div class="ct-field"><span class="ct-field-label">Teléfono:</span><span class="ct-field-value"><?= htmlspecialchars($ct['asesor_tel'] ?? '—') ?></span></div>
                <div class="ct-field"><span class="ct-field-label">Email:</span><span class="ct-field-value"><?= htmlspecialchars($ct['asesor_email']) ?></span></div>
            </div>
        </div>
    </div>

    <!-- CLÁUSULA 2: Bien inmueble -->
    <div class="ct-section">
        <div class="ct-section-title">Cláusula Segunda — Descripción del Bien Inmueble</div>
        <div class="ct-grid">
            <div class="ct-field"><span class="ct-field-label">Código de lote:</span><span class="ct-field-value"><?= htmlspecialchars($ct['lote_codigo']) ?></span></div>
            <div class="ct-field"><span class="ct-field-label">Nombre:</span><span class="ct-field-value"><?= htmlspecialchars($ct['lote_nombre']) ?></span></div>
            <div class="ct-field"><span class="ct-field-label">Zona:</span><span class="ct-field-value"><?= htmlspecialchars($ct['zona_nombre']) ?></span></div>
            <div class="ct-field"><span class="ct-field-label">Tipo:</span><span class="ct-field-value"><?= htmlspecialchars($ct['lote_tipo']) ?></span></div>
            <div class="ct-field"><span class="ct-field-label">Superficie:</span><span class="ct-field-value"><?= number_format($ct['superficie'], 2) ?> m²</span></div>
            <div class="ct-field"><span class="ct-field-label">Precio de catálogo:</span><span class="ct-field-value">USD $<?= number_format($ct['lote_precio'], 2, '.', ',') ?></span></div>
            <?php if ($ct['lote_direccion']): ?>
            <div class="ct-field" style="grid-column:1/-1"><span class="ct-field-label">Dirección:</span><span class="ct-field-value"><?= htmlspecialchars($ct['lote_direccion']) ?></span></div>
            <?php endif; ?>
            <?php if ($ct['servicios']): ?>
            <div class="ct-field" style="grid-column:1/-1"><span class="ct-field-label">Servicios:</span><span class="ct-field-value"><?= htmlspecialchars($ct['servicios']) ?></span></div>
            <?php endif; ?>
        </div>
        <p style="margin-top:12px">El bien inmueble descrito ha sido <strong>verificado en Derechos Reales (DDRR)</strong> conforme a la Ley 247 de Regularización del Derecho Propietario, acreditando que se encuentra libre de gravámenes, hipotecas y cargas reales.</p>
    </div>

    <!-- CLÁUSULA 3: Precio y forma de pago -->
    <div class="ct-section">
        <div class="ct-section-title">Cláusula Tercera — Precio y Forma de Pago</div>
        <div class="ct-grid" style="margin-bottom:16px">
            <div class="ct-field"><span class="ct-field-label">Monto acordado:</span><span class="ct-field-value" style="color:#0A2647;font-size:15px">USD $<?= number_format($ct['monto'], 2, '.', ',') ?></span></div>
            <div class="ct-field"><span class="ct-field-label">Modalidad:</span><span class="ct-field-value"><?= htmlspecialchars($ct['modalidad']) ?></span></div>
            <div class="ct-field"><span class="ct-field-label">Fecha de venta:</span><span class="ct-field-value"><?= date('d/m/Y', strtotime($ct['fecha_venta'])) ?></span></div>
        </div>
        <p>El precio pactado es de <strong>USD $<?= number_format($ct['monto'], 2, '.', ',') ?> (<?= strtoupper(num2words($ct['monto'])) ?> DÓLARES AMERICANOS)</strong>, pagadero bajo la modalidad de <strong><?= htmlspecialchars($ct['modalidad']) ?></strong>, conforme a la Ley 393 de Servicios Financieros.</p>

        <?php if (!empty($pagos)): ?>
        <div style="margin-top:14px">
            <strong style="font-size:12px;font-family:'Poppins',sans-serif">Historial de pagos registrados:</strong>
            <table class="ct-table" style="margin-top:8px">
                <thead><tr><th>#</th><th>Fecha</th><th>Método</th><th>Comprobante</th><th>Monto USD</th></tr></thead>
                <tbody>
                    <?php foreach ($pagos as $i => $p): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td><?= date('d/m/Y', strtotime($p['fecha_pago'])) ?></td>
                        <td><?= htmlspecialchars($p['metodo']) ?></td>
                        <td><?= htmlspecialchars($p['comprobante'] ?? '—') ?></td>
                        <td><strong>$<?= number_format($p['monto'], 2, '.', ',') ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" style="text-align:right">Total pagado:</td>
                        <td>$<?= number_format($totalPagado, 2, '.', ',') ?></td>
                    </tr>
                </tfoot>
            </table>
            <div class="ct-saldo">
                <div>
                    <div class="ct-saldo-label">Saldo pendiente</div>
                    <div style="font-size:11px;opacity:.7">Monto acordado menos pagos realizados</div>
                </div>
                <div class="ct-saldo-monto">$<?= number_format($saldo, 2, '.', ',') ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- CLÁUSULA 4: Obligaciones -->
    <div class="ct-section">
        <div class="ct-section-title">Cláusula Cuarta — Obligaciones de las Partes</div>
        <div class="ct-clausula">
            <span class="ct-clausula-num">4.1.</span> El VENDEDOR se obliga a transferir la propiedad libre de todo gravamen una vez concluido el pago íntegro del precio pactado, debiendo tramitar la minuta de transferencia ante notario público competente.
        </div>
        <div class="ct-clausula">
            <span class="ct-clausula-num">4.2.</span> El COMPRADOR se obliga a efectuar los pagos en los plazos y formas convenidas, debiendo presentar comprobante de toda transferencia o depósito bancario superior a USD 1,000 conforme a la <strong>Ley 393</strong>.
        </div>
        <div class="ct-clausula">
            <span class="ct-clausula-num">4.3.</span> Ambas partes se comprometen a mantener la confidencialidad de los datos personales intercambiados durante la presente relación comercial, conforme a la <strong>Ley 164</strong> de Telecomunicaciones y Tecnologías de la Información.
        </div>
    </div>

    <!-- CLÁUSULA 5: Resolución -->
    <div class="ct-section">
        <div class="ct-section-title">Cláusula Quinta — Resolución del Contrato</div>
        <p>En caso de incumplimiento de cualquiera de las partes, la parte afectada podrá resolver el presente contrato notificando por escrito con un plazo de quince (15) días hábiles. El COMPRADOR perderá como penalidad el 10% del monto total pagado; el VENDEDOR deberá devolver el íntegro de los pagos recibidos más un 10% adicional por concepto de daños y perjuicios.</p>
    </div>

    <!-- CLÁUSULA 6: Jurisdicción -->
    <div class="ct-section">
        <div class="ct-section-title">Cláusula Sexta — Jurisdicción y Ley Aplicable</div>
        <p>Para todas las controversias derivadas del presente contrato, las partes se someten expresamente a la jurisdicción de los tribunales ordinarios de la ciudad de <strong>Santa Cruz de la Sierra</strong>, aplicando la legislación vigente de la República Plurinacional de Bolivia.</p>
    </div>

    <!-- Conformidad -->
    <div class="ct-section">
        <p>En fe de lo cual, las partes suscriben el presente contrato en la ciudad de Santa Cruz de la Sierra, a los
        <strong><?= date('d', strtotime($ct['fecha_gen'])) ?></strong> días del mes de
        <strong><?= strftime_es(strtotime($ct['fecha_gen'])) ?></strong>
        del año <strong><?= date('Y', strtotime($ct['fecha_gen'])) ?></strong>.</p>
    </div>

    <!-- Firmas -->
    <div class="ct-firmas">
        <div class="ct-firma">
            <div style="height:40px"></div>
            <div class="ct-firma-linea"></div>
            <div class="ct-firma-nombre"><?= htmlspecialchars($ct['cliente_nombre']) ?></div>
            <div class="ct-firma-cargo">COMPRADOR</div>
            <div class="ct-firma-ci">C.I. <?= htmlspecialchars($ct['cliente_ci'] ?? 'S/D') ?></div>
        </div>
        <div class="ct-firma">
            <div style="height:40px"></div>
            <div class="ct-firma-linea"></div>
            <div class="ct-firma-nombre"><?= htmlspecialchars($ct['asesor_nombre']) ?></div>
            <div class="ct-firma-cargo">VENDEDOR — Bienes Raíces Aurora</div>
            <div class="ct-firma-ci">C.I. <?= htmlspecialchars($ct['asesor_ci'] ?? 'S/D') ?></div>
        </div>
    </div>

    <!-- Pie -->
    <div class="ct-footer">
        Documento N° <?= $numContrato ?> · Generado por SGI Aurora v1.0 · <?= date('d/m/Y H:i') ?><br>
        Bienes Raíces Aurora · Santa Cruz de la Sierra, Bolivia<br>
        Este documento tiene validez legal conforme a la legislación boliviana vigente.
        Ley 164 · Ley 393 · Ley 247
    </div>

</div>

<?php
// Helper: número a palabras (simplificado para montos inmobiliarios)
function num2words(float $n): string {
    $n = (int)round($n);
    $unidades = ['','uno','dos','tres','cuatro','cinco','seis','siete','ocho','nueve',
        'diez','once','doce','trece','catorce','quince','dieciséis','diecisiete','dieciocho','diecinueve'];
    $decenas  = ['','','veinte','treinta','cuarenta','cincuenta','sesenta','setenta','ochenta','noventa'];
    $centenas = ['','cien','doscientos','trescientos','cuatrocientos','quinientos','seiscientos','setecientos','ochocientos','novecientos'];
    if ($n === 0) return 'cero';
    if ($n < 20) return $unidades[$n];
    if ($n < 100) { $d = intdiv($n,10); $u = $n%10; return $decenas[$d].($u?' y '.$unidades[$u]:''); }
    if ($n < 1000) { $c = intdiv($n,100); $r = $n%100; return ($n===100?'cien':$centenas[$c]).($r?' '.num2words($r):''); }
    if ($n < 1000000) { $m = intdiv($n,1000); $r = $n%1000; return ($m===1?'mil':num2words($m).' mil').($r?' '.num2words($r):''); }
    return number_format($n);
}

// Helper: mes en español
function strftime_es(int $ts): string {
    $meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    return $meses[(int)date('n', $ts) - 1];
}
?>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
