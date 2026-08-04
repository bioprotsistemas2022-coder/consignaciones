(function () {
    'use strict';

    var apiBase = 'includes/api_cajas.php';
    var usrCod = document.getElementById('usr-cod').value;

    var selDestino = document.getElementById('caja-destino');
    var listaOrigen = document.getElementById('lista-origen');
    var buscarOrigen = document.getElementById('buscar-origen');
    var contenidoWrap = document.getElementById('origen-contenido');
    var origenVacio = document.getElementById('origen-vacio');
    var tbody = document.querySelector('#tabla-origen tbody');
    var moverBtn = document.getElementById('mover-btn');
    var selTodos = document.getElementById('sel-todos');
    var resultado = document.getElementById('resultado');

    var cajas = [];
    var mapaCajas = {};
    var seleccionadas = {};
    var contenidoActual = [];

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    async function fetchJson(url, opts) {
        var res = await fetch(url, opts);
        return res.json();
    }

    function llenarSelect(select, lista, incluirVacio) {
        select.innerHTML = '';
        if (incluirVacio) {
            var opt = document.createElement('option');
            opt.value = '';
            opt.textContent = 'Seleccionar caja...';
            select.appendChild(opt);
        }
        lista.forEach(function (c) {
            var o = document.createElement('option');
            o.value = c.CacCod;
            o.textContent = c.CacDes + ' (' + c.items + ' ítems)';
            select.appendChild(o);
        });
        if (incluirVacio) {
            var sp = document.createElement('option');
            sp.value = '0';
            sp.textContent = '(Sin caja / depósito)';
            select.appendChild(sp);
        }
    }

    function cajasOrigenSeleccionadas() {
        return Object.keys(seleccionadas).map(Number).filter(function (v) { return v > 0; });
    }

    function renderListaOrigen() {
        var filtro = buscarOrigen.value.trim().toLowerCase();
        var visibles = cajas.filter(function (c) {
            return !filtro || (c.CacDes || '').toLowerCase().indexOf(filtro) !== -1;
        });
        if (!visibles.length) {
            listaOrigen.innerHTML = '<div class="mensaje-info" style="border:none;padding:10px;">Sin resultados</div>';
            return;
        }
        listaOrigen.innerHTML = visibles.map(function (c) {
            var checked = seleccionadas[c.CacCod] ? ' checked' : '';
            return '<label class="caja-item"><input type="checkbox" class="caja-chk" data-cac="' + c.CacCod + '"' + checked + '> ' +
                esc(c.CacDes) + ' <span class="cnt-chk">(' + c.items + ')</span></label>';
        }).join('');
    }

    function registrarSeleccion() {
        seleccionadas = {};
        listaOrigen.querySelectorAll('.caja-chk:checked').forEach(function (cb) {
            seleccionadas[parseInt(cb.dataset.cac, 10)] = true;
        });
    }

    function formatearFecha(f) {
        if (!f) return '';
        var d = new Date(f);
        if (isNaN(d)) return f;
        return d.getDate() + '/' + (d.getMonth() + 1) + '/' + d.getFullYear() + ' ' +
            ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2);
    }

    function cargarCajas() {
        fetchJson(apiBase + '?accion=cajas').then(function (r) {
            if (!r.ok) { alert('Error al cargar cajas: ' + (r.error || '')); return; }
            cajas = r.data || [];
            cajas.forEach(function (c) { mapaCajas[c.CacCod] = c.CacDes; });
            llenarSelect(selDestino, cajas, true);
            renderListaOrigen();
        });
    }

    function cargarContenido() {
        var seleccionadas = cajasOrigenSeleccionadas();
        selTodos.checked = false;
        if (!seleccionadas.length) {
            contenidoActual = [];
            tbody.innerHTML = '';
            contenidoWrap.style.display = 'none';
            origenVacio.style.display = 'block';
            actualizarEstadoBoton();
            return;
        }
        contenidoWrap.style.display = 'none';
        origenVacio.style.display = 'block';
        tbody.innerHTML = '<tr><td colspan="5">Cargando...</td></tr>';

        Promise.all(seleccionadas.map(function (cac) {
            return fetchJson(apiBase + '?accion=contenido&cac=' + cac);
        })).then(function (resps) {
            var todos = [];
            resps.forEach(function (r) {
                if (r.ok) {
                    (r.data || []).forEach(function (it) {
                        it.CajaNombre = mapaCajas[it.CacCod] || 'Caja ' + it.CacCod;
                        todos.push(it);
                    });
                }
            });
            todos.sort(function (a, b) {
                var n = a.CajaNombre.localeCompare(b.CajaNombre);
                if (n !== 0) return n;
                return (a.CacIntDes || '').localeCompare(b.CacIntDes || '');
            });
            contenidoActual = todos;
            renderContenido(todos);
        });
    }

    function renderContenido(lista) {
        if (!lista.length) {
            tbody.innerHTML = '<tr><td colspan="5">Las cajas seleccionadas no tienen instrumental.</td></tr>';
            return;
        }
        tbody.innerHTML = lista.map(function (it) {
            var desc = it.CacIntDes || '(sin descripción)';
            var checked = it.CacIntCan > 0 ? '' : ' disabled';
            return '<tr data-cac="' + it.CacCod + '">' +
                '<td style="text-align:center"><input type="checkbox" class="it-chk" data-id="' + it.CacIntCod + '"' + checked + '></td>' +
                '<td style="text-align:center">' + it.CacIntCan + '</td>' +
                '<td>' + esc(desc) + '</td>' +
                '<td>' + esc(it.CajaNombre) + '</td>' +
                '<td><input type="number" class="it-cant" data-id="' + it.CacIntCod + '" min="1" max="' + it.CacIntCan + '" value="' + it.CacIntCan + '" placeholder="0" disabled></td>' +
                '</tr>';
        }).join('');
        origenVacio.style.display = 'none';
        contenidoWrap.style.display = 'block';
        actualizarEstadoBoton();
    }

    function cantidadTotal() {
        var total = 0;
        tbody.querySelectorAll('.it-chk:checked').forEach(function (cb) {
            var cant = parseInt(cb.closest('tr').querySelector('.it-cant').value, 10) || 0;
            total += cant;
        });
        return total;
    }

    function actualizarEstadoBoton() {
        var destino = parseInt(selDestino.value, 10) || -1;
        moverBtn.disabled = !(cajasOrigenSeleccionadas().length > 0 && destino >= 0 && cantidadTotal() > 0);
    }

    // === EVENTOS ===
    buscarOrigen.addEventListener('input', renderListaOrigen);

    listaOrigen.addEventListener('change', function (e) {
        if (e.target.classList.contains('caja-chk')) {
            registrarSeleccion();
            cargarContenido();
        }
    });

    selDestino.addEventListener('change', actualizarEstadoBoton);

    selTodos.addEventListener('change', function () {
        tbody.querySelectorAll('.it-chk:not(:disabled)').forEach(function (cb) {
            cb.checked = selTodos.checked;
            cb.closest('tr').querySelector('.it-cant').disabled = !selTodos.checked;
        });
        actualizarEstadoBoton();
    });

    tbody.addEventListener('change', function (e) {
        if (e.target.classList.contains('it-chk')) {
            var row = e.target.closest('tr');
            row.querySelector('.it-cant').disabled = !e.target.checked;
            actualizarEstadoBoton();
        }
    });

    tbody.addEventListener('input', actualizarEstadoBoton);

    moverBtn.addEventListener('click', async function () {
        var destino = parseInt(selDestino.value, 10);
        var origenes = cajasOrigenSeleccionadas();
        if (!origenes.length || destino < 0) { alert('Seleccioná caja(s) origen y caja destino.'); return; }
        if (origenes.indexOf(destino) !== -1) { alert('La caja destino no puede ser una caja origen.'); return; }

        var items = [];
        tbody.querySelectorAll('.it-chk:checked').forEach(function (cb) {
            var tr = cb.closest('tr');
            var cant = parseInt(tr.querySelector('.it-cant').value, 10) || 0;
            if (cant > 0) items.push({ cac_cod: parseInt(tr.dataset.cac, 10), cac_int_cod: parseInt(cb.dataset.id, 10), cantidad: cant });
        });
        if (!items.length) { alert('Seleccioná al menos un instrumento.'); return; }

        if (!confirm('¿Confirmás mover ' + cantidadTotal() + ' unidades a la caja destino?')) return;

        moverBtn.disabled = true;
        moverBtn.textContent = 'Moviendo...';
        try {
            var r = await fetchJson(apiBase + '?accion=mover', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    destino: destino,
                    usr: usrCod,
                    comentario: document.getElementById('mov-comentario').value.trim(),
                    items: items
                })
            });
            if (r.ok) {
                resultado.style.display = 'block';
                resultado.innerHTML = '<div style="color:#155724;background:#d4edda;border:1px solid #c3e6cb;padding:12px 15px;border-radius:4px;">' +
                    'Se movieron <strong>' + r.movidos + '</strong> unidades correctamente.' +
                    (r.errores && r.errores.length ? '<br><span style="color:#856404;">' + esc(r.errores.join('<br>')) + '</span>' : '') +
                    '</div>';
                cargarContenido();       // recargar las cajas origen (queda el resto)
                cargarHistorial();       // refrescar historial
                selDestino.value = '';
                document.getElementById('mov-comentario').value = '';
                actualizarEstadoBoton();
            } else {
                alert('Error: ' + (r.error || 'Desconocido'));
            }
        } catch (err) {
            alert('Error de conexión: ' + err.message);
        } finally {
            moverBtn.disabled = false;
            moverBtn.textContent = 'Mover Instrumental';
        }
    });

    function cargarHistorial() {
        var hb = document.getElementById('historial-body');
        hb.innerHTML = '<tr><td colspan="7">Cargando...</td></tr>';
        fetchJson(apiBase + '?accion=movimientos&limite=20').then(function (r) {
            if (!r.ok) { hb.innerHTML = '<tr><td colspan="7">No se pudo cargar el historial.</td></tr>'; return; }
            var lista = r.data || [];
            if (!lista.length) { hb.innerHTML = '<tr><td colspan="7">Sin movimientos registrados.</td></tr>'; return; }
            hb.innerHTML = lista.map(function (m) {
                var coment = m.MovCom ? esc(m.MovCom) : '';
                return '<tr>' +
                    '<td>' + formatearFecha(m.MovFec) + '</td>' +
                    '<td>' + esc(m.MovUsr) + '</td>' +
                    '<td>' + esc(m.OriDes || m.MovCacOri) + '</td>' +
                    '<td>' + esc(m.DesDes || m.MovCacDes) + '</td>' +
                    '<td>' + esc(m.MovIntDes) + '</td>' +
                    '<td style="text-align:center">' + m.MovCan + '</td>' +
                    '<td>' + coment + '</td>' +
                    '</tr>';
            }).join('');
        });
    }

    cargarCajas();
    cargarHistorial();
})();