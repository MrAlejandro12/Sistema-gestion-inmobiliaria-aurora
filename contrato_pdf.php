<?php
// contrato_pdf.php — Genera el contrato como PDF descargable
// Ubicar en: sgi_aurora/contrato_pdf.php
// Requiere: composer require tecnickcom/tcpdf
// Si no tienes Composer, se cae en modo HTML imprimible

require_once __DIR__ . '/config/db.php';
secureSessionStart();
requireAuth(['asesor', 'legal', 'admin']);

$pdo = getDB();
$id  = (int)($_GET['id'] ?? 0);

// ── Obtener datos del contrato ───────────────────────────────
$stmt = $pdo->prepare("
    SELECT
        ct.id contrato_id, ct.estado contrato_estado, ct.fecha_gen, ct.fecha_firma, ct.notario,
        v.id venta_id, v.monto, v.modalidad, v.fecha_venta,
        uc.nombre cliente_nombre, uc.email cliente_email, uc.telefono cliente_tel, uc.ci cliente_ci,
        l.codigo lote_codigo, l.nombre lote_nombre, l.direccion lote_direccion,
        l.superficie, l.precio lote_precio, l.tipo lote_tipo, l.servicios,
        z.nombre zona_nombre,
        ua.nombre asesor_nombre, ua.email asesor_email, ua.telefono asesor_tel, ua.ci asesor_ci
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

if (!$ct) { http_response_code(404); die('Contrato no encontrado'); }

// Verificar acceso asesor
if ($_SESSION['rol'] === 'asesor') {
    $check = $pdo->prepare("SELECT v.asesor_id FROM ventas v JOIN contratos ct ON ct.venta_id=v.id WHERE ct.id=?");
    $check->execute([$id]);
    $row = $check->fetch();
    if (!$row || $row['asesor_id'] != $_SESSION['user_id']) {
        http_response_code(403); die('Sin permisos');
    }
}

// Pagos
$pagosStmt = $pdo->prepare("SELECT p.monto, p.metodo, p.comprobante, p.fecha_pago FROM pagos p WHERE p.venta_id = ? ORDER BY p.fecha_pago ASC");
$pagosStmt->execute([$ct['venta_id']]);
$pagos = $pagosStmt->fetchAll();
$totalPagado = array_sum(array_column($pagos, 'monto'));
$saldo = $ct['monto'] - $totalPagado;

$numContrato = 'CT-' . str_pad($ct['contrato_id'], 4, '0', STR_PAD_LEFT) . '-' . date('Y', strtotime($ct['fecha_gen']));

auditLog("Descargó PDF contrato $numContrato", 'contratos', $id);

// ── Helpers ──────────────────────────────────────────────────
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
function mes_es(int $ts): string {
    $m = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    return $m[(int)date('n',$ts)-1];
}

// ── Intentar usar TCPDF ─────────────────────────────────────
$tcpdfPath = __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php';
$useTCPDF  = file_exists($tcpdfPath);

if ($useTCPDF) {
    // ── MODO TCPDF (instalado con Composer) ─────────────────
    require_once $tcpdfPath;

    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('SGI Aurora');
    $pdf->SetAuthor('Bienes Raíces Aurora');
    $pdf->SetTitle('Contrato ' . $numContrato);
    $pdf->SetSubject('Contrato de Compraventa');
    $pdf->SetKeywords('contrato, inmueble, aurora, bolivia');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(20, 20, 20);
    $pdf->SetAutoPageBreak(true, 25);
    $pdf->AddPage();

    // HTML del contrato para TCPDF
    ob_start();
    include __DIR__ . '/contrato_tcpdf_tpl.php'; // ver más abajo, usa $ct, $pagos, etc.
    $html = ob_get_clean();

    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output($numContrato . '.pdf', 'D'); // D = Descarga directa
    exit;
}

// ── MODO FALLBACK: HTML con CSS print + botón imprimir ──────
// Se envía HTML que el navegador puede imprimir/guardar como PDF
// con Ctrl+P → Guardar como PDF (funciona en Chrome, Edge, Firefox)

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $numContrato ?> — Bienes Raíces Aurora</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&family=EB+Garamond:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet">
<style>
* { margin:0; padding:0; box-sizing:border-box; }

:root {
    --navy: #0A2647;
    --navy-l: #1a3a6b;
    --gold: #D4AF37;
    --muted: #6b7280;
    --border: #e5e7eb;
}

/* Barra de descarga — solo en pantalla */
@media screen {
    body { background: #f3f4f6; padding: 20px; font-family: 'EB Garamond', Georgia, serif; }
    .download-bar {
        background: #0A2647;
        color: #fff;
        border-radius: 12px;
        padding: 14px 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        max-width: 820px;
        margin: 0 auto 20px;
        font-family: 'Poppins', sans-serif;
        font-size: 13px;
    }
    .download-bar-left { display: flex; align-items: center; gap: 12px; }
    .download-bar-right { display: flex; gap: 8px; }
    .btn-dl {
        padding: 8px 18px;
        border-radius: 8px;
        border: none;
        font-family: 'Poppins', sans-serif;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .btn-dl-primary { background: #D4AF37; color: #0A2647; }
    .btn-dl-outline { background: rgba(255,255,255,.1); color: #fff; border: 1px solid rgba(255,255,255,.3); }
    .doc { max-width: 820px; margin: 0 auto; }
}

@media print {
    .download-bar { display: none !important; }
    body { background: #fff; padding: 0; }
    .doc { max-width: 100%; }
    .page-break { page-break-before: always; }
}

/* Documento */
.doc {
    background: #fff;
    padding: 52px 60px;
    font-family: 'EB Garamond', Georgia, serif;
    font-size: 13.5px;
    line-height: 1.8;
    color: #111827;
}

/* Membrete */
.header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    padding-bottom: 20px;
    border-bottom: 3px solid var(--navy);
    margin-bottom: 28px;
}
.logo-block { display: flex; align-items: center; gap: 12px; }
.logo-icon {
    width: 48px; height: 48px;
    background: linear-gradient(135deg, var(--navy), var(--navy-l));
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; color: #fff;
}
.empresa-nombre { font-family: 'Poppins', sans-serif; font-size: 16px; font-weight: 700; color: var(--navy); }
.empresa-sub { font-size: 11px; color: var(--muted); line-height: 1.4; }
.num-block { text-align: right; }
.num-contrato { font-family: 'Poppins', sans-serif; font-size: 20px; font-weight: 700; color: var(--navy); }
.num-fecha { font-size: 11px; color: var(--muted); margin-top: 2px; }

/* Estado */
.estado-bar {
    text-align: center;
    padding: 10px;
    border-radius: 8px;
    font-family: 'Poppins', sans-serif;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .05em;
    margin-bottom: 28px;
    background: #f0fdf4;
    border: 1.5px solid #86efac;
    color: #166534;
}

/* Título */
.titulo {
    text-align: center;
    margin-bottom: 24px;
    border-bottom: 1px solid var(--border);
    padding-bottom: 20px;
}
.titulo h1 {
    font-family: 'Poppins', sans-serif;
    font-size: 17px;
    font-weight: 700;
    color: var(--navy);
    text-transform: uppercase;
    letter-spacing: .07em;
    margin-bottom: 4px;
}
.titulo p { font-size: 12px; color: var(--muted); }

/* Secciones */
.sec { margin-bottom: 22px; }
.sec-title {
    font-family: 'Poppins', sans-serif;
    font-size: 10.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .1em;
    color: #fff;
    background: var(--navy);
    padding: 5px 12px;
    border-radius: 5px;
    margin-bottom: 12px;
    display: inline-block;
}
.grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 5px 20px; }
.field { display: flex; gap: 6px; font-size: 13px; margin-bottom: 2px; }
.flabel { color: var(--muted); min-width: 130px; flex-shrink: 0; font-size: 12px; }
.fvalue { font-weight: 600; }

/* Cláusulas */
.clausula { margin-bottom: 10px; }
.clausula-num { font-family: 'Poppins', sans-serif; font-weight: 700; color: var(--navy); }

/* Tabla pagos */
table { width: 100%; border-collapse: collapse; font-size: 12.5px; margin-top: 10px; }
table thead th {
    background: var(--navy);
    color: #fff;
    padding: 7px 10px;
    text-align: left;
    font-family: 'Poppins', sans-serif;
    font-size: 11px;
    font-weight: 600;
}
table tbody td { padding: 7px 10px; border-bottom: 1px solid var(--border); }
table tfoot td { font-weight: 700; background: #f8fafc; padding: 7px 10px; }

/* Saldo */
.saldo-box {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--navy);
    border-radius: 8px;
    padding: 12px 18px;
    color: #fff;
    margin-top: 12px;
    font-family: 'Poppins', sans-serif;
}
.saldo-monto { font-size: 20px; font-weight: 700; color: var(--gold); }

/* Firmas */
.firmas { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 48px; padding-top: 24px; border-top: 1px solid var(--border); }
.firma { text-align: center; }
.firma-linea { border-top: 1.5px solid #374151; margin-bottom: 8px; }
.firma-nombre { font-weight: 700; font-size: 13px; }
.firma-cargo { font-size: 11px; color: var(--muted); }

/* Pie */
.footer {
    margin-top: 28px;
    padding-top: 14px;
    border-top: 1px solid var(--border);
    font-size: 10px;
    color: #9ca3af;
    text-align: center;
    line-height: 1.6;
}

/* Sello legal */
.sello {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 2px solid #166534;
    border-radius: 8px;
    padding: 6px 14px;
    color: #166534;
    font-family: 'Poppins', sans-serif;
    font-size: 11px;
    font-weight: 700;
    margin-top: 16px;
}
</style>
</head>
<body>

<!-- Barra de descarga (solo pantalla, no imprime) -->
<div class="download-bar">
    <div class="download-bar-left">
        <span style="font-size:20px">📄</span>
        <div>
            <div style="font-weight:600"><?= $numContrato ?></div>
            <div style="opacity:.7;font-size:12px"><?= htmlspecialchars($ct['cliente_nombre']) ?> · <?= htmlspecialchars($ct['lote_codigo']) ?></div>
        </div>
    </div>
    <div class="download-bar-right">
        <a href="javascript:history.back()" class="btn-dl btn-dl-outline">← Volver</a>
        <button onclick="window.print()" class="btn-dl btn-dl-primary">📥 Guardar como PDF</button>
    </div>
</div>

<script>
// Auto-abrir el diálogo de impresión en modo PDF
// Solo si viene con ?autoprint=1
<?php if (!empty($_GET['autoprint'])): ?>
window.addEventListener('load', () => setTimeout(() => window.print(), 600));
<?php endif; ?>
</script>

<!-- DOCUMENTO -->
<div class="doc">

    <!-- Membrete -->
    <div class="header">
        <div class="logo-block">
            <div class="logo-icon">🏠</div>
            <div>
                <div class="empresa-nombre">Bienes Raíces Aurora</div>
                <div class="empresa-sub">Santa Cruz de la Sierra, Bolivia<br>NIT: 123456789 · Tel: +591 3 000-0000</div>
            </div>
        </div>
        <div class="num-block">
            <div class="num-contrato"><?= $numContrato ?></div>
            <div class="num-fecha">Generado: <?= date('d/m/Y', strtotime($ct['fecha_gen'])) ?></div>
            <?php if ($ct['fecha_firma']): ?><div class="num-fecha">Firmado: <?= date('d/m/Y', strtotime($ct['fecha_firma'])) ?></div><?php endif; ?>
        </div>
    </div>

    <!-- Estado -->
    <div class="estado-bar">
        ✅ CONTRATO <?= strtoupper($ct['contrato_estado']) ?>
        <?php if ($ct['fecha_firma']): ?> · Firmado el <?= date('d', strtotime($ct['fecha_firma'])) ?> de <?= mes_es(strtotime($ct['fecha_firma'])) ?> de <?= date('Y', strtotime($ct['fecha_firma'])) ?><?php endif; ?>
    </div>

    <!-- Título -->
    <div class="titulo">
        <h1>Contrato de Compraventa de Bien Inmueble</h1>
        <p>Documento emitido conforme a la Ley 247 · Ley 393 · Ley 164 — República Plurinacional de Bolivia</p>
    </div>

    <!-- Cuerpo -->
    <div class="sec">
        <p>Conste por el presente documento el <strong>CONTRATO DE COMPRAVENTA DE BIEN INMUEBLE</strong>
        que celebran de una parte <strong><?= htmlspecialchars($ct['cliente_nombre']) ?></strong>,
        portador del C.I. <strong><?= htmlspecialchars($ct['cliente_ci'] ?? 'S/D') ?></strong>, en calidad de <strong>COMPRADOR</strong>;
        y de otra parte <strong>Bienes Raíces Aurora</strong>, representada por
        <strong><?= htmlspecialchars($ct['asesor_nombre']) ?></strong>, portador del C.I.
        <strong><?= htmlspecialchars($ct['asesor_ci'] ?? 'S/D') ?></strong>, en calidad de <strong>VENDEDOR</strong>,
        bajo las siguientes cláusulas:</p>
    </div>

    <!-- DATOS PARTES -->
    <div class="sec">
        <div class="sec-title">Cláusula Primera — Datos de las Partes</div>
        <p><strong>COMPRADOR</strong></p>
        <div class="grid2" style="margin: 8px 0 14px">
            <div class="field"><span class="flabel">Nombre completo:</span><span class="fvalue"><?= htmlspecialchars($ct['cliente_nombre']) ?></span></div>
            <div class="field"><span class="flabel">C.I.:</span><span class="fvalue"><?= htmlspecialchars($ct['cliente_ci'] ?? '—') ?></span></div>
            <div class="field"><span class="flabel">Teléfono:</span><span class="fvalue"><?= htmlspecialchars($ct['cliente_tel'] ?? '—') ?></span></div>
            <div class="field"><span class="flabel">Email:</span><span class="fvalue"><?= htmlspecialchars($ct['cliente_email']) ?></span></div>
        </div>
        <p><strong>VENDEDOR / ASESOR</strong></p>
        <div class="grid2" style="margin-top: 8px">
            <div class="field"><span class="flabel">Nombre completo:</span><span class="fvalue"><?= htmlspecialchars($ct['asesor_nombre']) ?></span></div>
            <div class="field"><span class="flabel">C.I.:</span><span class="fvalue"><?= htmlspecialchars($ct['asesor_ci'] ?? '—') ?></span></div>
            <div class="field"><span class="flabel">Teléfono:</span><span class="fvalue"><?= htmlspecialchars($ct['asesor_tel'] ?? '—') ?></span></div>
            <div class="field"><span class="flabel">Email:</span><span class="fvalue"><?= htmlspecialchars($ct['asesor_email']) ?></span></div>
        </div>
    </div>

    <!-- BIEN INMUEBLE -->
    <div class="sec">
        <div class="sec-title">Cláusula Segunda — Descripción del Bien Inmueble</div>
        <div class="grid2">
            <div class="field"><span class="flabel">Código de lote:</span><span class="fvalue"><?= htmlspecialchars($ct['lote_codigo']) ?></span></div>
            <div class="field"><span class="flabel">Nombre:</span><span class="fvalue"><?= htmlspecialchars($ct['lote_nombre']) ?></span></div>
            <div class="field"><span class="flabel">Zona:</span><span class="fvalue"><?= htmlspecialchars($ct['zona_nombre']) ?></span></div>
            <div class="field"><span class="flabel">Tipo:</span><span class="fvalue"><?= htmlspecialchars($ct['lote_tipo']) ?></span></div>
            <div class="field"><span class="flabel">Superficie:</span><span class="fvalue"><?= number_format($ct['superficie'], 2) ?> m²</span></div>
            <div class="field"><span class="flabel">Precio catálogo:</span><span class="fvalue">USD $<?= number_format($ct['lote_precio'], 2, '.', ',') ?></span></div>
            <?php if ($ct['lote_direccion']): ?>
            <div class="field" style="grid-column:1/-1"><span class="flabel">Dirección:</span><span class="fvalue"><?= htmlspecialchars($ct['lote_direccion']) ?></span></div>
            <?php endif; ?>
            <?php if ($ct['servicios']): ?>
            <div class="field" style="grid-column:1/-1"><span class="flabel">Servicios:</span><span class="fvalue"><?= htmlspecialchars($ct['servicios']) ?></span></div>
            <?php endif; ?>
        </div>
        <p style="margin-top:10px">El bien inmueble ha sido <strong>verificado en Derechos Reales (DDRR)</strong> conforme a la Ley 247 de Regularización del Derecho Propietario, encontrándose libre de gravámenes, hipotecas y cargas reales.</p>
        <div class="sello">⚖️ VERIFICADO DDRR — Ley 247</div>
    </div>

    <!-- PRECIO Y PAGO -->
    <div class="sec">
        <div class="sec-title">Cláusula Tercera — Precio y Forma de Pago</div>
        <div class="grid2" style="margin-bottom:12px">
            <div class="field"><span class="flabel">Monto acordado:</span><span class="fvalue" style="color:var(--navy);font-size:15px">USD $<?= number_format($ct['monto'], 2, '.', ',') ?></span></div>
            <div class="field"><span class="flabel">Modalidad:</span><span class="fvalue"><?= htmlspecialchars($ct['modalidad']) ?></span></div>
            <div class="field"><span class="flabel">Fecha de venta:</span><span class="fvalue"><?= date('d/m/Y', strtotime($ct['fecha_venta'])) ?></span></div>
        </div>
        <p>El precio pactado es de <strong>USD $<?= number_format($ct['monto'], 2, '.', ',') ?> (<?= strtoupper(num2words($ct['monto'])) ?> DÓLARES AMERICANOS)</strong>, pagadero bajo la modalidad de <strong><?= htmlspecialchars($ct['modalidad']) ?></strong>, conforme a la Ley 393 de Servicios Financieros de Bolivia.</p>

        <?php if (!empty($pagos)): ?>
        <table style="margin-top:14px">
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
                <tr><td colspan="4" style="text-align:right;padding:7px 10px">Total pagado:</td><td style="padding:7px 10px"><strong>$<?= number_format($totalPagado, 2, '.', ',') ?></strong></td></tr>
            </tfoot>
        </table>
        <div class="saldo-box">
            <div><div style="font-size:12px;opacity:.8">Saldo pendiente</div><div style="font-size:11px;opacity:.6">Monto acordado − pagos realizados</div></div>
            <div class="saldo-monto">$<?= number_format($saldo, 2, '.', ',') ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- OBLIGACIONES -->
    <div class="sec">
        <div class="sec-title">Cláusula Cuarta — Obligaciones de las Partes</div>
        <div class="clausula"><span class="clausula-num">4.1.</span> El VENDEDOR se obliga a transferir la propiedad libre de todo gravamen una vez concluido el pago íntegro del precio pactado, debiendo tramitar la minuta de transferencia ante notario público competente.</div>
        <div class="clausula"><span class="clausula-num">4.2.</span> El COMPRADOR se obliga a efectuar los pagos en los plazos y formas convenidas, presentando comprobante de toda transferencia superior a USD 1,000 conforme a la <strong>Ley 393</strong>.</div>
        <div class="clausula"><span class="clausula-num">4.3.</span> Ambas partes se comprometen a mantener la confidencialidad de los datos personales, conforme a la <strong>Ley 164</strong> de Telecomunicaciones y Tecnologías de la Información.</div>
    </div>

    <!-- RESOLUCIÓN -->
    <div class="sec">
        <div class="sec-title">Cláusula Quinta — Resolución del Contrato</div>
        <p>En caso de incumplimiento, la parte afectada podrá resolver el contrato notificando por escrito con quince (15) días hábiles de anticipación. El COMPRADOR perderá como penalidad el 10% del monto pagado; el VENDEDOR deberá restituir los pagos recibidos más un 10% adicional por daños y perjuicios.</p>
    </div>

    <!-- JURISDICCIÓN -->
    <div class="sec">
        <div class="sec-title">Cláusula Sexta — Jurisdicción y Ley Aplicable</div>
        <p>Las partes se someten a la jurisdicción de los tribunales ordinarios de <strong>Santa Cruz de la Sierra</strong>, aplicando la legislación vigente de la República Plurinacional de Bolivia.</p>
    </div>

    <!-- CONFORMIDAD -->
    <p>En fe de lo cual suscriben en la ciudad de Santa Cruz de la Sierra, a los <strong><?= date('d', strtotime($ct['fecha_gen'])) ?></strong> días del mes de <strong><?= mes_es(strtotime($ct['fecha_gen'])) ?></strong> del año <strong><?= date('Y', strtotime($ct['fecha_gen'])) ?></strong>.</p>

    <!-- FIRMAS -->
    <div class="firmas">
        <div class="firma">
            <div style="height:44px"></div>
            <div class="firma-linea"></div>
            <div class="firma-nombre"><?= htmlspecialchars($ct['cliente_nombre']) ?></div>
            <div class="firma-cargo">COMPRADOR</div>
            <div class="firma-cargo">C.I. <?= htmlspecialchars($ct['cliente_ci'] ?? 'S/D') ?></div>
        </div>
        <div class="firma">
            <div style="height:44px"></div>
            <div class="firma-linea"></div>
            <div class="firma-nombre"><?= htmlspecialchars($ct['asesor_nombre']) ?></div>
            <div class="firma-cargo">VENDEDOR — Bienes Raíces Aurora</div>
            <div class="firma-cargo">C.I. <?= htmlspecialchars($ct['asesor_ci'] ?? 'S/D') ?></div>
        </div>
    </div>

    <!-- PIE -->
    <div class="footer">
        Documento N° <?= $numContrato ?> · Generado por SGI Aurora v1.0 · <?= date('d/m/Y H:i') ?><br>
        Bienes Raíces Aurora · Santa Cruz de la Sierra, Bolivia<br>
        Cumplimiento: Ley 164 · Ley 393 · Ley 247 — República Plurinacional de Bolivia
    </div>

</div>
</body>
</html>
