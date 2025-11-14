<?php
header('Content-Type: application/json; charset=utf-8');

$file = __DIR__ . '/data/zeitdaten.json';

if (!file_exists($file)) {
    echo '[]';
    exit;
}

$content = file_get_contents($file);
if ($content === false || trim($content) === '') {
    echo '[]';
    exit;
}

echo $content;
