<?php
header("Content-Type: application/json; charset=utf-8");
$file = __DIR__ . "/data/schulungen.json";

if(!file_exists($file)){
    echo "[]";
    exit;
}

echo file_get_contents($file);
