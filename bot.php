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
   READ REQUEST (FORM + JSON)
================================ */
$raw = file_get_contents("php://input");

// Try JSON first
$data = json_decode($raw, true);

// Fallback to form-urlencoded (WhatsAuto sends this)
if (!is_array($data)) {
    parse_str($raw, $data);
}

$message = trim($data['message'] ?? '');
$messageLower = mb_strtolower($message, 'UTF-8');

/* ==============================
   IGNORE PHONE-NUMBER JUNK
================================ */
if (preg_match('/^\+?\d{10,13}$/', $messageLower)) {
    echo json_encode(
        ["reply" => mainMenu("en", $CLINIC_NAME)],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

/* ==============================
   LANGUAGE DETECTION (CONFIRMED)
================================ */
function detectLanguage($text) {
    if (preg_match('/[\x{0C00}-\x{0C7F}]/u', $text)) return "te"; // Telugu
    if (preg_match('/[\x{0900}-\x{097F}]/u', $text)) return "hi"; // Hindi
    return "en";
}

$lang = detectLanguage($message);

/* ==============================
   MENU
================================ */
function mainMenu($lang, $clinic) {

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
   GEMINI AI (ROBUST PARSER)
================================ */
function askGemini($userMessage, $lang, $apiKey) {

    if (!$apiKey) {
        return "⚠️ AI service unavailable. Please contact the clinic.";
    }

    $language =
        ($lang === "te") ? "Telugu" :
        (($lang === "hi") ? "Hindi" : "English");

    $prompt = "
You are a homoeopathic clinic assistant in India.

Rules:
- Reply ONLY in $language
- Give general health guidance only
- Do NOT diagnose or prescribe medicines
- Keep reply short and caring
- Always advise consulting the doctor

User message:
$userMessage
";

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=$apiKey";

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
    curl_close($ch);

    $result = json_decode($response, true);

    // ✅ Gemini 1.5 SAFE EXTRACTION
    $aiText = null;

    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        $aiText = $result['candidates'][0]['content']['parts'][0]['text'];
    } elseif (isset($result['candidates'][0]['output_text'])) {
        $aiText = $result['candidates'][0]['output_text'];
    }

    if (!$aiText || trim($aiText) === "") {
        return "🙏 Please consult our doctor for proper guidance.";
    }

    if ($lang === "te") {
        return trim($aiText) . "\n\n⚠️ ఇది సాధారణ సమాచారం మాత్రమే. చికిత్స కోసం డాక్టర్‌ను సంప్రదించండి.";
    }

    if ($lang === "hi") {
        return trim($aiText) . "\n\n⚠️ यह केवल सामान्य जानकारी है। उपचार के लिए डॉक्टर से संपर्क करें।";
    }

    return trim($aiText) . "\n\n⚠️ This is general information only. Please consult our doctor.";
}

/* ==============================
   ROUTING
================================ */
if ($messageLower === "" || in_array($messageLower, ["hi", "hello", "start"], true)) {

    $reply = mainMenu($lang, $CLINIC_NAME);

} elseif (in_array($messageLower, ["1","2","3","4","5"], true)) {

    switch ($messageLower) {
        case "1":
            $reply = "📦 Track your medicine order here:\n👉 $TRACK_URL";
            break;
        case "2":
            $reply = "📄 View your prescriptions:\n👉 $PRESCRIPTION_URL";
            break;
        case "3":
            $reply = "📅 Book an appointment:\n👉 $APPOINTMENT_URL";
            break;
        case "4":
            $reply = "🏥 $CLINIC_NAME\n🌐 $WEBSITE";
            break;
        case "5":
            $reply = "👩‍⚕️ Our clinic assistant will respond shortly.";
            break;
    }

} else {

    // AI trigger: any real language character
    if (preg_match('/[a-zA-Z\x{0900}-\x{097F}\x{0C00}-\x{0C7F}]/u', $message)) {
        $reply = askGemini($message, $lang, $GEMINI_API_KEY);
    } else {
        $reply = mainMenu($lang, $CLINIC_NAME);
    }
}

/* ==============================
   RESPONSE (AS PER APP SPEC)
================================ */
echo json_encode(
    ["reply" => $reply],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
