# SEO Meta Checker Tool

A high-performance PHP-based web crawler that analyzes websites for SEO issues. Checks for missing or incorrect meta tags, title tags, canonical URLs, H1 headings, viewport settings, charset declarations, and language attributes.

## Features

- **Parallel Processing**: Uses cURL multi-handle for concurrent requests (10-20x faster than sequential crawling)
- **Comprehensive SEO Checks**:
  - Title tags (missing/empty)
  - Meta descriptions (missing/empty)
  - Canonical URLs (missing/empty/incorrect format)
  - H1 headings (missing/multiple/empty)
  - Meta viewport (missing)
  - Meta charset (missing)
  - Language attribute (missing)
- **Real-time Progress Updates**: Live crawling status with progress bar
- **Email Reports**: Automatic HTML email reports with color-coded issues
- **Configurable Limits**: Set custom page limits (1-1000 pages)
- **Performance Optimized**:
  - Hash map URL tracking for O(1) lookups
  - URL caching to prevent redundant processing
  - Batch processing with configurable concurrent requests
  - Memory-efficient DOM parsing

## Requirements

### No Database Required

**Important:** This tool does NOT require a database. It's a stateless, real-time crawler that:
- Analyzes websites on-demand
- Processes everything in memory
- Returns results immediately
- Optionally sends email reports
- Does not store any data persistently

### System Requirements

- **PHP**: 8.0 or higher (tested with PHP 8.4)
- **PHP Extensions**:
  - cURL
  - DOM
  - libxml
  - mbstring (recommended)
- **Web Server**: Apache with mod_rewrite (or Nginx)
- **Memory**: 256MB minimum (configurable in code)

## Installation

### 1. Clone the Repository

```bash
git clone https://github.com/ebizindia/seo-meta-checker.git
cd seo-meta-checker
```

### 2. Configure Web Server

**Apache (.htaccess - already included):**
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^.*$ index.php [L]
</IfModule>
```

**Nginx:**
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

### 3. Set Permissions

```bash
chmod 644 index.php
# Ensure web server has read access
```

### 4. Configure the Tool (REQUIRED)

**Before first use, you MUST configure the tool.**

1. Copy the example configuration file:
   ```bash
   cp config.example.php config.php
   ```

2. Edit `config.php` and update with your settings:
   ```php
   // Email Configuration
   define('ADMIN_EMAIL', 'your-email@yourdomain.com');
   define('FROM_EMAIL', 'seo-tool@yourdomain.com');
   define('FROM_NAME', 'SEO Meta Tool');

   // Application Configuration
   define('MAX_PAGES_DEFAULT', 500);
   define('MAX_PAGES_LIMIT', 1000);
   define('CRAWL_TIMEOUT', 8);
   define('CRAWL_BATCH_SIZE', 10);

   // Security Configuration
   define('ENABLE_RATE_LIMITING', true);
   define('MAX_REQUESTS_PER_HOUR', 5);
   ```

3. Ensure `config.php` is protected:
   - Already included in `.gitignore`
   - Protected by `.htaccess` rules

**See [CONFIGURATION.md](CONFIGURATION.md) for detailed configuration instructions.**

## Usage

### Basic Usage

1. Navigate to the tool in your web browser: `http://yourdomain.com/`
2. Enter a domain name (with or without `https://`)
3. Check/uncheck the email report option
4. Click "Start SEO Analysis"

### Custom Page Limits

Add `?limit=50` to the URL to limit crawling to 50 pages (default: 500, max: 1000):

```
http://yourdomain.com/?limit=50
```

### Example URLs to Analyze

```
example.com
https://www.example.com
example.com/subfolder
```

## Configuration

### Adjust Crawling Settings

Edit these parameters in the `SEOCrawler` class constructor:

```php
private $maxPages = 500;      // Maximum pages to crawl
private $timeout = 8;          // Request timeout in seconds
private $batchSize = 10;       // Concurrent requests
```

### Adjust Execution Limits

At the top of `index.php`:

```php
ini_set('max_execution_time', 600);  // 10 minutes
ini_set('memory_limit', '256M');     // Memory limit
```

## SEO Checks Performed

| Check | Description |
|-------|-------------|
| **Title Tag** | Ensures every page has a non-empty title tag |
| **Meta Description** | Checks for meta description presence and content |
| **Canonical URL** | Validates canonical link element format |
| **H1 Heading** | Ensures single, non-empty H1 tag per page |
| **Meta Viewport** | Checks for mobile viewport configuration |
| **Meta Charset** | Validates character encoding declaration |
| **Language Attribute** | Ensures HTML lang attribute is set |

## Output

### Summary Cards
- Total pages crawled
- Pages with issues
- Pages OK
- Execution time

### Detailed Results Table
- Page URL (clickable)
- Color-coded issue badges
- Issue type categorization

### Email Report (Optional)
- HTML formatted report
- Color-coded issue types
- Complete legend
- Performance metrics

## Performance

Typical performance on a standard server:
- Small sites (10-50 pages): 5-15 seconds
- Medium sites (100-200 pages): 30-60 seconds
- Large sites (500+ pages): 2-5 minutes

Performance factors:
- Target website response time
- Network latency
- Server resources
- Concurrent request limit

## Troubleshooting

### Common Issues

**"Maximum execution time exceeded"**
- Increase `max_execution_time` in php.ini or at the top of index.php
- Reduce the page limit using `?limit=100`

**"Allowed memory size exhausted"**
- Increase `memory_limit` in php.ini or at the top of index.php
- Reduce batch size by editing `$batchSize` property

**Email not sending**
- Verify PHP mail() function is configured
- Check email headers and recipient address
- Consider using PHPMailer for SMTP support

**SSL/Certificate errors**
- SSL certificate verification is now ENABLED by default for security
- Ensure your server has up-to-date CA certificates
- Update cURL CA bundle if needed: `curl.cainfo` in php.ini

## Security Features (Version 2.0)

This tool implements comprehensive security measures:

### Core Security Features
- ✅ **SSL Certificate Verification** - All HTTPS connections are verified
- ✅ **CSRF Protection** - Token-based form protection
- ✅ **Session Security** - HTTPOnly, Secure, SameSite cookies
- ✅ **Rate Limiting** - Prevents abuse (configurable)
- ✅ **Input Validation** - Strict URL validation and sanitization
- ✅ **Security Headers** - X-Frame-Options, CSP, XSS Protection, etc.
- ✅ **Email Injection Prevention** - Sanitized email headers
- ✅ **Configuration Protection** - Sensitive files blocked via .htaccess

### Security Best Practices
- Use HTTPS in production (enable HTTPS redirect in `.htaccess`)
- Keep PHP and dependencies updated
- Monitor logs for suspicious activity
- Review `SECURITY.md` for detailed security information

## Browser Compatibility

- Chrome/Edge: ✓
- Firefox: ✓
- Safari: ✓
- Mobile browsers: ✓

## Technology Stack

- **Backend**: PHP 8+
- **Frontend**: Bootstrap 4, jQuery
- **Crawling**: cURL with multi-handle
- **Parsing**: PHP DOMDocument

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Credits

Developed by [Ebizindia](https://www.ebizindia.com)

## Roadmap

Potential future enhancements:
- [ ] Export results to CSV/Excel
- [ ] Integration with Google Search Console
- [ ] Scheduled crawling with cron jobs
- [ ] Multi-language support
- [ ] Advanced filtering and sorting
- [ ] Open Graph and Twitter Card validation
- [ ] Schema.org markup detection
- [ ] Page speed insights integration
- [ ] Broken link detection
- [ ] Robots.txt validation

## Support

For issues, questions, or contributions, please open an issue on GitHub.

---

**Star this repository if you find it useful!** ⭐
