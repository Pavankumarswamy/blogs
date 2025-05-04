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
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-4906126626960844"
     crossorigin="anonymous"></script>
    <!-- start webpushr tracking code --> 
    <!-- Start Webpushr Tracking Code -->
    <!-- start webpushr code --> <script>(function(w,d, s, id) {if(typeof(w.webpushr)!=='undefined') return;w.webpushr=w.webpushr||function(){(w.webpushr.q=w.webpushr.q||[]).push(arguments)};var js, fjs = d.getElementsByTagName(s)[0];js = d.createElement(s); js.id = id;js.async=1;js.src = "https://cdn.webpushr.com/app.min.js";fjs.parentNode.appendChild(js);}(window,document, 'script', 'webpushr-jssdk'));webpushr('setup',{'key':'BI7Cs2vsdlMb0UK-bERWcIFAkqlJ_KGIeA3chw2k49_S61zDPNruUSJk5tgpa_9BfevB6C9dLMBvW6mr0J96tJU' });</script><!-- end webpushr code -->

<!-- End Webpushr Tracking Code -->

    
    <title>
        <?php 
        switch ($currentPage) {
            case 'about.php':
                echo 'About Me | pavankumarswamy sheshetti';
                break;
            case 'projects.php':
                echo 'My Projects | pavankumarswamy sheshetti';
                break;
            case 'contact.php':
                echo 'Contact Me | pavankumarswamy sheshetti';
                break;
            case 'blog.php':
                echo 'Blog | pavankumarswamy sheshetti';
                break;
            case 'blog-post.php':
                if (isset($post) && !empty($post['title'])) {
                    echo htmlspecialchars($post['title']) . ' |pavankumarswamy sheshetti';
                } else {
                    echo 'Blog Post | pavankumarswamy sheshetti';
                }
                break;
            default:
                echo 'SPKS | pavankumarswamy sheshetti';
        }
        ?>
    </title>
    
    <!-- Meta tags for SEO -->
    <meta name="description" content="Personal portfolio showcasing my web development projects, skills, and professional blog">
    <meta name="keywords" content="web development, portfolio, blog, developer, designer , pavankumarswamy , sheshetti">
    <meta name="author" content="pavankumarswamy sheshetti">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="https://ggusoc.in/logo.png">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="https://ggusoc.in/css/style.css">
    <link rel="stylesheet" href="https://ggusoc.in/css/responsive.css">
</head>
<body>
    <style>
 .code-container {
  background-color: #fff;
  border: 1px solid #ccc;
  border-radius: 8px;
  padding: 15px;
  margin: 10px 0;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  position: relative;
  max-width: 600px;
  overflow-x: auto;
}

code {
  background-color: #f4f4f4;
  border: 1px solid #ddd;
  border-radius: 4px;
  padding: 2px 6px;
  font-family: 'Courier New', Courier, monospace;
  font-size: 0.9em;
  color: #000;
  white-space: pre;
  display: block;
  overflow-x: auto;
  line-height: 0.72;
  max-width: 100%;
  box-sizing: border-box;
}

.code-container:hover {
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
  transition: box-shadow 0.3s ease;
}

.copy-button {
  position: absolute;
  top: 10px;
  right: 10px;
  background-color: #007bff;
  color: #fff;
  border: none;
  border-radius: 4px;
  padding: 5px 10px;
  cursor: pointer;
  font-size: 0.8em;
}

.copy-button:hover {
  background-color: #0056b3;
}

.copy-button:active {
  background-color: #004085;
}

/* Custom scrollbar styling for WebKit browsers */
code::-webkit-scrollbar {
  height: 8px;
}

code::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 4px;
}

code::-webkit-scrollbar-thumb {
  background: #888;
  border-radius: 4px;
}

code::-webkit-scrollbar-thumb:hover {
  background: #555;
}

/* Responsive adjustments for mobile devices */
@media screen and (max-width: 768px) {
  .code-container {
    max-width: 100%;
    padding: 10px;
    margin: 5px 0;
    border-radius: 6px;
  }

  code {
    font-size: 0.8em;
    padding: 2px 4px;
    line-height: 0.72;
  }

  .copy-button {
    padding: 4px 8px;
    font-size: 0.7em;
    top: 8px;
    right: 8px;
  }
}
    
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
      const codeElements = document.querySelectorAll('code');
      codeElements.forEach((code, index) => {
        // Create a container div
        const container = document.createElement('div');
        container.className = 'code-container';

        // Create a copy button
        const copyButton = document.createElement('button');
        copyButton.className = 'copy-button';
        copyButton.textContent = 'Copy';
        copyButton.addEventListener('click', () => {
          navigator.clipboard.writeText(code.textContent).then(() => {
            copyButton.textContent = 'Copied!';
            setTimeout(() => {
              copyButton.textContent = 'Copy';
            }, 2000);
          }).catch(err => {
            console.error('Failed to copy: ', err);
          });
        });

        // Wrap the code element with the container
        code.parentNode.insertBefore(container, code);
        container.appendChild(code);
        container.appendChild(copyButton);
      });
    });
    
  </script>
  
    <!-- Header Section -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <a href="https://www.ggusoc.in/index">
                        <span class="logo-text">SPKS</span>
                    </a>
                </div>
                
                <div class="nav-toggle" id="navToggle">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
                
                <nav class="nav-menu">
                    <ul class="nav-list">
                        <li class="nav-item <?php echo $currentPage == 'https://ggusoc.in/index' ? 'active' : ''; ?>">
                            <a href="https://ggusoc.in/index" class="nav-link">Home</a>
                        </li>
                        <li class="nav-item <?php echo $currentPage == 'https://ggusoc.in/about' ? 'active' : ''; ?>">
                            <a href="https://ggusoc.in/about" class="nav-link">About</a>
                        </li>
                        <li class="nav-item <?php echo $currentPage == 'https://ggusoc.in/projects' ? 'active' : ''; ?>">
                            <a href="https://ggusoc.in/projects" class="nav-link">Projects</a>
                        </li>
                        <li class="nav-item <?php echo $currentPage == 'https://ggusoc.in/blog' || $currentPage == 'blog-post.php' ? 'active' : ''; ?>">
                            <a href="https://ggusoc.in/blog" class="nav-link">Blog</a>
                        </li>
                        <li class="nav-item <?php echo $currentPage == 'https://ggusoc.in/contact' ? 'active' : ''; ?>">
                            <a href="https://ggusoc.in/contact" class="nav-link">Contact</a>
                        </li>
                        <li class="nav-item <?php echo strpos($currentPage, 'admin') !== false ? 'active' : ''; ?>">
                            <a href="https://ggusoc.in/admin/login" class="nav-link admin-link"><i class="fas fa-lock"></i> Admin</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>
    <!-- Main Content -->
    <main>