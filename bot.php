<?php
header("Content-Type: application/json; charset=UTF-8");

/* ==============================
   DEBUG MODE – AI ONLY
================================ */

// Load Composer (Predis not needed here)
$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require $autoload;
}

/* ==============================
   READ REQUEST
================================ */
$raw = file_get_contents("php://input");
parse_str($raw, $data);

$message = trim($data['message'] ?? '');

/* ==============================
   LANGUAGE DETECTION
================================ */
function detectLang($text) {
    if (preg_match('/[\x{0C00}-\x{0C7F}]/u', $text)) return "te";
    if (preg_match('/[\x{0900}-\x{097F}]/u', $text)) return "hi";
    return "en";
}

$lang = detectLang($message);

/* ==============================
   NORMALIZE INPUT
================================ */
function normalizeHealthText($text) {
    if (mb_strlen($text, 'UTF-8') < 20) {
        return "I am experiencing the following symptoms: " . $text;
    }
    return $text;
}

$normalized = normalizeHealthText($message);

/* ==============================
   GEMINI DEBUG CALL
================================ */
$apiKey = getenv("GEMINI_API_KEY");

if (!$apiKey) {
    echo json_encode([
        "reply" => "❌ DEBUG: GEMINI_API_KEY missing"
    ]);
    exit;
}

$url = "https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash:generateContent?key=".$apiKey;

$prompt =
    "You are a caring clinic assistant.\n".
    "Reply ONLY in ".($lang === "te" ? "Telugu" : ($lang === "hi" ? "Hindi" : "English")).".\n".
    "Do NOT diagnose.\n".
    "Do NOT prescribe medicines.\n".
    "Give general advice.\n\n".
    "Patient message:\n".$normalized;

$payload = [
    "contents" => [[
        "parts" => [[ "text" => $prompt ]]
    ]],
    "generationConfig" => [
        "temperature" => 0.7
    ]
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
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

$json = json_decode($response, true);
$aiText = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;

/* ==============================
   BUILD DEBUG OUTPUT
================================ */
$debug = "🛠 AI DEBUG MODE\n\n";
$debug .= "USER MESSAGE:\n[$message]\n\n";
$debug .= "NORMALIZED MESSAGE:\n[$normalized]\n\n";
$debug .= "LANG DETECTED: $lang\n\n";
$debug .= "HTTP CODE: $httpCode\n\n";

if ($curlError) {
    $debug .= "CURL ERROR:\n$curlError\n\n";
}

$debug .= "RAW GEMINI RESPONSE:\n";
$debug .= $response ?: "NULL\n";

$debug .= "\n\nEXTRACTED AI TEXT:\n";
$debug .= $aiText ?? "❌ NULL (THIS IS WHY FALLBACK HAPPENS)";

/* ==============================
   SEND DEBUG
================================ */
echo json_encode([
    "reply" => $debug
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
