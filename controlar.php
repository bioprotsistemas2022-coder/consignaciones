<?php
session_start();
require_once 'includes/render_paso_caja.php';

$plcCod = isset($_POST['plc_cod']) ? (int)$_POST['plc_cod'] : 0;
$usrCod = trim($_POST['usr_cod'] ?? '');
$institucion = trim($_POST['institucion'] ?? '');
$fecha = trim($_POST['fecha'] ?? '');
$doctor = trim($_POST['doctor'] ?? '');
$paciente = trim($_POST['paciente'] ?? '');
$pdfs = $_POST['pdfs'] ?? [];
$paso = isset($_POST['paso']) ? (int)$_POST['paso'] : 0;
$accion = trim($_POST['accion'] ?? '');

$error = '';
$mostrarPaso = 0;

if ($plcCod <= 0) {
    $error = 'Error: No se especificó el código de planilla (PlcCod).';
} elseif (count($pdfs) === 0) {
    $error = 'Seleccione al menos un PDF.';
} else {
    $mostrarPaso = max(0, min(count($pdfs) - 1, $paso));
    // "Omitir" no guarda la caja actual, avanza directo a la siguiente.
    if ($accion === 'omitir') {
        $mostrarPaso = max(0, min(count($pdfs) - 1, $paso + 1));
    } else {
        // Entrada desde index.php: iniciar una nueva planilla, limpiar consignaciones previas
        $_SESSION['guardadas'] = [];
    }
}

function e($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$urlBack = 'index.php?PlcCod=' . $plcCod . ($usrCod !== '' ? '&UsrCod=' . urlencode($usrCod) : '');
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

    <?php renderPasoCaja($mostrarPaso, $pdfs, $plcCod, $usrCod, $institucion, $fecha, $doctor, $paciente); ?>

    <?php endif; ?>
</div>

</body>
</html>