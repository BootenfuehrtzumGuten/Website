<?php
header("Content-Type: application/json; charset=utf-8");

$file = __DIR__ . "/data/schulungen.json";

$data = [];
if (file_exists($file)) {
    $data = json_decode(file_get_contents($file), true);
    if (!is_array($data)) $data = [];
}

$input = json_decode(file_get_contents("php://input"), true);
if (!$input) {
    echo json_encode(["error"=>"Invalid input"]);
    exit;
}

if (isset($input["delete"]) && $input["delete"] === true) {

    $id = $input["id"];
    $data = array_values(array_filter($data, fn($e)=>$e["id"] !== $id));

    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo json_encode(["status"=>"deleted"]);
    exit;
}

if (!isset($input["id"]) || trim($input["id"]) === "") {
    $input["id"] = "TERM_" . substr(md5(uniqid("", true)), 0, 12);
}

$found = false;
foreach ($data as &$ev) {
    if ($ev["id"] === $input["id"]) {
        $ev = $input;
        $found = true;
        break;
    }
}

if (!$found) {
    $data[] = $input;
}

file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode(["status"=>"ok", "id"=>$input["id"]]);
exit;
