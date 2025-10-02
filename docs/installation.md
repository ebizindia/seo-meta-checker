# Installation Guide

Detailed step-by-step instructions for installing the SEO Meta Checker Tool.

## Table of Contents
1. [System Requirements](#system-requirements)
2. [Installation Methods](#installation-methods)
3. [Web Server Configuration](#web-server-configuration)
4. [Post-Installation](#post-installation)
5. [Troubleshooting](#troubleshooting)

## System Requirements

### Minimum Requirements
- **PHP**: 8.0 or higher
- **Memory**: 256 MB
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Disk Space**: 5 MB

### Required PHP Extensions
- cURL (for making HTTP requests)
- DOM (for HTML parsing)
- libxml (XML/HTML processing)
- mbstring (recommended for character encoding)

### Verify PHP Extensions
```bash
php -m | grep -E 'curl|dom|libxml|mbstring'
```

## Installation Methods

### Method 1: Direct Download

1. **Download the latest release**
   ```bash
   wget https://github.com/yourusername/seo-meta-checker/archive/refs/heads/main.zip
   unzip main.zip
   cd seo-meta-checker-main
   ```

2. **Move to web directory**
   ```bash
   # For Apache
   sudo mv * /var/www/html/seo-checker/
   
   # For Nginx
   sudo mv * /usr/share/nginx/html/seo-checker/
   ```

3. **Set permissions**
   ```bash
   sudo chown -R www-data:www-data /var/www/html/seo-checker/
   sudo chmod 644 /var/www/html/seo-checker/index.php
   ```

### Method 2: Git Clone

1. **Clone the repository**
   ```bash
   cd /var/www/html/
   git clone https://github.com/yourusername/seo-meta-checker.git
   cd seo-meta-checker
   ```

2. **Set permissions**
   ```bash
   sudo chown -R www-data:www-data .
   sudo chmod 644 index.php
   ```

## Web Server Configuration

### Apache Configuration

#### Option A: Using .htaccess (Recommended)
The `.htaccess` file is included and will work if `mod_rewrite` is enabled.

**Enable mod_rewrite:**
```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

**Verify AllowOverride:**
Edit `/etc/apache2/sites-available/000-default.conf`:
```apache
<Directory /var/www/html>
    AllowOverride All
    Require all granted
</Directory>
```

#### Option B: VirtualHost Configuration
Create `/etc/apache2/sites-available/seo-checker.conf`:
```apache
<VirtualHost *:80>
    ServerName seo-checker.yourdomain.com
    DocumentRoot /var/www/html/seo-checker
    
    <Directory /var/www/html/seo-checker>
        Options -Indexes
        AllowOverride All
        Require all granted
        
        # Rewrite rules
        RewriteEngine On
        RewriteBase /
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^.*$ index.php [L]
    </Directory>
    
    # PHP settings
    php_value max_execution_time 600
    php_value memory_limit 256M
    
    ErrorLog ${APACHE_LOG_DIR}/seo-checker-error.log
    CustomLog ${APACHE_LOG_DIR}/seo-checker-access.log combined
</VirtualHost>
```

**Enable the site:**
```bash
sudo a2ensite seo-checker
sudo systemctl restart apache2
```

### Nginx Configuration

Create `/etc/nginx/sites-available/seo-checker`:
```nginx
server {
    listen 80;
    server_name seo-checker.yourdomain.com;
    root /usr/share/nginx/html/seo-checker;
    index index.php;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Main location
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP processing
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        
        # PHP settings
        fastcgi_param PHP_VALUE "max_execution_time=600
                                 memory_limit=256M";
    }

    # Deny access to hidden files
    location ~ /\. {
        deny all;
    }

    # Logging
    access_log /var/log/nginx/seo-checker-access.log;
    error_log /var/log/nginx/seo-checker-error.log;
}
```

**Enable the site:**
```bash
sudo ln -s /etc/nginx/sites-available/seo-checker /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

## Post-Installation

### 1. Test the Installation
Navigate to your installation URL:
```
http://yourdomain.com/seo-checker/
```

You should see the SEO Meta Checker Tool interface.

### 2. Configure Email (Optional)
Edit `index.php` around line 370:
```php
if (mail('your-email@example.com', $subject, $emailBody, $headers)) {
```

Replace `your-email@example.com` with your actual email address.

### 3. Test PHP Extensions
Create a test file `test.php`:
```php
<?php
phpinfo();
?>
```

Visit `http://yourdomain.com/seo-checker/test.php` and verify:
- PHP version 8.0+
- cURL extension enabled
- DOM extension enabled
- libxml extension enabled

**Delete the test file after verification:**
```bash
rm test.php
```

### 4. Adjust PHP Settings (If Needed)

#### For Apache with mod_php
Edit `php.ini` or use `.htaccess`:
```ini
max_execution_time = 600
memory_limit = 256M
post_max_size = 20M
```

#### For PHP-FPM
Edit `/etc/php/8.2/fpm/php.ini`:
```ini
max_execution_time = 600
memory_limit = 256M
post_max_size = 20M
```

Restart PHP-FPM:
```bash
sudo systemctl restart php8.2-fpm
```

### 5. Security Hardening

**Disable directory listing:**
Already configured in `.htaccess`

**Set proper file permissions:**
```bash
# Files should be readable but not writable by web server
find . -type f -exec chmod 644 {} \;

# Directories should be executable
find . -type d -exec chmod 755 {} \;
```

**Add security headers:**
Already configured in `.htaccess`

## Troubleshooting

### Issue: "Maximum execution time exceeded"

**Solution 1:** Increase PHP time limit
```bash
# Edit php.ini
max_execution_time = 600
```

**Solution 2:** Use URL parameter
```
http://yourdomain.com/seo-checker/?limit=50
```

### Issue: "Allowed memory size exhausted"

**Solution:** Increase memory limit
```bash
# Edit php.ini
memory_limit = 512M
```

### Issue: cURL not working

**Solution:** Verify cURL is installed
```bash
# Check if cURL extension is loaded
php -m | grep curl

# Install if missing (Ubuntu/Debian)
sudo apt-get install php-curl

# Restart web server
sudo systemctl restart apache2
# OR
sudo systemctl restart nginx
```

### Issue: Email not sending

**Solution 1:** Check mail configuration
```bash
# Test PHP mail
php -r "mail('test@example.com', 'Test', 'Test message');"
```

**Solution 2:** Use external SMTP
Consider implementing PHPMailer for reliable email delivery.

### Issue: Permission denied errors

**Solution:** Fix file permissions
```bash
sudo chown -R www-data:www-data /var/www/html/seo-checker/
sudo chmod -R 755 /var/www/html/seo-checker/
```

### Issue: 404 errors with clean URLs

**Solution:** Enable mod_rewrite (Apache)
```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### Issue: Slow crawling performance

**Solutions:**
1. Reduce concurrent requests in code
2. Increase timeout values
3. Limit pages with `?limit=100`
4. Check target website response time

## Performance Tuning

### For High-Traffic Usage

**1. Enable PHP OPcache**
Edit `php.ini`:
```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
```

**2. Increase PHP-FPM Workers**
Edit `/etc/php/8.2/fpm/pool.d/www.conf`:
```ini
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
```

**3. Enable Browser Caching**
Already configured in `.htaccess`

## Updating

### Git Installation
```bash
cd /var/www/html/seo-checker
git pull origin main
```

### Manual Installation
1. Backup current `index.php`
2. Download new version
3. Replace files
4. Test functionality

## Uninstallation

```bash
# Remove files
sudo rm -rf /var/www/html/seo-checker/

# Remove Apache configuration (if created)
sudo a2dissite seo-checker
sudo rm /etc/apache2/sites-available/seo-checker.conf
sudo systemctl restart apache2

# Remove Nginx configuration (if created)
sudo rm /etc/nginx/sites-enabled/seo-checker
sudo rm /etc/nginx/sites-available/seo-checker
sudo systemctl restart nginx
```

## Support

For additional help:
- Open an issue on GitHub
- Check the main README.md
- Review troubleshooting section

---

**Installation complete!** You can now start analyzing websites for SEO issues.
