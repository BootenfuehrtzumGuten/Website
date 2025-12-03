<?php
session_start();

if (!isset($_SESSION['kunde'])) {
    http_response_code(403);
    echo "Zugriff verweigert.";
    exit;
}

$kunde_name    = $_SESSION['kunde_name'] ?? 'Kunde';
$traegerFilter = $_SESSION['traeger_filter'] ?? '';

require __DIR__ . '/fpdf/fpdf.php';

// --------------------------------------------------
// Hilfsfunktionen für Datum & Text
// --------------------------------------------------
function format_eu_date($d) {
    if (strpos($d, '-') === false) return $d;
    [$y,$m,$day] = explode('-', $d);
    return sprintf('%02d.%02d.%04d', $day, $m, $y);
}

/**
 * Text von UTF-8 nach ISO-8859-1 für FPDF konvertieren
 * (ohne utf8_decode, damit keine PHP 8.2 Warnungen kommen)
 */
function pdf_text($s) {
    if ($s === null) return '';
    // iconv kann bei komischen Zeichen false liefern → dann Original zurück
    $res = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$s);
    return $res === false ? (string)$s : $res;
}

function clean_text_pdf($t) {
    if (!$t) return '';
    $t = str_replace(["\r\n", "\r"], "\n", $t);
    $t = str_replace(["<br>", "<br/>", "<br />"], "\n", $t);
    $t = preg_replace("/\n{2,}/", "\n", $t);
    return trim($t);
}

// --------------------------------------------------
// Daten laden
// --------------------------------------------------
$file = __DIR__ . '/data/schulungen.json';
$events = [];

if (file_exists($file)) {
    $json = file_get_contents($file);
    $events = json_decode($json, true);
    if (!is_array($events)) {
        $events = [];
    }
}

$today = date('Y-m-d');

// Filter: nur Träger + zukünftige Termine
$filtered = array_values(array_filter($events, function($e) use ($traegerFilter, $today) {
    if (!isset($e['traeger'], $e['datum'])) return false;
    if (trim($e['traeger']) !== trim($traegerFilter)) return false;
    if ($e['datum'] < $today) return false;
    return true;
}));

// --------------------------------------------------
// Sortierung: Datum -> Zeit (von) -> Lehrgang
// --------------------------------------------------
usort($filtered, function($a, $b) {
    $da = $a['datum'] ?? '';
    $db = $b['datum'] ?? '';

    if ($da !== $db) {
        return strcmp($da, $db);
    }

    $va = $a['von'] ?? '';
    $vb = $b['von'] ?? '';

    if ($va !== $vb) {
        return strcmp($va, $vb);
    }

    return strcmp($a['lehrgang'] ?? '', $b['lehrgang'] ?? '');
});

// Gesamtstunden berechnen
$total_hours = 0.0;
foreach ($filtered as $e) {
    $total_hours += (float)($e['dauer'] ?? 0);
}

// --------------------------------------------------
// PDF-Klasse
// --------------------------------------------------
class KundenPDF extends FPDF {

    function Header() {
        $this->SetFont('Arial','B',16);
        $this->SetTextColor(30,30,30);
        $this->Cell(0,10,pdf_text("Schulungstermine – ".$GLOBALS['kunde_name']),0,1,'C');
        $this->Ln(2);

        $this->SetFont('Arial','',11);
        $this->SetTextColor(100,100,100);
        $this->Cell(
            0,
            6,
            pdf_text("Zukünftige Termine (gefiltert nach Ihrem Träger)"),
            0,
            1,
            'C'
        );
        $this->Ln(4);

        $this->SetDrawColor(180,180,180);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(6);
    }

    function FancyRow($cols) {
        $lineHeight = 6;
        $maxLines = 1;

        foreach ($cols as $col) {
            $this->SetFont('Arial', $col['style'] ?? '', 10);
            $text = pdf_text($col['text']);
            $nb = $this->NbLines($col['w'], $text);
            if ($nb > $maxLines) $maxLines = $nb;
        }

        $totalHeight = $lineHeight * $maxLines;

        foreach ($cols as $col) {
            $x = $this->GetX();
            $y = $this->GetY();

            $this->Rect($x, $y, $col['w'], $totalHeight);

            $text = pdf_text($col['text']);
            $this->MultiCell(
                $col['w'],
                $lineHeight,
                $text,
                0,
                $col['align'] ?? 'L'
            );

            $this->SetXY($x + $col['w'], $y);
        }

        $this->Ln($totalHeight);
    }

    function NbLines($w, $txt) {
        $cw = &$this->CurrentFont['cw'];
        if ($w == 0) {
            $w = $this->w - $this->rMargin - $this->x;
        }
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
            if ($c == "\n") {
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
                continue;
            }
            if ($c == ' ') $sep = $i;
            $l += $cw[$c];

            if ($l > $wmax) {
                if ($sep == -1) {
                    if ($i == $j) $i++;
                } else {
                    $i = $sep + 1;
                }
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
            } else {
                $i++;
            }
        }

        return $nl;
    }
}

// --------------------------------------------------
// PDF erzeugen
// --------------------------------------------------
$pdf = new KundenPDF();
$pdf->AddPage();

$pdf->SetFont('Arial','',11);
$pdf->SetTextColor(60,60,60);
$pdf->Cell(
    0,
    8,
    pdf_text("Gesamtstunden (zukünftige Termine): ".number_format($total_hours,2,',','.')." Std"),
    0,
    1
);
$pdf->Ln(4);

// Tabellenkopf
$pdf->SetFont('Arial','B',11);
$pdf->SetFillColor(245,245,245);
$pdf->SetDrawColor(200,200,200);

$pdf->Cell(25,8,pdf_text("Datum"),1,0,'L',true);
$pdf->Cell(50,8,pdf_text("Lehrgang"),1,0,'L',true);
$pdf->Cell(30,8,pdf_text("Zeit"),1,0,'L',true);
$pdf->Cell(20,8,pdf_text("Std"),1,0,'R',true);
$pdf->Cell(65,8,pdf_text("Thema / Beschreibung"),1,1,'L',true);

$pdf->SetFont('Arial','',10);

// Inhalt
foreach ($filtered as $ev) {

    $datum = format_eu_date($ev['datum'] ?? '');
    $lehrgang = $ev['lehrgang'] ?? '';
    $thema    = $ev['thema'] ?? '';
    $von      = $ev['von'] ?? '';
    $bis      = $ev['bis'] ?? '';
    $zeit     = trim($von." – ".$bis." Uhr");
    $dauer    = number_format((float)($ev['dauer'] ?? 0), 2, ',', '.');
    $beschr   = $ev['beschreibung'] ?? '';

    $details = "";
    if (!empty($thema)) {
        $details .= "Thema: ".$thema."\n";
    }
    if (!empty($beschr)) {
        $details .= $beschr;
    }
    $details = clean_text_pdf($details);

    $pdf->FancyRow([
        ['w'=>25, 'text'=>$datum,    'style'=>'',  'align'=>'L'],
        ['w'=>50, 'text'=>$lehrgang, 'style'=>'B', 'align'=>'L'],
        ['w'=>30, 'text'=>$zeit,     'style'=>'',  'align'=>'L'],
        ['w'=>20, 'text'=>$dauer,    'style'=>'',  'align'=>'R'],
        ['w'=>65, 'text'=>$details,  'style'=>'',  'align'=>'L'],
    ]);
}

// --------------------------------------------------
// Ausgabe
// --------------------------------------------------
header('Content-Type: application/pdf');
$filename = 'Termine_'.preg_replace('/[^A-Za-z0-9_\-]/','_',$kunde_name).'.pdf';
$pdf->Output('I', $filename);
exit;
