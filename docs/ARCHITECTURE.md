# Architecture & Technical Design

Deep dive into the technical architecture, data structures, and design decisions of Poppy Storage.

## System Overview

```
┌─────────────────────────────────────────────────────────────┐
│                    Client Applications                      │
│               (Web, Mobile, API Consumers)                  │
└──────────────────────┬──────────────────────────────────────┘
                       │ HTTPS
┌──────────────────────▼──────────────────────────────────────┐
│                   Apache Web Server                         │
│                    (mod_rewrite)                            │
└──────────────────────┬──────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────┐
│                 public/index.php                            │
│              (Router / Dispatcher)                          │
└──────────┬──────────┬──────────┬──────────┬─────────────────┘
           │          │          │          │
    ┌──────▼──┐ ┌─────▼──┐ ┌────▼────┐ ┌──▼─────┐
    │Upload   │ │File    │ │Delete   │ │Admin   │
    │Endpoint │ │Endpoint│ │Endpoint │ │Panel   │
    └─────┬───┘ └────┬───┘ └────┬────┘ └───┬────┘
          │          │          │          │
    ┌─────▼──────────▼──────────▼──────────▼────┐
    │        app/config.php (Core Logic)        │
    │    - Bucket Management                    │
    │    - File Operations                      │
    │    - Authentication                       │
    └─────┬──────────────────────────────────────┘
          │
    ┌─────▼──────────────────────────────────────┐
    │   storage/ (File System Storage)           │
    │   - buckets.json (Global metadata)         │
    │   - buckets/*/files.json (Per-bucket)      │
    │   - buckets/*/ab/* (Sharded files)         │
    └────────────────────────────────────────────┘
```

## Directory Structure

### Application Layer (`app/`)

**Purpose**: Business logic, outside web root for security

```
app/
├── config.php
│   ├── Environment loading (.env)
│   ├── Path definitions (BASE_URL, etc.)
│   ├── Bucket functions (get_bucket, create_bucket, etc.)
│   ├── File functions (add_file_entry, get_file_etc.)
│   └── Utility functions (calculate_bucket_size, etc.)
│
├── Api/
│   ├── Upload.php    (POST /api/upload)
│   ├── File.php      (GET /api/file)
│   └── Delete.php    (POST /api/delete)
│
├── Admin/
│   ├── Dashboard.php     (GET /admin)
│   ├── CreateBucket.php  (POST /admin/create-bucket)
│   ├── DeleteBucket.php  (POST /admin/delete-bucket)
│   └── Auth.php          (Admin authentication wrapper)
│
└── Helpers/
    └── Security.php      (Path validation, sanitization)
```

### Public Layer (`public/`)

**Purpose**: Web root, contains only router and .htaccess

```
public/
├── index.php        (Single entry point, router)
└── .htaccess        (URL rewriting, security rules)
```

### Storage Layer (`storage/`)

**Purpose**: Runtime data, outside web root, not directly accessible

```
storage/
├── buckets.json         (Global bucket metadata)
├── buckets/
│   └── bucket-name/
│       ├── files.json   (Per-bucket file metadata)
│       └── ab/          (Sharded storage, 2-char prefix)
│           ├── abc123def456.jpg
│           ├── xyz789uvw012.pdf
│           └── ...
├── .htaccess            (Deny all access)
└── error.log            (Error logging)
```

## Data Structures

### buckets.json

Global registry of all buckets.

```json
[
  {
    "name": "my-bucket",
    "key": "$2y$10$3qAY.HzFup10wgnD5NT6xux1AkhRd0Oc2.X/FU4ylzC9QMRyQROmC"
  },
  {
    "name": "photos",
    "key": "$2y$10$0uHy1V7oUZw8E5LpXqL1YuQ2xKj9Mn7Pp6Rr8Ss9Tt0Uu1Vv2Ww3"
  }
]
```

**Fields**:
- `name`: Bucket identifier (alphanumeric, hyphens, underscores)
- `key`: BCRYPT hash of the API key (never store plaintext)

### files.json (per bucket)

Metadata for all files in a bucket.

```json
[
  {
    "path": "ab/abc123def456xyz789.jpg",
    "size": 234567,
    "mime": "image/jpeg",
    "uploaded_at": "2024-01-15 14:30:45"
  },
  {
    "path": "cd/cdef789ghi123jkl456.pdf",
    "size": 891234,
    "mime": "application/pdf",
    "uploaded_at": "2024-01-16 09:15:22"
  }
]
```

**Fields**:
- `path`: File path within bucket (shard prefix + hash)
- `size`: File size in bytes
- `mime`: MIME type (validated against whitelist)
- `uploaded_at`: ISO 8601 timestamp

## File Storage Strategy

### Sharding

Files are stored in sharded directories to avoid filesystem limitations.

**Example**:
```
Original filename: image.jpg (user-uploaded)
↓ (hash with SHA256)
File hash: abc123def456xyz789...
↓ (take first 2 chars as shard)
Stored as: storage/buckets/my-bucket/ab/abc123def456xyz789.jpg
```

**Benefits**:
- Prevents "too many files in directory" errors
- Distributes files evenly
- Enables future sharding expansion (currently 2 chars = 256 possible shards)

### File Naming

Files are stored by their **SHA256 hash** of content:

```php
$hash = hash_file('sha256', $_FILES['file']['tmp_name']);
$shard = substr($hash, 0, 2);  // First 2 chars for sharding
$filename = substr($hash, 2);  // Remaining chars
$full_path = "$shard/$filename";
```

**Advantages**:
- Prevents duplicate uploads (same content = same hash)
- Immutable file paths (content determines path)
- Safe for caching (same URL always = same content)
- No filename collisions

## Request Flow

### Upload Flow

```
1. Client → POST /api/upload?bucket=X&key=Y with file
   │
2. Router (index.php) dispatches to Api/Upload.php
   │
3. Upload.php validates:
   ├─ Bucket exists
   ├─ API key matches (BCRYPT compare)
   ├─ File provided
   ├─ File type in whitelist (MIME validation)
   └─ File size under limit
   │
4. If validation fails → Return 403 or 400 error
   │
5. If validation passes:
   ├─ Generate SHA256 hash of file content
   ├─ Extract shard prefix (first 2 chars)
   ├─ Create shard directory if needed
   ├─ Move file to: storage/buckets/BUCKET/SHARD/HASH
   ├─ Record metadata in files.json
   └─ Return success + file URL
   │
6. Client receives URL: /api/file?bucket=X&f=SHARD/HASH
```

### Download/Display Flow

```
1. Client → GET /api/file?bucket=X&f=SHARD/HASH
   │
2. Router (index.php) dispatches to Api/File.php
   │
3. File.php validates:
   ├─ Bucket exists
   └─ File path safe (no traversal)
   │
4. If validation fails → Return 404
   │
5. If validation passes:
   ├─ Detect MIME type using finfo
   ├─ Validate MIME against whitelist
   ├─ Set appropriate headers:
   │  ├─ Content-Type: image/jpeg (etc.)
   │  ├─ Cache-Control: public, max-age=31536000
   │  ├─ Content-Disposition: inline (for images/PDFs)
   │  └─ Content-Length: file size
   └─ Stream file to client
   │
6. Client receives file
```

### Delete Flow

```
1. Client → POST /api/delete?bucket=X&f=SHARD/HASH&key=Y
   │
2. Router (index.php) dispatches to Api/Delete.php
   │
3. Delete.php validates:
   ├─ Bucket exists
   ├─ API key matches
   └─ File path safe (no traversal)
   │
4. If validation fails → Return 403 or 404
   │
5. If validation passes:
   ├─ Delete physical file
   ├─ Remove entry from files.json
   ├─ If shard directory empty → Remove shard directory
   └─ Return success
   │
6. Client receives confirmation
```

## Authentication & Security

### API Key Storage

API keys are stored as BCRYPT hashes to prevent plaintext exposure:

```php
// When creating bucket:
$api_key = bin2hex(random_bytes(16));  // 32 hex chars
$hashed = password_hash($api_key, PASSWORD_BCRYPT);
// Store $hashed in buckets.json
// Return $api_key to user (shown once)

// When authenticating:
$user_key = $_GET['key'];  // From request
$stored_hash = $bucket['key'];  // From buckets.json
if (password_verify($user_key, $stored_hash)) {
    // Authenticated
}
```

### Admin Authentication

Admin panel uses HTTP Basic Auth:

```
GET /admin
Authorization: Basic base64(username:password)
↓
Server verifies against ADMIN_USER and ADMIN_PASS from .env
```

### Path Validation

Prevents directory traversal attacks:

```php
// User provides: f=../../etc/passwd
// Sanitize:
function sanitize_path($path) {
    $path = urldecode($path);
    $path = str_replace(['..', '\\', '//'], '', $path);
    return ltrim($path, '/');
}
// Result: etcpasswd (safe)

// Then verify with realpath:
function valid_file_path($bucket, $path) {
    $real = realpath($full_path);
    $expected = realpath(bucket_path($bucket));
    return strpos($real, $expected) === 0;  // Must be inside bucket
}
```

## Performance Considerations

### File Operations

**Time Complexity**:
- Upload: O(n) where n = file size (stream copy)
- Download: O(n) where n = file size (stream read)
- Delete: O(m) where m = file count in bucket (JSON parse/rewrite)

**Space Complexity**:
- Per file: O(size) physical + O(metadata) JSON
- Per bucket: O(files) JSON entries

### Caching Strategy

**HTTP Caching**:
```
Cache-Control: public, max-age=31536000
```
- Files cached for 1 year
- Safe because file paths are content-based (immutable)
- Can be served from CDN without invalidation

**Future Optimization**:
- Move metadata to database instead of JSON
- Implement Redis caching for file list queries
- Add async background jobs for cleanup

## MIME Type Handling

### Allowed Types

```php
$ALLOWED_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'application/pdf' => 'pdf'
];
```

### MIME Detection

Uses PHP's `finfo_file()` to detect MIME type:

```php
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $path);
finfo_close($finfo);

if (!isset(ALLOWED_TYPES[$mime])) {
    // Reject file
}
```

### Why Not Trust File Extension?

Attackers can rename files with fake extensions:

```
malicious.exe → rename to → malicious.jpg
```

Using MIME detection reads file headers (magic bytes) to determine true type.

## Error Handling

### Error Codes

| Code | HTTP | Meaning |
|------|------|---------|
| 200 | OK | Success |
| 400 | Bad Request | Invalid input (missing file, etc.) |
| 403 | Forbidden | Auth failed, invalid key, bucket invalid |
| 404 | Not Found | File/bucket doesn't exist |
| 413 | Payload Too Large | File exceeds size limit |
| 500 | Server Error | Unexpected error |

### Error Logging

Errors logged to `storage/error.log` with:
- Timestamp
- Error message
- Request context (bucket, IP, etc.)

## Scalability Limitations

### Current Limits

- **Bucket size**: Unlimited (but will slow down)
- **File count**: ~10,000 files per bucket before notable slowdown
- **File size**: 10MB (configurable, but affects memory/time)
- **Concurrent uploads**: Depends on server resources

### Scaling Bottlenecks

1. **JSON file operations**
   - `load_files()` reads entire files.json into memory
   - With 100k files, JSON becomes large (multi-MB)
   - Solution: Migrate to database

2. **Sharding depth**
   - Currently 2-char sharding (256 possible shards)
   - With millions of files, shards fill up
   - Solution: Expand to 3+ char sharding or use hash distribution

3. **Single server**
   - No load balancing
   - Single point of failure
   - Solution: Add clustering/replication

## Future Enhancements

### Short Term (v1.1)
- Database-backed metadata storage
- Rate limiting per API key
- File encryption at rest
- Audit logging

### Medium Term (v1.5)
- Multi-region replication
- CDN integration
- Batch upload/download API
- File versioning

### Long Term (v2.0)
- Object storage backend (S3 compatible)
- Kubernetes deployment
- ML-based malware detection
- Full-text search for PDFs

## References

- [SECURITY.md](SECURITY.md) - Security architecture
- [API.md](API.md) - API design
- [INSTALLATION.md](INSTALLATION.md) - Deployment architecture
