<?php
header("Content-Type: application/json; charset=UTF-8");

$autoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    echo json_encode([
        "reply" => "⚠️ Server is starting. Please try again in a moment."
    ]);
    exit;
}

require $autoload;

use Predis\Client;

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
   REDIS (PREDIS) CONNECTION
================================ */
function redisClient() {
    static $redis = null;
    if ($redis !== null) return $redis;

    $url = getenv("REDIS_URL");
    if (!$url) return null;

    $redis = new Client($url);
    return $redis;
}

/* ==============================
   REDIS SESSION HELPERS
================================ */
function getSession($phone) {
    $redis = redisClient();
    if (!$redis || !$phone) return [];

    $data = $redis->get("wa:session:$phone");
    return $data ? json_decode($data, true) : [];
}

function saveSession($phone, $data, $ttl = 1800) {
    $redis = redisClient();
    if ($redis && $phone) {
        $redis->setex("wa:session:$phone", $ttl, json_encode($data));
    }
}

function clearSession($phone) {
    $redis = redisClient();
    if ($redis && $phone) {
        $redis->del("wa:session:$phone");
    }
}

/* ==============================
   READ REQUEST (WhatsAuto)
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
   TRIGGERS
================================ */
function isMenuTrigger($text) {
    return in_array(mb_strtolower(trim($text),'UTF-8'),
        ["hi","hello","menu","start"], true);
}

function isAIStart($text) {
    return in_array(mb_strtolower(trim($text),'UTF-8'),
        ["start chat","ai chat"], true);
}

/* ==============================
   MENU
================================ */
function mainMenu($lang, $clinic) {

    if ($lang === "te") {
        return "👋 *$clinic*\n\nనంబర్ పంపండి 👇\n\n"
            ."1️⃣ మందుల ట్రాకింగ్ 💊\n"
            ."2️⃣ ప్రిస్క్రిప్షన్ 📄\n"
            ."3️⃣ అపాయింట్మెంట్ 📅\n"
            ."4️⃣ క్లినిక్ వివరాలు 🏥\n"
            ."5️⃣ AI సహాయకుడితో మాట్లాడండి 🤖";
    }

    if ($lang === "hi") {
        return "👋 *$clinic*\n\nनंबर भेजें 👇\n\n"
            ."1️⃣ दवा ट्रैक करें 💊\n"
            ."2️⃣ प्रिस्क्रिप्शन 📄\n"
            ."3️⃣ अपॉइंटमेंट 📅\n"
            ."4️⃣ क्लिनिक जानकारी 🏥\n"
            ."5️⃣ AI सहायक से बात करें 🤖";
    }

    return "👋 *$clinic*\n\nReply with a number 👇\n\n"
        ."1️⃣ Track Medicine 💊\n"
        ."2️⃣ Prescriptions 📄\n"
        ."3️⃣ Appointment 📅\n"
        ."4️⃣ Clinic Details 🏥\n"
        ."5️⃣ Chat with AI Assistant 🤖";
}

/* ==============================
   GEMINI AI
================================ */
function askGemini($text, $lang, $apiKey) {

    if (!$apiKey) {
        return "⚠️ AI unavailable. Please contact the clinic.";
    }

    $language =
        ($lang === "te") ? "Telugu" :
        (($lang === "hi") ? "Hindi" : "English");

    $prompt =
        "Reply ONLY in $language.\n".
        "Give general health guidance only.\n".
        "Do NOT diagnose or prescribe medicines.\n".
        "Keep it short and caring.\n\n".
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
    return $json['candidates'][0]['content']['parts'][0]['text']
        ?? "🙏 Please consult our doctor for proper guidance.";
}

/* ==============================
   ROUTING (REDIS SESSION BASED)
================================ */

// MENU trigger → reset session
if (isMenuTrigger($message)) {

    clearSession($phone);
    $reply = mainMenu($lang, $CLINIC_NAME);

// MENU OPTIONS
} elseif (in_array($messageLower, ["1","2","3","4","5"], true)) {

    switch ($messageLower) {

        case "1":
            $reply = "📦 Track medicine:\n👉 $TRACK_URL";
            break;

        case "2":
            $reply = "📄 Prescriptions:\n👉 $PRESCRIPTION_URL";
            break;

        case "3":
            $reply = "📅 Appointment:\n👉 $APPOINTMENT_URL";
            break;

        case "4":
            $reply = "🏥 $CLINIC_NAME\n🌐 $WEBSITE";
            break;

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

// ONE-SHOT AI RESPONSE
} elseif (!empty($session['awaiting_question'])) {

    $reply = askGemini($message, $lang, $GEMINI_API_KEY);
    clearSession($phone);

// SHORT RANDOM TEXT → hint once
} elseif (mb_strlen($message,'UTF-8') <= 8 && empty($session['hint_shown'])) {

    $reply = "ℹ️ To view the menu, type *hi*.";
    $session['hint_shown'] = true;
    saveSession($phone, $session);

// EVERYTHING ELSE → SILENT
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
