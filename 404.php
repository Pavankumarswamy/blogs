<?php
// Start output buffering
ob_start();

// Disable error display, keep logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Log start
error_log('Starting 404.php');

// Start session if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    error_log('Session started, ID: ' . session_id());
}

include 'includes/header.php';
?>

<!-- 404 Error Section -->
<section class="error-404 section">
    <div class="container">
        <div class="error-content text-center">
            <h1 class="error-title">404 - Page Not Found</h1>
            <p class="error-description">Oops! It seems the page you're looking for has vanished or moved.</p>
            <div class="error-graphic">
                <svg id="error-svg" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="100" cy="100" r="80" fill="#f5f5f5" stroke="#333" stroke-width="4"/>
                    <text x="50%" y="40%" dominant-baseline="middle" text-anchor="middle" font-family="Arial" font-size="48" fill="#333">404</text>
                    <text x="50%" y="60%" dominant-baseline="middle" text-anchor="middle" font-family="Arial" font-size="16" fill="#555">Page Not Found</text>
                    <circle cx="70" cy="80" r="10" fill="#ff6b6b" class="pulse"/>
                    <circle cx="130" cy="80" r="10" fill="#ff6b6b" class="pulse"/>
                </svg>
            </div>
            <div class="error-search">
                <input type="text" id="search-input" placeholder="Search for something else..." class="search-input">
                <div id="search-suggestions" class="search-suggestions"></div>
            </div>
            <div class="error-actions">
                <a href="index.php" class="btn btn-primary">Return Home</a>
                <a href="contact.php" class="btn btn-secondary">Contact Me</a>
            </div>
            <p class="error-suggestion">Or explore my <a href="projects.php">projects</a> or <a href="blog.php">blog</a>.</p>
        </div>
    </div>
</section>

<style>
.error-404 {
    padding: 80px 0;
    background-color: #f9f9f9;
}

.error-content {
    max-width: 600px;
    margin: 0 auto;
}

.error-title {
    font-size: 3rem;
    color: #333;
    margin-bottom: 20px;
}

.error-description {
    font-size: 1.2rem;
    color: #555;
    margin-bottom: 30px;
}

.error-graphic {
    margin: 30px 0;
}

#error-svg {
    width: 200px;
    height: 200px;
}

.pulse {
    animation: pulse 2s infinite ease-in-out;
}

@keyframes pulse {
    0% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.2); opacity: 0.7; }
    100% { transform: scale(1); opacity: 1; }
}

.error-search {
    margin: 20px 0;
}

.search-input {
    width: 100%;
    max-width: 400px;
    padding: 12px;
    font-size: 1rem;
    border: 2px solid #ddd;
    border-radius: 25px;
    outline: none;
    transition: border-color 0.3s;
}

.search-input:focus {
    border-color: #007bff;
}

.search-suggestions {
    margin-top: 10px;
    text-align: left;
    max-width: 400px;
    margin-left: auto;
    margin-right: auto;
}

.suggestion-item {
    padding: 8px;
    font-size: 0.9rem;
    color: #333;
    background: #fff;
    border-radius: 5px;
    margin: 5px 0;
    cursor: pointer;
    transition: background 0.3s;
}

.suggestion-item:hover {
    background: #e6f3ff;
}

.error-actions .btn {
    margin: 10px;
    padding: 12px 24px;
    font-size: 1rem;
    transition: transform 0.2s, box-shadow 0.2s;
}

.error-actions .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.error-suggestion {
    margin-top: 20px;
    font-size: 1rem;
    color: #555;
}

.error-suggestion a {
    color: #007bff;
    text-decoration: none;
}

.error-suggestion a:hover {
    text-decoration: underline;
}
</style>

<script>
const searchInput = document.getElementById('search-input');
const suggestionsDiv = document.getElementById('search-suggestions');

const pages = [
    { name: 'Home', url: 'index.php', keywords: ['home', 'main', 'welcome'] },
    { name: 'Projects', url: 'projects.php', keywords: ['project', 'portfolio', 'work'] },
    { name: 'Blog', url: 'blog.php', keywords: ['blog', 'posts', 'articles'] },
    { name: 'Contact', url: 'contact.php', keywords: ['contact', 'reach', 'email'] }
];

searchInput.addEventListener('input', () => {
    const query = searchInput.value.toLowerCase().trim();
    suggestionsDiv.innerHTML = '';

    if (query.length > 0) {
        const matches = pages.filter(page => 
            page.name.toLowerCase().includes(query) || 
            page.keywords.some(keyword => keyword.includes(query))
        );

        if (matches.length > 0) {
            matches.forEach(page => {
                const suggestion = document.createElement('div');
                suggestion.className = 'suggestion-item';
                suggestion.innerText = page.name;
                suggestion.addEventListener('click', () => {
                    window.location.href = page.url;
                });
                suggestionsDiv.appendChild(suggestion);
            });
        } else {
            suggestionsDiv.innerHTML = '<div class="suggestion-item">No matches found</div>';
        }
    }
});
</script>

<?php 
include 'includes/footer.php';
ob_end_flush();
?>