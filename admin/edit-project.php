<?php
// Start output buffering
ob_start();

// Disable error display, keep logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Log start
error_log('Starting edit-project.php');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    error_log('Session started, ID: ' . session_id());
}

// Include required files
require_once '../config.php';
require_once '../includes/admin-auth.php';

// Check authentication
try {
    checkAuth();
    error_log('Authentication passed');
} catch (Exception $e) {
    error_log('Authentication error: ' . $e->getMessage());
    ob_clean();
    die('Authentication failed: ' . htmlspecialchars($e->getMessage()));
}

// Initialize variables
$project = null;
$message = '';
$messageType = '';
$errors = [];

// MySQL Database Connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    error_log('Connected to MySQL database');
} catch (PDOException $e) {
    error_log('MySQL connection failed: ' . $e->getMessage());
    ob_clean();
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Sanitize and validate inputs
        $projectId = filter_input(INPUT_POST, 'project_id', FILTER_SANITIZE_NUMBER_INT);
        $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $project_url = filter_input(INPUT_POST, 'project_url', FILTER_SANITIZE_URL);
        $github_url = filter_input(INPUT_POST, 'github_url', FILTER_SANITIZE_URL);
        $technologies = filter_input(INPUT_POST, 'technologies', FILTER_SANITIZE_STRING);
        $created_at = filter_input(INPUT_POST, 'created_at', FILTER_SANITIZE_STRING);
        $technologiesArray = !empty($technologies) ? array_filter(array_map('trim', explode(',', $technologies))) : [];

        // Validate required fields
        if (empty($projectId) || !is_numeric($projectId)) {
            $errors[] = 'Invalid project ID.';
        }
        if (empty($title)) {
            $errors[] = 'Title is required.';
        }
        if (empty($description)) {
            $errors[] = 'Description is required.';
        }
        if (empty($created_at) || !strtotime($created_at)) {
            $errors[] = 'Valid created date is required.';
        }

        // Generate slug for file naming
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
        if (empty($slug)) {
            $errors[] = 'Invalid title for slug generation.';
            $slug = 'project-' . time();
        }

        // Handle file uploads
        $image_url = filter_input(INPUT_POST, 'existing_image_url', FILTER_SANITIZE_URL);
        $thumbnail_url = filter_input(INPUT_POST, 'existing_thumbnail_url', FILTER_SANITIZE_URL);
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/images/projects/';
        $mainDir = $uploadDir . 'main/';
        $thumbDir = $uploadDir . 'thumbnails/';
        $baseUrl = 'https://ggusoc.in/images/projects/';

        // Ensure directories exist
        if (!is_dir($mainDir)) mkdir($mainDir, 0755, true);
        if (!is_dir($thumbDir)) mkdir($thumbDir, 0755, true);

        // Main image
        if (!empty($_FILES['main_image']['name']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
            $mainImage = $_FILES['main_image'];
            $ext = pathinfo($mainImage['name'], PATHINFO_EXTENSION);
            $mainImageName = $slug . '-' . time() . '.' . $ext;
            $mainImagePath = $mainDir . $mainImageName;

            if ($mainImage['size'] > 10 * 1024 * 1024) {
                $errors[] = 'Main image too large (max 10MB).';
                error_log('Main image too large: ' . $mainImage['size'] . ' bytes');
            } elseif (move_uploaded_file($mainImage['tmp_name'], $mainImagePath)) {
                // Delete old image
                if (!empty($image_url)) {
                    $oldImageFile = str_replace($baseUrl . 'main/', $mainDir, $image_url);
                    if (file_exists($oldImageFile)) {
                        unlink($oldImageFile);
                        error_log('Deleted old main image: ' . $oldImageFile);
                    }
                }
                $image_url = $baseUrl . 'main/' . $mainImageName;
                error_log('Main image uploaded: ' . $mainImagePath);
            } else {
                $errors[] = 'Failed to upload main image.';
                error_log('Main image upload failed: ' . print_r($mainImage, true));
            }
        }

        // Thumbnail
        if (!empty($_FILES['thumbnail']['name']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
            $thumbnail = $_FILES['thumbnail'];
            $ext = pathinfo($thumbnail['name'], PATHINFO_EXTENSION);
            $thumbName = $slug . '-' . time() . '-thumb.' . $ext;
            $thumbPath = $thumbDir . $thumbName;

            if ($thumbnail['size'] > 10 * 1024 * 1024) {
                $errors[] = 'Thumbnail too large (max 10MB).';
                error_log('Thumbnail too large: ' . $thumbnail['size'] . ' bytes');
            } elseif (move_uploaded_file($thumbnail['tmp_name'], $thumbPath)) {
                // Delete old thumbnail
                if (!empty($thumbnail_url)) {
                    $oldThumbFile = str_replace($baseUrl . 'thumbnails/', $thumbDir, $thumbnail_url);
                    if (file_exists($oldThumbFile)) {
                        unlink($oldThumbFile);
                        error_log('Deleted old thumbnail: ' . $oldThumbFile);
                    }
                }
                $thumbnail_url = $baseUrl . 'thumbnails/' . $thumbName;
                error_log('Thumbnail uploaded: ' . $thumbPath);
            } else {
                $errors[] = 'Failed to upload thumbnail.';
                error_log('Thumbnail upload failed: ' . print_r($thumbnail, true));
            }
        }

        // Update project if no errors
        if (empty($errors)) {
            $stmt = $pdo->prepare("
                UPDATE projects
                SET title = ?, description = ?, technologies = ?, project_url = ?, github_url = ?, image_url = ?, thumbnail_url = ?, created_at = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $title,
                $description,
                json_encode($technologiesArray),
                $project_url ?: '',
                $github_url ?: '',
                $image_url ?: '',
                $thumbnail_url ?: '',
                $created_at,
                $projectId
            ]);

            if ($stmt->rowCount() === 0) {
                throw new Exception('No changes made to project or project not found.');
            }

            error_log("Project $projectId updated successfully");
            $message = 'Project updated successfully.';
            $messageType = 'success';

            // Redirect to projects.php
            ob_end_clean();
            header('Location: projects.php?message=Project updated successfully');
            exit;
        } else {
            $message = implode('<br>', $errors);
            $messageType = 'error';
        }
    } catch (Exception $e) {
        error_log('Update error: ' . $e->getMessage());
        $message = 'Error: ' . htmlspecialchars($e->getMessage());
        $messageType = 'error';
    }
}

// Fetch project details
try {
    if (!isset($_GET['id']) || empty(trim($_GET['id'])) || !is_numeric($_GET['id'])) {
        throw new Exception('Invalid or missing project ID.');
    }

    $projectId = trim($_GET['id']);
    $stmt = $pdo->prepare("
        SELECT id, title, description, technologies, project_url, github_url, image_url, thumbnail_url, created_at
        FROM projects
        WHERE id = ?
    ");
    $stmt->execute([$projectId]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        throw new Exception('Project not found.');
    }

    // Decode technologies
    $project['technologies'] = !empty($project['technologies']) ? json_decode($project['technologies'], true) : [];
    $project['technologies'] = is_array($project['technologies']) ? implode(', ', $project['technologies']) : '';
    error_log("Project $projectId fetched successfully");
} catch (Exception $e) {
    error_log('Fetch error: ' . $e->getMessage());
    $message = 'Error: ' . htmlspecialchars($e->getMessage());
    $messageType = 'error';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Edit Project | Portfolio</title>

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/responsive.css">
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
    <div class="admin-wrapper">
        <!-- Admin Sidebar -->
        <?php
        $sidebarFile = 'partials/sidebar.php';
        if (!file_exists($sidebarFile)) {
            error_log('Error: sidebar.php not found at ' . $sidebarFile);
            ob_clean();
            die('Error: Sidebar file not found.');
        }
        include $sidebarFile;
        ?>

        <!-- Admin Content -->
        <div class="admin-content">
            <div class="admin-header">
                <h1>Edit Project</h1>
                <div>
                    <a href="projects.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Projects</a>
                </div>
            </div>

            <!-- Display Messages -->
            <?php if ($message): ?>
                <div class="alert <?= $messageType === 'error' ? 'alert-danger' : 'alert-success'; ?>">
                    <?= htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Edit Project Form -->
            <?php if ($project): ?>
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h2>Update Project Details</h2>
                    </div>
                    <form action="edit-project.php?id=<?= htmlspecialchars($project['id']); ?>" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="project_id" value="<?= htmlspecialchars($project['id']); ?>">
                        <input type="hidden" name="existing_image_url" value="<?= htmlspecialchars($project['image_url']); ?>">
                        <input type="hidden" name="existing_thumbnail_url" value="<?= htmlspecialchars($project['thumbnail_url']); ?>">
                        <div class="form-group">
                            <label for="title">Project Title *</label>
                            <input type="text" id="title" name="title" class="form-control" value="<?= htmlspecialchars($project['title']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="main_image">Project Image</label>
                            <?php if ($project['image_url']): ?>
                                <p>Current: <a href="<?= htmlspecialchars($project['image_url']); ?>" target="_blank">View Image</a></p>
                            <?php endif; ?>
                            <input type="file" id="main_image" name="main_image" accept="image/jpeg,image/png,image/gif">
                        </div>
                        <div class="form-group">
                            <label for="thumbnail">Thumbnail Image</label>
                            <?php if ($project['thumbnail_url']): ?>
                                <p>Current: <a href="<?= htmlspecialchars($project['thumbnail_url']); ?>" target="_blank">View Thumbnail</a></p>
                            <?php endif; ?>
                            <input type="file" id="thumbnail" name="thumbnail" accept="image/jpeg,image/png,image/gif">
                        </div>
                        <div class="form-group">
                            <label for="description">Description *</label>
                            <textarea id="description" name="description" class="form-control" rows="10" required><?= htmlspecialchars($project['description']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="technologies">Technologies Used</label>
                            <input type="text" id="technologies" name="technologies" class="form-control" value="<?= htmlspecialchars($project['technologies']); ?>" placeholder="e.g., PHP, JavaScript, MySQL">
                            <small>Separate technologies with commas</small>
                        </div>
                        <div class="form-group">
                            <label for="project_url">Live Project URL</label>
                            <input type="url" id="project_url" name="project_url" class="form-control" value="<?= htmlspecialchars($project['project_url']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="github_url">GitHub Repository URL</label>
                            <input type="url" id="github_url" name="github_url" class="form-control" value="<?= htmlspecialchars($project['github_url']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="created_at">Created Date *</label>
                            <input type="date" id="created_at" name="created_at" class="form-control" value="<?= htmlspecialchars(date('Y-m-d', strtotime($project['created_at']))); ?>" required>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Project</button>
                            <a href="projects.php" class="btn btn-outline">Cancel</a>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="admin-card">
                    <p>Project not found or an error occurred.</p>
                    <a href="projects.php" class="btn btn-outline">Back to Projects</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const sidebar = document.querySelector('.sidebar');
            const toggleBtn = document.querySelector('.sidebar-toggle');
            const content = document.querySelector('.admin-content');

            if (toggleBtn && sidebar && content) {
                toggleBtn.addEventListener('click', function () {
                    sidebar.classList.toggle('collapsed');
                    content.classList.toggle('full-width');
                });
            }
        });
    </script>
    <script src="../js/main.js"></script>
</body>
</html>
<?php
ob_end_flush();
?>