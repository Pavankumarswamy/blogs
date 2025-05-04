<?php
// Disable error display, keep logging
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Start output buffering
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    error_log('Session started in index.php, ID: ' . session_id());
}

error_log('index.php loaded, session data: ' . print_r($_SESSION, true));

// Include required files
require_once '../config.php';

$authFile = '../includes/admin-auth.php';
if (!file_exists($authFile)) {
    error_log('Error: admin-auth.php not found in includes/');
    die('Error: Authentication file not found.');
}
require_once $authFile;

// Check authentication
checkAuth();

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
    die('Database connection failed.');
}

// Fetch recent blog posts from MySQL
try {
    $stmt = $pdo->query("SELECT id, title, slug, published_date, image_url, thumbnail_url FROM blogs ORDER BY published_date DESC LIMIT 5");
    $recentPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $blogPostCount = $pdo->query("SELECT COUNT(*) FROM blogs")->fetchColumn();
    error_log('Fetched ' . count($recentPosts) . ' recent blog posts, total count: ' . $blogPostCount);
} catch (PDOException $e) {
    error_log('Error fetching blog posts: ' . $e->getMessage());
    $recentPosts = [];
    $blogPostCount = 0;
    $message = 'Error loading blog posts: ' . htmlspecialchars($e->getMessage());
    $messageType = 'error';
}

// Fetch projects from MySQL
try {
    $stmt = $pdo->query("
        SELECT id, title, created_at, image_url, thumbnail_url
        FROM projects
        WHERE id IS NOT NULL
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $recentProjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $projectCount = $pdo->query("SELECT COUNT(*) FROM projects WHERE id IS NOT NULL")->fetchColumn();
    error_log('Fetched ' . count($recentProjects) . ' recent projects, total count: ' . $projectCount);
    error_log('Recent project IDs: ' . json_encode(array_column($recentProjects, 'id')));
} catch (PDOException $e) {
    error_log('Error fetching projects: ' . $e->getMessage());
    $recentProjects = [];
    $projectCount = 0;
    $message = 'Error loading projects: ' . htmlspecialchars($e->getMessage());
    $messageType = 'error';
}

// Handle success/error messages from redirects
$message = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : (isset($message) ? $message : '');
$messageType = isset($_GET['error']) ? 'error' : (isset($_GET['message']) || isset($message) ? 'success' : '');

// Check for CSS/JS files
$css_path = $_SERVER['DOCUMENT_ROOT'] . '/css/admin.css';
$js_path = $_SERVER['DOCUMENT_ROOT'] . '/js/admin.js';
error_log('Checking admin resource paths:');
error_log('admin.css exists: ' . (file_exists($css_path) ? 'Yes' : 'No') . ' at ' . $css_path);
error_log('admin.js exists: ' . (file_exists($js_path) ? 'Yes' : 'No') . ' at ' . $js_path);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Admin Dashboard | <?php echo htmlspecialchars(SITE_NAME); ?></title>

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

    <!-- Custom CSS -->
    <?php if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/css/style.css')): ?>
        <link rel="stylesheet" href="https://ggusoc.in/css/style.css">
    <?php endif; ?>
    <?php if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/css/responsive.css')): ?>
        <link rel="stylesheet" href="https://ggusoc.in/css/responsive.css">
    <?php endif; ?>
    <?php if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/css/admin.css')): ?>
        <link rel="stylesheet" href="https://ggusoc.in/css/admin.css">
    <?php else: ?>
        <style>
            .admin-wrapper { display: flex; min-height: 100vh; }
            .admin-content { flex: 1; padding: 20px; }
            .admin-header { display: flex; justify-content: space-between; align-items: center; }
            .admin-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
            .admin-stat-card { background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; }
            .admin-card { background: #fff; padding: 20px; border-radius: 8px; margin-top: 20px; }
            .admin-table { width: 100%; border-collapse: collapse; }
            .admin-table th, .admin-table td { padding: 10px; border: 1px solid #ddd; }
            .btn { padding: 8px 16px; text-decoration: none; border-radius: 4px; }
            .btn-primary { background: #007bff; color: #fff; }
            .btn-outline { border: 1px solid #007bff; color: #007bff; }
            .btn-danger { background: #dc3545; color: #fff; }
            .alert { padding: 15px; border-radius: 4px; }
            .alert-success { background: #d4edda; color: #155724; }
            .alert-danger { background: #f8d7da; color: #721c24; }
        </style>
    <?php endif; ?>
</head>
<body>
    <div class="admin-wrapper">
        <!-- Admin Sidebar -->
        <?php
        if (!file_exists('partials/sidebar.php')) {
            error_log('Error: sidebar.php not found in partials/');
            die('Error: Sidebar file not found.');
        }
        include 'partials/sidebar.php';
        ?>

        <!-- Admin Content -->
        <div class="admin-content">
            <div class="admin-header">
                <h1>Dashboard</h1>
                <div>
                    <a href="https://ggusoc.in/" class="btn btn-outline" target="_blank"><i class="fas fa-external-link-alt"></i> View Website</a>
                </div>
            </div>

            <!-- Display Messages -->
            <?php if ($message): ?>
                <div class="alert <?= $messageType === 'error' ? 'alert-danger' : 'alert-success'; ?>">
                    <?= $message; ?>
                </div>
            <?php endif; ?>

            <!-- Stats Section -->
            <div class="admin-stats">
                <div class="admin-stat-card">
                    <h3>Total Blog Posts</h3>
                    <p class="stat-value"><?= $blogPostCount; ?></p>
                </div>
                <div class="admin-stat-card">
                    <h3>Total Projects</h3>
                    <p class="stat-value"><?= $projectCount; ?></p>
                </div>
            </div>

            <!-- Recent Blog Posts -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h2>Recent Blog Posts</h2>
                </div>

                <?php if (empty($recentPosts)): ?>
                    <p>No blog posts found.</p>
                <?php else: ?>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentPosts as $post): ?>
                                <tr>
                                    <td><?= htmlspecialchars($post['title']); ?></td>
                                    <td><?= date('M d, Y', strtotime($post['published_date'])); ?></td>
                                    <td class="actions">
                                        <a href="edit-post.php?id=<?= htmlspecialchars($post['id']); ?>" class="edit"><i class="fas fa-edit"></i> Edit</a>
                                        <button onclick="deletePost('<?= htmlspecialchars($post['id']); ?>')" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i> Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <div style="margin-top: 20px">
                    <a href="posts.php" class="btn btn-outline">View All Posts</a>
                    <a href="create-post.php" class="btn btn-primary">Create New Post</a>
                </div>
            </div>

            <!-- Recent Projects -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h2>Recent Projects</h2>
                </div>

                <?php if (empty($recentProjects)): ?>
                    <p>No projects found.</p>
                <?php else: ?>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentProjects as $project): ?>
                                <tr>
                                    <td><?= htmlspecialchars($project['title']); ?></td>
                                    <td><?= date('M d, Y', strtotime($project['created_at'] ?? 'now')); ?></td>
                                    <td class="actions">
                                        <a href="edit-project.php?id=<?= htmlspecialchars($project['id']); ?>" class="edit"><i class="fas fa-edit"></i> Edit</a>
                                        <button onclick="deleteProject('<?= htmlspecialchars($project['id']); ?>')" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i> Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <div style="margin-top: 20px">
                    <a href="projects.php" class="btn btn-outline">View All Projects</a>
                    <a href="create-project.php" class="btn btn-primary">Create New Project</a>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Sidebar toggle
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

    // Delete post function
    async function deletePost(postId) {
        if (!confirm('Are you sure you want to delete this post?')) return;

        try {
            const response = await fetch('delete-post.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `post_id=${encodeURIComponent(postId)}`
            });
            const result = await response.json();

            if (result.success) {
                alert(result.message);
                window.location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            console.error('Delete post error:', error);
            alert('Error: Failed to delete post. ' + error.message);
        }
    }

    // Delete project function
    async function deleteProject(projectId) {
        if (!confirm('Are you sure you want to delete this project?')) return;
        try {
            const response = await fetch('delete-project.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `project_id=${encodeURIComponent(projectId)}`
            });
            if (!response.ok) {
                const text = await response.text();
                console.error('Delete project response:', text);
                throw new Error(`HTTP error! Status: ${response.status}, StatusText: ${response.statusText}, Response: ${text.substring(0, 100)}`);
            }
            const result = await response.json();
            if (result.success) {
                alert(result.message);
                window.location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            console.error('Delete project error:', {
                message: error.message,
                stack: error.stack,
                projectId: projectId
            });
            alert('Error: Failed to delete project. Check console for details.');
        }
    }
    </script>

    <?php if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/js/main.js')): ?>
        <script src="https://ggusoc.in/js/main.js"></script>
    <?php endif; ?>
</body>
</html>
<?php
// Clean output buffer
ob_end_flush();
?>