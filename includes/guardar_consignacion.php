<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['plc_cod']) || !isset($data['items'])) {
    echo json_encode(['ok' => false, 'error' => 'Datos incompletos']);
    exit;
}

$plcCod = (int)$data['plc_cod'];
$usrCod = trim($data['usr_cod'] ?? '');
$items = $data['items'];
$hosDesc = trim($data['institucion'] ?? '');
$med = trim($data['doctor'] ?? '');
$pac = trim($data['paciente'] ?? '');

if ($plcCod <= 0 || empty($items)) {
    echo json_encode(['ok' => false, 'error' => 'Código inválido o sin items']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('INSERT INTO notaconsignacion (NcoFec, NcoCac, NcoPlcCod, NcoHosDesc, NcoMed, NcoPac) VALUES (CURDATE(), 262, ?, ?, ?, ?)');
    $stmt->execute([$plcCod, $hosDesc, $med, $pac]);
    $ncoCod = $pdo->lastInsertId();

    $stmtDet = $pdo->prepare('INSERT INTO notaconsignaciondetalle (NcoCod, NcoDetItm, NcoDetCan, NcoDetDsc, NcoDetChk) VALUES (?, ?, ?, ?, ?)');
    $itm = 1;
    foreach ($items as $item) {
        $cantidad = (int)($item['cantidad'] ?? 0);
        $descripcion = trim($item['descripcion'] ?? '');
        $checked = !empty($item['checked']) ? 'S' : 'N';
        if ($cantidad > 0 && $descripcion !== '') {
            $stmtDet->execute([$ncoCod, $itm++, $cantidad, $descripcion, $checked]);
        }
    }

    $pdo->commit();
    echo json_encode(['ok' => true, 'nco_cod' => $ncoCod]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
