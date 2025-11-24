<?php
$year  = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
$month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('n'));

$file = __DIR__ . '/data/schulungen.json';
$data = [];
if (file_exists($file)) {
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    if (!is_array($data)) $data = [];
}

$filtered = array_values(array_filter($data, function($e) use ($year, $month) {
    if (!isset($e['datum'])) return false;
    $d = strtotime($e['datum']);
    if ($d === false) return false;
    return intval(date('Y', $d)) === $year && intval(date('n', $d)) === $month;
}));

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="schulungen_'.$year.'_'.$month.'.ics"');

echo "BEGIN:VCALENDAR\r\n";
echo "VERSION:2.0\r\n";
echo "PRODID:-//DozentenPortal//Schulungsplanung//DE\r\n";

foreach ($filtered as $e) {
    $datum = $e['datum'] ?? '';
    $von   = $e['von'] ?? '';
    $bis   = $e['bis'] ?? '';
    $lehrgang = $e['lehrgang'] ?? 'Lehrgang';
    $thema    = $e['thema'] ?? '';
    $besch    = $e['beschreibung'] ?? '';

    if (!$datum) continue;

    $start = $datum . ' ' . ($von ?: '09:00');
    $end   = $datum . ' ' . ($bis ?: '17:00');

    $dtStart = date('Ymd\THis', strtotime($start));
    $dtEnd   = date('Ymd\THis', strtotime($end));
    $uid     = uniqid('lehrgang-')."@dozentenportal.local";

    $summary = $lehrgang . ($thema ? ' – '.$thema : '');
    $descr   = $besch;

    echo "BEGIN:VEVENT\r\n";
    echo "UID:$uid\r\n";
    echo "DTSTAMP:".gmdate('Ymd\THis\Z')."\r\n";
    echo "DTSTART:$dtStart\r\n";
    echo "DTEND:$dtEnd\r\n";
    echo "SUMMARY:".str_replace("\n","\\n",addslashes($summary))."\r\n";
    if ($descr) {
        echo "DESCRIPTION:".str_replace("\n","\\n",addslashes($descr))."\r\n";
    }
    echo "END:VEVENT\r\n";
}

echo "END:VCALENDAR\r\n";
