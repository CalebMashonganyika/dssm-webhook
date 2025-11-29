<?php
// admin/view_keys.php - View all unlock keys with filtering

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

// Get filtered keys
function getKeys($filter = 'all') {
    $pdo = getDBConnection();

    $where = '';
    if ($filter === 'active') {
        $where = 'WHERE used = 0 AND expires_at > NOW()';
    } elseif ($filter === 'used') {
        $where = 'WHERE used = 1';
    } elseif ($filter === 'expired') {
        $where = 'WHERE used = 0 AND expires_at <= NOW()';
    }

    $stmt = $pdo->query("SELECT id, `key`, created_at, expires_at, used, user_id FROM unlock_keys $where ORDER BY created_at DESC");
    return $stmt->fetchAll();
}

$filter = $_GET['filter'] ?? 'all';
$keys = getKeys($filter);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Keys - DSSM Admin</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1200px;
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
        .filters {
            margin-bottom: 20px;
        }
        .filter-btn {
            display: inline-block;
            padding: 8px 16px;
            margin: 0 5px;
            background: #f8f9fa;
            color: #333;
            text-decoration: none;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .filter-btn.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
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
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .status-used {
            color: #28a745;
            font-weight: bold;
        }
        .status-expired {
            color: #dc3545;
            font-weight: bold;
        }
        .status-active {
            color: #007bff;
            font-weight: bold;
        }
        .key-code {
            font-family: monospace;
            font-weight: bold;
        }
        .no-keys {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        .copy-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }
        .copy-btn:hover {
            background: #218838;
        }
    </style>
    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('Key copied to clipboard!');
            });
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 View Unlock Keys</h1>
            <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
        </div>

        <div class="filters">
            <a href="?filter=all" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">All Keys</a>
            <a href="?filter=active" class="filter-btn <?php echo $filter === 'active' ? 'active' : ''; ?>">Active</a>
            <a href="?filter=used" class="filter-btn <?php echo $filter === 'used' ? 'active' : ''; ?>">Used</a>
            <a href="?filter=expired" class="filter-btn <?php echo $filter === 'expired' ? 'active' : ''; ?>">Expired</a>
        </div>

        <?php if (empty($keys)): ?>
            <div class="no-keys">
                <h3>No keys found</h3>
                <p>No unlock keys match the current filter.</p>
                <a href="generate_key.php" class="btn">Generate New Key</a>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Key</th>
                        <th>Created</th>
                        <th>Expires</th>
                        <th>Status</th>
                        <th>User ID</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($keys as $key): ?>
                        <?php
                        $status = $key['used'] ? 'Used' : (strtotime($key['expires_at']) > time() ? 'Active' : 'Expired');
                        $statusClass = $key['used'] ? 'status-used' : (strtotime($key['expires_at']) > time() ? 'status-active' : 'status-expired');
                        ?>
                        <tr>
                            <td>
                                <span class="key-code"><?php echo htmlspecialchars($key['key']); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($key['created_at']); ?></td>
                            <td><?php echo htmlspecialchars($key['expires_at']); ?></td>
                            <td class="<?php echo $statusClass; ?>"><?php echo $status; ?></td>
                            <td><?php echo htmlspecialchars($key['user_id'] ?: 'N/A'); ?></td>
                            <td>
                                <button class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($key['key']); ?>')">Copy</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>