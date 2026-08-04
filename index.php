<?php
require_once 'includes/funciones.php';
require_once 'includes/db.php';

$plcCod = isset($_GET['PlcCod']) ? (int)$_GET['PlcCod'] : (isset($_GET['plccod']) ? (int)$_GET['plccod'] : 0);
$usrCod = isset($_GET['UsrCod']) ? trim($_GET['UsrCod']) : (isset($_GET['usrcod']) ? trim($_GET['usrcod']) : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subir_pdf'])) {
    $carpeta = trim($_POST['pdf_carpeta']);
    $archivo = $_FILES['pdf_archivo'] ?? null;
    $ok = false;
    if ($archivo && $archivo['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        if ($ext === 'pdf' && $carpeta !== '') {
            $destino = rtrim(__DIR__, '\\/') . DIRECTORY_SEPARATOR . $carpeta . DIRECTORY_SEPARATOR . basename($archivo['name']);
            $dir = dirname($destino);
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            if (is_dir($dir)) $ok = move_uploaded_file($archivo['tmp_name'], $destino);
        }
    }
    $redirectPlc = $plcCod ? '&PlcCod=' . $plcCod : '';
    $redirectUsr = $usrCod !== '' ? '&UsrCod=' . urlencode($usrCod) : '';
    header('Location: index.php?upload=' . ($ok ? 'ok' : 'error') . $redirectPlc . $redirectUsr);
    exit;
}
$error = '';
$cirugia = null;



if ($plcCod <= 0) {
    $error = 'Error: No se especificó el código de planilla (PlcCod).';
} else {
    try {
        $stmt = $pdo->prepare('
            SELECT p.PlcCod, p.PlcFec, p.PlcPac, p.PlcMed, p.PlcSer,
                   m.mediconombre,
                   h.HospDesc
            FROM planillacirugia p
            LEFT JOIN medicos m ON p.PlcMed = m.cod_medico
            LEFT JOIN hospitales h ON p.PlcSer = h.HospCod
            WHERE p.PlcCod = ?
        ');
        $stmt->execute([$plcCod]);
        $cirugia = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cirugia) $error = 'Error: No se encontró la planilla con código ' . $plcCod;
    } catch (PDOException $e) {
        $error = 'Error de base de datos: ' . $e->getMessage();
    }
}

$categorias = listarPDFs(__DIR__);

$mensaje = '';
if (isset($_GET['upload'])) {
    if ($_GET['upload'] === 'ok') $mensaje = 'PDF subido correctamente.';
    else $mensaje = 'Error al subir el PDF. Verifique el archivo e intente nuevamente.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Remito en Consignación - BIOPROT</title>
<link rel="stylesheet" href="css/estilo.css">
</head>
<body>

<?php if ($error): ?>
<div class="contenedor-form">
    <div style="padding:40px;text-align:center;color:#721c24;background:#f8d7da;border:1px solid #f5c6cb;border-radius:6px;font-size:16px;">
        <?= htmlspecialchars($error) ?>
    </div>
</div>
<?php endif; ?>

<!-- ===== FORMULARIO PRINCIPAL ===== -->
<div id="form-section" class="contenedor-form"<?= $error ? ' style="display:none"' : '' ?>>

    <div class="cabecera-form">
        <img class="logo" src="LOGO/logo_bioimplant.png" alt="Logo BIOPROT">
    </div>

    <!-- ===== FORMULARIO SUBIR PDF (separado) ===== -->

    <h2 class="section-title">Agregar PDF</h2>
    <form action="index.php" method="post" enctype="multipart/form-data" style="margin-top:10px;">
        <div class="campos-form">
            <div class="campo">
                <label for="pdf-archivo">Seleccionar archivo PDF:</label>
                <input type="file" id="pdf-archivo" name="pdf_archivo" accept=".pdf" required>
            </div>
            <div class="campo">
                <label for="pdf-carpeta">Carpeta destino:</label>
                <select id="pdf-carpeta" name="pdf_carpeta" required>
                    <option value="">Seleccionar carpeta...</option>
                    <?php foreach ($categorias as $cat): ?>
                    <option value="<?= htmlspecialchars($cat['nombre']) ?>"><?= htmlspecialchars($cat['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="acciones no-print">
            <button type="submit" name="subir_pdf" class="btn btn-primario">Subir PDF</button>
        </div>
    </form>

    <form id="form-remito" action="controlar.php" method="post">

        <input type="hidden" name="plc_cod" value="<?= $plcCod ?>">
        <input type="hidden" name="usr_cod" value="<?= htmlspecialchars($usrCod) ?>">

        <h2 class="section-title">Datos de la cirugía</h2>
        <div class="campos-form">
            <div class="campo">
                <label for="institucion">INSTITUCION:</label>
                <input type="text" id="institucion" name="institucion" value="<?= htmlspecialchars($cirugia['HospDesc'] ?? '') ?>">
            </div>
            <div class="campo">
                <label for="fecha">FECHA:</label>
                <input type="date" id="fecha" name="fecha" value="<?= htmlspecialchars($cirugia['PlcFec'] ?? '') ?>">
            </div>
            <div class="campo">
                <label for="doctor">DOCTOR:</label>
                <input type="text" id="doctor" name="doctor" value="<?= htmlspecialchars($cirugia['mediconombre'] ?? '') ?>">
            </div>
            <div class="campo">
                <label for="paciente">PACIENTE:</label>
                <input type="text" id="paciente" name="paciente" value="<?= htmlspecialchars($cirugia['PlcPac'] ?? '') ?>">
            </div>
        </div>

        <h2 class="section-title">Seleccionar PDFs para adjuntar</h2>

        <div class="pdf-selector">
            <?php foreach ($categorias as $cat): ?>
            <div class="categoria">
                <div class="categoria-header">
                    <span><?= htmlspecialchars($cat['nombre']) ?></span>
                    <span class="count">(<?= count($cat['pdfs']) ?>)</span>
                </div>
                <div class="pdf-lista">
                    <?php foreach ($cat['pdfs'] as $pdf): ?>
                    <div class="pdf-item">
                        <input type="checkbox" id="pdf-<?= md5($pdf['ruta_web']) ?>" name="pdfs[]" value="<?= htmlspecialchars($pdf['ruta_web']) ?>">
                        <label for="pdf-<?= md5($pdf['ruta_web']) ?>"><?= htmlspecialchars($pdf['nombre']) ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="acciones no-print">
            <button type="submit" class="btn btn-primario">Controlar Cajas</button>
        </div>
    </form>

    <?php if ($mensaje): ?>
    <div style="margin:15px 0;padding:10px 15px;border-radius:4px;font-size:14px;background:#<?= strpos($mensaje, 'Error') === false ? 'd4edda;color:#155724' : 'f8d7da;color:#721c24' ?>;border:1px solid #<?= strpos($mensaje, 'Error') === false ? 'c3e6cb' : 'f5c6cb' ?>;">
        <?= htmlspecialchars($mensaje) ?>
    </div>
    <?php endif; ?>

</div>

</body>
</html>
