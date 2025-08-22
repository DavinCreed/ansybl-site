/**
 * Menu Renderer
 * Handles dynamic menu rendering on the frontend
 */

class MenuRenderer {
    constructor() {
        this.apiBaseUrl = 'api/config.php';
        this.currentMenu = null;
        this.menuCache = new Map();
        
        this.init();
    }
    
    async init() {
        await this.loadMenuConfiguration();
        this.renderPrimaryMenu();
        this.bindMenuEvents();
    }
    
    /**
     * Load menu configuration from API
     */
    async loadMenuConfiguration() {
        try {
            const response = await fetch(`${this.apiBaseUrl}/menu`);
            const data = await response.json();
            
            if (data.success && data.data) {
                // The API returns { success: true, data: { config: actualMenuData } }
                this.currentMenu = data.data.config || data.data;
                this.menuCache.set('primary', this.currentMenu.menus?.primary);
            } else {
                console.warn('Failed to load menu config, using defaults');
                this.currentMenu = this.getDefaultMenuConfig();
            }
        } catch (error) {
            console.error('Failed to load menu config:', error);
            this.currentMenu = this.getDefaultMenuConfig();
        }
    }
    
    /**
     * Get default menu configuration
     */
    getDefaultMenuConfig() {
        return {
            version: '1.0',
            menus: {
                primary: {
                    name: 'Primary Navigation',
                    location: 'header',
                    items: [
                        {
                            id: 'home',
                            type: 'link',
                            title: 'Home',
                            url: '/',
                            order: 1,
                            visible: true,
                            target: '_self'
                        },
                        {
                            id: 'feeds',
                            type: 'link',
                            title: 'All Feeds',
                            url: '#feeds',
                            order: 2,
                            visible: true,
                            target: '_self'
                        }
                    ]
                }
            }
        };
    }
    
    /**
     * Render primary navigation menu
     */
    renderPrimaryMenu() {
        const navList = document.getElementById('nav-list');
        if (!navList) return;
        
        const primaryMenu = this.currentMenu.menus?.primary;
        if (!primaryMenu || !primaryMenu.items) {
            console.warn('No primary menu items found');
            return;
        }
        
        // Sort items by order
        const sortedItems = [...primaryMenu.items]
            .filter(item => item.visible !== false)
            .sort((a, b) => (a.order || 0) - (b.order || 0));
        
        // Clear existing menu items
        navList.innerHTML = '';
        
        // Render menu items
        sortedItems.forEach(item => {
            const listItem = this.createMenuItem(item);
            if (listItem) {
                navList.appendChild(listItem);
            }
        });
        
        // Update mobile menu if it exists
        this.updateMobileMenu();
    }
    
    /**
     * Create a menu item element
     */
    createMenuItem(item) {
        const li = document.createElement('li');
        li.className = `nav-item nav-item-${item.type}`;
        
        if (item.css_class) {
            li.className += ` ${item.css_class}`;
        }
        
        const link = document.createElement('a');
        link.className = 'nav-link';
        link.href = this.resolveMenuItemUrl(item);
        link.textContent = item.title;
        link.setAttribute('data-menu-id', item.id);
        
        if (item.target) {
            link.target = item.target;
        }
        
        if (item.target === '_blank') {
            link.rel = 'noopener noreferrer';
        }
        
        // Add icon if present
        if (item.icon) {
            const icon = document.createElement('span');
            icon.className = 'nav-icon';
            
            // Handle emoji vs CSS class icons
            if (this.isEmoji(item.icon)) {
                icon.textContent = item.icon;
            } else {
                icon.className += ` ${item.icon}`;
            }
            
            link.insertBefore(icon, link.firstChild);
        }
        
        // Handle special menu item types
        switch (item.type) {
            case 'feed':
                this.setupFeedMenuItem(link, item);
                break;
            case 'custom':
                this.setupCustomMenuItem(link, item);
                break;
            default:
                // Standard link - no special handling needed
                break;
        }
        
        li.appendChild(link);
        return li;
    }
    
    /**
     * Resolve menu item URL based on type
     */
    resolveMenuItemUrl(item) {
        switch (item.type) {
            case 'feed':
                if (item.feed_id) {
                    // Check if it's a local or external feed
                    if (item.feed_id.startsWith('local:')) {
                        const feedId = item.feed_id.replace('local:', '');
                        return `#local-feed-${feedId}`;
                    } else if (item.feed_id.startsWith('external:')) {
                        const feedId = item.feed_id.replace('external:', '');
                        return `#external-feed-${feedId}`;
                    }
                }
                return '#feeds';
                
            case 'custom':
            case 'link':
            default:
                return item.url || '#';
        }
    }
    
    /**
     * Setup feed-specific menu item functionality
     */
    setupFeedMenuItem(link, item) {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            
            if (item.feed_id) {
                // Trigger feed filtering
                this.filterByFeed(item.feed_id);
            } else {
                // Show all feeds
                this.showAllFeeds();
            }
        });
    }
    
    /**
     * Setup custom menu item functionality
     */
    setupCustomMenuItem(link, item) {
        // Custom menu items can have special behaviors
        if (item.action) {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                this.executeCustomAction(item.action, item);
            });
        }
    }
    
    /**
     * Filter content by specific feed
     */
    filterByFeed(feedId) {
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
        
        // Dispatch custom event for feed filtering
        document.dispatchEvent(new CustomEvent('menuFilterFeed', {
            detail: { feedId: actualFeedId }
        }));
        
        // Update active menu state
        this.updateActiveMenuItem(feedId);
    }
    
    /**
     * Show all feeds
     */
    showAllFeeds() {
        document.dispatchEvent(new CustomEvent('menuShowAllFeeds'));
        this.updateActiveMenuItem('all');
    }
    
    /**
     * Execute custom menu action
     */
    executeCustomAction(action, item) {
        switch (action) {
            case 'search':
                this.focusSearch();
                break;
            case 'toggle-view':
                this.toggleContentView();
                break;
            case 'refresh':
                this.refreshContent();
                break;
            default:
                console.warn('Unknown custom action:', action);
        }
    }
    
    /**
     * Update active menu item
     */
    updateActiveMenuItem(activeId) {
        const navLinks = document.querySelectorAll('.nav-link');
        
        navLinks.forEach(link => {
            link.classList.remove('active');
            
            const menuId = link.getAttribute('data-menu-id');
            if (menuId === activeId || 
                (activeId === 'all' && (menuId === 'home' || menuId === 'feeds'))) {
                link.classList.add('active');
            }
        });
    }
    
    /**
     * Update mobile menu structure
     */
    updateMobileMenu() {
        const mobileToggle = document.getElementById('mobile-menu-toggle');
        if (!mobileToggle) return;
        
        // Ensure mobile menu works with dynamic content
        const navList = document.getElementById('nav-list');
        if (navList) {
            navList.classList.add('mobile-ready');
            
            // Hide mobile menu by default
            navList.classList.remove('show');
        }
    }
    
    /**
     * Bind menu-related events
     */
    bindMenuEvents() {
        // Mobile menu toggle
        const mobileToggle = document.getElementById('mobile-menu-toggle');
        if (mobileToggle) {
            mobileToggle.addEventListener('click', () => {
                this.toggleMobileMenu();
            });
        }
        
        // Close mobile menu when clicking nav links
        document.addEventListener('click', (e) => {
            if (e.target.closest('.nav-link')) {
                this.closeMobileMenu();
            }
        });
        
        // Close mobile menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.main-nav') && !e.target.closest('.mobile-menu-toggle')) {
                this.closeMobileMenu();
            }
        });
        
        // Listen for configuration updates
        document.addEventListener('configUpdated', (e) => {
            if (e.detail.type === 'menu') {
                this.handleMenuConfigUpdate(e.detail.config);
            }
        });
    }
    
    /**
     * Toggle mobile menu
     */
    toggleMobileMenu() {
        const navList = document.getElementById('nav-list');
        const toggle = document.getElementById('mobile-menu-toggle');
        
        if (navList && toggle) {
            const isOpen = navList.classList.contains('show');
            
            if (isOpen) {
                this.closeMobileMenu();
            } else {
                this.openMobileMenu();
            }
        }
    }
    
    /**
     * Open mobile menu
     */
    openMobileMenu() {
        const navList = document.getElementById('nav-list');
        const toggle = document.getElementById('mobile-menu-toggle');
        
        if (navList && toggle) {
            navList.classList.add('show');
            toggle.setAttribute('aria-expanded', 'true');
            document.body.classList.add('mobile-menu-open');
        }
    }
    
    /**
     * Close mobile menu
     */
    closeMobileMenu() {
        const navList = document.getElementById('nav-list');
        const toggle = document.getElementById('mobile-menu-toggle');
        
        if (navList && toggle) {
            navList.classList.remove('show');
            toggle.setAttribute('aria-expanded', 'false');
            document.body.classList.remove('mobile-menu-open');
        }
    }
    
    /**
     * Handle menu configuration updates
     */
    handleMenuConfigUpdate(newConfig) {
        this.currentMenu = newConfig;
        this.menuCache.set('primary', newConfig.menus?.primary);
        this.renderPrimaryMenu();
    }
    
    /**
     * Focus search input
     */
    focusSearch() {
        const searchInput = document.getElementById('site-search');
        if (searchInput) {
            searchInput.focus();
        }
    }
    
    /**
     * Toggle content view (grid/list)
     */
    toggleContentView() {
        const gridButton = document.getElementById('grid-view');
        const listButton = document.getElementById('list-view');
        
        if (gridButton && listButton) {
            if (gridButton.classList.contains('active')) {
                listButton.click();
            } else {
                gridButton.click();
            }
        }
    }
    
    /**
     * Refresh content
     */
    refreshContent() {
        document.dispatchEvent(new CustomEvent('refreshContent'));
    }
    
    /**
     * Check if string is an emoji
     */
    isEmoji(str) {
        const emojiRegex = /[\u{1F600}-\u{1F64F}]|[\u{1F300}-\u{1F5FF}]|[\u{1F680}-\u{1F6FF}]|[\u{1F1E0}-\u{1F1FF}]|[\u{2600}-\u{26FF}]|[\u{2700}-\u{27BF}]/u;
        return emojiRegex.test(str);
    }
    
    /**
     * Get current menu configuration
     */
    getCurrentMenu() {
        return { ...this.currentMenu };
    }
    
    /**
     * Refresh menu from server
     */
    async refreshMenu() {
        await this.loadMenuConfiguration();
        this.renderPrimaryMenu();
    }
}

// Create global instance
window.menuRenderer = new MenuRenderer();