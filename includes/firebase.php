<?php
// Log file inclusion
error_log('firebase.php included successfully');

// Disable error display, keep logging
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../vendor/autoload.php';

use Kreait\Firebase\Factory;
use GuzzleHttp\Exception\RequestException;

// Create Firebase Factory instance
function firebaseFactory() {
    error_log('Attempting to initialize Firebase factory');
    $credentials = [
        "type" => "service_account",
        "project_id" => "portfolio-spks",
        "private_key_id" => "7a4fe1a9cb35d67e113670aae905b632011f8bbf",
        "private_key" => "-----BEGIN PRIVATE KEY-----\nMIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQCs5Np5rCRtsc0E\nfe2hXUhPrqnqqqYpH1avzXEVJB4ZkOQvtItCslR8tihRgZoIFBYEwEPO4lKIZrn/\n6cnLxP7WJTGDmsLNGlT4WxkRF8+XgmWuvFedWQkwBZWiJpX8+jkLBMiRDUGqDHY8\nv4vV7J3DpqqXtK9q9sEMg0Gf3MOgIvX/qnPbtlxVeHDnYAzZTWZhy2/ro5ZcDL7K\neH1ktpGZERoP2ibHkau9acecQrI/LuJRjEs5ZebxFeHdg9HKUm37OWj/NILfc9yj\nJmTAWIs+JO2ORH1izs0+UBgJ5+bmOZcyac1nMnknd06FG8NzMxrmDouwyuxkvwGg\naEbARYN3AgMBAAECggEAPmHSnI0qlbPOG/1N5OeyBCIq5+OL0ZGfpw4S68Rc4e+g\nDcBwcO/EQT0+fe4rxBdvPGac8MRDcDjUisxxhcb+BglkfK419Gg4WWYRPNQhEHM5\nuhr15+2svXl+XIPcoWstctbWgVPNqgwWC2Q3kcfuwp3btqI4mvkOfbPMVWCD6z6q\nOSywYDvh1u86oItl5gqF/PGvfM4Au1d/xtitB5oe6pz3pOOmHJJSVoX0o/lFZhcW\nMqa/EWBc2sUFgp+RBl9TqaP39imLTOZm6fAjl8BGsSxRQBiAgo6ng+nHvWJgeumD\nQ4ijFo9NZO0T9X0C69+WB9wOnoJ3Y61kGYJncms3MQKBgQDWJmjPzwWUIrTByUSy\n2+hG4dMGSGNvNqoAZ4oFHzkz69h/XaTY8vew/b8hETp4BRWMkBu4idQg3UYZ2i2L\nNeOhTDNi/ghKwH/zYNqgUA1AR1n0kZchjaRtL0eZTbwgSr66ik0fU4ISd4735pvo\nUkcTZ+As3s+uXfIIynTtMqFBRwKBgQDOrneTdvvs5Tt62f0TriHjFyfEUUOytRKu\n5r7bm+WVW7kpaF2OOM2s2zzRVuvpwJ/jyVJfAupE+A9qC81d7VEhUpw8H3r8MCXc\nCV2/y6FRXkTVEoFdmMEDu5fQqPQPhKcyzLdbYSEpQHa2yA+umhwMsggrrqvLvS91\nrwfWt09EUQKBgQCQQJtFsFw/pwk/qEYgfUV/ycqOZuCkH5xwXU55mMi9ktbpJLlQ\ncooqrSK8MZDDHBmh78jci+tan/MCoxceuRQ2qM/MlPYc9IK7/LgIWSQz8lxEBHZb\nYcAq1DhUqipZTkAgA91jhqsNqX+iubK30gSSq9w0HXqkKCBLj6Unyn0ZLQKBgQCL\nxTDYaP61ldBDpGehh4EjMeWSveIqWInp/eHUYflAqGbvucSLCZ8N5rWaXE3uJnX6\ndYte9Xm4aokCDjkz6+mRv2wovKFPKsBvrWXY7ryJalbiQUF7KnJdM9/XHGrwk6T+\nlbLp+SWT4CRoN6NjUJTngP5FjDph7e7KhzOl4RGaAQKBgAGQFpULLbG25h7thJL7\nIkPo0zHMqMAiJg3+xaLOMAg0DYoGjMvqk77sGwdwP/jfiljgD2mPmDDdIy2fOwrF\n6OuNDkLxo8aQOPlmC8aOdVtkaJxsgfaK997CvIfI3Y6o+uXO0UMeWthKrDkfTYeb\n8ndk+jYkWEC55RoBJz1CTFRr\n-----END PRIVATE KEY-----\n",
        "client_email" => "firebase-adminsdk-fbsvc@portfolio-spks.iam.gserviceaccount.com",
        "client_id" => "115872168691184886939",
        "auth_uri" => "https://accounts.google.com/o/oauth2/auth",
        "token_uri" => "https://oauth2.googleapis.com/token",
        "auth_provider_x509_cert_url" => "https://www.googleapis.com/oauth2/v1/certs",
        "client_x509_cert_url" => "https://www.googleapis.com/robot/v1/metadata/x509/firebase-adminsdk-fbsvc%40portfolio-spks.iam.gserviceaccount.com",
        "universe_domain" => "googleapis.com"
    ];

    try {
        $factory = (new Factory())
            ->withServiceAccount($credentials)
            ->withDatabaseUri('https://portfolio-spks-default-rtdb.firebaseio.com')
            ->withDefaultStorageBucket('portfolio-spks.firebasestorage.app');
        error_log('Firebase factory initialized successfully with bucket: portfolio-spks.firebasestorage.app');
        return $factory;
    } catch (Exception $e) {
        error_log('Firebase factory initialization error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        return (new Factory())->withDatabaseUri('https://portfolio-spks-default-rtdb.firebaseio.com');
    }
}

// Initialize Realtime Database
function initializeFirebase() {
    error_log('Attempting to initialize Firebase Realtime Database');
    try {
        $database = firebaseFactory()->createDatabase();
        error_log('Firebase Realtime Database initialized successfully');
        return $database;
    } catch (Exception $e) {
        error_log('Firebase initialization error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        return null;
    }
}

// Initialize Firebase Storage
function initializeFirebaseStorage() {
    error_log('Attempting to initialize Firebase Storage');
    try {
        $bucket = firebaseFactory()->createStorage()->getBucket('portfolio-spks.firebasestorage.app');
        error_log('Firebase Storage initialized successfully for bucket: portfolio-spks.firebasestorage.app');
        return $bucket;
    } catch (Exception $e) {
        error_log('Firebase Storage initialization error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        return null;
    }
}

// Upload image to Firebase Storage
function uploadImageToFirebase($localFilePath, $firebasePath) {
    error_log('Starting uploadImageToFirebase with file: ' . $localFilePath . ', path: ' . $firebasePath);
    try {
        // Validate file path
        if (empty($localFilePath)) {
            error_log('Image upload error: File path is empty');
            return null;
        }

        // Check if file exists and is readable
        if (!file_exists($localFilePath) || !is_readable($localFilePath)) {
            error_log('Image upload error: File does not exist or is not readable: ' . $localFilePath);
            return null;
        }

        // Check file size (<10MB)
        $fileSize = filesize($localFilePath);
        error_log('File size: ' . $fileSize . ' bytes');
        if ($fileSize > 10 * 1024 * 1024) {
            error_log('Image upload error: File too large: ' . $fileSize . ' bytes');
            return null;
        }

        // Check file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $fileType = mime_content_type($localFilePath);
        error_log('File type: ' . $fileType);
        if (!in_array($fileType, $allowedTypes)) {
            error_log('Image upload error: Invalid file type: ' . $fileType);
            return null;
        }

        // Initialize Storage
        $bucket = initializeFirebaseStorage();
        if (!$bucket) {
            error_log('Image upload error: Failed to initialize Firebase Storage');
            return null;
        }

        // Open file
        error_log('Attempting to open file: ' . $localFilePath);
        $file = fopen($localFilePath, 'r');
        if (!$file) {
            error_log('Image upload error: Failed to open file: ' . $localFilePath);
            return null;
        }

        // Upload file
        error_log('Uploading file to Firebase Storage: ' . $firebasePath);
        try {
            $object = $bucket->upload($file, [
                'name' => $firebasePath,
                'metadata' => [
                    'contentType' => $fileType
                ]
            ]);
        } catch (RequestException $e) {
            error_log('Image upload error: HTTP request failed - Status: ' . ($e->getResponse() ? $e->getResponse()->getStatusCode() : 'N/A') . ', Reason: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            fclose($file);
            return null;
        } catch (Exception $e) {
            error_log('Image upload error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            fclose($file);
            return null;
        }

        fclose($file);
        error_log('File closed successfully');

        // Generate signed URL
        error_log('Generating signed URL');
        $url = $object->signedUrl(new \DateTime('+10 years'));
        error_log('Image uploaded successfully to: ' . $url);
        return $url;
    } catch (Exception $e) {
        error_log('Image upload error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        if (isset($file) && is_resource($file)) {
            fclose($file);
        }
        return null;
    }
}

// Get blog posts from Firebase
function getBlogPosts($limit = null) {
    error_log('Attempting to retrieve blog posts');
    try {
        $database = initializeFirebase();
        if (!$database) {
            error_log('Failed to initialize Firebase for blog posts');
            return [];
        }

        $reference = $database->getReference('blog_posts');
        $snapshot = $reference->getSnapshot();

        if (!$snapshot->exists()) {
            error_log('No blog posts found in Firebase');
            return [];
        }

        $posts = [];
        foreach ($snapshot->getValue() as $key => $post) {
            if (isset($post['published_date'])) {
                $post['id'] = $key;
                $posts[] = $post;
            }
        }

        // Sort by published_date descending
        usort($posts, function($a, $b) {
            return strtotime($b['published_date']) - strtotime($a['published_date']);
        });

        if ($limit && count($posts) > $limit) {
            $posts = array_slice($posts, 0, $limit);
        }

        error_log('Retrieved ' . count($posts) . ' blog posts from Firebase');
        return $posts;
    } catch (Exception $e) {
        error_log('Error retrieving blog posts: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        return [];
    }
}

// Get a single blog post
function getBlogPost($postId) {
    error_log('Attempting to retrieve blog post: ' . $postId);
    try {
        $database = initializeFirebase();
        if (!$database) {
            error_log('Failed to initialize Firebase for single blog post');
            return null;
        }

        $reference = $database->getReference('blog_posts/' . $postId);
        $snapshot = $reference->getSnapshot();

        if (!$snapshot->exists()) {
            error_log('Blog post not found: ' . $postId);
            return null;
        }

        $post = $snapshot->getValue();
        $post['id'] = $postId;

        error_log('Retrieved blog post: ' . $postId);
        return $post;
    } catch (Exception $e) {
        error_log('Error retrieving blog post: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        return null;
    }
}
?>