document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // Reviewed for blog category URL issue (blog.php?category=XXX).
    // No changes needed, as this script does not generate blog category URLs.
    // Check PHP files (e.g., includes/header.php, includes/footer.php, blog-post.php) for links like blog.php?category=Development.

    // Mobile Navigation
    const navToggle = document.getElementById('navToggle');
    const navMenu = document.querySelector('.nav-menu');
    
    if (navToggle) {
        navToggle.addEventListener('click', function() {
            navToggle.classList.toggle('active');
            navMenu.classList.toggle('active');
        });
    }
    
    // Close menu when clicking outside
    document.addEventListener('click', function(event) {
        if (navMenu && navMenu.classList.contains('active') && !event.target.closest('.nav-menu') && !event.target.closest('#navToggle')) {
            navMenu.classList.remove('active');
            if (navToggle) {
                navToggle.classList.remove('active');
            }
        }
    });
    
    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href');
            
            // Only run for actual anchors (not just "#")
            if (targetId !== '#') {
                e.preventDefault();
                const target = document.querySelector(targetId);
                
                if (target) {
                    // Close mobile nav if open
                    if (navMenu && navMenu.classList.contains('active')) {
                        navMenu.classList.remove('active');
                        if (navToggle) {
                            navToggle.classList.remove('active');
                        }
                    }
                    
                    // Scroll to target
                    window.scrollTo({
                        top: target.offsetTop - 80, // Account for header height
                        behavior: 'smooth'
                    });
                }
            }
        });
    });
    
    // Header background change on scroll
    const header = document.querySelector('.header');
    
    if (header) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 50) {
                header.style.backgroundColor = 'rgba(255, 255, 255, 0.95)';
                header.style.boxShadow = '0 2px 10px rgba(0, 0, 0, 0.1)';
            } else {
                header.style.backgroundColor = 'rgba(255, 255, 255, 0.95)';
                header.style.boxShadow = '0 2px 15px rgba(0, 0, 0, 0.1)';
            }
        });
    }
    
    // Project Filtering
    const filterButtons = document.querySelectorAll('.filter-btn');
    const projectCards = document.querySelectorAll('.project-card');
    
    if (filterButtons.length > 0 && projectCards.length > 0) {
        filterButtons.forEach(button => {
            button.addEventListener('click', function() {
                // Remove active class from all buttons
                filterButtons.forEach(btn => {
                    btn.classList.remove('active');
                });
                
                // Add active class to clicked button
                this.classList.add('active');
                
                // Get filter value
                const filterValue = this.getAttribute('data-filter');
                
                // Filter projects
                projectCards.forEach(card => {
                    if (filterValue === 'all') {
                        card.style.display = 'block';
                    } else {
                        if (card.getAttribute('data-category') === filterValue) {
                            card.style.display = 'block';
                        } else {
                            card.style.display = 'none';
                        }
                    }
                });
            });
        });
    }
    
    // Form validation
    const contactForm = document.querySelector('.contact-form');
    
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            let isValid = true;
            const formElements = contactForm.elements;
            
            // Basic validation
            for (let i = 0; i < formElements.length; i++) {
                const element = formElements[i];
                
                if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA') {
                    if (element.required && !element.value.trim()) {
                        isValid = false;
                        element.classList.add('error');
                    } else {
                        element.classList.remove('error');
                    }
                    
                    // Email validation
                    if (element.type === 'email' && element.value.trim()) {
                        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        if (!emailRegex.test(element.value.trim())) {
                            isValid = false;
                            element.classList.add('error');
                        }
                    }
                }
            }
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields correctly.');
            }
        });
    }
    
    // Newsletter subscription form
    const subscribeForm = document.querySelector('.subscribe-form');
    
    if (subscribeForm) {
        subscribeForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const emailInput = this.querySelector('input[type="email"]');
            const emailValue = emailInput.value.trim();
            
            if (!emailValue) {
                alert('Please enter your email address.');
                return;
            }
            
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(emailValue)) {
                alert('Please enter a valid email address.');
                return;
            }
            
            // In a real implementation, you would send this to your backend
            alert('Thank you for subscribing! You will receive updates soon.');
            this.reset();
        });
    }
    
    // Comment form submission
    const commentForm = document.querySelector('.comment-form');
    
    if (commentForm) {
        commentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const nameInput = this.querySelector('#commentName');
            const emailInput = this.querySelector('#commentEmail');
            const contentInput = this.querySelector('#commentContent');
            
            if (!nameInput.value.trim() || !emailInput.value.trim() || !contentInput.value.trim()) {
                alert('Please fill in all fields.');
                return;
            }
            
            // In a real implementation, you would send this to your backend
            alert('Thank you for your comment! It will be visible after moderation.');
            this.reset();
        });
    }
});