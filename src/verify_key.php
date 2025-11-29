<?php
// src/verify_key.php - API endpoint for verifying unlock keys
// Accepts POST with user_id and unlock_key, returns JSON response

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

$userId = trim($input['user_id'] ?? '');
$unlockKey = trim($input['unlock_key'] ?? '');

if (!$userId || !$unlockKey) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'user_id and unlock_key required']);
    exit;
}

try {
    $pdo = getDBConnection();

    // Check if key exists, is unused, and not expired
    $stmt = $pdo->prepare("
        SELECT id
        FROM unlock_keys
        WHERE `key` = ? AND used = 0 AND expires_at > NOW()
    ");
    $stmt->execute([$unlockKey]);
    $keyRecord = $stmt->fetch();

    if (!$keyRecord) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid or expired unlock key'
        ]);
        exit;
    }

    // Mark key as used and assign to user
    $stmt = $pdo->prepare("
        UPDATE unlock_keys
        SET used = 1, user_id = ?
        WHERE id = ?
    ");
    $stmt->execute([$userId, $keyRecord['id']]);

    // Return success - premium features unlocked
    echo json_encode([
        'success' => true,
        'message' => 'Premium features unlocked!',
        'premium_until' => date('c', strtotime('+5 minutes')),
        'duration_minutes' => 5
    ]);

} catch (Exception $e) {
    error_log("Verify key error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
?>