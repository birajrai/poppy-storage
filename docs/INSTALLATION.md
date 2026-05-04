# Installation & Setup Guide

Complete step-by-step instructions for installing and configuring Poppy Storage.

## Prerequisites

- **PHP 7.4 or higher**
- **Apache web server** with mod_rewrite enabled (for .htaccess support)
- **File system write permissions** for the `storage/` directory
- **cPanel account** (optional, for hosting) or VPS/local machine with SSH access

## Installation Steps

### 1. Download & Upload

**Option A: cPanel File Manager**
1. Download `poppy-storage` repository
2. Log in to cPanel
3. Navigate to File Manager
4. Upload the entire `poppy-storage` folder to your home directory
5. Ensure the web root (public_html) points to `poppy-storage/public/`

**Option B: SSH/Command Line**
```bash
# Clone or download repository
cd /home/your-user
git clone https://github.com/your-repo/poppy-storage.git
# Or unzip uploaded file
unzip poppy-storage.zip
```

### 2. Configure Environment Variables

Create `.env` file from template:

```bash
cp .env.example .env
```

Edit `.env` with your settings:

```env
# Base URL where files will be served from
URL=https://storage.example.com

# Admin panel credentials
ADMIN_USER=admin
ADMIN_PASS=your_super_strong_password_here

# Maximum file size in bytes (10MB = 10485760)
MAX_SIZE=10485760

# CSRF token secret (generate a random string)
CSRF_SECRET=8b0181a1d202f70561e70fc19b95e6449ec5a1072a14e6aaa867a858c6395aea
```

### 3. Set File Permissions

**Via SSH:**
```bash
# Make storage directory writable
chmod 750 storage/

# Make JSON files readable/writable
chmod 640 storage/buckets.json
chmod 640 storage/buckets/*/files.json
```

**Via cPanel File Manager:**
1. Right-click `storage/` → Change Permissions
2. Set to `750` (rwxr-x---)
3. Repeat for `buckets.json` and all `files.json` files

### 4. Enable Apache Modules

**On cPanel:**
1. Go to WHM (if you have root access)
2. Navigate to "Apache Modules"
3. Ensure `mod_rewrite` is enabled
4. Restart Apache

**On VPS/Local:**
```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### 5. Configure Domain

**Option A: cPanel Addon Domain**
1. Log in to cPanel
2. Go to "Addon Domains"
3. Create a new domain pointing to `poppy-storage/public/`
4. SSL will be auto-installed if available

**Option B: Apache VirtualHost (VPS/Local)**
```apache
<VirtualHost *:80>
    ServerName storage.example.com
    DocumentRoot /var/www/poppy-storage/public
    
    <Directory /var/www/poppy-storage/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Then restart Apache:
```bash
sudo systemctl restart apache2
```

### 6. Enable SSL/HTTPS

**cPanel:**
- SSL should auto-install via AutoSSL
- Go to SSL/TLS Status to verify

**Let's Encrypt (Manual):**
```bash
sudo certbot certonly --apache -d storage.example.com
```

### 7. Create First Bucket

1. Open browser and go to: `https://storage.example.com/admin`
2. Enter admin credentials from `.env`
3. Click "Create Bucket"
4. Enter bucket name (e.g., `my-bucket`)
5. **Copy and save the API key** (shown only once!)
6. Click "Create"

### 8. Test Upload

**Using cURL:**
```bash
curl -X POST \
  -F "file=@/path/to/image.jpg" \
  "https://storage.example.com/api/upload?bucket=my-bucket&key=YOUR_API_KEY"
```

**Using JavaScript:**
```js
const formData = new FormData();
formData.append("file", yourFile);

const res = await fetch(
  "https://storage.example.com/api/upload?bucket=my-bucket&key=YOUR_API_KEY",
  { method: "POST", body: formData }
);

const data = await res.json();
console.log(data.url); // Should print file URL
```

## Deployment Checklist

- [ ] `.env` file created and configured
- [ ] `.env` is in `.gitignore` (don't commit credentials!)
- [ ] File permissions set correctly (storage/ = 750)
- [ ] Domain configured and pointing to `public/` folder
- [ ] SSL/HTTPS enabled
- [ ] Apache mod_rewrite enabled
- [ ] First bucket created
- [ ] Upload test successful
- [ ] Admin credentials saved securely
- [ ] API key saved for each bucket
- [ ] Backup plan in place

## Troubleshooting

### 403 Forbidden Error
- Check file permissions on `storage/` directory
- Verify Apache can write to `storage/`
- Check `.htaccess` file isn't blocking requests

### 404 Not Found
- Verify domain points to `poppy-storage/public/`
- Check Apache mod_rewrite is enabled
- Verify `.htaccess` file exists in public folder

### Upload Fails
- Check `MAX_SIZE` in `.env` matches your needs
- Verify `php.ini` settings:
  ```
  post_max_size = 10M
  upload_max_filesize = 10M
  ```
- Check file permissions on `storage/` directory

### Admin Panel Blank
- Check PHP error logs
- Verify `.env` file is readable by Apache
- Check `public/index.php` path configuration

## Performance Optimization

### Enable Cloudflare (Recommended)
1. Add domain to Cloudflare account
2. Update nameservers
3. Purge cache after uploads: `https://api.cloudflare.com/client/v4/zones/{zone_id}/purge_cache`

### Enable Gzip Compression
Add to `.htaccess` in `public/`:
```apache
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml
    AddOutputFilterByType DEFLATE application/json
</IfModule>
```

### Database-backed File Tracking (Future)
Currently uses JSON files. For high-scale deployments:
1. Set up MySQL database
2. Replace `load_files()` and `save_files()` functions
3. Add database indexing for faster queries

## Backup Strategy

### Regular Backups
```bash
# Daily backup script
tar -czf backup-$(date +%Y%m%d).tar.gz storage/
```

### Restore from Backup
```bash
tar -xzf backup-20240115.tar.gz -C /path/to/poppy-storage/
```

## Security Recommendations

1. **Regenerate credentials** after any security incident
2. **Rotate API keys** periodically
3. **Monitor file uploads** for malicious content
4. **Enable HTTPS** (mandatory in production)
5. **Set up firewall rules** to block suspicious IPs
6. **Keep PHP updated** to latest security patches
7. **Review admin logs** regularly

See [SECURITY.md](SECURITY.md) for detailed security guidance.

## Next Steps

- Read [API.md](API.md) to start integrating with your application
- Check [ADMIN.md](ADMIN.md) for admin panel features
- Review [SECURITY.md](SECURITY.md) for security best practices
- See [TROUBLESHOOTING.md](TROUBLESHOOTING.md) if you encounter issues
