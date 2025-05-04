<?php
// Get current page name
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    
    <title>
        <?php 
        switch ($currentPage) {
            case 'index.php':
                echo 'Dashboard | Admin Panel';
                break;
            case 'create-post.php':
                echo 'Create Post | Admin Panel';
                break;
            case 'edit-post.php':
                echo 'Edit Post | Admin Panel';
                break;
            default:
                echo 'Admin Panel';
        }
        ?>
    </title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/admin-style.css">
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">Portfolio Admin</div>
                <button type="button" class="sidebar-close">&times;</button>
            </div>
            
            <div class="sidebar-menu">
                <ul>
                    <li class="sidebar-menu-item">
                        <a href="index.php" class="sidebar-menu-link <?php echo $currentPage === 'index.php' ? 'active' : ''; ?>">
                            <i class="fas fa-th-large"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="create-post.php" class="sidebar-menu-link <?php echo $currentPage === 'create-post.php' ? 'active' : ''; ?>">
                            <i class="fas fa-pen-to-square"></i>
                            <span>Create Post</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="../index.php" class="sidebar-menu-link" target="_blank">
                            <i class="fas fa-eye"></i>
                            <span>View Site</span>
                        </a>
                    </li>
                </ul>
            </div>
            
            <div class="sidebar-footer">
                <p class="sidebar-footer-text">&copy; <?php echo date('Y'); ?> Portfolio Admin</p>
            </div>
        </aside>
        
        <!-- Main Content -->
        <div class="admin-main">
            <div class="admin-topbar">
                <div class="admin-topbar-left">
                    <button class="mobile-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h2 class="topbar-title">
                        <?php 
                        switch ($currentPage) {
                            case 'index.php':
                                echo 'Dashboard';
                                break;
                            case 'create-post.php':
                                echo 'Create Post';
                                break;
                            case 'edit-post.php':
                                echo 'Edit Post';
                                break;
                            default:
                                echo 'Admin Panel';
                        }
                        ?>
                    </h2>
                </div>
                
                <div class="admin-topbar-right">
                    <div class="user-dropdown">
                        <div class="user-dropdown-toggle">
                            <svg width="35" height="35" viewBox="0 0 50 50" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="25" cy="25" r="25" fill="#e0e0e0"/>
                                <circle cx="25" cy="20" r="8" fill="#c0c0c0"/>
                                <path d="M25,50 C34,50 41,45 41,35 C41,28 34,30 25,30 C16,30 9,28 9,35 C9,45 16,50 25,50 Z" fill="#c0c0c0"/>
                            </svg>
                            <span><?php echo isset($_SESSION['admin_username']) ? $_SESSION['admin_username'] : 'Admin'; ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        
                        <div class="user-dropdown-menu">
                            <a href="../index.php" class="user-dropdown-item" target="_blank">
                                <i class="fas fa-globe"></i>
                                <span>View Site</span>
                            </a>
                            <div class="user-dropdown-divider"></div>
                            <a href="logout.php" class="user-dropdown-item">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Logout</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Page Content -->
