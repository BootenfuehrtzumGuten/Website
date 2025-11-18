<?php
require('fpdf/fpdf.php');

$year  = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
$month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('n'));

$file = __DIR__ . '/data/schulungen.json';
$data = [];
if (file_exists($file)) {
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    if (!is_array($data)) $data = [];
}

$filtered = array_values(array_filter($data, function($e) use ($year, $month) {
    if (!isset($e['datum'])) return false;
    $d = strtotime($e['datum']);
    if ($d === false) return false;
    return intval(date('Y', $d)) === $year && intval(date('n', $d)) === $month;
}));

usort($filtered, function($a, $b){
    $da = $a['datum'] ?? '';
    $db = $b['datum'] ?? '';
    $ta = ($a['von'] ?? '') . ($a['bis'] ?? '');
    $tb = ($b['von'] ?? '') . ($b['bis'] ?? '');
    return strcmp($da.$ta, $db.$tb);
});

function format_date_eu($d) {
    if (strpos($d,'-') === false) return $d;
    list($y,$m,$day) = explode('-', $d);
    return sprintf('%02d.%02d.%04d', $day, $m, $y);
}

$total_hours = 0.0;
$by_course = [];
$by_traeger = [];
foreach ($filtered as $e) {
    $dur = floatval($e['dauer'] ?? 0);
    $total_hours += $dur;
    $key = $e['lehrgang'] ?? '(ohne Lehrgang)';
    $by_course[$key] = ($by_course[$key] ?? 0) + $dur;
    $tr  = $e['traeger'] ?? '(ohne Träger)';
    $by_traeger[$tr] = ($by_traeger[$tr] ?? 0) + $dur;
}

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial','B',16);
$title = sprintf('Schulungsübersicht %02d/%04d', $month, $year);
$pdf->Cell(0,10,utf8_decode($title),0,1,'C');

$pdf->Ln(4);
$pdf->SetFont('Arial','',12);
$pdf->Cell(0,8,utf8_decode(sprintf('Gesamtstunden: %.2f Std', $total_hours)),0,1);

if (!empty($by_course)) {
    $pdf->Ln(2);
    $pdf->SetFont('Arial','B',11);
    $pdf->Cell(0,8,utf8_decode('Stunden pro Lehrgang:'),0,1);
    $pdf->SetFont('Arial','',10);
    foreach ($by_course as $k => $h) {
        $pdf->Cell(0,6,utf8_decode(" - {$k}: ".number_format($h,2,',','.')." Std"),0,1);
    }
}

if (!empty($by_traeger)) {
    $pdf->Ln(2);
    $pdf->SetFont('Arial','B',11);
    $pdf->Cell(0,8,utf8_decode('Stunden pro Träger:'),0,1);
    $pdf->SetFont('Arial','',10);
    foreach ($by_traeger as $k => $h) {
        $pdf->Cell(0,6,utf8_decode(" - {$k}: ".number_format($h,2,',','.')." Std"),0,1);
    }
}

$pdf->Ln(6);
$pdf->SetFont('Arial','B',10);
$pdf->Cell(20,8,utf8_decode('Datum'),1);
$pdf->Cell(40,8,utf8_decode('Lehrgang'),1);
$pdf->Cell(35,8,utf8_decode('Träger'),1);
$pdf->Cell(25,8,utf8_decode('Zeit'),1);
$pdf->Cell(20,8,utf8_decode('Std'),1);
$pdf->Cell(40,8,utf8_decode('Ort'),1);
$pdf->Ln();

$pdf->SetFont('Arial','',8);
foreach ($filtered as $e) {
    $datum = format_date_eu($e['datum'] ?? '');
    $lehrgang = $e['lehrgang'] ?? '';
    $traeger  = $e['traeger'] ?? '';
    $zeit = trim(($e['von'] ?? '').' - '.($e['bis'] ?? ''));
    $dauer = floatval($e['dauer'] ?? 0);
    $ort = $e['ort'] ?? '';

    $pdf->Cell(20,6,$datum,1);
    $pdf->Cell(40,6,utf8_decode(mb_strimwidth($lehrgang,0,28,'...','UTF-8')),1);
    $pdf->Cell(35,6,utf8_decode(mb_strimwidth($traeger,0,18,'...','UTF-8')),1);
    $pdf->Cell(25,6,utf8_decode($zeit),1);
    $pdf->Cell(20,6,number_format($dauer,2,',','.'),1);
    $pdf->Cell(40,6,utf8_decode(mb_strimwidth($ort,0,24,'...','UTF-8')),1);
    $pdf->Ln();
}

header('Content-Type: application/pdf');
$pdf->Output('I', $title.'.pdf');
