<?php
// Start output buffering
ob_start();

// Disable error display, keep logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Log start
error_log('Starting edit-event.php');

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
$event = null;
$message = '';
$messageType = '';
$errors = [];
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
    ob_clean();
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Sanitize and validate inputs
        $eventId = filter_input(INPUT_POST, 'event_id', FILTER_SANITIZE_NUMBER_INT);
        $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $created_at = filter_input(INPUT_POST, 'created_at', FILTER_SANITIZE_STRING);

        // Validate required fields
        if (empty($eventId) || !is_numeric($eventId)) {
            $errors[] = 'Invalid event ID.';
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
            $slug = 'event-' . time();
        }

        // Handle file uploads
        $thumbnail_url = filter_input(INPUT_POST, 'existing_thumbnail_url', FILTER_SANITIZE_URL);
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/images/events/';
        $thumbnailDir = $uploadDir . 'thumbnails/';
        $galleryDir = $uploadDir . 'gallery/';
        $baseUrl = 'https://ggusoc.in/images/events/';

        // Ensure directories exist
        if (!is_dir($thumbnailDir)) mkdir($thumbnailDir, 0755, true);
        if (!is_dir($galleryDir)) mkdir($galleryDir, 0755, true);

        // Thumbnail
        if (!empty($_FILES['thumbnail']['name']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
            $thumbnail = $_FILES['thumbnail'];
            $ext = pathinfo($thumbnail['name'], PATHINFO_EXTENSION);
            $thumbName = $slug . '-' . time() . '-thumb.' . $ext;
            $thumbPath = $thumbnailDir . $thumbName;

            if ($thumbnail['size'] > 10 * 1024 * 1024) {
                $errors[] = 'Thumbnail too large (max 10MB).';
                error_log('Thumbnail too large: ' . $thumbnail['size'] . ' bytes');
            } elseif (!in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif'])) {
                $errors[] = 'Thumbnail must be JPG, PNG, or GIF.';
                error_log('Invalid thumbnail format: ' . $ext);
            } elseif (move_uploaded_file($thumbnail['tmp_name'], $thumbPath)) {
                // Delete old thumbnail
                if (!empty($thumbnail_url)) {
                    $oldThumbFile = str_replace($baseUrl . 'thumbnails/', $thumbnailDir, $thumbnail_url);
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

        // Handle gallery images
        $existingGalleryImages = json_decode(filter_input(INPUT_POST, 'existing_gallery_images', FILTER_SANITIZE_STRING) ?: '[]', true);
        $galleryUrls = is_array($existingGalleryImages) ? $existingGalleryImages : [];
        if (isset($_FILES['gallery_images']) && is_array($_FILES['gallery_images']['name'])) {
            $fileCount = count($_FILES['gallery_images']['name']);
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['gallery_images']['error'][$i] === UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $_FILES['gallery_images']['name'][$i],
                        'tmp_name' => $_FILES['gallery_images']['tmp_name'][$i],
                        'size' => $_FILES['gallery_images']['size'][$i],
                        'error' => $_FILES['gallery_images']['error'][$i]
                    ];
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $galleryName = $slug . '-' . time() . '-gallery-' . $i . '.' . $ext;
                    $galleryPath = $galleryDir . $galleryName;

                    if ($file['size'] > 10 * 1024 * 1024) {
                        $errors[] = 'Gallery image ' . ($i + 1) . ' too large (max 10MB).';
                        error_log('Gallery image ' . $i . ' too large: ' . $file['size'] . ' bytes');
                    } elseif (!in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif'])) {
                        $errors[] = 'Gallery image ' . ($i + 1) . ' must be JPG, PNG, or GIF.';
                        error_log('Invalid gallery image ' . $i . ' format: ' . $ext);
                    } elseif (move_uploaded_file($file['tmp_name'], $galleryPath)) {
                        $galleryUrls[] = $baseUrl . 'gallery/' . $galleryName;
                        error_log('Gallery image ' . $i . ' uploaded: ' . $galleryPath);
                    } else {
                        $errors[] = 'Failed to upload gallery image ' . ($i + 1) . '.';
                        error_log('Gallery image ' . $i . ' upload failed: ' . print_r($file, true));
                    }
                }
            }
        }

        // Update event if no errors
        if (empty($errors)) {
            // Start transaction
            $pdo->beginTransaction();

            // Update events table
            $stmt = $pdo->prepare("
                UPDATE events
                SET title = ?, description = ?, thumbnail_url = ?, created_at = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $title,
                $description,
                $thumbnail_url ?: null,
                $created_at,
                $eventId
            ]);

            if ($stmt->rowCount() === 0 && empty($_FILES['thumbnail']['name']) && empty($_FILES['gallery_images']['name'])) {
                throw new Exception('No changes made to event or event not found.');
            }

            // Update gallery images
            $stmt = $pdo->prepare("DELETE FROM event_images WHERE event_id = ?");
            $stmt->execute([$eventId]);
            if (!empty($galleryUrls)) {
                $stmt = $pdo->prepare("INSERT INTO event_images (event_id, image_url) VALUES (?, ?)");
                foreach ($galleryUrls as $url) {
                    $stmt->execute([$eventId, $url]);
                    error_log("Gallery image updated for event ID: $eventId, URL: $url");
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
                $notification_title = substr(preg_replace('/[^\x20-\x7E]/', '', 'Updated Event: ' . $title), 0, 100);
                $notification_message = substr(preg_replace('/[^\x20-\x7E]/', '', strip_tags($description)), 0, 255);
                if (strlen(strip_tags($description)) > 252) {
                    $notification_message .= '...';
                }
                $notification_name = substr(preg_replace('/[^\x20-\x7E]/', '', 'Event: ' . $title), 0, 100);

                // Use thumbnail or fallback
                $image_url = $thumbnail_url ?: 'https://ggusoc.in/images/default-notification.jpg';
                $icon_url = 'https://ggusoc.in/logo.png';

                $req_data = [
                    'title' => $notification_title,
                    'message' => $notification_message,
                    'target_url' => $target_url,
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

            // Commit transaction
            $pdo->commit();
            error_log("Event $eventId updated successfully");
            $message = 'Event updated successfully.';
            $messageType = 'success';

            // Redirect to events.php
            ob_end_clean();
            header('Location: events.php?message=Event updated successfully');
            exit;
        } else {
            $message = implode('<br>', $errors);
            $messageType = 'error';
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Update error: ' . $e->getMessage());
        $message = 'Error: ' . htmlspecialchars($e->getMessage());
        $messageType = 'error';
    }
}

// Fetch event details
try {
    if (!isset($_GET['id']) || empty(trim($_GET['id'])) || !is_numeric($_GET['id'])) {
        throw new Exception('Invalid or missing event ID.');
    }

    $eventId = trim($_GET['id']);
    $stmt = $pdo->prepare("
        SELECT id, title, description, thumbnail_url, created_at
        FROM events
        WHERE id = ?
    ");
    $stmt->execute([$eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        throw new Exception('Event not found.');
    }

    // Fetch gallery images
    $stmt = $pdo->prepare("SELECT image_url FROM event_images WHERE event_id = ?");
    $stmt->execute([$eventId]);
    $event['gallery_images'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    error_log("Event $eventId fetched successfully");
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
    <title>Edit Event | Portfolio</title>

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
                <h1>Edit Event</h1>
                <div>
                    <a href="events.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Events</a>
                </div>
            </div>

            <!-- Display Messages -->
            <?php if ($message): ?>
                <div class="alert <?= $messageType === 'error' ? 'alert-danger' : 'alert-success'; ?>">
                    <?= htmlspecialchars($message); ?>
                    <?php if ($notificationMessage): ?>
                        <br><span class="<?= strpos($notificationMessage, 'successfully') !== false ? 'notification-success' : 'notification-error'; ?>">
                            <?= htmlspecialchars($notificationMessage); ?>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Edit Event Form -->
            <?php if ($event): ?>
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h2>Update Event Details</h2>
                    </div>
                    <form action="edit-event.php?id=<?= htmlspecialchars($event['id']); ?>" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="event_id" value="<?= htmlspecialchars($event['id']); ?>">
                        <input type="hidden" name="existing_thumbnail_url" value="<?= htmlspecialchars($event['thumbnail_url']); ?>">
                        <input type="hidden" name="existing_gallery_images" value="<?= htmlspecialchars(json_encode($event['gallery_images'])); ?>">
                        <div class="form-group">
                            <label for="title">Event Title *</label>
                            <input type="text" id="title" name="title" class="form-control" value="<?= htmlspecialchars($event['title']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="thumbnail">Thumbnail Image</label>
                            <?php if ($event['thumbnail_url']): ?>
                                <p>Current: <a href="<?= htmlspecialchars($event['thumbnail_url']); ?>" target="_blank">View Thumbnail</a></p>
                            <?php endif; ?>
                            <input type="file" id="thumbnail" name="thumbnail" accept="image/jpeg,image/png,image/gif">
                        </div>
                        <div class="form-group">
                            <label for="gallery_images">Gallery Images</label>
                            <?php if (!empty($event['gallery_images'])): ?>
                                <p>Current Images:</p>
                                <ul>
                                    <?php foreach ($event['gallery_images'] as $image): ?>
                                        <li><a href="<?= htmlspecialchars($image); ?>" target="_blank">View Image</a></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <input type="file" id="gallery_images" name="gallery_images[]" multiple accept="image/jpeg,image/png,image/gif">
                            <small>Upload new images to add to the gallery</small>
                        </div>
                        <div class="form-group">
                            <label for="description">Description *</label>
                            <textarea id="description" name="description" class="form-control" rows="10" required><?= htmlspecialchars($event['description']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="created_at">Created Date *</label>
                            <input type="date" id="created_at" name="created_at" class="form-control" value="<?= htmlspecialchars(date('Y-m-d', strtotime($event['created_at']))); ?>" required>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Event</button>
                            <a href="events.php" class="btn btn-outline">Cancel</a>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="admin-card">
                    <p>Event not found or an error occurred.</p>
                    <a href="events.php" class="btn btn-outline">Back to Events</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- JavaScript -->
    <style>
        .notification-success {
            color: #28a745;
        }
        .notification-error {
            color: #dc3545;
        }
    </style>
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