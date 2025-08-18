/**
 * Admin Panel Main JavaScript
 * Handles overall admin interface functionality
 */

class AdminPanel {
    constructor() {
        this.currentSection = 'feeds';
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
            enabled: formData.has('external-feed-enabled') || formData.has('feed-enabled')
        };
        
        try {
            // Here you would normally submit to your external feeds API
            console.log('External feed data:', feedData);
            this.showMessage('success', 'External feed functionality will be implemented with the existing feeds API');
            
        } catch (error) {
            this.showMessage('error', 'Failed to add external feed: ' + error.message);
        }
    }
    
    /**
     * Load feeds section
     */
    async loadFeedsSection() {
        try {
            // Load local feeds if available
            if (window.localFeedManager) {
                await window.localFeedManager.loadFeeds();
                this.updateFeedsList();
            }
        } catch (error) {
            console.error('Failed to load feeds section:', error);
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
        if (!feedsList || !window.localFeedManager) return;
        
        const localFeeds = window.localFeedManager.getFeeds();
        
        if (localFeeds.length === 0) {
            feedsList.innerHTML = `
                <div class="empty-state">
                    <p>No feeds created yet.</p>
                    <button class="button primary create-local-feed-btn">Create Your First Feed</button>
                </div>
            `;
            return;
        }
        
        feedsList.innerHTML = localFeeds.map(feed => this.renderFeedItem(feed)).join('');
    }
    
    /**
     * Render individual feed item
     */
    renderFeedItem(feed) {
        return `
            <div class="feed-item" data-feed-id="${feed.id}">
                <div class="feed-info">
                    <h4 class="feed-name">📍 ${feed.name}</h4>
                    <p class="feed-url">${feed.url || 'Local feed'}</p>
                    <div class="feed-meta">
                        <span class="feed-status ${feed.published ? 'active' : 'inactive'}">
                            ${feed.published ? 'Published' : 'Draft'}
                        </span>
                        <span class="feed-last-updated">Updated: ${this.formatDate(feed.updated)}</span>
                        <span class="feed-item-count">${feed.totalItems || 0} items</span>
                    </div>
                </div>
                
                <div class="feed-controls">
                    <label class="toggle">
                        <input type="checkbox" class="feed-enabled-toggle" ${feed.published ? 'checked' : ''}>
                        <span class="toggle-slider"></span>
                    </label>
                    
                    <button class="feed-action-button edit-local-feed-btn" data-feed-id="${feed.id}" title="Edit">✏️</button>
                    <button class="feed-action-button manage-items-btn" data-feed-id="${feed.id}" title="Manage Items">📝</button>
                    <button class="feed-action-button media-manager-btn" data-feed-id="${feed.id}" title="Media Manager">📎</button>
                    <button class="feed-action-button delete-local-feed-btn" data-feed-id="${feed.id}" title="Delete">🗑️</button>
                </div>
            </div>
        `;
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