<?php
// Include configuration
require_once 'config.php';

// Start output buffering
ob_start();

// Disable error display, keep logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Log start
error_log('Starting blog.php');

// Start session if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    error_log('Session started, ID: ' . session_id());
}

// Redirect old blog.php?category=XXX URLs to /blog/xxx
if (isset($_GET['category']) && !empty($_GET['category']) && strpos($_SERVER['REQUEST_URI'], 'blog.php') !== false) {
    $category = $_GET['category'];
    error_log('Redirecting old category URL: blog.php?category=' . $category);
    header('Location: ' . SITE_URL . '/blog/' . urlencode($category), true, 301);
    ob_end_flush();
    exit;
}

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
    die('Database connection error');
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

// Function to generate slug
function generateSlug($title) {
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return $slug;
}

// Function to fetch blog posts from MySQL
function getBlogPostsFromMySQL($pdo, $category = null, $tag = null) {
    try {
        $sql = "SELECT * FROM blogs WHERE 1=1";
        $params = [];

        if ($category) {
            $sql .= " AND LOWER(category) = LOWER(?)";
            $params[] = $category;
        }

        if ($tag) {
            $sql .= " AND LOWER(tags) LIKE LOWER(?)";
            $params[] = "%\"$tag\"%";
        }

        $sql .= " ORDER BY published_date DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        error_log('Fetched ' . count($posts) . ' posts for category: ' . ($category ?: 'none') . ', tag: ' . ($tag ?: 'none'));

        // Process posts (add slugs and tags if missing)
        foreach ($posts as &$post) {
            if (empty($post['slug'])) {
                $post['slug'] = generateSlug($post['title']);
                $stmt = $pdo->prepare("UPDATE blogs SET slug = ? WHERE id = ?");
                $stmt->execute([$post['slug'], $post['id']]);
                error_log("Generated slug for post ID {$post['id']}: {$post['slug']}");
            }
            $post['tags'] = !empty($post['tags']) ? json_decode($post['tags'], true) ?: DEFAULT_TAGS : DEFAULT_TAGS;
        }

        return $posts;
    } catch (PDOException $e) {
        error_log('Error fetching blog posts from MySQL: ' . $e->getMessage());
        return [];
    }
}

// Get filter parameters
$category = isset($_GET['category']) ? $_GET['category'] : null;
$tag = isset($_GET['tag']) ? $_GET['tag'] : null;

error_log('Category parameter: ' . ($category ?: 'none') . ', Tag parameter: ' . ($tag ?: 'none'));

// Get blog posts from MySQL
$posts = getBlogPostsFromMySQL($pdo, $category, $tag);

// SEO: Prepare meta data
$meta_title = ($category ? htmlspecialchars($category) . ' | ' : ($tag ? htmlspecialchars($tag) . ' | ' : '')) . 'Blog | ' . SITE_NAME;
$meta_description = 'Explore our latest blog posts on programming, app development, and more.' . ($category ? ' Filtered by ' . htmlspecialchars($category) . '.' : ($tag ? ' Tagged with ' . htmlspecialchars($tag) . '.' : ''));
$meta_keywords = implode(', ', DEFAULT_TAGS);
$canonical_url = SITE_URL . '/blog' . ($category ? '/' . urlencode($category) : ($tag ? '?tag=' . urlencode($tag) : ''));

// SEO: Schema.org structured data (Blog)
$schema = [
    '@context' => 'https://schema.org',
    '@type' => 'Blog',
    'name' => 'My Blog',
    'url' => $canonical_url,
    'description' => $meta_description,
    'publisher' => [
        '@type' => 'Organization',
        'name' => SITE_NAME,
        'logo' => [
            '@type' => 'ImageObject',
            'url' => SITE_LOGO
        ]
    ],
    'blogPost' => array_map(function($post) use ($pdo) {
        return [
            '@type' => 'BlogPosting',
            'headline' => htmlspecialchars($post['title']),
            'url' => SITE_URL . '/blog/' . htmlspecialchars($post['slug']),
            'datePublished' => date('c', strtotime($post['published_date'] ?? 'now')),
            'description' => htmlspecialchars($post['excerpt']),
            'image' => !empty($post['image_url']) ? htmlspecialchars($post['image_url']) : SITE_URL . '/default-image.jpg',
            'author' => [
                '@type' => 'Person',
                'name' => htmlspecialchars($post['author'] ?? 'Unknown Author')
            ],
            'keywords' => implode(', ', $post['tags'])
        ];
    }, $posts)
];

// SEO: BreadcrumbList
$schema_breadcrumbs = [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        [
            '@type' => 'ListItem',
            'position' => 1,
            'name' => 'Home',
            'item' => SITE_URL
        ],
        [
            '@type' => 'ListItem',
            'position' => 2,
            'name' => 'Blog',
            'item' => SITE_URL . '/blog'
        ]
    ]
];
if ($category || $tag) {
    $schema_breadcrumbs['itemListElement'][] = [
        '@type' => 'ListItem',
        'position' => 3,
        'name' => $category ? htmlspecialchars($category) : htmlspecialchars($tag),
        'item' => $canonical_url
    ];
}

include 'includes/header.php';
?>

<!-- SEO: Meta Tags -->
<head>
    <title><?php echo $meta_title; ?></title>
    <meta name="description" content="<?php echo $meta_description; ?>">
    <meta name="keywords" content="<?php echo $meta_keywords; ?>">
    <meta name="robots" content="index, follow">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="canonical" href="<?php echo $canonical_url; ?>">
    
    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo $meta_title; ?>">
    <meta property="og:description" content="<?php echo $meta_description; ?>">
    <meta property="og:image" content="<?php echo SITE_URL; ?>/default-image.jpg">
    <meta property="og:url" content="<?php echo $canonical_url; ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?php echo SITE_NAME; ?>">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo $meta_title; ?>">
    <meta name="twitter:description" content="<?php echo $meta_description; ?>">
    <meta name="twitter:image" content="<?php echo SITE_URL; ?>/default-image.jpg">
    
    <!-- Schema.org Structured Data -->
    <script type="application/ld+json">
        <?php echo json_encode($schema, JSON_UNESCAPED_SLASHES); ?>
    </script>
    <script type="application/ld+json">
        <?php echo json_encode($schema_breadcrumbs, JSON_UNESCAPED_SLASHES); ?>
    </script>
</head>

<!-- Breadcrumbs -->
<section class="breadcrumbs">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>">Home</a></li>
                <li class="breadcrumb-item<?php echo (!$category && !$tag) ? ' active' : ''; ?>" aria-current="<?php echo (!$category && !$tag) ? 'page' : ''; ?>">
                    <?php if (!$category && !$tag): ?>
                        Blog
                    <?php else: ?>
                        <a href="<?php echo SITE_URL . '/blog'; ?>">Blog</a>
                    <?php endif; ?>
                </li>
                <?php if ($category || $tag): ?>
                    <li class="breadcrumb-item active" aria-current="page">
                        <?php echo $category ? htmlspecialchars($category) : htmlspecialchars($tag); ?>
                    </li>
                <?php endif; ?>
            </ol>
        </nav>
    </div>
</section>

<!-- Blog Hero Section -->
<section class="blog-hero">
    <div class="container">
        <div class="section-header">
            <h1 class="section-title"><?php echo $category ? htmlspecialchars($category) : ($tag ? htmlspecialchars($tag) : 'My Blog'); ?></h1>
            <p class="section-subtitle"><?php echo $category ? 'Posts in ' . htmlspecialchars($category) : ($tag ? 'Posts tagged with ' . htmlspecialchars($tag) : 'Thoughts, tutorials, and insights'); ?></p>
        </div>
    </div>
</section>

<!-- Blog Posts Section -->
<section class="blog-posts-section section">
    <div class="container">
        <div class="blog-grid">
            <?php if (empty($posts)): ?>
                <div class="empty-state">
                    <p>No blog posts found. Check back soon for new content!</p>
                </div>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <div class="blog-card">
                        <div class="blog-img">
                            <?php if (!empty($post['image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($post['image_url']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" loading="lazy">
                            <?php else: ?>
                                <svg class="blog-svg" viewBox="0 0 400 225" xmlns="http://www.w3.org/2000/svg">
                                    <rect width="400" height="225" fill="#f0f0f0"/>
                                    <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="Arial" font-size="18" fill="#555">Blog Image</text>
                                </svg>
                            <?php endif; ?>
                        </div>
                        <div class="blog-content">
                            <div class="blog-meta">
                                <span class="blog-date"><?php echo date('M d, Y', strtotime($post['published_date'] ?? 'now')); ?></span>
                                <?php if (!empty($post['category'])): ?>
                                    <span class="blog-category">
                                        <a href="<?php echo SITE_URL . '/blog/' . urlencode($post['category']); ?>">
                                            <?php echo htmlspecialchars($post['category']); ?>
                                        </a>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($post['author'])): ?>
                                    <span class="blog-author"><?php echo htmlspecialchars($post['author']); ?></span>
                                <?php endif; ?>
                            </div>
                            <h3 class="blog-title">
                                <a href="<?php echo SITE_URL . '/blog/' . htmlspecialchars($post['slug']); ?>">
                                    <?php echo htmlspecialchars($post['title']); ?>
                                </a>
                            </h3>
                            <p class="blog-excerpt">
                                <?php echo htmlspecialchars($post['excerpt']); ?>
                            </p>
                            <div class="blog-tags">
                                <?php foreach ($post['tags'] as $tag): ?>
                                    <a href="<?php echo SITE_URL . '/blog?tag=' . urlencode($tag); ?>" class="tag"><?php echo htmlspecialchars($tag); ?></a>
                                <?php endforeach; ?>
                            </div>
                            <a href="<?php echo SITE_URL . '/blog/' . htmlspecialchars($post['slug']); ?>" class="read-more">Read More</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<style>
.breadcrumbs{padding:1rem 0;background:#f8f9fa}.breadcrumb{margin:0;padding:0;list-style:none;display:flex}.breadcrumb-item+.breadcrumb-item::before{content:"›";margin:0 .5rem;color:#666}.breadcrumb-item a{color:#007bff;text-decoration:none}.breadcrumb-item a:hover{text-decoration:underline}.breadcrumb-item.active{color:#333}.blog-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:2rem}.blog-card{background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1);transition:transform .3s ease}.blog-card:hover{transform:translateY(-5px)}.blog-img img,.blog-img svg{width:100%;height:225px;object-fit:cover}.blog-content{padding:1.5rem}.blog-meta{font-size:.85rem;color:#888;margin-bottom:.5rem}.blog-meta span{margin-right:1rem}.blog-category a{color:#007bff;text-decoration:none}.blog-category a:hover{text-decoration:underline}.blog-title{font-size:1.25rem;margin-bottom:.5rem}.blog-title a{color:#333;text-decoration:none}.blog-title a:hover{color:#007bff}.blog-excerpt{color:#666;margin-bottom:1rem;font-size:.9rem}.blog-tags{display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:1rem}.tag{background:#f0f0f0;padding:.3rem .6rem;border-radius:3px;font-size:.85rem;color:#333;text-decoration:none}.tag:hover{background:#e0e0e0}.read-more{color:#007bff;text-decoration:none;font-weight:bold}.read-more:hover{text-decoration:underline}.empty-state{text-align:center;padding:2rem;color:#666}
</style>

<?php 
include 'includes/footer.php';
ob_end_flush();
?>