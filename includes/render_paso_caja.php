<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/extraer_instrumental.php';
require_once __DIR__ . '/codigo_articulo.php';
require_once __DIR__ . '/cajas_planilla.php';

function renderPasoCaja($paso, $pdfs, $plcCod, $usrCod, $institucion, $fecha, $doctor, $paciente, $mensajePaso = '', $guardadas = [])
{
    global $pdo;
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

    $ubicaciones = listarUbicaciones($pdo);
    $ubiDefecto = 1;

    $cajasOpciones = cajasGlobal($pdo);
    $cajaDefault = isset($cajasOpciones[0]) ? (int)$cajasOpciones[0]['CacCod'] : 0;
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
            <div class="campo">
                <label for="caja-search">Caja de este remito:</label>
                <div class="caja-picker">
                    <input type="text" id="caja-search" class="inst-desc" placeholder="Buscar caja por descripción o código..." autocomplete="off">
                    <input type="hidden" name="nco_cac" id="nco_cac" value="<?= (int)$cajaDefault ?>">
                    <div id="caja-options" class="caja-options"></div>
                </div>
            </div>
        </div>

        <script>
        var cajasDisponibles = <?= json_encode(array_map(function ($c) { return ['cod' => (int)$c['CacCod'], 'des' => $c['CacDes']]; }, $cajasOpciones)) ?>;
        var cajaSearch = document.getElementById('caja-search');
        var cajaOptions = document.getElementById('caja-options');
        var ncoCac = document.getElementById('nco_cac');

        function resolverCajaPorCod(des) {
            for (var i = 0; i < cajasDisponibles.length; i++) {
                if (String(cajasDisponibles[i].cod) === String(des)) return cajasDisponibles[i];
            }
            return null;
        }
        function mostrarCajas(filtro) {
            filtro = filtro.toUpperCase();
            var match = cajasDisponibles.filter(function (c) {
                return c.des.toUpperCase().indexOf(filtro) !== -1 || String(c.cod).indexOf(filtro) !== -1;
            });
            if (match.length === 0) {
                cajaOptions.innerHTML = '<div class="caja-option noresult">Sin coincidencias</div>';
                return;
            }
            var html = '';
            match.slice(0, 50).forEach(function (c) {
                html += '<div class="caja-option" data-cod="' + c.cod + '"><strong>' + c.cod + '</strong> - ' + c.des + '</div>';
            });
            cajaOptions.innerHTML = html;
            cajaOptions.querySelectorAll('.caja-option:not(.noresult)').forEach(function (o) {
                o.addEventListener('click', function () {
                    ncoCac.value = o.getAttribute('data-cod');
                    var origen = resolverCajaPorCod(o.getAttribute('data-cod'));
                    cajaSearch.value = (origen ? origen.cod + ' - ' + origen.des : o.textContent);
                    cajaOptions.innerHTML = '';
                });
            });
        }
        cajaSearch.addEventListener('input', function () {
            var v = cajaSearch.value.trim();
            if (v === '') { cajaOptions.innerHTML = ''; return; }
            mostrarCajas(v);
        });
        cajaSearch.addEventListener('focus', function () {
            if (cajaSearch.value.trim() !== '') mostrarCajas(cajaSearch.value.trim());
        });
        document.addEventListener('click', function (ev) {
            if (!ev.target.closest('.caja-picker')) cajaOptions.innerHTML = '';
        });
        // preseleccion: mostrar la caja por defecto como texto
        if (ncoCac.value !== '0') {
            var pre = resolverCajaPorCod(ncoCac.value);
            if (pre) cajaSearch.value = pre.cod + ' - ' + pre.des;
        }
        </script>

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

        <h2 class="section-title">Materiales / Implantes de la caja
            <span style="font-weight:normal;color:#888;font-size:12px;">(escanéá o cargá los implantes)</span>
        </h2>

        <div class="scan-box">
            <input type="text" id="scan-codigo" class="scan-input" placeholder="Escaneá el código (GTIN / GS1-128 / QR) y presioná Enter..." autocomplete="off">
            <div id="scan-msg" class="scan-msg"></div>
        </div>

        <table class="tabla-instrumental" id="tabla-implantes">
            <thead>
                <tr>
                    <th style="width:60px">Cant.</th>
                    <th>Descripción</th>
                    <th style="width:70px">Reposición</th>
                    <th style="width:50px"></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>

        <button type="button" class="btn-agregar-item" onclick="agregarImplanteManual()">+ Agregar implante manualmente</button>

        <div class="campo" style="margin-top:12px;">
            <label>Depósito (UbiCod):</label>
            <select id="deposito-caja" class="inst-desc" style="max-width:300px;">
                <?php foreach ($ubicaciones as $u): ?>
                <option value="<?= (int)$u['UbiCod'] ?>"<?= (int)$u['UbiCod'] === $ubiDefecto ? ' selected' : '' ?>>
                    <?= $e($u['DepDes'] !== '' ? $u['DepDes'] . ' / ' . $u['UbiDes'] : $u['UbiDes']) ?> (Ubi:<?= (int)$u['UbiCod'] ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <script>
        var implIdx = 0;
        var scanInput = document.getElementById('scan-codigo');
        scanInput.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter') {
                ev.preventDefault();
                var codigo = scanInput.value.trim();
                if (codigo === '') return;
                escanearCodigo(codigo);
            }
        });

        function escanearCodigo(codigo) {
            var msg = document.getElementById('scan-msg');
            msg.textContent = 'Buscando...';
            msg.className = 'scan-msg';
            fetch('includes/api_codigo.php?accion=buscar&codigo=' + encodeURIComponent(codigo))
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.ok) {
                        msg.textContent = 'Agregado: ' + res.articulo.descripcion;
                        msg.className = 'scan-msg ok';
                        agregarImplante(res.articulo);
                    } else {
                        msg.textContent = res.error || 'No se encontró el artículo.';
                        msg.className = 'scan-msg error';
                    }
                })
                .catch(function () {
                    msg.textContent = 'Error de conexión al buscar el código.';
                    msg.className = 'scan-msg error';
                })
                .finally(function () { scanInput.value = ''; scanInput.focus(); });
        }

        function agregarImplante(a) {
            var tbody = document.querySelector('#tabla-implantes tbody');
            var tr = document.createElement('tr');
            var ubi = (document.getElementById('deposito-caja') || {}).value || '1';
            var hidden =
                '<input type="hidden" name="impl_art_id[]" value="' + (a.art_id || 0) + '">' +
                '<input type="hidden" name="impl_art_mat_id[]" value="' + (a.art_mat_id || '') + '">' +
                '<input type="hidden" name="impl_gtin[]" value="' + _esc(a.gtin || '') + '">' +
                '<input type="hidden" name="impl_lot[]" value="' + _esc(a.lote || '') + '">' +
                '<input type="hidden" name="impl_ven[]" value="' + _esc(a.vencimiento || '') + '">' +
                '<input type="hidden" name="impl_ser[]" value="' + _esc(a.serie || '') + '">' +
                '<input type="hidden" name="impl_ubi[]" value="' + _esc(ubi) + '">';
            tr.innerHTML =
                '<td><input type="number" name="impl_can[]" value="1" min="1" class="inst-cant"></td>' +
                '<td>' + hidden +
                    '<input type="hidden" name="impl_dsc[]" value="' + _esc(a.descripcion || '') + '">' +
                    '<div class="impl-desc">' + _esc(a.descripcion || '') + '</div>' +
                    '<div class="impl-det">' + (a.lote ? 'Lote: ' + _esc(a.lote) : '') + (a.serie ? ' | Serie: ' + _esc(a.serie) : '') + '</div>' +
                '</td>' +
                '<td style="text-align:center"><input type="checkbox" name="impl_rep[]" value="S" class="inst-chk"></td>' +
                '<td style="text-align:center"><button type="button" class="btn-eliminar-item" onclick="eliminarFila(this)" title="Eliminar">&times;</button></td>';
            tbody.appendChild(tr);
            implIdx++;
        }

        function agregarImplanteManual() {
            var tbody = document.querySelector('#tabla-implantes tbody');
            var tr = document.createElement('tr');
            var hidden =
                '<input type="hidden" name="impl_art_id[]" value="0">' +
                '<input type="hidden" name="impl_art_mat_id[]" value="">' +
                '<input type="hidden" name="impl_gtin[]" value="">' +
                '<input type="hidden" name="impl_lot[]" value="">' +
                '<input type="hidden" name="impl_ven[]" value="">' +
                '<input type="hidden" name="impl_ser[]" value="">' +
                '<input type="hidden" name="impl_ubi[]" value="' + _esc((document.getElementById('deposito-caja') || {}).value || '1') + '">';
            tr.innerHTML =
                '<td><input type="number" name="impl_can[]" value="1" min="1" class="inst-cant"></td>' +
                '<td>' + hidden + '<input type="text" name="impl_dsc[]" value="" class="inst-desc" placeholder="Descripción del implante"></td>' +
                '<td style="text-align:center"><input type="checkbox" name="impl_rep[]" value="S" class="inst-chk"></td>' +
                '<td style="text-align:center"><button type="button" class="btn-eliminar-item" onclick="eliminarFila(this)" title="Eliminar">&times;</button></td>';
            tbody.appendChild(tr);
            implIdx++;
        }

        function _esc(s) {
            return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
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