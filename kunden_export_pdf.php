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

// Filter: nur Termine für diesen Kunden + nachdem oder heute
$filtered = array_values(array_filter($events, function($e) use ($traegerFilter, $today) {
    if (!isset($e['traeger']) || $e['traeger'] !== $traegerFilter) return false;
    if (!isset($e['datum']) || $e['datum'] < $today) return false;
    return true;
}));

// Sortieren nach Datum + Zeit
usort($filtered, function($a,$b){
    $da = $a['datum'] ?? '';
    $db = $b['datum'] ?? '';
    $ta = $a['von'] ?? '';
    $tb = $b['von'] ?? '';
    return strcmp($da.$ta, $db.$tb);
});

// EU Datum formatieren
function format_eu($d) {
    if (strpos($d, '-') === false) return $d;
    list($y,$m,$day) = explode('-', $d);
    return sprintf('%02d.%02d.%04d', $day, $m, $y);
}

// Gesamtstunden berechnen
$total_hours = 0;
foreach ($filtered as $ev) {
    $total_hours += floatval($ev['dauer'] ?? 0);
}

// -----------------------------------------
// PDF START
// -----------------------------------------
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
$pdf->Cell(50,8,utf8_decode('Lehrgang'),1);
$pdf->Cell(30,8,utf8_decode('Zeit'),1);
$pdf->Cell(20,8,utf8_decode('Std'),1);
$pdf->Cell(65,8,utf8_decode('Ort / Beschreibung'),1);
$pdf->Ln();

$pdf->SetFont('Arial','',9);

$lineHeight = 5;

// -----------------------------------------
// TABELLENINHALT
// -----------------------------------------
foreach ($filtered as $ev) {

    $datum = format_eu($ev['datum']);
    $lehrgang = $ev['lehrgang'] ?? '';
    $zeit = ($ev['von'] ?? '') . ' - ' . ($ev['bis'] ?? '');
    $dauer = number_format(floatval($ev['dauer'] ?? 0),2,',','.');
    $ort = $ev['ort'] ?? '';
    $besch = $ev['beschreibung'] ?? '';

    $lehrgang_text = utf8_decode($lehrgang);
    $details_text  = utf8_decode($ort . " / " . $besch);

    // Startkoordinaten sichern
    $x = $pdf->GetX();
    $y = $pdf->GetY();

    // Spalte "Datum"
    $pdf->MultiCell(25, $lineHeight, utf8_decode($datum), 1);
    $y_after_datum = $pdf->GetY();

    // Zurück nach rechts
    $pdf->SetXY($x + 25, $y);

    // Spalte "Lehrgang" (mehrzeilig!)
    $pdf->MultiCell(50, $lineHeight, $lehrgang_text, 1);
    $y_after_lehrgang = $pdf->GetY();

    // Spalte "Zeit"
    $pdf->SetXY($x + 25 + 50, $y);
    $pdf->MultiCell(30, $lineHeight, utf8_decode($zeit), 1);
    $y_after_zeit = $pdf->GetY();

    // Spalte "Std"
    $pdf->SetXY($x + 25 + 50 + 30, $y);
    $pdf->MultiCell(20, $lineHeight, utf8_decode($dauer), 1);
    $y_after_dauer = $pdf->GetY();

    // Spalte "Ort / Beschreibung" (mehrzeilig!)
    $pdf->SetXY($x + 25 + 50 + 30 + 20, $y);
    $pdf->MultiCell(65, $lineHeight, $details_text, 1);
    $y_after_details = $pdf->GetY();

    // Nächste Zeile setzen (höchste Zelle bestimmen)
    $pdf->SetY(
        max(
            $y_after_datum,
            $y_after_lehrgang,
            $y_after_zeit,
            $y_after_dauer,
            $y_after_details
        )
    );
}

$pdf->Output("I", "Termine_$kunde_name.pdf");
exit;
