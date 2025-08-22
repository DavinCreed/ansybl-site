/**
 * Feed Manager - Handles Activity Streams 2.0 feed fetching and processing
 * Manages feed data, caching, and provides data to the UI layer
 */

class FeedManager {
    constructor() {
        this.feeds = new Map(); // Active feeds data
        this.feedConfigs = new Map(); // Feed configuration (URLs, names, etc.)
        this.cache = AnsyblConfig.cache;
        this.isLoading = false;
        this.lastUpdate = null;
        this.updateInterval = null;
        
        // Event system for UI updates
        this.listeners = new Map();
        
        // Error tracking
        this.errors = new Map();
        this.retryQueue = new Set();
        
        this.init();
    }
    
    /**
     * Initialize feed manager
     */
    async init() {
        AnsyblConfig.utils.log('info', 'Initializing FeedManager');
        
        try {
            // Load cached data first for faster initial render
            this.loadFromCache();
            
            // Load feed configurations
            await this.loadFeedConfigs();
            
            // Start background updates if enabled
            if (AnsyblConfig.feeds.backgroundUpdate) {
                this.startBackgroundUpdates();
            }
            
            // Emit ready event
            this.emit('ready');
            
        } catch (error) {
            AnsyblConfig.utils.log('error', 'FeedManager initialization failed', error);
            this.emit('error', error);
        }
    }
    
    /**
     * Load feed configurations from API (both external and local feeds)
     */
    async loadFeedConfigs() {
        try {
            // Load external feeds
            const externalResponse = await this.fetchWithRetry(AnsyblConfig.api.feeds);
            const externalFeeds = externalResponse.data?.feeds || [];
            
            // Load local feeds
            let localFeeds = [];
            try {
                if (window.location.pathname.includes('/admin/') && window.localFeedManager) {
                    // In admin, use the admin API
                    localFeeds = await window.localFeedManager.loadFeeds();
                } else {
                    // On frontend, load local feeds by discovering published .ansybl files
                    localFeeds = await this.discoverLocalFeeds();
                }
            } catch (error) {
                AnsyblConfig.utils.log('warn', 'Failed to load local feeds', error);
            }
            
            this.feedConfigs.clear();
            
            // Add external feeds
            externalFeeds.forEach(feed => {
                if (feed.enabled !== false) {
                    this.feedConfigs.set(feed.id, {
                        id: feed.id,
                        url: feed.url,
                        name: feed.name,
                        enabled: feed.enabled !== false,
                        order: feed.order || 0,
                        lastFetched: feed.lastFetched || null,
                        error: null,
                        type: 'external'
                    });
                }
            });
            
            // Add local feeds
            localFeeds.forEach(feed => {
                if (feed.published !== false) {
                    this.feedConfigs.set('local-' + feed.id, {
                        id: 'local-' + feed.id,
                        localId: feed.id,
                        url: feed.url,
                        name: feed.name,
                        enabled: feed.published !== false,
                        order: feed.order || 999, // Local feeds at end by default
                        lastFetched: feed.updated || null,
                        error: null,
                        type: 'local'
                    });
                }
            });
            
            AnsyblConfig.utils.log('info', `Loaded ${this.feedConfigs.size} feed configurations (${externalFeeds.length} external, ${localFeeds.length} local)`);
            
        } catch (error) {
            AnsyblConfig.utils.log('error', 'Failed to load feed configurations', error);
            throw error;
        }
    }
    
    /**
     * Fetch all configured feeds
     */
    async fetchAllFeeds(force = false) {
        if (this.isLoading) {
            AnsyblConfig.utils.log('debug', 'Fetch already in progress, skipping');
            return;
        }
        
        this.isLoading = true;
        this.emit('loadStart');
        
        try {
            const promises = Array.from(this.feedConfigs.values())
                .filter(config => config.enabled)
                .map(config => this.fetchFeed(config.id, force));
            
            await Promise.allSettled(promises);
            
            this.lastUpdate = new Date();
            this.saveToCache();
            
            this.emit('loadComplete', this.getAllItems());
            
        } catch (error) {
            AnsyblConfig.utils.log('error', 'Failed to fetch feeds', error);
            this.emit('error', error);
        } finally {
            this.isLoading = false;
        }
    }
    
    /**
     * Fetch individual feed by ID (handles both local and external feeds)
     */
    async fetchFeed(feedId, force = false) {
        const config = this.feedConfigs.get(feedId);
        if (!config) {
            throw new Error(`Feed configuration not found: ${feedId}`);
        }
        
        // Check if we need to fetch (cache TTL, force refresh, etc.)
        if (!force && this.feeds.has(feedId)) {
            const cached = this.feeds.get(feedId);
            const age = Date.now() - new Date(cached.fetchedAt).getTime();
            if (age < AnsyblConfig.cache.feedTTL) {
                AnsyblConfig.utils.log('debug', `Using cached feed data for ${feedId}`);
                return cached;
            }
        }
        
        try {
            let feedData;
            
            if (config.type === 'local') {
                // Fetch local feed directly
                feedData = await this.fetchLocalFeed(config);
            } else {
                // Fetch external feed from cache
                feedData = await this.fetchExternalFeed(config);
            }
            
            // Store processed feed data
            this.feeds.set(feedId, {
                id: feedId,
                name: config.name,
                url: config.url,
                data: feedData,
                fetchedAt: new Date().toISOString(),
                itemCount: this.countFeedItems(feedData),
                error: null,
                type: config.type
            });
            
            // Clear any previous errors
            this.errors.delete(feedId);
            this.retryQueue.delete(feedId);
            
            // Update config with successful fetch
            config.lastFetched = new Date().toISOString();
            config.error = null;
            
            this.emit('feedUpdated', feedId, this.feeds.get(feedId));
            
            return this.feeds.get(feedId);
            
        } catch (error) {
            AnsyblConfig.utils.log('error', `Failed to fetch feed ${feedId}`, error);
            
            // Store error information
            this.errors.set(feedId, {
                message: error.message,
                timestamp: new Date().toISOString(),
                attempts: ((this.errors.get(feedId) && this.errors.get(feedId).attempts) || 0) + 1
            });
            
            config.error = error.message;
            
            // Add to retry queue if under max retries
            const errorInfo = this.errors.get(feedId);
            if (errorInfo.attempts < AnsyblConfig.errors.maxRetries) {
                this.retryQueue.add(feedId);
            }
            
            this.emit('feedError', feedId, error);
            throw error;
        }
    }
    
    /**
     * Fetch local feed data
     */
    async fetchLocalFeed(config) {
        AnsyblConfig.utils.log('debug', `Fetching local feed: ${config.id}`);
        
        // Fetch from the public local feed URL directly
        const response = await this.fetchWithRetry(config.url);
        
        // Validate and process the Activity Streams data
        return this.validateAndProcessFeed(response, config.id);
    }
    
    /**
     * Fetch external feed from feeds API
     */
    async fetchExternalFeed(config) {
        AnsyblConfig.utils.log('debug', `Fetching external feed: ${config.id}`);
        
        try {
            // Get feed data from feeds API which fetches and processes external feeds
            const feedUrl = `${AnsyblConfig.api.feeds}/${config.id}`;
            AnsyblConfig.utils.log('debug', `Fetching from URL: ${feedUrl}`);
            const feedResponse = await this.fetchWithRetry(feedUrl);
            
            AnsyblConfig.utils.log('debug', `Feed response received for: ${config.id}`);
            
            // Extract the processed feed data from feeds API response
            if (!feedResponse.success || !feedResponse.data) {
                throw new Error(`No feed data found for feed: ${config.id}`);
            }
            
            // Get the raw feed data from the API response
            const rawFeedData = feedResponse.data.data?.data || feedResponse.data.data || feedResponse.data;
            
            // Process the external feed through the same validation and processing pipeline as local feeds
            return this.validateAndProcessFeed(rawFeedData, config.id);
        } catch (error) {
            AnsyblConfig.utils.log('error', `Failed to fetch external feed ${config.id}:`, error);
            throw error;
        }
    }
    
    /**
     * Validate and process Activity Streams 2.0 feed data
     */
    validateAndProcessFeed(rawData, feedId) {
        // Basic validation
        if (!rawData || typeof rawData !== 'object') {
            throw new Error('Invalid feed data: not a valid JSON object');
        }
        
        // Check for Activity Streams context
        if (!rawData['@context'] || !rawData['@context'].includes('activitystreams')) {
            AnsyblConfig.utils.log('warn', `Feed ${feedId} missing Activity Streams context`);
        }
        
        // Ensure required fields
        if (!rawData.type) {
            throw new Error('Invalid feed data: missing type field');
        }
        
        // Process items based on type
        let items = [];
        
        if (rawData.type === 'Collection' || rawData.type === 'OrderedCollection') {
            items = this.extractCollectionItems(rawData);
        } else if (rawData.type === 'CollectionPage' || rawData.type === 'OrderedCollectionPage') {
            items = rawData.orderedItems || rawData.items || [];
        } else if (Array.isArray(rawData.items)) {
            items = rawData.items;
        } else {
            // Single activity/object
            items = [rawData];
        }
        
        // Process and validate each item
        const processedItems = items
            .map(item => this.processActivityItem(item, feedId))
            .filter(item => item !== null);
        
        return {
            ...rawData,
            processedItems,
            totalItems: processedItems.length,
            processedAt: new Date().toISOString()
        };
    }
    
    /**
     * Extract items from Collection/OrderedCollection
     */
    extractCollectionItems(collection) {
        // Handle nested collections (like podcast feeds with episodes)
        let items = collection.orderedItems || collection.items || [];
        
        // Handle pagination - for now we just take what's directly available
        // TODO: Implement pagination fetching for large collections
        
        return items;
    }
    
    /**
     * Process individual activity or object
     */
    processActivityItem(item, feedId) {
        if (!item || typeof item !== 'object') {
            return null;
        }
        
        try {
            // Ensure we have basic required fields
            const processed = {
                id: item.id || this.generateItemId(item, feedId),
                type: item.type || 'Object',
                published: item.published || item.updated || new Date().toISOString(),
                updated: item.updated || item.published,
                feedId: feedId,
                
                // Activity properties
                actor: this.processActor(item.actor),
                object: item.object ? this.processObject(item.object) : 
                       (item.type === 'Collection' ? item : null),
                
                // Common properties
                name: item.name || null,
                summary: item.summary || null,
                content: item.content || null,
                url: item.url || null,
                
                // Media attachments
                attachment: Array.isArray(item.attachment) ? 
                    item.attachment.map(att => this.processAttachment(att)) : 
                    (item.attachment ? [this.processAttachment(item.attachment)] : []),
                
                // Tags and categories
                tag: Array.isArray(item.tag) ? item.tag : (item.tag ? [item.tag] : []),
                
                // Original raw data for debugging
                _raw: AnsyblConfig.debug.enabled ? item : null
            };
            
            // If this is an activity with an object, merge object properties for easier access
            if (processed.object && processed.object.type) {
                processed.objectType = processed.object.type;
                processed.objectName = processed.object.name || processed.name;
                processed.objectSummary = processed.object.summary || processed.summary;
                processed.objectContent = processed.object.content || processed.content;
                processed.objectUrl = processed.object.url || processed.url;
            }
            
            return processed;
            
        } catch (error) {
            AnsyblConfig.utils.log('warn', `Failed to process activity item from ${feedId}`, error);
            return null;
        }
    }
    
    /**
     * Process actor information
     */
    processActor(actor) {
        if (!actor) return null;
        
        return {
            type: actor.type || 'Person',
            name: actor.name || actor.preferredUsername || 'Unknown',
            summary: actor.summary || null,
            icon: actor.icon ? (actor.icon.url || actor.icon) : null,
            url: actor.url || null
        };
    }
    
    /**
     * Process object (the thing being acted upon)
     */
    processObject(obj) {
        if (!obj) return null;
        
        // If object is just a URL string, convert to basic object
        if (typeof obj === 'string') {
            return {
                type: 'Link',
                url: obj,
                name: obj
            };
        }
        
        return {
            type: obj.type || 'Object',
            name: obj.name || null,
            summary: obj.summary || null,
            content: obj.content || null,
            url: obj.url || null,
            mediaType: obj.mediaType || null,
            attachment: Array.isArray(obj.attachment) ? 
                obj.attachment.map(att => this.processAttachment(att)) : 
                (obj.attachment ? [this.processAttachment(obj.attachment)] : [])
        };
    }
    
    /**
     * Process media attachments
     */
    processAttachment(attachment) {
        if (!attachment) return null;
        
        return {
            type: attachment.type || 'Document',
            mediaType: attachment.mediaType || this.guessMediaType(attachment.url),
            url: attachment.url || null,
            name: attachment.name || null,
            summary: attachment.summary || null,
            width: attachment.width || null,
            height: attachment.height || null,
            duration: attachment.duration || null
        };
    }
    
    /**
     * Guess media type from URL
     */
    guessMediaType(url) {
        if (!url) return null;
        
        const extension = url.split('.').pop().toLowerCase();
        const mediaTypes = {
            // Images
            'jpg': 'image/jpeg',
            'jpeg': 'image/jpeg', 
            'png': 'image/png',
            'gif': 'image/gif',
            'webp': 'image/webp',
            'svg': 'image/svg+xml',
            
            // Audio
            'mp3': 'audio/mpeg',
            'wav': 'audio/wav',
            'ogg': 'audio/ogg',
            'm4a': 'audio/mp4',
            
            // Video
            'mp4': 'video/mp4',
            'webm': 'video/webm',
            'mov': 'video/quicktime',
            'avi': 'video/x-msvideo'
        };
        
        return mediaTypes[extension] || 'application/octet-stream';
    }
    
    /**
     * Generate unique ID for items that don't have one
     */
    generateItemId(item, feedId) {
        const content = item.name || item.summary || item.content || '';
        const timestamp = item.published || item.updated || Date.now();
        return `${feedId}-${btoa(content.substring(0, 50) + timestamp).replace(/[^a-zA-Z0-9]/g, '')}`;
    }
    
    /**
     * Count items in feed data
     */
    countFeedItems(feedData) {
        return feedData.processedItems ? feedData.processedItems.length : 0;
    }
    
    /**
     * Get all items from all feeds, sorted by date
     */
    getAllItems(options = {}) {
        const allItems = [];
        
        for (const feed of this.feeds.values()) {
            if (feed.data && feed.data.processedItems) {
                allItems.push(...feed.data.processedItems);
            }
        }
        
        // Sort by published date (newest first)
        allItems.sort((a, b) => {
            const dateA = new Date(a.published);
            const dateB = new Date(b.published);
            return dateB - dateA;
        });
        
        // Apply filters if provided
        if (options.feedId) {
            return allItems.filter(item => item.feedId === options.feedId);
        }
        
        if (options.limit) {
            return allItems.slice(0, options.limit);
        }
        
        return allItems;
    }
    
    /**
     * Get paginated items
     */
    getItemsPaginated(page = 1, itemsPerPage = null) {
        const perPage = itemsPerPage || AnsyblConfig.ui.itemsPerPage;
        const allItems = this.getAllItems();
        
        const startIndex = (page - 1) * perPage;
        const endIndex = startIndex + perPage;
        
        return {
            items: allItems.slice(startIndex, endIndex),
            pagination: {
                page: page,
                perPage: perPage,
                total: allItems.length,
                totalPages: Math.ceil(allItems.length / perPage),
                hasNext: endIndex < allItems.length,
                hasPrev: page > 1
            }
        };
    }
    
    /**
     * Search items
     */
    searchItems(query, options = {}) {
        if (!query || query.length < AnsyblConfig.search.minLength) {
            return [];
        }
        
        const searchQuery = query.toLowerCase();
        const allItems = this.getAllItems();
        
        const results = allItems.filter(item => {
            return AnsyblConfig.search.searchFields.some(field => {
                const value = this.getNestedProperty(item, field);
                return value && value.toLowerCase().includes(searchQuery);
            });
        });
        
        const maxResults = options.maxResults || AnsyblConfig.search.maxResults;
        return results.slice(0, maxResults);
    }
    
    /**
     * Get nested property value (e.g., 'object.name')
     */
    getNestedProperty(obj, path) {
        return path.split('.').reduce((current, key) => {
            return current && current[key] !== undefined ? current[key] : null;
        }, obj);
    }
    
    /**
     * Get feed information
     */
    getFeedInfo() {
        const configs = Array.from(this.feedConfigs.values());
        const activeFeeds = Array.from(this.feeds.values());
        
        return configs.map(config => {
            const feedData = this.feeds.get(config.id);
            const error = this.errors.get(config.id);
            
            return {
                id: config.id,
                localId: config.localId || null,
                name: config.name,
                url: config.url,
                enabled: config.enabled,
                order: config.order,
                lastFetched: config.lastFetched,
                itemCount: feedData ? feedData.itemCount : 0,
                status: error ? 'error' : (feedData ? 'active' : 'pending'),
                error: error ? error.message : null,
                inRetryQueue: this.retryQueue.has(config.id),
                type: config.type || 'external'
            };
        });
    }
    
    /**
     * Fetch with retry logic
     */
    async fetchWithRetry(url, options = {}) {
        let lastError;
        
        for (let attempt = 1; attempt <= AnsyblConfig.errors.maxRetries; attempt++) {
            try {
                const response = await fetch(url, {
                    headers: {
                        'Accept': 'application/activity+json,application/ld+json,application/json',
                        ...options.headers
                    },
                    ...options
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                return await response.json();
                
            } catch (error) {
                lastError = error;
                
                if (attempt < AnsyblConfig.errors.maxRetries) {
                    const delay = AnsyblConfig.errors.retryDelay * 
                        Math.pow(AnsyblConfig.errors.backoffMultiplier, attempt - 1);
                    
                    AnsyblConfig.utils.log('warn', 
                        `Fetch attempt ${attempt} failed for ${url}, retrying in ${delay}ms`, error);
                    
                    await new Promise(resolve => setTimeout(resolve, delay));
                }
            }
        }
        
        throw lastError;
    }
    
    /**
     * Start background updates
     */
    startBackgroundUpdates() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
        }
        
        this.updateInterval = setInterval(() => {
            this.fetchAllFeeds();
        }, AnsyblConfig.feeds.updateInterval);
        
        AnsyblConfig.utils.log('info', 'Background updates started');
    }
    
    /**
     * Stop background updates
     */
    stopBackgroundUpdates() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
            this.updateInterval = null;
        }
        
        AnsyblConfig.utils.log('info', 'Background updates stopped');
    }
    
    /**
     * Cache management
     */
    loadFromCache() {
        const cachedFeeds = this.cache.get(this.cache.keys.feeds);
        const cachedUpdate = this.cache.get(this.cache.keys.lastUpdate);
        
        if (cachedFeeds) {
            try {
                this.feeds = new Map(Object.entries(cachedFeeds));
                this.lastUpdate = cachedUpdate ? new Date(cachedUpdate) : null;
                
                AnsyblConfig.utils.log('info', `Loaded ${this.feeds.size} feeds from cache`);
                
                // Emit cached data for immediate UI update
                this.emit('loadComplete', this.getAllItems());
            } catch (error) {
                AnsyblConfig.utils.log('error', 'Failed to load from cache', error);
            }
        }
    }
    
    saveToCache() {
        if (this.feeds.size > 0) {
            const feedsData = Object.fromEntries(this.feeds);
            this.cache.set(this.cache.keys.feeds, feedsData, this.cache.feedTTL);
            this.cache.set(this.cache.keys.lastUpdate, this.lastUpdate && this.lastUpdate.toISOString());
            
            AnsyblConfig.utils.log('debug', 'Saved feeds to cache');
        }
    }
    
    clearCache() {
        this.cache.clear();
        AnsyblConfig.utils.log('info', 'Feed cache cleared');
    }
    
    /**
     * Event system
     */
    on(event, callback) {
        if (!this.listeners.has(event)) {
            this.listeners.set(event, []);
        }
        this.listeners.get(event).push(callback);
    }
    
    off(event, callback) {
        const eventListeners = this.listeners.get(event);
        if (eventListeners) {
            const index = eventListeners.indexOf(callback);
            if (index > -1) {
                eventListeners.splice(index, 1);
            }
        }
    }
    
    emit(event, ...args) {
        const eventListeners = this.listeners.get(event);
        if (eventListeners) {
            eventListeners.forEach(callback => {
                try {
                    callback(...args);
                } catch (error) {
                    AnsyblConfig.utils.log('error', `Error in event listener for ${event}`, error);
                }
            });
        }
        
        AnsyblConfig.utils.log('debug', `Event emitted: ${event}`, args);
    }
    
    /**
     * Discover local feeds by checking the local feeds API
     */
    async discoverLocalFeeds() {
        const localFeeds = [];
        
        try {
            // Get local feeds from the API (which only includes properly managed feeds)
            const response = await this.fetchWithRetry('/api/local-feeds.php');
            
            if (response.success && response.data?.feeds) {
                const apiFeeds = response.data.feeds;
                
                for (const feed of apiFeeds) {
                    // Only include published feeds
                    if (feed.published) {
                        localFeeds.push({
                            id: feed.id,
                            name: feed.name || feed.id,
                            description: feed.description || '',
                            url: `/feeds/${feed.id}.ansybl`,
                            published: true,
                            updated: feed.updated || null,
                            totalItems: feed.totalItems || 0
                        });
                        
                        AnsyblConfig.utils.log('debug', `Discovered local feed via API: ${feed.id}`);
                    }
                }
            } else {
                AnsyblConfig.utils.log('warn', 'No local feeds found in API response');
            }
            
        } catch (error) {
            AnsyblConfig.utils.log('error', 'Failed to discover local feeds via API, falling back to hardcoded list', error);
            
            // Fallback: try to fetch feeds directly (this ensures compatibility)
            const knownLocalFeeds = ['test-home', 'test-local-feed'];  // Removed test-local-feed-1 since it has no backend data
            
            for (const feedId of knownLocalFeeds) {
                try {
                    const feedUrl = `/feeds/${feedId}.ansybl`;
                    const response = await fetch(feedUrl);
                    
                    if (response.ok) {
                        const feedData = await response.json();
                        
                        localFeeds.push({
                            id: feedId,
                            name: feedData.name || feedId,
                            description: feedData.summary || '',
                            url: feedUrl,
                            published: true,
                            updated: feedData.updated || null,
                            totalItems: feedData.totalItems || (feedData.orderedItems ? feedData.orderedItems.length : 0)
                        });
                        
                        AnsyblConfig.utils.log('debug', `Discovered local feed via fallback: ${feedId}`);
                    }
                } catch (error) {
                    AnsyblConfig.utils.log('debug', `Local feed ${feedId} not accessible: ${error.message}`);
                }
            }
        }
        
        return localFeeds;
    }
}

// Make FeedManager globally available
window.FeedManager = FeedManager;
