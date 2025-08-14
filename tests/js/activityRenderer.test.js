/**
 * Tests for ActivityRenderer class
 */

// Load required modules
require('../../public/assets/js/config.js');
require('../../public/assets/js/activityRenderer.js');

describe('ActivityRenderer', () => {
  let renderer;

  beforeEach(() => {
    renderer = new ActivityRenderer();
  });

  test('should initialize with templates and renderers', () => {
    expect(renderer.templates).toBeInstanceOf(Map);
    expect(renderer.renderers).toBeInstanceOf(Map);
  });

  test('should render activity item to HTML', () => {
    const mockItem = global.createMockActivityItem();
    const result = renderer.render(mockItem);
    
    expect(result).toBeDefined();
  });

  test('should handle missing templates gracefully', () => {
    const mockItem = global.createMockActivityItem();
    
    // Clear templates to simulate missing template
    renderer.templates.clear();
    
    const result = renderer.render(mockItem);
    
    expect(result).toBeDefined();
  });
});