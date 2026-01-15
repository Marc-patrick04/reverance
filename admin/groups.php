<?php
require_once '../includes/config.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}

$message = '';
$action = $_GET['action'] ?? 'list';
$editId = $_GET['id'] ?? null;
$viewId = $_GET['id'] ?? null;
$editGroup = null;
$viewGroup = null;

if ($action === 'edit' && $editId) {
    $stmt = $pdo->prepare("SELECT * FROM groups WHERE id = ?");
    $stmt->execute([$editId]);
    $editGroup = $stmt->fetch();
    if (!$editGroup) {
        $message = 'Group not found.';
        $action = 'list';
    }
} elseif ($action === 'view' && $viewId) {
    $stmt = $pdo->prepare("SELECT * FROM groups WHERE id = ?");
    $stmt->execute([$viewId]);
    $viewGroup = $stmt->fetch();
    if (!$viewGroup) {
        $message = 'Group not found.';
        $action = 'list';
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_groups'])) {
        $groupCount = (int)$_POST['group_count'];
        $groupNames = $_POST['group_names'];
        $serviceDate = $_POST['service_date'];

        // Validate inputs
        if ($groupCount < 1) {
            $message = 'Number of services must be at least 1.';
        } elseif (empty($serviceDate)) {
            $message = 'Service date is required.';
        } else {
            // Run mixing algorithm
            $result = runMixingAlgorithm($groupCount);

            if ($result['success']) {
                // Create groups in database
                $pdo->beginTransaction();
                try {
                    // Unpublish existing groups
                    $pdo->exec("UPDATE groups SET is_published = 0");

                    $groupIds = [];
                    foreach ($groupNames as $index => $name) {
                        $serviceOrder = $index + 1;
                        $stmt = $pdo->prepare("INSERT INTO groups (name, service_date, service_order, is_published, created_by) VALUES (?, ?, ?, 1, ?)");
                        $stmt->execute([$name, $serviceDate, $serviceOrder, $_SESSION['user_id']]);
                        $groupIds[] = $pdo->lastInsertId();
                    }

                    // Assign singers to groups
                    foreach ($result['assignments'] as $groupIndex => $singers) {
                        foreach ($singers as $singerId) {
                            $stmt = $pdo->prepare("INSERT INTO group_assignments (group_id, singer_id) VALUES (?, ?)");
                            $stmt->execute([$groupIds[$groupIndex], $singerId]);
                        }
                    }

                    $pdo->commit();
                    $message = 'Groups created and published successfully!';
                    logAction('create_groups', "Created $groupCount groups for $serviceDate: " . implode(', ', $groupNames));
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $message = 'Error creating groups: ' . $e->getMessage();
                }
            } else {
                $message = $result['message'];
            }
        }
    } elseif (isset($_POST['publish_groups'])) {
        $groupId = $_POST['group_id'];
        try {
            // Get current status
            $stmt = $pdo->prepare("SELECT is_published FROM groups WHERE id = ?");
            $stmt->execute([$groupId]);
            $current = $stmt->fetch();

            if ($current) {
                $newStatus = $current['is_published'] ? 0 : 1;
                $stmt = $pdo->prepare("UPDATE groups SET is_published = ? WHERE id = ?");
                $stmt->execute([$newStatus, $groupId]);
                $message = $newStatus ? 'Group published successfully!' : 'Group unpublished successfully!';
                logAction('publish_groups', ($newStatus ? 'Published' : 'Unpublished') . " group ID: $groupId");
            }
        } catch (PDOException $e) {
            $message = 'Error updating group status: ' . $e->getMessage();
        }
    } elseif (isset($_POST['delete_groups'])) {
        $groupId = $_POST['group_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM groups WHERE id = ?");
            $stmt->execute([$groupId]);
            $message = 'Groups deleted successfully!';
            logAction('delete_groups', "Deleted group ID: $groupId");
        } catch (PDOException $e) {
            $message = 'Error deleting groups: ' . $e->getMessage();
        }
    } elseif (isset($_POST['update_group'])) {
        $groupId = $_POST['group_id'];
        $groupName = $_POST['group_name'];
        $serviceDate = $_POST['service_date'];
        $serviceOrder = (int)$_POST['service_order'];
        $isPublished = isset($_POST['is_published']) ? 1 : 0;

        try {
            $stmt = $pdo->prepare("UPDATE groups SET name = ?, service_date = ?, service_order = ?, is_published = ? WHERE id = ?");
            $stmt->execute([$groupName, $serviceDate, $serviceOrder, $isPublished, $groupId]);
            $message = 'Group updated successfully!';
            logAction('update_group', "Updated group ID: $groupId");
            header('Location: groups.php?action=history');
            exit;
        } catch (PDOException $e) {
            $message = 'Error updating group: ' . $e->getMessage();
        }
    }
}

// Get published groups
$publishedGroups = $pdo->query("SELECT * FROM groups WHERE is_published = 1 ORDER BY created_at DESC")->fetchAll();

// Get all groups for history
$allGroups = $pdo->query("SELECT g.*, u.username as creator FROM groups g LEFT JOIN users u ON g.created_by = u.id ORDER BY g.created_at DESC LIMIT 20")->fetchAll();

function runMixingAlgorithm($numGroups) {
    global $pdo;

    // Get all active singers
    $singers = $pdo->query("SELECT * FROM singers WHERE status = 'Active' ORDER BY voice_category, voice_level DESC, full_name")->fetchAll();

    if (empty($singers)) {
        return ['success' => false, 'message' => 'No active singers found.'];
    }

    // Group singers by voice category and level
    $voiceGroups = [];
    foreach ($singers as $singer) {
        $voiceGroups[$singer['voice_category']][$singer['voice_level']][] = $singer['id'];
    }

    $assignments = array_fill(0, $numGroups, []);

    // Process each voice category
    $voices = ['Soprano', 'Alto', 'Tenor', 'Bass'];
    foreach ($voices as $voice) {
        if (!isset($voiceGroups[$voice])) continue;

        // First, distribute Good singers
        if (isset($voiceGroups[$voice]['Good'])) {
            $goodSingers = $voiceGroups[$voice]['Good'];
            $assignments = distributeSingersEvenly($goodSingers, $assignments);
        }

        // Then, distribute Normal singers
        if (isset($voiceGroups[$voice]['Normal'])) {
            $normalSingers = $voiceGroups[$voice]['Normal'];
            $assignments = distributeSingersEvenly($normalSingers, $assignments);
        }
    }

    // Validate results
    $groupSizes = array_map('count', $assignments);
    $maxSize = max($groupSizes);
    $minSize = min($groupSizes);

    if ($maxSize - $minSize > 1) {
        return ['success' => false, 'message' => 'Unable to balance group sizes. Max difference exceeded.'];
    }

    // Check that each group has all voice categories (if possible)
    $voiceCounts = [];
    foreach ($assignments as $groupIndex => $singerIds) {
        $voiceCounts[$groupIndex] = [];
        foreach ($singerIds as $singerId) {
            $stmt = $pdo->prepare("SELECT voice_category FROM singers WHERE id = ?");
            $stmt->execute([$singerId]);
            $voice = $stmt->fetch()['voice_category'];
            $voiceCounts[$groupIndex][$voice] = ($voiceCounts[$groupIndex][$voice] ?? 0) + 1;
        }
    }

    // Check for missing voices
    $warnings = [];
    foreach ($voiceCounts as $groupIndex => $counts) {
        foreach ($voices as $voice) {
            if (!isset($counts[$voice])) {
                $warnings[] = "Group " . ($groupIndex + 1) . " is missing $voice singers.";
            }
        }
    }

    return [
        'success' => true,
        'assignments' => $assignments,
        'warnings' => $warnings,
        'group_sizes' => $groupSizes
    ];
}

function distributeSingersEvenly($singerIds, $currentAssignments) {
    $numGroups = count($currentAssignments);
    $singerIds = array_values($singerIds);

    // Sort groups by current size (smallest first)
    $groupIndices = range(0, $numGroups - 1);
    usort($groupIndices, function($a, $b) use ($currentAssignments) {
        return count($currentAssignments[$a]) - count($currentAssignments[$b]);
    });

    $result = $currentAssignments;

    foreach ($singerIds as $index => $singerId) {
        $groupIndex = $groupIndices[$index % $numGroups];
        $result[$groupIndex][] = $singerId;
    }

    return $result;
}

function getServiceTimeDisplay($serviceOrder) {
    $suffixes = ['th', 'st', 'nd', 'rd'];
    $value = $serviceOrder % 100;

    if ($value >= 11 && $value <= 13) {
        $suffix = 'th';
    } else {
        $suffix = $suffixes[$value % 10] ?? 'th';
    }

    return $serviceOrder . $suffix . ' Service';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Groups - Reverence Worship Team</title>
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
                <a href="groups.php" class="active">Manage Groups</a>
                <a href="reports.php">Reports</a>
                <a href="images.php">Manage Images</a>
                <a href="logs.php">View Logs</a>
                <a href="../logout.php">Logout</a>
            </nav>
        </div>

        <div class="admin-main">
            <div class="admin-header">
                <h1>Manage Groups</h1>
            </div>

            <!-- Horizontal Navigation -->
            <div class="horizontal-nav">
                <a href="groups.php" class="nav-tab <?php echo $action === 'list' ? 'active' : ''; ?>">
                    📋 Published Groups
                </a>
                <a href="groups.php?action=history" class="nav-tab <?php echo $action === 'history' ? 'active' : ''; ?>">
                    📚 Group History
                </a>
                <a href="groups.php?action=create" class="nav-tab <?php echo $action === 'create' ? 'active' : ''; ?>">
                    ➕ Create Groups
                </a>
            </div>

            <div class="admin-content">
                <?php if ($message): ?>
                    <div class="message <?php echo strpos($message, 'Error') === 0 ? 'error' : 'success'; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($action === 'create'): ?>
                    <div class="form-container">
                        <h3>Create New Groups</h3>
                        <form method="POST" id="create-groups-form">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="service_date">Service Date:</label>
                                    <input type="date" id="service_date" name="service_date" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="group_count">Number of Groups:</label>
                                    <input type="number" id="group_count" name="group_count" min="1" value="1" required>
                                    <small>Number of groups on this date</small>
                                </div>
                            </div>
                            <div id="group_names_container">
                                <!-- Dynamic group name inputs will be added here -->
                            </div>
                            <div class="form-actions">
                                <button type="submit" name="create_groups" class="btn">Create Groups</button>
                                <a href="groups.php" class="btn">Cancel</a>
                            </div>
                        </form>
                    </div>
                <?php elseif ($action === 'edit' && $editGroup): ?>
                    <div class="form-container">
                        <h3>Edit Group: <?php echo htmlspecialchars($editGroup['name']); ?></h3>
                        <form method="POST">
                            <input type="hidden" name="group_id" value="<?php echo $editGroup['id']; ?>">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="group_name">Group Name:</label>
                                    <input type="text" id="group_name" name="group_name" value="<?php echo htmlspecialchars($editGroup['name']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="service_date">Service Date:</label>
                                    <input type="date" id="service_date" name="service_date" value="<?php echo $editGroup['service_date'] ?? date('Y-m-d', strtotime($editGroup['created_at'])); ?>" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="service_order">Service Order:</label>
                                    <input type="number" id="service_order" name="service_order" min="1" value="<?php echo $editGroup['service_order'] ?? 1; ?>" required>
                                    <small>Order of this service for the day</small>
                                </div>
                                <div class="form-group">
                                    <label for="is_published">Publish Status:</label>
                                    <div style="margin-top: 0.5rem;">
                                        <input type="checkbox" id="is_published" name="is_published" <?php echo $editGroup['is_published'] ? 'checked' : ''; ?>>
                                        <label for="is_published" style="display: inline; margin-left: 0.5rem;">Publish this group</label>
                                    </div>
                                </div>
                            </div>
                            <div class="form-actions">
                                <button type="submit" name="update_group" class="btn">Update Group</button>
                                <a href="groups.php?action=history" class="btn">Cancel</a>
                            </div>
                        </form>
                    </div>
                <?php elseif ($action === 'view' && $viewGroup): ?>
                    <div class="form-container">
                        <h3>View Group: <?php echo htmlspecialchars($viewGroup['name']); ?></h3>
                        <p class="group-meta">Service Date: <?php echo date('M j, Y', strtotime($viewGroup['service_date'] ?? $viewGroup['created_at'])); ?> • Status: <?php echo $viewGroup['is_published'] ? 'Published' : 'Draft'; ?></p>

                        <div class="voice-categories">
                            <?php
                            $stmt = $pdo->prepare("
                                SELECT s.* FROM singers s
                                JOIN group_assignments ga ON s.id = ga.singer_id
                                WHERE ga.group_id = ?
                                ORDER BY s.voice_category, s.voice_level DESC
                            ");
                            $stmt->execute([$viewGroup['id']]);
                            $groupSingers = $stmt->fetchAll();

                            $voiceData = [];
                            foreach ($groupSingers as $singer) {
                                $voiceData[$singer['voice_category']][] = $singer;
                            }

                            $voices = ['Soprano', 'Alto', 'Tenor', 'Bass'];
                            foreach ($voices as $voice): ?>
                                <div class="voice-category">
                                    <h5><?php echo $voice; ?> <span class="count">(<?php echo isset($voiceData[$voice]) ? count($voiceData[$voice]) : 0; ?>)</span></h5>
                                    <ul>
                                        <?php
                                        if (isset($voiceData[$voice])) {
                                            foreach ($voiceData[$voice] as $singer): ?>
                                                <li><?php echo htmlspecialchars($singer['full_name']); ?> <small>(<?php echo $singer['voice_level']; ?>)</small></li>
                                            <?php endforeach;
                                        } else {
                                            echo '<li><em>No singers assigned</em></li>';
                                        }
                                        ?>
                                    </ul>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="form-actions">
                            <a href="groups.php?action=history" class="btn">Back to Groups</a>
                        </div>
                    </div>

                <?php elseif ($action === 'history'): ?>
                    <h3>Group History</h3>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Group Name</th>
                                    <th>Service Date</th>
                                    <th>Created</th>
                                    <th>Creator</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allGroups as $group): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($group['name']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($group['service_date'] ?? $group['created_at'])); ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($group['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($group['creator'] ?: 'System'); ?></td>
                                        <td><?php echo $group['is_published'] ? 'Published' : 'Draft'; ?></td>
                                        <td>
                                            <a href="groups.php?action=view&id=<?php echo $group['id']; ?>" class="btn">View</a>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                                                <button type="submit" name="publish_groups" class="btn <?php echo $group['is_published'] ? 'btn-success' : ''; ?>">
                                                    <?php echo $group['is_published'] ? 'Unpublish' : 'Publish'; ?>
                                                </button>
                                            </form>
                                            <a href="groups.php?action=edit&id=<?php echo $group['id']; ?>" class="btn">Edit</a>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                                                <button type="submit" name="delete_groups" class="btn btn-delete" onclick="return confirm('Are you sure?')">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div style="margin-bottom: 2rem;">
                        <a href="groups.php?action=create" class="btn">Create New Groups</a>
                    </div>

                    <h3>Group History</h3>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Group Name</th>
                                    <th>Service Date</th>
                                    <th>Created</th>
                                    <th>Creator</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allGroups as $group): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($group['name']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($group['service_date'] ?? $group['created_at'])); ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($group['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($group['creator'] ?: 'System'); ?></td>
                                        <td><?php echo $group['is_published'] ? 'Published' : 'Draft'; ?></td>
                                        <td>
                                            <a href="groups.php?action=view&id=<?php echo $group['id']; ?>" class="btn"> View</a>
                                            <?php if (!$group['is_published']): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                                                    <button type="submit" name="publish_groups" class="btn">Publish</button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                                                <button type="submit" name="delete_groups" class="btn btn-delete" onclick="return confirm('Are you sure?')">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer>
        <div class="footer-content">
            <div class="footer-brand">
                <img src="../assets/Logo Reverence-Photoroom.png" alt="Reverence WorshipTeam Logo" class="footer-logo">
                <h3>Reverence WorshipTeam</h3>
                <p>Group Management - Creating balanced gospel choir formations through intelligent algorithms.</p>
            </div>

            <div class="footer-section footer-scripture">
                <blockquote>
                    <p><strong>Psalm 96:7-9</strong></p>
                    <p>Give praise to the Lord, you who belong to all peoples, give glory to him and take up his praise.</p>
                </blockquote>
            </div>

            <div class="footer-section">
                <h4>Group Actions</h4>
                <p><a href="groups.php?action=create">Create New Groups</a></p>
                <p><a href="singers.php">Manage Singers</a></p>
                <p><a href="images.php">Upload Images</a></p>
                <p><a href="dashboard.php">← Back to Dashboard</a></p>
            </div>

            <div class="footer-section">
                <h4>Algorithm Features</h4>
                <p>• Quality Balance</p>
                <p>• Voice Distribution</p>
                <p>• Size Optimization</p>
                <p>• Fair Assignments</p>
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
    <script>
        // Dynamic group name inputs
        document.getElementById('group_count').addEventListener('change', function() {
            const count = parseInt(this.value) || 0;
            const container = document.getElementById('group_names_container');

            // Clear existing inputs
            container.innerHTML = '';

            // Add new inputs
            for (let i = 1; i <= count; i++) {
                const inputGroup = document.createElement('div');
                inputGroup.className = 'form-group';
                inputGroup.innerHTML = `
                    <label for="group_name_${i}">Group ${i} Name:</label>
                    <input type="text" id="group_name_${i}" name="group_names[]" value="Service ${i}" required>
                `;
                container.appendChild(inputGroup);
            }
        });

        // Trigger change event on page load to show initial inputs
        document.getElementById('group_count').dispatchEvent(new Event('change'));
    </script>
</body>
</html>
