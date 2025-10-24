<?php
/**
 * SEO Meta Checker Configuration File
 *
 * Copy this file to config.php and update with your settings
 */

// Email Configuration
define('ADMIN_EMAIL', 'your-email@example.com');
define('FROM_EMAIL', 'seo-tool@example.com');
define('FROM_NAME', 'SEO Meta Tool');

// Application Configuration
define('MAX_PAGES_DEFAULT', 500);
define('MAX_PAGES_LIMIT', 1000);
define('CRAWL_TIMEOUT', 8);
define('CRAWL_BATCH_SIZE', 10);
define('MAX_EXECUTION_TIME', 600); // 10 minutes
define('MEMORY_LIMIT', '256M');

// Security Configuration
define('ENABLE_RATE_LIMITING', true);
define('MAX_REQUESTS_PER_HOUR', 5); // Maximum crawl requests per IP per hour

// Session Configuration
define('SESSION_LIFETIME', 3600); // 1 hour
define('SESSION_NAME', 'SEO_CHECKER_SESSION');
