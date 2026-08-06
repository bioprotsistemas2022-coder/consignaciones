<?php
require_once 'fpdf.php';
require_once 'db.php';

$ncoCod = isset($_GET['nco']) ? (int)$_GET['nco'] : 0;
if ($ncoCod <= 0) { die('Código inválido'); }

$stmt = $pdo->prepare('SELECT * FROM notaconsignacion WHERE NcoCod = ?');
$stmt->execute([$ncoCod]);
$nota = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$nota) { die('Nota no encontrada'); }

$stmtDet = $pdo->prepare('SELECT NcoDetCan, NcoDetDsc, NcoDetChk FROM notaconsignaciondetalle WHERE NcoCod = ? ORDER BY NcoDetItm');
$stmtDet->execute([$ncoCod]);
$detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

$stmtImpl = $pdo->prepare("
    SELECT npi.ImplCan, npi.ImplLot, npi.ImplSer, npi.ImplRep, npi.ImplUbiCod,
           COALESCE(a.ArtDes, npi.ImplDsc, '') AS ImplDsc
    FROM notaconsignacionimplante npi
    LEFT JOIN articulos a ON a.ArtId = npi.ArtId
    WHERE npi.NcoCod = ?
    ORDER BY npi.ImplItm
");
$stmtImpl->execute([$ncoCod]);
$implantes = $stmtImpl->fetchAll(PDO::FETCH_ASSOC);

class PDF extends FPDF
{
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, utf8_decode('Pág. ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

$pdf = new PDF('P', 'mm', 'A4');
$pdf->SetMargins(15, 15, 15);
$pdf->AliasNbPages();
$pdf->AddPage();

// === LOGO ===
$logoPath = __DIR__ . '/../LOGO/logo_bioimplant_impresion.png';
if (file_exists($logoPath)) {
    $pdf->Image($logoPath, 15, 15, 70);
}
$pdf->Ln(30);

// === DATOS HARCODEADOS ===
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 5, utf8_decode('BIOIMPLANT IMPLANTES S.R.L.'), 0, 1);
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(0, 4, utf8_decode('C.U.I.T. Nº: 30-70921726-3'), 0, 1);
$pdf->Cell(0, 4, utf8_decode('Montevideo 567 (Rosario) Tel/Fax: 0341 4485178'), 0, 1);
$pdf->Cell(0, 4, 'E mail: bioprotimplantes@bioimplant.com.ar', 0, 1);
$pdf->Ln(3);

$pdf->SetFont('Arial', '', 8);
$pdf->MultiCell(0, 4, utf8_decode('Remito en consignación. CONDICIONES DE LA CONSIGNACIÓN: se remite la siguiente mercadería en carácter de depositario asumiendo todas las obligaciones emergentes del Art. 2182 del Código Civil, de los artículos siguientes y concordantes, obligándose en consecuencia a la restitución en forma inmediata y dentro de los 15 días de la fecha de consignación, caso contrario será facturado de acuerdo a las condiciones habituales estipuladas por la Empresa.'), 0, 'J');
$pdf->Ln(5);

// === LINEA SEPARADORA ===
$pdf->SetDrawColor(26, 58, 92);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(5);

// === DATOS DEL FORMULARIO ===
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 6, utf8_decode('DATOS DE LA CIRUGÍA'), 0, 1);
$pdf->SetFont('Arial', '', 9);

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(25, 5, utf8_decode('INSTITUCIÓN:'), 0, 0);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(0, 5, utf8_decode($nota['NcoHosDesc'] ?? '___________________________'), 0, 1);

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(25, 5, 'FECHA:', 0, 0);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(0, 5, $nota['NcoFec'] ? date('d/m/Y', strtotime($nota['NcoFec'])) : '__/__/____', 0, 1);

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(25, 5, 'DOCTOR:', 0, 0);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(0, 5, utf8_decode($nota['NcoMed'] ?? '___________________________'), 0, 1);

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(25, 5, 'PACIENTE:', 0, 0);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(0, 5, utf8_decode($nota['NcoPac'] ?? '________________________________'), 0, 1);
$pdf->Ln(3);

// === LINEA SEPARADORA ===
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(5);

// === NOMBRE DE LA CAJA ===
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 6, utf8_decode('SET DE INSTRUMENTAL'), 0, 1);
$pdf->Ln(3);

// === TABLA DE INSTRUMENTAL ===
$header = ['Cant.', 'Descripción', ''];
$w = [20, 145, 15];

$pdf->SetFont('Arial', 'B', 8);
$pdf->SetFillColor(26, 58, 92);
$pdf->SetTextColor(255, 255, 255);
for ($i = 0; $i < count($header); $i++) {
    $pdf->Cell($w[$i], 6, utf8_decode($header[$i]), 1, 0, 'C', true);
}
$pdf->Ln();

$pdf->SetTextColor(0, 0, 0);
$fill = false;
$chkCount = 0;
foreach ($detalles as $d) {
    $chk = $d['NcoDetChk'] === 'S' ? '[X]' : '[ ]';
    if ($d['NcoDetChk'] === 'S') $chkCount++;
    if ($fill) {
        $pdf->SetFillColor(235, 235, 235);
    } else {
        $pdf->SetFillColor(255, 255, 255);
    }
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell($w[0], 5, $d['NcoDetCan'], 1, 0, 'C', true);
    $pdf->SetFont('Arial', '', 8);
    $pdf->Cell($w[1], 5, utf8_decode($d['NcoDetDsc']), 1, 0, 'L', true);
    $pdf->Cell($w[2], 5, $chk, 1, 0, 'C', true);
    $pdf->Ln();
    $fill = !$fill;
}

$pdf->SetFont('Arial', 'I', 7);
$pdf->Cell(0, 4, utf8_decode('Items chequeados: ' . $chkCount . ' de ' . count($detalles)), 0, 1, 'R');
$pdf->Ln(10);

// === MATERIALES / IMPLANTES ===
if (count($implantes) > 0) {
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 6, utf8_decode('MATERIALES / IMPLANTES'), 0, 1);
    $pdf->Ln(3);

    $hdr = ['Cant.', 'Descripción', 'Rep.'];
    $w2 = [20, 145, 15];
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(26, 58, 92);
    $pdf->SetTextColor(255, 255, 255);
    for ($i = 0; $i < count($hdr); $i++) {
        $pdf->Cell($w2[$i], 6, utf8_decode($hdr[$i]), 1, 0, 'C', true);
    }
    $pdf->Ln();
    $pdf->SetTextColor(0, 0, 0);

    $fill2 = false;
    foreach ($implantes as $imp) {
        $rep = $imp['ImplRep'] === 'S' ? 'S' : '';
        $dsc = $imp['ImplDsc'];
        if (!empty($imp['ImplLot'])) $dsc .= ' - Lote: ' . $imp['ImplLot'];
        if (!empty($imp['ImplSer'])) $dsc .= ' - N°: ' . $imp['ImplSer'];
        $pdf->SetFillColor($fill2 ? 235 : 255, $fill2 ? 235 : 255, $fill2 ? 235 : 255);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell($w2[0], 5, $imp['ImplCan'], 1, 0, 'C', true);
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell($w2[1], 5, utf8_decode($dsc), 1, 0, 'L', true);
        $pdf->Cell($w2[2], 5, utf8_decode($rep), 1, 0, 'C', true);
        $pdf->Ln();
        $fill2 = !$fill2;
    }
    $pdf->Ln(10);
}

// === LINEA SEPARADORA ===
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(8);

// === FOOTER: 4 COLUMNAS ===
$pdf->SetDrawColor(0, 0, 0);
$colW = 42;
$pdf->SetFont('Arial', 'B', 9);

$labels = ['CONTROL DE INGRESO', 'ACONDICIONAMIENTO', 'CONTROL DE SALIDA', 'REMITO'];
for ($i = 0; $i < 4; $i++) {
    $x = 15 + $i * ($colW + 3);
    $pdf->SetXY($x, $pdf->GetY());
    $pdf->Cell($colW, 6, utf8_decode($labels[$i]), 'B', 0, 'C');
}
$pdf->Ln(8);

// blank space for handwriting
$yStart = $pdf->GetY();
$rowH = 20;
for ($i = 0; $i < 4; $i++) {
    $x = 15 + $i * ($colW + 3);
    $pdf->Rect($x, $yStart, $colW, $rowH);
}
$pdf->SetY($yStart + $rowH);
$pdf->Ln(5);

// === RECIBIDO POR ===
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 6, utf8_decode('RECIBIDO POR'), 0, 1);
$pdf->Ln(2);

$recW = [50, 70, 40];
$recHeaders = ['FIRMA', 'ACLARACIÓN', 'FECHA'];
$pdf->SetFont('Arial', 'B', 8);
for ($i = 0; $i < 3; $i++) {
    $pdf->Cell($recW[$i], 6, utf8_decode($recHeaders[$i]), 1, 0, 'C');
}
$pdf->Ln();

$pdf->SetFont('Arial', '', 8);
$pdf->SetFillColor(255, 255, 255);
for ($r = 0; $r < 3; $r++) {
    for ($i = 0; $i < 3; $i++) {
        $pdf->Cell($recW[$i], 12, '', 1, 0, 'C', true);
    }
    $pdf->Ln();
}

$pdf->Output('I', 'Consignacion_' . $ncoCod . '.pdf');
