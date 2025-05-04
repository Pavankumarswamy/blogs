<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start output buffering
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    error_log('Session started in login.php, ID: ' . session_id());
}

// Debug session
error_log('Session data before login: ' . print_r($_SESSION, true));

// Redirect if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    error_log('Already logged in, redirecting to index.php');
    header('Location: index.php');
    ob_end_flush();
    exit;
}

// Initialize error message
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log('POST request received in login.php');
    
    // Define path for admin-auth.php
    $authFile = '../includes/admin-auth.php';
    if (!file_exists($authFile)) {
        $authFile = 'includes/admin-auth.php'; // Try root includes/
        if (!file_exists($authFile)) {
            error_log('Error: admin-auth.php not found in ../includes/ or includes/');
            die('Error: Authentication file not found. Please check the path to admin-auth.php.');
        }
    }
    
    error_log('Including admin-auth.php from: ' . $authFile);
    require_once $authFile;

    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    error_log('Login attempt: Username=' . $username . ', Password=' . $password);

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
        error_log('Login failed: Empty username or password');
    } else {
        // Check if authenticateAdmin exists
        if (!function_exists('authenticateAdmin')) {
            error_log('Error: authenticateAdmin function not defined');
            die('Error: Authentication function not defined. Please check admin-auth.php.');
        }

        $result = authenticateAdmin($username, $password);

        if ($result) {
            // Regenerate session ID to prevent fixation
            session_regenerate_id(true);

            // Set session variables
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $username;

            error_log('Login successful, session set: ' . print_r($_SESSION, true));
            error_log('Redirecting to index.php');

            // Redirect to admin dashboard
            header('Location: index.php');
            ob_end_flush();
            exit;
        } else {
            $error = 'Invalid username or password.';
            error_log('Login failed: Invalid credentials');
        }
    }
}

// Clean output buffer
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Admin Login | Portfolio</title>

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
        <div class="admin-login-container">
            <div class="admin-login-header">
                <h1><i class="fas fa-lock"></i> Admin Login</h1>
                <p>Enter your credentials to access the admin dashboard</p>
            </div>

            <?php if ($error): ?>
                <div class="admin-message error">
                    <?= htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" class="admin-login-form">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Login</button>
            </form>

            <div class="admin-login-footer">
                <a href="../index.php">Back to Website</a>
            </div>
        </div>
    </div>

    <script src="../js/main.js"></script>
</body>
</html>