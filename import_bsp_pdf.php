<?php
// PDF IMPORTER für BSP-2026-BL
// --------------------------------------------------
// Erwartet: eine Text-Datei mit dem extrahierten PDF-Inhalt
// (PDF-Text kann nicht automatisch von PHP gelesen werden ohne Erweiterungen)

header("Content-Type: application/json; charset=utf-8");

$source = __DIR__ . "/import/BSP-2026-BL.txt"; // <-- PDF als Text speichern!
$target = __DIR__ . "/data/schulungen.json";

if (!file_exists($source)) {
    echo json_encode(["error" => "Import-Datei fehlt"]);
    exit;
}

$raw = file($source, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (!$raw) {
    echo json_encode(["error" => "Import-Datei leer"]);
    exit;
}

$existing = [];
if (file_exists($target)) {
    $existing = json_decode(file_get_contents($target), true);
    if (!is_array($existing)) $existing = [];
}

function make_id() {
    return "TERM_" . substr(md5(uniqid("", true)), 0, 12);
}

function toIsoDate($d) {
    // Eingabe: 17.01.2026
    [$day,$m,$y] = explode(".", $d);
    return "$y-$m-$day";
}

$newEvents = [];

foreach ($raw as $line) {

    // Beispielzeile:
    // Sa 17.01.2026 08:00 - 15:30 N. N. bei Bedarf

    if (!preg_match('/^[A-Z][a-z]\s(\d{2}\.\d{2}\.\d{4})\s(\d{2}:\d{2})\s-\s(\d{2}:\d{2})/u', $line, $m)) {
        continue; // kein echter Termin (z.B. Ferien / Prüfung)
    }

    $date = $m[1];
    $von  = $m[2];
    $bis  = $m[3];

    // Rest extrahieren
    // Entferne "Sa 17.01.2026 08:00 - 15:30"
    $rest = trim(str_replace($m[0], "", $line));

    // Dozent:
    $dozent = "";
    if (preg_match('/N\.\sN\./', $rest)) $dozent = "N.N.";

    // "Fach" und "Ort" stehen IMMER weiter hinten → wir ignorieren sie oder setzen Thema
    $thema = "Blockunterricht";

    $entry = [
        "id"          => make_id(),
        "datum"       => toIsoDate($date),
        "von"         => $von,
        "bis"         => $bis,
        "traeger"     => "BSP-2026-BL",
        "lehrgang"    => "BSP-2026-BL",
        "thema"       => $thema,
        "beschreibung"=> $rest
    ];

    $existing[] = $entry;
    $newEvents[] = $entry;
}

// Speichern
file_put_contents($target, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode([
    "status" => "ok",
    "imported" => count($newEvents),
    "sample" => array_slice($newEvents, 0, 3)
]);
exit;
