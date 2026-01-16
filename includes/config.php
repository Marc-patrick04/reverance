<?php
// Database configuration (PostgreSQL)
define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_NAME', 'reverence');
define('DB_USER', 'postgres');     // change if different
define('DB_PASS', 'numugisha'); // put your PostgreSQL password

// Create PDO connection
try {
    $pdo = new PDO(
        "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
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
