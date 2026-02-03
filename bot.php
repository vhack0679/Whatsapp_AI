<?php
header("Content-Type: application/json; charset=UTF-8");

/* ==============================
   RAW DEBUG
================================ */
$raw = file_get_contents("php://input");

// Try JSON
$data = json_decode($raw, true);
$parseMode = "json";

// If not JSON, try form
if (!is_array($data)) {
    parse_str($raw, $data);
    $parseMode = "form";
}

$message = $data['message'] ?? '';
$phone   = $data['phone'] ?? '';
$app     = $data['app'] ?? '';
$sender  = $data['sender'] ?? '';

/* ==============================
   DEBUG REPLY (SEND BACK TO WHATSAPP)
================================ */
$debugReply =
"🛠 DEBUG MODE\n\n"
."Parse mode: $parseMode\n"
."App: $app\n"
."Sender: $sender\n"
."Phone: $phone\n\n"
."RAW:\n$raw\n\n"
."MESSAGE FIELD:\n[$message]\n\n"
."HEX BYTES:\n" . bin2hex($message);

echo json_encode(
    ["reply" => $debugReply],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
