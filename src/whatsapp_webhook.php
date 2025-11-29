<?php
// whatsapp_webhook.php - Complete WhatsApp EcoCash subscription backend
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
define('SUBSCRIPTION_PRICE', 3.00); // $3 USD
define('CODE_EXPIRY_MINUTES', 20);
define('SUBSCRIPTION_DAYS', 30);

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

// --- ECOCASH MESSAGE PARSING ---
function parseEcoCashMessage($message) {
    // Normalize message
    $message = trim($message);
    $message = preg_replace('/\s+/', ' ', $message);

    // Check if it's an EcoCash message
    if (!preg_match('/^You have received/i', $message)) {
        return ['valid' => false, 'error' => 'Not an EcoCash payment message'];
    }

    // Extract amount
    if (!preg_match('/received \$?(\d+(?:\.\d{2})?)/i', $message, $amountMatch)) {
        return ['valid' => false, 'error' => 'Could not extract amount'];
    }
    $amount = floatval($amountMatch[1]);

    // Check amount
    if (abs($amount - SUBSCRIPTION_PRICE) > 0.01) {
        return ['valid' => false, 'error' => 'Amount does not match subscription price ($' . SUBSCRIPTION_PRICE . ')'];
    }

    // Extract sender phone
    if (!preg_match('/from (\d{10,12})/i', $message, $phoneMatch)) {
        return ['valid' => false, 'error' => 'Could not extract sender phone'];
    }
    $fromPhone = $phoneMatch[1];

    // Extract transaction reference
    if (!preg_match('/reference:?\s*([A-Z0-9]+)/i', $message, $refMatch)) {
        return ['valid' => false, 'error' => 'Could not extract transaction reference'];
    }
    $transactionRef = strtoupper($refMatch[1]);

    return [
        'valid' => true,
        'amount' => $amount,
        'from_phone' => $fromPhone,
        'transaction_ref' => $transactionRef,
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

// --- DATABASE OPERATIONS ---
function findOrCreateUser($phone) {
    $pdo = getDBConnection();

    // Check if user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
    $stmt->execute([$phone]);
    $user = $stmt->fetch();

    if ($user) {
        return $user['id'];
    }

    // Create new user
    $stmt = $pdo->prepare("INSERT INTO users (phone, created_at) VALUES (?, NOW())");
    $stmt->execute([$phone]);
    return $pdo->lastInsertId();
}

function savePayment($userId, $paymentData) {
    $pdo = getDBConnection();

    // Check for duplicate transaction
    $stmt = $pdo->prepare("SELECT id FROM payments WHERE transaction_ref = ?");
    $stmt->execute([$paymentData['transaction_ref']]);
    if ($stmt->fetch()) {
        return ['success' => false, 'error' => 'Transaction already processed'];
    }

    // Save payment
    $stmt = $pdo->prepare("
        INSERT INTO payments (user_id, phone, amount, transaction_ref, timestamp, status, raw_message)
        VALUES (?, ?, ?, ?, ?, 'verified', ?)
    ");
    $stmt->execute([
        $userId,
        $paymentData['from_phone'],
        $paymentData['amount'],
        $paymentData['transaction_ref'],
        $paymentData['timestamp'],
        json_encode($paymentData)
    ]);

    return ['success' => true, 'payment_id' => $pdo->lastInsertId()];
}

function generateActivationCode($userId, $paymentId) {
    $pdo = getDBConnection();

    // Generate unique code
    $code = strtoupper(bin2hex(random_bytes(8))); // 16 character code
    $plan = '1M'; // Monthly
    $expiresAt = date('Y-m-d H:i:s', strtotime("+20 minutes"));

    // Save activation code
    $stmt = $pdo->prepare("
        INSERT INTO activation_codes (code, user_id, payment_id, plan, amount, expires_at, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$code, $userId, $paymentId, $plan, SUBSCRIPTION_PRICE, $expiresAt]);

    return ['code' => $code, 'expires_at' => $expiresAt];
}

function createOrUpdateSubscription($userId, $activationCode, $paymentId) {
    $pdo = getDBConnection();

    $startDate = date('Y-m-d H:i:s');
    $expiryDate = date('Y-m-d H:i:s', strtotime("+30 days"));
    $plan = '1M';

    // Create subscription
    $stmt = $pdo->prepare("
        INSERT INTO subscriptions (user_id, activation_code, payment_id, plan, start_date, expiry_date, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())
    ");
    $stmt->execute([$userId, $activationCode, $paymentId, $plan, $startDate, $expiryDate]);

    return ['start_date' => $startDate, 'expiry_date' => $expiryDate];
}

// --- MAIN PROCESSING ---
function processEcoCashPayment($message, $senderPhone) {
    logMessage("Processing EcoCash message from $senderPhone: $message");

    // Parse the message
    $parsed = parseEcoCashMessage($message);
    if (!$parsed['valid']) {
        logMessage("Invalid EcoCash message: " . $parsed['error'], 'ERROR');
        sendWhatsAppMessage($senderPhone, "❌ Invalid payment message: " . $parsed['error'] . "\n\nPlease send the exact EcoCash payment confirmation message.");
        return;
    }

    try {
        // Find or create user
        $userId = findOrCreateUser($parsed['from_phone']);
        logMessage("User ID: $userId for phone: " . $parsed['from_phone']);

        // Save payment
        $paymentResult = savePayment($userId, $parsed);
        if (!$paymentResult['success']) {
            logMessage("Payment save failed: " . $paymentResult['error'], 'ERROR');
            sendWhatsAppMessage($senderPhone, "❌ Payment processing failed: " . $paymentResult['error']);
            return;
        }

        $paymentId = $paymentResult['payment_id'];
        logMessage("Payment saved with ID: $paymentId");

        // Generate activation code
        $codeResult = generateActivationCode($userId, $paymentId);
        $activationCode = $codeResult['code'];
        logMessage("Activation code generated: $activationCode");

        // Create subscription
        $subscriptionResult = createOrUpdateSubscription($userId, $activationCode, $paymentId);
        logMessage("Subscription created for user $userId");

        // Send success message
        $successMessage = "✅ Payment verified!\n\nYour activation code: $activationCode\n\nThis code expires in 20 minutes. Use it in the app to activate your 30-day subscription.";

        sendWhatsAppMessage($senderPhone, $successMessage);
        logMessage("Success message sent to $senderPhone");

    } catch (Exception $e) {
        logMessage("Processing error: " . $e->getMessage(), 'ERROR');
        sendWhatsAppMessage($senderPhone, "❌ An error occurred while processing your payment. Please try again later.");
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

                                // Process the EcoCash payment
                                processEcoCashPayment($text, $sender);
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