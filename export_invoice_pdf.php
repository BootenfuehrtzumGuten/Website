<?php
require('fpdf/fpdf.php');

$kunde = $_GET['kunde'] ?? '';
$von   = $_GET['von'] ?? '';
$bis   = $_GET['bis'] ?? '';
$rate  = isset($_GET['rate']) ? floatval($_GET['rate']) : 0.0;

if (!$kunde || !$von || !$bis) {
    die('Fehlende Parameter: kunde, von oder bis');
}

$file = __DIR__ . '/data/zeitdaten.json';
$data = [];
if (file_exists($file)) {
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    if (!is_array($data)) $data = [];
}

$filtered = array_values(array_filter($data, function($e) use ($kunde, $von, $bis) {
    if (!isset($e['kunde'], $e['datum'])) return false;
    if ($e['kunde'] !== $kunde) return false;
    $d = $e['datum'];
    return ($d >= $von && $d <= $bis);
}));

function format_date_eu($d) {
    if (strpos($d,'-') === false) return $d;
    list($y,$m,$day)=explode('-',$d);
    return sprintf('%02d.%02d.%04d', $day, $m, $y);
}

$total_minutes = 0;
foreach ($filtered as $e) {
    $total_minutes += floatval($e['duration'] ?? 0);
}
$total_hours = $total_minutes / 60.0;
$total_amount = $rate > 0 ? $total_hours * $rate : 0.0;

$pdf = new FPDF();
$pdf->AddPage();

$pdf->SetFont('Arial','B',16);
$pdf->Cell(0,10,utf8_decode('Leistungsnachweis'),0,1,'C');

$pdf->Ln(4);
$pdf->SetFont('Arial','',12);
$pdf->Cell(0,8,utf8_decode('Kunde: ').utf8_decode($kunde),0,1);
$pdf->Cell(0,8,'Zeitraum: '.format_date_eu($von).' bis '.format_date_eu($bis),0,1);
$pdf->Cell(0,8,sprintf('Gesamtzeit: %.1f Minuten (%.2f Stunden)', $total_minutes, $total_hours),0,1);
if ($rate > 0) {
    $pdf->Cell(0,8,sprintf('Stundensatz: %.2f EUR   Gesamtbetrag: %.2f EUR', $rate, $total_amount),0,1);
}
$pdf->Ln(6);

$pdf->SetFont('Arial','B',11);
$pdf->Cell(22,8,utf8_decode('Datum'),1);
$pdf->Cell(20,8,utf8_decode('Zeit'),1);
$pdf->Cell(38,8,utf8_decode('Art'),1);
$pdf->Cell(70,8,utf8_decode('Beschreibung'),1);
$pdf->Cell(30,8,utf8_decode('Minuten'),1);
$pdf->Ln();

$pdf->SetFont('Arial','',10);
foreach ($filtered as $e) {
    $pdf->Cell(22,8,format_date_eu($e['datum']),1);
    $pdf->Cell(20,8,isset($e['zeit']) ? $e['zeit'] : '',1);
    $pdf->Cell(38,8,utf8_decode($e['art'] ?? ''),1);
    $pdf->Cell(70,8,utf8_decode(substr($e['desc'] ?? '',0,60)),1);
    $pdf->Cell(30,8,number_format(floatval($e['duration'] ?? 0),1,',',''),1);
    $pdf->Ln();
}

header('Content-Type: application/pdf');
$fname = 'Leistungsnachweis_'.$kunde.'_'.format_date_eu($von).'_bis_'.format_date_eu($bis).'.pdf';
$pdf->Output('I', $fname);
