<?php
// Start output buffering
ob_start();

// Set JSON header
header('Content-Type: application/json');

// Disable error display, keep logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Log start
error_log('Starting delete-project.php');

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
    die(json_encode(['success' => false, 'message' => 'Authentication failed: ' . htmlspecialchars($e->getMessage())]));
}

$response = ['success' => false, 'message' => ''];

try {
    // Validate project ID
    if (!isset($_POST['project_id']) || empty(trim($_POST['project_id'])) || !is_numeric($_POST['project_id'])) {
        throw new Exception('Invalid or missing project ID.');
    }
    $projectId = trim($_POST['project_id']);
    error_log("Attempting to delete project ID: $projectId");

    // MySQL Database Connection
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    error_log('Connected to MySQL database');

    // Check project existence and get image URLs
    $stmt = $pdo->prepare("SELECT image_url, thumbnail_url FROM projects WHERE id = ?");
    $stmt->execute([$projectId]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        throw new Exception('Project does not exist.');
    }
    error_log('Project exists, image_url: ' . ($project['image_url'] ?: 'none') . ', thumbnail_url: ' . ($project['thumbnail_url'] ?: 'none'));

    // Delete associated images
    $baseUrl = 'https://ggusoc.in/images/projects/';
    $basePath = $_SERVER['DOCUMENT_ROOT'] . '/images/projects/';

    if (!empty($project['image_url'])) {
        $imageFile = str_replace($baseUrl . 'main/', $basePath . 'main/', $project['image_url']);
        if (file_exists($imageFile)) {
            if (unlink($imageFile)) {
                error_log('Deleted main image: ' . $imageFile);
            } else {
                error_log('Failed to delete main image: ' . $imageFile);
            }
        } else {
            error_log('Main image file not found: ' . $imageFile);
        }
    }

    if (!empty($project['thumbnail_url'])) {
        $thumbFile = str_replace($baseUrl . 'thumbnails/', $basePath . 'thumbnails/', $project['thumbnail_url']);
        if (file_exists($thumbFile)) {
            if (unlink($thumbFile)) {
                error_log('Deleted thumbnail: ' . $thumbFile);
            } else {
                error_log('Failed to delete thumbnail: ' . $thumbFile);
            }
        } else {
            error_log('Thumbnail file not found: ' . $thumbFile);
        }
    }

    // Delete project from database
    $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
    $stmt->execute([$projectId]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('Failed to delete project from database.');
    }

    error_log("Project $projectId deleted successfully from MySQL");
    $response['success'] = true;
    $response['message'] = 'Project deleted successfully.';
} catch (Exception $e) {
    error_log('Error: ' . $e->getMessage());
    $response['message'] = 'Error: ' . htmlspecialchars($e->getMessage());
}

ob_clean();
echo json_encode($response);
exit;
?>