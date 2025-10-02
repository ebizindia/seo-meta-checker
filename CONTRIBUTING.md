# Contributing to SEO Meta Checker Tool

First off, thank you for considering contributing to SEO Meta Checker Tool! It's people like you that make this tool better for everyone.

## Code of Conduct

This project and everyone participating in it is governed by respect and professionalism. By participating, you are expected to uphold this standard.

## How Can I Contribute?

### Reporting Bugs

Before creating bug reports, please check existing issues to avoid duplicates. When you create a bug report, include as many details as possible:

**Bug Report Template:**
```
**Describe the bug**
A clear description of what the bug is.

**To Reproduce**
Steps to reproduce the behavior:
1. Go to '...'
2. Enter domain '...'
3. Click on '...'
4. See error

**Expected behavior**
What you expected to happen.

**Screenshots**
If applicable, add screenshots.

**Environment:**
 - PHP Version: [e.g. 8.2]
 - Web Server: [e.g. Apache 2.4]
 - Browser: [e.g. Chrome 120]
 - OS: [e.g. Ubuntu 22.04]

**Additional context**
Any other relevant information.
```

### Suggesting Enhancements

Enhancement suggestions are welcome! Please provide:

- Clear use case description
- Why this enhancement would be useful
- Possible implementation approach (optional)

### Pull Requests

1. **Fork the Repository**
   ```bash
   git clone https://github.com/yourusername/seo-meta-checker.git
   cd seo-meta-checker
   ```

2. **Create a Branch**
   ```bash
   git checkout -b feature/YourFeatureName
   ```

3. **Make Your Changes**
   - Write clean, commented code
   - Follow existing code style (PSR-12 for PHP)
   - Test your changes thoroughly

4. **Commit Your Changes**
   ```bash
   git add .
   git commit -m "Add: Brief description of your changes"
   ```

   Commit message format:
   - `Add:` for new features
   - `Fix:` for bug fixes
   - `Update:` for updates to existing features
   - `Refactor:` for code refactoring
   - `Docs:` for documentation changes

5. **Push to Your Fork**
   ```bash
   git push origin feature/YourFeatureName
   ```

6. **Open a Pull Request**
   - Provide a clear description of changes
   - Reference any related issues
   - Include screenshots if UI changes

## Development Guidelines

### PHP Code Style

- Follow PSR-12 coding standards
- Use meaningful variable and function names
- Add comments for complex logic
- Keep functions focused and single-purpose

**Example:**
```php
/**
 * Normalize URL by adding protocol and removing fragments
 * 
 * @param string $url Raw URL input
 * @return string|false Normalized URL or false on failure
 */
private function normalizeUrl($url) {
    // Implementation
}
```

### Performance Considerations

- Optimize for memory efficiency
- Use appropriate data structures (hash maps for lookups)
- Profile code for bottlenecks
- Consider scalability for large sites

### Security Best Practices

- Sanitize all user inputs
- Validate URLs and domains
- Prevent SSRF attacks
- Use prepared statements if adding database features
- Keep dependencies updated

## Testing

Currently, the tool uses manual testing. When contributing:

1. Test with various domain types:
   - Small sites (10-50 pages)
   - Medium sites (100-200 pages)
   - Large sites (500+ pages)

2. Test edge cases:
   - Invalid URLs
   - Domains with redirects
   - Sites with various HTML structures
   - Slow-responding websites

3. Test across browsers:
   - Chrome/Edge
   - Firefox
   - Safari

4. Test PHP versions:
   - PHP 8.0+
   - Different web servers (Apache, Nginx)

## Documentation

When adding features:

- Update README.md with new usage instructions
- Add entries to CHANGELOG.md
- Include inline code comments
- Update configuration examples if needed

## Questions?

Feel free to open an issue with your question or reach out to the maintainers.

## Recognition

Contributors will be recognized in the project documentation. Thank you for helping make this tool better!

---

**Thank you for contributing!** 🙏
