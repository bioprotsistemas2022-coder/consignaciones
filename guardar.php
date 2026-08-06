<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/render_paso_caja.php';
require_once 'includes/extraer_instrumental.php';

$plcCod = isset($_POST['plc_cod']) ? (int)$_POST['plc_cod'] : 0;
$usrCod = trim($_POST['usr_cod'] ?? '');
$institucion = trim($_POST['institucion'] ?? '');
$fecha = trim($_POST['fecha'] ?? '');
$doctor = trim($_POST['doctor'] ?? '');
$paciente = trim($_POST['paciente'] ?? '');
$pdfs = $_POST['pdfs'] ?? [];
$paso = isset($_POST['paso']) ? (int)$_POST['paso'] : 0;
$total = count($pdfs);

$cants = $_POST['cant'] ?? [];
$descs = $_POST['desc'] ?? [];
$chks = $_POST['chk'] ?? [];

// Cargar consignaciones previas desde la sesión
$guardadas = (isset($_SESSION['guardadas']) && is_array($_SESSION['guardadas'])) ? $_SESSION['guardadas'] : [];

$error = '';
$ncoCod = 0;
$nombreActual = ($total > 0) ? extraerNombrePdf($pdfs[$paso]) : '';

try {
        $pdo->beginTransaction();

        // Validar la caja seleccionada contra el catálogo global de cajas
        $ncoCac = (int)($_POST['nco_cac'] ?? 0);
        if ($ncoCac > 0) {
            $val = $pdo->prepare('SELECT COUNT(*) FROM cajacirugia WHERE CacCod = ?');
            $val->execute([$ncoCac]);
            if ((int)$val->fetchColumn() === 0) $ncoCac = 0;
        }

        $stmt = $pdo->prepare('INSERT INTO notaconsignacion (NcoFec, NcoCac, NcoPlcCod, NcoHosDesc, NcoMed, NcoPac) VALUES (CURDATE(), ?, ?, ?, ?, ?)');
        $stmt->execute([$ncoCac, $plcCod, $institucion, $doctor, $paciente]);
        $ncoCod = (int)$pdo->lastInsertId();

        $stmtDet = $pdo->prepare('INSERT INTO notaconsignaciondetalle (NcoCod, NcoDetItm, NcoDetCan, NcoDetDsc, NcoDetChk) VALUES (?, ?, ?, ?, ?)');
        $itm = 1;
        foreach ($cants as $k => $cant) {
            $cantidad = (int)$cant;
            $descripcion = trim($descs[$k] ?? '');
            $checked = isset($chks[$k]) ? 'S' : 'N';
            if ($cantidad > 0 && $descripcion !== '') {
                $stmtDet->execute([$ncoCod, $itm++, $cantidad, $descripcion, $checked]);
            }
        }

        // Implantes / materiales
        $implArtId    = $_POST['impl_art_id']    ?? [];
        $implArtMat   = $_POST['impl_art_mat_id'] ?? [];
        $implGtin     = $_POST['impl_gtin']      ?? [];
        $implDsc      = $_POST['impl_dsc']       ?? [];
        $implCan      = $_POST['impl_can']       ?? [];
        $implRep      = $_POST['impl_rep']       ?? [];
        $implLot      = $_POST['impl_lot']       ?? [];
        $implVen      = $_POST['impl_ven']       ?? [];
        $implSer      = $_POST['impl_ser']       ?? [];
        $implUbi      = $_POST['impl_ubi']       ?? [];

        $stmtImpl = $pdo->prepare('INSERT INTO notaconsignacionimplante
            (NcoCod, ImplItm, ArtId, ArtMatId, ArtMatGtin, ImplCan, ImplDsc, ImplLot, ImplVen, ImplSer, ImplRep, ImplUbiCod)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $iitm = 1;
        foreach ($implArtId as $k => $artId) {
            $artId = (int)$artId;
            $artMatId = ($implArtMat[$k] ?? '') !== '' ? (int)$implArtMat[$k] : null;
            $gtin = trim($implGtin[$k] ?? '');
            $desc = trim($implDsc[$k] ?? '');
            $can = (float)($implCan[$k] ?? 1);
            $lote = trim($implLot[$k] ?? '');
            $venRaw = trim($implVen[$k] ?? '');
            $ven = ($venRaw !== '' && $venRaw !== '0000-00-00') ? $venRaw : null;
            $ser = trim($implSer[$k] ?? '');
            $rep = (!empty($implRep[$k])) ? 'S' : 'N';
            $ubi = (int)($implUbi[$k] ?? 1);

            if ($can <= 0) continue;

            $stmtImpl->execute([$ncoCod, $iitm++, $artId, $artMatId, $gtin !== '' ? $gtin : null,
                $can, $desc, $lote !== '' ? $lote : null, $ven, $ser !== '' ? $ser : null, $rep, $ubi]);
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Error de base de datos: ' . $e->getMessage();
    }

if ($ncoCod > 0) {
    $guardadas[] = [$nombreActual, $ncoCod];
    $_SESSION['guardadas'] = $guardadas;
}

function e($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$esUltima = ($paso >= $total - 1);
$urlBack = 'index.php' . ($plcCod > 0 ? '?PlcCod=' . $plcCod : '?') . ($usrCod !== '' ? ($plcCod > 0 ? '&' : '') . 'UsrCod=' . urlencode($usrCod) : '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Control de Cajas - Instrumental</title>
<link rel="stylesheet" href="css/estilo.css">
</head>
<body>

<div class="contenedor-form">

    <div class="cabecera-form">
        <img class="logo" src="LOGO/logo_bioimplant.png" alt="Logo BIOPROT">
        <div class="datos-empresa" style="align-self:center;">
            <h1>Control de Cajas - Instrumental</h1>
            <p class="direccion">Verificá y editá la tabla de cada caja, luego continuá con la siguiente</p>
        </div>
    </div>

    <div class="nav-top no-print">
        <a href="<?= e($urlBack) ?>" class="btn btn-secundario">&larr; Volver</a>
    </div>

    <?php if ($error): ?>
    <div style="padding:15px;color:#721c24;background:#f8d7da;border:1px solid #f5c6cb;border-radius:4px;font-size:14px;">
        <?= e($error) ?>
    </div>
    <?php else: ?>

    <?php if ($esUltima): ?>
        <!-- === RESUMEN FINAL === -->
        <h2 class="section-title">Consignaciones guardadas</h2>
        <div style="padding:15px;color:#155724;background:#d4edda;border:1px solid #c3e6cb;border-radius:4px;font-size:15px;margin-bottom:15px;">
            Se guardaron <strong><?= count($guardadas) ?></strong> consignación(es) correctamente.
        </div>

        <?php if (count($guardadas) === 0): ?>
        <div style="padding:20px;color:#666;border:1px dashed #ccc;border-radius:6px;text-align:center;">
            No se guardó ninguna caja.
        </div>
        <?php else: ?>
        <table class="tabla-instrumental">
            <thead>
                <tr>
                    <th style="width:60px">N°</th>
                    <th>Caja</th>
                    <th style="width:200px">Remito PDF</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($guardadas as $g): ?>
                <?php if (is_array($g) && count($g) >= 2): ?>
                <tr>
                    <td style="text-align:center;font-weight:bold;"><?= (int)$g[1] ?></td>
                    <td><?= e($g[0]) ?></td>
                    <td style="text-align:center;"><a href="includes/generar_pdf.php?nco=<?= (int)$g[1] ?>" target="_blank" class="badge badge-secundario">Abrir Remito</a></td>
                </tr>
                <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Botón para generar todos los remitos en un solo PDF -->
        <div class="acciones no-print" style="margin-top:20px;">
            <a href="includes/generar_todos_pdf.php?ncos=<?= implode(',', array_column($guardadas, 1)) ?>" target="_blank" class="btn btn-primario">Generar todos los remitos en un PDF</a>
        </div>
        <?php endif; ?>

        <div class="acciones no-print">
            <a href="<?= e($urlBack) ?>" class="btn btn-primario">Volver al inicio</a>
        </div>

    <?php else: ?>
        <?php
        $siguiente = $paso + 1;
        $mensajePaso = 'Caja guardada: <strong>' . e($nombreActual) . '</strong> (N&deg; ' . $ncoCod . '). Continú&aacute; con la siguiente caja.';
        renderPasoCaja($siguiente, $pdfs, $plcCod, $usrCod, $institucion, $fecha, $doctor, $paciente, $mensajePaso, $guardadas);
        ?>
    <?php endif; ?>

    <?php endif; ?>
</div>

</body>
</html>