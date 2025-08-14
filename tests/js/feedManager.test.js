/**
 * Tests for FeedManager class
 */

// Load required modules
require('../../public/assets/js/config.js');
require('../../public/assets/js/feedManager.js');

describe('FeedManager', () => {
  let feedManager;

  beforeEach(() => {
    global.fetch.mockResolvedValue({
      ok: true,
      json: async () => ({
        success: true,
        data: { feeds: [] },
      }),
    });
    
    feedManager = new FeedManager();
  });

  afterEach(() => {
    jest.clearAllMocks();
  });

  test('should initialize with default values', () => {
    expect(feedManager.feeds).toBeInstanceOf(Map);
    expect(feedManager.feedConfigs).toBeInstanceOf(Map);
    expect(feedManager.isLoading).toBe(false);
  });

  test('should set up event listeners', () => {
    const mockCallback = jest.fn();
    feedManager.on('test-event', mockCallback);
    
    feedManager.emit('test-event', 'test-data');
    
    expect(mockCallback).toHaveBeenCalledWith('test-data');
  });

  test('should get all items sorted by date', () => {
    feedManager.feeds.set('feed1', {
      data: {
        processedItems: [
          { id: 'item1', published: '2025-01-15T12:00:00Z' },
          { id: 'item2', published: '2025-01-15T11:00:00Z' },
        ],
      },
    });
    
    const items = feedManager.getAllItems();
    
    expect(items).toHaveLength(2);
    expect(items[0].id).toBe('item1'); // More recent
  });
});