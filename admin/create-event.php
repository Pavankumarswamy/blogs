<?php
// Disable error display, keep logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Start output buffering
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    error_log('Session started in create-event.php, ID: ' . session_id());
}

// Include required files
require_once '../config.php';
require_once '../includes/admin-auth.php';

// Check authentication
checkAuth();

// Initialize variables
$errors = [];
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
    error_log('Connected to MySQL database in create-event.php');

    // Create events table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            thumbnail_url VARCHAR(255),
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    error_log('Ensured events table exists with id as auto-increment primary key');

    // Create event_images table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS event_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            image_url VARCHAR(255) NOT NULL,
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    error_log('Ensured event_images table exists with foreign key to events.id');
} catch (PDOException $e) {
    error_log('MySQL connection or table creation failed: ' . $e->getMessage());
    $message = 'Database setup failed.';
    $messageType = 'error';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($message)) {
    error_log('POST received, data: ' . print_r($_POST, true));
    error_log('FILES: ' . print_r($_FILES, true));

    // Sanitize and validate inputs
    $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    // Validate required fields
    if (empty($title)) $errors[] = 'Title is required.';
    if (empty($description)) $errors[] = 'Description is required.';

    // Define image directories
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/images/events/';
    $thumbnailDir = $uploadDir . 'thumbnails/';
    $galleryDir = $uploadDir . 'gallery/';
    $thumbnailUrlBase = 'https://ggusoc.in/images/events/thumbnails/';
    $galleryUrlBase = 'https://ggusoc.in/images/events/gallery/';

    // Ensure directories exist
    foreach ([$thumbnailDir, $galleryDir] as $dir) {
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                $errors[] = 'Failed to create directory: ' . basename($dir);
                error_log('Failed to create directory: ' . $dir);
            }
        }
    }

    // Handle thumbnail
    $thumbnailUrl = '';
    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
        try {
            $filePath = $_FILES['thumbnail']['tmp_name'];
            error_log('Thumbnail tmp_name: ' . $filePath . ', size: ' . $_FILES['thumbnail']['size']);
            if ($_FILES['thumbnail']['size'] > 10 * 1024 * 1024) {
                $errors[] = 'Thumbnail too large (max 10MB).';
                error_log('Thumbnail too large: ' . $_FILES['thumbnail']['size'] . ' bytes');
            } elseif (!file_exists($filePath) || !is_readable($filePath)) {
                $errors[] = 'Thumbnail not found or inaccessible.';
                error_log('Thumbnail inaccessible: ' . $filePath);
            } else {
                $ext = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                    $errors[] = 'Thumbnail must be JPG, PNG, or GIF.';
                    error_log('Invalid thumbnail format: ' . $ext);
                } else {
                    $filename = time() . '_' . uniqid() . '_thumb.' . $ext;
                    $destPath = $thumbnailDir . $filename;
                    if (!move_uploaded_file($filePath, $destPath)) {
                        $errors[] = 'Cannot save thumbnail.';
                        error_log('Cannot move thumbnail to: ' . $destPath);
                    } else {
                        $thumbnailUrl = $thumbnailUrlBase . $filename;
                        error_log('Thumbnail saved: ' . $thumbnailUrl);
                    }
                }
            }
        } catch (Exception $e) {
            $errors[] = 'Thumbnail upload failed: ' . htmlspecialchars($e->getMessage());
            error_log('Thumbnail error: ' . $e->getMessage());
        }
    } elseif (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] !== UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Thumbnail upload error (code: ' . $_FILES['thumbnail']['error'] . ').';
        error_log('Thumbnail upload error code: ' . $_FILES['thumbnail']['error']);
    }

    // Handle gallery images
    $galleryUrls = [];
    if (isset($_FILES['gallery_images']) && is_array($_FILES['gallery_images']['name'])) {
        $fileCount = count($_FILES['gallery_images']['name']);
        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['gallery_images']['error'][$i] === UPLOAD_ERR_OK) {
                try {
                    $filePath = $_FILES['gallery_images']['tmp_name'][$i];
                    error_log('Gallery image ' . $i . ' tmp_name: ' . $filePath . ', size: ' . $_FILES['gallery_images']['size'][$i]);
                    if ($_FILES['gallery_images']['size'][$i] > 10 * 1024 * 1024) {
                        $errors[] = 'Gallery image ' . ($i + 1) . ' too large (max 10MB).';
                        error_log('Gallery image ' . $i . ' too large: ' . $_FILES['gallery_images']['size'][$i] . ' bytes');
                    } elseif (!file_exists($filePath) || !is_readable($filePath)) {
                        $errors[] = 'Gallery image ' . ($i + 1) . ' not found or inaccessible.';
                        error_log('Gallery image ' . $i . ' inaccessible: ' . $filePath);
                    } else {
                        $ext = strtolower(pathinfo($_FILES['gallery_images']['name'][$i], PATHINFO_EXTENSION));
                        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                            $errors[] = 'Gallery image ' . ($i + 1) . ' must be JPG, PNG, or GIF.';
                            error_log('Invalid gallery image ' . $i . ' format: ' . $ext);
                        } else {
                            $filename = time() . '_' . uniqid() . '_gallery_' . $i . '.' . $ext;
                            $destPath = $galleryDir . $filename;
                            if (!move_uploaded_file($filePath, $destPath)) {
                                $errors[] = 'Cannot save gallery image ' . ($i + 1) . '.';
                                error_log('Cannot move gallery image ' . $i . ' to: ' . $destPath);
                            } else {
                                $galleryUrls[] = $galleryUrlBase . $filename;
                                error_log('Gallery image ' . $i . ' saved: ' . $galleryUrlBase . $filename);
                            }
                        }
                    }
                } catch (Exception $e) {
                    $errors[] = 'Gallery image ' . ($i + 1) . ' upload failed: ' . htmlspecialchars($e->getMessage());
                    error_log('Gallery image ' . $i . ' error: ' . $e->getMessage());
                }
            } elseif ($_FILES['gallery_images']['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                $errors[] = 'Gallery image ' . ($i + 1) . ' upload error (code: ' . $_FILES['gallery_images']['error'][$i] . ').';
                error_log('Gallery image ' . $i . ' upload error code: ' . $_FILES['gallery_images']['error'][$i]);
            }
        }
    }

    // If no errors, proceed with database insertion and notification
    if (empty($errors)) {
        try {
            // Insert into events table
            $stmt = $pdo->prepare("
                INSERT INTO events (title, description, thumbnail_url, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([
                $title,
                $description,
                $thumbnailUrl ?: null
            ]);
            $eventId = $pdo->lastInsertId();
            error_log("Event inserted into MySQL, ID: $eventId");

            // Insert gallery images into event_images table
            if (!empty($galleryUrls)) {
                $stmt = $pdo->prepare("
                    INSERT INTO event_images (event_id, image_url)
                    VALUES (?, ?)
                ");
                foreach ($galleryUrls as $url) {
                    $stmt->execute([$eventId, $url]);
                    error_log("Gallery image inserted for event ID: $eventId, URL: $url");
                }
            }

            // Send Webpushr Notification
            try {
                $end_point = 'https://api.webpushr.com/v1/notification/send/all';
                $http_header = [
                    'Content-Type: application/json',
                    'webpushrKey: 365e5959e93fb3ec1a42096ea5955749',
                    'webpushrAuthToken: 108478'
                ];

                // Set target URL
                $target_url = 'https://ggusoc.in/events';

                // Sanitize parameters
                $notification_title = substr(preg_replace('/[^\x20-\x7E]/', '', 'New Event: ' . $title), 0, 100);
                $notification_message = substr(preg_replace('/[^\x20-\x7E]/', '', strip_tags($description)), 0, 255);
                if (strlen(strip_tags($description)) > 252) {
                    $notification_message .= '...';
                }
                $notification_name = substr(preg_replace('/[^\x20-\x7E]/', '', 'Event: ' . $title), 0, 100);

                // Use thumbnail or fallback
                $image_url = $thumbnailUrl ?: 'https://ggusoc.in/images/default-notification.jpg';
                $icon_url = 'https://ggusoc.in/logo.png';

                $req_data = [
                    'title' => $notification_title,
                    'message' => $notification_message,
                    'target_url' => $target_url,
                    'image' => $image_url,
                    'icon' => $icon_url,
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

            $message = 'Event created successfully';
            $messageType = 'success';
        } catch (PDOException $e) {
            error_log('Error inserting event: ' . $e->getMessage());
            $message = 'Failed to create event: ' . htmlspecialchars($e->getMessage());
            $messageType = 'error';
        }
    } else {
        $message = implode('<br>', $errors);
        $messageType = 'error';
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
        <h1>Create New Event</h1>
        <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <div class="admin-content">
        <?php if ($message): ?>
            <div class="alert <?= $messageType === 'error' ? 'alert-danger' : 'alert-success'; ?>">
                <?= htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="event-form" id="eventForm">
            <div class="form-group">
                <label for="title">Event Title *</label>
                <input type="text" id="title" name="title" required value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="thumbnail">Thumbnail Image</label>
                <input type="file" id="thumbnail" name="thumbnail" accept="image/jpeg,image/png,image/gif">
            </div>

            <div class="form-group">
                <label for="gallery_images">Gallery Images</label>
                <input type="file" id="gallery_images" name="gallery_images[]" multiple accept="image/jpeg,image/png,image/gif">
                <small>Upload multiple images for the event gallery</small>
            </div>

            <div class="form-group">
                <label for="description">Description *</label>
                <textarea id="description" name="description" rows="10" required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary" id="submitButton">Create Event</button>
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
.loading-spinner {
    display: none;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #3498db;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    animation: spin 1s linear infinite;
    margin-left: 10px;
    vertical-align: middle;
}
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
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
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('eventForm');
    const submitButton = document.getElementById('submitButton');
    const spinner = document.getElementById('loadingSpinner');

    form.addEventListener('submit', function () {
        console.log('Form submitting');
        submitButton.disabled = true;
        spinner.style.display = 'inline-block';
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