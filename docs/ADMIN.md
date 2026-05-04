# Admin Panel Guide

Complete guide to using the Poppy Storage Admin Panel for managing buckets and monitoring storage.

## Accessing the Admin Panel

### URL
```
https://your-domain.com/admin
```

### Authentication

The admin panel is protected with **HTTP Basic Authentication**. You'll be prompted for:

- **Username**: Set in `.env` as `ADMIN_USER`
- **Password**: Set in `.env` as `ADMIN_PASS`

**Example credentials in `.env`:**
```env
ADMIN_USER=admin
ADMIN_PASS=your_strong_password
```

### First Login

1. Navigate to `https://storage.example.com/admin`
2. Enter credentials from `.env`
3. Click "Log In" or press Enter
4. You'll see the admin dashboard

## Dashboard Overview

The admin dashboard shows:

- **Total Buckets**: Number of active buckets
- **Total Storage**: Combined size of all buckets
- **Buckets List**: Table with bucket information
  - Bucket name
  - Current size
  - File count
  - Creation date
  - Actions (view, delete)

## Creating a Bucket

### Steps

1. Click **"Create New Bucket"** button
2. Enter bucket name (alphanumeric, hyphens allowed)
3. Click **"Create"**
4. **Important**: Copy and save the API key (shown only once!)

### Bucket Naming Rules

- Alphanumeric characters (a-z, 0-9)
- Hyphens allowed (-)
- Underscores allowed (_)
- No spaces
- 50 characters maximum
- Examples: `my-bucket`, `photos_2024`, `documents`

### After Creation

You'll receive:

```
✓ Bucket Created Successfully

Bucket Name: my-bucket
API Key: your_unique_api_key_here

⚠️ IMPORTANT: This API key will not be shown again!
Please copy and store it in a safe place.
You can regenerate it from the bucket settings if needed.
```

**Next steps:**
1. Save the API key securely (password manager, secure notes, etc.)
2. Use the API key in your application to upload files
3. Share the API key only with authorized users/services

## Managing Buckets

### View Bucket Details

Click on a bucket name to see:

- Total size in bytes and human-readable format
- Number of files
- File list (path, size, upload date)
- Storage usage breakdown

### Delete Bucket

**Warning**: Deletion is permanent and cannot be undone!

1. Click **"Delete"** button next to bucket
2. Confirm deletion with password
3. All files in the bucket are permanently removed
4. Process may take time for large buckets

**Steps:**
```
1. Click "Delete" → Confirmation dialog appears
2. Enter admin password to confirm
3. Click "Yes, Delete Permanently"
4. Bucket and all files are deleted
5. You'll see a success message
```

### Regenerate API Key

To create a new API key for a bucket:

1. Click bucket name or settings icon
2. Click **"Regenerate API Key"** 
3. Confirm the action
4. New key is displayed (save it!)
5. Old key stops working immediately

**When to regenerate:**
- If you suspect key compromise
- Rotating keys periodically for security
- Sharing bucket access with new users

### Monitor Storage Usage

The dashboard shows per-bucket storage:

```
Bucket Name     | Size      | Files | Actions
my-bucket       | 2.5 GB    | 1,250 | View | Delete
photos          | 5.2 GB    | 3,480 | View | Delete
documents       | 892 MB    |   145 | View | Delete
────────────────────────────────────────────────
TOTAL           | 8.6 GB    | 4,875 |
```

### Check File Details

Click "View" on a bucket to see:

- File path (example: `ab/abc123def456.jpg`)
- File size
- MIME type (image/jpeg, application/pdf, etc.)
- Upload timestamp
- Delete option per file

## Admin Security

### Changing Admin Password

To change the admin password:

1. Edit `.env` file on server
2. Change `ADMIN_PASS` to new password
3. No restart required (changes take effect immediately)

**Via SSH:**
```bash
nano .env
# Edit ADMIN_PASS line
# Press Ctrl+X, then Y to save
```

**Via cPanel File Manager:**
1. Right-click `.env` → Edit
2. Change `ADMIN_PASS` value
3. Save

### Securing Admin Access

1. **Use HTTPS only** - Admin panel should never be over HTTP
2. **Strong password** - At least 16 characters, mix of upper/lower/numbers/special
3. **Limited access** - Only give admin credentials to trusted administrators
4. **Change password** after someone leaves your team
5. **Monitor access** - Check server logs for failed login attempts
6. **IP whitelist** (optional) - Restrict admin access to specific IPs via .htaccess

### Optional: IP Whitelisting

Edit `public/.htaccess` to restrict admin panel to specific IPs:

```apache
# Restrict admin panel to specific IPs
<Files "admin.php">
    Order Deny,Allow
    Deny from all
    Allow from 192.168.1.100    # Your office IP
    Allow from 203.0.113.0      # Your home IP
</Files>
```

## Monitoring & Maintenance

### Regular Tasks

**Daily:**
- Monitor for unusual upload activity
- Check total storage usage

**Weekly:**
- Review new buckets created
- Check for abandoned buckets

**Monthly:**
- Verify backups are working
- Review and rotate API keys if needed
- Check logs for errors

### Checking Server Logs

**Via SSH:**
```bash
# PHP error logs
tail -f /var/log/php-errors.log

# Apache access logs
tail -f /var/log/apache2/access.log

# Poppy Storage logs
tail -f storage/error.log
```

### Disk Space Monitoring

Check available disk space:

```bash
# Check root partition
df -h /

# Check storage directory size
du -sh storage/

# Find largest buckets
du -sh storage/buckets/*/
```

### Performance Optimization

For optimal performance:

1. **Archive old files** - Consider archiving old buckets
2. **Cleanup unused buckets** - Delete buckets no longer needed
3. **Monitor file count** - Very large buckets (10k+ files) may slow queries
4. **Enable caching** - Configure Cloudflare or CDN caching

## Troubleshooting Admin Panel

### Can't Log In

**Problem**: Authentication fails even with correct credentials

**Solutions**:
1. Verify `.env` file exists and is readable
2. Check `ADMIN_USER` and `ADMIN_PASS` values
3. Clear browser cache and cookies
4. Try a different browser
5. Check PHP error logs

### Blank Admin Page

**Problem**: Admin page loads but shows nothing

**Solutions**:
1. Check PHP error logs in `storage/error.log`
2. Verify PHP version is 7.4+
3. Check file permissions on `app/Admin/` files
4. Verify `.env` file is readable by Apache

### Bucket Creation Fails

**Problem**: Error when trying to create bucket

**Solutions**:
1. Check storage directory permissions (should be 750)
2. Verify `storage/` is writable by Apache
3. Check available disk space
4. Review error message for specific issue

### Can't Delete Bucket

**Problem**: Delete button doesn't work or shows error

**Solutions**:
1. Check if bucket directory still exists
2. Verify permissions on bucket directory
3. Close any files being served from that bucket
4. Check server disk space
5. Review Apache error logs

### Slow Dashboard Loading

**Problem**: Admin dashboard takes long time to load

**Solutions**:
1. Check file count in each bucket (very large counts slow things down)
2. Verify server resources (CPU, memory)
3. Consider archiving very old buckets
4. Check network connection speed

## API Key Management

### Generate New Keys Programmatically

Future versions may support API key generation via API. Current process:

1. Create bucket via admin panel
2. Copy API key immediately
3. Store securely in application configuration

### Key Rotation Strategy

Recommended rotation schedule:

- Every **90 days** (standard security practice)
- Immediately if compromise suspected
- When personnel changes occur

**Process:**
1. Generate new key via admin panel
2. Update application configuration with new key
3. Monitor for old key usage (if logging enabled)
4. After grace period (1-2 weeks), old key stops working

## Support

For issues with the admin panel:

1. Check [TROUBLESHOOTING.md](TROUBLESHOOTING.md)
2. Review error logs in `storage/error.log`
3. Check [ARCHITECTURE.md](ARCHITECTURE.md) for technical details
4. Report bugs on GitHub with:
   - Error message
   - Steps to reproduce
   - Server environment info (PHP version, OS, etc.)

## Next Steps

- Learn about the [API](API.md) to integrate with applications
- Review [SECURITY.md](SECURITY.md) for security best practices
- Check [ARCHITECTURE.md](ARCHITECTURE.md) to understand how data is stored
