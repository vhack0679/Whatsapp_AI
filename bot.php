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

$DOCTOR_WHATSAPP = "9198XXXXXXXX"; // doctor number (no +)
$GEMINI_API_KEY = getenv("GEMINI_API_KEY");

/* ==============================
   READ REQUEST
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
   MENUS
================================ */
function mainMenu($lang, $clinic) {
    if ($lang === "te") {
        return "👋 *$clinic*\n\nనంబర్ పంపండి 👇\n\n"
            ."1️⃣ మందుల ట్రాకింగ్\n"
            ."2️⃣ ప్రిస్క్రిప్షన్\n"
            ."3️⃣ అపాయింట్మెంట్\n"
            ."4️⃣ క్లినిక్ వివరాలు\n"
            ."5️⃣ సహాయకుడితో మాట్లాడండి 🤖";
    }
    if ($lang === "hi") {
        return "👋 *$clinic*\n\nनंबर भेजें 👇\n\n"
            ."1️⃣ दवा ट्रैक करें\n"
            ."2️⃣ प्रिस्क्रिप्शन\n"
            ."3️⃣ अपॉइंटमेंट\n"
            ."4️⃣ क्लिनिक जानकारी\n"
            ."5️⃣ सहायक से बात करें 🤖";
    }
    return "👋 *$clinic*\n\nReply with a number 👇\n\n"
        ."1️⃣ Track Medicine\n"
        ."2️⃣ Prescriptions\n"
        ."3️⃣ Appointment\n"
        ."4️⃣ Clinic Details\n"
        ."5️⃣ Chat with Assistant 🤖";
}

/* ==============================
   DOCTOR LINK (ON CONFIRMATION)
================================ */
function doctorLink($userMessage, $lang, $doctorNumber) {
    $label =
        ($lang === "te") ? "రోగి సందేశం:\n" :
        (($lang === "hi") ? "मरीज का संदेश:\n" :
        "Patient message:\n");

    return "https://wa.me/$doctorNumber?text=" . urlencode($label.$userMessage);
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
    $aiText = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;

    if (!$aiText) {
        return "🙏 Please consult our doctor for proper guidance.";
    }

    // Ask confirmation (NO link here)
    if ($lang === "te") {
        return trim($aiText)
            . "\n\n👨‍⚕️ డాక్టర్‌తో మాట్లాడాలా?\nYES / NO అని రిప్లై చేయండి.";
    }
    if ($lang === "hi") {
        return trim($aiText)
            . "\n\n👨‍⚕️ क्या आप डॉक्टर से बात करना चाहते हैं?\nYES / NO लिखें।";
    }
    return trim($aiText)
        . "\n\n👨‍⚕️ Do you want to talk to a real doctor?\nReply YES or NO.";
}

/* ==============================
   ROUTING
================================ */

// Start → menu
if ($message === "" || in_array($messageLower, ["hi","hello","start"], true)) {
    $reply = mainMenu($lang, $CLINIC_NAME);

// Menu options
} elseif (in_array($messageLower, ["1","2","3","4","5"], true)) {

    switch ($messageLower) {
        case "1": $reply = "📦 Track medicine:\n👉 $TRACK_URL"; break;
        case "2": $reply = "📄 Prescriptions:\n👉 $PRESCRIPTION_URL"; break;
        case "3": $reply = "📅 Appointment:\n👉 $APPOINTMENT_URL"; break;
        case "4": $reply = "🏥 $CLINIC_NAME\n🌐 $WEBSITE"; break;
        case "5":
            $reply =
                ($lang === "te") ? "🤖 మీరు ఇప్పుడు AI సహాయకుడితో మాట్లాడవచ్చు. మీ సమస్యను టైప్ చేయండి."
                : (($lang === "hi") ? "🤖 अब आप AI सहायक से बात कर सकते हैं। अपनी समस्या लिखें।"
                : "🤖 You are now chatting with our AI assistant. Please describe your issue.");
            break;
    }

// Doctor confirmation
} elseif (in_array($messageLower, ["yes","avunu","avunu.","haan","haan.","ha","ok"], true)) {

    $reply =
        ($lang === "te") ? "👨‍⚕️ డాక్టర్‌కు మీ సందేశాన్ని పంపండి:\n👉 ".doctorLink($message, $lang, $DOCTOR_WHATSAPP)
        : (($lang === "hi") ? "👨‍⚕️ डॉक्टर को संदेश भेजें:\n👉 ".doctorLink($message, $lang, $DOCTOR_WHATSAPP)
        : "👨‍⚕️ Send your message to the doctor:\n👉 ".doctorLink($message, $lang, $DOCTOR_WHATSAPP));

// AI chat
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
