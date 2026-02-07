<?php
header("Content-Type: application/json; charset=UTF-8");

/* ==============================
   AUTOLOAD (Predis)
================================ */
$autoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    echo json_encode(["reply" => "⚠️ Server is starting. Please try again shortly."]);
    exit;
}
require $autoload;

use Predis\Client;

/* ==============================
   CONFIG
================================ */
$CLINIC_NAME = "Vijaya Homoeopathic Clinic";
$WEBSITE = "https://vijayahomoeopathic.rf.gd";
$TRACK_URL = "https://www.indiapost.gov.in/";
$PRESCRIPTION_URL = "https://vijayahomoeopathic.rf.gd/App/prescriptions.html";
$APPOINTMENT_URL = "https://vijayahomoeopathic.rf.gd/App/appointment.html";

$GEMINI_API_KEY = getenv("GEMINI_API_KEY");

/* ==============================
   REDIS (PREDIS)
================================ */
function redisClient() {
    static $redis = null;
    if ($redis !== null) return $redis;

    $url = getenv("REDIS_URL");
    if (!$url) return null;

    return new Client($url);
}

function getSession($phone) {
    $r = redisClient();
    if (!$r || !$phone) return [];
    $data = $r->get("wa:session:$phone");
    return $data ? json_decode($data, true) : [];
}

function saveSession($phone, $data, $ttl = 1800) {
    $r = redisClient();
    if ($r && $phone) {
        $r->setex("wa:session:$phone", $ttl, json_encode($data));
    }
}

function clearSession($phone) {
    $r = redisClient();
    if ($r && $phone) {
        $r->del("wa:session:$phone");
    }
}

/* ==============================
   READ REQUEST (FORM MODE)
================================ */
$raw = file_get_contents("php://input");
parse_str($raw, $data);

$message = trim($data['message'] ?? '');
$messageLower = mb_strtolower($message, 'UTF-8');
$phone = preg_replace('/\D/', '', $data['phone'] ?? $data['sender'] ?? '');
$session = getSession($phone);

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
   HELPERS
================================ */
function isMenuTrigger($text) {
    return in_array(mb_strtolower(trim($text),'UTF-8'),
        ["hi","hello","menu","start"], true);
}

function isAIStart($text) {
    return in_array(mb_strtolower(trim($text),'UTF-8'),
        ["start chat","ai chat"], true);
}

function normalizeHealthText($text) {
    if (mb_strlen($text, 'UTF-8') < 20) {
        return "I am experiencing the following symptoms: ".$text;
    }
    return $text;
}

/* ==============================
   MENU
================================ */
function mainMenu($lang, $clinic) {
    if ($lang === "te") {
        return "👋 *$clinic*\n\nనంబర్ పంపండి 👇\n\n"
            ."1️⃣ మందుల ట్రాకింగ్\n"
            ."2️⃣ ప్రిస్క్రిప్షన్\n"
            ."3️⃣ అపాయింట్మెంట్\n"
            ."4️⃣ క్లినిక్ వివరాలు\n"
            ."5️⃣ AI సహాయకుడితో మాట్లాడండి 🤖";
    }
    if ($lang === "hi") {
        return "👋 *$clinic*\n\nनंबर भेजें 👇\n\n"
            ."1️⃣ दवा ट्रैक करें\n"
            ."2️⃣ प्रिस्क्रिप्शन\n"
            ."3️⃣ अपॉइंटमेंट\n"
            ."4️⃣ क्लिनिक जानकारी\n"
            ."5️⃣ AI सहायक से बात करें 🤖";
    }
    return "👋 *$clinic*\n\nReply with a number 👇\n\n"
        ."1️⃣ Track Medicine\n"
        ."2️⃣ Prescriptions\n"
        ."3️⃣ Appointment\n"
        ."4️⃣ Clinic Details\n"
        ."5️⃣ Chat with AI Assistant 🤖";
}

/* ==============================
   GEMINI AI (WITH QUOTA HANDLING)
================================ */
function askGemini($text, $lang, $apiKey) {

    if (!$apiKey) {
        return "⚠️ AI service unavailable. Please contact the clinic.";
    }

    $text = normalizeHealthText($text);

    $language =
        ($lang === "te") ? "Telugu" :
        (($lang === "hi") ? "Hindi" : "English");

    $prompt =
        "You are a caring clinic assistant.\n".
        "Reply ONLY in $language.\n".
        "Do NOT diagnose.\n".
        "Do NOT prescribe medicines.\n".
        "Give general advice like rest and hydration.\n".
        "Encourage consulting a doctor.\n".
        "Keep it short.\n\n".
        "Patient message:\n".$text;

    $url = "https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash:generateContent?key=".$apiKey;

    $payload = [
        "contents" => [[
            "parts" => [[ "text" => $prompt ]]
        ]],
        "generationConfig" => ["temperature" => 0.7]
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
    curl_close($ch);

    // 🚨 QUOTA EXCEEDED
    if ($httpCode === 429) {
        if ($lang === "te")
            return "⚠️ ఈ రోజు AI పరిమితి పూర్తైంది.\nదయచేసి కొంతసేపటి తరువాత ప్రయత్నించండి లేదా డాక్టర్‌ను సంప్రదించండి.";
        if ($lang === "hi")
            return "⚠️ आज AI की सीमा पूरी हो गई है।\nकृपया बाद में प्रयास करें या डॉक्टर से संपर्क करें.";
        return "⚠️ AI limit is completed for today.\nPlease try again later or contact our doctor.";
    }

    $json = json_decode($response, true);

    if (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
        return trim($json['candidates'][0]['content']['parts'][0]['text']);
    }

    return "🤖 I understand your concern.\nPlease consult our doctor for proper guidance.";
}

/* ==============================
   ROUTING (STRICT + SAFE)
================================ */

// MENU
if (isMenuTrigger($message)) {
    clearSession($phone);
    $reply = mainMenu($lang, $CLINIC_NAME);

// MENU OPTIONS
} elseif (in_array($messageLower, ["1","2","3","4","5"], true)) {

    switch ($messageLower) {
        case "1": $reply = "📦 Track medicine:\n👉 $TRACK_URL"; break;
        case "2": $reply = "📄 Prescriptions:\n👉 $PRESCRIPTION_URL"; break;
        case "3": $reply = "📅 Appointment:\n👉 $APPOINTMENT_URL"; break;
        case "4": $reply = "🏥 $CLINIC_NAME\n🌐 $WEBSITE"; break;

        case "5":
            $session['ai_mode'] = true;
            saveSession($phone, $session);
            $reply = "🤖 To chat with AI\n👉 type *START CHAT*";
            break;
    }

// AI START
} elseif (isAIStart($message) && !empty($session['ai_mode'])) {

    $session['awaiting_question'] = true;
    saveSession($phone, $session);
    $reply = "🤖 Please describe your health issue.";

// AI RESPONSE (ONE-SHOT)
} elseif (!empty($session['awaiting_question'])) {

    $reply = askGemini($message, $lang, $GEMINI_API_KEY);
    clearSession($phone);

// ONE-TIME HINT
} elseif (mb_strlen($message,'UTF-8') <= 8 && empty($session['hint_shown'])) {

    $reply = "ℹ️ To view the menu, type *hi*.";
    $session['hint_shown'] = true;
    saveSession($phone, $session);

// SILENT DEFAULT
} else {
    $reply = "";
}

/* ==============================
   RESPONSE
================================ */
echo json_encode(
    ["reply" => $reply],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
