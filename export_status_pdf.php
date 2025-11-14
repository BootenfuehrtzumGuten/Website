<?php
require('fpdf/fpdf.php');

$kunde = $_GET['kunde'] ?? '';
$datum = $_GET['datum'] ?? '';

if (!$kunde || !$datum) {
    die('Fehlende Parameter: kunde oder datum');
}

$file = __DIR__ . '/data/zeitdaten.json';
$data = [];
if (file_exists($file)) {
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    if (!is_array($data)) $data = [];
}

$filtered = array_values(array_filter($data, function($e) use ($kunde, $datum) {
    return isset($e['kunde'], $e['datum']) && $e['kunde'] === $kunde && $e['datum'] === $datum;
}));

// EU-Datum erzeugen
if (strpos($datum, '-') !== false) {
    list($y,$m,$d) = explode('-', $datum);
    $datum_de = sprintf('%02d.%02d.%04d', $d, $m, $y);
} else {
    $datum_de = $datum;
}

$total_minutes = 0;
foreach ($filtered as $e) {
    $total_minutes += floatval($e['duration'] ?? 0);
}

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial','B',16);
$pdf->Cell(0,10,utf8_decode('Statusbericht – Tageseintrag'),0,1,'C');

$pdf->Ln(4);
$pdf->SetFont('Arial','',12);
$pdf->Cell(0,8,utf8_decode('Kunde: ').utf8_decode($kunde),0,1);
$pdf->Cell(0,8,'Datum: '.$datum_de,0,1);
$pdf->Cell(0,8,sprintf('Gesamtzeit: %.1f Minuten (%.2f Stunden)', $total_minutes, $total_minutes/60),0,1);

$pdf->Ln(8);

// Tabellen-Header – OHNE Uhrzeit
$pdf->SetFont('Arial','B',11);
$pdf->Cell(40,8,utf8_decode('Art'),1,0);
$pdf->Cell(110,8,utf8_decode('Beschreibung'),1,0);
$pdf->Cell(40,8,utf8_decode('Minuten'),1,1);

$pdf->SetFont('Arial','',10);

// Mehrzeilige Beschreibung (MultiCell)
foreach ($filtered as $e) {

    // Höhe dynamisch bestimmen
    $startX = $pdf->GetX();
    $startY = $pdf->GetY();

    // Art
    $pdf->MultiCell(40,8,utf8_decode($e['art']),1,'L');

    // Zurück für nächste Spalte
    $currentY = $pdf->GetY();
    $pdf->SetXY($startX + 40, $startY);

    // Beschreibung
    $pdf->MultiCell(110,8,utf8_decode($e['desc']),1,'L');

    // Minuten (rechts)
    $pdf->SetXY($startX + 150, $startY);
    $pdf->Cell(40,8,number_format(floatval($e['duration'] ?? 0),1,',',''),1,1);

    // Falls die Beschreibung mehrzeilig war, muss der Y-Wert korrigiert werden
    $endY = max($currentY, $pdf->GetY());
    $pdf->SetY($endY);
}

header('Content-Type: application/pdf');
$pdf->Output('I', 'Statusbericht_'.$kunde.'_'.$datum_de.'.pdf');
