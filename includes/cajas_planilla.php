<?php
require_once __DIR__ . '/db.php';

/**
 * Catálogo global de cajas quirúrgicas.
 */
function cajasGlobal(PDO $pdo)
{
    $stmt = $pdo->query("
        SELECT CacCod, CacDes
        FROM cajacirugia
        WHERE TRIM(COALESCE(CacDes, '')) <> ''
        ORDER BY CacDes
    ");
    return $stmt->fetchAll();
}

/**
 * Cajas asignadas a una planilla (planillacirugiacajas -> cajacirugia).
 */
function cajasDePlanilla(PDO $pdo, $plcCod)
{
    $stmt = $pdo->prepare("
        SELECT pc.PccCac AS CacCod, c.CacDes
        FROM planillacirugiacajas pc
        INNER JOIN cajacirugia c ON c.CacCod = pc.PccCac
        WHERE pc.PlcCod = ? AND TRIM(COALESCE(c.CacDes, '')) <> ''
        ORDER BY c.CacDes
    ");
    $stmt->execute([(int)$plcCod]);
    return $stmt->fetchAll();
}

/**
 * Cajas consignadas de una planilla, con sus notas asociadas.
 * Devuelve una fila por nota (puede haber varias notas por caja).
 */
function cajasConsignadas(PDO $pdo, $plcCod)
{
    $stmt = $pdo->prepare("
        SELECT n.NcoCod, n.NcoFec, n.NcoCac, c.CacDes
        FROM notaconsignacion n
        INNER JOIN cajacirugia c ON c.CacCod = n.NcoCac
        WHERE n.NcoPlcCod = ?
        ORDER BY c.CacDes, n.NcoCod
    ");
    $stmt->execute([(int)$plcCod]);
    return $stmt->fetchAll();
}

/**
 * Cajas de la planilla con su estado de consignacion.
 * Marca cada caja asignada con si tiene al menos una nota consignada
 * y el detalle (NcoCod/fechas) de sus notas.
 */
function cajasPlanillaConEstado(PDO $pdo, $plcCod)
{
    $asignadas = cajasDePlanilla($pdo, $plcCod);
    $consignadas = cajasConsignadas($pdo, $plcCod);

    $porCaja = [];
    foreach ($consignadas as $c) {
        $cod = (int)$c['NcoCac'];
        $porCaja[$cod][] = ['NcoCod' => (int)$c['NcoCod'], 'NcoFec' => $c['NcoFec']];
    }

    $resultado = [];
    foreach ($asignadas as $caja) {
        $cod = (int)$caja['CacCod'];
        $resultado[] = [
            'CacCod'    => $cod,
            'CacDes'    => $caja['CacDes'],
            'Consignada'=> isset($porCaja[$cod]) ? 'S' : 'N',
            'Notas'     => $porCaja[$cod] ?? [],
        ];
    }
    return $resultado;
}

/**
 * Detalle (implantes/materiales + consumos) de una caja consignada en una planilla.
 */
function detalleCajaConsignada(PDO $pdo, $plcCod, $cacCod)
{
    $detalle = ['implantes' => [], 'consumos' => []];

    $stmtImp = $pdo->prepare("
        SELECT npi.NcoCod, npi.ImplCan, npi.ImplDsc, npi.ImplLot, npi.ImplSer, npi.ImplRep,
               COALESCE(a.ArtDes, npi.ImplDsc, '') AS ArtDes
        FROM notaconsignacionimplante npi
        LEFT JOIN articulos a ON a.ArtId = npi.ArtId
        WHERE npi.NcoCod IN (
            SELECT NcoCod FROM notaconsignacion WHERE NcoPlcCod = ? AND NcoCac = ?
        )
        ORDER BY npi.NcoCod, npi.ImplItm
    ");
    $stmtImp->execute([(int)$plcCod, (int)$cacCod]);
    $detalle['implantes'] = $stmtImp->fetchAll();

    $stmtCon = $pdo->prepare("
        SELECT e.EscConCod, e.EscConCan, e.EscConArt, e.EscConArtMatId, e.EscConUbiCod,
               e.EscConUpdHor, e.EscConRep,
               COALESCE(a.ArtDes, '') AS ArtDes, COALESCE(a.ArtCod, '') AS ArtCod
        FROM estadocajacajasconsumos e
        LEFT JOIN articulos a ON a.ArtId = e.EscConArt
        WHERE e.EscConPlcCod = ? AND e.EscConArt IN (
            SELECT npi.ArtId
            FROM notaconsignacionimplante npi
            INNER JOIN notaconsignacion n ON n.NcoCod = npi.NcoCod
            WHERE n.NcoPlcCod = ? AND n.NcoCac = ?
        )
        ORDER BY e.EscConCod
    ");
    $stmtCon->execute([(int)$plcCod, (int)$plcCod, (int)$cacCod]);
    $detalle['consumos'] = $stmtCon->fetchAll();

    return $detalle;
}

/**
 * Cajas consignadas a la fecha (independiente de la planilla), agrupadas por caja.
 * Opciones: $q filtro por descripcion/codigo, $soloSinAsignar (solo NcoPlcCod=0).
 */
function listarConsignadasCajas(PDO $pdo, $q = null, $soloSinAsignar = false)
{
    $where = ["TRIM(COALESCE(c.CacDes, '')) <> ''"];
    $params = [];

    if ($soloSinAsignar) {
        // todas sus notas sin planilla
        $where[] = "(n.NcoPlcCod = 0 OR n.NcoPlcCod IS NULL)";
    }
    if ($q !== null && trim($q) !== '') {
        $where[] = "(c.CacDes LIKE ? OR CAST(c.CacCod AS CHAR) LIKE ?)";
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
    }

    $sql = "
        SELECT c.CacCod, c.CacDes,
               MAX(COALESCE(n.NcoPlcCod, 0)) AS PlcCod,
               COUNT(n.NcoCod) AS Notas
        FROM notaconsignacion n
        INNER JOIN cajacirugia c ON c.CacCod = n.NcoCac
        WHERE " . implode(' AND ', $where) . "
        GROUP BY c.CacCod, c.CacDes
        ORDER BY c.CacDes
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Descuento de stock (CONSUMO) de los implantes de las notas de una planilla.
 * Inserta en estadocajacajasconsumos (el trigger replica a stock20) y marca PlcCons='S'.
 */
function descontarImplantesDePlanilla(PDO $pdo, $plcCod, $usuario = 'admin', $ubiOverride = 0)
{
    $stmt = $pdo->prepare("
        SELECT npi.NcoCod, npi.ArtId, npi.ArtMatId, npi.ImplCan,
               npi.ImplLot, npi.ImplRep, COALESCE(npi.ImplUbiCod, 1) AS ImplUbiCod,
               COALESCE(a.ArtCod, '') AS ArtCod
        FROM notaconsignacionimplante npi
        INNER JOIN notaconsignacion n ON n.NcoCod = npi.NcoCod
        LEFT JOIN articulos a ON a.ArtId = npi.ArtId
        WHERE n.NcoPlcCod = ? AND npi.ArtId > 0
        ORDER BY npi.NcoCod, npi.ImplItm
    ");
    $stmt->execute([(int)$plcCod]);
    $implantes = $stmt->fetchAll();

    if (count($implantes) === 0) {
        return ['descontados' => 0, 'por_deposito' => []];
    }

    $ins = $pdo->prepare("
        INSERT INTO estadocajacajasconsumos
            (EscConCan, EscConDet, EscConArt, EscConArtMatId, EscConPlcCod,
             EscConArtLotMan, EscConUpdUsr, EscConUpdHor, EscConRep, EscConExc, EscConUbiCod)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, 'N', ?)
    ");

    $descontados = 0;
    $porDeposito = [];
    foreach ($implantes as $imp) {
        $ubi = $ubiOverride > 0 ? $ubiOverride : (int)$imp['ImplUbiCod'];
        $artMatId = $imp['ArtMatId'] !== null ? (int)$imp['ArtMatId'] : null;
        $ins->execute([
            (float)$imp['ImplCan'],
            trim($imp['ArtCod'] ?? ''),
            (int)$imp['ArtId'],
            $artMatId,
            (int)$plcCod,
            (string)($imp['ImplLot'] ?? ''),
            $usuario,
            $imp['ImplRep'] === 'S' ? 'S' : 'N',
            $ubi,
        ]);
        $descontados++;
        $porDeposito[$ubi] = ($porDeposito[$ubi] ?? 0) + (float)$imp['ImplCan'];
    }

    $pdo->prepare("UPDATE planillacirugia SET PlcCons = 'S' WHERE PlcCod = ?")->execute([(int)$plcCod]);

    return ['descontados' => $descontados, 'por_deposito' => $porDeposito];
}

/**
 * Asigna cajas sin planilla a una planilla de CX y descuenta stock de sus implantes.
 * Debe ejecutarse dentro de una transaccion (la inicia y confirma).
 */
function asignarCajasACX(PDO $pdo, $plcCod, $cajas, $usuario = 'admin', $ubiOverride = 0)
{
    $cajas = array_values(array_unique(array_map('intval', $cajas)));
    if ($plcCod <= 0 || count($cajas) === 0) {
        throw new Exception('Planilla o cajas inválidas para asignar');
    }

    $pdo->beginTransaction();
    try {
        $upd = $pdo->prepare("
            UPDATE notaconsignacion
            SET NcoPlcCod = ?
            WHERE NcoCac = ? AND (NcoPlcCod = 0 OR NcoPlcCod IS NULL)
        ");
        $asignadas = 0;
        foreach ($cajas as $cac) {
            $upd->execute([(int)$plcCod, $cac]);
            $asignadas += $upd->rowCount();
        }

        $desc = descontarImplantesDePlanilla($pdo, $plcCod, $usuario, $ubiOverride);

        $pdo->commit();
        return ['asignadas_notas' => $asignadas, 'cajas' => count($cajas)] + $desc;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}