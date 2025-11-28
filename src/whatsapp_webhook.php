<?php
// whatsapp_webhook.php - minimal, secure, Render-compatible

// Show errors only during testing — remove on production
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Load env variables from server env (Render uses ENV)
$VERIFY_TOKEN = getenv('WHATSAPP_VERIFY_TOKEN') ?: 'dssm_verify_2025';

// --- GET verification (Meta) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    // Meta sends hub.mode, hub.verify_token, hub.challenge
    // Some hosts rewrite dots — check many variants
    $mode = $_GET['hub_mode'] ?? $_GET['hub.mode'] ?? $_GET['hubmode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? $_GET['hubverifytoken'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? $_GET['hubchallenge'] ?? '';

    // Simple logging for debug (do not expose logs publicly)
    file_put_contents(__DIR__ . "/logs/debug_get.txt", date("c") . " GET: " . print_r($_GET, true) . PHP_EOL, FILE_APPEND);

    if ($mode === 'subscribe' && $token === $VERIFY_TOKEN) {
        echo $challenge;
        exit;
    } else {
        http_response_code(403);
        echo "Invalid verification token";
        exit;
    }
}

// --- POST events (incoming messages) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $raw = file_get_contents("php://input");
    $input = json_decode($raw, true);

    // Log raw incoming webhook (for debugging)
    file_put_contents(__DIR__ . "/logs/whatsapp_incoming.log", date("c") . " POST: " . $raw . PHP_EOL, FILE_APPEND);

    // Respond 200 immediately
    http_response_code(200);
    echo "EVENT_RECEIVED";

    // TODO: enqueue processing (parse EcoCash message, validate, generate code, save to DB, reply)
    // We keep this webhook minimal; heavy work should be done asynchronously.

    exit;
}

// Other methods
http_response_code(404);
echo "Not found";
exit;
?>