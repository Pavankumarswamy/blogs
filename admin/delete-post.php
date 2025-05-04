<?php
// Start output buffering
ob_start();

// Set JSON header
header('Content-Type: application/json; charset=UTF-8');

// Disable error display, keep logging
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Log start
error_log('Starting delete-post.php');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    error_log('Session started in delete-post.php, ID: ' . session_id());
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
    echo json_encode(['success' => false, 'message' => 'Authentication failed: ' . htmlspecialchars($e->getMessage())]);
    exit;
}

// Check if post ID is provided
$postId = filter_input(INPUT_POST, 'post_id', FILTER_SANITIZE_NUMBER_INT);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$postId || !is_numeric($postId)) {
    error_log('Invalid delete request: ' . print_r($_POST, true));
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid or missing post ID']);
    exit;
}
error_log('Attempting to delete post ID: ' . $postId);

try {
    // MySQL Database Connection
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    error_log('Connected to MySQL database');

    // Fetch post details to get image URLs
    $stmt = $pdo->prepare("SELECT image_url, thumbnail_url FROM blogs WHERE id = ?");
    $stmt->execute([$postId]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        error_log('Post not found: ID ' . $postId);
        throw new Exception('Post not found');
    }
    error_log('Post exists, image_url: ' . ($post['image_url'] ?: 'none') . ', thumbnail_url: ' . ($post['thumbnail_url'] ?: 'none'));

    // Delete images from filesystem
    $mainImageDir = $_SERVER['DOCUMENT_ROOT'] . '/images/blog/main/';
    $thumbnailDir = $_SERVER['DOCUMENT_ROOT'] . '/images/blog/thumbnails/';
    $mainImageUrlBase = 'https://ggusoc.in/images/blog/main/';
    $thumbnailUrlBase = 'https://ggusoc.in/images/blog/thumbnails/';

    // Delete main image
    if (!empty($post['image_url'])) {
        $mainImagePath = str_replace($mainImageUrlBase, $mainImageDir, $post['image_url']);
        if (file_exists($mainImagePath)) {
            if (!unlink($mainImagePath)) {
                error_log('Failed to delete main image (permissions?): ' . $mainImagePath);
                throw new Exception('Failed to delete main image due to permissions');
            }
            error_log('Deleted main image: ' . $mainImagePath);
        } else {
            error_log('Main image file not found: ' . $mainImagePath);
        }
    }

    // Delete thumbnail
    if (!empty($post['thumbnail_url'])) {
        $thumbImagePath = str_replace($thumbnailUrlBase, $thumbnailDir, $post['thumbnail_url']);
        if (file_exists($thumbImagePath)) {
            if (!unlink($thumbImagePath)) {
                error_log('Failed to delete thumbnail (permissions?): ' . $thumbImagePath);
                throw new Exception('Failed to delete thumbnail due to permissions');
            }
            error_log('Deleted thumbnail: ' . $thumbImagePath);
        } else {
            error_log('Thumbnail file not found: ' . $thumbImagePath);
        }
    }

    // Delete post from MySQL
    $stmt = $pdo->prepare("DELETE FROM blogs WHERE id = ?");
    $stmt->execute([$postId]);
    $rowCount = $stmt->rowCount();

    if ($rowCount === 0) {
        error_log('No rows affected when deleting post: ID ' . $postId);
        throw new Exception('Failed to delete post from database');
    }

    error_log('Post deleted from MySQL: ID ' . $postId);
    ob_clean();
    echo json_encode(['success' => true, 'message' => 'Post and images deleted successfully']);
} catch (Exception $e) {
    error_log('Error deleting post ID ' . $postId . ': ' . $e->getMessage());
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Error: ' . htmlspecialchars($e->getMessage())]);
}

// Ensure no output after JSON
exit;
?>