<?php
require_once 'includes/funciones.php';
require_once 'includes/db.php';

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
    header('Location: index.php?upload=' . ($ok ? 'ok' : 'error') . (isset($_GET['PlcCod']) ? '&PlcCod=' . $_GET['PlcCod'] : '') . (isset($_GET['UsrCod']) ? '&UsrCod=' . urlencode($_GET['UsrCod']) : ''));
    exit;
}

$plcCod = isset($_GET['PlcCod']) ? (int)$_GET['PlcCod'] : 0;
$usrCod = isset($_GET['UsrCod']) ? trim($_GET['UsrCod']) : '';
$error = '';
$cirugia = null;

if ($plcCod <= 0) {
    $error = 'Error: No se especificó el código de planilla (PlcCod).';
} else {
    try {
        $stmt = $pdo->prepare('
            SELECT p.PlcCod, p.PlcFec, p.PlcPac, p.PlcMed, p.Plhcod,
                   m.mediconombre,
                   h.HospDesc
            FROM planillacirugia p
            LEFT JOIN medicos m ON p.PlcMed = m.cod_medico
            LEFT JOIN hospitales h ON p.Plhcod = h.HospCod
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

    <form id="form-remito">

        <input type="hidden" id="plc-cod" value="<?= $plcCod ?>">
        <input type="hidden" id="usr-cod" value="<?= htmlspecialchars($usrCod) ?>">

        <h2 class="section-title">Datos de la cirugía</h2>
        <div class="campos-form">
            <div class="campo">
                <label for="institucion">INSTITUCION:</label>
                <input type="text" id="institucion" value="<?= htmlspecialchars($cirugia['HospDesc'] ?? '') ?>">
            </div>
            <div class="campo">
                <label for="fecha">FECHA:</label>
                <input type="date" id="fecha" value="<?= htmlspecialchars($cirugia['PlcFec'] ?? '') ?>">
            </div>
            <div class="campo">
                <label for="doctor">DOCTOR:</label>
                <input type="text" id="doctor" value="<?= htmlspecialchars($cirugia['mediconombre'] ?? '') ?>">
            </div>
            <div class="campo">
                <label for="paciente">PACIENTE:</label>
                <input type="text" id="paciente" value="<?= htmlspecialchars($cirugia['PlcPac'] ?? '') ?>">
            </div>
        </div>

        <h2 class="section-title">Ajustes</h2>
        <div class="campos-form">
            <div class="campo">
                <label for="crop-height">Píxeles a recortar (fallback manual):</label>
                <input type="number" id="crop-height" value="800" min="0" max="3000" step="50">
            </div>
            <div class="campo">
                <label for="ref-imagen">Patrón de referencia a eliminar (opcional):</label>
                <input type="file" id="ref-imagen" accept="image/png,image/jpeg">
                <img id="ref-preview" style="display:none;max-width:200px;max-height:60px;margin-top:6px;border:1px solid #ccc;">
            </div>
        </div>

        <h2 class="section-title">Seleccionar PDFs para adjuntar</h2>

        <div class="pdf-selector">
            <div style="margin-bottom:8px;font-size:13px;">
                <label><input type="checkbox" id="seleccionar-todos"> Seleccionar todos</label>
                <span style="margin-left:15px;">|</span>
                <input type="text" id="buscar-pdf" placeholder="Buscar PDF..." style="margin-left:10px;padding:4px 8px;border:1px solid #ccc;border-radius:3px;font-size:13px;width:250px;">
            </div>
            <?php foreach ($categorias as $cat): ?>
            <div class="categoria">
                <div class="categoria-header">
                    <span class="toggle-icon">&#9654;</span>
                    <span><?= htmlspecialchars($cat['nombre']) ?></span>
                    <span class="count">(<?= count($cat['pdfs']) ?>)</span>
                </div>
                <div class="pdf-lista">
                    <?php foreach ($cat['pdfs'] as $pdf): ?>
                    <div class="pdf-item">
                        <input type="checkbox" id="pdf-<?= md5($pdf['ruta_web']) ?>" value="<?= htmlspecialchars($pdf['ruta_web']) ?>">
                        <label for="pdf-<?= md5($pdf['ruta_web']) ?>"><?= htmlspecialchars($pdf['nombre']) ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="acciones no-print">
            <button type="submit" class="btn btn-primario">Generar Remito</button>
        </div>
    </form>

    <?php if ($mensaje): ?>
    <div style="margin:15px 0;padding:10px 15px;border-radius:4px;font-size:14px;background:#<?= strpos($mensaje, 'Error') === false ? 'd4edda;color:#155724' : 'f8d7da;color:#721c24' ?>;border:1px solid #<?= strpos($mensaje, 'Error') === false ? 'c3e6cb' : 'f5c6cb' ?>;">
        <?= htmlspecialchars($mensaje) ?>
    </div>
    <?php endif; ?>

</div>

<!-- ===== VISTA PREVIA ===== -->
<div id="preview-section" class="contenedor-preview" style="display:none">

    <div class="acciones no-print" style="padding:10px 20px;border-bottom:1px solid #ddd;display:flex;gap:10px;">
        <button id="volver-form" class="btn btn-secundario">Volver</button>
        <button id="imprimir-limpio" class="btn btn-primario">Imprimir solo imágenes</button>
    </div>

    <div class="preview-header">
        <img class="logo" src="LOGO/logo_bioimplant.png" alt="Logo BIOPROT">
        <div class="info-empresa">
            <h1>BIOPROT IMPLANTES DE BIOIMPLANT S.R.L.</h1>
            <p>C.U.I.T. Nº: 30-70921726-3 &mdash; Montevideo 567 (Rosario) Tel/Fax: 0341 4485178</p>
            <p>E mail: bioprotimplantes@bioprot.com.ar</p>
        </div>
    </div>

    <div class="texto-legal-preview">
        <strong>Remito en consignación.</strong> CONDICIONES DE LA CONSIGNACIÓN: se remite la siguiente mercadería en carácter de depositario asumiendo todas las emergentes del Art. 2182 del Código Civil, de los artículos siguientes y concordantes, obligándose en consecuencia a la restitución en forma inde los 15 días de la fecha de consignación, caso contrario será facturado de acuerdo a las condiciones habituales estipuladas por la Empresa.
    </div>

    <div class="datos-remito">
        <div class="campo-remito"><span class="etiqueta">INSTITUCION:</span><span id="preview-institucion">___________________________</span></div>
        <div class="campo-remito"><span class="etiqueta">FECHA:</span><span id="preview-fecha">__/__/____</span></div>
        <div class="campo-remito"><span class="etiqueta">DOCTOR:</span><span id="preview-doctor">___________________________</span></div>
        <div class="campo-remito"><span class="etiqueta">PACIENTE:</span><span id="preview-paciente">________________________________</span></div>
    </div>

    <div id="loading" class="loading" style="display:none">
        <div>Procesando PDFs, por favor espere...</div>
        <div class="barra-progreso"><div id="barra-progreso" class="progreso"></div></div>
    </div>
    <div id="detect-info" style="padding:6px 20px;font-size:12px;color:#666;"></div>

    <div id="contenido-pdf" class="contenido-pdf"></div>

    <div id="instrumental-section" class="instrumental-section" style="display:none;padding:20px;border-top:2px solid #1a3a5c;">
        <h2 class="section-title">Instrumental de la caja</h2>
        <div id="instrumental-container"></div>
        <div class="acciones no-print" style="margin-top:15px;">
            <button id="guardar-instrumental" class="btn btn-primario">Guardar Instrumental</button>
        </div>
    </div>

</div>

<script src="js/pdf.min.js"></script>
<script src="js/app.js"></script>
</body>
</html>
