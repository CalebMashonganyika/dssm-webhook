<?php
// admin/dashboard.php - Admin dashboard for managing unlock keys

session_start();

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Session timeout after 30 minutes of inactivity
if (time() - ($_SESSION['admin_login_time'] ?? 0) > 1800) {
    session_destroy();
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

// Get key statistics
function getKeyStats() {
    $pdo = getDBConnection();

    $stats = [];

    // Total keys
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM unlock_keys");
    $stats['total'] = $stmt->fetch()['total'];

    // Active (unused and not expired)
    $stmt = $pdo->query("SELECT COUNT(*) as active FROM unlock_keys WHERE used = 0 AND expires_at > NOW()");
    $stats['active'] = $stmt->fetch()['active'];

    // Used
    $stmt = $pdo->query("SELECT COUNT(*) as used FROM unlock_keys WHERE used = 1");
    $stats['used'] = $stmt->fetch()['used'];

    // Expired
    $stmt = $pdo->query("SELECT COUNT(*) as expired FROM unlock_keys WHERE used = 0 AND expires_at <= NOW()");
    $stats['expired'] = $stmt->fetch()['expired'];

    return $stats;
}

$stats = getKeyStats();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - DSSM Unlock Keys</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        .header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-label {
            color: #666;
        }
        .actions {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 5px;
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
        .recent-keys {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f8f9fa;
        }
        .status-used { color: #28a745; }
        .status-expired { color: #dc3545; }
        .status-active { color: #007bff; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔑 DSSM Unlock Keys Dashboard</h1>
        <a href="logout.php" class="btn btn-secondary">Logout</a>
    </div>

    <div class="stats">
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['total']; ?></div>
            <div class="stat-label">Total Keys</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['active']; ?></div>
            <div class="stat-label">Active Keys</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['used']; ?></div>
            <div class="stat-label">Used Keys</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['expired']; ?></div>
            <div class="stat-label">Expired Keys</div>
        </div>
    </div>

    <div class="actions">
        <h2>Actions</h2>
        <a href="generate_key.php" class="btn">Generate New Key</a>
        <a href="view_keys.php" class="btn btn-secondary">View All Keys</a>
    </div>

    <div class="recent-keys">
        <h2>Recent Keys</h2>
        <table>
            <thead>
                <tr>
                    <th>Key</th>
                    <th>Created</th>
                    <th>Expires</th>
                    <th>Status</th>
                    <th>User ID</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $pdo = getDBConnection();
                $stmt = $pdo->query("SELECT `key`, created_at, expires_at, used, user_id FROM unlock_keys ORDER BY created_at DESC LIMIT 10");
                while ($key = $stmt->fetch()) {
                    $status = $key['used'] ? 'Used' : (strtotime($key['expires_at']) > time() ? 'Active' : 'Expired');
                    $statusClass = $key['used'] ? 'status-used' : (strtotime($key['expires_at']) > time() ? 'status-active' : 'status-expired');
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($key['key']) . "</td>";
                    echo "<td>" . htmlspecialchars($key['created_at']) . "</td>";
                    echo "<td>" . htmlspecialchars($key['expires_at']) . "</td>";
                    echo "<td class='$statusClass'>$status</td>";
                    echo "<td>" . htmlspecialchars($key['user_id'] ?: 'N/A') . "</td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</body>
</html>