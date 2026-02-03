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

$DOCTOR_WHATSAPP = "9198XXXXXXXX"; // doctor number (digits only)
$GEMINI_API_KEY = getenv("GEMINI_API_KEY");

/* ==============================
   READ REQUEST (WhatsAuto)
================================ */
$raw = file_get_contents("php://input");
parse_str($raw, $data);

$message = trim($data['message'] ?? '');
$messageLower = mb_strtolower($message, 'UTF-8');

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
   GREETING = MENU TRIGGER ONLY
================================ */
function isMenuTrigger($text) {
    $text = mb_strtolower(trim($text), 'UTF-8');

    return in_array($text, [
        "hi", "hello", "menu", "start"
    ], true);
}

/* ==============================
   AI START COMMAND
================================ */
function isAIStart($text) {
    $text = mb_strtolower(trim($text), 'UTF-8');

    return in_array($text, [
        "start chat",
        "ai chat"
    ], true);
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
        return "⚠️ AI service unavailable. Please contact the clinic.";
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
   ROUTING (STRICT)
================================ */

// 1️⃣ MENU ONLY FOR hi / hello / menu / start
if (isMenuTrigger($message)) {

    $reply = mainMenu($lang, $CLINIC_NAME);

// 2️⃣ MENU OPTIONS
} elseif (in_array($messageLower, ["1","2","3","4","5"], true)) {

    switch ($messageLower) {
        case "1": $reply = "📦 Track medicine:\n👉 $TRACK_URL"; break;
        case "2": $reply = "📄 Prescriptions:\n👉 $PRESCRIPTION_URL"; break;
        case "3": $reply = "📅 Appointment:\n👉 $APPOINTMENT_URL"; break;
        case "4": $reply = "🏥 $CLINIC_NAME\n🌐 $WEBSITE"; break;

        case "5":
            $reply =
                ($lang === "te") ? "🤖 AI తో మాట్లాడాలంటే\n👉 *START CHAT* అని టైప్ చేయండి."
                : (($lang === "hi") ? "🤖 AI से बात करने के लिए\n👉 *START CHAT* लिखें।"
                : "🤖 To chat with AI\n👉 type *START CHAT*");
            break;
    }

// 3️⃣ AI START CONFIRMATION
} elseif (isAIStart($message)) {

    $reply =
        ($lang === "te") ? "🤖 మీరు ఇప్పుడు AI సహాయకుడితో మాట్లాడుతున్నారు. మీ సమస్యను టైప్ చేయండి."
        : (($lang === "hi") ? "🤖 अब आप AI सहायक से बात कर रहे हैं। अपनी समस्या लिखें।"
        : "🤖 You are now chatting with the AI assistant. Please describe your issue.");

// 4️⃣ AI ONE-SHOT RESPONSE
} elseif (strlen($message) > 10) {

    $reply = askGemini($message, $lang, $GEMINI_API_KEY);

// 5️⃣ EVERYTHING ELSE → NO MENU, NO AI
} else {

    $reply =
        ($lang === "te") ? "ℹ️ మెనూ చూడాలంటే *hi* అని పంపండి."
        : (($lang === "hi") ? "ℹ️ मेनू देखने के लिए *hi* लिखें।"
        : "ℹ️ To view the menu, type *hi*.");
}

/* ==============================
   RESPONSE
================================ */
echo json_encode(
    ["reply" => $reply],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
