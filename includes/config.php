<?php
// Database configuration (PostgreSQL) - Render deployment ready
if (getenv('DATABASE_URL')) {
    // Parse DATABASE_URL for Render deployment
    $database_url = parse_url(getenv('DATABASE_URL'));
    $pdo = new PDO(
        "pgsql:host=" . $database_url['host'] . ";port=" . $database_url['port'] . ";dbname=" . ltrim($database_url['path'], '/'),
        $database_url['user'],
        $database_url['pass']
    );
} else {
    // Local development fallback
    define('DB_HOST', 'localhost');
    define('DB_PORT', '5432');
    define('DB_NAME', 'reverence');
    define('DB_USER', 'postgres');
    define('DB_PASS', 'numugisha');

    $pdo = new PDO(
        "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS
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
