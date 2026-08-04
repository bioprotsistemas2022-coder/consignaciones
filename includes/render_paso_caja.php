<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/extraer_instrumental.php';

function renderPasoCaja($paso, $pdfs, $plcCod, $usrCod, $institucion, $fecha, $doctor, $paciente, $mensajePaso = '', $guardadas = [])
{
    $total = count($pdfs);
    $mostrarPaso = max(0, min($total - 1, (int)$paso));

    $nombreActual = '';
    $errorCaja = '';
    $secciones = [];

    if ($total > 0) {
        $nombreActual = extraerNombrePdf($pdfs[$mostrarPaso]);
        $base = realpath(__DIR__ . '/..');
        $ruta = realpath($base . DIRECTORY_SEPARATOR . urldecode($pdfs[$mostrarPaso]));
        if ($ruta === false || strpos($ruta, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($ruta)) {
            $errorCaja = 'El archivo PDF no existe: ' . $pdfs[$mostrarPaso];
        } else {
            $secciones = extraerInstrumentalPdf($ruta);
            if (count($secciones) === 0) {
                $errorCaja = 'No se pudo extraer instrumental de esta caja (puede que no tenga capa de texto).';
            }
        }
    } else {
        $errorCaja = 'No se especificaron cajas.';
    }

    $e = function ($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };

    $esUltima = ($mostrarPaso >= $total - 1);
    $etiquetaGuardar = $esUltima ? 'Guardar y Terminar' : 'Guardar y Continuar';

    // Total de ítems para calcular el próximo índice al agregar filas
    $totalItems = 0;
    foreach ($secciones as $s) { $totalItems += count($s['items']); }
    ?>
    <div class="steps-nav">
        <div class="steps-progreso">
            <?php for ($i = 0; $i < $total; $i++): ?>
                <span class="step-punto<?= $i < $mostrarPaso ? ' done' : '' ?><?= $i == $mostrarPaso ? ' active' : '' ?>">
                    <?= $i + 1 ?>
                </span>
            <?php endfor; ?>
        </div>
        <div class="steps-titulo">
            Caja <strong><?= $mostrarPaso + 1 ?> de <?= $total ?></strong>
            <?php if ($nombreActual !== ''): ?><span class="steps-caja">&mdash; <?= $e($nombreActual) ?></span><?php endif; ?>
        </div>
    </div>

    <?php if ($mensajePaso !== ''): ?>
    <div style="margin:10px 0;padding:10px 15px;border-radius:4px;font-size:14px;background:#d4edda;color:#155724;border:1px solid #c3e6cb;">
        <?= $mensajePaso ?>
    </div>
    <?php endif; ?>

    <?php if ($errorCaja !== ''): ?>
    <div style="padding:15px;color:#721c24;background:#f8d7da;border:1px solid #f5c6cb;border-radius:4px;font-size:14px;margin-bottom:15px;">
        <?= $e($errorCaja) ?>
    </div>
    <?php endif; ?>

    <form action="guardar.php" method="post">
        <input type="hidden" name="plc_cod" value="<?= (int)$plcCod ?>">
        <input type="hidden" name="usr_cod" value="<?= $e($usrCod) ?>">
        <input type="hidden" name="institucion" value="<?= $e($institucion) ?>">
        <input type="hidden" name="fecha" value="<?= $e($fecha) ?>">
        <input type="hidden" name="doctor" value="<?= $e($doctor) ?>">
        <input type="hidden" name="paciente" value="<?= $e($paciente) ?>">
        <input type="hidden" name="paso" value="<?= (int)$mostrarPaso ?>">
        <input type="hidden" name="accion" value="guardar">
        <?php foreach ($pdfs as $i => $rutaWeb): ?>
        <input type="hidden" name="pdfs[]" value="<?= $e($rutaWeb) ?>">
        <?php endforeach; ?>
        <?php if (!empty($guardadas)): ?>
        <input type="hidden" name="guardadas_previas" value="<?= $e(json_encode($guardadas)) ?>">
        <?php endif; ?>

        <h2 class="section-title">Datos de la cirugía</h2>
        <div class="campos-form">
            <div class="campo"><label>INSTITUCION:</label><span><?= $e($institucion) ?: '________' ?></span></div>
            <div class="campo"><label>FECHA:</label><span><?= $e($fecha) ?></span></div>
            <div class="campo"><label>DOCTOR:</label><span><?= $e($doctor) ?: '________' ?></span></div>
            <div class="campo"><label>PACIENTE:</label><span><?= $e($paciente) ?: '________' ?></span></div>
        </div>

        <h2 class="section-title">Instrumental de la caja
            <span style="font-weight:normal;color:#888;font-size:12px;">(<?= count($secciones) ?> secciones, <?= $totalItems ?> ítems)</span>
        </h2>

        <table class="tabla-instrumental" id="tabla-instrumental">
            <thead>
                <tr>
                    <th style="width:50px">Cant.</th>
                    <th>Descripción</th>
                    <th style="width:70px">Incluir</th>
                    <th style="width:40px"></th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($secciones) === 0): ?>
                <tr id="fila-vacia">
                    <td colspan="4" style="text-align:center;color:#666;padding:20px;">
                        No hay ítems para mostrar en esta caja. Podés agregarlos con el botón "Agregar ítem".
                    </td>
                </tr>
                <?php else: ?>
                <?php $idx = 0; foreach ($secciones as $s): ?>
                <tr class="seccion-header">
                    <td colspan="4"><?= $e($s['nombre']) ?></td>
                </tr>
                <?php foreach ($s['items'] as $item): ?>
                <tr>
                    <td><input type="number" name="cant[<?= $idx ?>]" value="<?= (int)$item['cantidad'] ?>" min="0" class="inst-cant"></td>
                    <td><input type="text" name="desc[<?= $idx ?>]" value="<?= $e($item['descripcion']) ?>" class="inst-desc"></td>
                    <td style="text-align:center"><input type="checkbox" name="chk[<?= $idx ?>]" value="S" class="inst-chk"></td>
                    <td style="text-align:center"><button type="button" class="btn-eliminar-item" onclick="eliminarFila(this)" title="Eliminar ítem">&times;</button></td>
                </tr>
                <?php $idx++; endforeach; ?>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <button type="button" class="btn-agregar-item" onclick="agregarItem()">+ Agregar ítem</button>

        <script>
        var proximoIdx = <?= (int)$totalItems ?>;
        function agregarItem() {
            var tbody = document.querySelector('#tabla-instrumental tbody');
            var filaVacia = document.getElementById('fila-vacia');
            if (filaVacia) filaVacia.remove();
            var tr = document.createElement('tr');
            tr.innerHTML =
                '<td><input type="number" name="cant[' + proximoIdx + ']" value="1" min="0" class="inst-cant"></td>' +
                '<td><input type="text" name="desc[' + proximoIdx + ']" value="" class="inst-desc" placeholder="Descripción del ítem"></td>' +
                '<td style="text-align:center"><input type="checkbox" name="chk[' + proximoIdx + ']" value="S" class="inst-chk"></td>' +
                '<td style="text-align:center"><button type="button" class="btn-eliminar-item" onclick="eliminarFila(this)" title="Eliminar ítem">&times;</button></td>';
            tbody.appendChild(tr);
            proximoIdx++;
        }
        function eliminarFila(btn) {
            var tr = btn.closest('tr');
            if (tr) tr.remove();
        }
        </script>

        <div class="acciones no-print" style="gap:10px;">
            <button type="submit" class="btn btn-primario"><?= $etiquetaGuardar ?></button>
            <?php if (!$esUltima): ?>
            <button type="submit" name="accion" value="omitir" formaction="controlar.php" formnovalidate class="btn btn-secundario">Omitir y Continuar</button>
            <?php endif; ?>
        </div>
    </form>
    <?php
}