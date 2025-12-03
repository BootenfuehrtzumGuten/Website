<?php
// WICHTIG: keine Leerzeichen oder BOM vor diesem PHP-Tag!

session_start();

// Falls vorher schon Ausgabe gepuffert wurde: leeren, damit PDF nicht beschädigt wird
if (ob_get_length()) {
    ob_end_clean();
}

require 'fpdf/fpdf.php';

// ---------------------------
// Parameter prüfen
// ---------------------------
$traeger = $_GET['traeger'] ?? '';
if ($traeger === '') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Fehler: Der Parameter 'traeger' fehlt.";
    exit;
}

// ---------------------------
// Daten laden
// ---------------------------
$file = __DIR__ . '/data/schulungen.json';
$events = [];

if (file_exists($file)) {
    $json = file_get_contents($file);
    $events = json_decode($json, true);
    if (!is_array($events)) {
        $events = [];
    }
}

// ---------------------------
// Hilfsfunktionen
// ---------------------------

/**
 * ISO-Datum (YYYY-MM-DD) -> EU-Format (DD.MM.YYYY)
 */
function eu_date(string $d): string {
    if (strpos($d, '-') === false) return $d;
    [$y, $m, $day] = explode('-', $d);
    return sprintf('%02d.%02d.%04d', (int)$day, (int)$m, (int)$y);
}

/**
 * ISO-Datum (YYYY-MM-DD) -> deutscher Wochentag (Mo, Di, ...)
 */
function wochentag_de(string $datumIso): string {
    $ts = strtotime($datumIso);
    if ($ts === false) return '';
    $tage = ['So','Mo','Di','Mi','Do','Fr','Sa'];
    return $tage[(int)date('w', $ts)];
}

// Nach Träger filtern
$filtered = array_values(array_filter($events, function($e) use ($traeger) {
    return isset($e['traeger']) && $e['traeger'] === $traeger;
}));

// Nach Datum sortieren
usort($filtered, function($a, $b) {
    return strcmp($a['datum'] ?? '', $b['datum'] ?? '');
});

// ---------------------------
// PDF-Klasse
// ---------------------------
class PDF extends FPDF {

    public function Header() {
        global $traeger;

        // Titel zusammenbauen und nach Latin-1 konvertieren
        $title = "Schulungstermine – Träger: " . $traeger;
        $title = mb_convert_encoding($title, 'ISO-8859-1', 'UTF-8');

        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, $title, 0, 1, 'C');
        $this->Ln(4);

        // Tabellenkopf
        $this->SetFont('Arial', 'B', 11);
        $this->SetFillColor(240, 240, 240);
        $this->SetDrawColor(200, 200, 200);

        // Spalten: Tag | Datum | Lehrgang | Zeit | Thema/Beschreibung
        $this->Cell(18, 8, mb_convert_encoding('Tag', 'ISO-8859-1', 'UTF-8'), 1, 0, 'L', true);
        $this->Cell(25, 8, mb_convert_encoding('Datum', 'ISO-8859-1', 'UTF-8'), 1, 0, 'L', true);
        $this->Cell(45, 8, mb_convert_encoding('Lehrgang', 'ISO-8859-1', 'UTF-8'), 1, 0, 'L', true);
        $this->Cell(25, 8, mb_convert_encoding('Zeit', 'ISO-8859-1', 'UTF-8'), 1, 0, 'L', true);
        $this->Cell(82, 8, mb_convert_encoding('Thema / Beschreibung', 'ISO-8859-1', 'UTF-8'), 1, 1, 'L', true);

        $this->SetFont('Arial', '', 10);
    }

    public function Footer() {
        // Seitenzahl unten rechts
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(
            0,
            10,
            'Seite '.$this->PageNo().'/{nb}',
            0,
            0,
            'R'
        );
    }

    /**
     * Tabellenzeile mit automatischem Seitenumbruch
     * $cols = [
     *   ['w'=>Breite, 'text'=>'...', 'style'=>'B', 'align'=>'L'],
     *   ...
     * ]
     */
    public function FancyRow(array $cols) {
        $lineHeight = 6;
        $maxLines   = 1;

        // Ermitteln, wie viele Zeilen jede Spalte braucht
        foreach ($cols as $col) {
            $this->SetFont('Arial', $col['style'] ?? '', 10);
            $nb = $this->NbLines($col['w'], $col['text']);
            if ($nb > $maxLines) {
                $maxLines = $nb;
            }
        }

        $rowHeight = $lineHeight * $maxLines;

        // Seitenumbruch prüfen
        if ($this->GetY() + $rowHeight > $this->PageBreakTrigger) {
            $this->AddPage($this->CurOrientation);
        }

        // Zellen zeichnen
        foreach ($cols as $col) {
            $x = $this->GetX();
            $y = $this->GetY();

            $this->Rect($x, $y, $col['w'], $rowHeight);
            $this->MultiCell(
                $col['w'],
                $lineHeight,
                $col['text'],
                0,
                $col['align'] ?? 'L'
            );
            $this->SetXY($x + $col['w'], $y);
        }

        $this->Ln($rowHeight);
    }

    // Hilfsfunktion für MultiCell-Zeilenanzahl
    public function NbLines($w, $txt) {
        $cw = &$this->CurrentFont['cw'];
        if ($w == 0) {
            $w = $this->w - $this->rMargin - $this->x;
        }
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;

        $s  = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb - 1] == "\n") {
            $nb--;
        }

        $sep = -1;
        $i   = 0;
        $j   = 0;
        $l   = 0;
        $nl  = 1;

        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") {
                $i++;
                $sep = -1;
                $j   = $i;
                $l   = 0;
                $nl++;
                continue;
            }
            if ($c == ' ') {
                $sep = $i;
            }
            $l += $cw[$c];
            if ($l > $wmax) {
                if ($sep == -1) {
                    if ($i == $j) {
                        $i++;
                    }
                } else {
                    $i = $sep + 1;
                }
                $sep = -1;
                $j   = $i;
                $l   = 0;
                $nl++;
            } else {
                $i++;
            }
        }
        return $nl;
    }
}

// ---------------------------
// PDF erzeugen
// ---------------------------
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 10);

// Falls keine Termine
if (empty($filtered)) {
    $msg = mb_convert_encoding('Keine Termine vorhanden.', 'ISO-8859-1', 'UTF-8');
    $pdf->Ln(10);
    $pdf->Cell(0, 8, $msg, 0, 1);

    header('Content-Type: application/pdf');
    $pdf->Output('I', 'Termine_'.$traeger.'.pdf');
    exit;
}

// Zeilen ausgeben
foreach ($filtered as $ev) {
    if (empty($ev['datum'])) {
        continue;
    }

    $datumIso = $ev['datum'];
    $wd       = wochentag_de($datumIso);
    $datumEu  = eu_date($datumIso);

    $von  = $ev['von'] ?? '';
    $bis  = $ev['bis'] ?? '';
    $zeit = trim($von . ($von && $bis ? ' - ' : '') . $bis);

    // FPDF erwartet Latin-1 → sauber konvertieren
    $lg    = isset($ev['lehrgang'])     ? mb_convert_encoding($ev['lehrgang'],     'ISO-8859-1', 'UTF-8') : '';
    $thema = isset($ev['thema'])        ? mb_convert_encoding($ev['thema'],        'ISO-8859-1', 'UTF-8') : '';
    $desc  = isset($ev['beschreibung']) ? mb_convert_encoding($ev['beschreibung'], 'ISO-8859-1', 'UTF-8') : '';

    $wdTxt = mb_convert_encoding($wd, 'ISO-8859-1', 'UTF-8');
    $datumTxt = $datumEu; // nur Ziffern und Punkte → keine Umwandlung nötig

    $details = '';
    if ($thema !== '') {
        $details .= 'Thema: '.$thema."\n";
    }
    if ($desc !== '') {
        $details .= $desc;
    }

    $pdf->FancyRow([
        ['w'=>18, 'text'=> $wdTxt,    'style'=>'',  'align'=>'L'],
        ['w'=>25, 'text'=> $datumTxt, 'style'=>'',  'align'=>'L'],
        ['w'=>45, 'text'=> $lg,       'style'=>'B', 'align'=>'L'],
        ['w'=>25, 'text'=> $zeit,     'style'=>'',  'align'=>'L'],
        ['w'=>82, 'text'=> $details,  'style'=>'',  'align'=>'L'],
    ]);
}

// Ausgabe
header('Content-Type: application/pdf');
$pdf->Output('I', 'Termine_'.$traeger.'.pdf');
exit;
