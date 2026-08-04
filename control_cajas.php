<?php
$usrCod = isset($_GET['UsrCod']) ? trim($_GET['UsrCod']) : (isset($_GET['usrcod']) ? trim($_GET['usrcod']) : '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Control de Cajas - Mover Instrumental</title>
<link rel="stylesheet" href="css/estilo.css">
</head>
<body>

<div class="contenedor-form">

    <div class="cabecera-form">
        <img class="logo" src="LOGO/logo_bioimplant.png" alt="Logo BIOPROT">
        <div class="datos-empresa" style="align-self:center;">
            <h1>Control de Cajas</h1>
            <p class="direccion">Mover instrumental de una caja a otra</p>
        </div>
    </div>

    <div class="nav-top no-print">
        <a href="index.php<?= $usrCod !== '' ? '?UsrCod=' . urlencode($usrCod) : '' ?>" class="btn btn-secundario">&larr; Volver a Remito en Consignación</a>
    </div>

    <input type="hidden" id="usr-cod" value="<?= htmlspecialchars($usrCod) ?>">

    <h2 class="section-title">Transferencia</h2>
    <div class="campos-form">
        <div class="campo">
            <label for="buscar-origen">Caja(s) origen:</label>
            <input type="text" id="buscar-origen" placeholder="Buscar caja..." autocomplete="off" style="margin-bottom:6px;">
            <div id="lista-origen" class="lista-cajas"></div>
        </div>
        <div class="campo">
            <label for="caja-destino">Caja destino:</label>
            <select id="caja-destino">
                <option value="">Seleccionar caja...</option>
            </select>
        </div>
        <div class="campo campo-ancho">
            <label for="mov-comentario">Comentario / motivo (opcional):</label>
            <input type="text" id="mov-comentario" placeholder="Ej.: faltante detectado en control, se pasa a otra caja...">
        </div>
    </div>

    <h2 class="section-title">Instrumental de la(s) caja(s) origen</h2>
    <div id="origen-vacio" class="mensaje-info">Seleccioná una o más cajas de origen para ver su instrumental.</div>
    <div id="origen-contenido" style="display:none;">
        <div style="margin-bottom:8px;">
            <label><input type="checkbox" id="sel-todos"> Seleccionar todos</label>
            <span style="margin-left:12px;color:#666;font-size:13px;">Usa la cantidad de cada ítem para mover una parte.</span>
        </div>
        <div id="tabla-origen-wrap" style="max-height:420px;overflow:auto;border:1px solid #ddd;border-radius:4px;">
            <table class="tabla-instrumental tabla-mover" id="tabla-origen">
                <thead>
                    <tr>
                        <th style="width:30px;"></th>
                        <th style="width:60px;">Cant.</th>
                        <th>Descripción</th>
                        <th style="width:150px;">Caja</th>
                        <th style="width:100px;">A mover</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <div class="acciones no-print">
        <button id="mover-btn" class="btn btn-primario" disabled>Mover Instrumental</button>
    </div>

    <div id="resultado" class="resultado" style="display:none;"></div>

    <h2 class="section-title">Últimos movimientos</h2>
    <div id="historial-wrap" style="max-height:300px;overflow:auto;border:1px solid #ddd;border-radius:4px;">
        <table class="tabla-instrumental">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Usuario</th>
                    <th>Origen</th>
                    <th>Destino</th>
                    <th>Instrumento</th>
                    <th>Cant.</th>
                    <th>Comentario</th>
                </tr>
            </thead>
            <tbody id="historial-body"><tr><td colspan="7">Cargando...</td></tr></tbody>
        </table>
    </div>

</div>

<script src="js/control_cajas.js"></script>
</body>
</html>
