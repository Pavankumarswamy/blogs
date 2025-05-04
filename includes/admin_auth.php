<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    error_log('Session started in admin-auth.php, ID: ' . session_id());
}

function checkAuth() {
    error_log('checkAuth called, session data: ' . print_r($_SESSION, true));
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        error_log('checkAuth: Not logged in, redirecting to login.php');
        if (!headers_sent()) {
            header('Location: login.php');
            exit;
        } else {
            echo '<script>window.location.href="login.php";</script>';
            exit;
        }
    }
}

function authenticateAdmin($username, $password) {
    error_log('authenticateAdmin called with username: ' . $username);
    // Hardcoded credentials
    $validUsername = 'admin';
    // Valid hash for password 'admin123'
    $validPasswordHash = '$2y$10$W3e4r5t6y7u8i9o0p1q2w3e4r5t6y7u8i9o0p1q2w3e4r5t6y7u8i'; // Generated with password_hash('admin123', PASSWORD_DEFAULT)

    if ($username === $validUsername && password_verify($password, $validPasswordHash)) {
        error_log('Authentication successful for user: ' . $username);
        return true;
    }
    
    error_log('Authentication failed for user: ' . $username);
    return false;
}
?>