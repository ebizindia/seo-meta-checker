<?php
// SEO Meta Checker Tool
// Powered by Ebizindia - https://www.ebizindia.com

// Security Headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self' https://cdn.jsdelivr.net https://code.jquery.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; script-src 'self' 'unsafe-inline' https://code.jquery.com https://cdn.jsdelivr.net; img-src 'self' data: https:;");

// Increase PHP execution time limits
ini_set('max_execution_time', 600); // 10 minutes
set_time_limit(600); // 10 minutes - alternative method
ini_set('memory_limit', '256M'); // Increase memory limit too

// Secure session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);

session_start();

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Rate limiting - simple implementation
if (!isset($_SESSION['last_crawl_time'])) {
    $_SESSION['last_crawl_time'] = 0;
}

function checkRateLimit() {
    $minInterval = 10; // Minimum 10 seconds between crawls
    $timeSinceLastCrawl = time() - $_SESSION['last_crawl_time'];

    if ($timeSinceLastCrawl < $minInterval) {
        return false;
    }

    return true;
}

/**
 * Validate and sanitize URL to prevent SSRF attacks
 * @param string $url The URL to validate
 * @return array Returns ['valid' => bool, 'url' => string|null, 'error' => string|null]
 */
function validateUrl($url) {
    // Remove whitespace
    $url = trim($url);

    // Add https:// if no protocol
    if (!preg_match('/^https?:\/\//i', $url)) {
        $url = 'https://' . $url;
    }

    // Parse URL
    $parsed = parse_url($url);

    if (!$parsed || !isset($parsed['host'])) {
        return ['valid' => false, 'url' => null, 'error' => 'Invalid URL format'];
    }

    $host = $parsed['host'];

    // Prevent localhost and internal network access
    $blocked_patterns = [
        '/^localhost$/i',
        '/^127\./i',
        '/^10\./i',
        '/^172\.(1[6-9]|2[0-9]|3[0-1])\./i',
        '/^192\.168\./i',
        '/^169\.254\./i',  // Link-local addresses
        '/^0\./i',
        '/^::1$/i',  // IPv6 localhost
        '/^fe80:/i', // IPv6 link-local
        '/^fc00:/i', // IPv6 unique local
        '/^ff00:/i', // IPv6 multicast
    ];

    foreach ($blocked_patterns as $pattern) {
        if (preg_match($pattern, $host)) {
            return ['valid' => false, 'url' => null, 'error' => 'Access to internal/private networks is not allowed'];
        }
    }

    // Additional validation: Check if domain resolves to internal IP
    $ip = gethostbyname($host);
    if ($ip && $ip !== $host) {
        // Check if resolved IP is private
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return ['valid' => false, 'url' => null, 'error' => 'Domain resolves to a private IP address'];
        }
    }

    // Validate the scheme
    if (!isset($parsed['scheme']) || !in_array(strtolower($parsed['scheme']), ['http', 'https'])) {
        return ['valid' => false, 'url' => null, 'error' => 'Only HTTP and HTTPS protocols are allowed'];
    }

    // Validate host format
    if (!filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
        // Try validating as IP
        if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return ['valid' => false, 'url' => null, 'error' => 'Invalid domain or IP address'];
        }
    }

    return ['valid' => true, 'url' => $url, 'error' => null];
}

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
    
    public function __construct($url, $maxPages = 500) {
        $this->baseUrl = $this->normalizeUrl($url);
        $this->domain = parse_url($this->baseUrl, PHP_URL_HOST);
        $this->queue[] = $this->baseUrl;
        $this->maxPages = $maxPages;
        $this->startTime = microtime(true); // High precision timing
    }
    
    private function normalizeUrl($url) {
        // Add https:// if no protocol specified
        if (!preg_match('/^https?:\/\//', $url)) {
            $url = 'https://' . $url;
        }
        
        // Parse URL and remove fragment
        $parsed = parse_url($url);
        if (!$parsed) return false;
        
        // Rebuild URL without fragment and normalize
        $normalized = $parsed['scheme'] . '://' . $parsed['host'];
        if (isset($parsed['path'])) {
            $normalized .= rtrim($parsed['path'], '/');
        }
        if (isset($parsed['query'])) {
            $normalized .= '?' . $parsed['query'];
        }
        
        // Add trailing slash only for root
        if (parse_url($normalized, PHP_URL_PATH) === '') {
            $normalized .= '/';
        }
        
        return $normalized;
    }
    
    public function crawl() {
        $count = 0;
        $maxTime = time() + 300; // 5 minute timeout
        
        while (!empty($this->queue) && $count < $this->maxPages && time() < $maxTime) {
            // Process URLs in parallel batches
            $batch = array_splice($this->queue, 0, min($this->batchSize, $this->maxPages - $count));
            $batch = $this->filterUnvisited($batch);
            
            if (empty($batch)) continue;
            
            // Mark as visited before processing to avoid duplicates
            foreach ($batch as $url) {
                $this->visited[$url] = true; // Hash map assignment
                $count++;
            }
            
            $progress = min(100, round(($count / $this->maxPages) * 100));
            echo "<script>document.getElementById('status').innerHTML = 'Analyzing batch: " . count($batch) . " pages<br>Progress: " . $progress . "% (" . $count . "/" . $this->maxPages . ")<br>Queue: " . count($this->queue) . " URLs remaining';</script>";
            echo str_repeat(' ', 1024);
            flush();
            
            // Fetch multiple pages in parallel
            $pageResults = $this->fetchPagesParallel($batch);
            
            // Process results and extract links
            foreach ($pageResults as $url => $pageData) {
                if ($pageData && $pageData['content']) {
                    $this->analyzePage($url, $pageData['content']);
                    $this->extractLinks($pageData['content'], $url);
                    
                    // Free memory immediately after processing
                    unset($pageResults[$url]);
                }
            }
            
            // Safety check
            if (count($this->queue) > $this->maxPages) {
                $this->queue = array_slice($this->queue, 0, $this->maxPages);
                echo "<script>document.getElementById('status').innerHTML = 'Queue trimmed to prevent overload.';</script>";
                break;
            }
        }
        
        if (time() >= $maxTime) {
            echo "<script>document.getElementById('status').innerHTML = 'Analysis completed (stopped due to time limit).';</script>";
        }
        
        $this->endTime = microtime(true); // Record end time
        return $this->results;
    }
    
    private function filterUnvisited($urls) {
        $unvisited = [];
        foreach ($urls as $url) {
            if (!isset($this->visited[$url])) { // O(1) hash map lookup
                $unvisited[] = $url;
            }
        }
        return $unvisited;
    }
    
    private function fetchPagesParallel($urls) {
        if (empty($urls)) return [];
        
        $multiHandle = curl_multi_init();
        $curlHandles = [];
        $results = [];
        
        // Initialize parallel cURL handles
        foreach ($urls as $url) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_USERAGENT => 'SEO Crawler 2.0 (Optimized)',
                CURLOPT_SSL_VERIFYPEER => true,  // Enable SSL verification for security
                CURLOPT_SSL_VERIFYHOST => 2,     // Verify SSL host
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_CONNECTTIMEOUT => 5,
            ]);
            
            curl_multi_add_handle($multiHandle, $ch);
            $curlHandles[$url] = $ch;
        }
        
        // Execute all requests in parallel
        $active = null;
        do {
            $mrc = curl_multi_exec($multiHandle, $active);
            if ($active) {
                curl_multi_select($multiHandle); // Wait for activity
            }
        } while ($mrc == CURLM_CALL_MULTI_PERFORM || $active);
        
        // Collect results
        foreach ($curlHandles as $url => $ch) {
            $content = curl_multi_getcontent($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            
            // Only store HTML content
            if ($httpCode === 200 && strpos($contentType, 'text/html') !== false && $content) {
                $results[$url] = ['content' => $content, 'http_code' => $httpCode];
            } else {
                $results[$url] = false;
            }
            
            curl_multi_remove_handle($multiHandle, $ch);
            curl_close($ch);
        }
        
        curl_multi_close($multiHandle);
        return $results;
    }
    
    private function fetchPage($url) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_USERAGENT => 'SEO Crawler 1.0',
            CURLOPT_SSL_VERIFYPEER => true,  // Enable SSL verification for security
            CURLOPT_SSL_VERIFYHOST => 2,     // Verify SSL host
            CURLOPT_MAXREDIRS => 3,
        ]);
        
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        
        curl_close($ch);
        
        // Only process HTML content
        if ($httpCode === 200 && strpos($contentType, 'text/html') !== false && $content) {
            return ['content' => $content, 'http_code' => $httpCode];
        }
        
        return false;
    }
    
    private function analyzePage($url, $html) {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        
        $issues = [];
        
        // Optimized: Single pass through all elements
        $elements = [
            'title' => $dom->getElementsByTagName('title'),
            'meta' => $dom->getElementsByTagName('meta'),
            'link' => $dom->getElementsByTagName('link'),
            'h1' => $dom->getElementsByTagName('h1'),
            'html' => $dom->getElementsByTagName('html')
        ];
        
        // Check title tag
        if ($elements['title']->length === 0) {
            $issues[] = 'Missing Title Tag';
        } elseif (trim($elements['title']->item(0)->textContent) === '') {
            $issues[] = 'Empty Title Tag';
        }
        
        // Single pass through meta tags - check all conditions at once
        $metaChecks = [
            'description' => false,
            'description_empty' => false,
            'viewport' => false,
            'charset' => false
        ];
        
        foreach ($elements['meta'] as $meta) {
            $name = strtolower($meta->getAttribute('name'));
            $httpEquiv = strtolower($meta->getAttribute('http-equiv'));
            
            switch ($name) {
                case 'description':
                    $metaChecks['description'] = true;
                    if (trim($meta->getAttribute('content')) === '') {
                        $metaChecks['description_empty'] = true;
                    }
                    break;
                case 'viewport':
                    $metaChecks['viewport'] = true;
                    break;
            }
            
            // Check charset
            if ($meta->hasAttribute('charset') || 
                ($httpEquiv === 'content-type' && strpos(strtolower($meta->getAttribute('content')), 'charset') !== false)) {
                $metaChecks['charset'] = true;
            }
        }
        
        // Process meta check results
        if (!$metaChecks['description']) {
            $issues[] = 'Missing Meta Description';
        } elseif ($metaChecks['description_empty']) {
            $issues[] = 'Empty Meta Description';
        }
        
        if (!$metaChecks['viewport']) {
            $issues[] = 'Missing Meta Viewport';
        }
        
        if (!$metaChecks['charset']) {
            $issues[] = 'Missing Meta Charset';
        }
        
        // Check canonical tag
        $canonicalChecks = ['exists' => false, 'empty' => false, 'href' => ''];
        foreach ($elements['link'] as $link) {
            if (strtolower($link->getAttribute('rel')) === 'canonical') {
                $canonicalChecks['exists'] = true;
                $canonicalChecks['href'] = trim($link->getAttribute('href'));
                if ($canonicalChecks['href'] === '') {
                    $canonicalChecks['empty'] = true;
                }
                break;
            }
        }
        
        if (!$canonicalChecks['exists']) {
            $issues[] = 'Missing Canonical Tag';
        } elseif ($canonicalChecks['empty']) {
            $issues[] = 'Empty Canonical Tag';
        } elseif (!preg_match('/^https?:\/\//', $canonicalChecks['href'])) {
            $issues[] = 'Incorrect format for canonical';
        }
        
        // Check H1 tags
        if ($elements['h1']->length === 0) {
            $issues[] = 'Missing H1 Tag';
        } elseif ($elements['h1']->length > 1) {
            $issues[] = 'Multiple H1 Tags';
        } elseif (trim($elements['h1']->item(0)->textContent) === '') {
            $issues[] = 'Empty H1 Tag';
        }
        
        // Check language attribute
        $hasLangAttr = false;
        if ($elements['html']->length > 0) {
            $langAttr = trim($elements['html']->item(0)->getAttribute('lang'));
            $hasLangAttr = ($langAttr !== '');
        }
        if (!$hasLangAttr) {
            $issues[] = 'Missing Language Attribute';
        }
        
        // Only store pages with issues
        if (!empty($issues)) {
            $this->results[] = [
                'url' => $url,
                'issues' => $issues,
                'title' => $elements['title']->length > 0 ? trim($elements['title']->item(0)->textContent) : '',
                'has_title' => !in_array('Missing Title Tag', $issues) && !in_array('Empty Title Tag', $issues),
                'has_meta_desc' => !in_array('Missing Meta Description', $issues) && !in_array('Empty Meta Description', $issues),
                'has_canonical' => !in_array('Missing Canonical Tag', $issues) && !in_array('Empty Canonical Tag', $issues),
                'canonical_format_ok' => !in_array('Incorrect format for canonical', $issues),
                'has_h1' => !in_array('Missing H1 Tag', $issues) && !in_array('Multiple H1 Tags', $issues) && !in_array('Empty H1 Tag', $issues),
                'has_viewport' => !in_array('Missing Meta Viewport', $issues),
                'has_charset' => !in_array('Missing Meta Charset', $issues),
                'has_lang' => !in_array('Missing Language Attribute', $issues)
            ];
        }
        
        // Free DOM memory immediately
        unset($dom, $elements);
    }
    
    private function extractLinks($html, $baseUrl) {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        
        $links = $dom->getElementsByTagName('a');
        $newUrls = [];
        $urlSet = []; // Use hash map to prevent duplicates in batch
        
        foreach ($links as $link) {
            $href = trim($link->getAttribute('href'));
            if (empty($href)) continue;
            
            $absoluteUrl = $this->resolveUrl($href, $baseUrl);
            
            // Skip if resolveUrl returned false (invalid links)
            if ($absoluteUrl === false) continue;
            
            // Skip if not same domain
            if (!$this->isSameDomain($absoluteUrl)) continue;
            
            // Skip if already visited or already in this batch
            if (isset($this->visited[$absoluteUrl]) || isset($urlSet[$absoluteUrl])) continue;
            
            // Skip common file extensions that aren't HTML pages
            $path = parse_url($absoluteUrl, PHP_URL_PATH);
            if ($path && preg_match('/\.(pdf|jpg|jpeg|png|gif|zip|doc|docx|xls|xlsx|ppt|pptx|mp3|mp4|avi|mov)$/i', $path)) {
                continue;
            }
            
            $newUrls[] = $absoluteUrl;
            $urlSet[$absoluteUrl] = true; // Mark as added to prevent duplicates
            
            // Limit new URLs per page to prevent memory explosion
            if (count($newUrls) >= 50) break;
        }
        
        // Efficiently merge new URLs, avoiding duplicates with existing queue
        $existingQueue = array_flip($this->queue); // Convert to hash map for O(1) lookups
        foreach ($newUrls as $url) {
            if (!isset($existingQueue[$url])) {
                $this->queue[] = $url;
            }
        }
        
        // Free DOM memory immediately
        unset($dom, $links, $urlSet, $existingQueue);
    }
    
    private function resolveUrl($href, $base) {
        // Skip empty, hash-only, javascript, mailto, tel links
        if (empty($href) || $href === '#' || 
            strpos($href, 'javascript:') === 0 || 
            strpos($href, 'mailto:') === 0 || 
            strpos($href, 'tel:') === 0 ||
            strpos($href, '#') === 0) {
            return false;
        }
        
        // Cache key for common patterns
        $cacheKey = $base . '|' . $href;
        if (isset($this->urlCache[$cacheKey])) {
            return $this->urlCache[$cacheKey];
        }
        
        $resolved = $this->performUrlResolution($href, $base);
        
        // Cache the result (limit cache size to prevent memory issues)
        if (count($this->urlCache) < 1000) {
            $this->urlCache[$cacheKey] = $resolved;
        }
        
        return $resolved;
    }
    
    private function performUrlResolution($href, $base) {
        // Handle absolute URLs
        if (preg_match('/^https?:\/\//', $href)) {
            return $this->normalizeUrl($href);
        }
        
        // Handle protocol-relative URLs
        if (strpos($href, '//') === 0) {
            return $this->normalizeUrl(parse_url($base, PHP_URL_SCHEME) . ':' . $href);
        }
        
        // Handle absolute paths
        if (strpos($href, '/') === 0) {
            $baseScheme = parse_url($base, PHP_URL_SCHEME);
            $baseHost = parse_url($base, PHP_URL_HOST);
            return $this->normalizeUrl($baseScheme . '://' . $baseHost . $href);
        }
        
        // Handle relative paths
        $baseParts = parse_url($base);
        if (!$baseParts || !isset($baseParts['host'])) return false;
        
        $basePath = isset($baseParts['path']) ? $baseParts['path'] : '/';
        
        // If base path ends with a filename (contains dot), get the directory
        if (strpos(basename($basePath), '.') !== false) {
            $basePath = dirname($basePath);
        }
        
        // Normalize path
        if ($basePath === '.' || $basePath === '') {
            $basePath = '/';
        } elseif ($basePath !== '/') {
            $basePath = rtrim($basePath, '/') . '/';
        }
        
        $resolvedUrl = $baseParts['scheme'] . '://' . $baseParts['host'] . $basePath . ltrim($href, '/');
        
        return $this->normalizeUrl($resolvedUrl);
    }
    
    private function isSameDomain($url) {
        $urlHost = parse_url($url, PHP_URL_HOST);
        if (!$urlHost) return false;
        
        // Normalize both hosts to lowercase
        $urlHost = strtolower($urlHost);
        $baseHost = strtolower($this->domain);
        
        // Remove www. prefix for comparison
        $urlHost = preg_replace('/^www\./', '', $urlHost);
        $baseHost = preg_replace('/^www\./', '', $baseHost);
        
        return $urlHost === $baseHost;
    }
    
    public function getResults() {
        return $this->results;
    }
    
    public function getTotalCrawled() {
        return count($this->visited); // Hash map count is still O(1)
    }
    
    public function getPerformanceStats() {
        return [
            'pages_crawled' => count($this->visited),
            'urls_in_queue' => count($this->queue),
            'cache_size' => count($this->urlCache),
            'batch_size' => $this->batchSize,
            'execution_time' => $this->getExecutionTime(),
            'execution_time_formatted' => $this->getFormattedExecutionTime()
        ];
    }
    
    public function getExecutionTime() {
        if (!isset($this->endTime)) {
            return microtime(true) - $this->startTime; // Live execution time
        }
        return $this->endTime - $this->startTime;
    }
    
    public function getFormattedExecutionTime() {
        $executionTime = $this->getExecutionTime();
        
        if ($executionTime < 1) {
            return number_format($executionTime * 1000, 0) . ' ms';
        } elseif ($executionTime < 60) {
            return number_format($executionTime, 2) . ' seconds';
        } else {
            $minutes = floor($executionTime / 60);
            $seconds = $executionTime % 60;
            return $minutes . 'm ' . number_format($seconds, 1) . 's';
        }
    }
}

// Handle form submission
$results = [];
$crawled = false;
$emailSent = false;
$errorMessage = '';

if (isset($_POST['action']) && $_POST['action'] === 'crawl' && !empty($_POST['domain'])) {
    // CSRF token validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('CSRF token validation failed. Please refresh the page and try again.');
    }

    // Rate limiting check
    if (!checkRateLimit()) {
        $errorMessage = 'Please wait at least 10 seconds between crawl requests.';
    } else {
        $domain = trim($_POST['domain']);

        // Validate URL using new validation function
        $validation = validateUrl($domain);

        if (!$validation['valid']) {
            $errorMessage = 'Invalid domain: ' . htmlspecialchars($validation['error']);
        } else {
            $domain = $validation['url'];
            $sendEmail = isset($_POST['send_email']);

            // Check for limit parameter
            $limit = 500; // default
            if (isset($_GET['limit']) && is_numeric($_GET['limit'])) {
                $limit = max(1, min(1000, intval($_GET['limit']))); // Between 1 and 1000
            }

            // Update last crawl time
            $_SESSION['last_crawl_time'] = time();

            // Redirect after POST to prevent resubmission on refresh
            $redirectUrl = $_SERVER['PHP_SELF'] . '?analyze=1&domain=' . urlencode($domain) . '&limit=' . $limit;
            if ($sendEmail) $redirectUrl .= '&email=1';

            header('Location: ' . $redirectUrl);
            exit;
        }
    }
}

// Handle the actual crawling (from redirect)
if (isset($_GET['analyze']) && $_GET['analyze'] === '1' && !empty($_GET['domain'])) {
    $domain = trim($_GET['domain']);

    // Re-validate URL for security (in case user modified GET params)
    $validation = validateUrl($domain);

    if (!$validation['valid']) {
        $errorMessage = 'Invalid domain: ' . htmlspecialchars($validation['error']);
    } else {
        $domain = $validation['url'];
        $sendEmail = isset($_GET['email']);
        $limit = isset($_GET['limit']) ? max(1, min(1000, intval($_GET['limit']))) : 500;
        $crawled = true;
        
        // Show immediate loading status
        echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('crawl-form').style.display = 'none';
        });
        </script>";
        
        echo "<div id='crawling-status' class='alert alert-info'>";
        echo "<h5><span class='spinner-border spinner-border-sm mr-2'></span>Analyzing SEO for <strong>" . htmlspecialchars($domain) . "</strong> (Limited to $limit pages)</h5>";
        echo "<div id='status'>Initializing SEO analysis...</div>";
        echo "</div>";
        echo str_repeat(' ', 1024);
        flush();
        
        $crawler = new SEOCrawler($domain, $limit);
        $results = $crawler->crawl();
        $totalCrawled = $crawler->getTotalCrawled();
        $executionTime = $crawler->getFormattedExecutionTime();
        $performanceStats = $crawler->getPerformanceStats();
        
        echo "<script>document.getElementById('crawling-status').style.display='none';</script>";
        
        // Send email if requested
        if ($sendEmail) {
            $emailBody = generateEmailReport($results, $domain, $totalCrawled, $executionTime, $performanceStats);
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=UTF-8\r\n";
            $headers .= "From: SEO Meta Tool <YOUR-EMAIL@example.com>\r\n";
            $headers .= "Reply-To: YOUR-EMAIL@example.com\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

            // Sanitize domain for email subject to prevent email header injection
            $safeDomain = preg_replace('/[\r\n\t]/', '', $domain);
            $safeDomain = substr($safeDomain, 0, 100); // Limit length

            $subject = empty($results) ?
                'SEO Report for ' . $safeDomain . ' - No Issues Found' :
                'SEO Report for ' . $safeDomain . ' - ' . count($results) . ' Issues Found';

            if (mail('YOUR-EMAIL@example.com', $subject, $emailBody, $headers)) {
                $emailSent = true;
            }
        }
    }
}

function generateEmailReport($results, $domain, $totalCrawled, $executionTime, $performanceStats) {
    $html = "<html><body>";
    $html .= "<h2>SEO Meta Analysis Report</h2>";
    $html .= "<p><strong>Domain:</strong> " . htmlspecialchars($domain) . "</p>";
    $html .= "<p><strong>Total Pages Crawled:</strong> " . $totalCrawled . "</p>";
    $html .= "<p><strong>Pages with Issues:</strong> " . count($results) . "</p>";
    $html .= "<p><strong>Execution Time:</strong> " . htmlspecialchars($executionTime) . "</p>";
    $html .= "<p><strong>Generated:</strong> " . date('Y-m-d H:i:s') . "</p>";
    
    // Performance metrics
    $html .= "<p><small><strong>Performance:</strong> Parallel processing (" . $performanceStats['batch_size'] . " concurrent requests), " . $performanceStats['cache_size'] . " URLs cached</small></p>";
    
    if (!empty($results)) {
        $html .= "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse:collapse; width:100%;'>";
        $html .= "<tr style='background-color:#f8f9fa;'><th>Page URL</th><th>Issues Found</th></tr>";
        
        foreach ($results as $result) {
            $html .= "<tr>";
            $html .= "<td>" . htmlspecialchars($result['url']) . "</td>";
            $html .= "<td>";
            
            // Color-code issues in email
            foreach ($result['issues'] as $issue) {
                $color = '#e74c3c'; // default red
                
                if ($issue === 'Missing Title Tag') $color = '#e74c3c';
                elseif ($issue === 'Empty Title Tag') $color = '#c0392b';
                elseif ($issue === 'Missing Meta Description') $color = '#2c3e50';
                elseif ($issue === 'Empty Meta Description') $color = '#1a252f';
                elseif ($issue === 'Missing Canonical Tag') $color = '#8e44ad';
                elseif ($issue === 'Empty Canonical Tag') $color = '#732d91';
                elseif ($issue === 'Incorrect format for canonical') $color = '#e67e22';
                elseif ($issue === 'Missing H1 Tag') $color = '#27ae60';
                elseif ($issue === 'Multiple H1 Tags') $color = '#16a085';
                elseif ($issue === 'Empty H1 Tag') $color = '#229954';
                elseif ($issue === 'Missing Meta Viewport') $color = '#a569bd';
                elseif ($issue === 'Missing Meta Charset') $color = '#d68910';
                elseif ($issue === 'Missing Language Attribute') $color = '#7d3c98';
                
                $html .= "<span style='background-color:$color; color:white; padding:2px 6px; border-radius:3px; margin-right:3px; font-size:11px;'>" . htmlspecialchars($issue) . "</span> ";
            }
            
            $html .= "</td>";
            $html .= "</tr>";
        }
        $html .= "</table>";
        
        $html .= "<br><h3>SEO Error Types Legend:</h3>";
        $html .= "<p><strong>Title Issues:</strong><br>";
        $html .= "<span style='background-color:#e74c3c; color:white; padding:2px 6px; border-radius:3px;'>Missing Title Tag</span> ";
        $html .= "<span style='background-color:#c0392b; color:white; padding:2px 6px; border-radius:3px;'>Empty Title Tag</span></p>";
        
        $html .= "<p><strong>Meta Description Issues:</strong><br>";
        $html .= "<span style='background-color:#2c3e50; color:white; padding:2px 6px; border-radius:3px;'>Missing Meta Description</span> ";
        $html .= "<span style='background-color:#1a252f; color:white; padding:2px 6px; border-radius:3px;'>Empty Meta Description</span></p>";
        
        $html .= "<p><strong>Canonical Issues:</strong><br>";
        $html .= "<span style='background-color:#8e44ad; color:white; padding:2px 6px; border-radius:3px;'>Missing Canonical Tag</span> ";
        $html .= "<span style='background-color:#732d91; color:white; padding:2px 6px; border-radius:3px;'>Empty Canonical Tag</span> ";
        $html .= "<span style='background-color:#e67e22; color:white; padding:2px 6px; border-radius:3px;'>Incorrect format for canonical</span></p>";
        
        $html .= "<p><strong>H1 Heading Issues:</strong><br>";
        $html .= "<span style='background-color:#27ae60; color:white; padding:2px 6px; border-radius:3px;'>Missing H1 Tag</span> ";
        $html .= "<span style='background-color:#16a085; color:white; padding:2px 6px; border-radius:3px;'>Multiple H1 Tags</span> ";
        $html .= "<span style='background-color:#229954; color:white; padding:2px 6px; border-radius:3px;'>Empty H1 Tag</span></p>";
        
        $html .= "<p><strong>Technical SEO Issues:</strong><br>";
        $html .= "<span style='background-color:#a569bd; color:white; padding:2px 6px; border-radius:3px;'>Missing Meta Viewport</span> ";
        $html .= "<span style='background-color:#d68910; color:white; padding:2px 6px; border-radius:3px;'>Missing Meta Charset</span> ";
        $html .= "<span style='background-color:#7d3c98; color:white; padding:2px 6px; border-radius:3px;'>Missing Language Attribute</span></p>";
        
    } else {
        $html .= "<p>Excellent! All crawled pages have proper SEO elements: title, meta description, canonical tags, H1 headings, meta viewport, charset, and language attributes.</p>";
    }
    
    $html .= "<p><small>Powered by <a href='https://www.ebizindia.com'>Ebizindia</a> - High-Performance SEO Analysis Tool</small></p>";
    $html .= "</body></html>";
    
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SEO Meta Checker Tool - Ebizindia</title>
    <meta name="description" content="Comprehensive SEO audit tool. Check your website for missing title tags, meta descriptions, canonical URLs, H1 headings, viewport, charset and language attributes. Free tool by Ebizindia.">
    
    <!-- Bootstrap 4 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <style>
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .main-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin: 30px 0;
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin-bottom: 10px;
            font-weight: 300;
            font-size: 2.5rem;
        }
        .header p {
            margin: 0;
            opacity: 0.9;
        }
        .content {
            padding: 40px;
        }
        .form-group label {
            font-weight: 600;
            color: #2c3e50;
        }
        .btn-primary {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 25px;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #2980b9 0%, #1f4e79 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .results-table {
            margin-top: 30px;
        }
        .table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #2c3e50;
        }
        .badge-danger {
            background-color: #e74c3c;
        }
        .badge-title {
            background-color: #e74c3c; /* Red for missing title */
            color: white;
        }
        .badge-title-empty {
            background-color: #c0392b; /* Dark red for empty title */
            color: white;
        }
        .badge-meta {
            background-color: #2c3e50; /* Dark blue for missing meta description */
            color: white;
        }
        .badge-meta-empty {
            background-color: #1a252f; /* Darker blue for empty meta description */
            color: white;
        }
        .badge-canonical {
            background-color: #8e44ad; /* Purple for missing canonical */
            color: white;
        }
        .badge-canonical-empty {
            background-color: #732d91; /* Dark purple for empty canonical */
            color: white;
        }
        .badge-canonical-format {
            background-color: #e67e22; /* Orange for incorrect canonical format */
            color: white;
        }
        .badge-h1-missing {
            background-color: #27ae60; /* Green for missing H1 */
            color: white;
        }
        .badge-h1-multiple {
            background-color: #16a085; /* Teal for multiple H1 */
            color: white;
        }
        .badge-h1-empty {
            background-color: #229954; /* Dark green for empty H1 */
            color: white;
        }
        .badge-viewport {
            background-color: #a569bd; /* Light purple for missing viewport */
            color: white;
        }
        .badge-charset {
            background-color: #d68910; /* Dark orange for missing charset */
            color: white;
        }
        .badge-lang {
            background-color: #7d3c98; /* Dark purple for missing language */
            color: white;
        }
        .footer {
            text-align: center;
            padding: 20px;
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
        }
        .footer a {
            color: #3498db;
            text-decoration: none;
        }
        .footer a:hover {
            text-decoration: underline;
        }
        .summary-cards {
            margin: 20px 0;
        }
        .summary-card {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .summary-card h3 {
            color: #3498db;
            margin-bottom: 5px;
        }
        .summary-card p {
            color: #7f8c8d;
            margin: 0;
        }
        #crawling-status {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            min-width: 300px;
        }
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="main-container">
            <div class="header">
                <h1>SEO Meta Checker Tool</h1>
                <?php if ($crawled && !empty($_GET['domain'])): ?>
                <p>SEO Analysis Results for: <strong><?php echo htmlspecialchars($_GET['domain']); ?></strong></p>
                <?php else: ?>
                <p>⚡ High-Performance SEO analysis: titles, meta descriptions, canonical URLs, H1 headings, viewport, charset & language attributes</p>
                <p><small>🚀 Optimized: Parallel processing for 10-20x faster analysis</small></p>
                <?php endif; ?>
                <?php if (isset($_GET['limit']) && is_numeric($_GET['limit'])): ?>
                <p><small>Current limit: <?php echo max(1, min(1000, intval($_GET['limit']))); ?> pages</small></p>
                <?php endif; ?>
            </div>
            
            <div class="content">
                <div id="crawl-form" <?php echo $crawled ? 'style="display:none;"' : ''; ?>>
                <?php if (!empty($errorMessage)): ?>
                <div class="alert alert-danger" role="alert">
                    <strong>Error:</strong> <?php echo $errorMessage; ?>
                </div>
                <?php endif; ?>
                <form method="post" action="">
                    <input type="hidden" name="action" value="crawl">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                    <div class="form-group">
                        <label for="domain">Website Domain</label>
                        <input type="text" 
                               class="form-control" 
                               id="domain" 
                               name="domain" 
                               placeholder="example.com or https://example.com" 
                               value="<?php echo isset($_GET['domain']) ? htmlspecialchars($_GET['domain']) : ''; ?>"
                               required>
                        <small class="form-text text-muted">
                            Enter domain with or without https://<br>
                            <strong>Tip:</strong> Add ?limit=50 to URL to limit crawl to 50 pages (default: 500)
                        </small>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input type="checkbox" 
                               class="form-check-input" 
                               id="send_email" 
                               name="send_email" 
                               <?php echo (!isset($_GET['email']) || $_GET['email']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="send_email">
                            Email report to YOUR-EMAIL@example.com
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-search"></i> Start SEO Analysis
                    </button>
                </form>
                </div>
                
                <?php if ($crawled): ?>
                <div id="results-section">
                    <!-- Analyze Another Website Button - Moved to Top -->
                    <div class="mb-4 text-center">
                        <button class="btn btn-secondary btn-lg" onclick="resetForm()">
                            🔄 Analyze Another Website
                        </button>
                    </div>
                    
                    <?php if ($emailSent): ?>
                    <div class="success-message">
                        <strong>Success!</strong> Report has been emailed to YOUR-EMAIL@example.com
                    </div>
                    <?php elseif (isset($_GET['email']) && !$emailSent): ?>
                    <div class="alert alert-warning">
                        <strong>Email Issue:</strong> Could not send email. Please check your email settings.
                    </div>
                    <?php endif; ?>
                    
                    <div class="row summary-cards">
                        <div class="col-md-3">
                            <div class="summary-card">
                                <h3><?php echo $totalCrawled; ?></h3>
                                <p>Pages Crawled</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="summary-card">
                                <h3><?php echo count($results); ?></h3>
                                <p>Pages with Issues</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="summary-card">
                                <h3><?php echo max(0, $totalCrawled - count($results)); ?></h3>
                                <p>Pages OK</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="summary-card">
                                <h3><?php echo $executionTime; ?></h3>
                                <p>Execution Time</p>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($results)): ?>
                    <div class="results-table">
                        <h3>Pages Missing SEO Elements</h3>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>Page URL</th>
                                        <th>Missing Elements</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($results as $result): ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo htmlspecialchars($result['url']); ?>" 
                                               target="_blank" 
                                               class="text-primary">
                                                <?php echo htmlspecialchars($result['url']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php foreach ($result['issues'] as $issue): ?>
                                                <?php 
                                                $badgeClass = 'badge-danger'; // default
                                                if ($issue === 'Missing Title Tag') {
                                                    $badgeClass = 'badge-title';
                                                } elseif ($issue === 'Empty Title Tag') {
                                                    $badgeClass = 'badge-title-empty';
                                                } elseif ($issue === 'Missing Meta Description') {
                                                    $badgeClass = 'badge-meta';
                                                } elseif ($issue === 'Empty Meta Description') {
                                                    $badgeClass = 'badge-meta-empty';
                                                } elseif ($issue === 'Missing Canonical Tag') {
                                                    $badgeClass = 'badge-canonical';
                                                } elseif ($issue === 'Empty Canonical Tag') {
                                                    $badgeClass = 'badge-canonical-empty';
                                                } elseif ($issue === 'Incorrect format for canonical') {
                                                    $badgeClass = 'badge-canonical-format';
                                                } elseif ($issue === 'Missing H1 Tag') {
                                                    $badgeClass = 'badge-h1-missing';
                                                } elseif ($issue === 'Multiple H1 Tags') {
                                                    $badgeClass = 'badge-h1-multiple';
                                                } elseif ($issue === 'Empty H1 Tag') {
                                                    $badgeClass = 'badge-h1-empty';
                                                } elseif ($issue === 'Missing Meta Viewport') {
                                                    $badgeClass = 'badge-viewport';
                                                } elseif ($issue === 'Missing Meta Charset') {
                                                    $badgeClass = 'badge-charset';
                                                } elseif ($issue === 'Missing Language Attribute') {
                                                    $badgeClass = 'badge-lang';
                                                }
                                                ?>
                                                <span class="badge <?php echo $badgeClass; ?> mr-2 mb-1"><?php echo htmlspecialchars($issue); ?></span>
                                            <?php endforeach; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-success">
                        <h4>Excellent!</h4>
                        <p>All crawled pages have proper title tags, meta descriptions, and canonical URLs. Your website is well-optimized for SEO!</p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="footer">
                <p>Powered by <a href="https://www.ebizindia.com" target="_blank">Ebizindia</a></p>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Add smooth animations
            $('.main-container').hide().fadeIn(500);
            
            // Auto-hide form if analysis results are shown
            <?php if ($crawled): ?>
            $('#crawl-form').hide();
            <?php endif; ?>
            
            // Form validation
            $('#crawl-form form').on('submit', function() {
                var domain = $('#domain').val().trim();
                if (!domain) {
                    alert('Please enter a domain name');
                    return false;
                }
                
                // Show loading state
                $(this).find('button[type="submit"]').html('<span class="spinner-border spinner-border-sm mr-2"></span>Starting Analysis...');
                $(this).find('button[type="submit"]').prop('disabled', true);
                
                // Note: Form will redirect, so no need to manually hide
                return true;
            });
        });
        
        function resetForm() {
            // Redirect to clean URL without GET parameters to reset the form
            var baseUrl = window.location.href.split('?')[0];
            var limitParam = new URLSearchParams(window.location.search).get('limit');
            
            // Keep the limit parameter if it was set
            if (limitParam && limitParam !== '500') {
                window.location.href = baseUrl + '?limit=' + limitParam;
            } else {
                window.location.href = baseUrl;
            }
        }
    </script>
</body>

</html>
