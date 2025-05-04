<?php
// load-more.php
header('Content-Type: application/json');

// Add CORS headers (for safety, in case absolute URLs are used)
header('Access-Control-Allow-Origin: https://www.ggusoc.in');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Include configuration
require_once 'config.php';

// Disable error display, keep logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Log start
error_log('Starting load-more.php');

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log('Invalid request method: ' . $_SERVER['REQUEST_METHOD']);
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed: Use POST']);
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
    error_log('Connected to MySQL database in load-more.php');
} catch (PDOException $e) {
    error_log('MySQL connection failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database connection error: ' . $e->getMessage()]);
    exit;
}

// Get parameters
$offset = isset($_POST['offset']) ? (int)$_POST['offset'] : 0;
$limit = 6; // Number of posts to load
$category = isset($_POST['category']) && $_POST['category'] !== '' ? $_POST['category'] : null;
$tag = isset($_POST['tag']) && $_POST['tag'] !== '' ? $_POST['tag'] : null;

error_log("Load more request: offset=$offset, limit=$limit, category=" . ($category ?: 'none') . ", tag=" . ($tag ?: 'none'));

// Fetch blog posts
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

    $sql .= " ORDER BY published_date DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log('Fetched ' . count($posts) . ' posts for offset: ' . $offset);

    // Generate HTML for posts
    $html = '';
    foreach ($posts as $post) {
        $tags = !empty($post['tags']) ? json_decode($post['tags'], true) : [];
        if (!is_array($tags)) {
            $tags = [];
            error_log('Invalid tags format for post ID: ' . $post['id']);
        }
        $html .= '<div class="blog-card">';
        $html .= '<div class="blog-img">';
        if (!empty($post['image_url'])) {
            $html .= '<img src="' . htmlspecialchars($post['image_url']) . '" alt="' . htmlspecialchars($post['title']) . '" loading="lazy">';
        } else {
            $html .= '<svg class="blog-svg" viewBox="0 0 400 225" xmlns="http://www.w3.org/2000/svg">';
            $html .= '<rect width="400" height="225" fill="#f0f0f0"/>';
            $html .= '<text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="Arial" font-size="18" fill="#555">Blog Image</text>';
            $html .= '</svg>';
        }
        $html .= '</div>';
        $html .= '<div class="blog-content">';
        $html .= '<div class="blog-meta">';
        $html .= '<span class="blog-date">' . date('M d, Y', strtotime($post['published_date'] ?? 'now')) . '</span>';
        if (!empty($post['category'])) {
            $html .= '<span class="blog-category"><a href="' . SITE_URL . '/blog/' . urlencode($post['category']) . '">' . htmlspecialchars($post['category']) . '</a></span>';
        }
        if (!empty($post['author'])) {
            $html .= '<span class="blog-author">' . htmlspecialchars($post['author']) . '</span>';
        }
        $html .= '</div>';
        $html .= '<h3 class="blog-title"><a href="' . SITE_URL . '/blog/' . htmlspecialchars($post['slug']) . '">' . htmlspecialchars($post['title']) . '</a></h3>';
        $html .= '<p class="blog-excerpt">' . htmlspecialchars($post['excerpt']) . '</p>';
        $html .= '<div class="blog-tags">';
        foreach ($tags as $tag) {
            $html .= '<a href="' . SITE_URL . '/blog?tag=' . urlencode($tag) . '" class="tag">' . htmlspecialchars($tag) . '</a>';
        }
        $html .= '</div>';
        $html .= '<a href="' . SITE_URL . '/blog/' . htmlspecialchars($post['slug']) . '" class="read-more">Read More</a>';
        $html .= '</div>';
        $html .= '</div>';
    }

    // Check if more posts are available
    $sql_count = "SELECT COUNT(*) FROM blogs WHERE 1=1";
    $count_params = [];
    if ($category) {
        $sql_count .= " AND LOWER(category) = LOWER(?)";
        $count_params[] = $category;
    }
    if ($tag) {
        $sql_count .= " AND LOWER(tags) LIKE LOWER(?)";
        $count_params[] = "%\"$tag\"%";
    }
    $stmt = $pdo->prepare($sql_count);
    $stmt->execute($count_params);
    $total_posts = $stmt->fetchColumn();

    error_log('Total posts available: ' . $total_posts);

    $has_more = ($offset + $limit) < $total_posts;

    echo json_encode([
        'html' => $html,
        'has_more' => $has_more,
        'offset' => $offset,
        'total_posts' => $total_posts
    ], JSON_UNESCAPED_SLASHES);
} catch (PDOException $e) {
    error_log('Error fetching posts in load-more.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error fetching posts: ' . $e->getMessage()]);
    exit;
} catch (Exception $e) {
    error_log('General error in load-more.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
    exit;
}
?>