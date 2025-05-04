document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // Toggle sidebar on mobile
    const mobileToggle = document.querySelector('.mobile-toggle');
    const adminSidebar = document.querySelector('.admin-sidebar');
    const sidebarClose = document.querySelector('.sidebar-close');
    
    if (mobileToggle && adminSidebar) {
        mobileToggle.addEventListener('click', function() {
            adminSidebar.classList.add('active');
        });
    }
    
    if (sidebarClose && adminSidebar) {
        sidebarClose.addEventListener('click', function() {
            adminSidebar.classList.remove('active');
        });
    }
    
    // Close sidebar when clicking outside
    document.addEventListener('click', function(event) {
        if (adminSidebar && adminSidebar.classList.contains('active') && 
            !adminSidebar.contains(event.target) && 
            !mobileToggle.contains(event.target)) {
            adminSidebar.classList.remove('active');
        }
    });
    
    // User dropdown toggle
    const userDropdownToggle = document.querySelector('.user-dropdown-toggle');
    const userDropdownMenu = document.querySelector('.user-dropdown-menu');
    
    if (userDropdownToggle && userDropdownMenu) {
        userDropdownToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdownMenu.classList.toggle('show');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            if (userDropdownMenu.classList.contains('show') && 
                !userDropdownMenu.contains(event.target) && 
                !userDropdownToggle.contains(event.target)) {
                userDropdownMenu.classList.remove('show');
            }
        });
    }
    
    // Handle form validation
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            let isValid = true;
            
            // Check required fields
            const requiredFields = form.querySelectorAll('[required]');
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('is-invalid');
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });
        
        // Remove validation errors on input
        const formFields = form.querySelectorAll('input, textarea, select');
        formFields.forEach(field => {
            field.addEventListener('input', function() {
                if (this.classList.contains('is-invalid') && this.value.trim()) {
                    this.classList.remove('is-invalid');
                }
            });
        });
    });

    // Delete post handler
    window.deletePost = async function(postId) {
        if (confirm('Are you sure you want to delete this post?')) {
            try {
                const response = await fetch('delete-post.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'post_id=' + encodeURIComponent(postId)
                });
                const data = await response.json();
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Error deleting post: ' + data.message);
                }
            } catch (error) {
                console.error('Error deleting post:', error);
                alert('Error deleting post');
            }
        }
    };
});