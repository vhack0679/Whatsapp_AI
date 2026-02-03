<?php
header("Content-Type: application/json; charset=UTF-8");

$raw = file_get_contents("php://input");

echo json_encode([
  "reply" => "RAW INPUT RECEIVED:\n\n" . $raw
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
