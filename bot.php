<?php
header("Content-Type: application/json; charset=UTF-8");

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
   READ REQUEST (WhatsAuto = FORM)
================================ */
$raw = file_get_contents("php://input");
parse_str($raw, $data);

$message = trim($data['message'] ?? '');
$messageLower = mb_strtolower($message, 'UTF-8');

/* ==============================
   LANGUAGE DETECTION (CONFIRMED)
================================ */
function detectLang($text) {
    if (preg_match('/[\x{0C00}-\x{0C7F}]/u', $text)) return "te";
    if (preg_match('/[\x{0900}-\x{097F}]/u', $text)) return "hi";
    return "en";
}

$lang = detectLang($message);

/* ==============================
   MENUS
================================ */
function menu($lang, $clinic) {

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
   GEMINI AI (PRODUCTION)
================================ */
function askGemini($text, $lang, $apiKey) {

    if (!$apiKey) {
        return "⚠️ AI service temporarily unavailable. Please contact the clinic.";
    }

    $language =
        ($lang === "te") ? "Telugu" :
        (($lang === "hi") ? "Hindi" : "English");

    $prompt =
        "Reply ONLY in $language.\n".
        "Give general health advice only.\n".
        "Do NOT diagnose or prescribe medicines.\n".
        "Keep it short and caring.\n".
        "Always suggest consulting a doctor.\n\n".
        "User message:\n".$text;

    $url = "https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash:generateContent?key=".$apiKey;

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

    $response = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($response, true);
    $aiText = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;

    if (!$aiText) {
        return "🙏 Please consult our doctor for proper guidance.";
    }

    if ($lang === "te") {
        return trim($aiText)."\n\n⚠️ ఇది సాధారణ సమాచారం మాత్రమే.";
    }
    if ($lang === "hi") {
        return trim($aiText)."\n\n⚠️ यह केवल सामान्य जानकारी है।";
    }
    return trim($aiText)."\n\n⚠️ This is general information only.";
}

/* ==============================
   ROUTING (FINAL)
================================ */

// Show menu on start / empty
if ($message === "" || in_array($messageLower, ["hi","hello","start"], true)) {

    $reply = menu($lang, $CLINIC_NAME);

// Menu options
} elseif (in_array($messageLower, ["1","2","3","4","5"], true)) {

    switch ($messageLower) {
        case "1":
            $reply = "📦 Track your medicine here:\n👉 $TRACK_URL";
            break;
        case "2":
            $reply = "📄 View prescriptions:\n👉 $PRESCRIPTION_URL";
            break;
        case "3":
            $reply = "📅 Book appointment:\n👉 $APPOINTMENT_URL";
            break;
        case "4":
            $reply = "🏥 $CLINIC_NAME\n🌐 $WEBSITE";
            break;
        case "5":
            $reply = "👩‍⚕️ Our clinic assistant will respond shortly.";
            break;
    }

// AI for everything else
} else {
    $reply = askGemini($message, $lang, $GEMINI_API_KEY);
}

/* ==============================
   RESPONSE
================================ */
echo json_encode(
    ["reply" => $reply],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
