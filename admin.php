<?php
// admin.php - Admin router for DSSM Unlock Key System

// Get the request path and query string
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$parsedUrl = parse_url($requestUri);
$path = $parsedUrl['path'] ?? '';

// Handle different routing scenarios
if ($path === '/admin.php') {
    // Direct access to admin.php - show index
    $adminPath = 'index.php';
} elseif (strpos($path, '/admin.php/') === 0) {
    // Access like /admin.php/dashboard.php
    $adminPath = str_replace('/admin.php/', '', $path);
} else {
    // Fallback - assume direct access
    $adminPath = 'index.php';
}

// If no extension, add .php
if (!pathinfo($adminPath, PATHINFO_EXTENSION)) {
    $adminPath .= '.php';
}

// Build full path to admin file
$adminFile = __DIR__ . '/admin/' . $adminPath;

// Check if file exists and include it
if (file_exists($adminFile) && is_file($adminFile)) {
    include $adminFile;
    exit;
}

// If file not found, return 404
http_response_code(404);
echo "Admin page not found: $adminPath";
exit;
?>