// Firebase configuration
const firebaseConfig = {
    apiKey: "AIzaSyA0OyZke_Y3WStxamgCgM17R5Q4f8ewO6o",
    authDomain: "portfolio-spks.firebaseapp.com",
    databaseURL: "https://portfolio-spks-default-rtdb.firebaseio.com",
    projectId: "portfolio-spks",
    storageBucket: "portfolio-spks.appspot.com",
    messagingSenderId: "106673725991",
    appId: "1:106673725991:web:a1b25d6a49e996c9eba402",
    measurementId: "G-XD97FQEBQH"
};

// Initialize Firebase
firebase.initializeApp(firebaseConfig);

// Initialize storage reference
const storage = firebase.storage();
const storageRef = storage.ref();

// Function to upload file and get URL
async function uploadFile(file, path) {
    try {
        const fileRef = storageRef.child(path);
        await fileRef.put(file);
        const url = await fileRef.getDownloadURL();
        return { success: true, url };
    } catch (error) {
        console.error('Error uploading file:', error);
        return { success: false, error };
    }
}

// Initialize Firebase
const initFirebase = () => {
    // Check if Firebase is loaded and not already initialized
    if (typeof firebase !== 'undefined' && firebase.apps.length === 0) {
        try {
            // Initialize Firebase app
            firebase.initializeApp(firebaseConfig);
            // Initialize Storage
            const storage = firebase.storage();
            console.log('Firebase initialized successfully');
            return true;
        } catch (error) {
            console.error('Error initializing Firebase:', error);
            return false;
        }
    } else if (typeof firebase === 'undefined') {
        console.error('Firebase SDK not loaded');
        return false;
    }
    return firebase.apps.length > 0;
};

// Export the initialization function
export { initFirebase };
