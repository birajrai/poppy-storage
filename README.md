# Poppy Storage

A simple, secure PHP-based file storage API with bucket support. No S3 compatibility needed - just plain PHP, cPanel ready, and perfect for Next.js apps and other web applications.

## Features

- Multi-bucket support with unique API keys per bucket
- File upload (JPG, PNG, WebP images + PDFs only)
- File retrieval with Cloudflare-optimized caching headers
- File deletion with automatic metadata cleanup
- Folder-based storage with file sharding for performance
- MIME validation using finfo for security
- 10MB file size limit (configurable)
- Admin dashboard to create/delete buckets and monitor sizes
- Basic auth protected admin panel
- Per-bucket size tracking via JSON files
- Laravel-style security with storage outside web root
- API keys hashed with BCRYPT (plaintext shown once on creation)

## Directory Structure

```
poppy-storage/
├── app/                    # Application code (outside web root)
│   ├── config.php          # Configuration, helpers, bucket/file management
│   ├── Api/                # API endpoints (upload, download, delete)
│   │   ├── Upload.php
│   │   ├── File.php
│   │   └── Delete.php
│   ├── Admin/              # Admin panel handlers
│   │   ├── Dashboard.php
│   │   ├── CreateBucket.php
│   │   ├── DeleteBucket.php
│   │   └── Auth.php
│   └── Helpers/            # Security utilities
│       └── Security.php
├── docs/                   # Comprehensive documentation
│   ├── INSTALLATION.md     # Setup and deployment guide
│   ├── API.md              # API reference and examples
│   ├── ADMIN.md            # Admin panel guide
│   ├── ARCHITECTURE.md     # Technical architecture
│   ├── SECURITY.md         # Security considerations
│   └── TROUBLESHOOTING.md  # Common issues and solutions
├── public/                 # Web root (point domain here)
│   ├── index.php           # Front controller and router
│   └── .htaccess           # URL rewriting rules
├── storage/                # Runtime data (outside web root)
│   ├── buckets.json        # Global bucket metadata
│   ├── buckets/            # Per-bucket file storage
│   │   └── bucket-name/
│   │       ├── files.json  # Per-bucket file metadata
│   │       └── ab/         # Sharded storage (2-char prefix)
│   └── .htaccess           # Prevent PHP execution
├── .env.example            # Configuration template
└── README.md               # This file
```

## Quick Start

### Installation

1. Upload to your server (cPanel, VPS, or local machine)
2. Point your domain to `poppy-storage/public/`
3. Copy `.env.example` to `.env` and configure:
   ```env
   URL=http://your-domain.com
   ADMIN_USER=admin
   ADMIN_PASS=strong_password_here
   MAX_SIZE=10485760
   CSRF_SECRET=random_hex_string
   ```
4. Set file permissions: `storage/` to 0750

### First Steps

1. Access admin panel: `http://your-domain.com/admin` (use `.env` credentials)
2. Create your first bucket (API key will be shown once - save it!)
3. Start uploading files using the API

## Documentation

For detailed information, see the `/docs` folder:

- [INSTALLATION.md](docs/INSTALLATION.md) - Setup, deployment, and configuration
- [API.md](docs/API.md) - Complete API reference with code examples
- [ADMIN.md](docs/ADMIN.md) - Admin panel usage guide
- [ARCHITECTURE.md](docs/ARCHITECTURE.md) - Technical design and data structures
- [SECURITY.md](docs/SECURITY.md) - Security features and best practices
- [TROUBLESHOOTING.md](docs/TROUBLESHOOTING.md) - Common issues and solutions

## Basic Usage

### Upload a file (Next.js example)
```js
const formData = new FormData();
formData.append("file", file);

const res = await fetch(
  `http://your-domain.com/api/upload?bucket=my-bucket&key=YOUR_API_KEY`,
  { method: "POST", body: formData }
);
const data = await res.json();
console.log(data.url); // File URL
```

### Display a file
```jsx
<img src="http://your-domain.com/api/file?bucket=my-bucket&f=ab/abc123def456.jpg" />
```

### Delete a file
```js
await fetch(
  `http://your-domain.com/api/delete?bucket=my-bucket&f=ab/abc123def456.jpg&key=YOUR_API_KEY`,
  { method: "POST" }
);
```

## Security Features

- Sensitive files outside web root
- API keys hashed with BCRYPT
- Path traversal prevention
- PHP execution disabled in storage folders
- MIME type validation
- Basic auth for admin panel
- CSRF token validation
See [SECURITY.md](docs/SECURITY.md) for important notes

## Requirements

- PHP 7.4+ (for `random_bytes`, `finfo`, `password_hash`)
- Apache with `.htaccess` support (or nginx equivalent)
- No external dependencies (Composer-free)

## Configuration

Edit `.env` file to customize:

| Setting | Purpose | Default |
|---------|---------|---------|
| `URL` | Base URL for file serving | Required |
| `ADMIN_USER` | Admin panel username | Required |
| `ADMIN_PASS` | Admin panel password (plaintext, will be hashed) | Required |
| `MAX_SIZE` | Maximum file size in bytes | 10485760 (10MB) |
| `CSRF_SECRET` | CSRF token secret | Generated |

## File Structure Examples

### buckets.json
```json
[
  {
    "name": "my-bucket",
    "key": "$2y$10$..."  // BCRYPT hash of API key
  }
]
```

### files.json (per bucket)
```json
[
  {
    "path": "ab/abc123def456.jpg",
    "size": 234567,
    "mime": "image/jpeg",
    "uploaded_at": "2024-01-15 14:30:45"
  }
]
```

## Support & Contributions

Found a bug? Check [TROUBLESHOOTING.md](docs/TROUBLESHOOTING.md) first.

## License

MIT License - see LICENSE file for details.

---

Ready to get started? Check out [INSTALLATION.md](docs/INSTALLATION.md) for detailed setup instructions!
