<?php
header("Content-Type: application/json; charset=UTF-8");

/* ==============================
   READ REQUEST (FORM + JSON)
================================ */
$raw = file_get_contents("php://input");

// Detect payload type
$data = json_decode($raw, true);
$parseMode = "json";

if (!is_array($data)) {
    parse_str($raw, $data);
    $parseMode = "form";
}

$message = $data['message'] ?? '';
$message = trim($message);

/* ==============================
   LANGUAGE DETECTION DEBUG
================================ */
$lang = "en";
if (preg_match('/[\x{0C00}-\x{0C7F}]/u', $message)) $lang = "te";
elseif (preg_match('/[\x{0900}-\x{097F}]/u', $message)) $lang = "hi";

/* ==============================
   GEMINI DEBUG CALL (NO FALLBACK)
================================ */
$apiKey = getenv("GEMINI_API_KEY");

$geminiDebug = "";

if (!$apiKey) {
    $geminiDebug = "❌ GEMINI_API_KEY NOT FOUND IN ENV";
} else {

    $url = "https://generativelanguage.googleapis.com/v1/models/gemini-pro:generateContent?key=".$apiKey;

    $payload = [
        "contents" => [[
            "parts" => [[
                "text" => "Echo this message in same language:\n".$message
            ]]
        ]]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        $geminiDebug = "❌ CURL ERROR:\n".curl_error($ch);
    } else {
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $geminiDebug =
            "HTTP CODE: $httpCode\n\n".
            "RAW GEMINI RESPONSE:\n".$response;
    }

    curl_close($ch);
}

/* ==============================
   SEND FULL DEBUG TO WHATSAPP
================================ */
$reply =
"🛠 FULL DEBUG MODE\n\n".
"PARSE MODE: $parseMode\n\n".
"MESSAGE:\n[$message]\n\n".
"HEX:\n".bin2hex($message)."\n\n".
"DETECTED LANG: $lang\n\n".
"----------------------\n\n".
$geminiDebug;

echo json_encode(
    ["reply" => $reply],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
