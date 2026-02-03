<?php
// IMPORTANT: plain text response
header("Content-Type: text/plain; charset=UTF-8");

/* ==============================
   CONFIG
================================ */
$CLINIC_NAME = "Vijaya Homoeopathic Clinic";
$WEBSITE = "https://vijayahomoeopathic.rf.gd";
$TRACK_URL = "https://vijayahomoeopathic.rf.gd/App/track.html";
$PRESCRIPTION_URL = "https://vijayahomoeopathic.rf.gd/App/prescriptions.html";
$APPOINTMENT_URL = "https://vijayahomoeopathic.rf.gd/App/appointment.html";

$GEMINI_API_KEY = getenv("GEMINI_API_KEY");

/* ==============================
   READ RAW INPUT (CRITICAL)
================================ */
$raw = file_get_contents("php://input");
file_put_contents("log.txt", date("Y-m-d H:i:s") . "\n" . $raw . "\n\n", FILE_APPEND);

$data = json_decode($raw, true) ?: [];

/*
 WhatsAuto variants send message in DIFFERENT keys.
 We safely extract from all known patterns.
*/
$message =
    $data['message']
    ?? $data['text']
    ?? $data['content']
    ?? $data['msg']
    ?? '';

$message = trim($message);
$messageLower = mb_strtolower($message, 'UTF-8');

/* ==============================
   LANGUAGE DETECTION
================================ */
function detectLanguage($text) {
    if (preg_match('/[\x{0C00}-\x{0C7F}]/u', $text)) return "te";
    if (preg_match('/[\x{0900}-\x{097F}]/u', $text)) return "hi";
    return "en";
}

$lang = detectLanguage($message);

/* ==============================
   MENU
================================ */
function mainMenu($clinic, $lang) {

    if ($lang === "te") {
        return "👋 $clinic కు స్వాగతం\n\nనంబర్ పంపండి 👇\n\n"
            ."1️⃣ మందుల ట్రాకింగ్ 💊\n"
            ."2️⃣ ప్రిస్క్రిప్షన్ 📄\n"
            ."3️⃣ అపాయింట్మెంట్ 📅\n"
            ."4️⃣ క్లినిక్ వివరాలు 🏥\n"
            ."5️⃣ సహాయకుడితో మాట్లాడండి 👩‍⚕️";
    }

    if ($lang === "hi") {
        return "👋 $clinic में आपका स्वागत है\n\nनंबर भेजें 👇\n\n"
            ."1️⃣ दवा ट्रैक करें 💊\n"
            ."2️⃣ प्रिस्क्रिप्शन 📄\n"
            ."3️⃣ अपॉइंटमेंट 📅\n"
            ."4️⃣ क्लिनिक जानकारी 🏥\n"
            ."5️⃣ सहायक से बात करें 👩‍⚕️";
    }

    return "👋 Welcome to $clinic\n\nReply with a number 👇\n\n"
        ."1️⃣ Track Medicine 💊\n"
        ."2️⃣ Prescriptions 📄\n"
        ."3️⃣ Appointment 📅\n"
        ."4️⃣ Clinic Details 🏥\n"
        ."5️⃣ Talk to Assistant 👩‍⚕️";
}

/* ==============================
   GEMINI AI
================================ */
function askGemini($text, $apiKey, $lang) {

    if (!$apiKey) {
        return "⚠️ AI temporarily unavailable. Please contact clinic.";
    }

    $language = $lang === "te" ? "Telugu" : ($lang === "hi" ? "Hindi" : "English");

    $prompt = "Reply ONLY in $language.
Give general health guidance only.
No diagnosis. No medicine names.
Be short and caring.

User: $text";

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=$apiKey";

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
        CURLOPT_TIMEOUT => 15
    ]);

    $res = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($res, true);
    $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;

    if (!$text) {
        return "🙏 Please consult our doctor.";
    }

    if ($lang === "te") {
        return trim($text) . "\n\n⚠️ ఇది సాధారణ సమాచారం మాత్రమే.";
    }
    if ($lang === "hi") {
        return trim($text) . "\n\n⚠️ यह सामान्य जानकारी है।";
    }
    return trim($text) . "\n\n⚠️ General information only.";
}

/* ==============================
   ROUTING (FIXED)
================================ */
if ($message === "" || in_array($messageLower, ["hi", "hello", "start"])) {

    $reply = mainMenu($CLINIC_NAME, $lang);

} elseif (preg_match('/^[1-5]$/', $messageLower)) {

    switch ($messageLower) {
        case "1": $reply = "📦 Track Medicine:\n$TRACK_URL"; break;
        case "2": $reply = "📄 Prescriptions:\n$PRESCRIPTION_URL"; break;
        case "3": $reply = "📅 Appointment:\n$APPOINTMENT_URL"; break;
        case "4": $reply = "🏥 $CLINIC_NAME\n🌐 $WEBSITE"; break;
        case "5": $reply = "👩‍⚕️ Assistant will reply shortly."; break;
    }

} else {

    // AI ONLY for real sentences
    if (mb_strlen($message, 'UTF-8') >= 6) {
        $reply = askGemini($message, $GEMINI_API_KEY, $lang);
    } else {
        $reply = mainMenu($CLINIC_NAME, $lang);
    }
}

/* ==============================
   SEND PLAIN TEXT
================================ */
echo $reply;
