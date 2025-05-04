<?php
// Start output buffering
ob_start();

// Disable error display, keep logging
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Log start
error_log('Starting about.php');

// Start session if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    error_log('Session started, ID: ' . session_id());
}

// Include configuration
require_once 'config.php';

// MySQL Database Connection
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        error_log('MySQL connection failed: ' . $conn->connect_error);
        die('Database connection failed. Please try again later.');
    }
    $conn->set_charset('utf8mb4');
    error_log('Connected to MySQL database: ' . DB_NAME);
} catch (Exception $e) {
    error_log('MySQL error: ' . $e->getMessage());
    die('Database error. Please try again later.');
}

// Create education table if not exists
try {
    $sql = "
    CREATE TABLE IF NOT EXISTS education (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        degree VARCHAR(255) NOT NULL,
        field_of_study VARCHAR(255) NOT NULL,
        institution VARCHAR(255) NOT NULL,
        description TEXT,
        start_date DATE NOT NULL,
        end_date DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->query($sql);
    error_log('education table checked/created');
} catch (Exception $e) {
    error_log('education table creation failed: ' . $e->getMessage());
    die('Database table error');
}

// Create skills table if not exists
try {
    $sql = "
    CREATE TABLE IF NOT EXISTS skills (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        category VARCHAR(255) NOT NULL,
        name VARCHAR(255) NOT NULL,
        proficiency INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->query($sql);
    error_log('skills table checked/created');
} catch (Exception $e) {
    error_log('skills table creation failed: ' . $e->getMessage());
    die('Database table error');
}

// Function to fetch education data
function getEducation($conn) {
    $education = [];
    try {
        $sql = "SELECT id, degree, field_of_study, institution, description, start_date, end_date FROM education ORDER BY start_date DESC";
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $education[] = $row;
            }
        } else {
            error_log('Education query failed: ' . $conn->error);
        }
        return $education;
    } catch (Exception $e) {
        error_log('Education error: ' . $e->getMessage());
        return [];
    }
}

// Function to fetch skills by category
function getSkills($conn, $category = null) {
    $skills = [];
    try {
        $sql = "SELECT id, category, name, proficiency FROM skills";
        if ($category) {
            $sql .= " WHERE category = ?";
        }
        $sql .= " ORDER BY proficiency DESC";
        
        $stmt = $conn->prepare($sql);
        if ($category) {
            $stmt->bind_param('s', $category);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $skills[] = $row;
        }
        
        $stmt->close();
        return $skills;
    } catch (Exception $e) {
        error_log("Skills error for category $category: " . $e->getMessage());
        return [];
    }
}

// Get education data
$education = getEducation($conn);

// Get skills data by category
$categories = ['Frontend Development', 'Backend Development', 'Mobile Development', 'Database', 'Tools & Technologies', 'AI & Machine Learning', 'Other'];
$allSkills = [];
foreach ($categories as $category) {
    $allSkills[$category] = getSkills($conn, $category);
}

// Set caching headers for performance
header("Cache-Control: public, max-age=86400");
header("Expires: " . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');

// Include public header
$headerFile = 'includes/header.php';
if (!file_exists($headerFile)) {
    error_log('Error: header.php not found at ' . realpath($headerFile));
    die('Header file not found.');
}
include $headerFile;
?>

<!-- Meta Tags for SEO -->
<meta name="description" content="Learn about Pavankumarswamy Sheshetti, a skilled app and web developer, founder of CETNext, and Computer Science student specializing in Flutter, Python, and EdTech solutions.">
<meta name="keywords" content="Pavankumarswamy Sheshetti, app developer, web developer, Flutter, Python, EdTech, CETNext, software development, AI, computer science">
<meta name="author" content="Pavankumarswamy Sheshetti">
<meta name="robots" content="index, follow">
<meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">

<!-- Open Graph for Social Sharing -->
<meta property="og:title" content="About Pavankumarswamy Sheshetti | App & Web Developer">
<meta property="og:description" content="Discover the journey of Pavankumarswamy Sheshetti, founder of CETNext and a passionate developer skilled in Flutter, Python, and EdTech innovation.">
<meta property="og:image" content="https://avatars.githubusercontent.com/u/187513455">
<meta property="og:url" content="<?php echo SITE_URL; ?>/about.php">
<meta property="og:type" content="profile">

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="About Pavankumarswamy Sheshetti | App & Web Developer">
<meta name="twitter:description" content="Discover the journey of Pavankumarswamy Sheshetti, founder of CETNext and a passionate developer skilled in Flutter, Python, and EdTech innovation.">
<meta name="twitter:image" content="https://avatars.githubusercontent.com/u/187513455">

<!-- JSON-LD Schema for Person and Organization -->
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "Person",
    "name": "Pavankumarswamy Sheshetti",
    "jobTitle": "App & Web Developer, Founder & CEO",
    "affiliation": {
        "@type": "Organization",
        "name": "CETNext GV LLP",
        "url": "<?php echo SITE_URL; ?>"
    },
    "alumniOf": [
        {
            "@type": "EducationalOrganization",
            "name": "Sri Jyoti Polytechnic",
            "sameAs": ""
        },
        {
            "@type": "EducationalOrganization",
            "name": "GIET, Godavari Global University",
            "sameAs": ""
        }
    ],
    "image": "https://avatars.githubusercontent.com/u/187513455",
    "url": "<?php echo SITE_URL; ?>/about.php",
    "sameAs": [
        "https://www.linkedin.com/in/pavankumarswamy-sheshetti",
        "https://github.com/pavankumarswamy"
    ],
    "knowsAbout": [
        "Flutter",
        "Python",
        "JavaScript",
        "Web Development",
        "Mobile App Development",
        "EdTech",
        "AI",
        "Firebase",
        "Supabase"
    ]
}
</script>
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "Organization",
    "name": "CETNext GV LLP",
    "url": "<?php echo SITE_URL; ?>",
    "logo": "<?php echo SITE_LOGO; ?>",
    "founder": {
        "@type": "Person",
        "name": "Pavankumarswamy Sheshetti"
    },
    "description": "CETNext is an EdTech platform empowering students to excel in entrance exams like CET and EAMCET through mock tests, analytics, and smart preparation strategies."
}
</script>

<main>
    <!-- About Hero Section -->
    <section class="about-hero" aria-labelledby="about-heading">
        <div class="container">
            <div class="section-header"><br><br><br>
                <h1 id="about-heading" class="section-title">About Pavankumarswamy Sheshetti</h1>
                <br><p class="section-subtitle"><br>Discover my journey in tech and education</p>
            </div>
        </div>
    </section>

    <!-- About Content Section -->
    <article class="about-content section" aria-labelledby="bio-heading">
        <div class="container">
            <div class="about-grid">
                <div class="about-image">
                    <br><img src="https://avatars.githubusercontent.com/u/187513455" alt="Pavankumarswamy Sheshetti, app and web developer" class="about-image" loading="lazy" width="200" height="200">
                </div>
                
                <div class="about-text">
                    <br><h2 id="bio-heading">Hi, I'm Pavankumarswamy Sheshetti</h2>
                    <h3>Founder & CEO @ CETNext GV LLP | App & Web Developer</h3>
                    <p>I'm a passionate <strong>Computer Science student</strong> at GIET, Godavari Global University, and the founder of <a href="<?php echo SITE_URL; ?>" rel="nofollow">CETNext</a>, an EdTech platform empowering students to excel in entrance exams like CET and EAMCET through mock tests, analytics, and smart preparation strategies.</p>
                    <p>My journey began with a <strong>Computer Science diploma</strong> from Sri Jyoti Polytechnic (2021-2024, 91% grade), where I discovered my love for coding. As an intern at BharathRise, I’m honing my skills in <strong>software development</strong> while leading CETNext to innovate in education technology.</p>
                    <p>When not coding, I’m exploring <strong>AI/ML</strong>, contributing to open-source projects on <a href="https://github.com/pavankumarswamy" rel="nofollow">GitHub</a>, or mentoring students in app development workshops. I’m driven by a mission to blend technology and education for impact. Want to collaborate? <a href="<?php echo SITE_URL; ?>/contact.php">Get in touch</a>.</p>
                    
                    <div class="about-cta">
                        <a href="<?php echo SITE_URL; ?>/contact.php" class="btn btn-primary" aria-label="Contact Pavankumarswamy Sheshetti">Contact Me</a>
                        <a href="<?php echo SITE_URL; ?>/assets/cv.pdf" class="btn btn-secondary" aria-label="Download Pavankumarswamy Sheshetti's CV" download>Download CV</a>
                    </div>
                </div>
            </div>
        </div>
    </article>

    <!-- Skills Section -->
    <section class="skills section" aria-labelledby="skills-heading">
        <div class="container">
            <div class="section-header">
                <h2 id="skills-heading" class="section-title">My Skills</h2>
                <p class="section-subtitle"><br>Technical expertise in app and web development</p>
            </div>
            
            <nav class="skills-filter" aria-label="Skills category filter">
                <button class="filter-btn active" data-category="all" aria-pressed="true">All</button>
                <?php foreach ($categories as $category): ?>
                    <?php if (!empty($allSkills[$category])): ?>
                        <button class="filter-btn" data-category="<?php echo strtolower(str_replace(' ', '-', $category)); ?>" aria-pressed="false">
                            <?php echo htmlspecialchars($category); ?>
                        </button>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
            
            <div class="skills-grid">
                <?php foreach ($allSkills as $category => $skills): ?>
                    <?php if (!empty($skills)): ?>
                        <div class="skill-category <?php echo strtolower(str_replace(' ', '-', $category)); ?>">
                            <h3><?php echo htmlspecialchars($category); ?></h3>
                            <ul class="skill-items" aria-label="Skills in <?php echo htmlspecialchars($category); ?>">
                                <?php foreach ($skills as $skill): ?>
                                    <li class="skill-item" data-proficiency="<?php echo ($skill['proficiency'] * 20); ?>">
                                        <span class="skill-name"><?php echo htmlspecialchars($skill['name']); ?></span>
                                        <div class="skill-bar" role="progressbar" aria-valuenow="<?php echo ($skill['proficiency'] * 20); ?>" aria-valuemin="0" aria-valuemax="100">
                                            <div class="skill-progress"></div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php else: ?>
                        <div class="skill-category <?php echo strtolower(str_replace(' ', '-', $category)); ?>">
                            <h3><?php echo htmlspecialchars($category); ?></h3>
                            <p>No skills listed yet.</p>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Experience Section -->
    <section class="experience section" aria-labelledby="experience-heading">
        <div class="container">
            <div class="section-header">
                <h2 id="experience-heading" class="section-title">Experience & Education</h2>
                <br><p class="section-subtitle"><br>My professional and academic journey</p>
            </div>
            
            <div class="timeline">
                <div class="timeline-section">
                    <h3>Professional Experience</h3>
                    
                    <div class="timeline-item">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <div class="timeline-date">Apr 2025 - Present</div>
                            <h4>Founder & CEO</h4>
                            <p class="timeline-company">CETNext GV LLP</p>
                            <p>Leading the development of an EdTech platform for entrance exam preparation, overseeing app development, analytics, and strategic growth.</p>
                        </div>
                    </div>
                    
                    <div class="timeline-item">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <div class="timeline-date">Dec 2024 - Present</div>
                            <h4>Student Intern</h4>
                            <p class="timeline-company">BharathRise</p>
                            <p>Enhansing my communication skills</p>
                        </div>
                    </div>
                    
                    <div class="timeline-item">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <div class="timeline-date">2023 - Present</div>
                            <h4>Freelance Developer</h4>
                            <p class="timeline-company">Self-Employed</p>
                            <p>Developed mobile and web applications, including CETMock, Winwatts Solar, and ICAI25 Conference websites, using Flutter, Firebase, and modern web technologies.</p>
                        </div>
                    </div>
                </div>
                
                <div class="timeline-section">
                    <h3>Education</h3>
                    
                    <?php if (empty($education)): ?>
                        <p>No education information available yet. Check back soon!</p>
                    <?php else: ?>
                        <?php foreach ($education as $edu): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker"></div>
                                <div class="timeline-content">
                                    <div class="timeline-date">
                                        <?php 
                                            $start_year = date('Y', strtotime($edu['start_date']));
                                            $end_year = !empty($edu['end_date']) ? date('Y', strtotime($edu['end_date'])) : 'Present';
                                            echo $start_year . ' - ' . $end_year;
                                        ?>
                                    </div>
                                    <h4><?php echo htmlspecialchars($edu['degree']); ?> in <?php echo htmlspecialchars($edu['field_of_study']); ?></h4>
                                    <p class="timeline-company"><?php echo htmlspecialchars($edu['institution']); ?></p>
                                    <p><?php echo htmlspecialchars($edu['description'] ?? ''); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Achievements Section -->
    <section class="achievements section" aria-labelledby="achievements-heading">
        <div class="container">
            <div class="section-header">
                <h2 id="achievements-heading" class="section-title">Achievements</h2>
                <br><p class="section-subtitle"><br>Milestones in my journey</p>
            </div>
            
            <div class="achievements-grid">
                <div class="achievement-card">
                    <h4>3rd Prize, Ideathon GGU24 & MEDHA Engineers' Day</h4>
                    <p>Sep 2024 | Recognized for innovative project presentation at GIET’s Project Expo.</p>
                </div>
                <div class="achievement-card">
                    <h4>Future Founders Workshop</h4>
                    <p>2024 | Gained insights into entrepreneurship at SIT’s startup-focused workshop.</p>
                </div>
                <div class="achievement-card">
                    <h4>Technovate for India Conclave</h4>
                    <p>Nov 2024 | Represented GIET in a national case competition, showcasing problem-solving skills.</p>
                </div>
                <div class="achievement-card">
                    <h4>Joy with Code Webinar</h4>
                    <p>Earned certificate (JWC/0003) for completing coding and development webinar.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="testimonials section" aria-labelledby="testimonials-heading">
        <div class="container">
            <div class="section-header">
                <h2 id="testimonials-heading" class="section-title">Testimonials</h2>
               <br> <p class="section-subtitle"><br>Feedback from collaborators</p>
            </div>
            
            <div class="testimonials-carousel" role="region" aria-label="Testimonials carousel">
                <div class="testimonials-grid">
                    <div class="testimonial-card">
                        <div class="testimonial-content">
                            <p>"Pavankumarswamy’s leadership in developing the CETMock App has transformed how students prepare for entrance exams. His technical expertise and vision are exceptional."</p>
                        </div>
                        <div class="testimonial-info">
                            <div class="testimonial-avatar">
                                <svg viewBox="0 0 50 50" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                    <circle cx="25" cy="25" r="25" fill="#e0e0e0"/>
                                    <circle cx="25" cy="20" r="8" fill="#c0c0c0"/>
                                    <path d="M25,50 C34,50 41,45 41,35 C41,28 34,30 25,30 C16,30 9,28 9,35 C9,45 16,50 25,50 Z" fill="#c0c0c0"/>
                                </svg>
                            </div>
                            <div class="testimonial-author">
                                <h4>Suresh Babu</h4>
                                <p>EdTech Mentor</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="testimonial-card">
                        <div class="testimonial-content">
                            <p>"Working with Pavankumarswamy on the ICAI25 Conference website was seamless. His ability to integrate Firebase and Razorpay under tight deadlines was impressive."</p>
                        </div>
                        <div class="testimonial-info">
                            <div class="testimonial-avatar">
                                <svg viewBox="0 0 50 50" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                    <circle cx="25" cy="25" r="25" fill="#e0e0e0"/>
                                    <circle cx="25" cy="20" r="8" fill="#c0c0c0"/>
                                    <path d="M25,50 C34,50 41,45 41,35 C41,28 34,30 25,30 C16,30 9,28 9,35 C9,45 16,50 25,50 Z" fill="#c0c0c0"/>
                                </svg>
                            </div>
                            <div class="testimonial-author">
                                <h4>Anjali Rao</h4>
                                <p>Event Organizer, ICAI25</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="testimonial-card">
                        <div class="testimonial-content">
                            <p>"Pavankumarswamy’s SkillUp app, with its AI-powered quizzes, has been a game-changer for our students. His dedication to innovation is inspiring."</p>
                        </div>
                        <div class="testimonial-info">
                            <div class="testimonial-avatar">
                                <svg viewBox="0 0 50 50" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                    <circle cx="25" cy="25" r="25" fill="#e0e0e0"/>
                                    <circle cx="25" cy="20" r="8" fill="#c0c0c0"/>
                                    <path d="M25,50 C34,50 41,45 41,35 C41,28 34,30 25,30 C16,30 9,28 9,35 C9,45 16,50 25,50 Z" fill="#c0c0c0"/>
                                </svg>
                            </div>
                            <div class="testimonial-author">
                                <h4>Kiran Patel</h4>
                                <p>Academic Coordinator</p>
                            </div>
                        </div>
                    </div>
                </div>
                <button class="carousel-prev" aria-label="Previous testimonial"><i class="fas fa-chevron-left"></i></button>
                <button class="carousel-next" aria-label="Next testimonial"><i class="fas fa-chevron-right"></i></button>
            </div>
        </div>
    </section>
</main>

<style>
/* General container */
.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 1rem;
}

/* Keyframes for entrance animations */
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes slideUp {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}

/* Hero section */
.about-hero {
  text-align: center;
  padding: 3rem 0;
  background: #f8f9fa;
  animation: fadeIn 0.8s ease-out forwards;
}

.section-title {
  font-size: 2.5rem;
  margin-bottom: 0.5rem;
  font-weight: 700;
  animation: slideUp 0.6s ease-out 0.2s forwards;
  opacity: 0;
}

.section-subtitle {
  font-size: 1.1rem;
  color: #6c757d;
  margin-bottom: 1rem;
  animation: slideUp 0.6s ease-out 0.3s forwards;
  opacity: 0;
}

/* About content */
.about-image img {
  width: 100%;
  max-width: 200px;
  border-radius: 10px;
  object-fit: cover;
  aspect-ratio: 1/1;
  margin: 0 auto;
  display: block;
  animation: slideUp 0.6s ease-out 0.4s forwards;
  opacity: 0;
}
.btn-primary,
.btn-secondary {
  padding: 0.6rem 1.2rem;
  border-radius: 5px;
  text-decoration: none;
  font-size: 0.95rem;
  display: inline-block;
  animation: slideUp 0.6s ease-out 0.8s forwards;
  opacity: 0;
}

.btn-primary {
  background: #007bff;
  color: white;
}

.btn-primary:hover,
.btn-primary:active {
  background: #0056b3;
}

.btn-secondary {
  background: #6c757d;
  color: white;
}

.btn-secondary:hover,
.btn-secondary:active {
  background: #5a6268;
}

/* Skills section */
.skills-filter {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1.5rem;
  justify-content: center;
  flex-wrap: wrap;
}

.filter-btn {
  padding: 0.5rem 1rem;
  border: 1px solid #ddd;
  background: #f8f9fa;
  border-radius: 5px;
  cursor: pointer;
  font-size: 0.9rem;
  animation: slideUp 0.6s ease-out calc(0.3s + (var(--btn-index, 0) * 0.1s)) forwards;
  opacity: 0;
}

.filter-btn.active,
.filter-btn:hover,
.filter-btn:active {
  background: #007bff;
  color: white;
  border-color: #007bff;
}

.skills-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1.5rem;
}

.skill-category {
  display: none;
  opacity: 0;
  animation: slideUp 0.6s ease-out calc(0.4s + (var(--cat-index, 0) * 0.1s)) forwards;
}

.skill-category.visible {
  display: block;
  opacity: 1;
  transition: opacity 0.3s ease-out;
}

.skill-item {
  margin-bottom: 1rem;
}

.skill-name {
  font-size: 0.95rem;
  margin-bottom: 0.3rem;
  display: block;
}

.skill-bar {
  background: #e9ecef;
  height: 6px;
  border-radius: 3px;
}

.skill-progress {
  background: #007bff;
  height: 100%;
  border-radius: 3px;
  width: 0;
  transition: width 0.8s ease-out;
}

/* Timeline section */
.timeline {
  padding: 1.5rem 0;
}

.timeline-section {
  margin-bottom: 1.5rem;
}

.timeline-item {
  position: relative;
  padding-left: 2rem;
  margin-bottom: 1.5rem;
  animation: slideUp 0.6s ease-out calc(0.4s + (var(--item-index, 0) * 0.2s)) forwards;
  opacity: 0;
}

.timeline-marker {
  position: absolute;
  left: 0;
  top: 0;
  width: 12px;
  height: 12px;
  background: #007bff;
  border-radius: 50%;
}

.timeline-content {
  background: #f8f9fa;
  padding: 1rem;
  border-radius: 6px;
}

.timeline-date {
  font-size: 0.85rem;
  color: #6c757d;
  margin-bottom: 0.3rem;
}

.timeline-content h4 {
  font-size: 1.1rem;
  margin-bottom: 0.3rem;
}

.timeline-company {
  font-size: 0.95rem;
  color: #6c757d;
}

/* Achievements section */
.achievements-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
}

.achievement-card {
  background: #fff;
  padding: 1rem;
  border-radius: 6px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
  transition: transform 0.3s;
  animation: slideUp 0.6s ease-out calc(0.4s + (var(--card-index, 0) * 0.1s)) forwards;
  opacity: 0;
}

.achievement-card:hover {
  transform: translateY(-3px);
}

.achievement-card h4 {
  font-size: 1rem;
  margin-bottom: 0.5rem;
}

.achievement-card p {
  font-size: 0.9rem;
}

/* Testimonials carousel */
.testimonials-carousel {
  position: relative;
}

.testimonials-grid {
  display: flex;
}

.testimonial-card {
  flex: 0 0 100%;
  background: #fff;
  padding: 1rem;
  border-radius: 6px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
  margin: 0 0.5rem;
  animation: slideUp 0.6s ease-out calc(0.4s + (var(--card-index, 0) * 0.2s)) forwards;
  opacity: 0;
}

.testimonial-content p {
  font-style: italic;
  font-size: 0.95rem;
  margin-bottom: 0.75rem;
}

.testimonial-info {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.testimonial-avatar svg {
  width: 40px;
  height: 40px;
}

.testimonial-author h4 {
  font-size: 0.95rem;
}

.testimonial-author p {
  font-size: 0.85rem;
  color: #6c757d;
}

.carousel-prev,
.carousel-next {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  background: #007bff;
  color: white;
  border: none;
  padding: 0.5rem 0.75rem;
  cursor: pointer;
  border-radius: 50%;
  font-size: 0.9rem;
}

.carousel-prev {
  left: 0;
}

.carousel-next {
  right: 0;
}

.carousel-prev:hover,
.carousel-next:hover {
  background: #0056b3;
}

/* Responsive design */
@media (max-width: 768px) {
  .about-hero {
    padding: 2rem 0;
  }
  .section-title {
    font-size: 2rem;
  }
  .section-subtitle {
    font-size: 1rem;
  }
  .about-grid {
    grid-template-columns: 1fr;
    text-align: center;
  }
  .about-image img {
    max-width: 150px;
  }
  .about-text h2 {
    font-size: 1.6rem;
  }
  .about-text h3 {
    font-size: 1.2rem;
  }
  .about-text p {
    font-size: 0.95rem;
  }
  .about-cta {
    justify-content: center;
  }
  .skills-filter {
    justify-content: flex-start;
  }
  .filter-btn {
    font-size: 0.85rem;
    padding: 0.4rem 0.8rem;
  }
  .skills-grid {
    grid-template-columns: 1fr;
  }
  .timeline-item {
    padding-left: 1.5rem;
  }
  .timeline-marker {
    width: 10px;
    height: 10px;
  }
  .testimonials-grid {
    flex-direction: column;
  }
  .testimonial-card {
    margin: 0.3rem 0;
  }
  .carousel-prev,
  .carousel-next {
    padding: 0.4rem 0.6rem;
  }
}

@media (max-width: 480px) {
  .container {
    padding: 0 0.75rem;
  }
  .section-title {
    font-size: 1.8rem;
  }
  .section-subtitle {
    font-size: 0.9rem;
  }
  .about-text h2 {
    font-size: 1.4rem;
  }
  .about-text h3 {
    font-size: 1.1rem;
  }
  .about-text p {
    font-size: 0.9rem;
  }
  .btn-primary,
  .btn-secondary {
    padding: 0.5rem 1rem;
    font-size: 0.85rem;
  }
  .filter-btn {
    font-size: 0.8rem;
    padding: 0.3rem 0.6rem;
  }
  .skill-name {
    font-size: 0.9rem;
  }
  .timeline-content h4 {
    font-size: 1rem;
  }
  .timeline-date,
  .timeline-company {
    font-size: 0.8rem;
  }
  .achievement-card h4 {
    font-size: 0.95rem;
  }
  .achievement-card p {
    font-size: 0.85rem;
  }
  .testimonial-content p {
    font-size: 0.9rem;
  }
  .testimonial-author h4 {
    font-size: 0.9rem;
  }
  .testimonial-author p {
    font-size: 0.8rem;
  }
  
}

/* Accessibility: Disable animations for users who prefer reduced motion */
@media (prefers-reduced-motion: reduce) {
  .about-hero,
  .section-title,
  .section-subtitle,
  .about-grid,
  .about-image img,
  .about-text h2,
  .about-text h3,
  .about-text p,
  .btn-primary,
  .btn-secondary,
  .filter-btn,
  .skill-category,
  .skill-category.visible,
  .timeline-item,
  .achievement-card,
  .testimonial-card {
    animation: none;
    opacity: 1;
    transform: none;
    transition: none;
  }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const filterButtons = document.querySelectorAll('.filter-btn');
    const skillCategories = document.querySelectorAll('.skill-category');
    
    // Set animation indices for dynamic delays
    filterButtons.forEach((btn, i) => {
        btn.style.setProperty('--btn-index', i);
    });
    skillCategories.forEach((cat, i) => {
        cat.style.setProperty('--cat-index', i);
    });
    document.querySelectorAll('.timeline-item').forEach((item, i) => {
        item.style.setProperty('--item-index', i);
    });
    document.querySelectorAll('.achievement-card').forEach((card, i) => {
        card.style.setProperty('--card-index', i);
    });
    document.querySelectorAll('.testimonial-card').forEach((card, i) => {
        card.style.setProperty('--card-index', i);
    });

    // Skill filtering function
    const applyFilter = (category) => {
        filterButtons.forEach(btn => {
            const isActive = btn.dataset.category === category;
            btn.classList.toggle('active', isActive);
            btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
        skillCategories.forEach(cat => {
            const isVisible = category === 'all' || cat.classList.contains(category);
            cat.classList.toggle('visible', isVisible);
        });
    };

    // Initialize filter
    applyFilter('all');
    filterButtons.forEach(button => button.addEventListener('click', () => applyFilter(button.dataset.category)));

    // Animate skill progress bars
    const animateSkillBars = () => {
        document.querySelectorAll('.skill-item').forEach(item => {
            item.querySelector('.skill-progress').style.width = `${item.dataset.proficiency}%`;
        });
    };

    // Testimonials carousel
    const testimonialsGrid = document.querySelector('.testimonials-grid');
    const prevButton = document.querySelector('.carousel-prev');
    const nextButton = document.querySelector('.carousel-next');
    let currentIndex = 0;
    const totalItems = document.querySelectorAll('.testimonial-card').length;

    const updateCarousel = () => {
        testimonialsGrid.style.transform = `translateX(-${currentIndex * 100}%)`;
    };

    nextButton.addEventListener('click', () => {
        currentIndex = (currentIndex + 1) % totalItems;
        updateCarousel();
    });

    prevButton.addEventListener('click', () => {
        currentIndex = (currentIndex - 1 + totalItems) % totalItems;
        updateCarousel();
    });

    // Touch swipe support for carousel
    let touchStartX = 0;
    let touchEndX = 0;
    testimonialsGrid.addEventListener('touchstart', e => touchStartX = e.changedTouches[0].screenX);
    testimonialsGrid.addEventListener('touchend', e => {
        touchEndX = e.changedTouches[0].screenX;
        if (touchStartX - touchEndX > 50) {
            currentIndex = (currentIndex + 1) % totalItems;
            updateCarousel();
        } else if (touchEndX - touchStartX > 50) {
            currentIndex = (currentIndex - 1 + totalItems) % totalItems;
            updateCarousel();
        }
    });

    // Auto-slide carousel
    let autoSlide = setInterval(() => {
        currentIndex = (currentIndex + 1) % totalItems;
        updateCarousel();
    }, 5000);

    testimonialsGrid.addEventListener('mouseenter', () => clearInterval(autoSlide));
    testimonialsGrid.addEventListener('mouseleave', () => {
        autoSlide = setInterval(() => {
            currentIndex = (currentIndex + 1) % totalItems;
            updateCarousel();
        }, 5000);
    });

    // Lazy load images
    const lazyImages = document.querySelectorAll('img[loading="lazy"]');
    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                if (img.dataset.src) {
                    img.src = img.dataset.src;
                    img.removeAttribute('data-src');
                }
                observer.unobserve(img);
            }
        });
    });
    lazyImages.forEach(img => observer.observe(img));

    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', e => {
            e.preventDefault();
            document.querySelector(anchor.getAttribute('href')).scrollIntoView({ behavior: 'smooth' });
        });
    });

    // Trigger animations on scroll
    window.addEventListener('scroll', animateSkillBars);
    animateSkillBars();
});
</script>

<?php 
$conn->close();
$footerFile = 'includes/footer.php';
if (!file_exists($footerFile)) {
    error_log('Error: footer.php not found at ' . realpath($footerFile));
    die('Footer file not found.');
}
include $footerFile;
ob_end_flush();
?>