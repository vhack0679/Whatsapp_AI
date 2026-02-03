<?php
header("Content-Type: application/json; charset=UTF-8");

/* ==============================
   CONFIG
================================ */
$CLINIC_NAME = "Vijaya Homoeopathic Clinic";
$GEMINI_API_KEY = getenv("GEMINI_API_KEY");

/* ==============================
   READ REQUEST (FORM + JSON)
================================ */
$raw = file_get_contents("php://input");

// Try JSON
$data = json_decode($raw, true);
$parseMode = "json";

// Fallback to form (WhatsAuto)
if (!is_array($data)) {
    parse_str($raw, $data);
    $parseMode = "form";
}

$message = trim($data['message'] ?? '');
$messageLower = mb_strtolower($message, 'UTF-8');

/* ==============================
   FORCE LANGUAGE DETECTION
================================ */
function detectLangStrict($text) {
    if (preg_match('/[\x{0C00}-\x{0C7F}]/u', $text)) return "te"; // Telugu
    if (preg_match('/[\x{0900}-\x{097F}]/u', $text)) return "hi"; // Hindi
    return "en";
}

$lang = detectLangStrict($message);

/* ==============================
   MENU (LANGUAGE FORCED)
================================ */
function menu($lang, $clinic) {

    if ($lang === "te") {
        return "🟢 తెలుగు మెనూ గుర్తించబడింది\n\n"
            ."👋 $clinic కు స్వాగతం\n\n"
            ."1️⃣ మందుల ట్రాకింగ్\n"
            ."2️⃣ ప్రిస్క్రిప్షన్\n"
            ."3️⃣ అపాయింట్మెంట్\n"
            ."4️⃣ క్లినిక్ వివరాలు\n"
            ."5️⃣ సహాయకుడు";
    }

    if ($lang === "hi") {
        return "🟢 हिंदी मेनू पहचाना गया\n\n"
            ."👋 $clinic में आपका स्वागत है\n\n"
            ."1️⃣ दवा ट्रैक करें\n"
            ."2️⃣ प्रिस्क्रिप्शन\n"
            ."3️⃣ अपॉइंटमेंट\n"
            ."4️⃣ क्लिनिक जानकारी\n"
            ."5️⃣ सहायक";
    }

    return "🟢 English menu detected\n\n"
        ."👋 Welcome to $clinic\n\n"
        ."1️⃣ Track Medicine\n"
        ."2️⃣ Prescriptions\n"
        ."3️⃣ Appointment\n"
        ."4️⃣ Clinic Details\n"
        ."5️⃣ Assistant";
}

/* ==============================
   GEMINI AI WITH FULL DEBUG
================================ */
function askGeminiDebug($text, $lang, $apiKey) {

    if (!$apiKey) {
        return "❌ DEBUG: GEMINI_API_KEY NOT FOUND";
    }

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=$apiKey";

    $payload = [
        "contents" => [[
            "parts" => [[
                "text" => "Reply briefly in ".$lang.": ".$text
            ]]
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
        return "❌ DEBUG: CURL ERROR\n".curl_error($ch);
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($response, true);

    $aiText = null;
    if (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
        $aiText = $json['candidates'][0]['content']['parts'][0]['text'];
    }

    return
        "🧠 AI DEBUG\n\n"
        ."HTTP CODE: $httpCode\n\n"
        ."LANG: $lang\n\n"
        ."RAW RESPONSE:\n$response\n\n"
        ."EXTRACTED TEXT:\n".($aiText ?? "NULL");
}

/* ==============================
   ROUTING (DEBUG FIRST)
================================ */

// 1️⃣ ALWAYS show debug info + menu
if ($message === "" || !in_array($messageLower, ["1","2","3","4","5"], true)) {

    $reply =
        "🛠 DEBUG INFO\n\n"
        ."Parse mode: $parseMode\n"
        ."Message: [$message]\n"
        ."Hex: ".bin2hex($message)."\n"
        ."Detected lang: $lang\n\n"
        ."------------------\n\n"
        .menu($lang, $CLINIC_NAME);

} else {

    // 2️⃣ If user typed number, call AI for testing
    $reply = askGeminiDebug($message, $lang, $GEMINI_API_KEY);
}

/* ==============================
   RESPONSE
================================ */
echo json_encode(
    ["reply" => $reply],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
