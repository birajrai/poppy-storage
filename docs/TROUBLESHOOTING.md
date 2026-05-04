# Troubleshooting Guide

Common issues and solutions for Poppy Storage.

## Installation & Setup Issues

### Issue: 403 Forbidden Error

**Symptoms**: Browser shows "403 Forbidden" when accessing storage domain

**Causes**:
1. Incorrect file permissions on storage directory
2. Apache doesn't have write permissions
3. .htaccess blocking requests

**Solutions**:

Check file permissions:
```bash
# Via SSH
ls -la storage/
# Should show: drwxr-x--- (750)

# Fix permissions
chmod 750 storage/
chmod 640 storage/buckets.json
```

Check Apache configuration:
```bash
# Verify mod_rewrite is enabled
apache2ctl -M | grep rewrite

# If not enabled
sudo a2enmod rewrite
sudo systemctl restart apache2
```

Check .htaccess in public directory:
```bash
# Verify .htaccess exists
ls -la public/.htaccess

# Check for syntax errors
apache2ctl configtest
```

### Issue: 404 Not Found

**Symptoms**: All requests return 404, even admin page

**Causes**:
1. Domain not pointing to public/ directory
2. .htaccess rewrite rules not working
3. index.php not in public folder

**Solutions**:

Verify directory structure:
```bash
# Check if index.php exists
ls -la public/index.php

# Check if .htaccess exists
ls -la public/.htaccess

# Verify directory has correct permissions
ls -la public/
```

Check domain configuration (cPanel):
1. Go to "Addon Domains"
2. Verify domain points to: poppy-storage/public
3. Check not pointing to poppy-storage/ (without /public)

Check Apache VirtualHost (for manual setup):
```apache
<VirtualHost *:80>
    ServerName storage.example.com
    DocumentRoot /var/www/poppy-storage/public
    <Directory /var/www/poppy-storage/public>
        AllowOverride All
    </Directory>
</VirtualHost>
```

### Issue: Can't Access Admin Panel

**Symptoms**: Browser shows auth dialog but credentials don't work

**Causes**:
1. Credentials in .env are wrong
2. .env file not readable by PHP
3. PHP can't load .env file

**Solutions**:

Verify .env file exists:
```bash
# Check file exists and is readable
ls -la .env

# Make sure it's readable
chmod 644 .env
```

Check .env contents:
```bash
# View credentials (be careful!)
grep -E "ADMIN_USER|ADMIN_PASS" .env

# Should output:
# ADMIN_USER=admin
# ADMIN_PASS=your_password
```

Verify PHP can read .env:
```bash
# Test PHP file reading
php -r "echo file_get_contents('.env');" | head -5
```

Check PHP error logs:
```bash
# View PHP errors
tail -f /var/log/php-errors.log

# Or check in browser - enable debugging temporarily
```

### Issue: Blank Admin Panel

**Symptoms**: Admin page loads but shows nothing, no error

**Causes**:
1. PHP version too old (need 7.4+)
2. PHP extensions missing (finfo, etc.)
3. Error display disabled and error log not accessible

**Solutions**:

Check PHP version:
```bash
php -v
# Should show 7.4 or higher
```

Check required PHP extensions:
```bash
php -m | grep -E "finfo|json|hash|standard"
# All should be present
```

Enable error display temporarily:
```php
// Add to top of public/index.php (temporarily)
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

Check PHP error log:
```bash
# View recent errors
tail -20 /var/log/php-errors.log

# Or via cPanel - check Error Log
```

## File Upload Issues

### Issue: Upload Returns 403 Forbidden

**Symptoms**: Upload endpoint returns "Unauthorized" error

**Causes**:
1. API key is incorrect
2. Bucket doesn't exist
3. API key format wrong

**Solutions**:

Verify API key:
1. Go to admin panel
2. Check the exact API key for the bucket
3. Copy it exactly (no spaces)
4. Regenerate if unsure

Verify bucket exists:
1. Log in to admin panel
2. See list of buckets
3. Create bucket if missing

Test with curl:
```bash
# Test upload with correct format
curl -X POST \
  -F "file=@image.jpg" \
  "https://storage.example.com/api/upload?bucket=my-bucket&key=YOUR_API_KEY"

# Should return: {"success": true, "url": "..."}
```

### Issue: Upload Returns 400 Bad Request

**Symptoms**: "No file provided" or "Invalid file type" error

**Causes**:
1. No file attached to request
2. File type not allowed (only JPG, PNG, WebP, PDF)
3. File too large (over 10MB)

**Solutions**:

Verify file type:
```bash
# Check what file type server thinks it is
file image.jpg
# Should show: image/jpeg

# Check MIME type
php -r "echo mime_content_type('image.jpg');"
```

Verify file size:
```bash
# Check file size in bytes
ls -lah image.jpg
# Look for size after permissions

# Check if over 10MB (10485760 bytes)
du -b image.jpg
```

Verify request format:
```bash
# Make sure using multipart/form-data
curl -X POST \
  -F "file=@image.jpg" \  # Note: -F for multipart
  "https://storage.example.com/api/upload?bucket=my-bucket&key=KEY"

# NOT this format (application/x-www-form-urlencoded):
curl -X POST \
  -d "file=image.jpg" \
  "https://storage.example.com/api/upload?bucket=my-bucket&key=KEY"
```

### Issue: Upload Takes Too Long or Times Out

**Symptoms**: Upload hangs or connection timeout after several seconds

**Causes**:
1. Server performance issue
2. PHP execution time too short
3. Network latency
4. Large file taking too long

**Solutions**:

Check PHP timeout settings:
```bash
# View PHP config
php -i | grep timeout
# Should show max_execution_time = 30 (or higher)

# Increase if needed (in php.ini)
max_execution_time = 300  # 5 minutes
```

Check server resources:
```bash
# Check CPU usage
top -bn1 | head -20

# Check memory usage
free -h

# Check disk space
df -h /

# If any are maxed out, that's the issue
```

Try smaller file:
```bash
# Test with small file to verify it works
dd if=/dev/zero of=test-small.jpg bs=1024 count=100  # 100KB
curl -X POST \
  -F "file=@test-small.jpg" \
  "https://storage.example.com/api/upload?bucket=my-bucket&key=KEY"
```

### Issue: File Uploaded But Can't Access It

**Symptoms**: Upload succeeds but file URL returns 404

**Causes**:
1. Bucket name in upload vs. retrieval doesn't match
2. File path incorrect
3. File wasn't actually written to disk

**Solutions**:

Verify file was uploaded:
```bash
# Check storage directory
ls -la storage/buckets/my-bucket/

# Check if file exists in correct shard
ls -la storage/buckets/my-bucket/ab/

# Should see files with hash names
```

Verify metadata:
```bash
# Check files.json
cat storage/buckets/my-bucket/files.json

# Should contain uploaded file
```

Verify access URL:
```bash
# Make sure bucket and file path match
# From upload response: "url": "...?bucket=my-bucket&f=ab/hash123.jpg"
# Access with exact same parameters

curl "https://storage.example.com/api/file?bucket=my-bucket&f=ab/hash123.jpg"
```

## API Issues

### Issue: "Invalid bucket" Error

**Symptoms**: Both upload and retrieve return 403 "Invalid bucket"

**Causes**:
1. Bucket name spelled wrong
2. Bucket name contains invalid characters
3. Bucket doesn't exist

**Solutions**:

Check bucket name:
```bash
# Valid characters: a-z, 0-9, hyphens, underscores
# Example: my-bucket-2024, photos_v1, bucket123

# Invalid: My Bucket, bucket!, my bucket
```

List existing buckets:
```bash
# Check buckets.json
cat storage/buckets.json

# Should show array of bucket names
```

Create bucket if missing:
1. Log in to admin panel
2. Click "Create New Bucket"
3. Enter exact bucket name
4. Copy API key
5. Try upload/access again

### Issue: API Returns 413 Payload Too Large

**Symptoms**: All uploads fail with "Payload too large" error

**Causes**:
1. File exceeds MAX_SIZE (10MB)
2. PHP post_max_size too small
3. MAX_SIZE configuration too small

**Solutions**:

Check file size:
```bash
# File must be under 10MB (10485760 bytes)
ls -lah file.jpg

# To convert:
# 1 MB = 1048576 bytes
# 10 MB = 10485760 bytes
```

Check MAX_SIZE in .env:
```bash
# View setting
grep MAX_SIZE .env

# If need larger size (50MB = 52428800 bytes)
# Edit .env and change MAX_SIZE=52428800
```

Check PHP settings:
```bash
# View PHP limits
php -i | grep -E "post_max_size|upload_max_filesize"

# Should show values >= MAX_SIZE in .env
# If not, edit php.ini:
post_max_size = 50M
upload_max_filesize = 50M
```

## Admin Panel Issues

### Issue: Can't Create Bucket

**Symptoms**: Create bucket button doesn't work or shows error

**Causes**:
1. Invalid bucket name
2. Bucket already exists
3. Disk space issue
4. File permission issue

**Solutions**:

Check bucket name:
```bash
# Valid: alphanumeric, hyphens, underscores
# Invalid: spaces, special chars, uppercase
```

Verify disk space:
```bash
# Check available space
df -h /

# Need at least 100MB free
```

Check permissions:
```bash
# storage/ must be writable
ls -la storage/
# Should show: drwxr-x---

# Fix if needed:
chmod 750 storage/
```

Check for existing bucket:
```bash
# List buckets in buckets.json
cat storage/buckets.json

# If bucket already exists, use different name
```

### Issue: Can't Delete Bucket

**Symptoms**: Delete button doesn't work or shows error

**Causes**:
1. Bucket directory doesn't exist
2. File permission issue
3. Files still in use

**Solutions**:

Check bucket directory:
```bash
# Verify bucket directory exists
ls -la storage/buckets/my-bucket/

# If doesn't exist, check buckets.json
cat storage/buckets.json
```

Check permissions:
```bash
# Bucket directory must be writable
ls -la storage/buckets/my-bucket/

# Fix if needed:
chmod 750 storage/buckets/my-bucket/
```

Check for open files:
```bash
# List processes with open files in bucket
lsof storage/buckets/my-bucket/ 2>/dev/null

# If any shown, close them (browser tab, etc.)
```

### Issue: Dashboard Shows Incorrect Sizes

**Symptoms**: Bucket size doesn't match actual files

**Causes**:
1. files.json out of sync with actual files
2. Calculation error
3. Concurrent upload/delete issue

**Solutions**:

Recalculate sizes:
```bash
# Check actual size on disk
du -sh storage/buckets/my-bucket/

# Check files.json size calculation
php -r "
\$files = json_decode(file_get_contents('storage/buckets/my-bucket/files.json'), true);
\$total = array_sum(array_column(\$files, 'size'));
echo 'Metadata size: ' . \$total . ' bytes\n';
"
```

Verify files exist:
```bash
# Count actual files
find storage/buckets/my-bucket/ -type f ! -name "*.json" | wc -l

# Count in files.json
cat storage/buckets/my-bucket/files.json | grep -o '"path"' | wc -l
```

Rebuild metadata (future feature):
- No automatic rebuild in current version
- Manual fix by editing files.json

## Performance Issues

### Issue: Admin Dashboard Loads Slowly

**Symptoms**: Admin page takes 5+ seconds to load

**Causes**:
1. Large number of files in buckets
2. Large files.json files
3. Slow server/network
4. Browser caching issue

**Solutions**:

Check for many files:
```bash
# Count total files across all buckets
find storage/buckets -name "files.json" -exec \
  sh -c 'echo -n "$1: "; grep -o "\"path\"" "$1" | wc -l' _ {} \;
```

Check file sizes:
```bash
# See size of each files.json
du -h storage/buckets/*/files.json
```

If files.json > 10MB:
1. Archive old buckets
2. Delete unused buckets
3. Migrate to database (future)

Clear browser cache:
```bash
# Ctrl+Shift+Delete (most browsers)
# Or: Ctrl+F5 hard refresh
```

### Issue: Uploads Are Slow

**Symptoms**: File takes minutes to upload

**Causes**:
1. Slow network connection
2. Server overload
3. Large file size
4. Server processing bottleneck

**Solutions**:

Test network speed:
```bash
# Check connection quality
ping storage.example.com
# Should show < 100ms latency

# Or use online speed test
```

Check server resources:
```bash
# CPU usage
top -bn1 | head -20

# Memory
free -h

# Disk I/O
iostat -x 1 5
```

Monitor during upload:
```bash
# In separate terminal during upload:
watch -n 1 'du -sh storage/'
```

Try smaller file:
```bash
# Test with known-good small file
dd if=/dev/zero of=test-1mb.jpg bs=1024 count=1024
# Should upload instantly
```

## Network & Connectivity

### Issue: SSL Certificate Error

**Symptoms**: Browser shows security warning or "NET::ERR_CERT_*"

**Causes**:
1. SSL certificate expired
2. Domain mismatch
3. Self-signed certificate
4. Certificate not installed

**Solutions**:

Check certificate status:
```bash
# View certificate details
openssl s_client -connect storage.example.com:443 < /dev/null
```

Renew Let's Encrypt certificate:
```bash
# Check expiration
certbot certificates

# Renew if within 30 days of expiry
certbot renew --dry-run
certbot renew
```

Verify domain name:
```bash
# Domain in URL must match certificate
# If certificate for storage.example.com
# Don't access via storage.example.org (different domain)
```

### Issue: CORS Errors in Browser

**Symptoms**: JavaScript console shows CORS error, file won't load

**Causes**:
1. Accessing from different domain
2. CORS headers not set
3. Preflight request failing

**Solutions**:

Use same domain:
```js
// If on example.com, access files on example.com
// NOT on storage.example.com from different site

// Good:
<img src="/api/file?bucket=x&f=y" />

// Requires CORS setup:
<img src="https://storage.example.com/api/file?bucket=x&f=y" />
```

Configure CORS in app config:
```php
// In api/File.php (for cross-origin access)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
```

Test with curl:
```bash
# Test if CORS headers present
curl -i -H "Origin: http://example.com" \
  "https://storage.example.com/api/file?bucket=x&f=y"

# Should show Access-Control-* headers
```

## Database Issues (Future)

These will be relevant when migration to database happens.

## Getting Help

If issue persists after trying solutions:

1. Check error logs:
   ```bash
   tail -50 /var/log/php-errors.log
   tail -50 /var/log/apache2/error.log
   tail -50 storage/error.log
   ```

2. Check PHP info:
   ```bash
   php -i
   ```

3. Collect information:
   - PHP version
   - Server OS and version
   - Browser and version
   - Exact error message
   - Steps to reproduce

4. Report issue with:
   - Error logs
   - Configuration details (without credentials!)
   - Steps to reproduce
   - Expected vs. actual behavior

## Related Documentation

- [INSTALLATION.md](INSTALLATION.md) - Setup troubleshooting
- [SECURITY.md](SECURITY.md) - Security issues
- [ARCHITECTURE.md](ARCHITECTURE.md) - Technical details
- [API.md](API.md) - API issues
