<?php
require_once '../includes/config.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['upload_image'])) {
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/images/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $fileName = basename($_FILES['image']['name']);
            $filePath = $uploadDir . time() . '_' . $fileName;
            $fileType = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

            // Validate file type
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
            if (!in_array($fileType, $allowedTypes)) {
                $message = 'Only JPG, JPEG, PNG, and GIF files are allowed.';
            } elseif ($_FILES['image']['size'] > 5 * 1024 * 1024) { // 5MB limit
                $message = 'File size must be less than 5MB.';
            } elseif (move_uploaded_file($_FILES['image']['tmp_name'], $filePath)) {
                // Save to database
                $stmt = $pdo->prepare("INSERT INTO landing_images (image_path) VALUES (?)");
                $stmt->execute([$filePath]);
                $message = 'Image uploaded successfully!';
                logAction('upload_image', "Uploaded image: $fileName");
            } else {
                $message = 'Error uploading file.';
            }
        } else {
            $message = 'Please select an image to upload.';
        }
    } elseif (isset($_POST['toggle_active'])) {
        $imageId = $_POST['image_id'];
        try {
            // Get current status
            $stmt = $pdo->prepare("SELECT is_active FROM landing_images WHERE id = ?");
            $stmt->execute([$imageId]);
            $current = $stmt->fetch();

            if ($current) {
                $newStatus = $current['is_active'] ? 0 : 1;
                $stmt = $pdo->prepare("UPDATE landing_images SET is_active = ? WHERE id = ?");
                $stmt->execute([$newStatus, $imageId]);
                $message = $newStatus ? 'Image activated successfully!' : 'Image deactivated successfully!';
                logAction('toggle_active_image', ($newStatus ? 'Activated' : 'Deactivated') . " image ID $imageId");
            }
        } catch (PDOException $e) {
            $message = 'Error updating image status: ' . $e->getMessage();
        }
    } elseif (isset($_POST['delete_image'])) {
        $imageId = $_POST['image_id'];
        try {
            $stmt = $pdo->prepare("SELECT image_path FROM landing_images WHERE id = ?");
            $stmt->execute([$imageId]);
            $image = $stmt->fetch();

            if ($image) {
                // Delete file if it exists
                if (file_exists($image['image_path'])) {
                    unlink($image['image_path']);
                }

                // Delete from database
                $stmt = $pdo->prepare("DELETE FROM landing_images WHERE id = ?");
                $stmt->execute([$imageId]);
                $message = 'Image deleted successfully!';
                logAction('delete_image', "Deleted image ID $imageId");
            }
        } catch (PDOException $e) {
            $message = 'Error deleting image: ' . $e->getMessage();
        }
    }
}

// Get all images
$images = $pdo->query("SELECT * FROM landing_images ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Images - Reverence Worship Team</title>
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
                <a href="images.php" class="active">Manage Images</a>
                <a href="reports.php">Reports</a>
                <a href="logs.php">View Logs</a>

                <a href="../logout.php">Logout</a>
            </nav>
        </div>

        <div class="admin-main">
            <div class="admin-header">
                <h1>Manage Landing Page Images</h1>
            </div>

            <div class="admin-content">
                <?php if ($message): ?>
                    <div class="message <?php echo strpos($message, 'Error') === 0 || strpos($message, 'must be') !== false ? 'error' : 'success'; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <div class="form-container">
                    <h3>Upload New Image</h3>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="image">Select Image:</label>
                            <input type="file" id="image" name="image" accept="image/*" required>
                            <small>Allowed formats: JPG, JPEG, PNG, GIF. Max size: 5MB</small>
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="upload_image" class="btn">Upload Image</button>
                        </div>
                    </form>
                </div>

                <h3>Image Gallery</h3>
                <div class="image-gallery">
                    <?php if (empty($images)): ?>
                        <p>No images uploaded yet.</p>
                    <?php else: ?>
                        <?php foreach ($images as $image): ?>
                            <div class="image-item <?php echo $image['is_active'] ? 'active' : ''; ?>">
                                <div class="image-preview">
                                    <?php if (file_exists($image['image_path'])): ?>
                                        <img src="<?php echo htmlspecialchars($image['image_path']); ?>" alt="Landing page image">
                                    <?php else: ?>
                                        <div class="image-placeholder">Image not found</div>
                                    <?php endif; ?>
                                </div>
                                <div class="image-info">
                                    <p><strong>Uploaded:</strong> <?php echo date('M j, Y g:i A', strtotime($image['created_at'])); ?></p>
                                    <?php if ($image['is_active']): ?>
                                        <p class="active-indicator">✓ Currently Active</p>
                                    <?php endif; ?>
                                    <div class="image-actions">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                                            <button type="submit" name="toggle_active" class="btn <?php echo $image['is_active'] ? 'btn-deactivate' : 'btn-activate'; ?>">
                                                <?php echo $image['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                                            <button type="submit" name="delete_image" class="btn btn-delete" onclick="return confirm('Are you sure?')">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <div class="footer-content">
            <div class="footer-brand">
                <img src="../assets/Logo Reverence-Photoroom.png" alt="Reverence WorshipTeam Logo" class="footer-logo">
                <h3>Reverence WorshipTeam</h3>
                <p>Image Management - Showcasing gospel choir ministry through visual storytelling.</p>
            </div>

            <div class="footer-section footer-scripture">
                <blockquote>
                    <p><strong>Psalm 96:7-9</strong></p>
                    <p>Give praise to the Lord, you who belong to all peoples, give glory to him and take up his praise.</p>
                </blockquote>
            </div>

            <div class="footer-section">
                <h4>Media Tools</h4>
                <p><a href="images.php">Upload Images</a></p>
                <p><a href="groups.php">Manage Groups</a></p>
                <p><a href="singers.php">View Singers</a></p>
                <p><a href="dashboard.php">← Back to Dashboard</a></p>
            </div>

            <div class="footer-section">
                <h4>Image Guidelines</h4>
                <p>• JPG, PNG, GIF</p>
                <p>• Max 5MB</p>
                <p>• High resolution</p>
                <p>• Worship-focused</p>
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
.image-gallery {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1rem;
    margin: 2rem 0;
}

.image-item {
    border: 2px solid #ddd;
    border-radius: 8px;
    padding: 1rem;
    background-color: #f9f9f9;
}

.image-item.active {
    border-color: #000;
    background-color: #fff;
}

.image-preview {
    text-align: center;
    margin-bottom: 1rem;
}

.image-preview img {
    max-width: 100%;
    max-height: 200px;
    object-fit: cover;
    border-radius: 4px;
}

.image-placeholder {
    width: 100%;
    height: 200px;
    background-color: #eee;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    color: #666;
}

.image-info p {
    margin: 0.5rem 0;
}

.active-indicator {
    color: #28a745;
    font-weight: bold;
}

.image-actions {
    margin-top: 1rem;
}

.image-actions .btn {
    margin-right: 0.5rem;
    margin-bottom: 0.5rem;
}

.btn-activate {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    border-color: #28a745;
}

.btn-activate:hover {
    background: linear-gradient(135deg, #218838 0%, #1aa085 100%);
    color: white;
}

.btn-deactivate {
    background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
    border-color: #ffc107;
    color: #000;
}

.btn-deactivate:hover {
    background: linear-gradient(135deg, #e0a800 0%, #d39e00 100%);
    color: #000;
}
</style>
