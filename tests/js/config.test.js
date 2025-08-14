/**
 * Tests for AnsyblConfig utility functions
 */

// Load the config module
require('../../public/assets/js/config.js');

describe('AnsyblConfig', () => {
  test('should have all required configuration sections', () => {
    expect(AnsyblConfig).toBeDefined();
    expect(AnsyblConfig.api).toBeDefined();
    expect(AnsyblConfig.activityStreams).toBeDefined();
    expect(AnsyblConfig.ui).toBeDefined();
    expect(AnsyblConfig.utils).toBeDefined();
  });

  test('should have correct API endpoints', () => {
    expect(AnsyblConfig.api.base).toBe('/api');
    expect(AnsyblConfig.api.feeds).toBe('/api/feeds');
    expect(AnsyblConfig.api.config).toBe('/api/config');
  });

  describe('Utils - getApiUrl', () => {
    test('should construct correct API URLs', () => {
      expect(AnsyblConfig.utils.getApiUrl('/feeds')).toBe('/api/feeds');
      expect(AnsyblConfig.utils.getApiUrl('/config')).toBe('/api/config');
    });
  });

  describe('Utils - formatDate', () => {
    test('should format dates correctly', () => {
      const date = new Date('2025-01-15T12:00:00Z');
      const result = AnsyblConfig.utils.formatDate(date.toISOString());
      expect(result).toContain('2025');
    });
  });
});