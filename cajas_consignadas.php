<?php
require_once 'includes/db.php';
require_once 'includes/cajas_planilla.php';
require_once 'includes/codigo_articulo.php';

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$plcCod = (int)($_GET['plc_cod'] ?? $_POST['plc_cod'] ?? 0);
$usrCod = trim($_GET['usr'] ?? '');

$planillas = $pdo->query("
    SELECT PlcCod, PlcFec, COALESCE(PlcPac, '') AS PlcPac
    FROM planillacirugia
    ORDER BY PlcCod DESC
    LIMIT 300
")->fetchAll();

$ubicaciones = listarUbicaciones($pdo);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Asignar cajas consignadas a la CX</title>
<link rel="stylesheet" href="css/estilo.css">
<style>
.planilla-selector { display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; }
.planilla-selector .campo { min-width:220px; }
.badge-cx { display:inline-block; padding:2px 8px; border-radius:3px; font-size:12px; font-weight:bold; }
.badge-cx.s { background:#d4edda; color:#155724; }
.badge-cx.n { background:#eee; color:#888; }
.detalle-caja { border:1px solid #ddd; border-radius:4px; padding:12px; margin-bottom:12px; background:#fafafa; }
.detalle-caja h4 { margin-bottom:6px; color:#1a3a5c; }
.detalle-caja table { margin-top:6px; }
.filtro-row { display:flex; gap:10px; align-items:center; margin:12px 0; flex-wrap:wrap; }
.msg-resultado { padding:12px 15px; border-radius:4px; font-size:14px; margin:12px 0; }
.msg-ok { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
.msg-error { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
.link-caja { color:#1a73e8; text-decoration:none; cursor:pointer; }
.link-caja:hover { text-decoration:underline; }
</style>
</head>
<body>

<div class="contenedor-form">

    <div class="cabecera-form">
        <img class="logo" src="LOGO/logo_bioimplant.png" alt="Logo BIOPROT">
        <div class="datos-empresa" style="align-self:center;">
            <h1>Asignar cajas consignadas a la CX</h1>
            <p class="direccion">Seleccioná la planilla de CX y asignálas las cajas consignadas</p>
        </div>
        <a href="index.php<?= $usrCod !== '' ? '?UsrCod=' . urlencode($usrCod) : '' ?>" class="btn btn-secundario">&larr; Volver a Remito en Consignación</a>
    </div>

    <div class="planilla-selector">
        <div class="campo">
            <label for="plc_cod">Asignar a planilla de CX (opcional al filtrar):</label>
            <select id="plc_cod" class="inst-desc">
                <option value="">Sin CX (solo listar/filtrar)</option>
                <?php foreach ($planillas as $p): ?>
                <option value="<?= (int)$p['PlcCod'] ?>"<?= (int)$p['PlcCod'] === $plcCod ? ' selected' : '' ?>>
                    <?= (int)$p['PlcCod'] ?> | <?= e($p['PlcFec']) ?> | <?= e($p['PlcPac']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="filtro-row">
        <div class="campo" style="flex:1;">
            <label>Filtrar por título de la caja:</label>
            <input type="text" id="filtro-caja" class="inst-desc" placeholder="Escribí parte del título de la caja...">
        </div>
        <div class="campo">
            <label>&nbsp;</label>
            <label style="font-weight:normal;font-size:13px;">
                <input type="checkbox" id="solo-sin-asignar" checked style="width:auto;">
                Solo sin asignar
            </label>
        </div>
    </div>

    <div id="msg-asignar"></div>

    <table class="tabla-instrumental" id="tabla-cajas">
        <thead>
            <tr>
                <th style="width:40px">Sel.</th>
                <th style="width:70px">Cod.</th>
                <th>Caja</th>
                <th>Notas</th>
                <th style="width:130px">Asignada a</th>
            </tr>
        </thead>
        <tbody id="tbody-cajas"><tr><td colspan="5" style="text-align:center;color:#888;">Cargando...</td></tr></tbody>
    </table>

    <div class="filtro-row" style="border-top:1px solid #ddd;padding-top:12px;">
        <div class="campo">
            <label>Depósito para el descuento:</label>
            <select id="ubi-asignar">
                <option value="0">Usar el de cada caja</option>
                <?php foreach ($ubicaciones as $u): ?>
                <option value="<?= (int)$u['UbiCod'] ?>"><?= e($u['DepDes'] !== '' ? $u['DepDes'] . ' / ' . $u['UbiDes'] : $u['UbiDes']) ?> (Ubi:<?= (int)$u['UbiCod'] ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="acciones no-print" style="gap:10px;justify-content:flex-start;">
        <button type="button" class="btn btn-primario" onclick="asignar()" id="btn-asignar">Asignar seleccionadas a la CX y descontar stock</button>
        <button type="button" class="btn btn-secundario" onclick="verDetalle()">Ver detalle de seleccionadas</button>
        <button type="button" class="btn btn-secundario" onclick="seleccionarTodas()">Seleccionar visibles</button>
    </div>

    <div id="detalles" style="margin-top:20px;"></div>

</div>

<script>
var plcSel = document.getElementById('plc_cod');
var plc = parseInt(plcSel.value, 10) || 0;
var usr = <?= json_encode($usrCod) ?>;
var todas = [];

plcSel.addEventListener('change', function () { plc = parseInt(plcSel.value, 10) || 0; });

function cargarCajas() {
    var tbody = document.getElementById('tbody-cajas');
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#888;">Cargando...</td></tr>';
    fetch('includes/api_cajas_planilla.php?accion=listarConsignadas&plc_cod=' + plc)
        .then(function (r) { return r.json(); })
        .then(function (res) {
            todas = res.ok ? res.data : [];
            render();
        })
        .catch(function () {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#a00;">Error al cargar.</td></tr>';
        });
}

function visible() {
    var q = (document.getElementById('filtro-caja').value || '').toUpperCase();
    var solo = document.getElementById('solo-sin-asignar').checked;
    return todas.filter(function (c) {
        if (solo && (parseInt(c.PlcCod, 10) || 0) !== 0) return false;
        if (q !== '' && c.CacDes.toUpperCase().indexOf(q) === -1 && String(c.CacCod).indexOf(q) === -1) return false;
        return true;
    });
}

function render() {
    var items = visible();
    var tb = document.getElementById('tbody-cajas');
    if (items.length === 0) {
        tb.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#888;">Sin cajas consignadas que coincidan.</td></tr>';
        return;
    }
    var html = '';
    items.forEach(function (c) {
        var asignada = (parseInt(c.PlcCod, 10) || 0) !== 0;
        html += '<tr data-cac="' + c.CacCod + '" data-des="' + _esc(c.CacDes) + '" data-plc="' + (c.PlcCod || 0) + '">' +
            '<td style="text-align:center">' + (asignada ? '' : '<input type="checkbox" class="sel-caja" value="' + c.CacCod + '">') + '</td>' +
            '<td>' + c.CacCod + '</td>' +
            '<td><a href="#" onclick="verDetalleDe(' + c.CacCod + ', this); return false;" class="link-caja">' + _esc(c.CacDes) + '</a></td>' +
            '<td>' + c.Notas + '</td>' +
            '<td><span class="badge-cx ' + (asignada ? 's' : 'n') + '">' + (asignada ? 'CX ' + c.PlcCod : 'Sin asignar') + '</span></td>' +
            '</tr>';
    });
    tb.innerHTML = html;
}

document.getElementById('filtro-caja').addEventListener('input', render);
document.getElementById('solo-sin-asignar').addEventListener('change', render);

function _esc(s) {
    return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
function seleccionadas() {
    return Array.prototype.map.call(document.querySelectorAll('.sel-caja:checked'), function (cb) { return parseInt(cb.value, 10); });
}
function seleccionarTodas() {
    document.querySelectorAll('.sel-caja').forEach(function (cb) { cb.checked = true; });
}

function asignar() {
    var cajas = seleccionadas();
    var msg = document.getElementById('msg-asignar');
    var btn = document.getElementById('btn-asignar');
    if (cajas.length === 0) { msg.className = 'msg-resultado msg-error'; msg.textContent = 'Seleccioná al menos una caja sin asignar.'; return; }
    if (plc <= 0) { msg.className = 'msg-resultado msg-error'; msg.textContent = 'Seleccioná la planilla de CX para asignar.'; return; }
    btn.disabled = true;
    msg.className = 'msg-resultado'; msg.textContent = 'Asignando y descontando stock...';
    fetch('includes/api_cajas_planilla.php?accion=asignar', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            plc_cod: plc,
            cajas: cajas,
            usr: usr,
            ubi_override: parseInt(document.getElementById('ubi-asignar').value, 10) || 0
        })
    })
    .then(function (r) { return r.json(); })
    .then(function (res) {
        if (res.ok) {
            msg.className = 'msg-resultado msg-ok';
            msg.textContent = 'OK: notitas vinculadas: ' + res.asignadas_notas + ' · cajas: ' + res.cajas + ' · consumos: ' + res.descontados + '.';
        } else {
            msg.className = 'msg-resultado msg-error';
            msg.textContent = res.error || 'Error al asignar.';
        }
        cargarCajas();
    })
    .catch(function () {
        msg.className = 'msg-resultado msg-error';
        msg.textContent = 'Error de conexión.';
    })
    .finally(function () { btn.disabled = false; });
}

function verDetalle() {
    var sel = seleccionadas();
    if (sel.length === 0) { document.getElementById('detalles').innerHTML = ''; return; }
    cargarDetalles(sel);
}
function verDetalleDe(cac, el) {
    var tr = (el && el.closest) ? el.closest('tr') : null;
    var des = tr ? tr.getAttribute('data-des') : ('Caja ' + cac);
    var plcCaja = tr ? parseInt(tr.getAttribute('data-plc') || '0', 10) : 0;
    var filtro = document.getElementById('filtro-caja'),
        solo = document.getElementById('solo-sin-asignar');
    if (filtro) filtro.value = '';
    if (solo) solo.checked = false;
    render();
    var el2 = document.querySelector('#tabla-cajas tr[data-cac="' + cac + '"]');
    if (el2) el2.style.background = '#fff3cd';
    cargarDetalles([cac], el2);
}
function cargarDetalles(sel, destacaEl) {
    var cont = document.getElementById('detalles');
    cont.innerHTML = 'Cargando detalle...';
    var map = {}, pend = sel.length;
    sel.forEach(function (cac) {
        var tr = document.querySelector('#tabla-cajas tr[data-cac="' + cac + '"]');
        var des = tr ? tr.getAttribute('data-des') : ('Caja ' + cac);
        var plcCaja = tr ? (tr.getAttribute('data-plc') || 0) : 0;
        fetch('includes/api_cajas_planilla.php?accion=detalle&plc_cod=' + plcCaja + '&cac_cod=' + cac)
            .then(function (r) { return r.json(); })
            .then(function (res) {
                map[cac] = res.ok ? renderCaja(cac, des, res.data) : '<div class="detalle-caja"><h4>#' + cac + ' ' + _esc(des) + '</h4><div style="color:#a00">' + (res.error || '') + '</div></div>';
                pend--;
                if (pend === 0) {
                    var h = '';
                    sel.forEach(function (cc) { h += map[cc]; });
                    cont.innerHTML = h;
                    if (destacaEl && destacaEl.scrollIntoView) destacaEl.scrollIntoView({ block: 'center' });
                }
            })
            .catch(function () {
                map[cac] = '';
                pend--;
                if (pend === 0) {
                    var h = '';
                    sel.forEach(function (cc) { h += map[cc]; });
                    cont.innerHTML = h;
                }
            });
    });
}
function renderCaja(cac, des, d) {
    var esc = _esc;
    var impl = (d.implantes || []).map(function (i) {
        return '<tr><td>' + i.ImplCan + '</td><td>' + esc(i.ArtDes || i.ImplDsc || '') + '</td><td>' + esc(i.ImplLot || '—') + '</td><td>' + (i.ImplRep === 'S' ? 'S' : '—') + '</td></tr>';
    }).join('');
    var cons = (d.consumos || []).map(function (c) {
        return '<tr><td>' + c.EscConCod + '</td><td>' + esc(c.ArtDes || '') + ' <small>(' + esc(c.ArtCod || '') + ')</small></td><td>' + c.EscConCan + '</td><td>' + esc(c.EscConUbiCod || '') + '</td><td>' + esc(c.EscConUpdHor || '') + '</td></tr>';
    }).join('');
    var h = '<div class="detalle-caja"><h4>#' + cac + ' ' + esc(des) + '</h4>';
    h += '<strong>Implantes / materiales (' + (d.implantes || []).length + ')</strong>';
    h += '<table class="tabla-instrumental"><thead><tr><th>Cant.</th><th>Descripción</th><th>Lote</th><th>Rep.</th></tr></thead><tbody>' + (impl || '<tr><td colspan="4" style="color:#888">Sin datos</td></tr>') + '</tbody></table>';
    h += '<strong style="display:block;margin-top:10px;">Consumos / stock (' + (d.consumos || []).length + ')</strong>';
    h += '<table class="tabla-instrumental"><thead><tr><th>EscCon</th><th>Artículo</th><th>Cant.</th><th>Ubi</th><th>Fecha</th></tr></thead><tbody>' + (cons || '<tr><td colspan="5" style="color:#888">Sin consumos</td></tr>') + '</tbody></table>';
    h += '</div>';
    return h;
}

if (document.getElementById('plc_cod')) cargarCajas();
</script>

</body>
</html>