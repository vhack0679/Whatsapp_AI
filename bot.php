<?php
header("Content-Type: application/json");

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
   READ MESSAGE
================================ */
$input = file_get_contents("php://input");
$data = json_decode($input, true);
$message = trim($data['message'] ?? '');
$messageLower = strtolower($message);

/* ==============================
   LANGUAGE DETECTION
================================ */
function detectLanguage($text) {
    // Telugu Unicode range
    if (preg_match('/[\x{0C00}-\x{0C7F}]/u', $text)) {
        return "te";
    }
    // Hindi (Devanagari)
    if (preg_match('/[\x{0900}-\x{097F}]/u', $text)) {
        return "hi";
    }
    return "en";
}

$lang = detectLanguage($message);

/* ==============================
   MENUS BY LANGUAGE
================================ */
function mainMenu($clinic, $lang) {

    if ($lang === "te") {
        return "👋 *$clinic* కు స్వాగతం\n\nదయచేసి నంబర్ పంపండి 👇\n\n"
            ."1️⃣ మందుల ట్రాకింగ్ 💊\n"
            ."2️⃣ మీ ప్రిస్క్రిప్షన్ 📄\n"
            ."3️⃣ అపాయింట్మెంట్ బుక్ చేయండి 📅\n"
            ."4️⃣ క్లినిక్ వివరాలు 🏥\n"
            ."5️⃣ సహాయకుడితో మాట్లాడండి 👩‍⚕️\n\n"
            ."💬 మీ ఆరోగ్య సమస్యను కూడా టైప్ చేయవచ్చు";
    }

    if ($lang === "hi") {
        return "👋 *$clinic* में आपका स्वागत है\n\nकृपया नंबर भेजें 👇\n\n"
            ."1️⃣ दवा ट्रैक करें 💊\n"
            ."2️⃣ प्रिस्क्रिप्शन देखें 📄\n"
            ."3️⃣ अपॉइंटमेंट बुक करें 📅\n"
            ."4️⃣ क्लिनिक जानकारी 🏥\n"
            ."5️⃣ सहायक से बात करें 👩‍⚕️\n\n"
            ."💬 आप अपनी स्वास्थ्य समस्या भी लिख सकते हैं";
    }

    // English
    return "👋 Welcome to *$clinic*\n\nPlease reply with a number 👇\n\n"
        ."1️⃣ Track Medicine 💊\n"
        ."2️⃣ View Prescribed Medicines 📄\n"
        ."3️⃣ Book Appointment 📅\n"
        ."4️⃣ Clinic Details 🏥\n"
        ."5️⃣ Talk to Clinic Assistant 👩‍⚕️\n\n"
        ."💬 You can also ask health-related questions.";
}

/* ==============================
   GEMINI AI
================================ */
function askGemini($userMessage, $apiKey, $clinic, $lang) {

    if (!$apiKey) {
        return "⚠️ AI service unavailable. Please contact clinic.";
    }

    $languageText = ($lang === "te") ? "Telugu" : (($lang === "hi") ? "Hindi" : "English");

    $prompt = "
You are a friendly AI assistant for $clinic (India).

Rules:
- Reply ONLY in $languageText
- Give general health guidance only
- Do NOT diagnose or prescribe medicines
- Keep response short and simple
- Encourage consulting the doctor
- Tone: caring, calm, professional

User message:
$userMessage
";

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

    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);
    $aiText = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;

    if (!$aiText) {
        return "🙏 దయచేసి డాక్టర్‌ను సంప్రదించండి.";
    }

    // Disclaimer by language
    if ($lang === "te") {
        $disclaimer = "\n\n⚠️ ఇది సాధారణ సమాచారం మాత్రమే. సరైన చికిత్స కోసం డాక్టర్‌ను సంప్రదించండి.";
    } elseif ($lang === "hi") {
        $disclaimer = "\n\n⚠️ यह केवल सामान्य जानकारी है। उपचार के लिए डॉक्टर से संपर्क करें।";
    } else {
        $disclaimer = "\n\n⚠️ This is general information only. Please consult our doctor.";
    }

    return trim($aiText) . $disclaimer;
}

/* ==============================
   ROUTING
================================ */
if ($messageLower === "" || in_array($messageLower, ["hi", "hello", "start"])) {

    $reply = mainMenu($CLINIC_NAME, $lang);

} elseif ($messageLower === "1") {

    $reply = ($lang === "te")
        ? "📦 *మందుల ట్రాకింగ్*\n\n👉 $TRACK_URL"
        : (($lang === "hi")
            ? "📦 *दवा ट्रैकिंग*\n\n👉 $TRACK_URL"
            : "📦 *Medicine Tracking*\n\n👉 $TRACK_URL");

} elseif ($messageLower === "2") {

    $reply = ($lang === "te")
        ? "📄 *మీ ప్రిస్క్రిప్షన్*\n\n👉 $PRESCRIPTION_URL"
        : (($lang === "hi")
            ? "📄 *प्रिस्क्रिप्शन देखें*\n\n👉 $PRESCRIPTION_URL"
            : "📄 *Prescribed Medicines*\n\n👉 $PRESCRIPTION_URL");

} elseif ($messageLower === "3") {

    $reply = ($lang === "te")
        ? "📅 *అపాయింట్మెంట్ బుక్ చేయండి*\n\n👉 $APPOINTMENT_URL"
        : (($lang === "hi")
            ? "📅 *अपॉइंटमेंट बुक करें*\n\n👉 $APPOINTMENT_URL"
            : "📅 *Book Appointment*\n\n👉 $APPOINTMENT_URL");

} elseif ($messageLower === "4") {

    $reply = ($lang === "te")
        ? "🏥 *$CLINIC_NAME*\n🕘 ఉదయం 9 – రాత్రి 8\n🌐 $WEBSITE"
        : (($lang === "hi")
            ? "🏥 *$CLINIC_NAME*\n🕘 सुबह 9 – रात 8\n🌐 $WEBSITE"
            : "🏥 *$CLINIC_NAME*\n🕘 9 AM – 8 PM\n🌐 $WEBSITE");

} elseif ($messageLower === "5") {

    $reply = ($lang === "te")
        ? "👩‍⚕️ మా సహాయకుడు త్వరలో స్పందిస్తారు ⏳"
        : (($lang === "hi")
            ? "👩‍⚕️ हमारा सहायक जल्द ही जवाब देगा ⏳"
            : "👩‍⚕️ Our clinic assistant will respond shortly ⏳");

} else {

    // AI fallback only for meaningful messages
    if (strlen($message) < 6) {
        $reply = mainMenu($CLINIC_NAME, $lang);
    } else {
        $reply = askGemini($message, $GEMINI_API_KEY, $CLINIC_NAME, $lang);
    }
}

/* ==============================
   SEND RESPONSE
================================ */
echo json_encode([
    "reply" => $reply
]);
