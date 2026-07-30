pdfjsLib.GlobalWorkerOptions.workerSrc = 'js/pdf.worker.min.js';

var referenciaImgData = null;
var logoImg = new Image();
logoImg.src = 'LOGO/logo_bioimplant_impresion.png';

document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('form-remito');
    var formSection = document.getElementById('form-section');
    var previewSection = document.getElementById('preview-section');
    var contenidoPDF = document.getElementById('contenido-pdf');
    var progressBar = document.getElementById('barra-progreso');
    var loadingEl = document.getElementById('loading');
    var infoEl = document.getElementById('detect-info');

    /* ===== Categorias collapse ===== */
    document.querySelectorAll('.categoria-header').forEach(function (header) {
        header.addEventListener('click', function () {
            var list = this.nextElementSibling;
            var icon = this.querySelector('.toggle-icon');
            list.classList.toggle('abierto');
            icon.classList.toggle('abierto');
        });
    });

    /* ===== Seleccionar todos ===== */
    document.getElementById('seleccionar-todos').addEventListener('change', function () {
        var checked = this.checked;
        document.querySelectorAll('.pdf-item input[type="checkbox"]').forEach(function (cb) { cb.checked = checked; });
    });

    /* ===== Buscador ===== */
    document.getElementById('buscar-pdf').addEventListener('input', function () {
        var q = this.value.toLowerCase().trim();
        document.querySelectorAll('.pdf-item').forEach(function (item) {
            var label = item.querySelector('label');
            var match = !q || label.textContent.toLowerCase().indexOf(q) !== -1;
            item.style.display = match ? '' : 'none';
        });
        document.querySelectorAll('.categoria').forEach(function (cat) {
            var visible = Array.from(cat.querySelectorAll('.pdf-item')).some(function (item) { return item.style.display !== 'none'; });
            cat.style.display = !q || visible ? '' : 'none';
        });
    });

    /* ===== Imprimir limpio ===== */
    document.getElementById('imprimir-limpio').addEventListener('click', function () {
        var imgs = contenidoPDF.querySelectorAll('.pagina-pdf');
        if (imgs.length === 0) { alert('No hay imágenes para imprimir.'); return; }
        var h = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Remito BIOPROT</title>';
        h += '<style>*{margin:0;padding:0;box-sizing:border-box;}body{width:210mm;margin:0 auto;}';
        h += 'img{display:block;width:100%;height:auto;vertical-align:bottom;}';
        h += '@media print{@page{margin:0;}}</style></head><body>';
        for (var i = 0; i < imgs.length; i++) h += '<img src="' + imgs[i].src + '">';
        h += '<script>window.onload=function(){window.print();window.close();}<\/script></body></html>';
        var w = window.open('', '_blank', 'width=800,height=600');
        w.document.write(h);
        w.document.close();
    });

    /* ===== Volver ===== */
    document.getElementById('volver-form').addEventListener('click', function () {
        previewSection.style.display = 'none';
        formSection.style.display = 'block';
    });

    /* ===== Guardar instrumental ===== */
    document.getElementById('guardar-instrumental').addEventListener('click', async function () {
        var btn = this;
        btn.textContent = 'Guardando...';
        btn.disabled = true;

        var rows = document.querySelectorAll('#instrumental-container .tabla-instrumental tbody tr:not(.seccion-header)');
        var items = [];
        rows.forEach(function (row) {
            var cant = parseInt(row.querySelector('.inst-cant').value, 10) || 0;
            var desc = row.querySelector('.inst-desc').value.trim();
            var chk = row.querySelector('.inst-chk').checked;
            if (desc) {
                items.push({ cantidad: cant, descripcion: desc, checked: chk });
            }
        });

        var plcCod = document.getElementById('plc-cod').value;
        var usrCod = document.getElementById('usr-cod').value;

        try {
            var resp = await fetch('includes/guardar_consignacion.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ plc_cod: plcCod, usr_cod: usrCod, items: items })
            });
            var result = await resp.json();
            if (result.ok) {
                btn.textContent = 'Guardado (N° ' + result.nco_cod + ')';
                btn.style.background = '#28a745';
            } else {
                alert('Error: ' + (result.error || 'Desconocido'));
                btn.textContent = 'Guardar Instrumental';
                btn.disabled = false;
            }
        } catch (err) {
            alert('Error de conexión: ' + err.message);
            btn.textContent = 'Guardar Instrumental';
            btn.disabled = false;
        }
    });

    /* ===== Cargar imagen de referencia ===== */
    var refImagen = document.getElementById('ref-imagen');
    if (refImagen) refImagen.addEventListener('change', function (e) {
        var file = e.target.files[0];
        if (!file) { referenciaImgData = null; document.getElementById('ref-preview').style.display = 'none'; return; }
        var reader = new FileReader();
        reader.onload = function (ev) {
            var img = new Image();
            img.onload = function () {
                var c = document.createElement('canvas');
                c.width = img.width;
                c.height = img.height;
                var ctx = c.getContext('2d');
                ctx.drawImage(img, 0, 0);
                referenciaImgData = { data: ctx.getImageData(0, 0, img.width, img.height).data, width: img.width, height: img.height };
                var pv = document.getElementById('ref-preview');
                pv.src = ev.target.result;
                pv.style.display = 'inline-block';
            };
            img.src = ev.target.result;
        };
        reader.readAsDataURL(file);
    });

    /* ===== Perfil de brillo ===== */
    function perfilBrillo(data, w, h, stepX) {
        var p = [];
        for (var y = 0; y < h; y++) {
            var s = 0, n = 0;
            for (var x = 0; x < w; x += stepX) {
                var idx = (y * w + x) * 4;
                s += 0.299 * data[idx] + 0.587 * data[idx + 1] + 0.114 * data[idx + 2];
                n++;
            }
            p.push(s / n);
        }
        return p;
    }

    function correlacion(a, b, offset) {
        var n = a.length, ma = 0, mb = 0;
        for (var i = 0; i < n; i++) { ma += a[i]; mb += b[offset + i]; }
        ma /= n; mb /= n;
        var num = 0, da = 0, db = 0;
        for (var i = 0; i < n; i++) {
            var za = a[i] - ma, zb = b[offset + i] - mb;
            num += za * zb; da += za * za; db += zb * zb;
        }
        return num / (Math.sqrt(da * db) + 1e-10);
    }

    function detectarPorReferencia(canvas) {
        if (!referenciaImgData) return null;
        var ctx = canvas.getContext('2d');
        var iw = canvas.width, ih = canvas.height;
        var rw = referenciaImgData.width, rh = referenciaImgData.height;
        if (rw > iw || rh > ih) return null;
        var pd = ctx.getImageData(0, 0, iw, ih).data;
        var rd = referenciaImgData.data;
        var refPerfil = perfilBrillo(rd, rw, rh, 1);
        var scanH = Math.min(ih, Math.floor(ih * 0.45));
        var pdfPerfil = perfilBrillo(pd, iw, scanH, 2);
        var maxY = scanH - rh;
        if (maxY < 0) return null;
        var bestY = 0, bestR = -Infinity;
        for (var y = 0; y <= maxY; y++) {
            var r = correlacion(refPerfil, pdfPerfil, y);
            if (r > bestR) { bestR = r; bestY = y; }
        }
        console.log('Ref match: r=' + bestR.toFixed(3) + ' at y=' + bestY);
        if (bestR > 0.7) return bestY;
        return null;
    }

    function detectarPorBlancos(canvas) {
        var ctx = canvas.getContext('2d');
        var w = canvas.width, h = canvas.height;
        var scanH = Math.min(h, Math.floor(h * 0.45));
        var pd = ctx.getImageData(0, 0, w, scanH).data;
        var dens = [];
        for (var y = 0; y < scanH; y++) {
            var nw = 0, t = 0;
            for (var x = 0; x < w; x += 4) {
                var idx = (y * w + x) * 4;
                if (pd[idx] < 235 || pd[idx + 1] < 235 || pd[idx + 2] < 235) nw++;
                t++;
            }
            dens.push(nw / t);
        }
        for (var y = 3; y < dens.length - 3; y++) {
            var avg = (dens[y - 3] + dens[y - 2] + dens[y - 1] + dens[y] + dens[y + 1] + dens[y + 2] + dens[y + 3]) / 7;
            if (avg < 0.015) {
                var ahead = (dens[y + 4] + dens[y + 5] + dens[y + 6] + dens[y + 7] + dens[y + 8] + dens[y + 9]) / 6;
                if (ahead > 0.08) {
                    console.log('Blancos: transicion en y=' + y);
                    return y + 17;
                }
            }
        }
        return null;
    }

    /* ===== Procesar página individual (extracto para reuso) ===== */
    async function procesarPagina(page, pdfUrl, pNum) {
        var viewport = page.getViewport({ scale: 1.5 });
        var canvas = document.createElement('canvas');
        var ctx = canvas.getContext('2d');
        canvas.width = viewport.width;
        canvas.height = viewport.height;
        await page.render({ canvasContext: ctx, viewport: viewport }).promise;

        var cropY = null, metodo = 'manual';
        if (referenciaImgData) { cropY = detectarPorReferencia(canvas); if (cropY !== null) metodo = 'referencia'; }
        if (cropY === null) { cropY = detectarPorBlancos(canvas); if (cropY !== null) metodo = 'blancos'; }
        if (cropY === null || cropY <= 0) { cropY = parseInt(document.getElementById('crop-height').value) || 800; }

        var sourceY = Math.min(cropY, canvas.height);
        var restH = canvas.height - sourceY;

        var logoH = 0;
        if (logoImg && logoImg.complete && logoImg.naturalWidth > 0) {
            logoH = Math.min(120, Math.round(canvas.width * logoImg.naturalHeight / logoImg.naturalWidth));
        }
        var compC = document.createElement('canvas');
        var compCtx = compC.getContext('2d');
        compC.width = canvas.width;
        compC.height = restH + logoH;
        if (logoH > 0) compCtx.drawImage(logoImg, 0, 0, 400, logoH);
        compCtx.drawImage(canvas, 0, sourceY, canvas.width, restH, 0, logoH, canvas.width, restH);

        return { dataUrl: compC.toDataURL('image/png'), metodo: metodo, debugCanvas: canvas, debugCropY: sourceY, restH: restH };
    }

    /* ===== Extraer tabla instrumental del PDF ===== */
    function groupTextLines(textContent) {
        var items = [].slice.call(textContent.items);
        items.sort(function (a, b) {
            var ya = Math.round(a.transform[5]);
            var yb = Math.round(b.transform[5]);
            if (yb !== ya) return yb - ya;
            return a.transform[4] - b.transform[4];
        });
        var lines = [], current = null;
        for (var i = 0; i < items.length; i++) {
            var y = Math.round(items[i].transform[5]);
            if (current === null || Math.abs(y - current.y) > 3) {
                current = { y: y, texts: [] };
                lines.push(current);
            }
            current.texts.push(items[i]);
        }
        for (var l = 0; l < lines.length; l++) {
            lines[l].texts.sort(function (a, b) { return a.transform[4] - b.transform[4]; });
            lines[l].text = lines[l].texts.map(function (t) { return t.str; }).join(' ').trim();
        }
        return lines;
    }

    var skipPrefixes = [
        'BIOPROT IMPLANTES', 'C.U.I.T.', 'Montevideo', 'E mail',
        'Remito en consignaci', 'CONDICIONES', 'Pagina', 'Página',
        'INSTITUCION', 'FECHA', 'DOCTOR', 'PACIENTE',
        'CONTROL DE INGRESO', 'CONTROL DE SALIDA', 'ACONDICIONAMIENTO',
        'REMITO', 'RECIBIDO POR', 'FIRMA', 'ACLARACION',
    ];

    function esLineaOmitir(texto) {
        var t = texto.toUpperCase();
        for (var i = 0; i < skipPrefixes.length; i++) {
            if (t.indexOf(skipPrefixes[i].toUpperCase()) === 0) return true;
        }
        return false;
    }

    async function extraerTablaPDF(pdfUrl) {
        var task = pdfjsLib.getDocument(pdfUrl);
        var pdf = await task.promise;
        var secciones = [], currentSection = null;

        for (var p = 1; p <= pdf.numPages; p++) {
            var page = await pdf.getPage(p);
            var textContent = await page.getTextContent();
            var lines = groupTextLines(textContent);

            for (var l = 0; l < lines.length; l++) {
                var line = lines[l].text;
                if (!line) continue;
                if (esLineaOmitir(line)) continue;

                var itemMatch = line.match(/^(\d+)\s+(.+)/);
                if (itemMatch) {
                    if (!currentSection) {
                        currentSection = { nombre: 'Instrumental', items: [] };
                        secciones.push(currentSection);
                    }
                    currentSection.items.push({
                        cantidad: parseInt(itemMatch[1], 10),
                        descripcion: itemMatch[2].trim()
                    });
                    continue;
                }

                if (line.length <= 5) continue;
                currentSection = { nombre: line, items: [] };
                secciones.push(currentSection);
            }
        }
        return secciones;
    }

    function generarTablaEditable(secciones) {
        var h = '<table class="tabla-instrumental">';
        h += '<thead><tr><th style="width:50px">Cant.</th><th>Descripción</th><th style="width:50px">Incluir</th></tr></thead><tbody>';
        for (var s = 0; s < secciones.length; s++) {
            h += '<tr class="seccion-header"><td colspan="3">' + secciones[s].nombre + '</td></tr>';
            for (var i = 0; i < secciones[s].items.length; i++) {
                var item = secciones[s].items[i];
                h += '<tr>';
                h += '<td><input type="number" value="' + item.cantidad + '" min="0" class="inst-cant"></td>';
                h += '<td><input type="text" value="' + item.descripcion.replace(/"/g, '&quot;') + '" class="inst-desc"></td>';
                h += '<td style="text-align:center"><input type="checkbox" checked class="inst-chk"></td>';
                h += '</tr>';
            }
        }
        h += '</tbody></table>';
        return h;
    }

    /* ===== Procesamiento principal ===== */
    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        var institucion = document.getElementById('institucion').value.trim();
        var fecha = document.getElementById('fecha').value.trim();
        var doctor = document.getElementById('doctor').value.trim();
        var paciente = document.getElementById('paciente').value.trim();

        var checkboxes = document.querySelectorAll('.pdf-item input[type="checkbox"]:checked');
        if (checkboxes.length === 0) { alert('Seleccione al menos un PDF.'); return; }

        var pdfUrls = [];
        checkboxes.forEach(function (cb) { pdfUrls.push(cb.value); });

        document.getElementById('preview-institucion').textContent = institucion || '___________________________';
        document.getElementById('preview-fecha').textContent = fecha || '__/__/____';
        document.getElementById('preview-doctor').textContent = doctor || '___________________________';
        document.getElementById('preview-paciente').textContent = paciente || '________________________________';

        formSection.style.display = 'none';
        previewSection.style.display = 'block';
        loadingEl.style.display = 'block';
        if (infoEl) infoEl.textContent = '';
        progressBar.style.width = '0%';

        var totalPages = 0;
        for (var i = 0; i < pdfUrls.length; i++) {
            try { var t = pdfjsLib.getDocument(pdfUrls[i]); var pdf = await t.promise; totalPages += pdf.numPages; } catch (e) { }
        }
        if (totalPages === 0) { loadingEl.textContent = 'No se pudieron cargar los PDFs.'; return; }

        contenidoPDF.innerHTML = '';
        var processed = 0;
        var refOk = 0, blanOk = 0, fallbackOk = 0;
        var firstDebugDone = false;

        for (var i = 0; i < pdfUrls.length; i++) {
            try {
                var task = pdfjsLib.getDocument(pdfUrls[i]);
                var pdf = await task.promise;

                for (var p = 1; p <= pdf.numPages; p++) {
                    var page = await pdf.getPage(p);
                    var result = await procesarPagina(page, pdfUrls[i], p);
                    if (result.metodo === 'referencia') refOk++;
                    else if (result.metodo === 'blancos') blanOk++;
                    else fallbackOk++;

                    if (result.restH <= 0) { processed++; continue; }

                    if (!firstDebugDone) {
                        firstDebugDone = true;
                        var dC = document.createElement('canvas');
                        dC.width = result.debugCanvas.width;
                        dC.height = result.debugCanvas.height;
                        var dctx = dC.getContext('2d');
                        dctx.drawImage(result.debugCanvas, 0, 0);
                        dctx.strokeStyle = 'red';
                        dctx.lineWidth = 3;
                        dctx.beginPath();
                        dctx.moveTo(0, result.debugCropY + 0.5);
                        dctx.lineTo(result.debugCanvas.width, result.debugCropY + 0.5);
                        dctx.stroke();
                        dctx.fillStyle = 'rgba(255,0,0,0.15)';
                        dctx.fillRect(0, 0, result.debugCanvas.width, result.debugCropY);
                        var dImg = document.createElement('img');
                        dImg.src = dC.toDataURL('image/png');
                        dImg.style.cssText = 'display:block;width:100%;height:auto;opacity:0.6;margin-bottom:2px;';
                        dImg.alt = 'Debug: corte en y=' + result.debugCropY + ' (' + result.metodo + ')';
                        contenidoPDF.appendChild(dImg);
                    }

                    var img = document.createElement('img');
                    img.src = result.dataUrl;
                    img.className = 'pagina-pdf';
                    contenidoPDF.appendChild(img);

                    processed++;
                    progressBar.style.width = ((processed / totalPages) * 100) + '%';
                }
            } catch (err) {
                console.error('Error con PDF:', pdfUrls[i], err);
                var errEl = document.createElement('p');
                errEl.style.cssText = 'color:red;padding:10px 20px;font-size:13px;';
                errEl.textContent = 'Error al procesar: ' + pdfUrls[i];
                contenidoPDF.appendChild(errEl);
            }
        }

        loadingEl.style.display = 'none';
        progressBar.style.width = '100%';
        if (infoEl) {
            var partes = [];
            if (refOk > 0) partes.push('Referencia: ' + refOk);
            if (blanOk > 0) partes.push('Blancos: ' + blanOk);
            if (fallbackOk > 0) partes.push('Manual: ' + fallbackOk);
            infoEl.textContent = 'Páginas procesadas — ' + partes.join(' | ');
        }

        /* ===== Extraer instrumental del primer PDF ===== */
        if (pdfUrls.length > 0) {
            try {
                var instData = await extraerTablaPDF(pdfUrls[0]);
                var instSection = document.getElementById('instrumental-section');
                var instContainer = document.getElementById('instrumental-container');
                if (instData.length > 0) {
                    instContainer.innerHTML = generarTablaEditable(instData);
                    instSection.style.display = 'block';
                } else {
                    instSection.style.display = 'none';
                }
            } catch (err) {
                console.error('Error extrayendo instrumental:', err);
            }
        }
    });
});
