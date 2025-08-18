/**
 * Feed Item Manager
 * Handles CRUD operations for items within local feeds
 */

class FeedItemManager {
    constructor() {
        this.apiBaseUrl = '../api/local-feeds.php';
        this.currentFeedId = null;
        this.items = new Map();
        this.eventListeners = [];
        this.currentSubItems = []; // Track sub-items being created
        this.editingItemId = null;
        
        this.init();
    }
    
    init() {
        this.bindEvents();
    }
    
    /**
     * Bind event listeners
     */
    bindEvents() {
        document.addEventListener('click', (e) => {
            if (e.target.matches('.add-item-btn')) {
                this.showItemEditor();
            } else if (e.target.matches('.edit-item-btn')) {
                const itemId = e.target.dataset.itemId;
                this.showItemEditor(itemId);
            } else if (e.target.matches('.delete-item-btn')) {
                const itemId = e.target.dataset.itemId;
                this.deleteItem(itemId);
            } else if (e.target.matches('.preview-item-btn')) {
                const itemId = e.target.dataset.itemId;
                this.previewItem(itemId);
            } else if (e.target.matches('.add-sub-item-btn')) {
                this.showSubItemEditor();
            } else if (e.target.matches('.edit-sub-item-btn')) {
                const subItemIndex = parseInt(e.target.dataset.subItemIndex);
                this.showSubItemEditor(subItemIndex);
            } else if (e.target.matches('.delete-sub-item-btn')) {
                const subItemIndex = parseInt(e.target.dataset.subItemIndex);
                this.deleteSubItem(subItemIndex);
            } else if (e.target.matches('.toggle-sub-items-btn')) {
                this.toggleSubItemsSection();
            }
        });
    }
    
    /**
     * Show item manager modal for a feed
     */
    async showItemManager(feedId) {
        this.currentFeedId = feedId;
        
        try {
            // Load feed info and items
            const [feed, items] = await Promise.all([
                this.getFeed(feedId),
                this.loadItems(feedId)
            ]);
            
            this.showItemManagerModal(feed, items);
            
        } catch (error) {
            console.error('Failed to load feed items:', error);
            alert('Failed to load feed items: ' + error.message);
        }
    }
    
    /**
     * Show item manager modal
     */
    showItemManagerModal(feed, items) {
        const modalHtml = `
            <div id="item-manager-modal" class="modal-overlay large-modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Manage Items - ${feed.name}</h3>
                        <button class="modal-close" onclick="this.closest('.modal-overlay').remove()">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="item-manager-toolbar">
                            <button class="button primary add-item-btn">
                                + Add New Item
                            </button>
                            <div class="item-stats">
                                <span>${items.length} items</span>
                            </div>
                        </div>
                        
                        <div id="items-list" class="items-list">
                            ${this.renderItemsList(items)}
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHtml);
    }
    
    /**
     * Render items list
     */
    renderItemsList(items) {
        if (items.length === 0) {
            return `
                <div class="empty-state">
                    <p>No items in this feed yet.</p>
                    <button class="button primary add-item-btn">Add Your First Item</button>
                </div>
            `;
        }
        
        return items.map(item => `
            <div class="item-card" data-item-id="${item.id}">
                <div class="item-header">
                    <span class="item-type-badge item-type-${item.type.toLowerCase()}">${item.type}</span>
                    ${item.type === 'Collection' ? `<span class="collection-info">(${item.totalItems || 0} items)</span>` : ''}
                    <div class="item-actions">
                        <button class="item-action-btn edit-item-btn" data-item-id="${item.id}" title="Edit">
                            ✏️
                        </button>
                        <button class="item-action-btn preview-item-btn" data-item-id="${item.id}" title="Preview">
                            👁️
                        </button>
                        <button class="item-action-btn delete-item-btn" data-item-id="${item.id}" title="Delete">
                            🗑️
                        </button>
                    </div>
                </div>
                <div class="item-content">
                    <h4 class="item-title">${item.name || 'Untitled'}</h4>
                    ${item.summary ? `<p class="item-summary">${this.truncateText(item.summary, 100)}</p>` : ''}
                    ${item.type === 'Collection' && item.items ? `
                        <div class="collection-items-preview">
                            ${item.items.map(subItem => `<span class="sub-item-type-badge type-${subItem.type.toLowerCase()}">${subItem.type}</span>`).join(' ')}
                        </div>
                    ` : ''}
                    <div class="item-meta">
                        <span class="item-date">${this.formatDate(item.published || item.updated)}</span>
                        ${item.url ? `<span class="item-url">🔗 <a href="${item.url}" target="_blank">View</a></span>` : ''}
                    </div>
                </div>
            </div>
        `).join('');
    }
    
    /**
     * Show item editor modal
     */
    async showItemEditor(itemId = null) {
        const isEdit = itemId !== null;
        let item = null;
        
        if (isEdit) {
            try {
                item = await this.getItem(itemId);
            } catch (error) {
                alert('Failed to load item: ' + error.message);
                return;
            }
        }
        
        const modalHtml = `
            <div id="item-editor-modal" class="modal-overlay large-modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>${isEdit ? 'Edit Item' : 'Add New Item'}</h3>
                        <button class="modal-close" onclick="this.closest('.modal-overlay').remove()">&times;</button>
                    </div>
                    <form id="item-editor-form" class="modal-body">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="item-type">Item Type *</label>
                                <select id="item-type" name="type" required onchange="this.closest('form').querySelector('.item-type-fields').className = 'item-type-fields type-' + this.value.toLowerCase()">
                                    <option value="">Select Type</option>
                                    <option value="Article" ${item && item.type === 'Article' ? 'selected' : ''}>Article</option>
                                    <option value="Note" ${item && item.type === 'Note' ? 'selected' : ''}>Note</option>
                                    <option value="Image" ${item && item.type === 'Image' ? 'selected' : ''}>Image</option>
                                    <option value="Audio" ${item && item.type === 'Audio' ? 'selected' : ''}>Audio</option>
                                    <option value="Video" ${item && item.type === 'Video' ? 'selected' : ''}>Video</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="item-published">Published Date</label>
                                <input type="datetime-local" id="item-published" name="published" 
                                       value="${item ? this.formatDateForInput(item.published) : this.formatDateForInput(new Date().toISOString())}">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="item-name">Title *</label>
                            <input type="text" id="item-name" name="name" required 
                                   value="${item ? item.name || '' : ''}" 
                                   placeholder="Item title">
                        </div>
                        
                        <div class="form-group">
                            <label for="item-summary">Summary</label>
                            <textarea id="item-summary" name="summary" rows="2"
                                      placeholder="Brief description of this item">${item ? item.summary || '' : ''}</textarea>
                        </div>
                        
                        <div class="item-type-fields ${item ? 'type-' + item.type.toLowerCase() : ''}">
                            <!-- Article/Note content -->
                            <div class="form-group type-article type-note">
                                <label for="item-content">Content</label>
                                <textarea id="item-content" name="content" rows="10"
                                          placeholder="Content (supports Markdown)">${item ? item.content || '' : ''}</textarea>
                                <small>Supports Markdown formatting</small>
                            </div>
                            
                            <!-- Media URL -->
                            <div class="form-group type-image type-audio type-video">
                                <label for="item-url">Media URL</label>
                                <input type="url" id="item-url" name="url" 
                                       value="${item ? item.url || '' : ''}"
                                       placeholder="https://example.com/media.jpg">
                                <small>URL to the media file or upload using the media manager</small>
                            </div>
                            
                            <!-- Audio/Video specific fields -->
                            <div class="form-group type-audio type-video">
                                <label for="item-duration">Duration</label>
                                <input type="text" id="item-duration" name="duration" 
                                       value="${item ? item.duration || '' : ''}"
                                       placeholder="PT1H30M (ISO 8601 duration)">
                                <small>Duration in ISO 8601 format (e.g., PT1H30M for 1 hour 30 minutes)</small>
                            </div>
                            
                            <div class="form-group type-audio type-video type-image">
                                <label for="item-media-type">Media Type</label>
                                <input type="text" id="item-media-type" name="mediaType" 
                                       value="${item ? item.mediaType || '' : ''}"
                                       placeholder="image/jpeg, audio/mpeg, video/mp4">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="item-tags">Tags</label>
                            <input type="text" id="item-tags" name="tags" 
                                   value="${item && item.tag ? item.tag.join(', ') : ''}"
                                   placeholder="tag1, tag2, tag3">
                            <small>Comma-separated list of tags</small>
                        </div>
                        
                        <!-- Sub-Items Section -->
                        <div class="sub-items-section">
                            <div class="sub-items-header">
                                <button type="button" class="toggle-sub-items-btn button secondary">
                                    <span class="toggle-text">+ Add Another Type</span>
                                    <span class="toggle-icon">▼</span>
                                </button>
                                <small>Create multi-type content (e.g., Article + Audio for podcasts)</small>
                            </div>
                            
                            <div id="sub-items-content" class="sub-items-content" style="display: none;">
                                <div class="sub-items-list" id="sub-items-list">
                                    <!-- Sub-items will be rendered here -->
                                </div>
                                
                                <div class="sub-items-actions">
                                    <button type="button" class="add-sub-item-btn button secondary">
                                        + Add Sub-Item
                                    </button>
                                </div>
                                
                                <div class="collection-preview">
                                    <h4>Preview</h4>
                                    <div id="collection-preview-content" class="preview-content">
                                        <!-- Preview will be rendered here -->
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="modal-actions">
                            <button type="button" class="button secondary" onclick="this.closest('.modal-overlay').remove()">
                                Cancel
                            </button>
                            <button type="button" class="button secondary" onclick="window.mediaManager && window.mediaManager.showMediaManager('${this.currentFeedId}')">
                                📎 Media Manager
                            </button>
                            <button type="submit" class="button primary">
                                ${isEdit ? 'Update Item' : 'Add Item'}
                            </button>
                        </div>
                        
                        ${isEdit ? `<input type="hidden" name="itemId" value="${item.id}">` : ''}
                    </form>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Initialize sub-items for editing mode
        this.editingItemId = itemId;
        if (isEdit && item && item.type === 'Collection' && item.items) {
            this.currentSubItems = [...item.items];
            // Auto-expand sub-items section for Collections
            setTimeout(() => {
                const subItemsContent = document.getElementById('sub-items-content');
                const toggleBtn = document.querySelector('.toggle-sub-items-btn');
                if (subItemsContent && toggleBtn) {
                    subItemsContent.style.display = 'block';
                    const toggleText = toggleBtn.querySelector('.toggle-text');
                    const toggleIcon = toggleBtn.querySelector('.toggle-icon');
                    toggleText.textContent = '- Hide Additional Types';
                    toggleIcon.textContent = '▲';
                }
            }, 100);
        } else {
            this.currentSubItems = [];
        }
        
        // Bind form submission
        const form = document.getElementById('item-editor-form');
        form.addEventListener('submit', (e) => this.handleItemSubmit(e, isEdit));
        
        // Add real-time preview updates
        form.addEventListener('input', () => this.updateCollectionPreview());
        form.addEventListener('change', () => this.updateCollectionPreview());
        
        // Trigger initial type change to show correct fields
        const typeSelect = document.getElementById('item-type');
        if (typeSelect.value) {
            typeSelect.dispatchEvent(new Event('change'));
        }
        
        // Initialize sub-items display
        this.renderSubItemsList();
    }
    
    /**
     * Handle item form submission
     */
    async handleItemSubmit(event, isEdit = false) {
        event.preventDefault();
        
        const formData = new FormData(event.target);
        const mainItemData = {
            type: formData.get('type'),
            name: formData.get('name'),
            summary: formData.get('summary'),
            content: formData.get('content'),
            url: formData.get('url'),
            duration: formData.get('duration'),
            mediaType: formData.get('mediaType'),
            published: formData.get('published'),
            tag: formData.get('tags') ? formData.get('tags').split(',').map(tag => tag.trim()).filter(tag => tag) : []
        };
        
        // Remove empty values from main item
        Object.keys(mainItemData).forEach(key => {
            if (mainItemData[key] === '' || mainItemData[key] === null) {
                delete mainItemData[key];
            }
        });
        
        // Smart Collection logic: Determine if this should be a Collection
        let finalItemData;
        const allItems = [];
        
        // Add main item if it has meaningful content
        if (mainItemData.type && (mainItemData.content || mainItemData.url)) {
            allItems.push(mainItemData);
        }
        
        // Add sub-items
        allItems.push(...this.currentSubItems);
        
        if (allItems.length <= 1) {
            // Single item: use as-is
            finalItemData = mainItemData;
        } else {
            // Multiple items: create Collection
            const typesSummary = allItems.map(item => item.type).join(' and ');
            finalItemData = {
                type: 'Collection',
                name: mainItemData.name || `Multi-Type Content`,
                summary: mainItemData.summary || typesSummary,
                published: mainItemData.published,
                tag: mainItemData.tag,
                totalItems: allItems.length,
                items: allItems.map(item => {
                    // Remove main-item specific fields from sub-items
                    const cleanItem = { ...item };
                    delete cleanItem.published; // Collection handles published date
                    delete cleanItem.tag; // Collection handles tags
                    return cleanItem;
                })
            };
        }
        
        try {
            if (isEdit) {
                const itemId = formData.get('itemId');
                await this.updateItem(itemId, finalItemData);
            } else {
                await this.addItem(finalItemData);
            }
            
            // Close modal
            document.getElementById('item-editor-modal').remove();
            
            // Clear sub-items for next use
            this.currentSubItems = [];
            this.editingItemId = null;
            
            // Refresh item manager if open
            if (document.getElementById('item-manager-modal')) {
                this.refreshItemManager();
            }
            
            const collectionText = finalItemData.type === 'Collection' ? ' Collection' : '';
            this.showMessage('success', `Item${collectionText} ${isEdit ? 'updated' : 'added'} successfully!`);
            
        } catch (error) {
            this.showMessage('error', error.message);
        }
    }
    
    /**
     * Add item to feed
     */
    async addItem(itemData) {
        try {
            const response = await fetch(`${this.apiBaseUrl}/${this.currentFeedId}/items`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(itemData)
            });
            
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error.message);
            }
            
            this.notifyListeners('itemAdded', data.data);
            return data.data;
            
        } catch (error) {
            console.error('Failed to add item:', error);
            throw error;
        }
    }
    
    /**
     * Update item in feed
     */
    async updateItem(itemId, updateData) {
        try {
            const response = await fetch(`${this.apiBaseUrl}/${this.currentFeedId}/items/${itemId}`, {
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
            
            this.notifyListeners('itemUpdated', data.data);
            return data.data;
            
        } catch (error) {
            console.error('Failed to update item:', error);
            throw error;
        }
    }
    
    /**
     * Delete item from feed
     */
    async deleteItem(itemId) {
        if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
            return false;
        }
        
        try {
            const response = await fetch(`${this.apiBaseUrl}/${this.currentFeedId}/items/${itemId}`, {
                method: 'DELETE'
            });
            
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error.message);
            }
            
            // Refresh item manager if open
            if (document.getElementById('item-manager-modal')) {
                this.refreshItemManager();
            }
            
            this.notifyListeners('itemDeleted', { itemId });
            this.showMessage('success', 'Item deleted successfully!');
            return true;
            
        } catch (error) {
            console.error('Failed to delete item:', error);
            this.showMessage('error', 'Failed to delete item: ' + error.message);
            throw error;
        }
    }
    
    /**
     * Load items for current feed
     */
    async loadItems(feedId) {
        try {
            const response = await fetch(`${this.apiBaseUrl}/${feedId}/items`);
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error.message);
            }
            
            return data.data.items;
            
        } catch (error) {
            console.error('Failed to load items:', error);
            throw error;
        }
    }
    
    /**
     * Get specific item
     */
    async getItem(itemId) {
        try {
            const items = await this.loadItems(this.currentFeedId);
            const item = items.find(item => item.id === itemId);
            
            if (!item) {
                throw new Error('Item not found');
            }
            
            return item;
            
        } catch (error) {
            console.error('Failed to get item:', error);
            throw error;
        }
    }
    
    /**
     * Get feed info
     */
    async getFeed(feedId) {
        try {
            const response = await fetch(`${this.apiBaseUrl}/${feedId}`);
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error.message);
            }
            
            return data.data.feed;
            
        } catch (error) {
            console.error('Failed to get feed:', error);
            throw error;
        }
    }
    
    /**
     * Refresh item manager modal
     */
    async refreshItemManager() {
        try {
            const items = await this.loadItems(this.currentFeedId);
            const itemsList = document.getElementById('items-list');
            if (itemsList) {
                itemsList.innerHTML = this.renderItemsList(items);
            }
        } catch (error) {
            console.error('Failed to refresh item manager:', error);
        }
    }
    
    /**
     * Preview item
     */
    async previewItem(itemId) {
        try {
            const item = await this.getItem(itemId);
            this.showItemPreview(item);
        } catch (error) {
            alert('Failed to load item preview: ' + error.message);
        }
    }
    
    /**
     * Show item preview modal
     */
    showItemPreview(item) {
        const modalHtml = `
            <div id="item-preview-modal" class="modal-overlay">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Preview: ${item.name || 'Untitled'}</h3>
                        <button class="modal-close" onclick="this.closest('.modal-overlay').remove()">&times;</button>
                    </div>
                    <div class="modal-body item-preview">
                        <div class="item-meta">
                            <span class="item-type-badge item-type-${item.type.toLowerCase()}">${item.type}</span>
                            <span class="item-date">${this.formatDate(item.published || item.updated)}</span>
                        </div>
                        
                        ${item.summary ? `<p class="item-summary"><strong>Summary:</strong> ${item.summary}</p>` : ''}
                        
                        ${item.content ? `<div class="item-content">${this.formatMarkdown(item.content)}</div>` : ''}
                        
                        ${item.url ? `<p class="item-url"><strong>URL:</strong> <a href="${item.url}" target="_blank">${item.url}</a></p>` : ''}
                        
                        ${item.duration ? `<p class="item-duration"><strong>Duration:</strong> ${item.duration}</p>` : ''}
                        
                        ${item.mediaType ? `<p class="item-media-type"><strong>Media Type:</strong> ${item.mediaType}</p>` : ''}
                        
                        ${item.tag && item.tag.length > 0 ? `<p class="item-tags"><strong>Tags:</strong> ${item.tag.join(', ')}</p>` : ''}
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHtml);
    }
    
    /**
     * Format markdown content (basic)
     */
    formatMarkdown(content) {
        return content
            .replace(/^# (.+)$/gm, '<h1>$1</h1>')
            .replace(/^## (.+)$/gm, '<h2>$1</h2>')
            .replace(/^### (.+)$/gm, '<h3>$1</h3>')
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>')
            .replace(/\n/g, '<br>');
    }
    
    /**
     * Format date for display
     */
    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
    }
    
    /**
     * Format date for datetime-local input
     */
    formatDateForInput(dateString) {
        const date = new Date(dateString);
        return date.toISOString().slice(0, 16);
    }
    
    /**
     * Truncate text
     */
    truncateText(text, maxLength) {
        if (text.length <= maxLength) return text;
        return text.slice(0, maxLength) + '...';
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
     * Toggle sub-items section visibility
     */
    toggleSubItemsSection() {
        const content = document.getElementById('sub-items-content');
        const toggleBtn = document.querySelector('.toggle-sub-items-btn');
        const toggleText = toggleBtn.querySelector('.toggle-text');
        const toggleIcon = toggleBtn.querySelector('.toggle-icon');
        
        if (content.style.display === 'none') {
            content.style.display = 'block';
            toggleText.textContent = '- Hide Additional Types';
            toggleIcon.textContent = '▲';
            this.updateCollectionPreview();
        } else {
            content.style.display = 'none';
            toggleText.textContent = '+ Add Another Type';
            toggleIcon.textContent = '▼';
        }
    }
    
    /**
     * Show sub-item editor modal
     */
    showSubItemEditor(subItemIndex = null) {
        const isEdit = subItemIndex !== null;
        let subItem = null;
        
        if (isEdit && this.currentSubItems[subItemIndex]) {
            subItem = this.currentSubItems[subItemIndex];
        }
        
        const modalHtml = `
            <div id="sub-item-editor-modal" class="modal-overlay">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>${isEdit ? 'Edit Sub-Item' : 'Add Sub-Item'}</h3>
                        <button class="modal-close" onclick="this.closest('.modal-overlay').remove()">&times;</button>
                    </div>
                    <form id="sub-item-editor-form" class="modal-body">
                        <div class="form-group">
                            <label for="sub-item-type">Type *</label>
                            <select id="sub-item-type" name="type" required onchange="this.closest('form').querySelector('.sub-item-type-fields').className = 'sub-item-type-fields type-' + this.value.toLowerCase()">
                                <option value="">Select Type</option>
                                <option value="Article" ${subItem && subItem.type === 'Article' ? 'selected' : ''}>Article</option>
                                <option value="Note" ${subItem && subItem.type === 'Note' ? 'selected' : ''}>Note</option>
                                <option value="Image" ${subItem && subItem.type === 'Image' ? 'selected' : ''}>Image</option>
                                <option value="Audio" ${subItem && subItem.type === 'Audio' ? 'selected' : ''}>Audio</option>
                                <option value="Video" ${subItem && subItem.type === 'Video' ? 'selected' : ''}>Video</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="sub-item-name">Title</label>
                            <input type="text" id="sub-item-name" name="name" 
                                   value="${subItem ? subItem.name || '' : ''}" 
                                   placeholder="Sub-item title (optional)">
                        </div>
                        
                        <div class="sub-item-type-fields ${subItem ? 'type-' + subItem.type.toLowerCase() : ''}">
                            <!-- Article/Note content -->
                            <div class="form-group type-article type-note">
                                <label for="sub-item-content">Content</label>
                                <textarea id="sub-item-content" name="content" rows="8"
                                          placeholder="Content (supports Markdown)">${subItem ? subItem.content || '' : ''}</textarea>
                            </div>
                            
                            <!-- Media URL -->
                            <div class="form-group type-image type-audio type-video">
                                <label for="sub-item-url">Media URL</label>
                                <input type="url" id="sub-item-url" name="url" 
                                       value="${subItem ? subItem.url || '' : ''}"
                                       placeholder="https://example.com/media.jpg">
                            </div>
                            
                            <!-- Audio/Video specific fields -->
                            <div class="form-group type-audio type-video">
                                <label for="sub-item-duration">Duration</label>
                                <input type="text" id="sub-item-duration" name="duration" 
                                       value="${subItem ? subItem.duration || '' : ''}"
                                       placeholder="PT25M">
                            </div>
                            
                            <div class="form-group type-audio type-video type-image">
                                <label for="sub-item-media-type">Media Type</label>
                                <input type="text" id="sub-item-media-type" name="mediaType" 
                                       value="${subItem ? subItem.mediaType || '' : ''}"
                                       placeholder="audio/mpeg, video/mp4">
                            </div>
                        </div>
                        
                        <div class="modal-actions">
                            <button type="button" class="button secondary" onclick="this.closest('.modal-overlay').remove()">
                                Cancel
                            </button>
                            <button type="submit" class="button primary">
                                ${isEdit ? 'Update Sub-Item' : 'Add Sub-Item'}
                            </button>
                        </div>
                        
                        ${isEdit ? `<input type="hidden" name="subItemIndex" value="${subItemIndex}">` : ''}
                    </form>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Bind form submission
        const form = document.getElementById('sub-item-editor-form');
        form.addEventListener('submit', (e) => this.handleSubItemSubmit(e, isEdit, subItemIndex));
        
        // Trigger initial type change
        const typeSelect = document.getElementById('sub-item-type');
        if (typeSelect.value) {
            typeSelect.dispatchEvent(new Event('change'));
        }
    }
    
    /**
     * Handle sub-item form submission
     */
    handleSubItemSubmit(event, isEdit = false, subItemIndex = null) {
        event.preventDefault();
        
        const formData = new FormData(event.target);
        const subItemData = {
            type: formData.get('type'),
            name: formData.get('name'),
            content: formData.get('content'),
            url: formData.get('url'),
            duration: formData.get('duration'),
            mediaType: formData.get('mediaType')
        };
        
        // Remove empty values
        Object.keys(subItemData).forEach(key => {
            if (subItemData[key] === '' || subItemData[key] === null) {
                delete subItemData[key];
            }
        });
        
        if (isEdit) {
            this.currentSubItems[subItemIndex] = subItemData;
        } else {
            this.currentSubItems.push(subItemData);
        }
        
        // Close modal
        document.getElementById('sub-item-editor-modal').remove();
        
        // Update sub-items display
        this.renderSubItemsList();
        this.updateCollectionPreview();
    }
    
    /**
     * Delete sub-item
     */
    deleteSubItem(subItemIndex) {
        if (confirm('Are you sure you want to delete this sub-item?')) {
            this.currentSubItems.splice(subItemIndex, 1);
            this.renderSubItemsList();
            this.updateCollectionPreview();
        }
    }
    
    /**
     * Render sub-items list
     */
    renderSubItemsList() {
        const container = document.getElementById('sub-items-list');
        if (!container) return;
        
        if (this.currentSubItems.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <p>No sub-items added yet. Click "Add Sub-Item" to create multi-type content.</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = this.currentSubItems.map((subItem, index) => `
            <div class="sub-item-card" data-sub-item-index="${index}">
                <div class="sub-item-header">
                    <span class="sub-item-type-badge type-${subItem.type.toLowerCase()}">${subItem.type}</span>
                    <div class="sub-item-actions">
                        <button class="edit-sub-item-btn" data-sub-item-index="${index}" title="Edit">✏️</button>
                        <button class="delete-sub-item-btn" data-sub-item-index="${index}" title="Delete">🗑️</button>
                    </div>
                </div>
                <div class="sub-item-content">
                    ${subItem.name ? `<h5>${subItem.name}</h5>` : ''}
                    ${subItem.content ? `<p>${this.truncateText(subItem.content, 80)}</p>` : ''}
                    ${subItem.url ? `<p>🔗 ${subItem.url}</p>` : ''}
                </div>
            </div>
        `).join('');
    }
    
    /**
     * Update collection preview
     */
    updateCollectionPreview() {
        const container = document.getElementById('collection-preview-content');
        if (!container) return;
        
        const mainForm = document.getElementById('item-editor-form');
        const mainFormData = new FormData(mainForm);
        
        const mainItem = {
            type: mainFormData.get('type'),
            name: mainFormData.get('name'),
            summary: mainFormData.get('summary'),
            content: mainFormData.get('content'),
            url: mainFormData.get('url')
        };
        
        // Determine if this will be a Collection
        const allItems = [];
        
        // Add main item if it has content
        if (mainItem.type && (mainItem.content || mainItem.url)) {
            allItems.push(mainItem);
        }
        
        // Add sub-items
        allItems.push(...this.currentSubItems);
        
        if (allItems.length <= 1) {
            container.innerHTML = `
                <div class="preview-simple">
                    <strong>Simple Item</strong>
                    <p>This will be saved as a single ${mainItem.type || 'Item'}</p>
                </div>
            `;
        } else {
            const typesSummary = allItems.map(item => item.type).join(' and ');
            container.innerHTML = `
                <div class="preview-collection">
                    <strong>Collection Item</strong>
                    <p>This will be saved as a Collection with ${allItems.length} items:</p>
                    <ul>
                        ${allItems.map(item => `<li>${item.type}${item.name ? `: ${item.name}` : ''}</li>`).join('')}
                    </ul>
                    <p><em>Summary: ${typesSummary}</em></p>
                </div>
            `;
        }
    }
}

// Create global instance
window.feedItemManager = new FeedItemManager();