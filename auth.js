/**
 * Simple Password Protection with Custom Dark Mode Modal
 * Stores password in localStorage - remembers user on same browser
 */

(function() {
    'use strict';
    
    // Set your password here (change this!)
    const CORRECT_PASSWORD = 'podcast2025';
    
    // Debug: Log that auth.js is loading
    console.log('🔒 Password protection active');
    
    // Check if already authenticated
    const storedPassword = localStorage.getItem('podcast_auth');
    
    if (storedPassword === CORRECT_PASSWORD) {
        // Already authenticated, allow access
        console.log('✅ Already authenticated');
        return;
    }
    
    console.log('❌ Not authenticated - showing modal');
    
    // Not authenticated, show custom modal
    let attempts = 0;
    const maxAttempts = 3;
    
    // Load Font Awesome if not already loaded
    function loadFontAwesome() {
        if (!document.querySelector('link[href*="font-awesome"]')) {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css';
            document.head.appendChild(link);
        }
    }
    
    // Create and inject modal HTML
    function createAuthModal() {
        console.log('Creating auth modal...');
        loadFontAwesome();
        
        const modalHTML = `
            <div id="authModal" style="
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                right: 0 !important;
                bottom: 0 !important;
                background: rgba(13, 17, 23, 0.98) !important;
                backdrop-filter: blur(8px) !important;
                z-index: 999999 !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif !important;
                animation: fadeIn 0.3s ease !important;
                visibility: visible !important;
                opacity: 1 !important;
            ">
                <style>
                    @keyframes fadeIn {
                        from { opacity: 0; }
                        to { opacity: 1; }
                    }
                    @keyframes slideUp {
                        from { transform: translateY(20px); opacity: 0; }
                        to { transform: translateY(0); opacity: 1; }
                    }
                    @keyframes shake {
                        0%, 100% { transform: translateX(0); }
                        25% { transform: translateX(-10px); }
                        75% { transform: translateX(10px); }
                    }
                </style>
                <div style="
                    background: #161b22 !important;
                    border: 1px solid #30363d !important;
                    border-radius: 12px !important;
                    padding: 2.5rem !important;
                    max-width: 420px !important;
                    width: 90% !important;
                    box-shadow: 0 16px 32px rgba(0, 0, 0, 0.4) !important;
                    animation: slideUp 0.3s ease !important;
                    position: relative !important;
                    z-index: 1000000 !important;
                ">
                    <div style="text-align: center; margin-bottom: 2rem;">
                        <div style="
                            font-size: 3.5rem;
                            margin-bottom: 1rem;
                            color: #238636;
                        ">
                            🎧
                        </div>
                        <h1 style="
                            font-family: 'Oswald', sans-serif;
                            font-size: 1.75rem;
                            font-weight: 700;
                            color: #f0f6fc;
                            margin: 0 0 0.5rem 0;
                            text-transform: uppercase;
                            letter-spacing: 1px;
                        ">PodFeed Builder</h1>
                        <p style="
                            color: #8b949e;
                            font-size: 0.875rem;
                            margin: 0;
                        ">Enter password to access</p>
                    </div>
                    
                    <div id="authError" style="
                        display: none;
                        background: rgba(218, 54, 51, 0.1);
                        border: 1px solid rgba(218, 54, 51, 0.3);
                        border-radius: 6px;
                        padding: 0.75rem 1rem;
                        margin-bottom: 1.5rem;
                        color: #f85149;
                        font-size: 0.875rem;
                        text-align: center;
                    "></div>
                    
                    <form id="authForm" style="margin-bottom: 1.5rem;">
                        <div style="margin-bottom: 1rem;">
                            <label style="
                                display: block;
                                margin-bottom: 0.5rem;
                                font-weight: 500;
                                color: #f0f6fc;
                                font-size: 0.875rem;
                            ">Password</label>
                            <div style="position: relative;">
                                <input 
                                    type="password" 
                                    id="authPassword" 
                                    autocomplete="current-password"
                                    style="
                                        display: block;
                                        width: 100%;
                                        padding: 0.75rem 3rem 0.75rem 1rem;
                                        font-size: 1rem;
                                        line-height: 1.5;
                                        color: #f0f6fc;
                                        background-color: #0d1117;
                                        border: 1px solid #30363d;
                                        border-radius: 6px;
                                        transition: all 0.2s ease;
                                        box-sizing: border-box;
                                    " 
                                    placeholder="Enter password"
                                    required
                                />
                                <button 
                                    type="button"
                                    id="togglePassword"
                                    style="
                                        position: absolute;
                                        right: 0.75rem;
                                        top: 50%;
                                        transform: translateY(-50%);
                                        background: none;
                                        border: none;
                                        color: #8b949e;
                                        cursor: pointer;
                                        padding: 0.25rem;
                                        font-size: 1.125rem;
                                        transition: color 0.2s ease;
                                        line-height: 1;
                                    "
                                    title="Toggle password visibility"
                                >👁️</button>
                            </div>
                        </div>
                        
                        <button 
                            type="submit" 
                            id="authSubmit"
                            style="
                                width: 100%;
                                padding: 0.75rem 1.5rem;
                                font-size: 0.875rem;
                                font-weight: 500;
                                color: white;
                                background-color: #238636;
                                border: none;
                                border-radius: 6px;
                                cursor: pointer;
                                transition: all 0.2s ease;
                                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
                            "
                        >
                            🔓 Unlock
                        </button>
                    </form>
                    
                    <div style="
                        text-align: center;
                        color: #656d76;
                        font-size: 0.75rem;
                    ">
                        <span id="attemptsRemaining">Attempts remaining: ${maxAttempts}</span>
                    </div>
                </div>
            </div>
        `;
        
        console.log('Inserting modal into body...');
        document.body.insertAdjacentHTML('afterbegin', modalHTML);
        console.log('Modal inserted, checking if element exists:', document.getElementById('authModal'));
        
        // Add hover effect to button
        const submitBtn = document.getElementById('authSubmit');
        submitBtn.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#2ea043';
            this.style.boxShadow = '0 4px 8px rgba(0, 0, 0, 0.3)';
            this.style.transform = 'translateY(-1px)';
        });
        submitBtn.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '#238636';
            this.style.boxShadow = '0 2px 4px rgba(0, 0, 0, 0.2)';
            this.style.transform = 'translateY(0)';
        });
        
        // Add focus effect to input
        const passwordInput = document.getElementById('authPassword');
        passwordInput.addEventListener('focus', function() {
            this.style.borderColor = '#3d444d';
            this.style.outline = 'none';
        });
        passwordInput.addEventListener('blur', function() {
            this.style.borderColor = '#30363d';
        });
        
        // Focus the password input
        setTimeout(() => passwordInput.focus(), 100);
        
        // Handle password visibility toggle
        const togglePasswordBtn = document.getElementById('togglePassword');
        togglePasswordBtn.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Change icon based on state
            this.textContent = type === 'password' ? '👁️' : '🙈';
            this.style.color = type === 'password' ? '#8b949e' : '#f0f6fc';
        });
        
        // Hover effect for toggle button
        togglePasswordBtn.addEventListener('mouseenter', function() {
            this.style.color = '#f0f6fc';
        });
        togglePasswordBtn.addEventListener('mouseleave', function() {
            const type = passwordInput.getAttribute('type');
            this.style.color = type === 'password' ? '#8b949e' : '#f0f6fc';
        });
        
        // Handle form submission
        document.getElementById('authForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const password = document.getElementById('authPassword').value;
            
            if (password === CORRECT_PASSWORD) {
                // Correct password - save to localStorage
                localStorage.setItem('podcast_auth', password);
                
                // Remove modal with fade out
                const modal = document.getElementById('authModal');
                modal.style.animation = 'fadeOut 0.3s ease';
                modal.style.opacity = '0';
                
                setTimeout(() => {
                    modal.remove();
                    document.body.style.visibility = 'visible';
                }, 300);
            } else {
                attempts++;
                const remaining = maxAttempts - attempts;
                
                if (remaining > 0) {
                    showError(`Incorrect password. ${remaining} attempt${remaining !== 1 ? 's' : ''} remaining.`);
                    document.getElementById('attemptsRemaining').textContent = `Attempts remaining: ${remaining}`;
                } else {
                    showAccessDenied('Too many failed attempts.', true);
                }
            }
        });
    }
    
    function showError(message) {
        const errorDiv = document.getElementById('authError');
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
        errorDiv.style.animation = 'shake 0.4s ease';
        
        const passwordInput = document.getElementById('authPassword');
        passwordInput.style.borderColor = '#f85149';
        passwordInput.value = '';
        passwordInput.focus();
        
        setTimeout(() => {
            passwordInput.style.borderColor = '#30363d';
        }, 2000);
    }
    
    function showAccessDenied(message, showRetry = false) {
        const modal = document.getElementById('authModal');
        modal.innerHTML = `
            <div style="
                background: #161b22;
                border: 1px solid #30363d;
                border-radius: 12px;
                padding: 3rem 2.5rem;
                max-width: 420px;
                width: 90%;
                box-shadow: 0 16px 32px rgba(0, 0, 0, 0.4);
                text-align: center;
            ">
                <div style="
                    font-size: 4rem;
                    margin-bottom: 1.5rem;
                    color: #da3633;
                ">
                    🔒
                </div>
                <h2 style="
                    font-family: 'Oswald', sans-serif;
                    font-size: 1.5rem;
                    font-weight: 700;
                    color: #f0f6fc;
                    margin: 0 0 1rem 0;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                ">Access Denied</h2>
                <p style="
                    color: #8b949e;
                    font-size: 0.875rem;
                    margin: 0 0 ${showRetry ? '2rem' : '0'} 0;
                ">${message}</p>
                ${showRetry ? `
                    <button 
                        onclick="location.reload()" 
                        style="
                            padding: 0.75rem 1.5rem;
                            font-size: 0.875rem;
                            font-weight: 500;
                            color: white;
                            background-color: #238636;
                            border: none;
                            border-radius: 6px;
                            cursor: pointer;
                            transition: all 0.2s ease;
                            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
                        "
                    ">
                        🔄 Try Again
                    </button>
                ` : ''}
            </div>
        `;
    }
    
    // Wait for DOM to be ready before doing anything
    function init() {
        // Hide page content immediately
        document.body.style.visibility = 'hidden';
        
        // Create the modal
        createAuthModal();
    }
    
    // Run when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    // Add fadeOut animation
    const style = document.createElement('style');
    style.textContent = '@keyframes fadeOut { from { opacity: 1; } to { opacity: 0; } }';
    document.head.appendChild(style);
})();
