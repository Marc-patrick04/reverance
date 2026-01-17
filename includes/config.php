<?php
// Database configuration (PostgreSQL) - Render deployment ready
if (getenv('DATABASE_URL')) {
    // Parse the DATABASE_URL (format: postgresql://user:pass@host:port/dbname)
    $url = getenv('DATABASE_URL');
    if (preg_match('/postgresql:\/\/([^:]+):([^@]+)@([^:\/]+):?(\d+)?\/(.+)/', $url, $matches)) {
        $user = $matches[1];
        $pass = $matches[2];
        $host = $matches[3];
        $port = $matches[4] ?: 5432;
        $dbname = $matches[5];
        $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $pass);
    } else {
        // Fallback to parse_url if regex fails
        $dbUrl = parse_url($url);
        $host = $dbUrl['host'];
        $port = $dbUrl['port'] ?? 5432;
        $dbname = ltrim($dbUrl['path'], '/');
        $user = $dbUrl['user'];
        $pass = $dbUrl['pass'];
        $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $pass);
    }
} else {
    // Local development fallback
    $pdo = new PDO(
        "pgsql:host=localhost;port=5432;dbname=reverence",
        "postgres",
        "numugisha"
    );
}

// Set PDO attributes
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// Configure session for deployment
if (getenv('SESSION_SAVE_PATH')) {
    session_save_path(getenv('SESSION_SAVE_PATH'));
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function logAction($action, $details = '') {
    global $pdo;
    if (isLoggedIn()) {
        $stmt = $pdo->prepare(
            "INSERT INTO logs (user_id, action, details, created_at)
             VALUES (?, ?, ?, CURRENT_TIMESTAMP)"
        );
        $stmt->execute([$_SESSION['user_id'], $action, $details]);
    }
}

function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}
?>
