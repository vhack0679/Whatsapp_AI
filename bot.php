<?php
header("Content-Type: application/json; charset=UTF-8");

/* ==============================
   READ REQUEST
================================ */
$raw = file_get_contents("php://input");

// WhatsAuto sends form-urlencoded
parse_str($raw, $data);

$message = trim($data['message'] ?? '');

/* ==============================
   LANGUAGE DETECTION (DEBUG)
================================ */
$lang = "en";
if (preg_match('/[\x{0C00}-\x{0C7F}]/u', $message)) $lang = "te";
elseif (preg_match('/[\x{0900}-\x{097F}]/u', $message)) $lang = "hi";

/* ==============================
   GEMINI DEBUG
================================ */
$apiKey = getenv("GEMINI_API_KEY");

if (!$apiKey) {
    $reply = "❌ DEBUG: GEMINI_API_KEY not found in environment";
} else {

    $url = "https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash:generateContent?key=".$apiKey;

    $prompt =
        "Reply ONLY in the same language as the user.\n".
        "User message:\n".$message;

    $payload = [
        "contents" => [[
            "parts" => [[ "text" => $prompt ]]
        ]]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 20
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        $reply = "❌ CURL ERROR:\n".curl_error($ch);
    } else {
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $reply =
            "🧠 GEMINI DEBUG\n\n".
            "HTTP CODE: $http\n\n".
            "DETECTED LANG: $lang\n\n".
            "RAW RESPONSE:\n$response";
    }

    curl_close($ch);
}

/* ==============================
   SEND DEBUG TO WHATSAPP
================================ */
echo json_encode(
    ["reply" => $reply],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
