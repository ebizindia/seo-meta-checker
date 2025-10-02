# Configuration Guide

## Important: Configure Before First Use

Before using the SEO Meta Checker Tool, you need to update the configuration settings at the top of `index.php`.

## Step 1: Open index.php

Open the `index.php` file in a text editor.

## Step 2: Find the Configuration Section

**Look for these lines at the top of the file (around lines 5-10):**

```php
// Increase PHP execution time limits
ini_set('max_execution_time', 600); // 10 minutes
set_time_limit(600); // 10 minutes - alternative method
ini_set('memory_limit', '256M'); // Increase memory limit too
```

## Step 3: Replace with Configuration Section

**Replace those lines with this complete configuration section:**

```php
// ============================================================
// CONFIGURATION SECTION - UPDATE THESE SETTINGS
// ============================================================

// Email Configuration
define('REPORT_EMAIL', 'your-email@example.com');  // Email address where reports will be sent
define('REPORT_FROM_EMAIL', 'noreply@yourdomain.com');  // From email address for reports
define('REPORT_FROM_NAME', 'SEO Meta Tool');  // From name for email reports

// Crawler Settings
define('DEFAULT_MAX_PAGES', 500);  // Default maximum pages to crawl (can be overridden with ?limit= parameter)
define('MAX_PAGES_LIMIT', 1000);   // Maximum allowed pages (hard limit)
define('REQUEST_TIMEOUT', 8);      // Timeout for each HTTP request in seconds
define('BATCH_SIZE', 10);          // Number of concurrent requests to process
define('MAX_EXECUTION_TIME', 600); // Maximum script execution time in seconds (10 minutes)
define('MEMORY_LIMIT', '256M');    // Memory limit for the script

// Performance Settings
define('URL_CACHE_SIZE', 1000);    // Maximum URLs to keep in cache
define('MAX_LINKS_PER_PAGE', 50);  // Maximum links to extract from each page

// ============================================================
// END CONFIGURATION SECTION
// ============================================================

// Apply PHP settings
ini_set('max_execution_time', MAX_EXECUTION_TIME);
set_time_limit(MAX_EXECUTION_TIME);
ini_set('memory_limit', MEMORY_LIMIT);
```

## Step 4: Update Email Settings

**Change these three values:**

```php
define('REPORT_EMAIL', 'ebizindia@gmail.com');  // <- Change this to your email
define('REPORT_FROM_EMAIL', 'arun@ebizindia.com');  // <- Change this to your from email
define('REPORT_FROM_NAME', 'SEO Meta Tool');  // <- Optional: Change the from name
```

**Example:**
```php
define('REPORT_EMAIL', 'youremail@yourdomain.com');
define('REPORT_FROM_EMAIL', 'seo-reports@yourdomain.com');
define('REPORT_FROM_NAME', 'Acme SEO Tool');
```

## Step 5: Update Email Function Calls

**Find line ~370 in index.php** and look for:

```php
if (mail('ebizindia@gmail.com', $subject, $emailBody, $headers)) {
```

**Replace with:**

```php
if (mail(REPORT_EMAIL, $subject, $emailBody, $headers)) {
```

**Find line ~350** and look for:

```php
$headers .= "From: SEO Meta Tool <arun@ebizindia.com>\r\n";
$headers .= "Reply-To: arun@ebizindia.com\r\n";
```

**Replace with:**

```php
$headers .= "From: " . REPORT_FROM_NAME . " <" . REPORT_FROM_EMAIL . ">\r\n";
$headers .= "Reply-To: " . REPORT_FROM_EMAIL . "\r\n";
```

## Step 6: Update Class Properties

**Find the SEOCrawler class (around line 12)** and look for:

```php
class SEOCrawler {
    private $visited = []; // Hash map for O(1) lookups
    private $queue = [];
    private $results = [];
    private $domain;
    private $baseUrl;
    private $maxPages = 500;
    private $timeout = 8; // Reduced timeout
    private $urlCache = []; // Cache for parsed URLs
    private $batchSize = 10; // Parallel request batch size
    private $startTime; // Track execution time
    private $endTime;
```

**Replace with:**

```php
class SEOCrawler {
    private $visited = []; // Hash map for O(1) lookups
    private $queue = [];
    private $results = [];
    private $domain;
    private $baseUrl;
    private $maxPages = DEFAULT_MAX_PAGES;
    private $timeout = REQUEST_TIMEOUT;
    private $urlCache = []; // Cache for parsed URLs
    private $batchSize = BATCH_SIZE;
    private $startTime; // Track execution time
    private $endTime;
```

**And in the constructor (around line 25):**

```php
public function __construct($url, $maxPages = 500) {
```

**Replace with:**

```php
public function __construct($url, $maxPages = DEFAULT_MAX_PAGES) {
```

## Step 7: Update URL Cache Size

**Find line ~265** where it says:

```php
if (count($this->urlCache) < 1000) {
```

**Replace with:**

```php
if (count($this->urlCache) < URL_CACHE_SIZE) {
```

## Step 8: Update Max Links Per Page

**Find line ~305** where it says:

```php
if (count($newUrls) >= 50) break;
```

**Replace with:**

```php
if (count($newUrls) >= MAX_LINKS_PER_PAGE) break;
```

## Step 9: Update Limit Validation

**Find lines ~380-385** where it checks the limit:

```php
if (isset($_GET['limit']) && is_numeric($_GET['limit'])) {
    $limit = max(1, min(1000, intval($_GET['limit']))); // Between 1 and 1000
}
```

**Replace with:**

```php
if (isset($_GET['limit']) && is_numeric($_GET['limit'])) {
    $limit = max(1, min(MAX_PAGES_LIMIT, intval($_GET['limit'])));
}
```

**Also find the similar check around line 402:**

```php
$limit = isset($_GET['limit']) ? max(1, min(1000, intval($_GET['limit']))) : 500;
```

**Replace with:**

```php
$limit = isset($_GET['limit']) ? max(1, min(MAX_PAGES_LIMIT, intval($_GET['limit']))) : DEFAULT_MAX_PAGES;
```

## Configuration Options Explained

### Email Settings

| Setting | Description | Example |
|---------|-------------|---------|
| `REPORT_EMAIL` | Where SEO reports are sent | `admin@example.com` |
| `REPORT_FROM_EMAIL` | From address for emails | `noreply@example.com` |
| `REPORT_FROM_NAME` | Display name for emails | `SEO Reports` |

### Crawler Settings

| Setting | Description | Default | Recommended Range |
|---------|-------------|---------|-------------------|
| `DEFAULT_MAX_PAGES` | Default pages to crawl | 500 | 100-500 |
| `MAX_PAGES_LIMIT` | Maximum allowed limit | 1000 | 500-2000 |
| `REQUEST_TIMEOUT` | HTTP request timeout (seconds) | 8 | 5-15 |
| `BATCH_SIZE` | Concurrent requests | 10 | 5-20 |
| `MAX_EXECUTION_TIME` | Script timeout (seconds) | 600 | 300-900 |
| `MEMORY_LIMIT` | PHP memory limit | 256M | 128M-512M |

### Performance Settings

| Setting | Description | Default | Recommended Range |
|---------|-------------|---------|-------------------|
| `URL_CACHE_SIZE` | URLs to cache | 1000 | 500-2000 |
| `MAX_LINKS_PER_PAGE` | Links per page to extract | 50 | 20-100 |

## Tuning Recommendations

### For Small Sites (<100 pages)
```php
define('DEFAULT_MAX_PAGES', 100);
define('REQUEST_TIMEOUT', 5);
define('BATCH_SIZE', 15);
```

### For Medium Sites (100-500 pages)
```php
define('DEFAULT_MAX_PAGES', 500);
define('REQUEST_TIMEOUT', 8);
define('BATCH_SIZE', 10);
```

### For Large Sites (500+ pages)
```php
define('DEFAULT_MAX_PAGES', 1000);
define('REQUEST_TIMEOUT', 10);
define('BATCH_SIZE', 5);
define('MAX_EXECUTION_TIME', 900);
define('MEMORY_LIMIT', '512M');
```

### For Slow Servers
```php
define('REQUEST_TIMEOUT', 15);
define('BATCH_SIZE', 5);
```

### For High-Performance Servers
```php
define('BATCH_SIZE', 20);
define('MEMORY_LIMIT', '512M');
```

## Verification

After making changes, test the configuration:

1. **Test email functionality:**
   - Crawl a small site with email enabled
   - Verify you receive the report

2. **Test crawler settings:**
   - Try different page limits
   - Monitor execution time
   - Check memory usage

3. **Check error logs:**
   - Look for timeout errors
   - Look for memory errors
   - Adjust settings if needed

## Troubleshooting

### Email Not Sending
- Verify `REPORT_EMAIL` is correct
- Check PHP mail() function is configured
- Consider using SMTP instead of mail()

### Timeout Errors
- Increase `MAX_EXECUTION_TIME`
- Reduce `BATCH_SIZE`
- Increase `REQUEST_TIMEOUT`

### Memory Errors
- Increase `MEMORY_LIMIT`
- Reduce `DEFAULT_MAX_PAGES`
- Reduce `URL_CACHE_SIZE`

### Slow Performance
- Increase `BATCH_SIZE` (if server can handle it)
- Reduce `REQUEST_TIMEOUT`
- Check target website response time

## Note: No Database Required

**Important:** This tool does NOT require a database. It's a stateless crawler that:
- Analyzes websites in real-time
- Returns results immediately
- Sends optional email reports
- Does not store any data persistently

All processing happens in memory during the crawl session.

## Quick Start After Configuration

Once configured, simply:

1. Upload `index.php` to your web server
2. Navigate to the URL in your browser
3. Enter a domain to analyze
4. Click "Start SEO Analysis"

That's it! No database setup needed.
