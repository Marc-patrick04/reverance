<?php
// Database configuration (without DB name for initial connection)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'reverance_choir');

try {
    // Connect without database first
    $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
    $pdo->exec("USE " . DB_NAME);

    // Create users table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin', 'viewer') NOT NULL DEFAULT 'viewer',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Create singers table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS singers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(100) NOT NULL,
            voice_category ENUM('Soprano', 'Alto', 'Tenor', 'Bass') NOT NULL,
            voice_level ENUM('Good', 'Normal') NOT NULL,
            status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    // Create groups table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS groups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL,
            service_date DATE NOT NULL,
            service_order INT NOT NULL DEFAULT 1,
            is_published BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_by INT,
            FOREIGN KEY (created_by) REFERENCES users(id)
        )
    ");

    // Add new columns to existing groups table if they don't exist
    try {
        $pdo->exec("ALTER TABLE groups ADD COLUMN IF NOT EXISTS service_date DATE NOT NULL DEFAULT CURDATE()");
        $pdo->exec("ALTER TABLE groups ADD COLUMN IF NOT EXISTS service_order INT NOT NULL DEFAULT 1");
    } catch (PDOException $e) {
        // Column might already exist, continue
    }

    // Create group_assignments table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS group_assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            group_id INT NOT NULL,
            singer_id INT NOT NULL,
            assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
            FOREIGN KEY (singer_id) REFERENCES singers(id) ON DELETE CASCADE,
            UNIQUE KEY unique_assignment (group_id, singer_id)
        )
    ");

    // Create logs table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            action VARCHAR(255) NOT NULL,
            details TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");

    // Create landing_images table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS landing_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            image_path VARCHAR(255) NOT NULL,
            is_active BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Create monthly_schedule table for group planning
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS monthly_schedule (
            id INT AUTO_INCREMENT PRIMARY KEY,
            group_id INT NOT NULL,
            service_date DATE NOT NULL,
            service_time ENUM('1st Service', '2nd Service', '3rd Service') NOT NULL DEFAULT '1st Service',
            status ENUM('Scheduled', 'Confirmed', 'Cancelled') NOT NULL DEFAULT 'Scheduled',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
            UNIQUE KEY unique_schedule (group_id, service_date, service_time)
        )
    ");

    // Migrate existing monthly_schedule table if it has singer_id instead of group_id
    try {
        $result = $pdo->query("SHOW COLUMNS FROM monthly_schedule LIKE 'singer_id'");
        if ($result->rowCount() > 0) {
            // Rename singer_id to group_id
            $pdo->exec("ALTER TABLE monthly_schedule CHANGE singer_id group_id INT NOT NULL");
            // Update the unique key
            $pdo->exec("ALTER TABLE monthly_schedule DROP INDEX unique_schedule");
            $pdo->exec("ALTER TABLE monthly_schedule ADD UNIQUE KEY unique_schedule (group_id, service_date, service_time)");
        }
    } catch (PDOException $e) {
        // Table might not exist yet or migration already done, continue
    }

    // Insert default admin user (password: admin123)
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, password, role) VALUES (?, ?, 'admin')");
    $stmt->execute(['admin', $adminPassword]);

    // Insert test singers for demonstration (34 singers with various voice categories and levels)
   

    

    echo "Database setup completed successfully!<br>";
    echo "Default admin login: username 'admin', password 'admin123'<br>";
  
    echo "<a href='index.php'>Go to main site</a>";

} catch (PDOException $e) {
    echo "Database setup failed: " . $e->getMessage();
}
?>
