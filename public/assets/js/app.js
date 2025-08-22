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
    menuRenderer: null,
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
            typeof ActivityRenderer === 'undefined' ||
            typeof MenuRenderer === 'undefined') {
            throw new Error('Required modules not loaded');
        }
        
        // Create component instances
        window.AnsyblApp.activityRenderer = new ActivityRenderer();
        window.AnsyblApp.feedManager = new FeedManager(AnsyblConfig.api.base);
        window.AnsyblApp.ui = new UIManager();
        window.AnsyblApp.menuRenderer = window.menuRenderer; // Use global instance
        
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
        
        // Set up menu integration events
        setupMenuIntegration();
        
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
 * Setup menu integration with feed filtering
 */
function setupMenuIntegration() {
    // Listen for menu-triggered feed filtering
    document.addEventListener('menuFilterFeed', (event) => {
        const { feedId } = event.detail;
        
        if (window.AnsyblApp.ui && window.AnsyblApp.feedManager) {
            // Update feed filter in UI
            const feedFilter = document.getElementById('feed-filter');
            if (feedFilter) {
                feedFilter.value = feedId;
                feedFilter.dispatchEvent(new Event('change'));
            }
            
            // Filter content by feed
            window.AnsyblApp.ui.filterByFeed(feedId);
            
            // Update URL hash for deep linking
            if (feedId !== 'all') {
                window.location.hash = `feed-${feedId}`;
            } else {
                window.location.hash = '';
            }
        }
    });
    
    // Listen for show all feeds command
    document.addEventListener('menuShowAllFeeds', () => {
        if (window.AnsyblApp.ui) {
            const feedFilter = document.getElementById('feed-filter');
            if (feedFilter) {
                feedFilter.value = 'all';
                feedFilter.dispatchEvent(new Event('change'));
            }
            
            window.AnsyblApp.ui.showAllFeeds();
            window.location.hash = '';
        }
    });
    
    // Listen for refresh content command
    document.addEventListener('refreshContent', () => {
        if (window.AnsyblApp.feedManager) {
            window.AnsyblApp.feedManager.fetchAllFeeds();
        }
    });
    
    // Listen for configuration updates
    document.addEventListener('configUpdated', (event) => {
        if (event.detail.type === 'menu' && window.AnsyblApp.menuRenderer) {
            window.AnsyblApp.menuRenderer.handleMenuConfigUpdate(event.detail.config);
        }
    });
    
    // Handle deep linking on page load
    handleDeepLinking();
    
    // Listen for hash changes
    window.addEventListener('hashchange', handleDeepLinking);
}

/**
 * Handle deep linking for feed filtering
 */
function handleDeepLinking() {
    const hash = window.location.hash.substring(1); // Remove #
    
    if (hash.startsWith('feed-')) {
        const feedId = hash.replace('feed-', '');
        
        // Convert menu feed ID format to feedManager format
        let actualFeedId = feedId;
        if (feedId.includes(':')) {
            const [type, id] = feedId.split(':');
            if (type === 'local') {
                // Local feeds are stored as "local-{id}" in feedManager
                actualFeedId = `local-${id}`;
            } else if (type === 'external') {
                // External feeds use just the ID
                actualFeedId = id;
            }
        }
        
        // Wait for app to be ready
        if (window.AnsyblApp.initialized && window.AnsyblApp.ui) {
            window.AnsyblApp.ui.filterByFeed(actualFeedId);
            
            if (window.AnsyblApp.menuRenderer) {
                window.AnsyblApp.menuRenderer.updateActiveMenuItem(feedId);
            }
        } else {
            // Wait for initialization
            const checkInitialized = setInterval(() => {
                if (window.AnsyblApp.initialized && window.AnsyblApp.ui) {
                    clearInterval(checkInitialized);
                    window.AnsyblApp.ui.filterByFeed(actualFeedId);
                    
                    if (window.AnsyblApp.menuRenderer) {
                        window.AnsyblApp.menuRenderer.updateActiveMenuItem(feedId);
                    }
                }
            }, 100);
        }
    } else if (hash === 'feeds' || hash === '') {
        // Show all feeds
        if (window.AnsyblApp.initialized && window.AnsyblApp.ui) {
            window.AnsyblApp.ui.showAllFeeds();
            
            if (window.AnsyblApp.menuRenderer) {
                window.AnsyblApp.menuRenderer.updateActiveMenuItem('all');
            }
        }
    }
}

/**
 * Expose global functions for debugging
 */
window.AnsyblDebug = {
    getApp: () => window.AnsyblApp,
    reloadFeeds: () => window.AnsyblApp.feedManager?.fetchAllFeeds(),
    clearCache: () => window.AnsyblApp.feedManager?.clearCache(),
    refreshMenu: () => window.AnsyblApp.menuRenderer?.refreshMenu(),
    getState: () => ({
        initialized: window.AnsyblApp.initialized,
        feeds: window.AnsyblApp.feedManager?.feeds,
        config: window.AnsyblApp.config,
        menu: window.AnsyblApp.menuRenderer?.getCurrentMenu()
    })
};
