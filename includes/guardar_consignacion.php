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

if (empty($items)) {
    echo json_encode(['ok' => false, 'error' => 'Código inválido o sin items']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Validar la caja seleccionada contra el catálogo global de cajas
    $ncoCac = (int)($data['cac_cod'] ?? 0);
    if ($ncoCac > 0) {
        $val = $pdo->prepare('SELECT COUNT(*) FROM cajacirugia WHERE CacCod = ?');
        $val->execute([$ncoCac]);
        if ((int)$val->fetchColumn() === 0) $ncoCac = 0;
    }

    $stmt = $pdo->prepare('INSERT INTO notaconsignacion (NcoFec, NcoCac, NcoPlcCod, NcoHosDesc, NcoMed, NcoPac) VALUES (CURDATE(), ?, ?, ?, ?, ?)');
    $stmt->execute([$ncoCac, $plcCod, $hosDesc, $med, $pac]);
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
