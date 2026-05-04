# API Reference

Complete API documentation with examples for all Poppy Storage endpoints.

## Overview

Poppy Storage provides three main API endpoints:
- **Upload** - Add files to buckets
- **File** - Retrieve/display files
- **Delete** - Remove files

All endpoints use query parameters for authentication and configuration.

## Authentication

All API endpoints (except the admin panel) require an **API key** that is unique per bucket.

- API keys are generated when creating a bucket
- Keys are shown **only once** after creation
- Keys are hashed with BCRYPT before storage
- Keys are passed via the `key` query parameter

## Upload Endpoint

Upload files to a bucket.

### Request

```
POST /api/upload?bucket=BUCKET_NAME&key=API_KEY
```

### Query Parameters

| Parameter | Required | Description |
|-----------|----------|-------------|
| `bucket` | Yes | Bucket name where file will be stored |
| `key` | Yes | API key for the bucket |

### Request Body

```
Content-Type: multipart/form-data

file: <binary file data>
```

### Allowed File Types

- **JPEG** (`image/jpeg`) - `.jpg`, `.jpeg`
- **PNG** (`image/png`) - `.png`
- **WebP** (`image/webp`) - `.webp`
- **PDF** (`application/pdf`) - `.pdf`

### Size Limit

- Maximum 10MB per file (configured in `.env` as `MAX_SIZE`)

### Response - Success (200)

```json
{
  "success": true,
  "url": "https://storage.example.com/api/file?bucket=my-bucket&f=ab/abc123def456.jpg",
  "path": "ab/abc123def456.jpg",
  "size": 234567,
  "mime": "image/jpeg"
}
```

### Response - Error (400, 403, 413)

```json
{
  "error": "File exceeds size limit",
  "code": "SIZE_LIMIT_EXCEEDED"
}
```

### Common Error Codes

| Code | HTTP | Meaning |
|------|------|---------|
| `INVALID_BUCKET` | 403 | Bucket doesn't exist or is invalid |
| `UNAUTHORIZED` | 403 | API key is incorrect |
| `NO_FILE` | 400 | No file provided in upload |
| `INVALID_TYPE` | 400 | File type not allowed |
| `SIZE_LIMIT_EXCEEDED` | 413 | File exceeds MAX_SIZE |
| `UPLOAD_FAILED` | 500 | Server error during upload |

### JavaScript Example

```js
async function uploadFile(file, bucket, apiKey) {
  const formData = new FormData();
  formData.append("file", file);

  try {
    const res = await fetch(
      `/api/upload?bucket=${bucket}&key=${apiKey}`,
      {
        method: "POST",
        body: formData,
      }
    );

    const data = await res.json();

    if (data.success) {
      console.log("File uploaded:", data.url);
      return data.url;
    } else {
      console.error("Upload failed:", data.error);
    }
  } catch (error) {
    console.error("Upload error:", error);
  }
}

// Usage
const fileInput = document.querySelector("input[type=file]");
fileInput.addEventListener("change", (e) => {
  uploadFile(e.target.files[0], "my-bucket", "your-api-key");
});
```

### React Example

```jsx
import { useState } from "react";

export function FileUploader() {
  const [uploading, setUploading] = useState(false);
  const [fileUrl, setFileUrl] = useState(null);

  const handleUpload = async (e) => {
    const file = e.target.files?.[0];
    if (!file) return;

    setUploading(true);

    try {
      const formData = new FormData();
      formData.append("file", file);

      const res = await fetch(
        `/api/upload?bucket=my-bucket&key=YOUR_API_KEY`,
        {
          method: "POST",
          body: formData,
        }
      );

      const data = await res.json();

      if (data.success) {
        setFileUrl(data.url);
      } else {
        alert("Upload failed: " + data.error);
      }
    } finally {
      setUploading(false);
    }
  };

  return (
    <div>
      <input
        type="file"
        onChange={handleUpload}
        accept="image/*,.pdf"
        disabled={uploading}
      />
      {uploading && <p>Uploading...</p>}
      {fileUrl && <img src={fileUrl} alt="Uploaded" />}
    </div>
  );
}
```

### cURL Example

```bash
# Basic upload
curl -X POST \
  -F "file=@/path/to/image.jpg" \
  "https://storage.example.com/api/upload?bucket=my-bucket&key=YOUR_API_KEY"

# With verbose output
curl -v -X POST \
  -F "file=@photo.png" \
  "https://storage.example.com/api/upload?bucket=photos&key=abc123xyz"
```

## File Endpoint

Retrieve and display uploaded files.

### Request

```
GET /api/file?bucket=BUCKET_NAME&f=FILE_PATH
```

### Query Parameters

| Parameter | Required | Description |
|-----------|----------|-------------|
| `bucket` | Yes | Bucket name where file is stored |
| `f` | Yes | File path (e.g., `ab/abc123def456.jpg`) |

### Response - Success (200)

Returns the file with appropriate headers:

```
HTTP/1.1 200 OK
Content-Type: image/jpeg
Content-Length: 234567
Cache-Control: public, max-age=31536000
Content-Disposition: inline
```

### Response - Error (404, 403)

```
HTTP/1.1 404 Not Found
```

Or for authorization issues:

```
HTTP/1.1 403 Forbidden
```

### HTML Example

```html
<!-- Display image -->
<img
  src="/api/file?bucket=my-bucket&f=ab/abc123def456.jpg"
  alt="Uploaded image"
/>

<!-- Embed PDF -->
<iframe
  src="/api/file?bucket=docs&f=cd/doc789def012.pdf"
  width="100%"
  height="600px"
></iframe>

<!-- Download link -->
<a href="/api/file?bucket=downloads&f=ef/file123.pdf" download>
  Download PDF
</a>
```

### Caching

Files are served with aggressive caching headers:

```
Cache-Control: public, max-age=31536000
```

This means:
- Files are cached for 1 year
- Safe for CDNs and browsers to cache
- Good for immutable file paths (based on content hash)

### JavaScript Example

```js
// Display image
const img = document.createElement("img");
img.src = `/api/file?bucket=my-bucket&f=${filePath}`;
document.body.appendChild(img);

// Load image for processing
const image = new Image();
image.src = `/api/file?bucket=images&f=${filePath}`;
image.onload = () => {
  console.log("Image dimensions:", image.width, image.height);
};
```

## Delete Endpoint

Remove files from a bucket.

### Request

```
POST /api/delete?bucket=BUCKET_NAME&f=FILE_PATH&key=API_KEY
```

### Query Parameters

| Parameter | Required | Description |
|-----------|----------|-------------|
| `bucket` | Yes | Bucket name |
| `f` | Yes | File path to delete |
| `key` | Yes | API key for authentication |

### Response - Success (200)

```json
{
  "success": true,
  "message": "File deleted successfully"
}
```

### Response - Error (403, 404, 500)

```json
{
  "error": "File not found"
}
```

### Common Error Codes

| Code | HTTP | Meaning |
|------|------|---------|
| `INVALID_BUCKET` | 403 | Bucket doesn't exist |
| `UNAUTHORIZED` | 403 | API key is incorrect |
| `FILE_NOT_FOUND` | 404 | File doesn't exist |
| `DELETE_FAILED` | 500 | Server error during deletion |

### JavaScript Example

```js
async function deleteFile(bucket, filePath, apiKey) {
  try {
    const res = await fetch(
      `/api/delete?bucket=${bucket}&f=${filePath}&key=${apiKey}`,
      {
        method: "POST",
      }
    );

    const data = await res.json();

    if (data.success) {
      console.log("File deleted");
    } else {
      console.error("Delete failed:", data.error);
    }
  } catch (error) {
    console.error("Delete error:", error);
  }
}

// Usage
await deleteFile("my-bucket", "ab/abc123def456.jpg", "your-api-key");
```

### React Example

```jsx
function FileManager({ fileUrl }) {
  const handleDelete = async () => {
    if (!confirm("Delete this file?")) return;

    const filePath = new URL(fileUrl).searchParams.get("f");

    const res = await fetch(
      `/api/delete?bucket=my-bucket&f=${filePath}&key=YOUR_API_KEY`,
      { method: "POST" }
    );

    if ((await res.json()).success) {
      alert("File deleted");
      // Update UI
    }
  };

  return <button onClick={handleDelete}>Delete File</button>;
}
```

## Best Practices

### 1. Store API Keys Securely

```js
// ❌ DON'T - Hardcoded in frontend
const API_KEY = "abc123xyz";

// ✅ DO - From environment variables
const API_KEY = process.env.REACT_APP_BUCKET_KEY;

// ✅ BETTER - Request from secure backend
const { apiKey } = await fetch("/api/get-bucket-key").then((r) => r.json());
```

### 2. Validate Files on Frontend

```js
const ALLOWED_TYPES = ["image/jpeg", "image/png", "image/webp", "application/pdf"];
const MAX_SIZE = 10 * 1024 * 1024; // 10MB

function validateFile(file) {
  if (!ALLOWED_TYPES.includes(file.type)) {
    throw new Error(`File type not allowed: ${file.type}`);
  }
  if (file.size > MAX_SIZE) {
    throw new Error(`File too large: ${file.size} bytes`);
  }
  return true;
}
```

### 3. Handle Upload Progress

```js
function uploadWithProgress(file, bucket, apiKey) {
  const xhr = new XMLHttpRequest();
  const formData = new FormData();
  formData.append("file", file);

  xhr.upload.addEventListener("progress", (e) => {
    if (e.lengthComputable) {
      const percentComplete = (e.loaded / e.total) * 100;
      console.log(`Upload progress: ${percentComplete}%`);
    }
  });

  xhr.addEventListener("load", () => {
    const data = JSON.parse(xhr.responseText);
    console.log("Upload complete:", data.url);
  });

  xhr.open(
    "POST",
    `/api/upload?bucket=${bucket}&key=${apiKey}`
  );
  xhr.send(formData);
}
```

### 4. Implement Retry Logic

```js
async function uploadWithRetry(file, bucket, apiKey, maxRetries = 3) {
  for (let i = 0; i < maxRetries; i++) {
    try {
      const formData = new FormData();
      formData.append("file", file);

      const res = await fetch(
        `/api/upload?bucket=${bucket}&key=${apiKey}`,
        { method: "POST", body: formData }
      );

      if (res.ok) {
        return await res.json();
      }

      if (res.status >= 500) {
        throw new Error("Server error, retrying...");
      }
    } catch (error) {
      if (i === maxRetries - 1) throw error;
      await new Promise((r) => setTimeout(r, 1000 * (i + 1))); // Exponential backoff
    }
  }
}
```

## Rate Limiting

Future versions may implement rate limiting. Current limits:

- No hard limit on API endpoints
- Server-side file size limit: 10MB (configurable)
- Bucket name: 50 characters max
- File count per bucket: Unlimited

## CORS Support

File serving endpoint supports CORS requests from any origin:

```
Access-Control-Allow-Origin: *
```

API endpoints (upload, delete) may have CORS restrictions. Configure in admin settings.

## Changelog

### v1.0.0
- Initial release
- Upload, retrieve, delete endpoints
- Multi-bucket support
- Admin panel

See main [README.md](../README.md) for more info.
