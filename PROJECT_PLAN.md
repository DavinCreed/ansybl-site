# Ansybl Site - Project Plan

## Project Overview

A three-part web application system consisting of:
1. **Ansybl Feeds** - Content sources that provide site data
2. **Frontend Website** - Public-facing site that displays feeds through dynamic menus and content
3. **Admin Backend** - Configuration interface for managing feeds, custom CSS, and site settings

## Technology Stack

- **Frontend**: HTML5, Vanilla JavaScript, CSS3
- **Backend**: PHP 8+
- **Storage**: File-based (JSON) - No database required
- **Testing**: PHPUnit (PHP), Jest (JavaScript), Selenium (E2E)
- **Content Format**: Activity Streams 2.0 (JSON-LD)

## Project Structure

```
ansybl-site/
├── data/                   # File-based storage
│   ├── config.json        # Site configuration
│   ├── feeds.json         # Feed definitions
│   ├── styles.json        # Custom CSS configurations
│   └── cache/             # Feed content cache
├── feeds/                 # Ansybl feed sources
├── public/                # Frontend website
│   ├── index.php
│   ├── assets/
│   │   ├── css/
│   │   ├── js/
│   │   └── uploads/
│   └── api/               # Frontend API endpoints
├── admin/                 # Backend admin interface
│   ├── index.php
│   ├── feeds.php
│   ├── styles.php
│   └── settings.php
├── src/                   # Core PHP classes
│   ├── FileManager.php
│   ├── FeedManager.php
│   ├── StyleManager.php
│   └── ConfigManager.php
├── tests/                 # Test files
│   ├── unit/
│   ├── integration/
│   └── acceptance/
├── config/
│   └── app.php
└── composer.json         # PHP dependencies
```

## Core Components

### 1. Ansybl Feeds
- Activity Streams 2.0 compliant feed parser and validator
- Content caching system with smart invalidation
- Update scheduling and automatic refresh
- Support for standard ActivityPub federation

### 2. Frontend Website
- Dynamic menu generation from configured feeds
- Content rendering engine
- Responsive design with custom CSS
- AJAX-powered content loading

### 3. Admin Backend
- Feed management (add/remove/configure feeds)
- Custom CSS editor and theme management
- Site configuration panel
- Real-time preview capabilities

## Ansybl Feed Format (Activity Streams 2.0)

Ansybl feeds are based on the [Activity Streams 2.0 specification](https://www.w3.org/ns/activitystreams), providing a standardized, extensible format for representing content and social activities.

### Core Feed Structure

```json
{
  "@context": "https://www.w3.org/ns/activitystreams",
  "type": "OrderedCollection", 
  "id": "https://example.com/feeds/tech-blog",
  "name": "Tech Blog Updates",
  "summary": "Latest articles and updates from our technology blog",
  "published": "2025-01-15T10:30:00Z",
  "updated": "2025-01-15T15:45:00Z",
  "totalItems": 25,
  "orderedItems": [
    {
      "type": "Create",
      "id": "https://example.com/activities/create-article-123",
      "actor": {
        "type": "Person",
        "id": "https://example.com/authors/john-doe", 
        "name": "John Doe",
        "summary": "Senior Developer"
      },
      "object": {
        "type": "Article",
        "id": "https://example.com/articles/123",
        "name": "Introduction to Web Components",
        "content": "Web components are a set of web platform APIs...",
        "summary": "Learn the basics of building reusable web components",
        "published": "2025-01-15T09:00:00Z",
        "updated": "2025-01-15T09:30:00Z",
        "url": "https://example.com/articles/123",
        "tag": [
          {"type": "Hashtag", "name": "#webcomponents"},
          {"type": "Hashtag", "name": "#javascript"}
        ],
        "attachment": [
          {
            "type": "Image",
            "url": "https://example.com/images/web-components-diagram.png",
            "name": "Web Components Architecture Diagram"
          }
        ]
      },
      "published": "2025-01-15T09:00:00Z"
    }
  ]
}
```

### Supported Content Types

**Articles & Blog Posts**
```json
{
  "type": "Create",
  "object": {
    "type": "Article",
    "name": "Article Title",
    "content": "Full content...",
    "summary": "Brief description",
    "tag": [{"type": "Hashtag", "name": "#tech"}],
    "attachment": []
  }
}
```

**Media Posts**
```json
{
  "type": "Create",
  "object": {
    "type": "Image", 
    "name": "Project Screenshot",
    "content": "Check out our latest UI design",
    "url": "https://example.com/image.jpg",
    "mediaType": "image/jpeg"
  }
}
```

**Announcements**
```json
{
  "type": "Announce",
  "object": {
    "type": "Note",
    "name": "New Feature Release", 
    "content": "We're excited to announce...",
    "tag": [{"type": "Hashtag", "name": "#announcement"}]
  }
}
```

**Events**
```json
{
  "type": "Create",
  "object": {
    "type": "Event",
    "name": "Web Development Workshop",
    "content": "Join us for a hands-on workshop...",
    "startTime": "2025-02-01T10:00:00Z",
    "endTime": "2025-02-01T16:00:00Z",
    "location": {
      "type": "Place", 
      "name": "Tech Hub Conference Room"
    }
  }
}
```

### Ansybl-Specific Extensions

```json
{
  "@context": [
    "https://www.w3.org/ns/activitystreams",
    {
      "ansybl": "https://ansybl.com/ns#",
      "priority": "ansybl:priority",
      "featured": "ansybl:featured", 
      "expires": "ansybl:expires"
    }
  ],
  "type": "Create",
  "object": {
    "type": "Article",
    "name": "Important Update",
    "priority": "high",
    "featured": true,
    "expires": "2025-02-15T00:00:00Z"
  }
}
```

### Key Benefits

1. **Standardized**: Well-established W3C specification
2. **Extensible**: Easy to add custom object types and properties
3. **Semantic**: Rich metadata and relationship information
4. **Interoperable**: Compatible with ActivityPub and federated systems
5. **Flexible**: Supports diverse content types naturally
6. **Future-proof**: Based on web standards and JSON-LD

### Frontend Display Mapping

- **Activities**: Timeline/feed items showing what happened
- **Objects**: The actual content to display (articles, images, etc.)
- **Actors**: Author/creator information and attribution
- **Tags**: Category and filtering system for organization
- **Attachments**: Media galleries and supplementary content

## File-Based Storage Architecture

### Configuration Files
- `config.json` - Global site settings, theme preferences, caching options
- `feeds.json` - Feed definitions, URLs, display names, menu ordering
- `styles.json` - Custom CSS rules, theme overrides, responsive settings

### Benefits of File-Based Approach
- No database setup or maintenance
- Easy backup and version control
- Simple deployment
- Fast read/write operations
- Human-readable configuration

## TDD Workflow

### Red-Green-Refactor Cycle

**PHP Backend TDD:**
1. Write failing PHPUnit test
2. Write minimal code to make test pass
3. Refactor and clean up code
4. Run integration tests

**JavaScript Frontend TDD:**
1. Write failing Jest unit test
2. Write minimal implementation
3. Refactor and optimize
4. Test DOM interactions

**End-to-End TDD:**
1. Write user story test (Selenium/Cypress)
2. Implement backend API endpoints
3. Implement frontend interface
4. Test complete user workflow

### Example TDD Flow
```
1. Write test: "Should display feed menu items"
2. Create FeedManager::getMenuItems() test (PHPUnit)
3. Implement PHP method (minimal functionality)
4. Write JS test for menu rendering (Jest)
5. Implement JavaScript menu display
6. Write E2E test for full user flow (Selenium)
7. Refactor both backend and frontend
8. Verify all tests pass
```

## Testing Strategy by Layer

### 1. File Storage Layer
- JSON file read/write operations
- File locking for concurrent access
- Backup/restore functionality
- Configuration validation and schema compliance

### 2. PHP Backend Testing
```php
// PHPUnit tests for:
- FileManager::readConfig()
- FeedParser::processFeeds()
- StyleManager::generateCSS()
- ConfigManager::updateSettings()
- Error handling (file permissions, invalid JSON)
```

### 3. JavaScript Frontend Testing
```javascript
// Jest tests for:
- Feed data rendering and display
- Dynamic menu generation
- CSS loading and theme switching
- AJAX calls to PHP endpoints
- DOM manipulation functions
```

### 4. Integration Testing
- Admin saves feed → File updated → Frontend displays new content
- Style changes → CSS regenerated → Frontend theme updates
- Feed updates → Cache invalidated → Content refreshes
- Complete user workflows from admin to frontend

### 5. File System Mocking
- Mock file operations for unit testing
- Test error handling (permissions, disk space, corrupted files)
- Validate JSON schema compliance
- Test concurrent access scenarios

## Development Phases

### Phase 1: Core File System & Testing Foundation
**Timeline: Week 1-2**
- Set up project structure and development environment
- Implement FileManager class using TDD
- Create JSON schema validation
- Establish PHPUnit test suite
- Set up Jest for JavaScript testing

**Deliverables:**
- Basic project structure
- FileManager with read/write operations
- Test framework setup
- JSON validation utilities

### Phase 2: Feed Management System
**Timeline: Week 3-4**
- Develop FeedParser class with TDD
- Define Ansybl feed format and schema
- Implement feed caching mechanism
- Create feed validation and error handling
- Build feed management API endpoints

**Deliverables:**
- FeedParser class with full test coverage
- Feed format specification
- Caching system
- Feed CRUD API

### Phase 3: Configuration & Style Management
**Timeline: Week 5-6**
- Build ConfigManager for site settings
- Develop StyleManager for dynamic CSS generation
- Create admin interface backend API
- Implement configuration validation
- Add integration tests for config changes

**Deliverables:**
- Configuration management system
- Dynamic CSS generation
- Admin backend API
- Integration test suite

### Phase 4: Frontend Development
**Timeline: Week 7-8**
- Create HTML templates with dynamic content slots
- Develop JavaScript for feed rendering and display
- Implement AJAX communication with backend
- Build responsive menu system
- Add E2E testing with Selenium

**Deliverables:**
- Frontend website with dynamic content
- JavaScript rendering engine
- AJAX integration
- E2E test coverage

### Phase 5: Admin Interface
**Timeline: Week 9-10**
- Build admin panel HTML/CSS/JavaScript
- Implement feed CRUD operations interface
- Create CSS/style editor with preview
- Add user-friendly configuration forms
- Conduct user acceptance testing

**Deliverables:**
- Complete admin interface
- Feed management UI
- Style editor with real-time preview
- User documentation

### Phase 6: Polish & Deployment
**Timeline: Week 11-12**
- Performance optimization and caching improvements
- Enhanced error handling and user feedback
- Security review and hardening
- Documentation and deployment guides
- Final testing and quality assurance

**Deliverables:**
- Production-ready application
- Performance optimizations
- Security measures
- Complete documentation
- Deployment instructions

## Testing Tools and Setup

### PHP Testing (PHPUnit)
```bash
composer require --dev phpunit/phpunit
./vendor/bin/phpunit tests/
```

### JavaScript Testing (Jest)
```bash
npm install --save-dev jest
npm test
```

### E2E Testing (Selenium)
```bash
composer require --dev facebook/webdriver
# Or use Cypress for modern E2E testing
```

### Test Coverage
- Aim for 90%+ code coverage on core classes
- 100% coverage on critical file operations
- Integration tests for all user workflows
- E2E tests for complete user journeys

## Implementation Order (TDD-Driven)

1. **File Operations** → **Feed Parsing** → **Configuration Management**
2. **Backend APIs** → **Frontend Rendering** → **Admin Interface**
3. **Integration Testing** → **E2E Testing** → **Performance Testing**

## Risk Mitigation

### File System Risks
- Implement file locking to prevent corruption
- Regular backup mechanisms
- Graceful handling of permission issues
- Validation of all file operations

### Performance Considerations
- Efficient caching strategies
- Lazy loading of feed content
- Optimized file I/O operations
- Minimal JavaScript footprint

### Security Measures
- Input validation and sanitization
- Secure file upload handling
- Protection against directory traversal
- XSS prevention in dynamic content

## Success Criteria

- All components developed using strict TDD methodology
- 90%+ test coverage across all layers
- Sub-second page load times
- Intuitive admin interface
- Reliable file-based storage system
- Responsive design across devices
- Easy deployment and maintenance

## Getting Started

1. Clone/create project repository
2. Set up development environment (PHP 8+, Node.js)
3. Install dependencies via Composer and npm
4. Run initial test suite to verify setup
5. Begin Phase 1 development with FileManager class

This plan provides a comprehensive roadmap for building the Ansybl Site using TDD principles with a file-based architecture that eliminates database complexity while maintaining full functionality and testability.