# Ansybl Site

A dynamic content management system powered by Activity Streams 2.0 feeds with a file-based architecture and Test-Driven Development approach.

## 🌟 Features

- **Activity Streams 2.0 Compliant** - Full W3C specification support
- **File-Based Storage** - No database required, pure JSON storage
- **Modern Frontend** - Responsive design with dynamic content rendering
- **Professional Admin** - Complete backend management interface
- **Test-Driven Development** - Comprehensive PHP and JavaScript test suites
- **RESTful API** - Complete CRUD operations for feeds, config, and cache management
- **Dynamic Theming** - CSS generation with live preview and responsive breakpoints
- **Performance Optimized** - Intelligent caching, background updates, lazy loading

## 🏗️ Architecture

### Three-Part System

1. **Ansybl Feeds** - Activity Streams 2.0 content sources
2. **Frontend Website** - Dynamic content display with search, filtering, and pagination
3. **Admin Backend** - Configuration management for feeds, themes, and site settings

### Technology Stack

- **Backend**: PHP 8+ with Composer dependency management
- **Frontend**: Vanilla JavaScript (ES6+) with modular architecture
- **Styling**: CSS3 with variables and responsive design
- **Testing**: PHPUnit for PHP, Jest for JavaScript
- **Storage**: JSON files with atomic operations and file locking

## 🚀 Quick Start

### Prerequisites

- PHP 8.0 or higher
- Node.js 16+ and npm
- Composer

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/ansybl-site.git
   cd ansybl-site
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Install JavaScript dependencies**
   ```bash
   npm install
   ```

4. **Run tests to verify installation**
   ```bash
   # PHP tests
   ./vendor/bin/phpunit
   
   # JavaScript tests
   npm test
   ```

5. **Start development server**
   ```bash
   npm run dev
   # or manually: php -S localhost:8000 -t public
   ```

6. **Visit the application**
   - Main site: http://localhost:8000
   - Admin panel: http://localhost:8000/admin

## 📖 Usage

### Adding Feeds

1. Access the admin panel at `/admin`
2. Navigate to the "Feeds" section
3. Add feed URLs (must be Activity Streams 2.0 compatible)
4. Configure display names and ordering
5. Test feeds before enabling

### Content Management

- **Search**: Real-time search across all Activity Streams fields
- **Filtering**: Filter by specific feeds or content types
- **Views**: Switch between grid and list layouts
- **Pagination**: Configurable items per page

### Theming

1. Go to admin "Styles" section
2. Choose from predefined themes or create custom
3. Modify CSS variables for colors, fonts, spacing
4. Add custom CSS for advanced styling
5. Generate and preview changes live

## 🧪 Testing

### Running Tests

```bash
# All tests
npm run build

# PHP tests only
./vendor/bin/phpunit

# JavaScript tests only
npm test

# With coverage
npm run test:coverage

# Watch mode for development
npm run test:watch
```

### Test Structure

- **PHP Tests**: `tests/unit/` and `tests/integration/`
- **JavaScript Tests**: `tests/js/`
- **Test Coverage**: Comprehensive coverage for core components
- **Fixtures**: Real-world example data in `tests/fixtures/`

## 📁 Project Structure

```
ansybl-site/
├── api/                    # REST API endpoints
│   ├── feeds.php          # Feed management API
│   ├── config.php         # Configuration API
│   ├── styles.php         # Theme management API
│   └── cache.php          # Cache management API
├── data/                   # File-based storage
│   ├── config/            # Configuration files
│   ├── cache/             # Feed cache
│   └── styles/            # Generated CSS
├── examples/              # Example Activity Streams feeds
├── public/                # Web root
│   ├── admin/             # Admin interface
│   ├── assets/            # CSS, JS, images
│   └── index.html         # Main application
├── src/                   # PHP classes
│   └── Core/              # Core system components
├── tests/                 # Test suites
│   ├── js/                # JavaScript tests
│   └── unit/              # PHP unit tests
├── composer.json          # PHP dependencies
├── package.json           # Node.js dependencies
└── PROJECT_PLAN.md        # Detailed project documentation
```

## 🔧 Configuration

### Site Configuration

Edit configuration through the admin panel or directly in `data/config/site.json`:

```json
{
  "site": {
    "title": "My Ansybl Site",
    "description": "Dynamic content powered by Activity Streams",
    "language": "en",
    "timezone": "UTC"
  },
  "display": {
    "items_per_page": 10,
    "excerpt_length": 150,
    "show_timestamps": true
  }
}
```

### Feed Configuration

Feeds are managed in `data/config/feeds.json`:

```json
{
  "feeds": [
    {
      "id": "example-feed",
      "name": "Example Feed",
      "url": "https://example.com/feed.ansybl",
      "enabled": true,
      "order": 1
    }
  ]
}
```

## 🎨 Activity Streams Support

### Supported Types

**Activities**: Create, Update, Delete, Announce, Like, Follow
**Objects**: Article, Note, Image, Video, Audio, Document, Link  
**Actors**: Person, Organization, Service, Application

### Example Feed Format

```json
{
  "@context": "https://www.w3.org/ns/activitystreams",
  "type": "Collection",
  "items": [
    {
      "type": "Create",
      "actor": {
        "type": "Person",
        "name": "Author Name",
        "icon": "https://example.com/avatar.jpg"
      },
      "object": {
        "type": "Article",
        "name": "Article Title",
        "content": "Article content...",
        "url": "https://example.com/article"
      },
      "published": "2025-01-15T12:00:00Z"
    }
  ]
}
```

## 🔐 Security Features

- **Input Validation** - JSON schema validation for all inputs
- **File Safety** - Atomic writes with file locking
- **XSS Protection** - HTML sanitization in rendering
- **Path Security** - Safe file path handling
- **Error Handling** - Graceful error management without information disclosure

## 🚀 Deployment

### Production Setup

1. **Web Server Configuration**
   - Point document root to `public/` directory
   - Ensure PHP 8+ with required extensions
   - Set appropriate file permissions for `data/` directory

2. **Environment Configuration**
   - Set production settings in config files
   - Configure cache TTL for performance
   - Enable error logging

3. **Security Hardening**
   - Disable debug mode
   - Set restrictive file permissions
   - Configure web server security headers

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Write tests for new functionality
4. Ensure all tests pass (`npm run build`)
5. Commit changes (`git commit -m 'Add amazing feature'`)
6. Push to branch (`git push origin feature/amazing-feature`)
7. Open a Pull Request

### Development Guidelines

- Follow Test-Driven Development (TDD)
- Maintain code coverage above 80%
- Follow PSR-4 for PHP, ESLint for JavaScript
- Document new features and API changes
- Use semantic commit messages

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙋 Support

- **Documentation**: See `PROJECT_PLAN.md` for detailed architecture
- **Issues**: Report bugs and feature requests on GitHub Issues
- **Examples**: Check `examples/` directory for sample feeds

## 🏆 Acknowledgments

- Built following W3C Activity Streams 2.0 specification
- Inspired by modern JAMstack architecture
- Test-driven development principles throughout
- File-based storage for simplicity and portability

---

**Made with ❤️ and Test-Driven Development**