<?php
// Include configuration
require_once 'config.php';

// MySQL Database Connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log('Sitemap MySQL connection failed: ' . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    exit;
}

// Fetch all blog posts
try {
    $stmt = $pdo->query("SELECT slug, published_date FROM blogs WHERE slug IS NOT NULL");
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Sitemap query failed: ' . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    exit;
}

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc><?php echo SITE_URL; ?></loc>
        <lastmod><?php echo date('c'); ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc><?php echo SITE_URL; ?>/blog.php</loc>
        <lastmod><?php echo date('c'); ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.8</priority>
    </url>
    <?php foreach ($posts as $post): ?>
        <url>
            <loc><?php echo SITE_URL . '/blog/' . htmlspecialchars($post['slug']); ?></loc>
            <lastmod><?php echo date('c', strtotime($post['published_date'] ?? 'now')); ?></lastmod>
            <changefreq>weekly</changefreq>
            <priority>0.6</priority>
        </url>
    <?php endforeach; ?>
</urlset>