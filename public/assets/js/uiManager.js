/**
 * UI Manager - Handles user interface interactions and state management
 * Coordinates between different UI components and manages application state
 */

class UIManager {
    constructor() {
        this.feedManager = null;
        this.activityRenderer = null;
        
        // UI state
        this.currentView = 'grid';
        this.currentPage = 1;
        this.currentFilter = 'all';
        this.currentSort = 'published-desc';
        this.isLoading = false;
        this.searchQuery = '';
        this.searchTimeout = null;
        
        // DOM elements
        this.elements = {};
        
        // Event handlers
        this.boundHandlers = {};
        
        this.init();
    }
    
    /**
     * Initialize UI Manager
     */
    async init() {
        AnsyblConfig.utils.log('info', 'Initializing UIManager');
        
        try {
            // Cache DOM elements
            this.cacheElements();
            
            // Bind event handlers
            this.bindEvents();
            
            // Initialize components
            await this.initializeComponents();
            
            // Set initial UI state
            this.initializeUI();
            
            AnsyblConfig.utils.log('info', 'UIManager initialized successfully');
            
        } catch (error) {
            AnsyblConfig.utils.log('error', 'Failed to initialize UIManager', error);
            this.showError('Failed to initialize application');
        }
    }
    
    /**
     * Cache DOM elements for efficient access
     */
    cacheElements() {
        // Navigation elements
        this.elements.siteTitle = document.getElementById('site-title');
        this.elements.siteLogo = document.getElementById('site-logo-link');
        this.elements.navList = document.getElementById('nav-list');
        this.elements.mobileMenuToggle = document.getElementById('mobile-menu-toggle');
        
        // Search elements
        this.elements.siteSearch = document.getElementById('site-search');
        this.elements.searchButton = document.getElementById('search-button');
        
        // Loading and error states
        this.elements.loadingIndicator = document.getElementById('loading-indicator');
        this.elements.errorMessage = document.getElementById('error-message');
        this.elements.errorDetails = document.getElementById('error-details');
        this.elements.retryButton = document.getElementById('retry-button');
        
        // Content area
        this.elements.contentArea = document.getElementById('content-area');
        this.elements.activityStream = document.getElementById('activity-stream');
        
        // Controls
        this.elements.feedFilter = document.getElementById('feed-filter');
        this.elements.sortOrder = document.getElementById('sort-order');
        this.elements.gridView = document.getElementById('grid-view');
        this.elements.listView = document.getElementById('list-view');
        
        // Pagination
        this.elements.pagination = document.getElementById('pagination');
        this.elements.prevPage = document.getElementById('prev-page');
        this.elements.nextPage = document.getElementById('next-page');
        this.elements.pageInfo = document.getElementById('page-info');
        
        // Footer elements
        this.elements.currentYear = document.getElementById('current-year');
        this.elements.lastUpdated = document.getElementById('last-updated-time');
        this.elements.feedCount = document.getElementById('feed-count');
    }
    
    /**
     * Bind event handlers
     */
    bindEvents() {
        // Mobile menu toggle
        if (this.elements.mobileMenuToggle) {
            this.boundHandlers.mobileMenuToggle = this.handleMobileMenuToggle.bind(this);
            this.elements.mobileMenuToggle.addEventListener('click', this.boundHandlers.mobileMenuToggle);
        }
        
        // Search functionality
        if (this.elements.siteSearch) {
            this.boundHandlers.searchInput = this.handleSearchInput.bind(this);
            this.elements.siteSearch.addEventListener('input', this.boundHandlers.searchInput);
        }
        
        if (this.elements.searchButton) {
            this.boundHandlers.searchButton = this.handleSearchButton.bind(this);
            this.elements.searchButton.addEventListener('click', this.boundHandlers.searchButton);
        }
        
        // View controls
        if (this.elements.gridView) {
            this.boundHandlers.gridView = () => this.setView('grid');
            this.elements.gridView.addEventListener('click', this.boundHandlers.gridView);
        }
        
        if (this.elements.listView) {
            this.boundHandlers.listView = () => this.setView('list');
            this.elements.listView.addEventListener('click', this.boundHandlers.listView);
        }
        
        // Filter and sort controls
        if (this.elements.feedFilter) {
            this.boundHandlers.feedFilter = this.handleFeedFilter.bind(this);
            this.elements.feedFilter.addEventListener('change', this.boundHandlers.feedFilter);
        }
        
        if (this.elements.sortOrder) {
            this.boundHandlers.sortOrder = this.handleSortOrder.bind(this);
            this.elements.sortOrder.addEventListener('change', this.boundHandlers.sortOrder);
        }
        
        // Pagination
        if (this.elements.prevPage) {
            this.boundHandlers.prevPage = () => this.changePage(this.currentPage - 1);
            this.elements.prevPage.addEventListener('click', this.boundHandlers.prevPage);
        }
        
        if (this.elements.nextPage) {
            this.boundHandlers.nextPage = () => this.changePage(this.currentPage + 1);
            this.elements.nextPage.addEventListener('click', this.boundHandlers.nextPage);
        }
        
        // Retry button
        if (this.elements.retryButton) {
            this.boundHandlers.retryButton = this.handleRetry.bind(this);
            this.elements.retryButton.addEventListener('click', this.boundHandlers.retryButton);
        }
        
        // Window events
        this.boundHandlers.resize = this.handleResize.bind(this);
        window.addEventListener('resize', this.boundHandlers.resize);
        
        // Keyboard shortcuts
        this.boundHandlers.keydown = this.handleKeydown.bind(this);
        document.addEventListener('keydown', this.boundHandlers.keydown);
        
        // Custom events
        this.boundHandlers.tagClick = this.handleTagClick.bind(this);
        document.addEventListener('tagClick', this.boundHandlers.tagClick);
        
        // Visibility change (for background updates)
        this.boundHandlers.visibilityChange = this.handleVisibilityChange.bind(this);
        document.addEventListener('visibilitychange', this.boundHandlers.visibilityChange);
    }
    
    /**
     * Initialize components
     */
    async initializeComponents() {
        // Initialize FeedManager
        this.feedManager = new FeedManager();
        
        // Set up FeedManager event listeners
        this.feedManager.on('ready', () => {
            AnsyblConfig.utils.log('info', 'FeedManager ready');
            this.updateFeedFilter();
        });
        
        this.feedManager.on('loadStart', () => {
            this.showLoading();
        });
        
        this.feedManager.on('loadComplete', (items) => {
            this.hideLoading();
            this.renderContent(items);
            this.updateFooterInfo();
        });
        
        this.feedManager.on('error', (error) => {
            this.hideLoading();
            this.showError('Failed to load feeds: ' + error.message);
        });
        
        this.feedManager.on('feedUpdated', (feedId, feedData) => {
            AnsyblConfig.utils.log('info', `Feed updated: ${feedId}`);
            this.updateFeedFilter();
        });
        
        // Initialize ActivityRenderer
        this.activityRenderer = new ActivityRenderer();
        
        // Store references globally for access from other components
        window.uiManager = this;
        window.feedManager = this.feedManager;
        window.activityRenderer = this.activityRenderer;
    }
    
    /**
     * Initialize UI state
     */
    initializeUI() {
        // Set current year in footer
        if (this.elements.currentYear) {
            this.elements.currentYear.textContent = new Date().getFullYear();
        }
        
        // Set initial view
        this.setView(AnsyblConfig.ui.defaultView);
        
        // Hide error state initially
        this.hideError();
        
        // Initialize loading state
        this.showLoading();
        
        // Set up responsive behavior
        this.handleResize();
    }
    
    /**
     * Render content to the activity stream
     */
    renderContent(items = null) {
        if (!this.elements.activityStream) return;
        
        try {
            // Get items to render
            let itemsToRender = items;
            
            if (!itemsToRender) {
                // Apply current filters and get paginated results
                itemsToRender = this.getFilteredAndSortedItems();
            }
            
            // Clear existing content
            this.elements.activityStream.innerHTML = '';
            
            if (!itemsToRender || itemsToRender.length === 0) {
                this.showEmptyState();
                return;
            }
            
            // Render each item
            itemsToRender.forEach(item => {
                try {
                    this.activityRenderer.render(item, this.elements.activityStream);
                } catch (error) {
                    AnsyblConfig.utils.log('error', 'Failed to render activity item', error);
                }
            });
            
            // Update pagination
            this.updatePagination();
            
            // Hide error state if visible
            this.hideError();
            
        } catch (error) {
            AnsyblConfig.utils.log('error', 'Failed to render content', error);
            this.showError('Failed to display content');
        }
    }
    
    /**
     * Get filtered and sorted items with pagination
     */
    getFilteredAndSortedItems() {
        if (!this.feedManager) return [];
        
        let items = [];
        
        // Apply search filter
        if (this.searchQuery && this.searchQuery.length >= AnsyblConfig.search.minLength) {
            items = this.feedManager.searchItems(this.searchQuery);
        } else {
            // Apply feed filter
            const options = this.currentFilter !== 'all' ? { feedId: this.currentFilter } : {};
            items = this.feedManager.getAllItems(options);
        }
        
        // Apply sorting
        items = this.sortItems(items, this.currentSort);
        
        // Apply pagination
        const startIndex = (this.currentPage - 1) * AnsyblConfig.ui.itemsPerPage;
        const endIndex = startIndex + AnsyblConfig.ui.itemsPerPage;
        
        return items.slice(startIndex, endIndex);
    }
    
    /**
     * Sort items based on sort criteria
     */
    sortItems(items, sortOrder) {
        return items.slice().sort((a, b) => {
            switch (sortOrder) {
                case 'published-desc':
                    return new Date(b.published) - new Date(a.published);
                    
                case 'published-asc':
                    return new Date(a.published) - new Date(b.published);
                    
                case 'updated-desc':
                    const updatedA = a.updated || a.published;
                    const updatedB = b.updated || b.published;
                    return new Date(updatedB) - new Date(updatedA);
                    
                case 'title-asc':
                    const titleA = (a.name || a.objectName || '').toLowerCase();
                    const titleB = (b.name || b.objectName || '').toLowerCase();
                    return titleA.localeCompare(titleB);
                    
                default:
                    return 0;
            }
        });
    }
    
    /**
     * Update feed filter dropdown
     */
    updateFeedFilter() {
        if (!this.elements.feedFilter || !this.feedManager) return;
        
        try {
            const feedInfo = this.feedManager.getFeedInfo();
            
            // Clear existing options (except "All Feeds")
            const allOption = this.elements.feedFilter.querySelector('option[value="all"]');
            this.elements.feedFilter.innerHTML = '';
            if (allOption) {
                this.elements.feedFilter.appendChild(allOption);
            } else {
                const defaultOption = document.createElement('option');
                defaultOption.value = 'all';
                defaultOption.textContent = 'All Feeds';
                this.elements.feedFilter.appendChild(defaultOption);
            }
            
            // Add feed options
            feedInfo.forEach(feed => {
                if (feed.status === 'active') {
                    const option = document.createElement('option');
                    option.value = feed.id;
                    option.textContent = `${feed.name} (${feed.itemCount})`;
                    this.elements.feedFilter.appendChild(option);
                }
            });
            
        } catch (error) {
            AnsyblConfig.utils.log('error', 'Failed to update feed filter', error);
        }
    }
    
    /**
     * Update navigation menu
     */
    updateNavigation() {
        if (!this.elements.navList || !this.feedManager) return;
        
        try {
            const feedInfo = this.feedManager.getFeedInfo();
            
            // Clear existing navigation items
            this.elements.navList.innerHTML = '';
            
            // Add home link
            const homeItem = document.createElement('li');
            homeItem.innerHTML = '<a href="#" class="nav-link active" data-filter="all">All Feeds</a>';
            this.elements.navList.appendChild(homeItem);
            
            // Add feed links
            feedInfo.forEach(feed => {
                if (feed.status === 'active') {
                    const listItem = document.createElement('li');
                    listItem.innerHTML = `<a href="#" class="nav-link" data-filter="${feed.id}">${feed.name}</a>`;
                    this.elements.navList.appendChild(listItem);
                }
            });
            
            // Bind navigation click events
            this.elements.navList.addEventListener('click', (e) => {
                if (e.target.classList.contains('nav-link')) {
                    e.preventDefault();
                    
                    // Update active state
                    this.elements.navList.querySelectorAll('.nav-link').forEach(link => {
                        link.classList.remove('active');
                    });
                    e.target.classList.add('active');
                    
                    // Apply filter
                    const filter = e.target.dataset.filter;
                    this.setFilter(filter);
                }
            });
            
        } catch (error) {
            AnsyblConfig.utils.log('error', 'Failed to update navigation', error);
        }
    }
    
    /**
     * Update pagination controls
     */
    updatePagination() {
        if (!this.elements.pagination || !this.feedManager) return;
        
        try {
            const totalItems = this.getTotalItemsCount();
            const totalPages = Math.ceil(totalItems / AnsyblConfig.ui.itemsPerPage);
            
            // Update page info
            if (this.elements.pageInfo) {
                this.elements.pageInfo.textContent = `Page ${this.currentPage} of ${totalPages}`;
            }
            
            // Update button states
            if (this.elements.prevPage) {
                this.elements.prevPage.disabled = this.currentPage <= 1;
            }
            
            if (this.elements.nextPage) {
                this.elements.nextPage.disabled = this.currentPage >= totalPages;
            }
            
            // Hide pagination if only one page
            if (totalPages <= 1) {
                this.elements.pagination.style.display = 'none';
            } else {
                this.elements.pagination.style.display = 'flex';
            }
            
        } catch (error) {
            AnsyblConfig.utils.log('error', 'Failed to update pagination', error);
        }
    }
    
    /**
     * Get total count of items based on current filters
     */
    getTotalItemsCount() {
        if (!this.feedManager) return 0;
        
        if (this.searchQuery && this.searchQuery.length >= AnsyblConfig.search.minLength) {
            return this.feedManager.searchItems(this.searchQuery).length;
        } else {
            const options = this.currentFilter !== 'all' ? { feedId: this.currentFilter } : {};
            return this.feedManager.getAllItems(options).length;
        }
    }
    
    /**
     * Update footer information
     */
    updateFooterInfo() {
        try {
            // Update last updated time
            if (this.elements.lastUpdated && this.feedManager.lastUpdate) {
                this.elements.lastUpdated.textContent = AnsyblConfig.utils.formatDate(this.feedManager.lastUpdate);
            }
            
            // Update feed count
            if (this.elements.feedCount && this.feedManager) {
                const feedInfo = this.feedManager.getFeedInfo();
                const activeFeeds = feedInfo.filter(f => f.status === 'active');
                this.elements.feedCount.textContent = `${activeFeeds.length} feeds loaded`;
            }
            
        } catch (error) {
            AnsyblConfig.utils.log('error', 'Failed to update footer info', error);
        }
    }
    
    /**
     * Event Handlers
     */
    
    handleMobileMenuToggle() {
        if (!this.elements.navList) return;
        
        this.elements.navList.classList.toggle('show');
        
        // Update hamburger animation
        this.elements.mobileMenuToggle.classList.toggle('active');
    }
    
    handleSearchInput(e) {
        const query = e.target.value.trim();
        
        // Debounce search
        if (this.searchTimeout) {
            clearTimeout(this.searchTimeout);
        }
        
        this.searchTimeout = setTimeout(() => {
            this.searchQuery = query;
            this.currentPage = 1; // Reset to first page
            this.renderContent();
        }, AnsyblConfig.search.debounceDelay);
    }
    
    handleSearchButton() {
        const query = this.elements.siteSearch.value.trim();
        this.searchQuery = query;
        this.currentPage = 1;
        this.renderContent();
    }
    
    handleFeedFilter(e) {
        this.setFilter(e.target.value);
    }
    
    handleSortOrder(e) {
        this.setSort(e.target.value);
    }
    
    handleRetry() {
        this.hideError();
        if (this.feedManager) {
            this.feedManager.fetchAllFeeds(true); // Force refresh
        }
    }
    
    handleResize() {
        // Update mobile menu state based on screen size
        if (AnsyblConfig.utils.isViewport('mobile')) {
            // Mobile view
            if (this.elements.navList) {
                this.elements.navList.classList.remove('show');
            }
        } else {
            // Desktop view - show navigation
            if (this.elements.navList) {
                this.elements.navList.classList.remove('show');
            }
        }
    }
    
    handleKeydown(e) {
        // Keyboard shortcuts
        if (e.ctrlKey || e.metaKey) {
            switch (e.key) {
                case 'r':
                    // Ctrl+R: Refresh feeds
                    e.preventDefault();
                    if (this.feedManager) {
                        this.feedManager.fetchAllFeeds(true);
                    }
                    break;
                    
                case 'f':
                    // Ctrl+F: Focus search
                    e.preventDefault();
                    if (this.elements.siteSearch) {
                        this.elements.siteSearch.focus();
                    }
                    break;
            }
        }
        
        // Arrow key navigation for pagination
        if (!e.target.matches('input, textarea')) {
            switch (e.key) {
                case 'ArrowLeft':
                    if (this.currentPage > 1) {
                        this.changePage(this.currentPage - 1);
                    }
                    break;
                    
                case 'ArrowRight':
                    const totalPages = Math.ceil(this.getTotalItemsCount() / AnsyblConfig.ui.itemsPerPage);
                    if (this.currentPage < totalPages) {
                        this.changePage(this.currentPage + 1);
                    }
                    break;
            }
        }
    }
    
    handleTagClick(e) {
        const tag = e.detail.tag;
        
        // Set search query to tag
        this.searchQuery = tag;
        if (this.elements.siteSearch) {
            this.elements.siteSearch.value = tag;
        }
        
        this.currentPage = 1;
        this.renderContent();
    }
    
    handleVisibilityChange() {
        // Refresh feeds when page becomes visible (if background updates enabled)
        if (!document.hidden && AnsyblConfig.feeds.backgroundUpdate && this.feedManager) {
            const timeSinceUpdate = Date.now() - ((this.feedManager.lastUpdate && this.feedManager.lastUpdate.getTime()) || 0);
            
            // Refresh if it's been more than the update interval
            if (timeSinceUpdate > AnsyblConfig.feeds.updateInterval) {
                this.feedManager.fetchAllFeeds();
            }
        }
    }
    
    /**
     * Public Methods
     */
    
    setView(view) {
        if (view !== 'grid' && view !== 'list') return;
        
        this.currentView = view;
        
        // Update view button states
        if (this.elements.gridView && this.elements.listView) {
            this.elements.gridView.classList.toggle('active', view === 'grid');
            this.elements.listView.classList.toggle('active', view === 'list');
        }
        
        // Update activity stream classes
        if (this.elements.activityStream) {
            this.elements.activityStream.className = `activity-stream ${view}-view`;
        }
        
        AnsyblConfig.utils.log('debug', `View changed to: ${view}`);
    }
    
    setFilter(filter) {
        this.currentFilter = filter;
        this.currentPage = 1; // Reset to first page
        
        // Update filter dropdown
        if (this.elements.feedFilter) {
            this.elements.feedFilter.value = filter;
        }
        
        // Clear search when applying feed filter
        if (filter !== 'all') {
            this.searchQuery = '';
            if (this.elements.siteSearch) {
                this.elements.siteSearch.value = '';
            }
        }
        
        this.renderContent();
        
        AnsyblConfig.utils.log('debug', `Filter changed to: ${filter}`);
    }
    
    setSort(sort) {
        this.currentSort = sort;
        this.renderContent();
        
        AnsyblConfig.utils.log('debug', `Sort changed to: ${sort}`);
    }
    
    /**
     * Filter by specific feed (called from menu)
     */
    filterByFeed(feedId) {
        this.setFilter(feedId);
    }
    
    /**
     * Show all feeds (called from menu)
     */
    showAllFeeds() {
        this.setFilter('all');
    }
    
    changePage(page) {
        const totalPages = Math.ceil(this.getTotalItemsCount() / AnsyblConfig.ui.itemsPerPage);
        
        if (page < 1 || page > totalPages) return;
        
        this.currentPage = page;
        this.renderContent();
        
        // Scroll to top of content
        if (this.elements.contentArea) {
            this.elements.contentArea.scrollIntoView({ behavior: 'smooth' });
        }
        
        AnsyblConfig.utils.log('debug', `Page changed to: ${page}`);
    }
    
    /**
     * State Management
     */
    
    showLoading() {
        this.isLoading = true;
        
        if (this.elements.loadingIndicator) {
            this.elements.loadingIndicator.setAttribute('aria-hidden', 'false');
        }
        
        if (this.elements.contentArea) {
            this.elements.contentArea.style.opacity = '0.5';
        }
    }
    
    hideLoading() {
        this.isLoading = false;
        
        if (this.elements.loadingIndicator) {
            this.elements.loadingIndicator.setAttribute('aria-hidden', 'true');
        }
        
        if (this.elements.contentArea) {
            this.elements.contentArea.style.opacity = '1';
        }
    }
    
    showError(message, details = null) {
        if (this.elements.errorMessage) {
            this.elements.errorMessage.setAttribute('aria-hidden', 'false');
        }
        
        if (this.elements.errorDetails && message) {
            this.elements.errorDetails.textContent = message;
        }
        
        if (details && this.elements.errorDetails) {
            this.elements.errorDetails.textContent += '\n' + details;
        }
        
        // Hide content area when showing error
        if (this.elements.contentArea) {
            this.elements.contentArea.style.display = 'none';
        }
    }
    
    hideError() {
        if (this.elements.errorMessage) {
            this.elements.errorMessage.setAttribute('aria-hidden', 'true');
        }
        
        // Show content area when hiding error
        if (this.elements.contentArea) {
            this.elements.contentArea.style.display = 'block';
        }
    }
    
    showEmptyState() {
        if (!this.elements.activityStream) return;
        
        const emptyState = document.createElement('div');
        emptyState.className = 'empty-state';
        emptyState.innerHTML = `
            <div class="empty-state-content">
                <h3>No content found</h3>
                <p>No activities match your current filters.</p>
                ${this.searchQuery ? '<button class="clear-search-button">Clear search</button>' : ''}
            </div>
        `;
        
        // Add clear search functionality
        const clearButton = emptyState.querySelector('.clear-search-button');
        if (clearButton) {
            clearButton.addEventListener('click', () => {
                this.searchQuery = '';
                if (this.elements.siteSearch) {
                    this.elements.siteSearch.value = '';
                }
                this.renderContent();
            });
        }
        
        this.elements.activityStream.appendChild(emptyState);
    }
    
    /**
     * Cleanup
     */
    destroy() {
        // Remove event listeners
        Object.entries(this.boundHandlers).forEach(([event, handler]) => {
            // Remove specific event listeners (implementation depends on how they were added)
        });
        
        // Clear timeouts
        if (this.searchTimeout) {
            clearTimeout(this.searchTimeout);
        }
        
        // Clear references
        this.feedManager = null;
        this.activityRenderer = null;
        this.elements = {};
        this.boundHandlers = {};
        
        AnsyblConfig.utils.log('info', 'UIManager destroyed');
    }
}

// Make UIManager globally available
window.UIManager = UIManager;