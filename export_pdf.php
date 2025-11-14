<?php
require('fpdf/fpdf.php');
header('Content-Type: application/pdf');

// Parameter
$kunde = $_GET['kunde'] ?? '';
$datum = $_GET['datum'] ?? '';

if (!$kunde || !$datum) {
    die("Fehlende Parameter: kunde oder datum");
}

// JSON laden
$file = __DIR__ . '/data/zeitdaten.json';
$data = [];
if (file_exists($file)) {
    $data = json_decode(file_get_contents($file), true);
}

// Filtern
$filtered = array_filter($data, fn($e) =>
    $e["kunde"] === $kunde && $e["datum"] === $datum
);

// Summe
$total_minutes = array_reduce($filtered, fn($sum, $e) => $sum + $e["duration"], 0);

// PDF erzeugen
$pdf = new FPDF();
$pdf->AddPage();

$pdf->SetFont('Arial', 'B', 18);
$pdf->Cell(0, 10, utf8_decode("Statusbericht"), 0, 1, 'C');

$pdf->Ln(5);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 8, "Kunde: $kunde", 0, 1);
$pdf->Cell(0, 8, "Datum: $datum", 0, 1);
$pdf->Cell(0, 8, "Gesamtzeit: $total_minutes Minuten (" . number_format($total_minutes/60,2) . " Stunden)", 0, 1);

$pdf->Ln(5);

// Tabelle
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(40, 8, "Art", 1);
$pdf->Cell(110, 8, "Beschreibung", 1);
$pdf->Cell(40, 8, "Minuten", 1);
$pdf->Ln();

$pdf->SetFont('Arial', '', 10);
foreach ($filtered as $e) {
    $pdf->Cell(40, 8, utf8_decode($e['art']), 1);
    $pdf->Cell(110, 8, utf8_decode($e['desc']), 1);
    $pdf->Cell(40, 8, $e['duration'], 1);
    $pdf->Ln();
}

$pdf->Output("I", "Statusbericht_$kunde" . "_" . $datum . ".pdf");
?>
