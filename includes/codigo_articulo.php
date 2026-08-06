<?php
require_once __DIR__ . '/db.php';

/**
 * Devuelve la configuracion de codigos de barras por defecto (proveedor_codigobarras).
 * Si no existe BrcDefault=1 devuelve null (se usara el parser GS1-128 estandar).
 */
function configuracionCodigoBarras(PDO $pdo)
{
    $stmt = $pdo->query("SELECT * FROM proveedor_codigobarras WHERE BrcDefault = 1 LIMIT 1");
    $cfg = $stmt->fetch();
    return $cfg ?: null;
}

/**
 * Extrae un tramo fijo por posicion/longitud desde una config Custom (proveedor_codigobarras).
 */
function _extraerTramo($s, $pos, $len)
{
    $pos = (int)$pos;
    $len = (int)$len;
    if ($pos <= 0 || $len <= 0) return null;
    return substr($s, $pos, $len) ?: null;
}

/**
 * Longitudes fijas de los AI mas comunes de GS1-128 (0 => variable hasta fin / proximo AI).
 */
function _gs1LongitudAI($ai)
{
    $fijo = [
        '00' => 14, '01' => 14, '02' => 14, '03' => 14, '04' => 14,
        '05' => 14, '06' => 14, '07' => 14, '08' => 14, '09' => 14,
        '11' => 6, '12' => 6, '13' => 6, '14' => 6, '15' => 6, '16' => 6, '17' => 6, '18' => 6, '19' => 6,
        '20' => 2,
    ];
    return isset($fijo[$ai]) ? $fijo[$ai] : 0;
}

/**
 * Parsea un codigo GS1-128/DataMatrix/QR.
 * Soporta: parser GS1 por AIs (01 GTIN, 10 lote, 17 vencimiento, 21 serial),
 * extractos por offset configurados en proveedor_codigobarras, y GTIN puro.
 */
function parseCodigo($raw, $config = null, $soloGtinComo = 14)
{
    $out = ['gtin' => null, 'lote' => null, 'ven' => null, 'ser' => null];
    $s = trim((string)$raw);
    $s = preg_replace('/[\x{001d}\x{001e}\x{001f}]/u', '', $s); // separadores GS1
    if ($s === '') return $out;

    // 1) Config Custom con offsets fijos
    if ($config && ($config['BrcGtinPos'] ?? 0) > 0) {
        $gtin = _extraerTramo($s, $config['BrcGtinPos'], $config['BrcGtinLen']);
        if ($gtin) $out['gtin'] = $gtin;
        $out['lote'] = _extraerTramo($s, $config['BrcLotPos'], $config['BrcLotLen']);
        $out['ven']  = _extraerTramo($s, $config['BrcVenPos'], $config['BrcVenLen']);
        $out['ser']  = _extraerTramo($s, $config['BrcSerPos'], $config['BrcSerLen']);
        if ($out['gtin']) return $out;
    }

    // 2) Parser GS1-128 por AIs (formato parentizado (01)... o concatenado)
    $parsed = _parseGS1128($s);
    if ($parsed['gtin']) return $parsed;

    // 3) GTIN puro (13 o 14 digitos)
    if (preg_match('/^(\d{13}|\d{14})$/', $s, $m)) {
        $out['gtin'] = $m[1];
    }
    return $out;
}

function _parseGS1128($s)
{
    $out = ['gtin' => null, 'lote' => null, 'ven' => null, 'ser' => null];

    // Formato parentizado: (01)GTIN(17)DT(10)LOTE... -> cada AI va entre parentesis
    if (strpos($s, '(') !== false && preg_match_all('/\((\d{2,4})\)/', $s, $m, PREG_OFFSET_CAPTURE)) {
        $aies = $m[1];
        $posiciones = array_column($m[0], 1);
        $count = count($aies);
        foreach ($aies as $i => $aic) {
            $ai = $aic[0];
            $inicioValor = $posiciones[$i] + strlen($m[0][$i][0]);
            $finValor = ($i + 1 < $count) ? $posiciones[$i + 1] : strlen($s);
            $valor = trim(substr($s, $inicioValor, $finValor - $inicioValor));
            if (!preg_match('/^\d{2}/', $ai)) continue;
            switch (substr($ai, 0, 2)) {
                case '01': $out['gtin'] = $valor; break;
                case '10': $out['lote'] = $valor; break;
                case '17': $out['ven'] = _gs1FechaYMD($valor); break;
                case '21': $out['ser'] = $valor; break;
            }
        }
        return $out;
    }

    // Formato concatenado (sin parentesis, con/sin separador GS - ya removido arriba)
    $len = strlen($s);
    $i = 0;
    while ($i < $len) {
        if ($i + 2 > $len) break;
        $ai = substr($s, $i, 2);
        $largo = _gs1LongitudAI($ai);
        $valorInicio = $i + 2;
        if ($largo > 0) {
            if ($valorInicio + $largo > $len) break;
            $valor = substr($s, $valorInicio, $largo);
            $i = $valorInicio + $largo;
        } else {
            $valor = substr($s, $valorInicio);
            $i = $len;
        }
        if ($valor !== '') {
            switch ($ai) {
                case '01': $out['gtin'] = $valor; break;
                case '10': $out['lote'] = $valor; break;
                case '17': $out['ven'] = _gs1FechaYMD($valor); break;
                case '21': $out['ser'] = $valor; break;
            }
        }
    }
    return $out;
}

function _gs1FechaYMD($yyMMdd)
{
    if (!preg_match('/^(\d{2})(\d{2})(\d{2})$/', $yyMMdd, $m)) return null;
    $yy = (int)$m[1];
    $year = $yy <= 80 ? 2000 + $yy : 1900 + $yy;
    return sprintf('%04d-%02d-%02d', $year, (int)$m[2], (int)$m[3]);
}

/**
 * Resuelve un GTIN a un articulo (articulosmateriales -> articulos).
 * Devuelve los datos del material que coincida con el GTIN; de haber varios
 * (mismos GTIN serializados), prioriza el que mejor coincida con lote/serial.
 */
function resolverArticuloPorGtin(PDO $pdo, $gtin, $lote = null, $ser = null)
{
    if ($gtin === null || trim((string)$gtin) === '') return null;
    $stmt = $pdo->prepare("
        SELECT am.ArtMatId, am.ArtCod, am.ArtMatGtin, am.ArtMatLot, am.ArtMatVen,
               am.ArtMatSer, am.ArtMatDes, am.ArtMatFecIng,
               a.ArtId, a.ArtDes
        FROM articulosmateriales am
        INNER JOIN articulos a ON a.ArtCod = am.ArtCod
        WHERE am.ArtMatGtin = ?
        ORDER BY am.ArtMatFecIng ASC, am.ArtMatId ASC
    ");
    $stmt->execute([trim((string)$gtin)]);
    $opciones = $stmt->fetchAll();
    if (count($opciones) === 0) return null;
    if (count($opciones) === 1) return $opciones[0];

    // intentar match exacto por serial y/o lote
    foreach ($opciones as $o) {
        if ($ser !== null && $ser !== '' && strcasecmp((string)$o['ArtMatSer'], (string)$ser) === 0) return $o;
    }
    foreach ($opciones as $o) {
        if ($lote !== null && $lote !== '' && strcasecmp((string)$o['ArtMatLot'], (string)$lote) === 0) return $o;
    }
    return $opciones[0];
}

/**
 * Resuelve una descripcion libre (por si no se pudo decodificar el GTIN).
 */
function resolverArticuloPorDescripcion(PDO $pdo, $texto)
{
    $stmt = $pdo->prepare("
        SELECT a.ArtId, a.ArtCod, a.ArtDes
        FROM articulos a
        WHERE a.ArtAct = 'S' AND a.ArtDes LIKE ?
        ORDER BY a.ArtDes
        LIMIT 10
    ");
    $stmt->execute(['%' . $texto . '%']);
    return $stmt->fetchAll();
}

/**
 * Lista las ubicaciones (deposito) disponibles.
 */
function listarUbicaciones(PDO $pdo)
{
    $stmt = $pdo->query("
        SELECT u.UbiCod, u.UbiDes, COALESCE(d.DepDes, '') AS DepDes
        FROM ubicacion u
        LEFT JOIN deposito d ON u.DepCod = d.DepCod
        ORDER BY d.DepDes, u.UbiDes
    ");
    return $stmt->fetchAll();
}