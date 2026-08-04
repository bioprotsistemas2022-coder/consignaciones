<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

$accion = $_GET['accion'] ?? '';

try {
    switch ($accion) {

        case 'cajas':
            // Lista de cajas con cantidad de items
            $rows = $pdo->query("
                SELECT c.CacCod, c.CacDes, c.Cacubi,
                       COUNT(ci.CacIntCod) AS items,
                       COALESCE(SUM(ci.CacIntCan),0) AS unidades
                FROM cajacirugia c
                LEFT JOIN cajacirugiainstrumentacion ci ON ci.CacCod = c.CacCod
                WHERE c.CacDes IS NOT NULL AND TRIM(c.CacDes) <> ''
                GROUP BY c.CacCod, c.CacDes, c.Cacubi
                ORDER BY c.CacDes
            ")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['ok' => true, 'data' => $rows]);
            break;

        case 'contenido':
            $cac = (int)($_GET['cac'] ?? 0);
            $nombre = $pdo->prepare('SELECT CacDes FROM cajacirugia WHERE CacCod = ?');
            $nombre->execute([$cac]);
            $n = $nombre->fetchColumn();
            $stmt = $pdo->prepare("
                SELECT CacIntCod, CacCod, IncCod, CacIntDes, CacIntCan, CacIntChk
                FROM cajacirugiainstrumentacion
                WHERE CacCod = ?
                ORDER BY CacIntDes
            ");
            $stmt->execute([$cac]);
            echo json_encode(['ok' => true, 'nombre' => $n, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'mover':
            $data = json_decode(file_get_contents('php://input'), true);
            $destino = (int)($data['destino'] ?? 0);
            $usr     = trim($data['usr'] ?? '');
            $com     = trim($data['comentario'] ?? '');
            $items   = $data['items'] ?? [];

            if ($destino < 0) {
                echo json_encode(['ok' => false, 'error' => 'Caja destino inválida']);
                break;
            }
            if (!is_array($items) || count($items) === 0) {
                echo json_encode(['ok' => false, 'error' => 'Seleccione al menos un instrumento']);
                break;
            }

            $errores = [];
            $movidos = 0;
            $pdo->beginTransaction();

            $getFuente = $pdo->prepare('SELECT CacIntCod, CacCod, IncCod, CacIntDes, CacIntCan, CacIntChk, CacIntCom FROM cajacirugiainstrumentacion WHERE CacIntCod = ? AND CacCod = ?');
            $getDest   = $pdo->prepare('SELECT CacIntCod, CacIntCan FROM cajacirugiainstrumentacion WHERE CacCod = ? AND IncCod = ? LIMIT 1');
            $updDest   = $pdo->prepare('UPDATE cajacirugiainstrumentacion SET CacIntCan = ? WHERE CacIntCod = ?');
            $insDest   = $pdo->prepare('INSERT INTO cajacirugiainstrumentacion (CacCod, IncCod, CacIntDes, CacIntCan, CacIntChk, CacIntCom) VALUES (?, ?, ?, ?, ?, ?)');
            $delFuente = $pdo->prepare('DELETE FROM cajacirugiainstrumentacion WHERE CacIntCod = ?');
            $updFuente = $pdo->prepare('UPDATE cajacirugiainstrumentacion SET CacIntCan = ? WHERE CacIntCod = ?');
            $insMov    = $pdo->prepare('INSERT INTO movimiento_instrumental (MovFec, MovUsr, MovCacOri, MovCacDes, MovIntCod, MovIntDes, MovCan, MovCom) VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?)');

            foreach ($items as $it) {
                $origen  = (int)($it['cac_cod'] ?? 0);
                $intCod  = (int)($it['cac_int_cod'] ?? 0);
                $cantidad = (int)($it['cantidad'] ?? 0);
                if ($intCod <= 0 || $cantidad <= 0) continue;
                if ($origen === $destino) { $errores[] = 'Origen y destino iguales para el item #' . $intCod; continue; }

                $getFuente->execute([$intCod, $origen]);
                $fuente = $getFuente->fetch();
                if (!$fuente) { $errores[] = 'No se encontró el item #' . $intCod . ' en la caja origen ' . $origen; continue; }

                if ($cantidad > (int)$fuente['CacIntCan']) {
                    $errores[] = 'Cantidad inválida para "' . $fuente['CacIntDes'] . '"';
                    continue;
                }

                $nuevaCan = (int)$fuente['CacIntCan'] - $cantidad;

                // Buscar destino con mismo IncCod
                $getDest->execute([$destino, $fuente['IncCod']]);
                $dest = $getDest->fetch();
                if ($dest) {
                    $updDest->execute([(int)$dest['CacIntCan'] + $cantidad, $dest['CacIntCod']]);
                } else {
                    $insDest->execute([$destino, $fuente['IncCod'], $fuente['CacIntDes'], $cantidad, $fuente['CacIntChk'], $fuente['CacIntCom']]);
                }

                // Remover del origen
                if ($nuevaCan <= 0) {
                    $delFuente->execute([$intCod]);
                } else {
                    $updFuente->execute([$nuevaCan, $intCod]);
                }

                $insMov->execute([$usr, $origen, $destino, $fuente['IncCod'], $fuente['CacIntDes'], $cantidad, $com]);
                $movidos += $cantidad;
            }

            $pdo->commit();
            echo json_encode([
                'ok' => true,
                'movidos' => $movidos,
                'errores' => $errores
            ]);
            break;

        case 'movimientos':
            $limit = min((int)($_GET['limite'] ?? 20), 100);
            $rows = $pdo->query("
                SELECT m.MovCod, m.MovFec, m.MovUsr, m.MovCacOri, m.MovCacDes,
                       m.MovIntDes, m.MovCan, m.MovCom,
                       o.CacDes AS OriDes, d.CacDes AS DesDes
                FROM movimiento_instrumental m
                LEFT JOIN cajacirugia o ON o.CacCod = m.MovCacOri
                LEFT JOIN cajacirugia d ON d.CacCod = m.MovCacDes
                ORDER BY m.MovCod DESC
                LIMIT $limit
            ")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['ok' => true, 'data' => $rows]);
            break;

        default:
            echo json_encode(['ok' => false, 'error' => 'Acción no válida']);
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}