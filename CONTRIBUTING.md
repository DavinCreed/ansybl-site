# Contributing to Ansybl Site

Thank you for your interest in contributing to Ansybl Site! This document provides guidelines and information for contributors.

## 🚀 Getting Started

1. **Fork the repository** on GitHub
2. **Clone your fork** locally:
   ```bash
   git clone https://github.com/yourusername/ansybl-site.git
   cd ansybl-site
   ```
3. **Install dependencies**:
   ```bash
   composer install
   npm install
   ```
4. **Create a feature branch**:
   ```bash
   git checkout -b feature/your-feature-name
   ```

## 🧪 Development Workflow

### Test-Driven Development (TDD)

This project follows strict TDD principles:

1. **Write tests first** for any new functionality
2. **Run tests** to ensure they fail initially
3. **Implement the feature** to make tests pass
4. **Refactor** while keeping tests green

### Running Tests

```bash
# All tests
npm run build

# PHP tests only
./vendor/bin/phpunit

# JavaScript tests only
npm test

# Watch mode for development
npm run test:watch

# Coverage reports
npm run test:coverage
```

### Code Quality

- **PHP**: Follow PSR-4 autoloading and PSR-12 coding standards
- **JavaScript**: Follow ESLint rules (extends Airbnb base config)
- **Documentation**: Document all public methods and complex logic
- **Comments**: Use clear, concise comments for business logic

## 📝 Coding Standards

### PHP Guidelines

- Use strict typing: `declare(strict_types=1);`
- Follow PSR-4 namespace conventions
- Use dependency injection
- Write comprehensive PHPDoc comments
- Handle exceptions appropriately

### JavaScript Guidelines

- Use ES6+ features appropriately
- Follow functional programming patterns where possible
- Use meaningful variable and function names
- Implement proper error handling
- Use JSDoc for complex functions

### File Organization

```
src/Core/           # Core PHP classes
public/assets/js/   # JavaScript modules
tests/unit/         # PHP unit tests
tests/js/           # JavaScript tests
api/               # REST API endpoints
```

## 🔧 Adding New Features

### Backend (PHP)

1. Create class in appropriate `src/` directory
2. Write unit tests in `tests/unit/`
3. Implement following TDD cycle
4. Add integration tests if needed
5. Update API endpoints if required

### Frontend (JavaScript)

1. Create module in `public/assets/js/`
2. Write tests in `tests/js/`
3. Follow modular architecture patterns
4. Update configuration if needed
5. Test across different browsers

### Activity Streams Support

When adding new Activity Streams object types:

1. Add type to `AnsyblConfig.activityStreams.supportedTypes`
2. Create renderer in `ActivityRenderer`
3. Add schema validation in `SchemaValidator`
4. Write comprehensive tests
5. Update documentation

## 📋 Pull Request Process

1. **Ensure tests pass**: All existing and new tests must pass
2. **Update documentation**: README, code comments, and inline docs
3. **Follow commit message format**:
   ```
   type(scope): description
   
   - feat: new feature
   - fix: bug fix
   - docs: documentation
   - style: formatting
   - refactor: code refactoring
   - test: adding tests
   - chore: maintenance
   ```
4. **Create detailed PR description**:
   - What does this change do?
   - Why is this change needed?
   - How was it tested?
   - Any breaking changes?

### PR Requirements

- [ ] All tests pass (`npm run build`)
- [ ] Code follows project style guidelines
- [ ] Documentation updated (if applicable)
- [ ] No breaking changes (or clearly documented)
- [ ] Security considerations addressed
- [ ] Performance impact considered

## 🐛 Bug Reports

When reporting bugs, please include:

- **Environment**: PHP version, Node.js version, OS
- **Steps to reproduce**: Clear, minimal reproduction steps
- **Expected behavior**: What should happen
- **Actual behavior**: What actually happens
- **Error messages**: Any console or log errors
- **Screenshots**: If applicable

Use this template:

```markdown
## Bug Description
Brief description of the bug

## Environment
- PHP Version: 
- Node.js Version: 
- OS: 
- Browser (if frontend): 

## Steps to Reproduce
1. Step one
2. Step two
3. Step three

## Expected Result
What should happen

## Actual Result
What actually happens

## Additional Context
Any other relevant information
```

## 💡 Feature Requests

For new features, please:

1. **Check existing issues** to avoid duplicates
2. **Describe the use case** clearly
3. **Explain the benefit** to users
4. **Consider implementation impact**
5. **Provide examples** if helpful

## 🏗️ Architecture Guidelines

### File-Based Storage

- All data stored in JSON files under `data/`
- Use atomic operations with file locking
- Implement proper error handling for file operations
- Consider performance implications of file I/O

### Activity Streams Compliance

- Follow W3C Activity Streams 2.0 specification
- Validate all Activity Streams objects
- Support standard object types and extensibility
- Maintain backward compatibility

### Security Considerations

- Validate all inputs (JSON schema validation)
- Sanitize outputs (HTML sanitization)
- Use secure file operations (atomic writes, proper permissions)
- Handle errors without information disclosure

## 📚 Resources

- [Activity Streams 2.0 Specification](https://www.w3.org/ns/activitystreams)
- [PSR-4 Autoloading Standard](https://www.php-fig.org/psr/psr-4/)
- [PSR-12 Coding Style](https://www.php-fig.org/psr/psr-12/)
- [Airbnb JavaScript Style Guide](https://github.com/airbnb/javascript)

## 🤝 Community

- Be respectful and inclusive
- Help others learn and grow
- Share knowledge and best practices
- Focus on constructive feedback

## 📞 Questions?

- Open an issue for bugs or feature requests
- Start a discussion for architectural questions
- Review existing documentation first

Thank you for contributing to Ansybl Site! 🚀