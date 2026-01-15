<?php
require_once '../includes/config.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}

// Get logs with pagination
$page = $_GET['page'] ?? 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Get total count
$totalLogs = $pdo->query("SELECT COUNT(*) FROM logs")->fetchColumn();
$totalPages = ceil($totalLogs / $perPage);

// Get logs
$stmt = $pdo->prepare("
    SELECT l.*, u.username
    FROM logs l
    LEFT JOIN users u ON l.user_id = u.id
    ORDER BY l.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->bindValue(1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

// Get log statistics
$todayLogs = $pdo->query("SELECT COUNT(*) FROM logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$thisWeekLogs = $pdo->query("SELECT COUNT(*) FROM logs WHERE YEARWEEK(created_at) = YEARWEEK(CURDATE())")->fetchColumn();
$thisMonthLogs = $pdo->query("SELECT COUNT(*) FROM logs WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())")->fetchColumn();

// Get most active users
$activeUsers = $pdo->query("
    SELECT u.username, COUNT(l.id) as log_count
    FROM logs l
    LEFT JOIN users u ON l.user_id = u.id
    WHERE u.username IS NOT NULL
    GROUP BY u.id, u.username
    ORDER BY log_count DESC
    LIMIT 5
")->fetchAll();

// Get action types
$actionStats = $pdo->query("
    SELECT action, COUNT(*) as count
    FROM logs
    GROUP BY action
    ORDER BY count DESC
    LIMIT 10
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - Reverence Worship Team</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <div class="admin-layout">
        <div class="admin-sidebar">
            <div class="logo-container">
                <img src="../assets/Logo Reverence-Photoroom.png" alt="Reverence WorshipTeam Logo" class="logo">
            </div>
            <div class="sidebar-title">
                <h2>Admin Panel</h2>
            </div>
            <nav>
                <a href="dashboard.php">Dashboard</a>
                <a href="singers.php">Manage Singers</a>
                <a href="groups.php">Manage Groups</a>
                <a href="reports.php">Reports</a>
                <a href="images.php">Manage Images</a>
                <a href="logs.php" class="active">View Logs</a>

                <a href="../logout.php">Logout</a>
            </nav>
        </div>

        <div class="admin-main">
            <div class="admin-header">
                <h1>Activity Logs & Transparency</h1>
            </div>

            <div class="admin-content">
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Today's Activity</h3>
                        <p class="stat-number"><?php echo $todayLogs; ?></p>
                        <p>log entries</p>
                    </div>
                    <div class="stat-card">
                        <h3>This Week</h3>
                        <p class="stat-number"><?php echo $thisWeekLogs; ?></p>
                        <p>log entries</p>
                    </div>
                    <div class="stat-card">
                        <h3>This Month</h3>
                        <p class="stat-number"><?php echo $thisMonthLogs; ?></p>
                        <p>log entries</p>
                    </div>
                    <div class="stat-card">
                        <h3>Total Logs</h3>
                        <p class="stat-number"><?php echo $totalLogs; ?></p>
                        <p>all time</p>
                    </div>
                </div>

                <div class="stats-section">
                    <div class="stats-container">
                        <h3>Most Active Users</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Activity Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activeUsers as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo $user['log_count']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="stats-container">
                        <h3>Common Actions</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>Action</th>
                                    <th>Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($actionStats as $stat): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($stat['action']); ?></td>
                                        <td><?php echo $stat['count']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <h3>Detailed Activity Log</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($log['username'] ?: 'System'); ?></td>
                                    <td><?php echo htmlspecialchars($log['action']); ?></td>
                                    <td><?php echo htmlspecialchars($log['details'] ?: 'N/A'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>" class="btn">Previous</a>
                        <?php endif; ?>

                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);

                        if ($startPage > 1): ?>
                            <a href="?page=1" class="btn">1</a>
                            <?php if ($startPage > 2): ?>
                                <span>...</span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <a href="?page=<?php echo $i; ?>" class="btn <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>

                        <?php if ($endPage < $totalPages): ?>
                            <?php if ($endPage < $totalPages - 1): ?>
                                <span>...</span>
                            <?php endif; ?>
                            <a href="?page=<?php echo $totalPages; ?>" class="btn"><?php echo $totalPages; ?></a>
                        <?php endif; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>" class="btn">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="transparency-note">
                    <h3>Transparency Commitment</h3>
                    <p>All administrative actions are logged and visible to maintain fairness and accountability in our choir division process. This ensures:</p>
                    <ul>
                        <li>No favoritism in group assignments</li>
                        <li>Complete audit trail of all changes</li>
                        <li>Accountability for all administrative actions</li>
                        <li>Transparency for all choir members</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <div class="footer-content">
            <div class="footer-brand">
                <img src="../assets/Logo Reverence-Photoroom.png" alt="Reverence WorshipTeam Logo" class="footer-logo">
                <h3>Reverence WorshipTeam</h3>
                <p>Activity Logs - Maintaining transparency and accountability in gospel choir ministry.</p>
            </div>

            <div class="footer-section footer-scripture">
                <blockquote>
                    <p><strong>Psalm 96:7-9</strong></p>
                    <p>Give praise to the Lord, you who belong to all peoples, give glory to him and take up his praise.</p>
                </blockquote>
            </div>

            <div class="footer-section">
                <h4>System Monitoring</h4>
                <p><a href="logs.php">View All Logs</a></p>
                <p><a href="dashboard.php">Dashboard Stats</a></p>
                <p><a href="singers.php">User Management</a></p>
                <p><a href="dashboard.php">← Back to Dashboard</a></p>
            </div>

            <div class="footer-section">
                <h4>Transparency</h4>
                <p>• Audit Trail</p>
                <p>• Activity Monitoring</p>
                <p>• Accountability</p>
                <p>• Fair Practices</p>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="copyright">
                <p>&copy; 2026 Reverence WorshipTeam. All rights reserved.</p>
                <p>Made with <span class="heart">❤️</span> for gospel ministry</p>
            </div>
        </div>
    </footer>

    <script src="../js/main.js"></script>
</body>
</html>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin: 2rem 0;
}

.stat-card {
    border: 2px solid #000;
    padding: 1.5rem;
    border-radius: 8px;
    text-align: center;
    background-color: #f9f9f9;
}

.stat-number {
    font-size: 2.5rem;
    font-weight: bold;
    color: #000;
    margin: 0.5rem 0;
}

.stats-section {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
    margin: 2rem 0;
}

.stats-container {
    border: 2px solid #000;
    padding: 1rem;
    border-radius: 8px;
}

.stats-container h3 {
    margin-bottom: 1rem;
    text-align: center;
}

.pagination {
    text-align: center;
    margin: 2rem 0;
}

.pagination .btn {
    margin: 0 0.25rem;
}

.pagination .btn.active {
    background-color: #000;
    color: #fff;
}

.transparency-note {
    background-color: #f9f9f9;
    border: 2px solid #000;
    padding: 1.5rem;
    border-radius: 8px;
    margin: 2rem 0;
}

.transparency-note ul {
    margin: 1rem 0;
    padding-left: 2rem;
}

.transparency-note li {
    margin: 0.5rem 0;
}
</style>
