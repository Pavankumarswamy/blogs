<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<div class="sidebar">
    <div class="sidebar-header">
        <h2>Admin Panel</h2>
        <button class="sidebar-toggle"><i class="fas fa-bars"></i></button>
    </div>
    <ul class="sidebar-menu">
        <li class="<?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">
            <a href="index.php"><i class="fas fa-home"></i> Dashboard</a>
        </li>
        <li class="<?= basename($_SERVER['PHP_SELF']) === 'posts.php' || basename($_SERVER['PHP_SELF']) === 'create-post.php' || basename($_SERVER['PHP_SELF']) === 'edit-post.php' ? 'active' : '' ?>">
            <a href="create-post.php"><i class="fas fa-blog"></i> Blog Posts</a>
        </li>
        <li class="<?= basename($_SERVER['PHP_SELF']) === 'projects.php' || basename($_SERVER['PHP_SELF']) === 'create-project.php' || basename($_SERVER['PHP_SELF']) === 'edit-project.php' ? 'active' : '' ?>">
            <a href="create-project.php"><i class="fas fa-project-diagram"></i> Projects</a>
        </li>
        <li class="<?= basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : '' ?>">
            <a href="https://ggusoc.in/"><i class="fas fa-cog"></i> visit site</a>
        </li>
        <li>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </li>
    </ul>
</div>

<style>
.sidebar {
    width: 250px;
    background: #2c3e50;
    color: #ecf0f1;
    height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    transition: width 0.3s;
    overflow-y: auto;
}

.sidebar.collapsed {
    width: 60px;
}

.sidebar.collapsed .sidebar-header h2,
.sidebar.collapsed .sidebar-menu a span {
    display: none;
}

.sidebar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #34495e;
}

.sidebar-header h2 {
    margin: 0;
    font-size: 1.5em;
}

.sidebar-toggle {
    background: none;
    border: none;
    color: #ecf0f1;
    font-size: 1.2em;
    cursor: pointer;
}

.sidebar-menu {
    list-style: none;
    padding: 0;
    margin: 0;
}

.sidebar-menu li {
    margin: 10px 0;
}

.sidebar-menu li a {
    display: flex;
    align-items: center;
    padding: 15px 20px;
    color: #ecf0f1;
    text-decoration: none;
    transition: background 0.2s;
}

.sidebar-menu li a i {
    margin-right: 10px;
    width: 20px;
    text-align: center;
}

.sidebar-menu li a:hover,
.sidebar-menu li.active a {
    background: #34495e;
}

.admin-content {
    margin-left: 250px;
    padding: 20px;
    transition: margin-left 0.3s;
}

.admin-content.full-width {
    margin-left: 60px;
}

@media (max-width: 768px) {
    .sidebar {
        width: 60px;
    }

    .sidebar-header h2,
    .sidebar-menu a span {
        display: none;
    }

    .admin-content {
        margin-left: 60px;
    }
}
</style>