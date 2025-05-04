// Load Firebase config
const firebaseConfig = {
    apiKey: "AIzaSyA0OyZke_Y3WStxamgCgM17R5Q4f8ewO6o",
    authDomain: "portfolio-spks.firebaseapp.com",
    databaseURL: "https://portfolio-spks-default-rtdb.firebaseio.com",
    projectId: "portfolio-spks",
    storageBucket: "portfolio-spks.firebasestorage.app",
    messagingSenderId: "106673725991",
    appId: "1:106673725991:web:a1b25d6a49e996c9eba402",
    measurementId: "G-XD97FQEBQH"
};

document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    // Initialize Firebase
    let firebaseInitialized = false;
    try {
        if (firebase && firebase.apps.length === 0) {
            firebase.initializeApp(firebaseConfig);
            console.log('Firebase initialized successfully');
            firebaseInitialized = true;
        } else if (firebase.apps.length > 0) {
            firebaseInitialized = true;
        }
    } catch (error) {
        console.error('Error initializing Firebase:', error);
    }

    // Check if we're on a blog post page and need to load related posts
    const relatedPostsContainer = document.getElementById('relatedPosts');
    const commentsListContainer = document.getElementById('commentsList');
    
    // Function to load related posts
    async function loadRelatedPosts() {
        if (!firebaseInitialized || !relatedPostsContainer || !window.currentPostId) {
            return;
        }

        try {
            // Get Firebase database reference
            const db = firebase.database();
            const postsRef = db.ref('blog_posts');
            
            // Get current post category (we'll use it to find related posts)
            const currentPostSnapshot = await postsRef.child(window.currentPostId).once('value');
            const currentPost = currentPostSnapshot.val();
            
            if (!currentPost) {
                relatedPostsContainer.innerHTML = '<div class="empty-state"><p>No related posts found.</p></div>';
                return;
            }
            
            // Find posts with the same category, limited to 3
            let relatedPosts = [];
            const allPostsSnapshot = await postsRef.orderByChild('category').equalTo(currentPost.category).limitToLast(4).once('value');
            
            allPostsSnapshot.forEach(postSnapshot => {
                const post = postSnapshot.val();
                const postId = postSnapshot.key;
                
                // Don't include the current post in related posts
                if (postId !== window.currentPostId) {
                    relatedPosts.push({
                        id: postId,
                        title: post.title,
                        excerpt: post.content ? (post.content.substr(0, 100) + '...') : '',
                        published_date: post.published_date,
                        category: post.category
                    });
                }
            });
            
            // Display related posts
            if (relatedPosts.length === 0) {
                relatedPostsContainer.innerHTML = '<div class="empty-state"><p>No related posts found.</p></div>';
                return;
            }
            
            let relatedPostsHTML = '';
            relatedPosts.slice(0, 3).forEach(post => {
                relatedPostsHTML += `
                <div class="related-post-card">
                    <div class="related-post-img">
                        <svg class="related-post-svg" viewBox="0 0 400 225" xmlns="http://www.w3.org/2000/svg">
                            <rect width="400" height="225" fill="#f0f0f0"/>
                            <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="Arial" font-size="18" fill="#555">Related Post</text>
                        </svg>
                    </div>
                    <div class="related-post-content">
                        <div class="related-post-meta">
                            <span class="related-post-date">${new Date(post.published_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</span>
                        </div>
                        <h3 class="related-post-title">${post.title}</h3>
                        <a href="blog-post.php?id=${post.id}" class="read-more">Read More</a>
                    </div>
                </div>
                `;
            });
            
            relatedPostsContainer.innerHTML = relatedPostsHTML;
        } catch (error) {
            console.error('Error loading related posts:', error);
            relatedPostsContainer.innerHTML = '<div class="empty-state"><p>Error loading related posts. Please try again later.</p></div>';
        }
    }
    
    // Function to load and display comments
    async function loadComments() {
        if (!firebaseInitialized || !commentsListContainer || !window.currentPostId) {
            return;
        }

        try {
            // Get Firebase database reference
            const db = firebase.database();
            const commentsRef = db.ref(`comments/${window.currentPostId}`);
            
            // Get comments ordered by timestamp
            const commentsSnapshot = await commentsRef.orderByChild('timestamp').once('value');
            
            const comments = [];
            commentsSnapshot.forEach(commentSnapshot => {
                const comment = commentSnapshot.val();
                comments.push({
                    id: commentSnapshot.key,
                    name: comment.name,
                    content: comment.content,
                    timestamp: comment.timestamp
                });
            });
            
            // Display comments
            if (comments.length === 0) {
                commentsListContainer.innerHTML = '<div class="no-comments"><p>No comments yet. Be the first to comment!</p></div>';
                return;
            }
            
            let commentsHTML = '';
            comments.forEach(comment => {
                const date = new Date(comment.timestamp);
                commentsHTML += `
                <div class="comment-item">
                    <div class="comment-avatar">
                        <svg viewBox="0 0 50 50" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="25" cy="25" r="25" fill="#e0e0e0"/>
                            <circle cx="25" cy="20" r="8" fill="#c0c0c0"/>
                            <path d="M25,50 C34,50 41,45 41,35 C41,28 34,30 25,30 C16,30 9,28 9,35 C9,45 16,50 25,50 Z" fill="#c0c0c0"/>
                        </svg>
                    </div>
                    <div class="comment-content">
                        <div class="comment-header">
                            <h4 class="comment-author">${comment.name}</h4>
                            <span class="comment-date">${date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</span>
                        </div>
                        <div class="comment-body">
                            <p>${comment.content}</p>
                        </div>
                    </div>
                </div>
                `;
            });
            
            commentsListContainer.innerHTML = commentsHTML;
        } catch (error) {
            console.error('Error loading comments:', error);
            commentsListContainer.innerHTML = '<div class="empty-state"><p>Error loading comments. Please try again later.</p></div>';
        }
    }
    
    // Handle comment form submission
    const commentForm = document.querySelector('.comment-form');
    if (commentForm && firebaseInitialized && window.currentPostId) {
        commentForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const nameInput = this.querySelector('#commentName');
            const emailInput = this.querySelector('#commentEmail');
            const contentInput = this.querySelector('#commentContent');
            
            if (!nameInput.value.trim() || !emailInput.value.trim() || !contentInput.value.trim()) {
                alert('Please fill in all fields.');
                return;
            }
            
            try {
                // Get Firebase database reference
                const db = firebase.database();
                const commentsRef = db.ref(`comments/${window.currentPostId}`);
                
                // Create new comment
                const newComment = {
                    name: nameInput.value.trim(),
                    email: emailInput.value.trim(), // Storing email but not displaying it publicly
                    content: contentInput.value.trim(),
                    timestamp: Date.now()
                };
                
                // Push to Firebase
                await commentsRef.push(newComment);
                
                // Reset form
                commentForm.reset();
                
                // Reload comments
                loadComments();
                
                alert('Thank you for your comment!');
            } catch (error) {
                console.error('Error submitting comment:', error);
                alert('Error submitting comment. Please try again later.');
            }
        });
    }
    
    // If on blog post page, load related posts and comments
    if (window.currentPostId) {
        if (relatedPostsContainer) {
            loadRelatedPosts();
        }
        
        if (commentsListContainer) {
            loadComments();
        }
    }
    
    // If on blog list or homepage, nothing special to do as PHP handles loading posts
});
