<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/cajas_planilla.php';

$accion = $_GET['accion'] ?? '';

try {
    switch ($accion) {
        case 'listarConsignadas':
            $plcCod = (int)($_GET['plc_cod'] ?? 0);
            $q = trim($_GET['q'] ?? '');
            $soloSinAsignar = !empty($_GET['solo_sin_asignar']);
            echo json_encode(['ok' => true, 'data' => listarConsignadasCajas($pdo, $q !== '' ? $q : null, $soloSinAsignar)]);
            break;

        case 'detalle':
            $plcCod = (int)($_GET['plc_cod'] ?? 0);
            $cacCod = (int)($_GET['cac_cod'] ?? 0);
            if ($cacCod <= 0) {
                echo json_encode(['ok' => false, 'error' => 'Falta cac_cod']);
                break;
            }
            echo json_encode(['ok' => true, 'data' => detalleCajaConsignada($pdo, $plcCod, $cacCod)]);
            break;

        case 'asignar':
            $body = json_decode(file_get_contents('php://input'), true);
            $plcCod = (int)($body['plc_cod'] ?? 0);
            if ($plcCod <= 0) {
                echo json_encode(['ok' => false, 'error' => 'Falta plc_cod']);
                break;
            }
            $cajas = $body['cajas'] ?? [];
            if (!is_array($cajas) || count($cajas) === 0) {
                echo json_encode(['ok' => false, 'error' => 'Seleccioná al menos una caja']);
                break;
            }
            $usuario = trim($body['usr'] ?? ($_SESSION['USUARIO'] ?? 'admin'));
            $ubiOverride = (int)($body['ubi_override'] ?? 0);
            $res = asignarCajasACX($pdo, $plcCod, $cajas, $usuario, $ubiOverride);
            echo json_encode(['ok' => true] + $res);
            break;

        default:
            echo json_encode(['ok' => false, 'error' => 'Acción no válida']);
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}