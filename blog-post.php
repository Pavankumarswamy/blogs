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
error_log('Starting blog-post.php');

// Start session if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    error_log('Session started, ID: ' . session_id());
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
    header('Location: ' . SITE_URL . '/blog');
    exit;
}

// Ensure tags column exists in blogs table
try {
    $pdo->exec("ALTER TABLE blogs ADD COLUMN tags TEXT NULL AFTER author");
    error_log('Added tags column to blogs table');
} catch (PDOException $e) {
    if ($e->getCode() != '42S21') { // Ignore duplicate column error
        error_log('Failed to add tags column: ' . $e->getMessage());
    }
}

// Ensure slug column exists
try {
    $pdo->exec("ALTER TABLE blogs ADD COLUMN slug VARCHAR(255) NULL AFTER title");
    error_log('Added slug column to blogs table');
} catch (PDOException $e) {
    if ($e->getCode() != '42S21') { // Ignore duplicate column error
        error_log('Failed to add slug column: ' . $e->getMessage());
    }
}

// Function to generate slug
function generateSlug($title) {
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return $slug;
}

// Function to fetch a single blog post by ID or slug
function getBlogPost($pdo, $identifier, $bySlug = false) {
    try {
        $identifier = $bySlug ? $identifier : preg_replace('/[^a-zA-Z0-9_-]/', '', $identifier);
        if (empty($identifier)) {
            error_log('Invalid identifier');
            return null;
        }

        $column = $bySlug ? 'slug' : 'id';
        $stmt = $pdo->prepare("SELECT * FROM blogs WHERE $column = ?");
        $stmt->execute([$identifier]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$post) {
            error_log("Blog post not found for $column: $identifier");
            return null;
        }

        // Generate slug if missing
        if (empty($post['slug'])) {
            $post['slug'] = generateSlug($post['title']);
            $stmt = $pdo->prepare("UPDATE blogs SET slug = ? WHERE id = ?");
            $stmt->execute([$post['slug'], $post['id']]);
            error_log("Generated slug for post ID {$post['id']}: {$post['slug']}");
        }

        // Decode tags
        $post['tags'] = !empty($post['tags']) ? json_decode($post['tags'], true) ?: DEFAULT_TAGS : DEFAULT_TAGS;
        return $post;
    } catch (PDOException $e) {
        error_log("Blog post error for $column: $identifier: " . $e->getMessage());
        return null;
    }
}

// Function to fetch related blog posts (previous or next)
function getRelatedPosts($pdo, $currentPostId, $limit = 3) {
    try {
        // Get current post's published_date
        $currentPost = getBlogPost($pdo, $currentPostId);
        if (!$currentPost) {
            error_log("Current post not found for ID $currentPostId");
            return [];
        }
        $currentDate = strtotime($currentPost['published_date'] ?? 'now');

        // Fetch all blog posts except the current one
        $stmt = $pdo->prepare("SELECT * FROM blogs WHERE id != ? AND published_date IS NOT NULL ORDER BY published_date DESC");
        $stmt->execute([$currentPostId]);
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($posts)) {
            error_log("No related posts found");
            return [];
        }

        // Split posts into before and after the current post
        $before = [];
        $after = [];
        foreach ($posts as $post) {
            $postDate = strtotime($post['published_date'] ?? 'now');
            $post['tags'] = !empty($post['tags']) ? json_decode($post['tags'], true) ?: DEFAULT_TAGS : DEFAULT_TAGS;
            $post['image_url'] = $post['image_url'] ?? '';
            if ($postDate < $currentDate) {
                $before[] = $post;
            } else {
                $after[] = $post;
            }
        }

        // Get up to $limit posts, preferring newer posts, then older if needed
        $selected = array_merge(array_slice($after, 0, $limit), array_slice($before, 0, $limit - count($after)));
        return array_slice($selected, 0, $limit);
    } catch (PDOException $e) {
        error_log("Related posts error: " . $e->getMessage());
        return [];
    }
}

// Get identifier from URL (slug or ID)
$slug = isset($_GET['slug']) ? $_GET['slug'] : null;
$postId = isset($_GET['id']) ? $_GET['id'] : null;

// If no identifier is provided, redirect to blog page
if (!$slug && !$postId) {
    error_log('No post identifier provided');
    ob_clean();
    header('Location: ' . SITE_URL . '/blog');
    exit;
}

// Get the blog post
$post = $slug ? getBlogPost($pdo, $slug, true) : getBlogPost($pdo, $postId);

// If post doesn't exist, redirect to blog page
if (!$post) {
    error_log('Post not found for identifier: ' . ($slug ?: $postId));
    ob_clean();
    header('Location: ' . SITE_URL . '/blog');
    exit;
}

// Redirect old ID-based URL to slug-based URL
if ($postId && $post['slug']) {
    ob_clean();
    header('Location: ' . SITE_URL . '/blog/' . $post['slug'], true, 301);
    exit;
}

// SEO: Prepare meta data
$meta_title = htmlspecialchars($post['title']) . ' | ' . SITE_NAME;
$meta_description = htmlspecialchars($post['excerpt']);
$canonical_url = SITE_URL . '/blog/' . $post['slug'];
$og_image = !empty($post['image_url']) ? htmlspecialchars($post['image_url']) : SITE_URL . '/default-image.jpg';
$meta_keywords = implode(', ', $post['tags']);

// SEO: Schema.org structured data (BlogPosting and BreadcrumbList)
$schema_blog = [
    '@context' => 'https://schema.org',
    '@type' => 'BlogPosting',
    'headline' => htmlspecialchars($post['title']),
    'description' => $meta_description,
    'datePublished' => date('c', strtotime($post['published_date'] ?? 'now')),
    'dateModified' => date('c', strtotime($post['published_date'] ?? 'now')),
    'author' => [
        '@type' => 'Person',
        'name' => htmlspecialchars($post['author'] ?? 'Unknown Author')
    ],
    'publisher' => [
        '@type' => 'Organization',
        'name' => SITE_NAME,
        'logo' => [
            '@type' => 'ImageObject',
            'url' => SITE_LOGO
        ]
    ],
    'image' => $og_image,
    'mainEntityOfPage' => [
        '@type' => 'WebPage',
        '@id' => $canonical_url
    ],
    'keywords' => $meta_keywords
];

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
        ],
        [
            '@type' => 'ListItem',
            'position' => 3,
            'name' => htmlspecialchars($post['title']),
            'item' => $canonical_url
        ]
    ]
];

include 'includes/header.php';
?>

<!-- SEO: Meta Tags -->
<head>
    <title><?php echo $meta_title; ?></title>
    <meta name="description" content="<?php echo $meta_description; ?>">
    <meta name="keywords" content="<?php echo $meta_keywords; ?>">
    <meta name="author" content="<?php echo htmlspecialchars($post['author'] ?? 'Unknown Author'); ?>">
    <meta name="robots" content="index, follow">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="canonical" href="<?php echo $canonical_url; ?>">
    
    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo $meta_title; ?>">
    <meta property="og:description" content="<?php echo $meta_description; ?>">
    <meta property="og:image" content="<?php echo $og_image; ?>">
    <meta property="og:url" content="<?php echo $canonical_url; ?>">
    <meta property="og:type" content="article">
    <meta property="og:site_name" content="<?php echo SITE_NAME; ?>">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo $meta_title; ?>">
    <meta name="twitter:description" content="<?php echo $meta_description; ?>">
    <meta name="twitter:image" content="<?php echo $og_image; ?>">
    
    <!-- Schema.org Structured Data -->
    <script type="application/ld+json">
        <?php echo json_encode($schema_blog, JSON_UNESCAPED_SLASHES); ?>
    </script>
    <script type="application/ld+json">
        <?php echo json_encode($schema_breadcrumbs, JSON_UNESCAPED_SLASHES); ?>
    </script>

    <!-- Corrected CSS Links -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/style.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/responsive.css">
</head>

<!-- Breadcrumbs -->
<section class="breadcrumbs">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL . '/blog'; ?>">Blog</a></li>
               <!-- <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($post['title']); ?></li>-->
            </ol>
        </nav>
    </div>
</section>

<!-- Blog Post Hero Section -->
<section class="blog-post-hero">
    <div class="container">
        <div class="blog-post-header">
            <h1 class="blog-post-title"><?php echo htmlspecialchars($post['title']); ?></h1>
            <div class="blog-post-meta">
                <span class="post-date">
                    <i class="far fa-calendar-alt"></i>
                    <?php echo date('F d, Y', strtotime($post['published_date'] ?? 'now')); ?>
                </span>
                <?php if (!empty($post['category'])): ?>
                    <span class="post-category">
                        <i class="far fa-folder"></i>
                        <a href="<?php echo SITE_URL . '/blog/' . urlencode($post['category']); ?>">
                            <?php echo htmlspecialchars($post['category']); ?>
                        </a>
                    </span>
                <?php endif; ?>
                <?php if (!empty($post['author'])): ?>
                    <span class="post-author">
                        <i class="far fa-user"></i>
                        <?php echo htmlspecialchars($post['author']); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Blog Post Content Section -->
<section class="blog-post-content section">
    <div class="container">
        <div class="blog-post-container">
            <div class="blog-featured-image">
                <?php if (!empty($post['image_url'])): ?>
                    <img src="<?php echo htmlspecialchars($post['image_url']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" loading="lazy">
                <?php else: ?>
                    <svg class="blog-post-svg" viewBox="0 0 800 400" xmlns="http://www.w3.org/2000/svg">
                        <rect width="800" height="400" fill="#f0f0f0"/>
                        <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="Arial" font-size="24" fill="#555">Featured Image</text>
                    </svg>
                <?php endif; ?>
            </div>
            
            <article class="blog-post-body">
                <?php echo $post['content']; // Render HTML content as-is ?>
            </article>
            
            <?php if (!empty($post['tags']) && is_array($post['tags'])): ?>
                <div class="blog-post-tags">
                    <h4>Tags:</h4>
                    <div class="tags-container">
                        <?php foreach ($post['tags'] as $tag): ?>
                            <a href="<?php echo SITE_URL . '/blog?tag=' . urlencode($tag); ?>" class="tag"><?php echo htmlspecialchars($tag); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="blog-post-share">
                <h4>Share This Post:</h4>
                <div class="share-buttons">
                    <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode($canonical_url); ?>&text=<?php echo urlencode($post['title']); ?>" target="_blank" class="share-button twitter" aria-label="Share on Twitter">
                        <i class="fab fa-twitter"></i> Twitter
                    </a>
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($canonical_url); ?>" target="_blank" class="share-button facebook" aria-label="Share on Facebook">
                        <i class="fab fa-facebook"></i> Facebook
                    </a>
                    <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo urlencode($canonical_url); ?>&title=<?php echo urlencode($post['title']); ?>" target="_blank" class="share-button linkedin" aria-label="Share on LinkedIn">
                        <i class="fab fa-linkedin"></i> LinkedIn
                    </a>
                </div>
            </div>
        </div>
        
        <div class="blog-post-navigation">
            <a href="<?php echo SITE_URL . '/blog'; ?>" class="btn btn-secondary" aria-label="Back to Blog">
                <i class="fas fa-arrow-left"></i> Back to Blog
            </a>
        </div>
    </div>
</section>

<!-- Related Posts Section -->
<section class="related-posts section">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">Related Posts</h2>
            <p class="section-subtitle">You might also like these</p>
        </div>
        
        <?php
        $relatedPosts = getRelatedPosts($pdo, $post['id'], 3);
        ?>
        
        <div class="related-posts-container">
            <?php if (empty($relatedPosts)): ?>
                <p>No related posts found.</p>
            <?php else: ?>
                <?php foreach ($relatedPosts as $relatedPost): ?>
                    <div class="related-post-card">
                        <div class="related-post-image">
                            <?php if (!empty($relatedPost['image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($relatedPost['image_url']); ?>" alt="<?php echo htmlspecialchars($relatedPost['title']); ?>" loading="lazy">
                            <?php else: ?>
                                <svg class="blog-post-svg" viewBox="0 0 400 200" xmlns="http://www.w3.org/2000/svg">
                                    <rect width="400" height="200" fill="#f0f0f0"/>
                                    <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="Arial" font-size="16" fill="#555">Featured Image</text>
                                </svg>
                            <?php endif; ?>
                        </div>
                        <div class="related-post-content">
                            <h3 class="related-post-title">
                                <a href="<?php echo SITE_URL . '/blog/' . htmlspecialchars($relatedPost['slug']); ?>">
                                    <?php echo htmlspecialchars($relatedPost['title']); ?>
                                </a>
                            </h3>
                            <p class="related-post-excerpt">
                                <?php echo htmlspecialchars($relatedPost['excerpt']); ?>
                            </p>
                            <span class="related-post-date">
                                <i class="far fa-calendar-alt"></i>
                                <?php echo date('F d, Y', strtotime($relatedPost['published_date'] ?? 'now')); ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Inline Styles (unchanged) -->
<style>
.related-posts-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
    margin-top: 2rem;
}

.related-post-card {
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease;
}

.related-post-card:hover {
    transform: translateY(-5px);
}

.related-post-image img,
.related-post-image svg {
    width: 100%;
    height: 200px;
    object-fit: cover;
}

.related-post-content {
    padding: 1.5rem;
}

.related-post-title {
    font-size: 1.25rem;
    margin-bottom: 0.5rem;
}

.related-post-title a {
    color: #333;
    text-decoration: none;
}

.related-post-title a:hover {
    color: #007bff;
}

.related-post-excerpt {
    color: #666;
    margin-bottom: 1rem;
    font-size: 0.9rem;
}

.related-post-date {
    font-size: 0.85rem;
    color: #888;
}

.blog-post-tags {
    margin-top: 1.5rem;
}

.tags-container {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.tag {
    background: #f0f0f0;
    padding: 0.3rem 0.6rem;
    border-radius: 3px;
    font-size: 0.9rem;
    color: #333;
}

.tag:hover {
    background: #e0e0e0;
    text-decoration: none;
}
</style>

<!-- Corrected JavaScript Links -->
<script src="<?php echo SITE_URL; ?>/js/firebase-config.js"></script>
<script src="<?php echo SITE_URL; ?>/js/main.js"></script>

<?php 
include 'includes/footer.php';
ob_end_flush();
?>