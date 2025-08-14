/**
 * Jest Test Setup
 * Configure testing environment for Ansybl Site JavaScript components
 */

// Mock browser APIs that aren't available in Jest/JSDOM
global.fetch = jest.fn();
global.localStorage = {
  getItem: jest.fn(),
  setItem: jest.fn(),
  removeItem: jest.fn(),
  clear: jest.fn(),
};

// Global test utilities
global.createMockFeedData = () => ({
  id: 'test-feed',
  name: 'Test Feed',
  data: {
    processedItems: [
      {
        id: 'test-item-1',
        type: 'Create',
        published: new Date().toISOString(),
        feedId: 'test-feed',
      },
    ],
  },
});

global.createMockActivityItem = (overrides = {}) => ({
  id: 'test-activity',
  type: 'Create',
  actor: {
    type: 'Person',
    name: 'Test Author',
    icon: 'https://example.com/avatar.jpg',
  },
  object: {
    type: 'Article',
    name: 'Test Article',
    summary: 'Test summary',
    url: 'https://example.com/article',
  },
  published: new Date().toISOString(),
  feedId: 'test-feed',
  ...overrides,
});

// Load configuration
global.AnsyblConfig = {
  utils: {
    log: () => {},
    formatDate: (date) => new Date(date).toLocaleDateString(),
    truncateText: (text, length = 150) => text?.length > length ? text.substring(0, length - 3) + '...' : text,
  },
  ui: {
    itemsPerPage: 10,
    excerptLength: 150,
  },
  search: {
    minLength: 2,
    debounceDelay: 300,
  },
  cache: {
    feedTTL: 5 * 60 * 1000,
    keys: {
      feeds: 'ansybl_feeds_cache',
    },
  },
  feeds: {
    updateInterval: 5 * 60 * 1000,
  },
};

// Mock DOM elements
beforeEach(() => {
  document.body.innerHTML = `
    <div id="activity-stream"></div>
    <div id="loading-indicator"></div>
    <div id="error-message"></div>
    <input id="site-search" />
    <select id="feed-filter"></select>
    <template id="activity-item-template">
      <article class="activity-item">
        <div class="activity-title"></div>
        <div class="activity-summary"></div>
      </article>
    </template>
  `;
});