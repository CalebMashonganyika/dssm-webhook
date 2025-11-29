<?php
// admin/generate_key.php - Backend logic to generate new unlock keys

session_start();

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

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
            die("Database connection failed: " . $e->getMessage());
        }
    }
    return $pdo;
}

// Generate secure random key in format XXXX-XXXX-XXXX
function generateUnlockKey() {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $key = '';

    for ($i = 0; $i < 12; $i++) {
        if ($i > 0 && $i % 4 === 0) {
            $key .= '-';
        }
        $key .= $characters[random_int(0, strlen($characters) - 1)];
    }

    return $key;
}

// Handle key generation
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_key'])) {
    try {
        $pdo = getDBConnection();

        // Generate unique key
        $key = generateUnlockKey();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));

        // Check if key already exists (very unlikely but safe)
        $stmt = $pdo->prepare("SELECT id FROM unlock_keys WHERE `key` = ?");
        $stmt->execute([$key]);
        if ($stmt->fetch()) {
            // Regenerate if collision
            $key = generateUnlockKey();
        }

        // Insert new key
        $stmt = $pdo->prepare("
            INSERT INTO unlock_keys (`key`, expires_at, used, created_at)
            VALUES (?, ?, 0, NOW())
        ");
        $stmt->execute([$key, $expiresAt]);

        $message = "✅ Key generated successfully: <strong>$key</strong><br>Expires at: $expiresAt";
        $messageType = 'success';

    } catch (Exception $e) {
        $message = "❌ Error generating key: " . $e->getMessage();
        $messageType = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Key - DSSM Admin</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
        }
        .btn:hover {
            background: #0056b3;
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-secondary:hover {
            background: #545b62;
        }
        .generate-form {
            text-align: center;
            margin-bottom: 30px;
        }
        .key-display {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 4px;
            margin: 20px 0;
            border-left: 4px solid #007bff;
        }
        .success { border-left-color: #28a745; }
        .error { border-left-color: #dc3545; }
        .key-code {
            font-family: monospace;
            font-size: 1.2em;
            font-weight: bold;
            color: #007bff;
        }
        .instructions {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔑 Generate New Unlock Key</h1>
            <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
        </div>

        <div class="instructions">
            <h3>How to use:</h3>
            <ol>
                <li>Click "Generate Key" to create a new one-time unlock key</li>
                <li>Copy the key and send it to the user manually</li>
                <li>The key expires in 5 minutes and can only be used once</li>
                <li>User enters the key in the app to unlock premium features</li>
            </ol>
        </div>

        <?php if ($message): ?>
            <div class="key-display <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="generate-form">
            <button type="submit" name="generate_key" class="btn">🎯 Generate New Key</button>
        </form>

        <div style="text-align: center; margin-top: 30px;">
            <a href="dashboard.php" class="btn btn-secondary">View Dashboard</a>
        </div>
    </div>
</body>
</html>