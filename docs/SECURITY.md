# Security Guide

Comprehensive security information and best practices for Poppy Storage.

## Overview

Poppy Storage is designed with security as a core principle. This guide covers security features, risks, and best practices.

## Security Features

### ✅ Implemented

1. **File System Isolation**
   - Sensitive files (`storage/`, `.env`) outside web root
   - Only `public/` is web-accessible
   - `.htaccess` rules prevent direct file access

2. **API Key Security**
   - Keys generated with `random_bytes(16)` (cryptographically secure)
   - Keys hashed with BCRYPT before storage
   - Never stored or logged in plaintext
   - Unique per bucket

3. **MIME Type Validation**
   - Detected from file headers (not extension)
   - Whitelist enforced (JPG, PNG, WebP, PDF only)
   - Prevents malicious file uploads (e.g., .exe as .jpg)

4. **Path Traversal Prevention**
   - User input sanitized (`..` and `/` removed)
   - Verified with `realpath()` comparison
   - Files confined to bucket directory

5. **Admin Panel Protection**
   - HTTP Basic Authentication
   - Credentials stored in `.env` (not in code)
   - Password in plaintext (should be hashed in v2.0)

6. **PHP Execution Disabled**
   - `.htaccess` in `storage/` prevents PHP execution
   - Prevents arbitrary code execution via upload

7. **HTTP Headers**
   - `Cache-Control` optimized for immutable content
   - `Content-Disposition: inline` for safe display
   - `Content-Type` set explicitly (prevents MIME sniffing)

### ⚠️ Known Risks

#### 1. CRITICAL: Exposed Credentials in .env

**Risk Level**: CRITICAL

**Issue**: `.env` file is committed to git with credentials

**Impact**:
- Anyone with git access has admin credentials
- CSRF secret exposed
- Credentials visible in repository history

**Mitigation**:
```bash
# Remove .env from git history
git rm --cached .env

# Add to .gitignore (should already be there)
echo ".env" >> .gitignore

# Create new .env locally (never commit)
cp .env.example .env

# Regenerate all credentials after exposure
```

#### 2. CRITICAL: Missing CSRF Protection

**Risk Level**: CRITICAL

**Issue**: Upload and Delete endpoints don't validate CSRF tokens

**Impact**:
- Attacker can craft URL to delete files
- Victim clicks malicious link → files deleted without consent
- Example attack:
  ```html
  <!-- Hidden in attacker's website -->
  <img src="https://storage.example.com/api/delete?bucket=X&f=Y&key=Z" />
  ```

**Mitigation**:
- Add CSRF token validation to POST endpoints
- Use SameSite cookies attribute
- Require Origin/Referer validation

**Temporary workaround**:
- Only use API from same-origin requests
- Require authentication beyond API key (e.g., session token)

#### 3. HIGH: Debug Error Reporting Enabled

**Risk Level**: HIGH

**Issue**: PHP errors displayed in browser (in development)

**Impact**:
- Exposes file paths and code structure
- Shows database queries (future)
- Leaks variable contents

**Mitigation**:
```php
// Set APP_ENV=production in .env
if (getenv('APP_ENV') === 'production') {
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}
```

#### 4. HIGH: Weak Admin Auth Logic

**Risk Level**: HIGH

**Issue**: Timing side-channel leaks if ADMIN_PASS is empty

**Impact**:
- If password not configured, different timing for auth failure
- Allows username enumeration
- Vulnerable to timing attacks

**Mitigation**:
```php
// Always use hash_equals() for comparison
// Check both username AND password
// Return same response time regardless
```

#### 5. MEDIUM: No Rate Limiting

**Risk Level**: MEDIUM

**Issue**: No protection against brute force or DoS

**Impact**:
- Attacker could attempt many API keys quickly
- Could upload thousands of files to fill disk
- API key brute force possible (2^128 but slow iteration)

**Mitigation**:
```php
// Implement per-IP rate limiting
// Example: 100 uploads per hour per IP
// Set headers: Retry-After, X-RateLimit-*

// Disk quota per bucket (future)
```

#### 6. MEDIUM: URL Encoding in Upload

**Risk Level**: MEDIUM

**Issue**: File path constructed without URL encoding

**Current code**:
```php
$url = BASE_URL . "?bucket=$bucket&f=$full_path";
```

**Better approach**:
```php
$url = BASE_URL . '?' . http_build_query([
    'bucket' => $bucket,
    'f' => $full_path
]);
```

**Impact**: Minor - bucket names are validated, paths should be safe

### ⚠️ Potential Risks

#### 1. MEDIUM: File Encryption

**Risk**: Files stored in plaintext on server

**Impact**:
- Server compromise exposes all files
- No data privacy from hosting provider

**Mitigation**:
- Files at-rest encryption (GPG, native FS encryption)
- TLS/HTTPS for transport (already enforced)

#### 2. MEDIUM: No File Versioning

**Risk**: Deleted files can't be recovered

**Impact**:
- User errors result in permanent loss
- No audit trail of changes

**Mitigation**:
- Implement soft delete (mark deleted, keep file)
- Add file version history
- Regular backups

#### 3. MEDIUM: Single Point of Failure

**Risk**: Single server deployment

**Impact**:
- Server crash = service down
- Data loss if no backup

**Mitigation**:
- Regular backups to separate storage
- Monitoring and alerts
- Load balancing (future)

#### 4. LOW: JSON File Corruption

**Risk**: Concurrent writes could corrupt files.json

**Impact**:
- Metadata loss (files become inaccessible)
- Data inconsistency

**Mitigation**:
- Use file locking when writing JSON
- Implement atomic writes
- Migrate to database

## Best Practices

### For Administrators

1. **Secure .env File**
   ```bash
   # Never commit .env
   echo ".env" >> .gitignore
   
   # Restrict file permissions
   chmod 600 .env
   
   # Review before deploying
   cat .env | grep -E "(PASS|SECRET|KEY)"
   ```

2. **Strong Admin Password**
   - Minimum 16 characters
   - Mix of uppercase, lowercase, numbers, special chars
   - Not in dictionary
   - Changed regularly (monthly)
   - Example: `Tr0pic@lThund3r$torm2024!`

3. **Secure API Keys**
   - Store in secure location (password manager, vault)
   - Rotate keys quarterly
   - One key per application/environment
   - Regenerate if exposed

4. **HTTPS Only**
   - Never use HTTP in production
   - Enable HSTS headers
   - Redirect HTTP → HTTPS
   - Valid SSL certificate

5. **Access Control**
   - IP whitelist admin panel (optional)
   - SSH key authentication only (no passwords)
   - Minimal admin account privileges
   - Separate admin and user accounts

6. **Monitoring**
   - Monitor disk usage
   - Alert on failed auth attempts
   - Monitor upload patterns
   - Check file integrity

7. **Backups**
   ```bash
   # Daily encrypted backup
   tar -czf backup-$(date +%Y%m%d).tar.gz storage/
   gpg -c backup-20240115.tar.gz
   # Upload to secure storage (S3, Backblaze, etc.)
   ```

### For Developers

1. **Validate Input**
   ```php
   // Always validate user input
   if (!valid_bucket($bucket)) {
       http_response_code(403);
       exit(json_encode(['error' => 'Invalid bucket']));
   }
   ```

2. **Use HTTPS URLs**
   ```js
   // ❌ DON'T use HTTP
   const url = `http://storage.example.com/...`;
   
   // ✅ DO use HTTPS
   const url = `https://storage.example.com/...`;
   
   // ✅ BETTER - use relative URLs
   const url = `/api/file?bucket=...`;
   ```

3. **Secure API Keys**
   ```js
   // ❌ DON'T hardcode in frontend
   const API_KEY = "abc123xyz";
   
   // ✅ DO use environment variables
   const API_KEY = process.env.REACT_APP_BUCKET_KEY;
   
   // ✅ BETTER - request from secure backend
   ```

4. **Validate Files**
   ```js
   // Validate on frontend before upload
   const ALLOWED_TYPES = ["image/jpeg", "image/png", "image/webp", "application/pdf"];
   const MAX_SIZE = 10 * 1024 * 1024; // 10MB
   
   if (!ALLOWED_TYPES.includes(file.type)) {
       throw new Error("File type not allowed");
   }
   ```

5. **Handle Errors Safely**
   ```js
   // Don't expose error details to users
   try {
       await uploadFile(file);
   } catch (error) {
       // Log detailed error
       console.error("Upload error:", error);
       // Show generic message to user
       alert("Upload failed. Please try again.");
   }
   ```

6. **Use CORS Carefully**
   ```php
   // Only allow trusted origins
   $allowed_origins = [
       'https://myapp.com',
       'https://admin.myapp.com'
   ];
   
   $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
   if (in_array($origin, $allowed_origins)) {
       header("Access-Control-Allow-Origin: $origin");
   }
   ```

### For Hosting Providers

1. **System Security**
   - Keep OS and packages updated
   - Use firewall to restrict access
   - Monitor for intrusions
   - Regular security patches

2. **File System**
   - Regular backups (3-2-1 strategy: 3 copies, 2 media, 1 offsite)
   - Monitor disk usage and alerts
   - Implement quotas per user
   - Audit file access

3. **Network**
   - DDoS protection
   - WAF (Web Application Firewall)
   - Rate limiting at proxy level
   - TLS/SSL termination

4. **Access**
   - SSH key only (no password login)
   - Disable root login
   - Use sudo for admin tasks
   - Log all access

## Compliance & Regulations

### GDPR (General Data Protection Regulation)

If storing EU user data:

- ✅ Data minimization (only store necessary files)
- ⚠️ Data retention (should implement auto-delete policies)
- ⚠️ Right to deletion (implement proper delete operations)
- ⚠️ Data export (provide user data export feature)
- ⚠️ Breach notification (implement incident response)

### CCPA (California Consumer Privacy Act)

If storing CA user data:

- ✅ Privacy policy
- ⚠️ User data access rights
- ⚠️ Deletion rights
- ⚠️ Opt-out of sale

### PCI-DSS (Payment Card Industry)

If storing payment card data:

- ❌ **DON'T** store credit card data
- ✅ Use payment processor (Stripe, PayPal)
- ✅ No cardholder data in logs

## Incident Response

### If Credentials Compromised

1. **Immediate**
   ```bash
   # 1. Regenerate admin password
   # 2. Regenerate CSRF secret
   # 3. Regenerate all API keys
   
   # 4. Check git history
   git log --oneline -- .env
   
   # 5. Remove from history
   git filter-branch --tree-filter 'rm -f .env' HEAD
   ```

2. **Short Term**
   - Monitor for unauthorized access
   - Check logs for suspicious activity
   - Notify users if data exposed

3. **Long Term**
   - Review security controls
   - Implement rate limiting
   - Enable audit logging
   - Consider incident post-mortem

### If Files Compromised

1. **Immediate**
   - Identify affected files
   - Consider removing them
   - Notify affected users

2. **Investigation**
   - Check access logs
   - Review admin actions
   - Identify vulnerability
   - Monitor for further access

3. **Prevention**
   - Patch vulnerability
   - Strengthen access controls
   - Add monitoring

## Security Checklist

### Before Production

- [ ] SSL/HTTPS enabled
- [ ] .env file created and .gitignored
- [ ] Admin password strong (16+ chars)
- [ ] File permissions set (storage/ = 750)
- [ ] Apache mod_rewrite enabled
- [ ] Debug error reporting disabled
- [ ] Backups configured
- [ ] Monitoring alerts set up
- [ ] Incident response plan ready
- [ ] Security review completed

### Regular Maintenance

- [ ] Weekly: Review access logs
- [ ] Monthly: Rotate API keys
- [ ] Monthly: Test backups
- [ ] Quarterly: Security audit
- [ ] Quarterly: Update dependencies
- [ ] Annually: Review compliance

## Resources

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PHP Security Guide](https://www.php.net/manual/en/security.php)
- [Let's Encrypt](https://letsencrypt.org/) - Free SSL/TLS
- [Have I Been Pwned](https://haveibeenpwned.com/) - Check for exposed credentials

## Reporting Security Issues

If you discover a security vulnerability:

1. **Do NOT** open a public GitHub issue
2. Email: security@example.com with details
3. Include: Description, impact, reproduction steps
4. We'll acknowledge within 48 hours
5. Coordinate disclosure timeline

## See Also

- [INSTALLATION.md](INSTALLATION.md) - Secure deployment
- [ADMIN.md](ADMIN.md) - Admin security
- [API.md](API.md) - API security
- [ARCHITECTURE.md](ARCHITECTURE.md) - Technical design
