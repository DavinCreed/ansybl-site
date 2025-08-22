/**
 * Menu Manager
 * Handles navigation menu management in the admin interface
 */

class MenuManager {
    constructor() {
        this.apiBaseUrl = '../api/config.php';
        this.currentMenu = {};
        this.draggedItem = null;
        this.eventListeners = [];
        this.hasUnsavedChanges = false;
        
        this.init();
    }
    
    async init() {
        await this.loadMenuConfig();
        this.bindEvents();
        this.renderMenuBuilder();
        this.setupDragAndDrop();
    }
    
    /**
     * Load menu configuration from API
     */
    async loadMenuConfig() {
        try {
            const response = await fetch(`${this.apiBaseUrl}/menu`);
            const data = await response.json();
            
            if (data.success && data.data) {
                this.currentMenu = data.data;
            } else {
                console.warn('Failed to load menu config, using defaults');
                this.currentMenu = this.getDefaultMenuConfig();
            }
        } catch (error) {
            console.error('Failed to load menu config:', error);
            this.currentMenu = this.getDefaultMenuConfig();
        }
        
        // Ensure menu structure exists
        if (!this.currentMenu.menus) {
            this.currentMenu.menus = {};
        }
        
        if (!this.currentMenu.menus.primary) {
            this.currentMenu.menus.primary = {
                name: 'Primary Navigation',
                location: 'header',
                items: []
            };
        }
        
        if (!this.currentMenu.menus.primary.items) {
            this.currentMenu.menus.primary.items = [];
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
                            target: '_self',
                            css_class: '',
                            icon: ''
                        }
                    ]
                }
            }
        };
    }
    
    /**
     * Bind event listeners
     */
    bindEvents() {
        // Add menu item button
        document.addEventListener('click', (e) => {
            if (e.target.matches('.add-menu-item-btn')) {
                this.showMenuItemEditor();
            } else if (e.target.matches('.edit-menu-item-btn')) {
                const itemId = e.target.dataset.itemId;
                this.showMenuItemEditor(itemId);
            } else if (e.target.matches('.delete-menu-item-btn')) {
                const itemId = e.target.dataset.itemId;
                this.deleteMenuItem(itemId);
            } else if (e.target.matches('.save-menu-btn')) {
                this.saveMenuConfig();
            } else if (e.target.matches('.reset-menu-btn')) {
                this.resetMenuToDefaults();
            }
        });
        
        // Prevent leaving with unsaved changes
        window.addEventListener('beforeunload', (e) => {
            if (this.hasUnsavedChanges) {
                e.preventDefault();
                e.returnValue = 'You have unsaved menu changes. Are you sure you want to leave?';
                return e.returnValue;
            }
        });
    }
    
    /**
     * Render menu builder interface
     */
    renderMenuBuilder() {
        const container = document.getElementById('menu-builder');
        if (!container) return;
        
        // Ensure menu structure exists before rendering
        if (!this.currentMenu || !this.currentMenu.menus) {
            this.currentMenu = this.getDefaultMenuConfig();
        }
        
        const primaryMenu = this.currentMenu.menus?.primary || { items: [] };
        
        container.innerHTML = `
            <div class="menu-builder-header">
                <h4>Primary Navigation Menu</h4>
                <div class="menu-actions">
                    <button class="button secondary add-menu-item-btn">+ Add Menu Item</button>
                    <button class="button secondary reset-menu-btn">Reset to Defaults</button>
                    <button class="button primary save-menu-btn">Save Menu</button>
                </div>
            </div>
            
            <div class="menu-builder-content">
                <div class="menu-items-list" id="menu-items-list">
                    ${this.renderMenuItems(primaryMenu.items)}
                </div>
                
                <div class="menu-preview">
                    <h5>Preview</h5>
                    <nav class="menu-preview-nav">
                        <ul class="preview-nav-list">
                            ${this.renderMenuPreview(primaryMenu.items)}
                        </ul>
                    </nav>
                </div>
            </div>
        `;
    }
    
    /**
     * Render menu items for editing
     */
    renderMenuItems(items) {
        if (!items || items.length === 0) {
            return `
                <div class="empty-menu-state">
                    <p>No menu items yet. Click "Add Menu Item" to get started.</p>
                </div>
            `;
        }
        
        return items.map((item, index) => `
            <div class="menu-item-card" data-item-id="${item.id}" data-order="${item.order || index}" draggable="true">
                <div class="menu-item-header">
                    <div class="menu-item-info">
                        <span class="menu-item-type-badge type-${item.type}">${item.type}</span>
                        <span class="menu-item-title">${item.title}</span>
                        ${!item.visible ? '<span class="visibility-badge hidden">Hidden</span>' : ''}
                    </div>
                    <div class="menu-item-actions">
                        <button class="drag-handle" title="Drag to reorder">⋮⋮</button>
                        <button class="edit-menu-item-btn" data-item-id="${item.id}" title="Edit">✏️</button>
                        <button class="delete-menu-item-btn" data-item-id="${item.id}" title="Delete">🗑️</button>
                    </div>
                </div>
                <div class="menu-item-details">
                    ${item.type === 'link' ? `<small>URL: ${item.url}</small>` : ''}
                    ${item.type === 'feed' ? `<small>Feed: ${item.feed_id || 'None selected'}</small>` : ''}
                    ${item.css_class ? `<small>CSS Class: ${item.css_class}</small>` : ''}
                </div>
            </div>
        `).join('');
    }
    
    /**
     * Render menu preview
     */
    renderMenuPreview(items) {
        if (!items || items.length === 0) {
            return '<li><em>No menu items</em></li>';
        }
        
        return items
            .filter(item => item.visible !== false)
            .sort((a, b) => (a.order || 0) - (b.order || 0))
            .map(item => `
                <li class="preview-nav-item ${item.css_class}">
                    ${item.icon ? `<span class="menu-icon">${item.icon}</span>` : ''}
                    <a href="${item.url || '#'}" target="${item.target || '_self'}">${item.title}</a>
                </li>
            `).join('');
    }
    
    /**
     * Setup drag and drop functionality
     */
    setupDragAndDrop() {
        const container = document.getElementById('menu-items-list');
        if (!container) return;
        
        container.addEventListener('dragstart', (e) => {
            if (e.target.classList.contains('menu-item-card')) {
                this.draggedItem = e.target;
                e.target.style.opacity = '0.5';
                e.dataTransfer.effectAllowed = 'move';
            }
        });
        
        container.addEventListener('dragend', (e) => {
            if (e.target.classList.contains('menu-item-card')) {
                e.target.style.opacity = '';
                this.draggedItem = null;
            }
        });
        
        container.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
        });
        
        container.addEventListener('drop', (e) => {
            e.preventDefault();
            
            if (!this.draggedItem) return;
            
            const dropTarget = e.target.closest('.menu-item-card');
            if (dropTarget && dropTarget !== this.draggedItem) {
                const container = dropTarget.parentNode;
                const afterElement = this.getDragAfterElement(container, e.clientY);
                
                if (afterElement == null) {
                    container.appendChild(this.draggedItem);
                } else {
                    container.insertBefore(this.draggedItem, afterElement);
                }
                
                this.updateMenuOrder();
            }
        });
    }
    
    /**
     * Get element after drag position
     */
    getDragAfterElement(container, y) {
        const draggableElements = [...container.querySelectorAll('.menu-item-card:not([style*="opacity: 0.5"])')];
        
        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            
            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }
    
    /**
     * Update menu order after drag and drop
     */
    updateMenuOrder() {
        const menuItems = document.querySelectorAll('.menu-item-card');
        const items = this.currentMenu.menus.primary.items;
        
        menuItems.forEach((card, index) => {
            const itemId = card.dataset.itemId;
            const item = items.find(item => item.id === itemId);
            if (item) {
                item.order = index + 1;
            }
        });
        
        // Sort items by order
        this.currentMenu.menus.primary.items.sort((a, b) => (a.order || 0) - (b.order || 0));
        
        this.hasUnsavedChanges = true;
        this.updateSaveButtonState();
        this.renderMenuBuilder(); // Re-render to show new order
    }
    
    /**
     * Show menu item editor modal
     */
    showMenuItemEditor(itemId = null) {
        const isEdit = itemId !== null;
        let item = null;
        
        if (isEdit) {
            item = this.currentMenu.menus.primary.items.find(i => i.id === itemId);
        }
        
        const modalHtml = `
            <div id="menu-item-editor-modal" class="modal-overlay">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>${isEdit ? 'Edit Menu Item' : 'Add Menu Item'}</h3>
                        <button class="modal-close" onclick="this.closest('.modal-overlay').remove()">&times;</button>
                    </div>
                    <form id="menu-item-editor-form" class="modal-body">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="menu-item-type">Type *</label>
                                <select id="menu-item-type" name="type" required onchange="this.closest('form').querySelector('.menu-type-fields').className = 'menu-type-fields type-' + this.value">
                                    <option value="">Select Type</option>
                                    <option value="link" ${item && item.type === 'link' ? 'selected' : ''}>Link</option>
                                    <option value="feed" ${item && item.type === 'feed' ? 'selected' : ''}>Feed</option>
                                    <option value="custom" ${item && item.type === 'custom' ? 'selected' : ''}>Custom URL</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="menu-item-title">Title *</label>
                                <input type="text" id="menu-item-title" name="title" required 
                                       value="${item ? item.title || '' : ''}" 
                                       placeholder="Menu item title">
                            </div>
                        </div>
                        
                        <div class="menu-type-fields ${item ? 'type-' + item.type : ''}">
                            <div class="form-group type-link type-custom">
                                <label for="menu-item-url">URL</label>
                                <input type="url" id="menu-item-url" name="url" 
                                       value="${item ? item.url || '' : ''}"
                                       placeholder="https://example.com or /page">
                            </div>
                            
                            <div class="form-group type-feed">
                                <label for="menu-item-feed">Feed</label>
                                <select id="menu-item-feed" name="feed_id">
                                    <option value="">Select a feed</option>
                                    <!-- Feed options will be populated dynamically -->
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="menu-item-target">Target</label>
                                <select id="menu-item-target" name="target">
                                    <option value="_self" ${item && item.target === '_self' ? 'selected' : ''}>Same window</option>
                                    <option value="_blank" ${item && item.target === '_blank' ? 'selected' : ''}>New window</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="menu-item-icon">Icon</label>
                                <input type="text" id="menu-item-icon" name="icon" 
                                       value="${item ? item.icon || '' : ''}"
                                       placeholder="🏠 or icon class">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="menu-item-css-class">CSS Class</label>
                            <input type="text" id="menu-item-css-class" name="css_class" 
                                   value="${item ? item.css_class || '' : ''}"
                                   placeholder="custom-class highlight">
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" id="menu-item-visible" name="visible" 
                                       ${item ? (item.visible !== false ? 'checked' : '') : 'checked'}>
                                Visible in menu
                            </label>
                        </div>
                        
                        <div class="modal-actions">
                            <button type="button" class="button secondary" onclick="this.closest('.modal-overlay').remove()">
                                Cancel
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
        
        // Bind form submission
        const form = document.getElementById('menu-item-editor-form');
        form.addEventListener('submit', (e) => this.handleMenuItemSubmit(e, isEdit, itemId));
        
        // Trigger initial type change
        const typeSelect = document.getElementById('menu-item-type');
        if (typeSelect.value) {
            typeSelect.dispatchEvent(new Event('change'));
        }
        
        // Populate feed options if needed
        this.populateFeedOptions();
    }
    
    /**
     * Populate feed options in dropdown
     */
    async populateFeedOptions() {
        const feedSelect = document.getElementById('menu-item-feed');
        if (!feedSelect) return;
        
        try {
            // Load both external and local feeds
            const [externalResponse, localResponse] = await Promise.all([
                fetch('../api/feeds.php'),
                fetch('../api/local-feeds.php').catch(() => ({ json: () => ({ success: false }) }))
            ]);
            
            const externalData = await externalResponse.json();
            const localData = await localResponse.json();
            
            let options = '<option value="">Select a feed</option>';
            
            // Add external feeds
            if (externalData.success && externalData.data?.feeds) {
                externalData.data.feeds.forEach(feed => {
                    options += `<option value="external:${feed.id}">📡 ${feed.displayName || feed.url}</option>`;
                });
            }
            
            // Add local feeds
            if (localData.success && localData.data && localData.data.feeds) {
                localData.data.feeds.forEach(feed => {
                    options += `<option value="local:${feed.id}">📍 ${feed.name}</option>`;
                });
            }
            
            feedSelect.innerHTML = options;
            
        } catch (error) {
            console.error('Failed to load feeds for menu:', error);
        }
    }
    
    /**
     * Handle menu item form submission
     */
    handleMenuItemSubmit(event, isEdit = false, itemId = null) {
        event.preventDefault();
        
        const formData = new FormData(event.target);
        const itemData = {
            id: itemId || 'menu-item-' + Date.now(),
            type: formData.get('type'),
            title: formData.get('title'),
            url: formData.get('url'),
            feed_id: formData.get('feed_id'),
            target: formData.get('target') || '_self',
            icon: formData.get('icon') || '',
            css_class: formData.get('css_class') || '',
            visible: formData.get('visible') === 'on',
            order: isEdit ? null : this.getNextOrder()
        };
        
        // Remove empty values
        Object.keys(itemData).forEach(key => {
            if (itemData[key] === '' || itemData[key] === null) {
                delete itemData[key];
            }
        });
        
        // Set URL based on type
        if (itemData.type === 'feed' && itemData.feed_id) {
            itemData.url = `#feed-${itemData.feed_id}`;
        }
        
        if (isEdit) {
            // Update existing item
            const items = this.currentMenu.menus.primary.items;
            const index = items.findIndex(item => item.id === itemId);
            if (index !== -1) {
                items[index] = { ...items[index], ...itemData };
            }
        } else {
            // Add new item
            // Ensure menu structure exists
            if (!this.currentMenu.menus) {
                this.currentMenu.menus = {};
            }
            if (!this.currentMenu.menus.primary) {
                this.currentMenu.menus.primary = {
                    name: 'Primary Navigation',
                    location: 'header',
                    items: []
                };
            }
            if (!this.currentMenu.menus.primary.items) {
                this.currentMenu.menus.primary.items = [];
            }
            this.currentMenu.menus.primary.items.push(itemData);
        }
        
        // Close modal
        document.getElementById('menu-item-editor-modal').remove();
        
        this.hasUnsavedChanges = true;
        this.updateSaveButtonState();
        this.renderMenuBuilder();
        
        this.showMessage('success', `Menu item ${isEdit ? 'updated' : 'added'} successfully!`);
    }
    
    /**
     * Get next order number
     */
    getNextOrder() {
        // Ensure menu structure exists
        if (!this.currentMenu || !this.currentMenu.menus || !this.currentMenu.menus.primary) {
            return 1;
        }
        
        const items = this.currentMenu.menus.primary.items || [];
        return Math.max(0, ...items.map(item => item.order || 0)) + 1;
    }
    
    /**
     * Delete menu item
     */
    deleteMenuItem(itemId) {
        if (!confirm('Are you sure you want to delete this menu item?')) {
            return;
        }
        
        const items = this.currentMenu.menus.primary.items;
        const index = items.findIndex(item => item.id === itemId);
        
        if (index !== -1) {
            items.splice(index, 1);
            this.hasUnsavedChanges = true;
            this.updateSaveButtonState();
            this.renderMenuBuilder();
            this.showMessage('success', 'Menu item deleted successfully!');
        }
    }
    
    /**
     * Save menu configuration
     */
    async saveMenuConfig() {
        if (!this.hasUnsavedChanges) {
            this.showMessage('info', 'No changes to save');
            return;
        }
        
        try {
            const response = await fetch(`${this.apiBaseUrl}/menu`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(this.currentMenu)
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.hasUnsavedChanges = false;
                this.updateSaveButtonState();
                this.showMessage('success', 'Menu configuration saved successfully!');
                this.notifyListeners('menuSaved', this.currentMenu);
            } else {
                throw new Error(data.error?.message || 'Failed to save menu configuration');
            }
            
        } catch (error) {
            console.error('Failed to save menu config:', error);
            this.showMessage('error', 'Failed to save menu configuration: ' + error.message);
        }
    }
    
    /**
     * Reset menu to defaults
     */
    resetMenuToDefaults() {
        if (!confirm('Are you sure you want to reset the menu to defaults? This will remove all custom menu items.')) {
            return;
        }
        
        this.currentMenu = this.getDefaultMenuConfig();
        this.hasUnsavedChanges = true;
        this.updateSaveButtonState();
        this.renderMenuBuilder();
        this.showMessage('info', 'Menu reset to defaults. Click Save to apply changes.');
    }
    
    /**
     * Update save button state
     */
    updateSaveButtonState() {
        const saveButton = document.querySelector('.save-menu-btn');
        if (saveButton) {
            if (this.hasUnsavedChanges) {
                saveButton.textContent = 'Save Changes';
                saveButton.style.background = '#e67e22';
                saveButton.disabled = false;
            } else {
                saveButton.textContent = 'Menu Saved';
                saveButton.style.background = '#27ae60';
                saveButton.disabled = true;
            }
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
     * Notify event listeners
     */
    notifyListeners(eventType, data) {
        this.eventListeners
            .filter(listener => listener.eventType === eventType)
            .forEach(listener => {
                try {
                    listener.callback(data);
                } catch (error) {
                    console.error('Error in menu event listener:', error);
                }
            });
    }
    
    /**
     * Get current menu configuration
     */
    getCurrentMenu() {
        return { ...this.currentMenu };
    }
}

// Create global instance
window.menuManager = new MenuManager();