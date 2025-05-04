<?php
// Start output buffering
ob_start();

// Disable error display, keep logging
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Log start
error_log('Starting index.php');

// Start session if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    error_log('Session started in index.php, ID: ' . session_id());
}

// Include configuration
$configFile = 'config.php';
if (!file_exists($configFile)) {
    error_log('Error: config.php not found at ' . realpath($configFile));
    ob_clean();
    die('Configuration file not found.');
}
require_once $configFile;

// Default Tags for All Posts
define('DEFAULT_TAGS', [
    'html', 'flutter', 'dart', 'cross-platform', 'tutorial',
    'programming', 'app development', 'code', 'youtube', 'web development'
]);

// Site Configuration
define('SITE_URL', 'https://ggusoc.in');
define('SITE_NAME', 'spks blogs');
define('SITE_LOGO', SITE_URL . '/logo.png');

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
    die('Database connection error. Please try again later.');
}

// Create or update blogs table
try {
    $sql = "
    CREATE TABLE IF NOT EXISTS blogs (
        id VARCHAR(255) PRIMARY KEY,
        title TEXT NOT NULL,
        slug VARCHAR(255) NULL,
        content TEXT,
        excerpt TEXT,
        image_url TEXT,
        category VARCHAR(100),
        author VARCHAR(100),
        tags TEXT,
        published_date DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
    error_log('Blogs table checked/created');
} catch (PDOException $e) {
    error_log('Table creation failed: ' . $e->getMessage());
    ob_clean();
    die('Database table error');
}

// Ensure tags and slug columns exist
try {
    $pdo->exec("ALTER TABLE blogs ADD COLUMN tags TEXT NULL AFTER author");
    $pdo->exec("ALTER TABLE blogs ADD COLUMN slug VARCHAR(255) NULL AFTER title");
    error_log('Added tags and slug columns to blogs table');
} catch (PDOException $e) {
    if ($e->getCode() != '42S21') { // Ignore duplicate column error
        error_log('Failed to add columns: ' . $e->getMessage());
    }
}

// Create or update projects table
try {
    $sql = "
    CREATE TABLE IF NOT EXISTS projects (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        title TEXT NOT NULL,
        description TEXT NOT NULL,
        technologies TEXT,
        project_url TEXT,
        github_url TEXT,
        image_url TEXT,
        thumbnail_url TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
    error_log('Projects table checked/created');
} catch (PDOException $e) {
    error_log('Projects table creation failed: ' . $e->getMessage());
    ob_clean();
    die('Database table error');
}

// Function to generate slug
function generateSlug($title) {
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return $slug;
}

// Function to fetch recent blog posts from MySQL
function getBlogPosts($pdo, $limit = 3) {
    try {
        $sql = "SELECT id, title, slug, excerpt, image_url, category, author, tags, published_date 
                FROM blogs 
                ORDER BY published_date DESC 
                LIMIT :limit";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        error_log('Fetched ' . count($posts) . ' blog posts');
        foreach ($posts as $post) {
            error_log('Post ID: ' . $post['id'] . ', Title: ' . $post['title'] . ', Published: ' . $post['published_date']);
        }

        // Process posts (add slugs and tags if missing)
        foreach ($posts as &$post) {
            if (empty($post['slug'])) {
                $post['slug'] = generateSlug($post['title']);
                try {
                    $updateStmt = $pdo->prepare("UPDATE blogs SET slug = ? WHERE id = ?");
                    $updateStmt->execute([$post['slug'], $post['id']]);
                    error_log("Generated slug for post ID {$post['id']}: {$post['slug']}");
                } catch (PDOException $e) {
                    error_log('Failed to update slug for post ID ' . $post['id'] . ': ' . $e->getMessage());
                }
            }
            $post['tags'] = !empty($post['tags']) && json_decode($post['tags'], true) !== null 
                ? json_decode($post['tags'], true) 
                : DEFAULT_TAGS;
            $post['image_url'] = $post['image_url'] ?? '';
        }

        return $posts;
    } catch (PDOException $e) {
        error_log('Error fetching blog posts from MySQL: ' . $e->getMessage());
        return [];
    }
}

// Function to fetch recent projects from MySQL
function getProjects($pdo, $limit = 3) {
    try {
        $sql = "
            SELECT DISTINCT id, title, description, technologies, project_url, github_url, image_url, thumbnail_url, created_at
            FROM projects
            ORDER BY created_at DESC
            LIMIT :limit";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Decode technologies and filter duplicates
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

        error_log('Fetched ' . count($projects) . ' unique projects from MySQL: ' . print_r(array_map(function($p) { return $p['id'] . ':' . $p['title']; }, $projects), true));

        return $projects;
    } catch (PDOException $e) {
        error_log('Error fetching projects from MySQL: ' . $e->getMessage());
        return [];
    }
}

// Get the 3 most recent blog posts
$recentPosts = getBlogPosts($pdo, 3);

// Get the 3 most recent projects
$featuredProjects = getProjects($pdo, 3);

// Include public header
$headerFile = 'includes/header.php';
if (!file_exists($headerFile)) {
    error_log('Error: header.php not found at ' . realpath($headerFile));
    ob_clean();
    die('Header file not found.');
}
include $headerFile;
?>

<!-- Hero Section -->
<section class="hero">
    <div class="container">
        <div class="hero-content">
            <h1 class="hero-title">Hello, I'm <span class="highlight">Pavankumarswamy</span></h1>
            <h2 class="hero-subtitle">App & Web Developer</h2>
            <p class="hero-description">I build engaging web and mobile experiences with a focus on innovative solutions.</p>
            <div class="hero-buttons">
                <a href="projects.php" class="btn btn-primary">View My Work</a>
                <a href="contact.php" class="btn btn-secondary">Contact Me</a>
            </div>
        </div>
    </div>
</section>

<!-- Services Section -->
<section class="services section">
    <div class="container">
        <div class="section-header">
            <div class="section-title">What I Do</div>
            <p class="section-subtitle">Services I offer to my clients</p>
        </div>
        
        <div class="services-grid">
            <div class="service-card">
                <div class="service-icon">
                    <i class="fas fa-code"></i>
                </div>
                <div class="service-title">Flutter App Development</div>
                <p class="service-description">Creating cross-platform mobile applications with beautiful UI and seamless functionality.</p>
            </div>
            
            <div class="service-card">
                <div class="service-icon">
                    <i class="fas fa-paint-brush"></i>
                </div>
                <div class="service-title">Firebase Integration</div>
                <p class="service-description">Building scalable applications with real-time databases, authentication, and cloud functions.</p>
            </div>
            
            <div class="service-card">
                <div class="service-icon">
                    <i class="fas fa-mobile-alt"></i>
                </div>
                <div class="service-title">Web Development</div>
                <p class="service-description">Developing responsive websites with modern frameworks and clean, efficient code.</p>
            </div>
            
            <div class="service-card">
                <div class="service-icon">
                    <i class="fas fa-search"></i>
                </div>
                <div class="service-title">UI/UX Design</div>
                <p class="service-description">Creating intuitive and engaging user interfaces with focus on user experience.</p>
            </div>
        </div>
    </div>
</section>

<!-- Featured Projects Section -->
<section class="featured-projects section">
    <div class="container">
        <div class="section-header">
            <div class="section-title">Featured Projects</div>
            <p class="section-subtitle">Some of my recent work</p>
        </div>
        
        <div class="projects-grid">
            <?php if (empty($featuredProjects)): ?>
                <div class="empty-state">
                    <p>No projects found. Check back soon for new content!</p>
                </div>
            <?php else: ?>
                <?php foreach ($featuredProjects as $project): ?>
                    <?php error_log('Rendering project ID: ' . $project['id'] . ', Title: ' . $project['title']); ?>
                    <div class="project-card" id="project<?php echo htmlspecialchars($project['id']); ?>">
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
                            <div class="project-title"><?php echo htmlspecialchars($project['title']); ?></div>
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
        
        <div class="section-footer">
            <a href="projects.php" class="btn btn-primary">View All Projects</a>
        </div>
    </div>
</section>

<!-- Recent Blog Posts Section -->
<section class="recent-posts section">
    <div class="container">
        <div class="section-header">
            <div class="section-title">Recent Blog Posts</div>
            <p class="section-subtitle">Latest articles from my blog</p>
        </div>
        
        <div class="posts-grid">
            <?php if (empty($recentPosts)): ?>
                <div class="empty-state">
                    <p>No blog posts found. Check back soon for new content!</p>
                </div>
            <?php else: ?>
                <?php foreach ($recentPosts as $post): ?>
                    <div class="post-card">
                        <div class="post-img">
                            <?php if (!empty($post['image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($post['image_url']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>">
                            <?php else: ?>
                                <svg class="post-svg" viewBox="0 0 400 225" xmlns="http://www.w3.org/2000/svg">
                                    <rect width="400" height="225" fill="#f0f0f0"/>
                                    <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="Arial" font-size="18" fill="#555">Blog Image</text>
                                </svg>
                            <?php endif; ?>
                        </div>
                        <div class="post-content">
                            <div class="post-meta">
                                <span class="post-date"><?php echo date('M d, Y', strtotime($post['published_date'] ?? 'now')); ?></span>
                                <?php if (!empty($post['category'])): ?>
                                    <span class="post-category"><?php echo htmlspecialchars($post['category']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($post['author'])): ?>
                                    <span class="post-author"><?php echo htmlspecialchars($post['author']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="post-title"><?php echo htmlspecialchars($post['title']); ?></div>
                            <p class="post-excerpt">
                                <?php 
                                $excerpt = strip_tags($post['excerpt'] ?? '');
                                echo strlen($excerpt) > 150 ? substr($excerpt, 0, 150) . '...' : $excerpt; 
                                ?>
                            </p>
                            <a href="<?php echo SITE_URL . '/blog/' . htmlspecialchars($post['slug']); ?>" class="read-more">Read More</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="section-footer">
            <a href="blog.php" class="btn btn-primary">View All Posts</a>
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