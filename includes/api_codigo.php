<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';
require_once 'codigo_articulo.php';

try {
    $accion = $_GET['accion'] ?? '';

    switch ($accion) {
        case 'buscar':
            $codigo = trim($_GET['codigo'] ?? '');
            if ($codigo === '') {
                echo json_encode(['ok' => false, 'error' => 'Código vacío']);
                exit;
            }
            $cfg = configuracionCodigoBarras($pdo);
            $parsed = parseCodigo($codigo, $cfg);

            $articulo = null;
            if ($parsed['gtin']) {
                $articulo = resolverArticuloPorGtin($pdo, $parsed['gtin'], $parsed['lote'], $parsed['ser']);
            }

            if (!$articulo) {
                echo json_encode([
                    'ok' => false,
                    'error' => 'No se encontró ningún artículo para el código ingresado.',
                    'parsed' => $parsed,
                ]);
                exit;
            }

            // Si el lote/serial no vienen del codigo, usar los del material
            $lote = $parsed['lote'] !== null ? $parsed['lote'] : ($articulo['ArtMatLot'] ?? null);
            $ven  = $parsed['ven']  !== null ? $parsed['ven']  : ($articulo['ArtMatVen'] ?? null);
            $ser  = $parsed['ser']  !== null ? $parsed['ser']  : ($articulo['ArtMatSer'] ?? null);

            $descripcion = $articulo['ArtDes'];
            if (!empty($articulo['ArtMatDes'])) $descripcion .= ' - ' . $articulo['ArtMatDes'];

            echo json_encode([
                'ok' => true,
                'articulo' => [
                    'art_id'    => (int)$articulo['ArtId'],
                    'art_mat_id'=> $articulo['ArtMatId'] !== null ? (int)$articulo['ArtMatId'] : null,
                    'art_cod'   => $articulo['ArtCod'],
                    'gtin'      => $articulo['ArtMatGtin'],
                    'descripcion' => $descripcion,
                    'lote'      => $lote,
                    'vencimiento' => $ven,
                    'serie'     => $ser,
                ],
            ]);
            break;

        case 'ubicaciones':
            echo json_encode(['ok' => true, 'data' => listarUbicaciones($pdo)]);
            break;

        default:
            echo json_encode(['ok' => false, 'error' => 'Acción no válida']);
    }
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}