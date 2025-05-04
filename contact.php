<?php
// Start output buffering
ob_start();

// Disable error display, keep logging
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Log start
error_log('Starting contact.php');

// Start session if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    error_log('Session started in contact.php, ID: ' . session_id());
}

// Include configuration
$configFile = 'config.php';
if (!file_exists($configFile)) {
    error_log('Error: config.php not found at ' . realpath($configFile));
    ob_clean();
    die('Configuration file not found.');
}
require_once $configFile;

// Check cURL extension
if (!extension_loaded('curl')) {
    error_log('Error: PHP cURL extension not enabled');
    ob_clean();
    die('PHP cURL extension required.');
}

// MySQL Database Connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    error_log('Connected to MySQL database: ' . DB_NAME);
} catch (PDOException $e) {
    error_log('MySQL connection failed: ' . $e->getMessage());
    ob_clean();
    die('Database connection error. Please try again later.');
}

// Create contact_messages table if not exists
try {
    $sql = "
    CREATE TABLE IF NOT EXISTS contact_messages (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
    error_log('contact_messages table checked/created');
} catch (PDOException $e) {
    error_log('contact_messages table creation failed: ' . $e->getMessage());
    ob_clean();
    die('Database table error');
}

// Process form submission
$message = '';
$messageType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Form validation
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $subject = filter_input(INPUT_POST, 'subject', FILTER_SANITIZE_STRING);
    $message_content = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING);
    
    if (empty($name) || empty($email) || empty($subject) || empty($message_content)) {
        $message = 'Please fill in all fields.';
        $messageType = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $messageType = 'error';
    } else {
        // Save to MySQL database
        try {
            $stmt = $pdo->prepare("
                INSERT INTO contact_messages (name, email, subject, message)
                VALUES (:name, :email, :subject, :message)
            ");
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':subject' => $subject,
                ':message' => $message_content
            ]);
            error_log('Contact message saved: ' . $name . ', ' . $email);

            // Send Webpushr notification
            $end_point = 'https://api.webpushr.com/v1/notification/send/sid';
            $http_header = [
                "Content-Type: application/json",
                "webpushrKey: " . WEBPUSHR_KEY,
                "webpushrAuthToken: " . WEBPUSHR_AUTH_TOKEN
            ];
            $req_data = [
                'title' => 'New Contact Form Submission',
                'message' => "From: $name\n Email: $email\nSubject: $subject\nMessage: " . substr($message_content, 0, 100) . (strlen($message_content) > 100 ? '...' : ''),
                'target_url' => SITE_URL . '/contact/',
                'icon' =>'https://ggusoc.in/logo.png',
                'sid' => WEBPUSHR_SID
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_HTTPHEADER, $http_header);
            curl_setopt($ch, CURLOPT_URL, $end_point);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($req_data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FAILONERROR, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($httpCode !== 200 || $curlError) {
                error_log("Webpushr notification failed: HTTP $httpCode, Error: $curlError, Response: $response");
                error_log("Webpushr request data: " . json_encode($req_data));
            } else {
                error_log('Webpushr notification sent successfully: ' . $response);
            }

            $message = 'Thank you for your message! I will get back to you soon.';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'An error occurred while sending your message. Please try again later.';
            $messageType = 'error';
            error_log('Error saving contact message: ' . $e->getMessage());
        }
    }
}

// Include public header
$headerFile = 'includes/header.php';
if (!file_exists($headerFile)) {
    error_log('Error: header.php not found at ' . realpath($headerFile));
    ob_clean();
    die('Header file not found.');
}
include $headerFile;
?>

<!-- Contact Hero Section -->
<section class="contact-hero">
    <div class="container">
        <div class="section-header">
            <h1 class="section-title">Contact Me</h1>
            <p class="section-subtitle">Let's get in touch</p>
        </div>
    </div>
</section>

<!-- Contact Information Section -->
<section class="contact-info-section section">
    <div class="container">
        <div class="contact-grid">
            <div class="contact-card">
                <div class="contact-icon">
                    <i class="fas fa-envelope"></i>
                </div>
                <h3>Email</h3>
                <p>shesettipavankumarswamy@gmail.com</p>
                <a href="mailto:shesettipavankumarswamy@gmail.com" class="contact-link">Send Email</a>
            </div>
            
            <div class="contact-card">
                <div class="contact-icon">
                    <i class="fas fa-phone"></i>
                </div>
                <h3>Phone</h3>
                <p>+91 86391 22823</p>
                <a href="tel:+918639122823" class="contact-link">Call Me</a>
            </div>
            
            <div class="contact-card">
                <div class="contact-icon">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
                <h3>Location</h3>
                <p>Rajamahendravaram, Andhra Pradesh, India</p>
                <a href="https://maps.app.goo.gl/kXv1AqWhzrjFnQcM7" target="_blank" class="contact-link">View on Map</a>
            </div>
        </div>
    </div>
</section>

<!-- Contact Form Section -->
<section class="contact-form-section section">
    <div class="container">
        <div class="form-container">
            <div class="form-header">
                <h2>Send Me a Message</h2>
                <p>Feel free to reach out if you have any questions or want to work together.</p>
            </div>
            
            <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
            <?php endif; ?>
            
            <form action="<?php echo SITE_URL; ?>/contact.php" method="POST" class="contact-form">
                <div class="form-group">
                    <label for="name">Your Name</label>
                    <input type="text" id="name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Your Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="subject">Subject</label>
                    <input type="text" id="subject" name="subject" required>
                </div>
                
                <div class="form-group">
                    <label for="message">Your Message</label>
                    <textarea id="message" name="message" rows="6" required></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">Send Message</button>
            </form>
        </div>
    </div>
</section>

<!-- Social Media Section -->
<section class="social-section section">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">Connect With Me</h2>
            <p class="section-subtitle">Follow me on social media</p>
        </div>
        
        <div class="social-grid">
            <a href="https://x.com/shesetti_pks" target="_blank" rel="me nofollow noopener" class="social-card">
                <h3>X</h3>
                <p>Follow my updates and posts</p>
            </a>
            
            <a href="https://www.linkedin.com/in/pavankumarswamy-sheshetti-12b129253" target="_blank" rel="me nofollow noopener" class="social-card">
                <h3>LinkedIn</h3>
                <p>Connect with me professionally</p>
            </a>
            
            <a href="https://github.com/Pavankumarswamy" target="_blank" rel="me nofollow noopener" class="social-card">
                <h3>GitHub</h3>
                <p>Check out my code repositories and projects</p>
            </a>
        </div>
    </div>
</section>

<style>
/* Form styling */
.contact-form .form-group {
    position: relative;
    margin-bottom: 1.5rem;
}

.contact-form input,
.contact-form textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 1rem;
    transition: border-color 0.3s ease;
}

.contact-form input:focus,
.contact-form textarea:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 5px rgba(0,123,255,0.3);
}

.contact-form label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
}

/* Message styling */
.message {
    padding: 1rem;
    border-radius: 4px;
    margin-bottom: 1rem;
}

.message.success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.message.error {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Button styling */
.btn-primary {
    padding: 0.75rem 1.5rem;
    background-color: #007bff;
    border: none;
    color: white;
    border-radius: 4px;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

.btn-primary:hover {
    background-color: #0056b3;
}
</style>

<?php
// Include public footer
$footerFile = 'includes/footer.php';
if (!file_exists($footerFile)) {
    error_log('Error: footer.php not found at ' . realpath($footerFile));
    ob_clean();
    die('Footer file not found.');
}
include $footerFile;

ob_end_flush();
?>