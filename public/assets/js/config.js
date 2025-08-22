/**
 * Configuration and constants for Ansybl Site
 * Frontend JavaScript configuration
 */

const AnsyblConfig = {
  // API Endpoints
  api: {
    base: '/api',
    feeds: '/api/feeds.php',
    config: '/api/config.php',
    styles: '/api/styles.php',
    cache: '/api/cache.php',
  },

  // Activity Streams 2.0 Context
  activityStreams: {
    context: 'https://www.w3.org/ns/activitystreams',
    supportedTypes: {
      activities: ['Create', 'Update', 'Delete', 'Announce', 'Like', 'Follow'],
      objects: ['Article', 'Note', 'Image', 'Video', 'Audio', 'Document', 'Link'],
      actors: ['Person', 'Organization', 'Service', 'Application'],
    },

    // Object type to rendering strategy mapping
    renderers: {
      Article: 'renderArticle',
      Note: 'renderNote',
      Image: 'renderImage',
      Video: 'renderVideo',
      Audio: 'renderAudio',
      Document: 'renderDocument',
      Link: 'renderLink',
    },
  },

  // UI Settings (defaults - will be overridden by backend config)
  ui: {
    // Pagination
    itemsPerPage: 10,
    maxPages: 100,

    // Content display
    excerptLength: 150,
    dateFormat: {
      full: 'YYYY-MM-DD HH:mm:ss',
      short: 'MMM DD, YYYY',
      relative: true,
    },

    // View modes
    defaultView: 'grid', // 'grid' or 'list'

    // Responsive breakpoints (matches CSS)
    breakpoints: {
      mobile: 768,
      tablet: 1024,
    },

    // Animation settings
    transitions: {
      duration: 300,
      easing: 'ease-in-out',
    },
  },

  // Caching
  cache: {
    // Cache duration in milliseconds
    feedTTL: 5 * 60 * 1000, // 5 minutes
    configTTL: 10 * 60 * 1000, // 10 minutes

    // Local storage keys
    keys: {
      feeds: 'ansybl_feeds_cache',
      config: 'ansybl_config_cache',
      lastUpdate: 'ansybl_last_update',
    },
  },

  // Error handling
  errors: {
    // Retry configuration
    maxRetries: 3,
    retryDelay: 1000, // milliseconds
    backoffMultiplier: 2,

    // Error messages
    messages: {
      networkError: 'Network connection error. Please check your internet connection.',
      parseError: 'Unable to parse feed data. The feed may be corrupted.',
      notFound: 'The requested resource was not found.',
      serverError: 'Server error occurred. Please try again later.',
      timeout: 'Request timed out. Please try again.',
      unknown: 'An unexpected error occurred.',
    },
  },

  // Search configuration
  search: {
    minLength: 2,
    debounceDelay: 300, // milliseconds
    maxResults: 50,

    // Fields to search in Activity Streams objects
    searchFields: [
      'name',
      'summary',
      'content',
      'object.name',
      'object.summary',
      'object.content',
      'actor.name',
      'actor.summary',
    ],
  },

  // Feed management
  feeds: {
    // Update intervals
    updateInterval: 5 * 60 * 1000, // 5 minutes
    backgroundUpdate: true,

    // Feed validation
    requiredFields: ['@context', 'type'],

    // Supported MIME types for feed URLs
    supportedMimeTypes: [
      'application/activity+json',
      'application/ld+json',
      'application/json',
    ],
  },

  // Debug settings
  debug: {
    enabled: true, // Set to true for development
    verboseLogging: false,
    logLevel: 'debug', // 'debug', 'info', 'warn', 'error'
  },

  // Feature flags
  features: {
    search: true,
    sharing: true,
    comments: false,
    realTimeUpdates: false,
    offlineMode: false,
    darkMode: true,
  },
};

// Utility functions for configuration
AnsyblConfig.utils = {
  /**
     * Get API endpoint URL
     */
  getApiUrl(endpoint) {
    return AnsyblConfig.api.base + endpoint;
  },

  /**
     * Check if a feature is enabled
     */
  isFeatureEnabled(feature) {
    return AnsyblConfig.features[feature] === true;
  },

  /**
     * Get renderer function name for Activity Streams object type
     */
  getRenderer(objectType) {
    return AnsyblConfig.activityStreams.renderers[objectType] || 'renderDefault';
  },

  /**
     * Check if current viewport matches breakpoint
     */
  isViewport(breakpoint) {
    const width = window.innerWidth;
    switch (breakpoint) {
      case 'mobile':
        return width <= AnsyblConfig.ui.breakpoints.mobile;
      case 'tablet':
        return width > AnsyblConfig.ui.breakpoints.mobile
                       && width <= AnsyblConfig.ui.breakpoints.tablet;
      case 'desktop':
        return width > AnsyblConfig.ui.breakpoints.tablet;
      default:
        return false;
    }
  },

  /**
     * Debug logging function
     */
  log(level, message, data = null) {
    if (!AnsyblConfig.debug.enabled) return;

    const levels = ['debug', 'info', 'warn', 'error'];
    const currentLevelIndex = levels.indexOf(AnsyblConfig.debug.logLevel);
    const messageLevelIndex = levels.indexOf(level);

    if (messageLevelIndex >= currentLevelIndex) {
      const timestamp = new Date().toISOString();
      const prefix = `[Ansybl ${level.toUpperCase()}] ${timestamp}:`;

      switch (level) {
        case 'debug':
          console.debug(prefix, message, data);
          break;
        case 'info':
          console.info(prefix, message, data);
          break;
        case 'warn':
          console.warn(prefix, message, data);
          break;
        case 'error':
          console.error(prefix, message, data);
          break;
        default:
          console.log(prefix, message, data);
          break;
      }
    }
  },

  /**
     * Format date according to configuration
     */
  formatDate(dateString) {
    const date = new Date(dateString);

    if (AnsyblConfig.ui.dateFormat.relative) {
      const now = new Date();
      const diffMs = now - date;
      const diffMins = Math.floor(diffMs / 60000);
      const diffHours = Math.floor(diffMins / 60);
      const diffDays = Math.floor(diffHours / 24);

      if (diffMins < 1) return 'just now';
      if (diffMins < 60) return `${diffMins}m ago`;
      if (diffHours < 24) return `${diffHours}h ago`;
      if (diffDays < 7) return `${diffDays}d ago`;
    }

    // Fallback to configured format or simple format
    return `${date.toLocaleDateString()} ${date.toLocaleTimeString()}`;
  },

  /**
     * Truncate text to excerpt length
     */
  truncateText(text, length = null) {
    const maxLength = length || AnsyblConfig.ui.excerptLength;
    if (!text || text.length <= maxLength) return text;

    return `${text.substring(0, maxLength - 3)}...`;
  },
};

// Cache management utilities
AnsyblConfig.cache = {
  ...AnsyblConfig.cache,

  /**
     * Check if cache is available
     */
  isAvailable() {
    try {
      return typeof Storage !== 'undefined' && localStorage;
    } catch (e) {
      return false;
    }
  },

  /**
     * Get cached data
     */
  get(key) {
    if (!this.isAvailable()) return null;

    try {
      const cached = localStorage.getItem(key);
      if (!cached) return null;

      const data = JSON.parse(cached);

      // Check TTL
      if (data.expires && new Date() > new Date(data.expires)) {
        localStorage.removeItem(key);
        return null;
      }

      return data.value;
    } catch (e) {
      AnsyblConfig.utils.log('error', 'Cache get error', e);
      return null;
    }
  },

  /**
     * Set cached data with TTL
     */
  set(key, value, ttl = null) {
    if (!this.isAvailable()) return false;

    try {
      const data = {
        value,
        expires: ttl ? new Date(Date.now() + ttl).toISOString() : null,
        created: new Date().toISOString(),
      };

      localStorage.setItem(key, JSON.stringify(data));
      return true;
    } catch (e) {
      AnsyblConfig.utils.log('error', 'Cache set error', e);
      return false;
    }
  },

  /**
     * Clear cached data
     */
  clear(key = null) {
    if (!this.isAvailable()) return;

    try {
      if (key) {
        localStorage.removeItem(key);
      } else {
        // Clear all Ansybl cache keys
        Object.values(this.keys).forEach((cacheKey) => {
          localStorage.removeItem(cacheKey);
        });
      }
    } catch (e) {
      AnsyblConfig.utils.log('error', 'Cache clear error', e);
    }
  },
};

// Configuration loader for dynamic backend settings
AnsyblConfig.loader = {
  loaded: false,
  
  /**
   * Load site configuration from backend
   */
  async loadSiteConfig() {
    try {
      const response = await fetch(AnsyblConfig.api.config + '/site');
      const data = await response.json();
      
      if (data.success && data.data) {
        this.applyConfig(data.data);
        this.loaded = true;
        AnsyblConfig.utils.log('info', 'Site configuration loaded from backend');
      } else {
        AnsyblConfig.utils.log('warn', 'Failed to load site config, using defaults');
      }
    } catch (error) {
      AnsyblConfig.utils.log('error', 'Error loading site config:', error);
    }
  },
  
  /**
   * Apply configuration from backend
   */
  applyConfig(config) {
    // Update UI settings from backend config
    if (config.display) {
      AnsyblConfig.ui.itemsPerPage = config.display.items_per_page || AnsyblConfig.ui.itemsPerPage;
      AnsyblConfig.ui.excerptLength = config.display.excerpt_length || AnsyblConfig.ui.excerptLength;
      AnsyblConfig.ui.showTimestamps = config.display.show_timestamps !== undefined 
        ? config.display.show_timestamps 
        : AnsyblConfig.ui.showTimestamps;
    }
    
    // Update site information
    if (config.site) {
      AnsyblConfig.site = {
        ...AnsyblConfig.site,
        title: config.site.title || AnsyblConfig.site?.title || 'Ansybl Site',
        description: config.site.description || AnsyblConfig.site?.description || '',
        language: config.site.language || AnsyblConfig.site?.language || 'en',
        timezone: config.site.timezone || AnsyblConfig.site?.timezone || 'UTC'
      };
      
      // Update page title
      if (config.site.title && typeof document !== 'undefined') {
        document.title = config.site.title;
      }
    }
    
    // Update features
    if (config.features) {
      AnsyblConfig.features.search = config.features.search_enabled !== undefined 
        ? config.features.search_enabled 
        : AnsyblConfig.features.search;
    }
    
    // Dispatch config loaded event
    if (typeof window !== 'undefined') {
      window.dispatchEvent(new CustomEvent('ansyblConfigLoaded', { 
        detail: { config: config } 
      }));
    }
  },
  
  /**
   * Get current effective configuration
   */
  getCurrentConfig() {
    return {
      site: AnsyblConfig.site,
      ui: AnsyblConfig.ui,
      features: AnsyblConfig.features
    };
  }
};

// Auto-load configuration when DOM is ready
if (typeof document !== 'undefined') {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      AnsyblConfig.loader.loadSiteConfig();
    });
  } else {
    // DOM already loaded
    AnsyblConfig.loader.loadSiteConfig();
  }
}

// Make config globally available
window.AnsyblConfig = AnsyblConfig;

// Log initialization in debug mode
AnsyblConfig.utils.log('info', 'Ansybl configuration loaded', {
  features: AnsyblConfig.features,
  debug: AnsyblConfig.debug.enabled,
});
