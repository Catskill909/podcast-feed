/**
 * Simple Password Protection
 * Stores password in localStorage - remembers user on same browser
 */

(function() {
    'use strict';
    
    // Set your password here (change this!)
    const CORRECT_PASSWORD = 'podcast2025';
    
    // Check if already authenticated
    const storedPassword = localStorage.getItem('podcast_auth');
    
    if (storedPassword === CORRECT_PASSWORD) {
        // Already authenticated, allow access
        return;
    }
    
    // Not authenticated, prompt for password
    let attempts = 0;
    const maxAttempts = 3;
    
    while (attempts < maxAttempts) {
        const password = prompt('Enter password to access Podcast Directory:');
        
        if (password === null) {
            // User clicked cancel
            document.body.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100vh;font-family:sans-serif;"><div style="text-align:center;"><h1>🔒 Access Denied</h1><p>Password required to access this page.</p></div></div>';
            throw new Error('Access denied');
        }
        
        if (password === CORRECT_PASSWORD) {
            // Correct password - save to localStorage
            localStorage.setItem('podcast_auth', password);
            return; // Allow access
        }
        
        attempts++;
        if (attempts < maxAttempts) {
            alert('Incorrect password. ' + (maxAttempts - attempts) + ' attempts remaining.');
        }
    }
    
    // Max attempts reached
    document.body.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100vh;font-family:sans-serif;"><div style="text-align:center;"><h1>🔒 Access Denied</h1><p>Too many failed attempts.</p><p><a href="javascript:location.reload()">Try Again</a></p></div></div>';
    throw new Error('Too many failed attempts');
})();
