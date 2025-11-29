<?php
// whatsapp_webhook.php - WhatsApp Premium Activation Code System
// Render-compatible with environment variables

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Load environment variables
$VERIFY_TOKEN = getenv('WHATSAPP_VERIFY_TOKEN') ?: 'dssm_verify_2025';
$ACCESS_TOKEN = getenv('WHATSAPP_ACCESS_TOKEN') ?: '';
$PHONE_NUMBER_ID = getenv('WHATSAPP_PHONE_NUMBER_ID') ?: '';
$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_NAME = getenv('DB_NAME') ?: 'subscriptions';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';

// Configuration constants
define('CODE_EXPIRY_MINUTES', 5); // 5 minutes for testing

// --- DATABASE CONNECTION ---
function getDBConnection() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=$GLOBALS[DB_HOST];dbname=$GLOBALS[DB_NAME];charset=utf8mb4",
                $GLOBALS['DB_USER'],
                $GLOBALS['DB_PASS'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            logMessage("Database connection failed: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    return $pdo;
}

// --- LOGGING ---
function logMessage($message, $level = 'INFO') {
    $logFile = __DIR__ . "/logs/whatsapp_incoming.log";
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// --- WHATSAPP API ---
function sendWhatsAppMessage($to, $message) {
    global $ACCESS_TOKEN, $PHONE_NUMBER_ID;

    if (!$ACCESS_TOKEN || !$PHONE_NUMBER_ID) {
        logMessage("WhatsApp credentials not configured", 'ERROR');
        return false;
    }

    $url = "https://graph.facebook.com/v18.0/$PHONE_NUMBER_ID/messages";

    $data = [
        'messaging_product' => 'whatsapp',
        'to' => $to,
        'type' => 'text',
        'text' => ['body' => $message]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $ACCESS_TOKEN,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        logMessage("WhatsApp message sent to $to: $message");
        return true;
    } else {
        logMessage("Failed to send WhatsApp message to $to: HTTP $httpCode - $response", 'ERROR');
        return false;
    }
}

// --- ACTIVATION CODE GENERATION ---
function generateActivationCode($phone) {
    $pdo = getDBConnection();

    // Generate unique 8-character code
    $code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
    $expiresAt = date('Y-m-d H:i:s', strtotime("+" . CODE_EXPIRY_MINUTES . " minutes"));

    // Save activation code
    $stmt = $pdo->prepare("
        INSERT INTO activation_codes (code, phone, expires_at, used, created_at)
        VALUES (?, ?, ?, 0, NOW())
    ");
    $stmt->execute([$code, $phone, $expiresAt]);

    return ['code' => $code, 'expires_at' => $expiresAt];
}

// --- MESSAGE PROCESSING ---
function processActivationRequest($message, $senderPhone) {
    logMessage("Processing activation request from $senderPhone: $message");

    // Check if it's a request for activation code
    $message = strtolower(trim($message));
    if (strpos($message, 'activate') !== false || strpos($message, 'premium') !== false || strpos($message, 'code') !== false) {

        try {
            // Generate activation code
            $codeResult = generateActivationCode($senderPhone);
            $activationCode = $codeResult['code'];

            logMessage("Activation code generated for $senderPhone: $activationCode");

            // Send activation code
            $message = "🎉 Your Premium Activation Code:\n\n$activationCode\n\nThis code expires in " . CODE_EXPIRY_MINUTES . " minutes.\n\nEnter this code in the app to unlock premium features for 5 minutes.";

            sendWhatsAppMessage($senderPhone, $message);
            logMessage("Activation code sent to $senderPhone");

        } catch (Exception $e) {
            logMessage("Code generation error: " . $e->getMessage(), 'ERROR');
            sendWhatsAppMessage($senderPhone, "❌ Sorry, there was an error generating your activation code. Please try again.");
        }
    } else {
        // Send help message
        $helpMessage = "👋 Hi! Send 'activate' or 'premium' to get your activation code for unlocking premium features.";
        sendWhatsAppMessage($senderPhone, $helpMessage);
    }
}

// --- WEBHOOK HANDLER ---

// GET verification (Meta)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = $_GET['hub_mode'] ?? $_GET['hub.mode'] ?? $_GET['hubmode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? $_GET['hubverifytoken'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? $_GET['hubchallenge'] ?? '';

    logMessage("GET verification request - Mode: $mode, Token: $token");

    if ($mode === 'subscribe' && $token === $VERIFY_TOKEN) {
        logMessage("Verification successful");
        echo $challenge;
        exit;
    } else {
        logMessage("Verification failed - Invalid token or mode", 'ERROR');
        http_response_code(403);
        echo "Invalid verification token";
        exit;
    }
}

// POST webhook events
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents("php://input");
    logMessage("Received POST webhook: " . $rawInput);

    $input = json_decode($rawInput, true);

    if (!$input) {
        logMessage("Invalid JSON in webhook", 'ERROR');
        http_response_code(400);
        echo "Invalid JSON";
        exit;
    }

    // Process webhook entries
    if (isset($input['entry'])) {
        foreach ($input['entry'] as $entry) {
            if (isset($entry['changes'])) {
                foreach ($entry['changes'] as $change) {
                    if (isset($change['value']['messages'])) {
                        foreach ($change['value']['messages'] as $message) {
                            // Only process text messages
                            if (isset($message['type']) && $message['type'] === 'text') {
                                $sender = $message['from'];
                                $text = $message['text']['body'];

                                // Process the activation request
                                processActivationRequest($text, $sender);
                            }
                        }
                    }
                }
            }
        }
    }

    // Always respond with 200 OK
    http_response_code(200);
    echo "EVENT_RECEIVED";
    exit;
}

// Invalid method
logMessage("Invalid request method: " . $_SERVER['REQUEST_METHOD'], 'ERROR');
http_response_code(405);
echo "Method not allowed";
exit;
?>