/**
 * Admin Authentication Manager
 * Handles client-side authentication, session management, and redirects
 */

class AdminAuth {
    constructor() {
        this.currentUser = null;
        this.sessionCheckInterval = null;
        this.authStatusCallbacks = [];
        
        this.init();
    }
    
    async init() {
        await this.checkSetupRequired();
        await this.checkAuthStatus();
        this.startSessionMonitoring();
    }
    
    /**
     * Check if initial setup is required
     */
    async checkSetupRequired() {
        try {
            const response = await fetch('../api/auth.php/check-setup');
            const data = await response.json();
            
            if (data.success && data.data.setup_required) {
                // Redirect to setup page
                window.location.href = 'setup.html';
                return;
            }
        } catch (error) {
            console.error('Setup check failed:', error);
            // Assume setup is required if check fails
            window.location.href = 'setup.html';
        }
    }
    
    /**
     * Check current authentication status
     */
    async checkAuthStatus() {
        try {
            const response = await fetch('../api/auth.php', {
                method: 'GET'
            });
            
            const data = await response.json();
            
            if (data.success && data.data.authenticated) {
                this.currentUser = {
                    username: data.data.username,
                    loginTime: new Date(data.data.login_time),
                    lastActivity: new Date(data.data.last_activity),
                    sessionExpires: new Date(data.data.session_expires)
                };
                
                this.notifyAuthStatusChange(true);
                this.updateSessionInfo();
            } else {
                this.handleUnauthenticated();
            }
        } catch (error) {
            console.error('Auth status check failed:', error);
            this.handleUnauthenticated();
        }
    }
    
    /**
     * Handle unauthenticated state
     */
    handleUnauthenticated() {
        this.currentUser = null;
        this.notifyAuthStatusChange(false);
        
        // Redirect to login page unless we're already there
        if (!window.location.pathname.includes('login.html')) {
            window.location.href = 'login.html';
        }
    }
    
    /**
     * Logout current user
     */
    async logout() {
        try {
            await fetch('../api/auth.php', {
                method: 'DELETE'
            });
        } catch (error) {
            console.error('Logout request failed:', error);
        }
        
        this.currentUser = null;
        this.stopSessionMonitoring();
        this.notifyAuthStatusChange(false);
        
        // Clear any stored auth data
        localStorage.removeItem('rememberLogin');
        
        // Redirect to login page
        window.location.href = 'login.html';
    }
    
    /**
     * Start monitoring session status
     */
    startSessionMonitoring() {
        // Check session every 5 minutes
        this.sessionCheckInterval = setInterval(() => {
            this.checkAuthStatus();
        }, 5 * 60 * 1000);
        
        // Also check when page becomes visible (user returns to tab)
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                this.checkAuthStatus();
            }
        });
    }
    
    /**
     * Stop session monitoring
     */
    stopSessionMonitoring() {
        if (this.sessionCheckInterval) {
            clearInterval(this.sessionCheckInterval);
            this.sessionCheckInterval = null;
        }
    }
    
    /**
     * Update session information display
     */
    updateSessionInfo() {
        if (!this.currentUser) return;
        
        // Update username display
        const usernameElements = document.querySelectorAll('.current-username');
        usernameElements.forEach(el => {
            el.textContent = this.currentUser.username;
        });
        
        // Update session expiration warning
        const now = new Date();
        const timeUntilExpiry = this.currentUser.sessionExpires - now;
        const minutesUntilExpiry = Math.floor(timeUntilExpiry / (1000 * 60));
        
        if (minutesUntilExpiry <= 10 && minutesUntilExpiry > 0) {
            this.showSessionWarning(minutesUntilExpiry);
        }
        
        // Update backend status indicator
        const statusElement = document.getElementById('backend-status');
        if (statusElement) {
            statusElement.textContent = 'Connected';
            statusElement.className = 'status-indicator connected';
        }
    }
    
    /**
     * Show session expiration warning
     */
    showSessionWarning(minutesRemaining) {
        // Remove any existing warning
        const existingWarning = document.querySelector('.session-warning');
        if (existingWarning) {
            existingWarning.remove();
        }
        
        // Create warning banner
        const warning = document.createElement('div');
        warning.className = 'session-warning';
        warning.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #fff3cd;
            color: #856404;
            padding: 10px;
            text-align: center;
            border-bottom: 1px solid #ffeaa7;
            z-index: 1000;
        `;
        
        warning.innerHTML = `
            <span>Your session will expire in ${minutesRemaining} minute(s). 
            <button onclick="window.adminAuth.refreshSession()" style="margin-left: 10px; padding: 5px 10px; background: #007cba; color: white; border: none; border-radius: 4px; cursor: pointer;">
                Extend Session
            </button>
            </span>
        `;
        
        document.body.insertBefore(warning, document.body.firstChild);
        
        // Auto-remove warning if session is extended
        setTimeout(() => {
            const warningElement = document.querySelector('.session-warning');
            if (warningElement) {
                warningElement.remove();
            }
        }, 5 * 60 * 1000); // Remove after 5 minutes
    }
    
    /**
     * Refresh session by making an authenticated request
     */
    async refreshSession() {
        await this.checkAuthStatus();
        
        // Remove session warning
        const warning = document.querySelector('.session-warning');
        if (warning) {
            warning.remove();
        }
    }
    
    /**
     * Register callback for authentication status changes
     */
    onAuthStatusChange(callback) {
        this.authStatusCallbacks.push(callback);
    }
    
    /**
     * Notify listeners of authentication status changes
     */
    notifyAuthStatusChange(isAuthenticated) {
        this.authStatusCallbacks.forEach(callback => {
            try {
                callback(isAuthenticated, this.currentUser);
            } catch (error) {
                console.error('Auth status callback error:', error);
            }
        });
    }
    
    /**
     * Get current authentication status
     */
    isAuthenticated() {
        return this.currentUser !== null;
    }
    
    /**
     * Get current user information
     */
    getCurrentUser() {
        return this.currentUser;
    }
    
    /**
     * Add CSRF token to requests (placeholder for future implementation)
     */
    addCSRFToken(requestData) {
        // TODO: Implement CSRF token handling
        return requestData;
    }
}

// Global authentication manager
window.adminAuth = new AdminAuth();

// Utility function to make authenticated requests
window.authenticatedFetch = async function(url, options = {}) {
    if (!window.adminAuth.isAuthenticated()) {
        throw new Error('Not authenticated');
    }
    
    // Add CSRF token if needed
    if (options.body && typeof options.body === 'object') {
        options.body = window.adminAuth.addCSRFToken(options.body);
    }
    
    const response = await fetch(url, options);
    
    // Check if response indicates authentication failure
    if (response.status === 401) {
        window.adminAuth.handleUnauthenticated();
        throw new Error('Authentication expired');
    }
    
    return response;
};

// Export for use in other scripts
window.AdminAuth = AdminAuth;