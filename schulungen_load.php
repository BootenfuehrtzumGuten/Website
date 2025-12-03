<?php
header("Content-Type: application/json; charset=utf-8");

$file = __DIR__ . "/data/schulungen.json";

if (!file_exists($file)) {
    echo json_encode([]);
    exit;
}

$data = json_decode(file_get_contents($file), true);

if (!is_array($data)) {
    $data = [];
}

echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
