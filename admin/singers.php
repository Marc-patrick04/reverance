<?php
require_once '../includes/config.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}

$message = '';
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

// Function to convert technical database errors to user-friendly messages
function getUserFriendlyError($errorMessage) {
    if (strpos($errorMessage, 'SQLSTATE[23000]') !== false && strpos($errorMessage, 'Duplicate entry') !== false) {
        // Extract the duplicate value from the error message
        preg_match("/Duplicate entry '([^']+)' for key '([^']+)'/", $errorMessage, $matches);
        if (count($matches) >= 3) {
            $duplicateValue = $matches[1];
            $keyName = $matches[2];

            if ($keyName === 'full_name') {
                return "❌ <strong>Cannot Add Singer</strong><br>A singer with the name <strong>'$duplicateValue'</strong> already exists in the database.<br><small>Please use a different name or check if this person is already registered.</small>";
            }
        }
        return "❌ <strong>Duplicate Entry Error</strong><br>This information already exists in the system.<br><small>Please check your input and try again.</small>";
    }

    if (strpos($errorMessage, 'SQLSTATE[42000]') !== false) {
        return "❌ <strong>Database Syntax Error</strong><br>There was a problem with the database query.<br><small>Please contact the administrator.</small>";
    }

    if (strpos($errorMessage, 'SQLSTATE[HY000]') !== false) {
        return "❌ <strong>Database Connection Error</strong><br>Unable to connect to the database.<br><small>Please try again later or contact support.</small>";
    }

    if (strpos($errorMessage, 'foreign key constraint') !== false || strpos($errorMessage, 'foreign key') !== false) {
        return "❌ <strong>Data Relationship Error</strong><br>Cannot perform this action because it would break data relationships.<br><small>This record may be referenced by other data in the system.</small>";
    }

    // Generic fallback
    return "❌ <strong>Database Error</strong><br>An unexpected error occurred while processing your request.<br><small>Please try again or contact the administrator.</small>";
}

// Handle search and filters
$search = $_GET['search'] ?? '';
$voice_category_filter = $_GET['voice_category'] ?? '';
$voice_level_filter = $_GET['voice_level'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_singer'])) {
        $fullName = sanitize($_POST['full_name']);
        $voiceCategory = $_POST['voice_category'];
        $voiceLevel = $_POST['voice_level'];
        $status = $_POST['status'];
        $notes = sanitize($_POST['notes'] ?? '');

        try {
            $stmt = $pdo->prepare("INSERT INTO singers (full_name, voice_category, voice_level, status, notes) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$fullName, $voiceCategory, $voiceLevel, $status, $notes]);
            $message = 'Singer added successfully!';
            logAction('add_singer', "Added singer: $fullName");
        } catch (PDOException $e) {
            $message = getUserFriendlyError($e->getMessage());
        }
    } elseif (isset($_POST['edit_singer'])) {
        $fullName = sanitize($_POST['full_name']);
        $voiceCategory = $_POST['voice_category'];
        $voiceLevel = $_POST['voice_level'];
        $status = $_POST['status'];
        $notes = sanitize($_POST['notes'] ?? '');

        try {
            $stmt = $pdo->prepare("UPDATE singers SET full_name = ?, voice_category = ?, voice_level = ?, status = ?, notes = ? WHERE id = ?");
            $stmt->execute([$fullName, $voiceCategory, $voiceLevel, $status, $notes, $id]);
            $message = 'Singer updated successfully!';
            logAction('edit_singer', "Updated singer: $fullName");
        } catch (PDOException $e) {
            $message = getUserFriendlyError($e->getMessage());
        }
    } elseif (isset($_POST['delete_singer'])) {
        try {
            $stmt = $pdo->prepare("SELECT full_name FROM singers WHERE id = ?");
            $stmt->execute([$id]);
            $singer = $stmt->fetch();

            $stmt = $pdo->prepare("DELETE FROM singers WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Singer deleted successfully!';
            logAction('delete_singer', "Deleted singer: " . $singer['full_name']);
        } catch (PDOException $e) {
            $message = getUserFriendlyError($e->getMessage());
        }
    } elseif (isset($_POST['import_excel'])) {
        // Handle Excel import (simplified - would need PHPExcel or similar library)
        $message = 'Excel import functionality would be implemented here with a proper Excel parsing library.';
    }
}

// Get singer data for editing
$singer = null;
if ($action === 'edit' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM singers WHERE id = ?");
    $stmt->execute([$id]);
    $singer = $stmt->fetch();
}

// Build the singers query with search and filters
$query = "SELECT * FROM singers WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND full_name LIKE ?";
    $params[] = "%$search%";
}

if (!empty($voice_category_filter)) {
    $query .= " AND voice_category = ?";
    $params[] = $voice_category_filter;
}

if (!empty($voice_level_filter)) {
    $query .= " AND voice_level = ?";
    $params[] = $voice_level_filter;
}

if (!empty($status_filter)) {
    $query .= " AND status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY voice_category, voice_level DESC, full_name";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$singers = $stmt->fetchAll();

// Get filter counts for display
$totalSingers = $pdo->query("SELECT COUNT(*) FROM singers")->fetchColumn();
$activeSingers = $pdo->query("SELECT COUNT(*) FROM singers WHERE status = 'Active'")->fetchColumn();
$inactiveSingers = $pdo->query("SELECT COUNT(*) FROM singers WHERE status = 'Inactive'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Singers - Reverence Worship Team</title>
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
                <a href="singers.php" class="active">Manage Singers</a>
                <a href="groups.php">Manage Groups</a>
                <a href="reports.php">Reports</a>
                <a href="images.php">Manage Images</a>
                <a href="logs.php">View Logs</a>
                <a href="../logout.php">Logout</a>
            </nav>
        </div>

        <div class="admin-main">
            <div class="admin-header">
                <h1>Manage Singers</h1>
            </div>

            <!-- Horizontal Navigation -->
            <div class="horizontal-nav">
                <a href="singers.php" class="nav-tab <?php echo $action === 'list' || empty($action) ? 'active' : ''; ?>">
                    👥 All Singers
                </a>
                <a href="singers.php?action=add" class="nav-tab <?php echo $action === 'add' ? 'active' : ''; ?>">
                    ➕ Add Singer
                </a>
                <a href="singers.php?action=import" class="nav-tab <?php echo $action === 'import' ? 'active' : ''; ?>">
                    📤 Import Singers
                </a>
            </div>

            <div class="admin-content">
                <?php if ($message): ?>
                    <div class="message <?php echo strpos($message, 'Error') === 0 ? 'error' : 'success'; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($action === 'add' || $action === 'edit'): ?>
                    <div class="form-container">
                        <h3><?php echo $action === 'add' ? 'Add New Singer' : 'Edit Singer'; ?></h3>
                        <form method="POST" data-validate>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="full_name">Full Name:</label>
                                    <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($singer['full_name'] ?? ''); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="voice_category">Voice Category:</label>
                                    <select id="voice_category" name="voice_category" required>
                                        <option value="">Select Category</option>
                                        <option value="Soprano" <?php echo ($singer['voice_category'] ?? '') === 'Soprano' ? 'selected' : ''; ?>>Soprano</option>
                                        <option value="Alto" <?php echo ($singer['voice_category'] ?? '') === 'Alto' ? 'selected' : ''; ?>>Alto</option>
                                        <option value="Tenor" <?php echo ($singer['voice_category'] ?? '') === 'Tenor' ? 'selected' : ''; ?>>Tenor</option>
                                        <option value="Bass" <?php echo ($singer['voice_category'] ?? '') === 'Bass' ? 'selected' : ''; ?>>Bass</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="voice_level">Voice Level:</label>
                                    <select id="voice_level" name="voice_level" required>
                                        <option value="">Select Level</option>
                                        <option value="Good" <?php echo ($singer['voice_level'] ?? '') === 'Good' ? 'selected' : ''; ?>>Good</option>
                                        <option value="Normal" <?php echo ($singer['voice_level'] ?? '') === 'Normal' ? 'selected' : ''; ?>>Normal</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="status">Status:</label>
                                    <select id="status" name="status" required>
                                        <option value="Active" <?php echo ($singer['status'] ?? 'Active') === 'Active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="Inactive" <?php echo ($singer['status'] ?? '') === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="notes">Notes (optional):</label>
                                <textarea id="notes" name="notes" rows="3"><?php echo htmlspecialchars($singer['notes'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-actions">
                                <button type="submit" name="<?php echo $action === 'add' ? 'add_singer' : 'edit_singer'; ?>" class="btn">
                                    <?php echo $action === 'add' ? 'Add Singer' : 'Update Singer'; ?>
                                </button>
                                <a href="singers.php" class="btn">Cancel</a>
                            </div>
                        </form>
                    </div>
                <?php elseif ($action === 'import'): ?>
                    <div class="form-container">
                        <h3>Import Singers from Excel</h3>
                        <p>Upload an Excel file (.xlsx) with columns: Full Name, Voice, Level</p>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="excel_file">Excel File:</label>
                                <input type="file" id="excel_file" name="excel_file" accept=".xlsx" required>
                            </div>
                            <div class="form-actions">
                                <button type="submit" name="import_excel" class="btn">Import</button>
                                <a href="singers.php" class="btn">Cancel</a>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div style="margin-bottom: 2rem;">
                        <a href="singers.php?action=add" class="btn">Add New Singer</a>
                        <a href="singers.php?action=import" class="btn">Import from Excel</a>
                    </div>

                    <!-- Search and Filter Controls -->
                    <div class="filters-section">
                        <form method="GET" class="filters-form">
                            <div class="filter-row">
                                <div class="filter-group">
                                    <label for="search"> Search by Name:</label>
                                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Enter singer name...">
                                </div>
                                <div class="filter-group">
                                    <label for="voice_category">Voice Category:</label>
                                    <select id="voice_category" name="voice_category">
                                        <option value="">All Categories</option>
                                        <option value="Soprano" <?php echo $voice_category_filter === 'Soprano' ? 'selected' : ''; ?>>Soprano</option>
                                        <option value="Alto" <?php echo $voice_category_filter === 'Alto' ? 'selected' : ''; ?>>Alto</option>
                                        <option value="Tenor" <?php echo $voice_category_filter === 'Tenor' ? 'selected' : ''; ?>>Tenor</option>
                                        <option value="Bass" <?php echo $voice_category_filter === 'Bass' ? 'selected' : ''; ?>>Bass</option>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label for="voice_level"> Voice Level:</label>
                                    <select id="voice_level" name="voice_level">
                                        <option value="">All Levels</option>
                                        <option value="Good" <?php echo $voice_level_filter === 'Good' ? 'selected' : ''; ?>>Good</option>
                                        <option value="Normal" <?php echo $voice_level_filter === 'Normal' ? 'selected' : ''; ?>>Normal</option>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label for="status"> Status:</label>
                                    <select id="status" name="status">
                                        <option value="">All Status</option>
                                        <option value="Active" <?php echo $status_filter === 'Active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="Inactive" <?php echo $status_filter === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                                <div class="filter-actions">
                                    <button type="submit" class="btn">Filter</button>
                                    <a href="singers.php" class="btn secondary">❌ Clear</a>
                                </div>
                            </div>
                        </form>

                        <!-- Results Summary -->
                        <div class="results-summary">
                            <span class="results-count">
                                 Showing <?php echo count($singers); ?> of <?php echo $totalSingers; ?> singers
                                (<?php echo $activeSingers; ?> active, <?php echo $inactiveSingers; ?> inactive)
                            </span>
                            <?php if (!empty($search) || !empty($voice_category_filter) || !empty($voice_level_filter) || !empty($status_filter)): ?>
                                <span class="active-filters">
                                    Active filters:
                                    <?php if (!empty($search)): ?>Name: "<?php echo htmlspecialchars($search); ?>"<?php endif; ?>
                                    <?php if (!empty($voice_category_filter)): ?>Category: <?php echo $voice_category_filter; ?><?php endif; ?>
                                    <?php if (!empty($voice_level_filter)): ?>Level: <?php echo $voice_level_filter; ?><?php endif; ?>
                                    <?php if (!empty($status_filter)): ?>Status: <?php echo $status_filter; ?><?php endif; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Voice Category</th>
                                    <th>Voice Level</th>
                                    <th>Status</th>
                                    <th>Notes</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($singers as $singer): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($singer['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($singer['voice_category']); ?></td>
                                        <td><?php echo htmlspecialchars($singer['voice_level']); ?></td>
                                        <td><?php echo htmlspecialchars($singer['status']); ?></td>
                                        <td><?php echo htmlspecialchars($singer['notes'] ?: 'N/A'); ?></td>
                                        <td>
                                            <a href="singers.php?action=edit&id=<?php echo $singer['id']; ?>" class="btn">Edit</a>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="id" value="<?php echo $singer['id']; ?>">
                                                <button type="submit" name="delete_singer" class="btn btn-delete" onclick="return confirm('Are you sure?')">Delete</button>
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
                <p>Singer Management - Building harmonious gospel voices through fair and transparent assignments.</p>
            </div>

            <div class="footer-section footer-scripture">
                <blockquote>
                    <p><strong>Psalm 96:7-9</strong></p>
                    <p>Give praise to the Lord, you who belong to all peoples, give glory to him and take up his praise.</p>
                </blockquote>
            </div>

            <div class="footer-section">
                <h4>Singer Tools</h4>
                <p><a href="singers.php?action=add">Add New Singer</a></p>
                <p><a href="singers.php?action=import">Import from Excel</a></p>
                <p><a href="groups.php">View Group Assignments</a></p>
                <p><a href="dashboard.php">← Back to Dashboard</a></p>
            </div>

            <div class="footer-section">
                <h4>Voice Categories</h4>
                <p>• Soprano</p>
                <p>• Alto</p>
                <p>• Tenor</p>
                <p>• Bass</p>
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
