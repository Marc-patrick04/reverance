<?php
require_once '../includes/config.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}

// Get comprehensive statistics
$singerCount = $pdo->query("SELECT COUNT(*) FROM singers WHERE status = 'Active'")->fetchColumn();
$totalSingers = $pdo->query("SELECT COUNT(*) FROM singers")->fetchColumn();
$inactiveSingers = $pdo->query("SELECT COUNT(*) FROM singers WHERE status = 'Inactive'")->fetchColumn();
$groupCount = $pdo->query("SELECT COUNT(*) FROM groups WHERE is_published = true")->fetchColumn();
$totalGroups = $pdo->query("SELECT COUNT(*) FROM groups")->fetchColumn();
$logCount = $pdo->query("SELECT COUNT(*) FROM logs WHERE DATE(created_at) = CURRENT_DATE")->fetchColumn();
$totalLogs = $pdo->query("SELECT COUNT(*) FROM logs")->fetchColumn();

// Voice category breakdown
$voiceStats = $pdo->query("
    SELECT voice_category, COUNT(*) as count
    FROM singers
    WHERE status = 'Active'
    GROUP BY voice_category
    ORDER BY count DESC
")->fetchAll(PDO::FETCH_KEY_PAIR);

// Recent additions
$recentSingers = $pdo->query("
    SELECT full_name, voice_category, voice_level, created_at
    FROM singers
    ORDER BY created_at DESC
    LIMIT 5
")->fetchAll();

// Recent groups
$recentGroups = $pdo->query("
    SELECT name, is_published, created_at
    FROM groups
    ORDER BY created_at DESC
    LIMIT 3
")->fetchAll();

// System health
$imagesCount = $pdo->query("SELECT COUNT(*) FROM landing_images")->fetchColumn();
$activeImage = $pdo->query("SELECT COUNT(*) FROM landing_images WHERE is_active = true")->fetchColumn();


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Reverence Worship Team</title>
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
                <a href="dashboard.php" class="active">Dashboard</a>
                <a href="singers.php">Manage Singers</a>
                <a href="groups.php">Manage Groups</a>
                <a href="reports.php">Reports</a>
                <a href="images.php">Manage Images</a>
                <a href="logs.php">View Logs</a>
                <a href="../logout.php">Logout</a>
            </nav>
        </div>

        <div class="admin-main">
            <div class="admin-header">
                <h1>Dashboard</h1>
            </div>

            <div class="admin-content">
                <div class="welcome-section">
                    <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
                    <p>Here's what's happening with your gospel choir management system.</p>
                </div>

                <!-- Main Statistics Grid -->
                <div class="dashboard-section">
                    <h3>📊 Key Metrics</h3>
                    <div class="dashboard-grid">
                        <div class="dashboard-card primary">
                            <div class="card-icon">🎤</div>
                            <h3><?php echo $singerCount; ?></h3>
                            <p>Active Singers</p>
                            <small><?php echo $totalSingers; ?> total (<?php echo $inactiveSingers; ?> inactive)</small>
                        </div>

                        <div class="dashboard-card primary">
                            <div class="card-icon">👥</div>
                            <h3><?php echo $groupCount; ?></h3>
                            <p>Published Groups</p>
                            <small><?php echo $totalGroups; ?> total created</small>
                        </div>

                        <div class="dashboard-card secondary">
                            <div class="card-icon">📈</div>
                            <h3><?php echo $logCount; ?></h3>
                            <p>Today's Activity</p>
                            <small><?php echo $totalLogs; ?> total logged</small>
                        </div>

                        <div class="dashboard-card secondary">
                            <div class="card-icon">🖼️</div>
                            <h3><?php echo $imagesCount; ?></h3>
                            <p>Landing Images</p>
                            <small><?php echo $activeImage; ?> currently active</small>
                        </div>
                    </div>
                </div>

                <!-- Voice Distribution Chart -->
                <div class="dashboard-section">
                    <h3>🎵 Voice Category Distribution</h3>
                    <div class="voice-chart">
                        <?php foreach ($voiceStats as $voice => $count): ?>
                            <?php
                            $percentage = $singerCount > 0 ? round(($count / $singerCount) * 100) : 0;
                            $color = match($voice) {
                                'Soprano' => '#FFD700',
                                'Alto' => '#FFA500',
                                'Tenor' => '#FF6347',
                                'Bass' => '#DC143C',
                                default => '#666'
                            };
                            ?>
                            <div class="voice-bar">
                                <div class="voice-label">
                                    <span><?php echo $voice; ?></span>
                                    <span><?php echo $count; ?> (<?php echo $percentage; ?>%)</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $percentage; ?>%; background-color: <?php echo $color; ?>;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Recent Additions and Activity -->
                <div class="dashboard-row">
                    <!-- Recent Singers -->
                    <div class="dashboard-section half">
                        <h3>🆕 Recent Singer Additions</h3>
                        <div class="recent-list">
                            <?php if (empty($recentSingers)): ?>
                                <p class="empty-state">No recent singers added</p>
                            <?php else: ?>
                                <?php foreach ($recentSingers as $singer): ?>
                                    <div class="recent-item">
                                        <div class="recent-info">
                                            <strong><?php echo htmlspecialchars($singer['full_name']); ?></strong>
                                            <small><?php echo $singer['voice_category']; ?> • <?php echo $singer['voice_level']; ?></small>
                                        </div>
                                        <div class="recent-date">
                                            <?php echo date('M j', strtotime($singer['created_at'])); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Groups -->
                    <div class="dashboard-section half">
                        <h3>🎼 Recent Group Activity</h3>
                        <div class="recent-list">
                            <?php if (empty($recentGroups)): ?>
                                <p class="empty-state">No recent groups</p>
                            <?php else: ?>
                                <?php foreach ($recentGroups as $group): ?>
                                    <div class="recent-item">
                                        <div class="recent-info">
                                            <strong><?php echo htmlspecialchars($group['name']); ?></strong>
                                            <small><?php echo $group['is_published'] ? 'Published' : 'Draft'; ?></small>
                                        </div>
                                        <div class="recent-date">
                                            <?php echo date('M j', strtotime($group['created_at'])); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>



                <!-- Quick Actions -->
                <div class="dashboard-section">
                    <h3>⚡ Quick Actions</h3>
                    <div class="quick-actions">
                        <a href="singers.php?action=add" class="action-btn primary">
                            <span class="action-icon">➕</span>
                            <span>Add New Singer</span>
                        </a>
                        <a href="groups.php?action=create" class="action-btn primary">
                            <span class="action-icon">🎼</span>
                            <span>Create Groups</span>
                        </a>
                        <a href="images.php" class="action-btn secondary">
                            <span class="action-icon">📸</span>
                            <span>Manage Images</span>
                        </a>
                        <a href="logs.php" class="action-btn secondary">
                            <span class="action-icon">📋</span>
                            <span>View Logs</span>
                        </a>
                        <a href="singers.php" class="action-btn info">
                            <span class="action-icon">👥</span>
                            <span>Manage Singers</span>
                        </a>
                        <a href="groups.php" class="action-btn info">
                            <span class="action-icon">🎵</span>
                            <span>Manage Groups</span>
                        </a>
                    </div>
                </div>


            </div>
        </div>
    </div>

    <footer>
        <div class="footer-content">
            <div class="footer-brand">
                <img src="../assets/Logo Reverence-Photoroom.png" alt="Reverence WorshipTeam Logo" class="footer-logo">
                <h3>Reverence WorshipTeam</h3>
                <p>Admin Panel - Managing gospel choir assignments with excellence and transparency.</p>
            </div>

            <div class="footer-section footer-scripture">
                <blockquote>
                    <p><strong>Psalm 96:7-9</strong></p>
                    <p>Give praise to the Lord, you who belong to all peoples, give glory to him and take up his praise.</p>
                </blockquote>
            </div>

            <div class="footer-section">
                <h4>Admin Links</h4>
                <p><a href="dashboard.php">Dashboard</a></p>
                <p><a href="singers.php">Manage Singers</a></p>
                <p><a href="groups.php">Manage Groups</a></p>
                <p><a href="logs.php">View Logs</a></p>
            </div>

            <div class="footer-section">
                <h4>Support</h4>
                <p><strong>Email:</strong><br>worshipteamkicukiro@gmail.com</p>
                <p><strong>Admin Access:</strong><br>admin / admin123</p>
                <p><a href="../index.php">← Back to Site</a></p>
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
