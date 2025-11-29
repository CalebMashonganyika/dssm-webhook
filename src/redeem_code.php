<?php
// redeem_code.php - API endpoint for redeeming activation codes
// Accepts POST with device_id and code, returns premium unlock status

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Load environment variables
$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_NAME = getenv('DB_NAME') ?: 'subscriptions';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';

// Database connection
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
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database connection failed']);
            exit;
        }
    }
    return $pdo;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

$code = trim($input['code'] ?? '');
$deviceId = trim($input['device_id'] ?? '');

if (!$code || !$deviceId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Code and device_id required']);
    exit;
}

try {
    $pdo = getDBConnection();

    // Check if code exists and is valid
    $stmt = $pdo->prepare("
        SELECT id, phone, expires_at, used
        FROM activation_codes
        WHERE code = ? AND used = 0 AND expires_at > NOW()
    ");
    $stmt->execute([$code]);
    $activationCode = $stmt->fetch();

    if (!$activationCode) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid or expired activation code'
        ]);
        exit;
    }

    // Mark code as used
    $stmt = $pdo->prepare("
        UPDATE activation_codes
        SET used = 1, used_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$activationCode['id']]);

    // Return success with premium unlock for 5 minutes
    echo json_encode([
        'success' => true,
        'message' => 'Premium features unlocked!',
        'premium_until' => date('c', strtotime('+5 minutes')),
        'duration_minutes' => 5
    ]);

} catch (Exception $e) {
    error_log("Redeem code error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
?>