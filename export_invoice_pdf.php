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

/* -----------------------------------
   FILTER + SORTIERUNG
----------------------------------- */

$filtered = array_values(array_filter($data, function($e) use ($kunde, $von, $bis) {
    if (!isset($e['kunde'], $e['datum'])) return false;
    if ($e['kunde'] !== $kunde) return false;
    $d = $e['datum'];
    return ($d >= $von && $d <= $bis);
}));

// Datum → Art sortieren (Zeit entfällt!)
usort($filtered, function($a, $b) {
    if ($a['datum'] !== $b['datum']) {
        return strcmp($a['datum'], $b['datum']);
    }
    return strcmp($a['art'] ?? '', $b['art'] ?? '');
});

/* -----------------------------------
   FORMATIERER
----------------------------------- */

function format_date_eu($d) {
    if (strpos($d,'-') === false) return $d;
    list($y,$m,$day)=explode('-',$d);
    return sprintf('%02d.%02d.%04d', $day, $m, $y);
}

function clean_text($t) {
    $t = str_replace(["\r\n", "\r"], "\n", $t);
    $t = str_replace(["<br>", "<br/>", "<br />"], "\n", $t);
    return trim($t);
}

/* -----------------------------------
   SUMMEN
----------------------------------- */

$total_minutes = 0;
foreach ($filtered as $e) {
    $total_minutes += floatval($e['duration'] ?? 0);
}
$total_hours = $total_minutes / 60.0;
$total_amount = $rate > 0 ? $total_hours * $rate : 0.0;

/* -----------------------------------
   PDF START
----------------------------------- */

class PDF extends FPDF {

    function RowMulti($cols) {
        $lineHeight = 6;
        $maxLines = 1;

        foreach ($cols as $col) {
            $nb = $this->NbLines($col['w'], $col['text']);
            if ($nb > $maxLines) $maxLines = $nb;
        }

        $rowHeight = $lineHeight * $maxLines;

        foreach ($cols as $col) {
            $x = $this->GetX();
            $y = $this->GetY();

            $this->Rect($x, $y, $col['w'], $rowHeight);
            $this->MultiCell($col['w'], $lineHeight, $col['text'], 0);

            $this->SetXY($x + $col['w'], $y);
        }

        $this->Ln($rowHeight);
    }

    function NbLines($w, $txt) {
        $cw = &$this->CurrentFont['cw'];
        if ($w == 0)
            $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2*$this->cMargin) * 1000 / $this->FontSize;

        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb-1] == "\n") $nb--;

        $sep = -1;
        $i = 0; 
        $j = 0; 
        $l = 0; 
        $nl = 1;

        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") { $i++; $sep = -1; $j = $i; $l = 0; $nl++; continue; }
            if ($c == ' ') $sep = $i;
            $l += $cw[$c];

            if ($l > $wmax) {
                if ($sep == -1) { if ($i == $j) $i++; }
                else $i = $sep + 1;

                $sep = -1; $j = $i; $l = 0; $nl++;
            } else $i++;
        }
        return $nl;
    }
}

$pdf = new PDF();
$pdf->AddPage();

$pdf->SetFont('Arial','B',16);
$pdf->Cell(0,10,utf8_decode('Leistungsnachweis'),0,1,'C');

$pdf->Ln(4);
$pdf->SetFont('Arial','',12);
$pdf->Cell(0,8,'Kunde: '.utf8_decode($kunde),0,1);
$pdf->Cell(0,8,'Zeitraum: '.format_date_eu($von).' bis '.format_date_eu($bis),0,1);
$pdf->Cell(0,8,sprintf('Gesamtzeit: %.1f Minuten (%.2f Stunden)', $total_minutes, $total_hours),0,1);
if ($rate > 0) {
    $pdf->Cell(0,8,sprintf('Stundensatz: %.2f EUR   Gesamtbetrag: %.2f EUR', $rate, $total_amount),0,1);
}
$pdf->Ln(6);

/* -----------------------------------
   TABELLENKOPF – ohne Zeit
----------------------------------- */

$pdf->SetFont('Arial','B',11);
$pdf->Cell(25,8,'Datum',1);
$pdf->Cell(40,8,utf8_decode('Art'),1);
$pdf->Cell(95,8,utf8_decode('Beschreibung'),1);
$pdf->Cell(30,8,'Minuten',1);
$pdf->Ln();

$pdf->SetFont('Arial','',10);

/* -----------------------------------
   TABELLE – Inhalt
----------------------------------- */

foreach ($filtered as $e) {

    $datum = format_date_eu($e['datum']);
    $art   = utf8_decode($e['art'] ?? '');
    $desc  = clean_text($e['desc'] ?? '');
    $min   = number_format(floatval($e['duration'] ?? 0),1,',','');

    $pdf->RowMulti([
        ['w'=>25, 'text'=>$datum],
        ['w'=>40, 'text'=>$art],
        ['w'=>95, 'text'=>utf8_decode($desc)],
        ['w'=>30, 'text'=>$min],
    ]);
}

/* -----------------------------------
   Ausgabe
----------------------------------- */

header('Content-Type: application/pdf');
$fname = 'Leistungsnachweis_'.$kunde.'_'.format_date_eu($von).'_bis_'.format_date_eu($bis).'.pdf';
$pdf->Output('I', $fname);
exit;
