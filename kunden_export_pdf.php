<?php
session_start();

header('Content-Type: application/pdf; charset=utf-8');

if (!isset($_SESSION['kunde'])) {
    http_response_code(403);
    echo "Zugriff verweigert.";
    exit;
}

$kunde_name    = $_SESSION['kunde_name'] ?? 'Kunde';
$traegerFilter = $_SESSION['traeger_filter'] ?? '';

require('fpdf/fpdf.php');

// ---------------------------
// Daten laden
// ---------------------------
$file = __DIR__ . '/data/schulungen.json';
$events = [];

if (file_exists($file)) {
    $json = file_get_contents($file);
    $events = json_decode($json, true);
    if (!is_array($events)) $events = [];
}

$today = date('Y-m-d');

// Filter: nur für Kunde + zukünftig
$filtered = array_values(array_filter($events, function($e) use ($traegerFilter, $today) {
    return isset($e['traeger'], $e['datum'])
        && $e['traeger'] === $traegerFilter
        && $e['datum'] >= $today;
}));

usort($filtered, function($a,$b){
    return strcmp(($a['datum'] ?? '').($a['von'] ?? ''), ($b['datum'] ?? '').($b['von'] ?? ''));
});

function format_eu($d) {
    if (strpos($d, '-') === false) return $d;
    [$y,$m,$day] = explode('-', $d);
    return sprintf('%02d.%02d.%04d', $day, $m, $y);
}

// Summe Stunden
$total_hours = array_reduce($filtered, fn($s,$e)=>$s+($e['dauer']??0), 0);

// ---------------------------------------
// CUSTOM PDF CLASS MIT SCHÖNEREN TABELLEN
// ---------------------------------------
class ModernPDF extends FPDF {

    function Header() {
        $this->SetFont('Arial','B',16);
        $this->SetTextColor(30,30,30);

        $this->Cell(0,10,utf8_decode("Schulungstermine  ".$GLOBALS['kunde_name']),0,1,'C');
        $this->Ln(2);

        $this->SetFont('Arial','',11);
        $this->SetTextColor(100,100,100);

        $this->Cell(
            0,
            6,
            utf8_decode("Zukünftige Termine"),
            0,
            1,
            'C'
        );

        $this->Ln(4);

        // dünne Linie
        $this->SetDrawColor(180,180,180);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(6);
    }

    function FancyRow($cols) {
        // $cols = [
        //   ['w'=>30,'text'=>'...','style'=>'B'],
        //   ...
        // ]

        $lineHeight = 6;
        $maxLines = 1;

        // MultiCell benötigt Anzahl Zeilen → selbst berechnen
        foreach ($cols as $col) {
            $this->SetFont('Arial', $col['style'] ?? '', 10);
            $nb = $this->NbLines($col['w'], utf8_decode($col['text']));
            if ($nb > $maxLines) $maxLines = $nb;
        }

        $totalHeight = $lineHeight * $maxLines;

        // Rahmen + Text
        foreach ($cols as $col) {
            $x = $this->GetX();
            $y = $this->GetY();

            $this->Rect($x, $y, $col['w'], $totalHeight);

            $this->MultiCell($col['w'], $lineHeight, utf8_decode($col['text']), 0, $col['align'] ?? 'L');
            $this->SetXY($x + $col['w'], $y);
        }

        $this->Ln($totalHeight);
    }

    // MultiCell Helfer
    function NbLines($w, $txt) {
        $cw = &$this->CurrentFont['cw'];
        if ($w == 0) $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;

        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);

        if ($nb > 0 && $s[$nb - 1] == "\n") $nb--;

        $sep = -1;
        $i = 0; $j = 0; $l = 0; $nl = 1;

        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") {
                $i++; $sep = -1; $j = $i; $l = 0; $nl++;
                continue;
            }
            if ($c == ' ') $sep = $i;
            $l += $cw[$c];
            if ($l > $wmax) {
                if ($sep == -1) {
                    if ($i == $j) $i++;
                } else $i = $sep + 1;
                $sep = -1; $j = $i; $l = 0; $nl++;
            } else $i++;
        }
        return $nl;
    }
}

$pdf = new ModernPDF();
$pdf->AddPage();

$pdf->SetFont('Arial','',11);
$pdf->SetTextColor(60,60,60);

$pdf->Cell(
    0,
    8,
    utf8_decode("Gesamtstunden: ".number_format($total_hours,2,',','.')." Std"),
    0,
    1
);

$pdf->Ln(4);

// ---------------------------------------
// TABELLENKOPF – modern, grau hinterlegt
// ---------------------------------------
$pdf->SetFont('Arial','B',11);
$pdf->SetFillColor(245,245,245);
$pdf->SetDrawColor(200,200,200);

$pdf->Cell(28,8,"Datum",1,0,'L',true);
$pdf->Cell(55,8,"Lehrgang",1,0,'L',true);
$pdf->Cell(28,8,"Zeit",1,0,'L',true);
$pdf->Cell(20,8,"Std",1,0,'R',true);
$pdf->Cell(59,8,utf8_decode("Thema / Beschreibung"),1,1,'L',true);

$pdf->SetFont('Arial','',10);

// ---------------------------------------
// INHALT
// ---------------------------------------
foreach ($filtered as $ev) {

    $datum = format_eu($ev['datum']);
    $lehrgang = $ev['lehrgang'] ?? '';
    $thema = $ev['thema'] ?? '';
    $zeit = ($ev['von'] ?? '')." - ".($ev['bis'] ?? '');
    $dauer = number_format(floatval($ev['dauer'] ?? 0),2,',','.');
    $beschreibung = $ev['beschreibung'] ?? '';

    // Schönes kombiniertes Feld
    $details = "";
    if ($thema) $details .= "Thema: ".$thema."\n";
    if ($beschreibung) $details .= $beschreibung;

    $pdf->FancyRow([
        ['w'=>28, 'text'=>$datum, 'style'=>'', 'align'=>'L'],
        ['w'=>55, 'text'=>$lehrgang, 'style'=>'B', 'align'=>'L'],
        ['w'=>28, 'text'=>$zeit, 'style'=>'', 'align'=>'L'],
        ['w'=>20, 'text'=>$dauer, 'style'=>'', 'align'=>'R'],
        ['w'=>59, 'text'=>$details, 'style'=>'', 'align'=>'L'],
    ]);
}

// ---------------------------------------

$pdf->Output("I", "Termine_$kunde_name.pdf");
exit;
