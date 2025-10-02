# Quick Configuration Guide - START HERE

## Important: This Tool Does NOT Use a Database

**No SQL setup required!** This is a stateless tool that analyzes websites in real-time without storing any data.

## Before You Start

You MUST edit `index.php` to configure your email address and settings.

## Step 1: Update Email Address (REQUIRED)

Open `index.php` in a text editor and find **line 370**:

**FIND THIS:**
```php
if (mail('ebizindia@gmail.com', $subject, $emailBody, $headers)) {
```

**CHANGE TO:**
```php
if (mail('YOUR-EMAIL@example.com', $subject, $emailBody, $headers)) {
```

Replace `YOUR-EMAIL@example.com` with your actual email address.

## Step 2: Update From Email (REQUIRED)

Find **lines 350-351**:

**FIND THIS:**
```php
$headers .= "From: SEO Meta Tool <arun@ebizindia.com>\r\n";
$headers .= "Reply-To: arun@ebizindia.com\r\n";
```

**CHANGE TO:**
```php
$headers .= "From: SEO Meta Tool <your-email@yourdomain.com>\r\n";
$headers .= "Reply-To: your-email@yourdomain.com\r\n";
```

## That's It!

These are the MINIMUM required changes. The tool will now work with your email address.

## Optional: Advanced Configuration

For better maintainability, you can add a configuration section at the top of `index.php`.

See [CONFIGURATION.md](CONFIGURATION.md) for the complete advanced setup with constants.

## Quick Test

1. Upload `index.php` to your web server
2. Navigate to it in your browser
3. Enter a domain (like `example.com`)
4. Check the "Email report" checkbox
5. Click "Start SEO Analysis"
6. Check your email for the report

## No Database = No Setup!

Unlike many tools, this SEO checker:
- ✅ Requires NO database installation
- ✅ Requires NO SQL queries
- ✅ Requires NO data storage
- ✅ Works immediately after email configuration
- ✅ Processes everything in memory during the crawl

Simply upload the file and start analyzing!

## Troubleshooting

**Email not received?**
- Check spam folder
- Verify PHP mail() function is configured on your server
- Check the email addresses you entered are correct

**Need more help?**
- See [CONFIGURATION.md](CONFIGURATION.md) for detailed setup
- See [docs/installation.md](docs/installation.md) for server setup
- Check [README.md](README.md) for full documentation
