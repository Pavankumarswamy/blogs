<?php
// Start output buffering
ob_start();

// Disable error display, keep logging
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Log start
error_log('Starting edit-post.php');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    error_log('Session started in edit-post.php, ID: ' . session_id());
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
$post = null;
$message = '';
$messageType = '';
$notificationMessage = '';
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

// Generate slug
function generateSlug($title) {
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return $slug;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Sanitize and validate inputs
        $postId = filter_input(INPUT_POST, 'post_id', FILTER_SANITIZE_NUMBER_INT);
        $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $content = filter_input(INPUT_POST, 'content', FILTER_UNSAFE_RAW); // Allow HTML
        $category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $tags = filter_input(INPUT_POST, 'tags', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $author = filter_input(INPUT_POST, 'author', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $published_date = filter_input(INPUT_POST, 'published_date', FILTER_SANITIZE_STRING);

        // Validate required fields
        if (empty($postId) || !is_numeric($postId)) {
            $errors[] = 'Invalid post ID.';
        }
        if (empty($title)) {
            $errors[] = 'Title is required.';
        }
        if (empty($content)) {
            $errors[] = 'Content is required.';
        }
        if (empty($published_date) || !strtotime($published_date)) {
            $errors[] = 'Valid published date is required.';
        }

        // Generate slug
        $slug = generateSlug($title);
        if (empty($slug)) {
            $errors[] = 'Invalid title for slug generation.';
            $slug = 'post-' . time();
        }

        // Handle file uploads
        $image_url = filter_input(INPUT_POST, 'existing_image_url', FILTER_SANITIZE_URL);
        $thumbnail_url = filter_input(INPUT_POST, 'existing_thumbnail_url', FILTER_SANITIZE_URL);
        $mainImageDir = $_SERVER['DOCUMENT_ROOT'] . '/images/blog/main/';
        $thumbnailDir = $_SERVER['DOCUMENT_ROOT'] . '/images/blog/thumbnails/';
        $mainImageUrlBase = 'https://ggusoc.in/images/blog/main/';
        $thumbnailUrlBase = 'https://ggusoc.in/images/blog/thumbnails/';

        // Ensure directories exist
        if (!is_dir($mainImageDir)) {
            mkdir($mainImageDir, 0755, true) or throw new Exception('Failed to create main image directory');
        }
        if (!is_dir($thumbnailDir)) {
            mkdir($thumbnailDir, 0755, true) or throw new Exception('Failed to create thumbnail directory');
        }

        // Main image
        if (!empty($_FILES['main_image']['name']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
            $mainImage = $_FILES['main_image'];
            $ext = strtolower(pathinfo($mainImage['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                $errors[] = 'Main image must be JPG, PNG, or GIF.';
            } elseif ($mainImage['size'] > 10 * 1024 * 1024) {
                $errors[] = 'Main image too large (max 10MB).';
            } else {
                $filename = time() . '_' . uniqid() . '.' . $ext;
                $destPath = $mainImageDir . $filename;
                if (move_uploaded_file($mainImage['tmp_name'], $destPath)) {
                    // Delete old image
                    if (!empty($image_url)) {
                        $oldImagePath = str_replace($mainImageUrlBase, $mainImageDir, $image_url);
                        if (file_exists($oldImagePath)) {
                            unlink($oldImagePath);
                            error_log('Deleted old main image: ' . $oldImagePath);
                        }
                    }
                    $image_url = $mainImageUrlBase . $filename;
                    error_log('Main image uploaded: ' . $image_url);
                } else {
                    $errors[] = 'Failed to upload main image.';
                }
            }
        }

        // Thumbnail
        if (!empty($_FILES['thumbnail']['name']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
            $thumbnail = $_FILES['thumbnail'];
            $ext = strtolower(pathinfo($thumbnail['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                $errors[] = 'Thumbnail must be JPG, PNG, or GIF.';
            } elseif ($thumbnail['size'] > 10 * 1024 * 1024) {
                $errors[] = 'Thumbnail too large (max 10MB).';
            } else {
                $filename = time() . '_' . uniqid() . '-thumb.' . $ext;
                $destPath = $thumbnailDir . $filename;
                if (move_uploaded_file($thumbnail['tmp_name'], $destPath)) {
                    // Delete old thumbnail
                    if (!empty($thumbnail_url)) {
                        $oldThumbPath = str_replace($thumbnailUrlBase, $thumbnailDir, $thumbnail_url);
                        if (file_exists($oldThumbPath)) {
                            unlink($oldThumbPath);
                            error_log('Deleted old thumbnail: ' . $oldThumbPath);
                        }
                    }
                    $thumbnail_url = $thumbnailUrlBase . $filename;
                    error_log('Thumbnail uploaded: ' . $thumbnail_url);
                } else {
                    $errors[] = 'Failed to upload thumbnail.';
                }
            }
        }

        // Update post if no errors
        if (empty($errors)) {
            $tagArray = !empty($tags) ? array_filter(array_map('trim', explode(',', $tags))) : [];
            $tagsJson = json_encode($tagArray);
            $author = $author ?: 'Admin';
            $excerpt = substr(strip_tags($content), 0, 255);
            if (strlen(strip_tags($content)) > 252) {
                $excerpt .= '...';
            }

            $stmt = $pdo->prepare("
                UPDATE blogs
                SET title = ?, slug = ?, content = ?, excerpt = ?, author = ?, published_date = ?, tags = ?, category = ?, image_url = ?, thumbnail_url = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $title,
                $slug,
                $content,
                $excerpt,
                $author,
                $published_date,
                $tagsJson,
                $category ?: null,
                $image_url ?: null,
                $thumbnail_url ?: null,
                $postId
            ]);

            if ($stmt->rowCount() === 0) {
                throw new Exception('No changes made to post or post not found.');
            }

            // Send Webpushr Notification
            try {
                $end_point = 'https://api.webpushr.com/v1/notification/send/all';
                $http_header = [
                    'Content-Type: application/json',
                    'webpushrKey: 9cb29002403a757b9a6b189d13e6e8d7',
                    'webpushrAuthToken: 108045'
                ];

                $post_url = "https://ggusoc.in/blog/$slug";
                $notification_title = substr(preg_replace('/[^\x20-\x7E]/', '', 'Updated Blog Post: ' . $title), 0, 100);
                $notification_message = substr(preg_replace('/[^\x20-\x7E]/', '', $excerpt), 0, 255);
                if (strlen($notification_message) > 252) {
                    $notification_message = substr($notification_message, 0, 252) . '...';
                }
                $notification_name = substr(preg_replace('/[^\x20-\x7E]/', '', 'Blog Post: ' . $title), 0, 100);
                $image_url = $thumbnail_url ?: $image_url ?: 'https://ggusoc.in/images/default-notification.jpg';

                $req_data = [
                    'title' => $notification_title,
                    'message' => $notification_message,
                    'target_url' => $post_url,
                    'image' => $image_url,
                    'auto_hide' => 1,
                    'name' => $notification_name
                ];

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $end_point);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $http_header);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($req_data));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_HEADER, true);

                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                $curl_error = curl_error($ch);
                $response_headers = substr($response, 0, $header_size);
                $response_body = substr($response, $header_size);
                curl_close($ch);

                if ($response === false) {
                    error_log('Webpushr cURL error: ' . $curl_error);
                    $notificationMessage = 'Notification failed: cURL error - ' . htmlspecialchars($curl_error);
                } else {
                    error_log('Webpushr response [HTTP ' . $http_code . '] Headers: ' . $response_headers);
                    error_log('Webpushr response [HTTP ' . $http_code . '] Body: ' . $response_body);
                    $response_data = json_decode($response_body, true);
                    $error_description = isset($response_data['description']) ? $response_data['description'] : ($response_body ?: 'Empty response');

                    switch ($http_code) {
                        case 200:
                            error_log('Webpushr notification sent successfully');
                            $notificationMessage = 'Notification sent successfully';
                            break;
                        default:
                            error_log('Webpushr error: HTTP ' . $http_code . ' - ' . $error_description);
                            $notificationMessage = 'Notification failed: HTTP ' . $http_code . ' - ' . htmlspecialchars($error_description);
                    }
                }
            } catch (Exception $e) {
                error_log('Webpushr notification exception: ' . $e->getMessage());
                $notificationMessage = 'Notification failed: ' . htmlspecialchars($e->getMessage());
            }

            error_log("Post $postId updated successfully");
            $message = 'Post updated successfully.';
            $messageType = 'success';

            // Redirect to posts.php
            ob_end_clean();
            header('Location: posts.php?message=Post updated successfully');
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

// Fetch post details
try {
    if (!isset($_GET['id']) || empty(trim($_GET['id'])) || !is_numeric($_GET['id'])) {
        throw new Exception('Invalid or missing post ID.');
    }

    $postId = trim($_GET['id']);
    $stmt = $pdo->prepare("
        SELECT id, title, slug, content, excerpt, author, published_date, tags, category, image_url, thumbnail_url
        FROM blogs
        WHERE id = ?
    ");
    $stmt->execute([$postId]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        throw new Exception('Post not found.');
    }

    // Decode tags
    $post['tags'] = !empty($post['tags']) ? json_decode($post['tags'], true) : [];
    $post['tags'] = is_array($post['tags']) ? implode(', ', $post['tags']) : '';
    error_log("Post $postId fetched successfully");
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
    <title>Edit Post | Portfolio</title>

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
        if (!file_exists('partials/sidebar.php')) {
            error_log('Error: sidebar.php not found in partials/');
            ob_clean();
            die('Error: Sidebar file not found.');
        }
        include 'partials/sidebar.php';
        ?>

        <!-- Admin Content -->
        <div class="admin-content">
            <div class="admin-header">
                <h1>Edit Post</h1>
                <div>
                    <a href="posts.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Posts</a>
                </div>
            </div>

            <!-- Display Messages -->
            <?php if ($message): ?>
                <div class="alert <?= $messageType === 'error' ? 'alert-danger' : 'alert-success'; ?>">
                    <?= htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Edit Post Form -->
            <?php if ($post): ?>
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h2>Update Post Details</h2>
                    </div>
                    <form action="edit-post.php?id=<?= htmlspecialchars($post['id']); ?>" method="POST" enctype="multipart/form-data" class="post-form" id="postForm">
                        <input type="hidden" name="post_id" value="<?= htmlspecialchars($post['id']); ?>">
                        <input type="hidden" name="existing_image_url" value="<?= htmlspecialchars($post['image_url']); ?>">
                        <input type="hidden" name="existing_thumbnail_url" value="<?= htmlspecialchars($post['thumbnail_url']); ?>">
                        <div class="form-group">
                            <label for="title">Title *</label>
                            <input type="text" id="title" name="title" class="form-control" value="<?= htmlspecialchars($post['title']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="main_image">Main Image</label>
                            <?php if ($post['image_url']): ?>
                                <p>Current: <a href="<?= htmlspecialchars($post['image_url']); ?>" target="_blank">View Image</a></p>
                            <?php endif; ?>
                            <input type="file" id="main_image" name="main_image" accept="image/jpeg,image/png,image/gif">
                        </div>
                        <div class="form-group">
                            <label for="thumbnail">Thumbnail Image</label>
                            <?php if ($post['thumbnail_url']): ?>
                                <p>Current: <a href="<?= htmlspecialchars($post['thumbnail_url']); ?>" target="_blank">View Thumbnail</a></p>
                            <?php endif; ?>
                            <input type="file" id="thumbnail" name="thumbnail" accept="image/jpeg,image/png,image/gif">
                        </div>
                        <div class="form-group">
                            <label for="content">Content *</label>
                            <div class="formatting-controls">
                                <button type="button" data-open="<strong>" data-close="</strong>">B</button>
                                <button type="button" data-open="<em>" data-close="</em>">I</button>
                                <button type="button" data-open="<h2>" data-close="</h2>">H2</button>
                                <button type="button" data-open="<h3>" data-close="</h3>">H3</button>
                                <button type="button" data-open="<p>" data-close="</p>">P</button>
                                <button type="button" data-open='<a href="#">' data-close="</a>">Link</button>
                                <button type="button" data-open="<ul>\n  <li>" data-close="</li>\n</ul>">List</button>
                            </div>
                            <textarea id="content" name="content" class="form-control" rows="15" required><?= htmlspecialchars($post['content']); ?></textarea>
                        </div>
                        <div class="form-row">
                            <div class="form-group half">
                                <label for="category">Category</label>
                                <input type="text" id="category" name="category" class="form-control" value="<?= htmlspecialchars($post['category']); ?>">
                                <small>E.g., Development, Design, Business</small>
                            </div>
                            <div class="form-group half">
                                <label for="author">Author</label>
                                <input type="text" id="author" name="author" class="form-control" value="<?= htmlspecialchars($post['author']); ?>">
                                <small>Leave empty to use "Admin"</small>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="tags">Tags</label>
                            <input type="text" id="tags" name="tags" class="form-control" value="<?= htmlspecialchars($post['tags']); ?>">
                            <small>Separate tags with commas. E.g., php, web development, tutorial</small>
                        </div>
                        <div class="form-group">
                            <label for="published_date">Published Date *</label>
                            <input type="date" id="published_date" name="published_date" class="form-control" value="<?= htmlspecialchars(date('Y-m-d', strtotime($post['published_date']))); ?>" required>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary" id="submitButton"><i class="fas fa-save"></i> Update Post</button>
                            <span class="loading-spinner" id="loadingSpinner"></span>
                            <?php if ($notificationMessage): ?>
                                <span class="notification-status <?= strpos($notificationMessage, 'successfully') !== false ? 'notification-success' : 'notification-error'; ?>">
                                    <?= htmlspecialchars($notificationMessage); ?>
                                </span>
                            <?php endif; ?>
                            <a href="posts.php" class="btn btn-outline">Cancel</a>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="admin-card">
                    <p>Post not found or an error occurred.</p>
                    <a href="posts.php" class="btn btn-outline">Back to Posts</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Styles -->
    <style>
        .formatting-controls {
            margin-bottom: 10px;
        }
        .formatting-controls button {
            margin-right: 5px;
            padding: 5px 10px;
            background: #f0f0f0;
            border: 1px solid #ccc;
            cursor: pointer;
        }
        .formatting-controls button:hover {
            background: #e0e0e0;
        }
        .loading-spinner {
            display: none;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
            margin-left: 10px;
        }
        .form-row {
            display: flex;
            gap: 20px;
        }
        .form-group.half {
            flex: 1;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .notification-status {
            margin-left: 10px;
            font-size: 14px;
        }
        .notification-success {
            color: #28a745;
        }
        .notification-error {
            color: #dc3545;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>

    <!-- JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const sidebar = document.querySelector('.sidebar');
            const toggleBtn = document.querySelector('.sidebar-toggle');
            const content = document.querySelector('.admin-content');
            const form = document.getElementById('postForm');
            const submitButton = document.getElementById('submitButton');
            const spinner = document.getElementById('loadingSpinner');
            const contentField = document.getElementById('content');

            if (toggleBtn && sidebar && content) {
                toggleBtn.addEventListener('click', function () {
                    sidebar.classList.toggle('collapsed');
                    content.classList.toggle('full-width');
                });
            }

            if (form && submitButton && spinner) {
                form.addEventListener('submit', function () {
                    console.log('Form submitting');
                    submitButton.disabled = true;
                    spinner.style.display = 'inline-block';
                });
            }

            const addFormatting = (openTag, closeTag) => {
                const start = contentField.selectionStart;
                const end = contentField.selectionEnd;
                const selectedText = contentField.value.substring(start, end);
                const beforeText = contentField.value.substring(0, start);
                const afterText = contentField.value.substring(end);
                contentField.value = beforeText + openTag + selectedText + closeTag + afterText;
                contentField.focus();
                contentField.setSelectionRange(start + openTag.length, start + openTag.length + selectedText.length);
            };

            document.querySelectorAll('.formatting-controls button').forEach(btn => {
                btn.addEventListener('click', () => {
                    addFormatting(btn.dataset.open, btn.dataset.close);
                });
            });
        });
    </script>
    <script src="../js/main.js"></script>
</body>
</html>
<?php
ob_end_flush();
?>