/**
 * Admin Panel Main JavaScript
 * Handles overall admin interface functionality
 */

class AdminPanel {
    constructor() {
        this.currentSection = 'feeds';
        this.externalFeeds = [];
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.initializeSections();
    }
    
    /**
     * Bind event listeners
     */
    bindEvents() {
        // Section navigation
        document.addEventListener('click', (e) => {
            if (e.target.matches('.nav-link[data-section]')) {
                e.preventDefault();
                const section = e.target.dataset.section;
                this.showSection(section);
            }
        });
        
        // Feed type switching
        document.addEventListener('click', (e) => {
            if (e.target.matches('.feed-type-btn')) {
                this.switchFeedType(e.target.dataset.type);
            }
        });
        
        // External feed form submission
        const externalFeedForm = document.getElementById('add-external-feed-form');
        if (externalFeedForm) {
            externalFeedForm.addEventListener('submit', (e) => this.handleExternalFeedSubmit(e));
        }
        
        // Feed refresh buttons
        const refreshAllFeedsBtn = document.getElementById('refresh-all-feeds');
        if (refreshAllFeedsBtn) {
            refreshAllFeedsBtn.addEventListener('click', () => this.handleRefreshAllFeeds());
        }
        
        const clearFeedCacheBtn = document.getElementById('clear-feed-cache');
        if (clearFeedCacheBtn) {
            clearFeedCacheBtn.addEventListener('click', () => this.handleClearFeedCache());
        }
        
        // Individual feed refresh buttons (using delegation)
        document.addEventListener('click', (e) => {
            if (e.target.matches('.refresh-feed-btn')) {
                e.preventDefault();
                const feedId = e.target.dataset.feedId;
                this.handleRefreshSingleFeed(feedId, e.target);
            }
        });
    }
    
    /**
     * Initialize admin sections
     */
    initializeSections() {
        // Show initial section
        this.showSection(this.currentSection);
        
        // Load feeds if on feeds section
        if (this.currentSection === 'feeds') {
            this.loadFeedsSection();
        }
    }
    
    /**
     * Show specific admin section
     */
    showSection(sectionName) {
        // Update navigation
        document.querySelectorAll('.nav-link').forEach(link => {
            link.classList.remove('active');
            if (link.dataset.section === sectionName) {
                link.classList.add('active');
            }
        });
        
        // Show/hide sections
        document.querySelectorAll('.admin-section').forEach(section => {
            section.classList.remove('active');
        });
        
        const targetSection = document.getElementById(`${sectionName}-section`);
        if (targetSection) {
            targetSection.classList.add('active');
        }
        
        this.currentSection = sectionName;
        
        // Load section-specific data
        switch (sectionName) {
            case 'feeds':
                this.loadFeedsSection();
                break;
            case 'config':
                this.loadConfigSection();
                break;
            case 'menus':
                this.loadMenusSection();
                break;
            case 'styles':
                this.loadStylesSection();
                break;
            case 'cache':
                this.loadCacheSection();
                break;
        }
    }
    
    /**
     * Switch between external and local feed forms
     */
    switchFeedType(type) {
        // Update buttons
        document.querySelectorAll('.feed-type-btn').forEach(btn => {
            btn.classList.remove('active');
            if (btn.dataset.type === type) {
                btn.classList.add('active');
            }
        });
        
        // Show/hide forms
        document.querySelectorAll('.feed-form').forEach(form => {
            form.classList.remove('active');
        });
        
        const targetForm = document.getElementById(`add-${type}-feed-form`);
        if (targetForm) {
            targetForm.classList.add('active');
        }
    }
    
    /**
     * Handle external feed form submission
     */
    async handleExternalFeedSubmit(event) {
        event.preventDefault();
        
        const formData = new FormData(event.target);
        const feedData = {
            url: formData.get('external-feed-url') || formData.get('feed-url'),
            name: formData.get('external-feed-name') || formData.get('feed-name'),
            enabled: formData.has('external-feed-enabled') || formData.has('feed-enabled') || true
        };
        
        // Validate input
        if (!feedData.url || !feedData.name) {
            this.showMessage('error', 'Please fill in both URL and Name fields');
            return;
        }
        
        try {
            // Submit to feeds API
            const response = await fetch('/api/feeds.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(feedData)
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showMessage('success', `External feed "${feedData.name}" added successfully!`);
                
                // Clear the form
                event.target.reset();
                
                // Reload feeds list to show the new feed
                this.loadFeedsSection();
                
            } else {
                throw new Error(result.error?.message || 'Failed to add feed');
            }
            
        } catch (error) {
            console.error('Error adding external feed:', error);
            this.showMessage('error', 'Failed to add external feed: ' + error.message);
        }
    }
    
    /**
     * Load feeds section
     */
    async loadFeedsSection() {
        try {
            // Load both local and external feeds
            const loadTasks = [];
            
            // Load local feeds if available
            if (window.localFeedManager) {
                loadTasks.push(window.localFeedManager.loadFeeds());
            }
            
            // Load external feeds
            loadTasks.push(this.loadExternalFeeds());
            
            await Promise.all(loadTasks);
            this.updateFeedsList();
            
        } catch (error) {
            console.error('Failed to load feeds section:', error);
        }
    }
    
    /**
     * Load external feeds from API
     */
    async loadExternalFeeds() {
        try {
            const response = await fetch('/api/feeds.php');
            const result = await response.json();
            
            if (result.success && result.data?.feeds) {
                this.externalFeeds = result.data.feeds;
            } else {
                this.externalFeeds = [];
            }
        } catch (error) {
            console.error('Failed to load external feeds:', error);
            this.externalFeeds = [];
        }
    }
    
    /**
     * Load config section
     */
    loadConfigSection() {
        // Placeholder for config section loading
        console.log('Loading config section...');
    }
    
    /**
     * Load styles section
     */
    loadStylesSection() {
        // Placeholder for styles section loading
        console.log('Loading styles section...');
    }
    
    /**
     * Load cache section
     */
    loadCacheSection() {
        // Placeholder for cache section loading
        console.log('Loading cache section...');
    }
    
    /**
     * Update feeds list display
     */
    updateFeedsList() {
        const feedsList = document.getElementById('feeds-list');
        if (!feedsList) return;
        
        const localFeeds = window.localFeedManager ? window.localFeedManager.getFeeds() : [];
        const externalFeeds = this.externalFeeds || [];
        const allFeeds = [...localFeeds, ...externalFeeds];
        
        if (allFeeds.length === 0) {
            feedsList.innerHTML = `
                <div class="empty-state">
                    <p>No feeds created yet.</p>
                    <button class="button primary create-local-feed-btn">Create Your First Feed</button>
                </div>
            `;
            return;
        }
        
        feedsList.innerHTML = allFeeds.map(feed => this.renderFeedItem(feed)).join('');
    }
    
    /**
     * Render individual feed item
     */
    renderFeedItem(feed) {
        const isLocal = feed.hasOwnProperty('published') && !feed.hasOwnProperty('enabled');
        const isExternal = feed.hasOwnProperty('enabled') && !feed.hasOwnProperty('published');
        const isActive = isLocal ? feed.published : feed.enabled;
        const icon = isLocal ? '📍' : '📡';
        const type = isLocal ? 'Local' : 'External';
        
        return `
            <div class="feed-item ${isLocal ? 'local-feed' : 'external-feed'}" data-feed-id="${feed.id}">
                <div class="feed-info">
                    <h4 class="feed-name">${icon} ${feed.name}</h4>
                    <p class="feed-url">${feed.url || (isLocal ? 'Local feed' : 'External feed')}</p>
                    <div class="feed-meta">
                        <span class="feed-type">${type}</span>
                        <span class="feed-status ${isActive ? 'active' : 'inactive'}">
                            ${isActive ? 'Active' : 'Inactive'}
                        </span>
                        <span class="feed-last-updated">Updated: ${this.formatDate(feed.updated || feed.lastFetched)}</span>
                        <span class="feed-item-count">${feed.totalItems || 0} items</span>
                    </div>
                </div>
                
                <div class="feed-controls">
                    <label class="toggle">
                        <input type="checkbox" class="feed-enabled-toggle" ${isActive ? 'checked' : ''} data-feed-type="${isLocal ? 'local' : 'external'}">
                        <span class="toggle-slider"></span>
                    </label>
                    
                    ${isLocal ? `
                        <button class="feed-action-button edit-local-feed-btn" data-feed-id="${feed.id}" title="Edit">✏️</button>
                        <button class="feed-action-button manage-items-btn" data-feed-id="${feed.id}" title="Manage Items">📝</button>
                        <button class="feed-action-button media-manager-btn" data-feed-id="${feed.id}" title="Media Manager">📎</button>
                        <button class="feed-action-button delete-local-feed-btn" data-feed-id="${feed.id}" title="Delete">🗑️</button>
                    ` : `
                        <button class="feed-action-button edit-external-feed-btn" data-feed-id="${feed.id}" title="Edit">✏️</button>
                        <button class="feed-action-button refresh-feed-btn" data-feed-id="${feed.id}" title="Refresh">🔄</button>
                        <button class="feed-action-button delete-external-feed-btn" data-feed-id="${feed.id}" title="Delete">🗑️</button>
                    `}
                </div>
            </div>
        `;
    }
    
    /**
     * Load menus section
     */
    loadMenusSection() {
        // The menu manager will initialize itself when the section becomes active
        if (window.menuManager) {
            window.menuManager.renderMenuBuilder();
        }
    }
    
    /**
     * Load config section
     */
    loadConfigSection() {
        // Config manager handles its own initialization
        if (window.configManager) {
            window.configManager.loadSiteConfig();
        }
    }
    
    /**
     * Load styles section
     */
    loadStylesSection() {
        // Placeholder for styles management
        console.log('Styles section loaded');
    }
    
    /**
     * Load cache section
     */
    loadCacheSection() {
        // Placeholder for cache management
        console.log('Cache section loaded');
    }
    
    /**
     * Format date for display
     */
    formatDate(dateString) {
        if (!dateString) return 'Never';
        const date = new Date(dateString);
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
    }
    
    /**
     * Show status message
     */
    showMessage(type, message) {
        const messageHtml = `
            <div class="status-message ${type}">
                <span>${message}</span>
                <button class="status-message-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
        `;
        
        const container = document.getElementById('status-messages');
        if (container) {
            container.insertAdjacentHTML('afterbegin', messageHtml);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                const messageEl = container.querySelector(`.status-message.${type}`);
                if (messageEl) {
                    messageEl.remove();
                }
            }, 5000);
        }
    }
    
    /**
     * Handle refresh all feeds button click
     */
    async handleRefreshAllFeeds() {
        const button = document.getElementById('refresh-all-feeds');
        const originalText = button.textContent;
        
        try {
            // Update button state
            button.disabled = true;
            button.textContent = '🔄 Refreshing...';
            
            // Call the feeds API refresh endpoint
            const response = await fetch('/api/feeds.php/refresh', {
                method: 'POST'
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Clear frontend cache
                if (window.feedManager) {
                    window.feedManager.clearCache();
                    await window.feedManager.fetchAllFeeds(true); // Force refresh
                    
                    // Trigger UI update to refresh dropdown counts
                    if (window.uiManager && window.uiManager.updateFeedFilter) {
                        window.uiManager.updateFeedFilter();
                    }
                }
                
                // Signal other tabs/windows to refresh all feeds
                this.signalFeedRefresh('all');
                
                // Reload feeds list in admin
                this.loadFeedsSection();
                
                this.showMessage('success', `Successfully refreshed ${result.data.successCount || 'all'} feeds!`);
            } else {
                throw new Error(result.error?.message || 'Failed to refresh feeds');
            }
            
        } catch (error) {
            console.error('Failed to refresh feeds:', error);
            this.showMessage('error', 'Failed to refresh feeds: ' + error.message);
        } finally {
            // Restore button state
            button.disabled = false;
            button.textContent = originalText;
        }
    }
    
    /**
     * Handle clear feed cache button click
     */
    async handleClearFeedCache() {
        const button = document.getElementById('clear-feed-cache');
        const originalText = button.textContent;
        
        if (!confirm('Clear all feed cache? This will force fresh data to be fetched on next load.')) {
            return;
        }
        
        try {
            // Update button state
            button.disabled = true;
            button.textContent = '🗑️ Clearing...';
            
            // Clear backend cache
            const response = await fetch('/api/cache.php', {
                method: 'DELETE'
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Clear frontend cache
                if (window.feedManager) {
                    window.feedManager.clearCache();
                    // Reload feed configurations to sync frontend with backend
                    await window.feedManager.loadFeedConfigs();
                }
                
                // Clear browser localStorage cache
                if (typeof Storage !== 'undefined' && localStorage) {
                    const cacheKeys = ['ansybl_feeds_cache', 'ansybl_config_cache', 'ansybl_last_update'];
                    cacheKeys.forEach(key => localStorage.removeItem(key));
                }
                
                // Reload feeds list in admin to show synced state
                this.loadFeedsSection();
                
                this.showMessage('success', 'Feed cache cleared and feeds synced successfully!');
            } else {
                throw new Error(result.error?.message || 'Failed to clear cache');
            }
            
        } catch (error) {
            console.error('Failed to clear cache:', error);
            this.showMessage('error', 'Failed to clear cache: ' + error.message);
        } finally {
            // Restore button state
            button.disabled = false;
            button.textContent = originalText;
        }
    }
    
    /**
     * Handle refresh single feed button click
     */
    async handleRefreshSingleFeed(feedId, button) {
        const originalText = button.textContent;
        const originalTitle = button.title;
        
        try {
            // Update button state
            button.disabled = true;
            button.textContent = '⏳';
            button.title = 'Refreshing...';
            
            // Call the feeds API refresh endpoint for single feed
            const response = await fetch(`/api/feeds.php/${feedId}/refresh`, {
                method: 'POST'
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Clear frontend cache for this feed
                if (window.feedManager) {
                    // Force refresh this specific feed
                    await window.feedManager.fetchFeed(feedId, true);
                    
                    // Trigger UI update to refresh dropdown counts
                    if (window.uiManager && window.uiManager.updateFeedFilter) {
                        window.uiManager.updateFeedFilter();
                    }
                }
                
                // Signal other tabs/windows to refresh feed data
                this.signalFeedRefresh('single', feedId);
                
                // Reload feeds list to show updated info
                this.loadFeedsSection();
                
                this.showMessage('success', `Successfully refreshed "${result.data.feed?.name || feedId}" feed!`);
            } else {
                throw new Error(result.error?.message || 'Failed to refresh feed');
            }
            
        } catch (error) {
            console.error(`Failed to refresh feed ${feedId}:`, error);
            this.showMessage('error', `Failed to refresh feed: ${error.message}`);
        } finally {
            // Restore button state
            button.disabled = false;
            button.textContent = originalText;
            button.title = originalTitle;
        }
    }
}

// Initialize admin panel when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.adminPanel = new AdminPanel();
    
    // Set up event listeners for local feed management
    if (window.localFeedManager) {
        window.localFeedManager.addEventListener('feedCreated', () => {
            window.adminPanel.updateFeedsList();
        });
        
        window.localFeedManager.addEventListener('feedUpdated', () => {
            window.adminPanel.updateFeedsList();
        });
        
        window.localFeedManager.addEventListener('feedDeleted', () => {
            window.adminPanel.updateFeedsList();
        });
    }
});

// Export for use in other scripts
window.AdminPanel = AdminPanel;