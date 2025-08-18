/**
 * Local Feed Manager
 * Handles CRUD operations for locally stored feeds
 */

class LocalFeedManager {
    constructor() {
        this.apiBaseUrl = '../api/local-feeds.php';
        this.feeds = new Map();
        this.eventListeners = [];
        
        this.init();
    }
    
    init() {
        this.bindEvents();
    }
    
    /**
     * Bind event listeners
     */
    bindEvents() {
        // Create local feed form submission
        const createForm = document.getElementById('create-local-feed-form');
        if (createForm) {
            createForm.addEventListener('submit', (e) => this.handleCreateFeed(e));
        }
        
        // Local feed modal events
        document.addEventListener('click', (e) => {
            if (e.target.matches('.create-local-feed-btn')) {
                this.showCreateFeedModal();
            } else if (e.target.matches('.edit-local-feed-btn')) {
                const feedId = e.target.dataset.feedId;
                this.showEditFeedModal(feedId);
            } else if (e.target.matches('.delete-local-feed-btn')) {
                const feedId = e.target.dataset.feedId;
                this.deleteFeed(feedId);
            } else if (e.target.matches('.manage-items-btn')) {
                const feedId = e.target.dataset.feedId;
                this.showItemManager(feedId);
            }
        });
    }
    
    /**
     * Load all local feeds
     */
    async loadFeeds() {
        try {
            const response = await fetch(this.apiBaseUrl);
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error.message);
            }
            
            // Store feeds in Map for quick access
            this.feeds.clear();
            data.data.feeds.forEach(feed => {
                this.feeds.set(feed.id, feed);
            });
            
            this.notifyListeners('feedsLoaded', data.data.feeds);
            return data.data.feeds;
            
        } catch (error) {
            console.error('Failed to load local feeds:', error);
            this.notifyListeners('error', { message: 'Failed to load local feeds', error });
            throw error;
        }
    }
    
    /**
     * Create new local feed
     */
    async createFeed(feedData) {
        try {
            const response = await fetch(this.apiBaseUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(feedData)
            });
            
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error.message);
            }
            
            // Add to local storage
            this.feeds.set(data.data.feedId, data.data.feed);
            
            this.notifyListeners('feedCreated', data.data);
            return data.data;
            
        } catch (error) {
            console.error('Failed to create local feed:', error);
            this.notifyListeners('error', { message: 'Failed to create local feed', error });
            throw error;
        }
    }
    
    /**
     * Update local feed
     */
    async updateFeed(feedId, updateData) {
        try {
            const response = await fetch(`${this.apiBaseUrl}/${feedId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(updateData)
            });
            
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error.message);
            }
            
            // Update local storage
            this.feeds.set(feedId, data.data.feed);
            
            this.notifyListeners('feedUpdated', { feedId, feed: data.data.feed });
            return data.data.feed;
            
        } catch (error) {
            console.error('Failed to update local feed:', error);
            this.notifyListeners('error', { message: 'Failed to update local feed', error });
            throw error;
        }
    }
    
    /**
     * Delete local feed
     */
    async deleteFeed(feedId) {
        if (!confirm('Are you sure you want to delete this local feed? This action cannot be undone.')) {
            return false;
        }
        
        try {
            const response = await fetch(`${this.apiBaseUrl}/${feedId}`, {
                method: 'DELETE'
            });
            
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error.message);
            }
            
            // Remove from local storage
            this.feeds.delete(feedId);
            
            this.notifyListeners('feedDeleted', { feedId });
            return true;
            
        } catch (error) {
            console.error('Failed to delete local feed:', error);
            this.notifyListeners('error', { message: 'Failed to delete local feed', error });
            throw error;
        }
    }
    
    /**
     * Get specific local feed
     */
    async getFeed(feedId) {
        // Return from cache if available
        if (this.feeds.has(feedId)) {
            return this.feeds.get(feedId);
        }
        
        try {
            const response = await fetch(`${this.apiBaseUrl}/${feedId}`);
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error.message);
            }
            
            // Store in cache
            this.feeds.set(feedId, data.data.feed);
            
            return data.data.feed;
            
        } catch (error) {
            console.error('Failed to get local feed:', error);
            throw error;
        }
    }
    
    /**
     * Show create feed modal
     */
    showCreateFeedModal() {
        this.showFeedModal();
    }
    
    /**
     * Show edit feed modal
     */
    async showEditFeedModal(feedId) {
        try {
            const feed = await this.getFeed(feedId);
            this.showFeedModal(feed);
        } catch (error) {
            alert('Failed to load feed for editing: ' + error.message);
        }
    }
    
    /**
     * Show feed creation/edit modal
     */
    showFeedModal(feed = null) {
        const isEdit = feed !== null;
        
        // Create modal HTML
        const modalHtml = `
            <div id="local-feed-modal" class="modal-overlay">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>${isEdit ? 'Edit Local Feed' : 'Create Local Feed'}</h3>
                        <button class="modal-close" onclick="this.closest('.modal-overlay').remove()">&times;</button>
                    </div>
                    <form id="local-feed-form" class="modal-body">
                        <div class="form-group">
                            <label for="feed-name">Feed Name *</label>
                            <input type="text" id="feed-name" name="name" required 
                                   value="${feed ? feed.name : ''}" 
                                   placeholder="My Local Feed">
                        </div>
                        
                        <div class="form-group">
                            <label for="feed-description">Description</label>
                            <textarea id="feed-description" name="description" rows="3"
                                      placeholder="Description of this feed">${feed ? feed.description || '' : ''}</textarea>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="feed-language">Language</label>
                                <select id="feed-language" name="language">
                                    <option value="en" ${feed && feed.language === 'en' ? 'selected' : ''}>English</option>
                                    <option value="es" ${feed && feed.language === 'es' ? 'selected' : ''}>Spanish</option>
                                    <option value="fr" ${feed && feed.language === 'fr' ? 'selected' : ''}>French</option>
                                    <option value="de" ${feed && feed.language === 'de' ? 'selected' : ''}>German</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" id="feed-published" name="published" 
                                           ${feed ? (feed.published ? 'checked' : '') : 'checked'}>
                                    Published
                                </label>
                            </div>
                        </div>
                        
                        <div class="modal-actions">
                            <button type="button" class="button secondary" onclick="this.closest('.modal-overlay').remove()">
                                Cancel
                            </button>
                            <button type="submit" class="button primary">
                                ${isEdit ? 'Update Feed' : 'Create Feed'}
                            </button>
                        </div>
                        
                        ${isEdit ? `<input type="hidden" name="feedId" value="${feed.id}">` : ''}
                    </form>
                </div>
            </div>
        `;
        
        // Add modal to page
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Bind form submission
        const form = document.getElementById('local-feed-form');
        form.addEventListener('submit', (e) => this.handleFeedSubmit(e, isEdit));
    }
    
    /**
     * Handle feed form submission
     */
    async handleFeedSubmit(event, isEdit = false) {
        event.preventDefault();
        
        const formData = new FormData(event.target);
        const feedData = {
            name: formData.get('name'),
            description: formData.get('description'),
            language: formData.get('language'),
            published: formData.has('published')
        };
        
        try {
            if (isEdit) {
                const feedId = formData.get('feedId');
                await this.updateFeed(feedId, feedData);
            } else {
                await this.createFeed(feedData);
            }
            
            // Close modal
            document.getElementById('local-feed-modal').remove();
            
            // Show success message
            this.showMessage('success', `Feed ${isEdit ? 'updated' : 'created'} successfully!`);
            
        } catch (error) {
            this.showMessage('error', error.message);
        }
    }
    
    /**
     * Show item manager for a feed
     */
    showItemManager(feedId) {
        // This will be handled by FeedItemManager
        if (window.feedItemManager) {
            window.feedItemManager.showItemManager(feedId);
        } else {
            alert('Item manager not available. Please refresh the page.');
        }
    }
    
    /**
     * Show status message
     */
    showMessage(type, message) {
        const messageHtml = `
            <div class="status-message ${type}" style="margin-bottom: 1rem;">
                <span>${message}</span>
                <button class="status-message-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
        `;
        
        const container = document.getElementById('status-messages') || document.body;
        container.insertAdjacentHTML('afterbegin', messageHtml);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            const messageEl = container.querySelector(`.status-message.${type}`);
            if (messageEl) {
                messageEl.remove();
            }
        }, 5000);
    }
    
    /**
     * Add event listener
     */
    addEventListener(eventType, callback) {
        this.eventListeners.push({ eventType, callback });
    }
    
    /**
     * Remove event listener
     */
    removeEventListener(eventType, callback) {
        this.eventListeners = this.eventListeners.filter(
            listener => listener.eventType !== eventType || listener.callback !== callback
        );
    }
    
    /**
     * Notify event listeners
     */
    notifyListeners(eventType, data) {
        this.eventListeners
            .filter(listener => listener.eventType === eventType)
            .forEach(listener => {
                try {
                    listener.callback(data);
                } catch (error) {
                    console.error('Error in event listener:', error);
                }
            });
    }
    
    /**
     * Get all local feeds
     */
    getFeeds() {
        return Array.from(this.feeds.values());
    }
    
    /**
     * Check if feed exists
     */
    hasFeed(feedId) {
        return this.feeds.has(feedId);
    }
}

// Create global instance
window.localFeedManager = new LocalFeedManager();