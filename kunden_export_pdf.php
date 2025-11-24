<?php
session_start();

header('Content-Type: application/pdf; charset=utf-8');

// Zugriff nur für eingeloggte Kunden
if (!isset($_SESSION['kunde'])) {
    http_response_code(403);
    echo "Zugriff verweigert.";
    exit;
}

$kunde_name    = $_SESSION['kunde_name'] ?? 'Kunde';
$traegerFilter = $_SESSION['traeger_filter'] ?? '';

require('fpdf/fpdf.php');

// Daten laden
$file = __DIR__ . '/data/schulungen.json';
$events = [];

if (file_exists($file)) {
    $json = file_get_contents($file);
    $events = json_decode($json, true);
    if (!is_array($events)) $events = [];
}

$today = date('Y-m-d');

// Filter: nur Termine für diesen Kunden + heute/ Zukunft
$filtered = array_values(array_filter($events, function($e) use ($traegerFilter, $today) {
    if (!isset($e['traeger']) || $e['traeger'] !== $traegerFilter) return false;
    if (!isset($e['datum']) || $e['datum'] < $today) return false;
    return true;
}));

usort($filtered, function($a,$b){
    $da = $a['datum'] ?? '';
    $db = $b['datum'] ?? '';
    $ta = $a['von'] ?? '';
    $tb = $b['von'] ?? '';
    return strcmp($da.$ta, $db.$tb);
});

function format_eu($d) {
    if (strpos($d, '-') === false) return $d;
    list($y,$m,$day) = explode('-', $d);
    return sprintf('%02d.%02d.%04d', $day, $m, $y);
}

$total_hours = 0;
foreach ($filtered as $ev) {
    $total_hours += floatval($ev['dauer'] ?? 0);
}

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial','B',16);
$pdf->Cell(0,10,utf8_decode("Schulungstermine – $kunde_name"),0,1,'C');

$pdf->Ln(4);
$pdf->SetFont('Arial','',12);
$pdf->Cell(
    0,
    8,
    utf8_decode("Gesamtstunden (kommende Termine): " . number_format($total_hours,2,',','.')),
    0,
    1
);

$pdf->Ln(4);

// Tabellenkopf
$pdf->SetFont('Arial','B',10);
$pdf->Cell(25,8,utf8_decode('Datum'),1);
$pdf->Cell(45,8,utf8_decode('Lehrgang'),1);
$pdf->Cell(25,8,utf8_decode('Zeit'),1);
$pdf->Cell(20,8,utf8_decode('Std'),1);
$pdf->Cell(75,8,utf8_decode('Thema / Beschreibung'),1);
$pdf->Ln();

$pdf->SetFont('Arial','',9);
$lineHeight = 5;

foreach ($filtered as $ev) {

    $datum = format_eu($ev['datum'] ?? '');
    $lehrgang = $ev['lehrgang'] ?? '';
    $thema    = $ev['thema'] ?? '';
    $zeit = ($ev['von'] ?? '') . ' - ' . ($ev['bis'] ?? '');
    $dauer = number_format(floatval($ev['dauer'] ?? 0),2,',','.');
    $besch = $ev['beschreibung'] ?? '';

    $lehrgang_text = utf8_decode($lehrgang);
    $details_text  = utf8_decode(($thema ? "Thema: ".$thema."\n" : "").$besch);

    $x = $pdf->GetX();
    $y = $pdf->GetY();

    // Datum
    $pdf->MultiCell(25, $lineHeight, utf8_decode($datum), 1);
    $y_after_datum = $pdf->GetY();

    // Lehrgang
    $pdf->SetXY($x + 25, $y);
    $pdf->MultiCell(45, $lineHeight, $lehrgang_text, 1);
    $y_after_lehrgang = $pdf->GetY();

    // Zeit
    $pdf->SetXY($x + 25 + 45, $y);
    $pdf->MultiCell(25, $lineHeight, utf8_decode($zeit), 1);
    $y_after_zeit = $pdf->GetY();

    // Std
    $pdf->SetXY($x + 25 + 45 + 25, $y);
    $pdf->MultiCell(20, $lineHeight, utf8_decode($dauer), 1);
    $y_after_dauer = $pdf->GetY();

    // Thema + Beschreibung
    $pdf->SetXY($x + 25 + 45 + 25 + 20, $y);
    $pdf->MultiCell(75, $lineHeight, $details_text, 1);
    $y_after_details = $pdf->GetY();

    $pdf->SetY(max($y_after_datum, $y_after_lehrgang, $y_after_zeit, $y_after_dauer, $y_after_details));
}

$pdf->Output("I", "Termine_$kunde_name.pdf");
exit;
