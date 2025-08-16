/**
 * Main Application Entry Point
 * Initializes the Ansybl Site frontend application
 */

// Global application state
window.AnsyblApp = {
    initialized: false,
    ui: null,
    feedManager: null,
    activityRenderer: null,
    config: null
};

/**
 * Initialize the application
 */
async function initializeApp() {
    try {
        console.log('Initializing Ansybl Site...');
        
        // Initialize configuration
        if (typeof AnsyblConfig === 'undefined') {
            throw new Error('AnsyblConfig not loaded');
        }
        
        // Initialize components in order
        if (typeof FeedManager === 'undefined' || 
            typeof UIManager === 'undefined' || 
            typeof ActivityRenderer === 'undefined') {
            throw new Error('Required modules not loaded');
        }
        
        // Create component instances
        window.AnsyblApp.activityRenderer = new ActivityRenderer();
        window.AnsyblApp.feedManager = new FeedManager(AnsyblConfig.api.base);
        window.AnsyblApp.ui = new UIManager();
        
        // Set dependencies
        window.AnsyblApp.ui.feedManager = window.AnsyblApp.feedManager;
        window.AnsyblApp.ui.activityRenderer = window.AnsyblApp.activityRenderer;
        
        // Set up event listeners between FeedManager and UIManager
        window.AnsyblApp.feedManager.on('loadStart', () => {
            window.AnsyblApp.ui.isLoading = true;
            window.AnsyblApp.ui.showLoading();
        });
        
        window.AnsyblApp.feedManager.on('loadComplete', (items) => {
            window.AnsyblApp.ui.isLoading = false;
            window.AnsyblApp.ui.hideLoading();
            window.AnsyblApp.ui.renderContent();
        });
        
        window.AnsyblApp.feedManager.on('loadError', (error) => {
            window.AnsyblApp.ui.isLoading = false;
            window.AnsyblApp.ui.hideLoading();
            window.AnsyblApp.ui.showError('Failed to load feeds: ' + error.message);
        });
        
        // Load initial data
        await loadInitialData();
        
        window.AnsyblApp.initialized = true;
        console.log('Ansybl Site initialized successfully');

    } catch (error) {
        console.error('Failed to initialize Ansybl Site:', error);
        displayErrorMessage('Failed to load the application. Please refresh the page.');
    }
}

/**
 * Load initial application data
 */
async function loadInitialData() {
    try {
        // Load feeds and configuration
        await Promise.all([
            window.AnsyblApp.feedManager.loadFeedConfigs(),
            loadAppConfiguration()
        ]);
        
        // Fetch feed content
        await window.AnsyblApp.feedManager.fetchAllFeeds();
        
    } catch (error) {
        console.error('Error loading initial data:', error);
        throw error;
    }
}

/**
 * Load application configuration
 */
async function loadAppConfiguration() {
    try {
        const response = await fetch(AnsyblConfig.api.config);
        if (!response.ok) {
            throw new Error(`Config API error: ${response.status}`);
        }
        
        const data = await response.json();
        if (data.success && data.data) {
            // Apply configuration to the app
            window.AnsyblApp.config = data.data;
            
            // Apply theme if available
            if (data.data.theme) {
                applyTheme(data.data.theme);
            }
        }
    } catch (error) {
        console.warn('Could not load configuration:', error.message);
        // Continue with defaults
    }
}

/**
 * Apply theme configuration
 */
function applyTheme(theme) {
    try {
        if (theme.variables) {
            const root = document.documentElement;
            Object.entries(theme.variables).forEach(([key, value]) => {
                if (key.startsWith('--')) {
                    root.style.setProperty(key, value);
                }
            });
        }
        
        if (theme.customCSS) {
            const styleElement = document.createElement('style');
            styleElement.textContent = theme.customCSS;
            document.head.appendChild(styleElement);
        }
    } catch (error) {
        console.error('Error applying theme:', error);
    }
}

/**
 * Display error message to user
 */
function displayErrorMessage(message) {
    const errorContainer = document.getElementById('error-container');
    if (errorContainer) {
        errorContainer.innerHTML = `
            <div class="error-message">
                <h3>Application Error</h3>
                <p>${message}</p>
                <button onclick="location.reload()">Reload Page</button>
            </div>
        `;
        errorContainer.style.display = 'block';
    } else {
        // Fallback to alert if no error container
        alert(message);
    }
}

/**
 * Handle application errors
 */
window.addEventListener('error', (event) => {
    console.error('Application error:', event.error);
    
    if (!window.AnsyblApp.initialized) {
        displayErrorMessage('An error occurred while loading the application.');
    }
});

/**
 * Handle unhandled promise rejections
 */
window.addEventListener('unhandledrejection', (event) => {
    console.error('Unhandled promise rejection:', event.reason);
    
    if (!window.AnsyblApp.initialized) {
        displayErrorMessage('An error occurred while loading the application.');
    }
});

/**
 * DOM ready handler
 */
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeApp);
} else {
    // DOM is already loaded
    initializeApp();
}

/**
 * Service worker registration (if available)
 */
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then(registration => {
                console.log('Service Worker registered:', registration);
            })
            .catch(error => {
                console.log('Service Worker registration failed:', error);
            });
    });
}

/**
 * Expose global functions for debugging
 */
window.AnsyblDebug = {
    getApp: () => window.AnsyblApp,
    reloadFeeds: () => window.AnsyblApp.feedManager?.fetchAllFeeds(),
    clearCache: () => window.AnsyblApp.feedManager?.clearCache(),
    getState: () => ({
        initialized: window.AnsyblApp.initialized,
        feeds: window.AnsyblApp.feedManager?.feeds,
        config: window.AnsyblApp.config
    })
};
