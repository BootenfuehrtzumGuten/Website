<?php
header("Content-Type: application/json; charset=utf-8");

$file = __DIR__ . "/data/schulungen.json";
$dir  = __DIR__ . "/data";

if(!is_dir($dir)){
    mkdir($dir, 0775, true);
}

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if(!is_array($data)){
    http_response_code(400);
    echo json_encode(["error"=>"Invalid JSON"]);
    exit;
}

file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode($data);
