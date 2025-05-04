<?php
// Start output buffering
ob_start();

// Disable error display, keep logging
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Log start
error_log('Starting projects.php');

// Start session if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    error_log('Session started in projects.php, ID: ' . session_id());
}

// Include configuration
$configFile = 'config.php';
if (!file_exists($configFile)) {
    error_log('Error: config.php not found at ' . realpath($configFile));
    ob_clean();
    die('Configuration file not found.');
}
require_once $configFile;

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
    die('Database connection failed. Please try again later.');
}

// Fetch all projects
$projects = [];
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT id, title, description, technologies, project_url, github_url, image_url, thumbnail_url, created_at
        FROM projects
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Decode technologies and validate
    $uniqueProjects = [];
    $seenIds = [];
    foreach ($projects as &$project) {
        if (in_array($project['id'], $seenIds)) {
            error_log('Duplicate project ID found: ' . $project['id']);
            continue; // Skip duplicates
        }
        $seenIds[] = $project['id'];
        $project['technologies'] = !empty($project['technologies']) && json_decode($project['technologies'], true) !== null 
            ? json_decode($project['technologies'], true) 
            : [];
        $project['technologies'] = is_array($project['technologies']) ? $project['technologies'] : [];
        $uniqueProjects[] = $project;
        error_log('Processing project ID: ' . $project['id'] . ', Title: ' . $project['title']);
    }
    $projects = $uniqueProjects;
    error_log('Fetched ' . count($projects) . ' unique projects from database: ' . print_r(array_map(function($p) { return $p['id'] . ':' . $p['title']; }, $projects), true));
} catch (PDOException $e) {
    error_log('Error fetching projects: ' . $e->getMessage());
    $projects = [];
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

<!-- Projects Hero Section -->
<section class="projects-hero">
    <div class="container">
        <div class="section-header">
            <h1 class="section-title">My Projects</h1>
            <p class="section-subtitle">Showcasing my best work</p>
        </div>
    </div>
</section>

<!-- Projects Filter Section -->
<section class="projects-filter">
    <div class="container">
        <div class="filter-buttons">
            <button class="filter-btn active" data-filter="all">All</button>
            <button class="filter-btn" data-filter="web-development">Web Development</button>
            <button class="filter-btn" data-filter="ui-design">UI Design</button>
            <button class="filter-btn" data-filter="mobile-apps">Mobile Apps</button>
        </div>
    </div>
</section>

<!-- Projects Grid Section -->
<section class="projects-showcase section">
    <div class="container">
        <div class="projects-grid">
            <?php if (empty($projects)): ?>
                <div class="empty-state">
                    <p>No projects found. Check back soon for new content!</p>
                </div>
            <?php else: ?>
                <?php foreach ($projects as $project): ?>
                    <?php
                    // Determine category based on technologies
                    $category = 'mobile-apps'; // Default
                    if (is_array($project['technologies'])) {
                        $technologies = array_map('strtolower', $project['technologies']);
                        if (in_array(strtolower('HTML'), $technologies, true) || 
                            in_array(strtolower('CSS'), $technologies, true) || 
                            in_array(strtolower('JavaScript'), $technologies, true) || 
                            in_array(strtolower('PHP'), $technologies, true) || 
                            in_array(strtolower('React'), $technologies, true)) {
                            $category = 'web-development';
                        } elseif (in_array(strtolower('UI/UX'), $technologies, true) || 
                                in_array(strtolower('Figma'), $technologies, true) || 
                                in_array(strtolower('Design'), $technologies, true)) {
                            $category = 'ui-design';
                        } elseif (in_array(strtolower('Flutter'), $technologies, true) || 
                                in_array(strtolower('Dart'), $technologies, true) || 
                                in_array(strtolower('Android'), $technologies, true)) {
                            $category = 'mobile-apps';
                        }
                    }
                    error_log('Rendering project ID: ' . $project['id'] . ', Title: ' . $project['title'] . ', Category: ' . $category);
                    ?>
                    <!-- Project Card -->
                    <div class="project-card" id="project<?php echo htmlspecialchars($project['id']); ?>" data-category="<?php echo htmlspecialchars($category); ?>">
                        <div class="project-img">
                            <?php if (!empty($project['thumbnail_url'])): ?>
                                <img src="<?php echo htmlspecialchars($project['thumbnail_url']); ?>" alt="<?php echo htmlspecialchars($project['title']); ?>">
                            <?php elseif (!empty($project['image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($project['image_url']); ?>" alt="<?php echo htmlspecialchars($project['title']); ?>">
                            <?php else: ?>
                                <svg class="project-svg" viewBox="0 0 400 300" xmlns="http://www.w3.org/2000/svg">
                                    <rect width="400" height="300" fill="#f5f5f5"/>
                                    <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="Arial" font-size="24" fill="#333"><?php echo htmlspecialchars($project['title']); ?></text>
                                </svg>
                            <?php endif; ?>
                        </div>
                        <div class="project-content">
                            <h3 class="project-title"><?php echo htmlspecialchars($project['title']); ?></h3>
                            <p class="project-description"><?php echo htmlspecialchars($project['description'] ?? ''); ?></p>
                            <div class="project-tags">
                                <?php if (!empty($project['technologies'])): ?>
                                    <?php foreach ($project['technologies'] as $tech): ?>
                                        <span class="tag"><?php echo htmlspecialchars($tech); ?></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="project-links">
                                <?php if (!empty($project['project_url'])): ?>
                                    <a href="<?php echo htmlspecialchars($project['project_url']); ?>" class="btn btn-sm btn-outline" target="_blank">View Live</a>
                                <?php endif; ?>
                                <?php if (!empty($project['github_url'])): ?>
                                    <a href="<?php echo htmlspecialchars($project['github_url']); ?>" class="btn btn-sm btn-outline" target="_blank">Source Code</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="cta-section section">
    <div class="container">
        <div class="cta-content">
            <h2>Have a project in mind?</h2>
            <p>Let's work together to bring your ideas to life. I'm available for freelance projects.</p>
            <a href="contact.php" class="btn btn-primary">Get In Touch</a>
        </div>
    </div>
</section>

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