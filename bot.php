<?php
header("Content-Type: application/json; charset=UTF-8");

/* ==============================
   CONFIG
================================ */
$CLINIC_NAME = "Vijaya Homoeopathic Clinic";
$TRACK_URL = "https://vijayahomoeopathic.rf.gd/App/track.html";
$PRESCRIPTION_URL = "https://vijayahomoeopathic.rf.gd/App/prescriptions.html";
$APPOINTMENT_URL = "https://vijayahomoeopathic.rf.gd/App/appointment.html";
$WEBSITE = "https://vijayahomoeopathic.rf.gd";

$GEMINI_API_KEY = getenv("GEMINI_API_KEY");

/* ==============================
   READ REQUEST (AS PER SPEC)
================================ */
$raw = file_get_contents("php://input");
$data = json_decode($raw, true) ?: [];

/* TRUST ONLY message */
$message = trim($data['message'] ?? '');
$messageLower = mb_strtolower($message, 'UTF-8');

/* ==============================
   SANITY FILTERS (IMPORTANT)
================================ */

/* Ignore phone numbers mistakenly sent as message */
if (preg_match('/^\d{10,13}$/', $messageLower)) {
    echo json_encode(["reply" => menu("en", $CLINIC_NAME)], JSON_UNESCAPED_UNICODE);
    exit;
}

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
   MENU
================================ */
function menu($lang, $clinic) {
    if ($lang === "te") {
        return "👋 $clinic కు స్వాగతం\n\nనంబర్ పంపండి 👇\n\n1️⃣ మందుల ట్రాకింగ్\n2️⃣ ప్రిస్క్రిప్షన్\n3️⃣ అపాయింట్మెంట్\n4️⃣ క్లినిక్ వివరాలు\n5️⃣ సహాయకుడు";
    }
    if ($lang === "hi") {
        return "👋 $clinic में आपका स्वागत है\n\nनंबर भेजें 👇\n\n1️⃣ दवा ट्रैक करें\n2️⃣ प्रिस्क्रिप्शन\n3️⃣ अपॉइंटमेंट\n4️⃣ क्लिनिक जानकारी\n5️⃣ सहायक";
    }
    return "👋 Welcome to $clinic\n\nReply with a number 👇\n\n1️⃣ Track Medicine\n2️⃣ Prescriptions\n3️⃣ Appointment\n4️⃣ Clinic Details\n5️⃣ Talk to Assistant";
}

/* ==============================
   ROUTING (CORRECT)
================================ */
if ($messageLower === "" || in_array($messageLower, ["hi","hello","start"])) {

    $reply = menu($lang, $CLINIC_NAME);

} elseif (in_array($messageLower, ["1","2","3","4","5"], true)) {

    switch ($messageLower) {
        case "1": $reply = "📦 Track Medicine:\n$TRACK_URL"; break;
        case "2": $reply = "📄 Prescriptions:\n$PRESCRIPTION_URL"; break;
        case "3": $reply = "📅 Appointment:\n$APPOINTMENT_URL"; break;
        case "4": $reply = "🏥 $CLINIC_NAME\n$WEBSITE"; break;
        case "5": $reply = "👩‍⚕️ Clinic assistant will reply shortly."; break;
    }

} else {

    /* AI only for real sentences */
    if (mb_strlen($message, 'UTF-8') >= 6) {
        $reply = askGemini($message, $lang, $GEMINI_API_KEY);
    } else {
        $reply = menu($lang, $CLINIC_NAME);
    }
}

/* ==============================
   RESPONSE (AS PER SPEC)
================================ */
echo json_encode(
    ["reply" => $reply],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
