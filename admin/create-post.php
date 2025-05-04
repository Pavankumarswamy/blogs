<?php
// Start output buffering
ob_start();

// Disable error display, keep logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    error_log('Session started in create-post.php, ID: ' . session_id());
}

// Include configuration
require_once '../config.php';

// Include auth
$authFile = '../includes/admin-auth.php';
if (!file_exists($authFile)) {
    error_log('Error: admin-auth.php not found');
    die('Authentication file not found.');
}
require_once $authFile;

checkAuth();

$message = '';
$messageType = '';
$notificationMessage = '';

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

// Check if thumbnail_url column exists
$hasThumbnailColumn = false;
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM blogs LIKE 'thumbnail_url'");
    if ($stmt->rowCount() > 0) {
        $hasThumbnailColumn = true;
        error_log('thumbnail_url column exists in blogs table');
    } else {
        error_log('thumbnail_url column does not exist in blogs table');
    }
} catch (PDOException $e) {
    error_log('Error checking thumbnail_url column: ' . $e->getMessage());
}

// Generate slug
function generateSlug($title) {
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return $slug;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log('POST received, data: ' . print_r($_POST, true));
    error_log('FILES: ' . print_r($_FILES, true));

    $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $content = filter_input(INPUT_POST, 'content', FILTER_UNSAFE_RAW); // Allow HTML
    $category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $tags = filter_input(INPUT_POST, 'tags', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $author = filter_input(INPUT_POST, 'author', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    if (empty($title) || empty($content)) {
        $message = 'Title and content required.';
        $messageType = 'error';
        error_log('Missing title or content');
    } else {
        try {
            // Define image directories
            $mainImageDir = $_SERVER['DOCUMENT_ROOT'] . '/images/blog/main/';
            $thumbnailDir = $_SERVER['DOCUMENT_ROOT'] . '/images/blog/thumbnails/';
            $mainImageUrlBase = 'https://ggusoc.in/images/blog/main/';
            $thumbnailUrlBase = 'https://ggusoc.in/images/blog/thumbnails/';

            // Ensure directories exist
            if (!is_dir($mainImageDir)) {
                if (!mkdir($mainImageDir, 0755, true)) {
                    throw new Exception('Failed to create main image directory');
                }
            }
            if (!is_dir($thumbnailDir)) {
                if (!mkdir($thumbnailDir, 0755, true)) {
                    throw new Exception('Failed to create thumbnail directory');
                }
            }

            // Handle main image
            $mainImageUrl = '';
            if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
                try {
                    $filePath = $_FILES['main_image']['tmp_name'];
                    error_log('Main image tmp_name: ' . $filePath . ', size: ' . $_FILES['main_image']['size']);
                    if ($_FILES['main_image']['size'] > 10 * 1024 * 1024) {
                        error_log('Main image too large: ' . $_FILES['main_image']['size'] . ' bytes');
                        $message = 'Main image too large (max 10MB).';
                        $messageType = 'error';
                    } elseif (!file_exists($filePath) || !is_readable($filePath)) {
                        error_log('Main image inaccessible: ' . $filePath);
                        $message = 'Main image not found or inaccessible.';
                        $messageType = 'error';
                    } else {
                        $ext = strtolower(pathinfo($_FILES['main_image']['name'], PATHINFO_EXTENSION));
                        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                            error_log('Invalid main image format: ' . $ext);
                            $message = 'Main image must be JPG, PNG, or GIF.';
                            $messageType = 'error';
                        } else {
                            $filename = time() . '_' . uniqid() . '.' . $ext;
                            $destPath = $mainImageDir . $filename;
                            if (!move_uploaded_file($filePath, $destPath)) {
                                error_log('Cannot move main image to: ' . $destPath);
                                $message = 'Cannot save main image.';
                                $messageType = 'error';
                            } else {
                                $mainImageUrl = $mainImageUrlBase . $filename;
                                error_log('Main image saved: ' . $mainImageUrl);
                            }
                        }
                    }
                } catch (Exception $e) {
                    error_log('Main image error: ' . $e->getMessage());
                    $message = 'Main image upload failed: ' . htmlspecialchars($e->getMessage());
                    $messageType = 'error';
                }
            } elseif (isset($_FILES['main_image']) && $_FILES['main_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                error_log('Main image upload error code: ' . $_FILES['main_image']['error']);
                $message = 'Main image upload error (code: ' . $_FILES['main_image']['error'] . ').';
                $messageType = 'error';
            }

            // Handle thumbnail
            $thumbnailUrl = '';
            if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
                try {
                    $filePath = $_FILES['thumbnail']['tmp_name'];
                    error_log('Thumbnail tmp_name: ' . $filePath . ', size: ' . $_FILES['thumbnail']['size']);
                    if ($_FILES['thumbnail']['size'] > 10 * 1024 * 1024) {
                        error_log('Thumbnail too large: ' . $_FILES['thumbnail']['size'] . ' bytes');
                        $message = 'Thumbnail too large (max 10MB).';
                        $messageType = 'error';
                    } elseif (!file_exists($filePath) || !is_readable($filePath)) {
                        error_log('Thumbnail inaccessible: ' . $filePath);
                        $message = 'Thumbnail not found or inaccessible.';
                        $messageType = 'error';
                    } else {
                        $ext = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
                        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                            error_log('Invalid thumbnail format: ' . $ext);
                            $message = 'Thumbnail must be JPG, PNG, or GIF.';
                            $messageType = 'error';
                        } else {
                            $filename = time() . '_' . uniqid() . '.' . $ext;
                            $destPath = $thumbnailDir . $filename;
                            if (!move_uploaded_file($filePath, $destPath)) {
                                error_log('Cannot move thumbnail to: ' . $destPath);
                                $message = 'Cannot save thumbnail.';
                                $messageType = 'error';
                            } else {
                                $thumbnailUrl = $thumbnailUrlBase . $filename;
                                error_log('Thumbnail saved: ' . $thumbnailUrl);
                            }
                        }
                    }
                } catch (Exception $e) {
                    error_log('Thumbnail error: ' . $e->getMessage());
                    $message = 'Thumbnail upload failed: ' . htmlspecialchars($e->getMessage());
                    $messageType = 'error';
                }
            } elseif (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] !== UPLOAD_ERR_NO_FILE) {
                error_log('Thumbnail upload error code: ' . $_FILES['thumbnail']['error']);
                $message = 'Thumbnail upload error (code: ' . $_FILES['thumbnail']['error'] . ').';
                $messageType = 'error';
            }

            // If there’s an error, stop here
            if ($messageType === 'error') {
                throw new Exception($message);
            }

            // Generate slug
            $slug = generateSlug($title);

            // Prepare tags
            $tagArray = !empty($tags) ? array_filter(array_map('trim', explode(',', $tags))) : [];
            $tagsJson = json_encode($tagArray);

            // Prepare post data for MySQL
            $published_date = date('Y-m-d H:i:s');
            $author = $author ?: 'Admin';
            $excerpt = substr(strip_tags($content), 0, 255);
            if (strlen(strip_tags($content)) > 252) {
                $excerpt .= '...';
            }

            // Insert into MySQL
            if ($hasThumbnailColumn) {
                $stmt = $pdo->prepare("
                    INSERT INTO blogs (title, slug, content, excerpt, author, published_date, tags, category, image_url, thumbnail_url)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
                    $mainImageUrl ?: null,
                    $thumbnailUrl ?: null
                ]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO blogs (title, slug, content, excerpt, author, published_date, tags, category, image_url)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
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
                    $mainImageUrl ?: null
                ]);
            }
            $postId = $pdo->lastInsertId();
            error_log("Post inserted into MySQL, ID: $postId, Slug: $slug");

            // Send Webpushr Notification
            try {
                $end_point = 'https://api.webpushr.com/v1/notification/send/all';
                $http_header = [
                    'Content-Type: application/json',
                    'webpushrKey: 365e5959e93fb3ec1a42096ea5955749',
                    'webpushrAuthToken: 108478'
                ];

                $post_url = "https://ggusoc.in/blog/$slug";

                // Sanitize parameters
                $notification_title = substr(preg_replace('/[^\x20-\x7E]/', '', 'New Blog Post: ' . $title), 0, 100);
                $notification_message = substr(preg_replace('/[^\x20-\x7E]/', '', $excerpt), 0, 255);
                if (strlen($notification_message) > 252) {
                    $notification_message = substr($notification_message, 0, 252) . '...';
                }
                $notification_name = substr(preg_replace('/[^\x20-\x7E]/', '', 'Blog Post: ' . $title), 0, 100);

                // Use thumbnail or fallback
                $image_url = $thumbnailUrl ?: $mainImageUrl ?: 'https://ggusoc.in/images/default-notification.jpg';

                $req_data = [
                    'title' => $notification_title,
                    'message' => $notification_message,
                    'target_url' => $post_url,
                    'image' => $image_url,
                    'icon' =>'https://ggusoc.in/logo.png',
                    'auto_hide' => 1,
                    'name' => $notification_name
                ];

                error_log('Webpushr request data: ' . json_encode($req_data));

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
                $curl_errno = curl_errno($ch);

                $response_headers = substr($response, 0, $header_size);
                $response_body = substr($response, $header_size);
                curl_close($ch);

                if ($response === false) {
                    error_log('Webpushr cURL error [' . $curl_errno . ']: ' . $curl_error);
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
                        case 401:
                            error_log('Webpushr error: Invalid API key or token');
                            $notificationMessage = 'Notification failed: Invalid API key or token';
                            break;
                        case 402:
                            error_log('Webpushr error: Bad request');
                            $notificationMessage = 'Notification failed: Bad request';
                            break;
                        case 403:
                            error_log('Webpushr error: Unauthorized');
                            $notificationMessage = 'Notification failed: Unauthorized';
                            break;
                        case 404:
                            error_log('Webpushr error: Rate limit exceeded');
                            $notificationMessage = 'Notification failed: Rate limit exceeded';
                            break;
                        case 405:
                            error_log('Webpushr error: Missing required parameters');
                            $notificationMessage = 'Notification failed: Missing parameters';
                            break;
                        case 406:
                            error_log('Webpushr error: Invalid parameter format - ' . $error_description);
                            $notificationMessage = 'Notification failed: Invalid format - ' . htmlspecialchars($error_description);
                            break;
                        case 407:
                            error_log('Webpushr error: Invalid parameter value - ' . $error_description);
                            $notificationMessage = 'Notification failed: Invalid value - ' . htmlspecialchars($error_description);
                            break;
                        case 408:
                            error_log('Webpushr error: Invalid parameter type - ' . $error_description);
                            $notificationMessage = 'Notification failed: Invalid type - ' . htmlspecialchars($error_description);
                            break;
                        case 500:
                            error_log('Webpushr error: Internal server error');
                            $notificationMessage = 'Notification failed: Server error';
                            break;
                        default:
                            error_log('Webpushr error: Unexpected HTTP code ' . $http_code);
                            $notificationMessage = 'Notification failed: Unexpected error - HTTP ' . $http_code;
                    }
                }
            } catch (Exception $e) {
                error_log('Webpushr notification exception: ' . $e->getMessage());
                $notificationMessage = 'Notification failed: ' . htmlspecialchars($e->getMessage());
            }

            $message = 'Post created successfully';
            $messageType = 'success';
        } catch (Exception $e) {
            error_log('Error: ' . $e->getMessage());
            $message = 'Error creating post: ' . htmlspecialchars($e->getMessage());
            $messageType = 'error';
        }
    }
}

// Include header
$headerFile = '../includes/admin-header.php';
if (!file_exists($headerFile)) {
    error_log('Error: admin-header.php not found');
    die('Header file not found.');
}
include $headerFile;

// Check for CSS/JS files
$css_path = $_SERVER['DOCUMENT_ROOT'] . '/css/admin.css';
$js_path = $_SERVER['DOCUMENT_ROOT'] . '/js/admin.js';
error_log('Checking admin resource paths:');
error_log('admin.css exists: ' . (file_exists($css_path) ? 'Yes' : 'No') . ' at ' . $css_path);
error_log('admin.js exists: ' . (file_exists($js_path) ? 'Yes' : 'No') . ' at ' . $js_path);
?>

<div class="admin-container">
    <div class="admin-header">
        <h1>Create New Blog Post</h1>
        <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <div class="admin-content">
        <?php if ($message): ?>
            <div class="alert <?= $messageType === 'error' ? 'alert-danger' : 'alert-success'; ?>">
                <?= htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="post-form" id="postForm">
            <div class="form-group">
                <label for="title">Title *</label>
                <input type="text" id="title" name="title" required value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="mainImage">Main Image</label>
                <input type="file" id="mainImage" name="main_image" accept="image/jpeg,image/png,image/gif">
            </div>

            <div class="form-group">
                <label for="thumbnail">Thumbnail Image</label>
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
                <textarea id="content" name="content" rows="15" required><?= htmlspecialchars($_POST['content'] ?? '') ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group half">
                    <label for="category">Category</label>
                    <input type="text" id="category" name="category" value="<?= htmlspecialchars($_POST['category'] ?? '') ?>">
                    <small>E.g., Development, Design, Business</small>
                </div>

                <div class="form-group half">
                    <label for="author">Author</label>
                    <input type="text" id="author" name="author" value="<?= htmlspecialchars($_POST['author'] ?? '') ?>">
                    <small>Leave empty to use "Admin"</small>
                </div>
            </div>

            <div class="form-group">
                <label for="tags">Tags</label>
                <input type="text" id="tags" name="tags" value="<?= htmlspecialchars($_POST['tags'] ?? '') ?>">
                <small>Separate tags with commas. E.g., php, web development, tutorial</small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary" id="submitButton">Create Post</button>
                <span class="loading-spinner" id="loadingSpinner"></span>
                <?php if ($notificationMessage): ?>
                    <span class="notification-status <?= strpos($notificationMessage, 'successfully') !== false ? 'notification-success' : 'notification-error'; ?>">
                        <?= htmlspecialchars($notificationMessage); ?>
                    </span>
                <?php endif; ?>
                <a href="index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

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

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('postForm');
    const submitButton = document.getElementById('submitButton');
    const spinner = document.getElementById('loadingSpinner');
    const contentField = document.getElementById('content');

    form.addEventListener('submit', function () {
        console.log('Form submitting');
        submitButton.disabled = true;
        spinner.style.display = 'inline-block';
    });

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

<?php
$footerFile = '../includes/admin-footer.php';
if (!file_exists($footerFile)) {
    error_log('Error: admin-footer.php not found');
    die('Footer file not found.');
}
include $footerFile;
ob_end_flush();
?>