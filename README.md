# Bulk URL Media Importer for WordPress

A powerful WordPress plugin that allows administrators to import images from direct URLs in bulk using either manual input or CSV/TXT files. The plugin automatically downloads media into the WordPress Media Library, tracks every successful import, generates thumbnails, and maintains a detailed import history with ratings.

---

# Features

* Import images from direct URLs
* Manual URL entry
* CSV and TXT file upload support
* Batch import (up to 100 URLs per import)
* Automatic Media Library upload
* Import progress bar
* Live import status updates
* Thumbnail preview
* File size detection
* MIME type detection
* Source URL storage
* Import timestamp
* Star rating system
* Delete individual logs
* Delete all logs
* AJAX-powered importing
* Responsive admin interface
* Modern WordPress admin UI
* Duplicate URL filtering
* Invalid URL validation
* Professional logging system

---

# Requirements

* WordPress 6.0+
* PHP 7.4+
* Administrator privileges

---

# Installation

## Method 1 (ZIP)

1. Download the plugin.
2. Go to **WordPress Admin → Plugins → Add New**.
3. Click **Upload Plugin**.
4. Select the ZIP file.
5. Install and activate.

---

## Method 2 (Git)

```bash
cd wp-content/plugins

git clone https://github.com/yourusername/bulk-url-media-importer.git
```

Activate the plugin from the WordPress Plugins page.

---

# Usage

## Manual Import

1. Go to

Media → Bulk URL Importer

2. Open the **Manual URLs** tab.

3. Paste one direct image URL per line.

Example:

```
https://example.com/image1.jpg
https://example.com/image2.png
https://example.com/image3.webp
```

4. Click

**Start Import**

---

## Import from CSV/TXT

Upload a CSV or TXT file.

Example:

```
https://example.com/image1.jpg
https://example.com/image2.jpg
https://example.com/image3.jpg
```

CSV example:

```
https://example.com/image1.jpg
https://example.com/image2.jpg
https://example.com/image3.jpg
```

The plugin automatically reads the first column.

---

# Import Process

The plugin performs the following steps:

1. Validates URLs.
2. Removes duplicate URLs.
3. Limits imports to 100 URLs.
4. Downloads media.
5. Adds media to WordPress.
6. Creates an import log.
7. Generates a thumbnail.
8. Saves metadata.
9. Updates progress bar.

---

# Import Log

Each successful import stores:

* Thumbnail
* File name
* File size
* File type
* Source URL
* Import date
* Star rating

---

# Supported Formats

Images

* JPG
* JPEG
* PNG
* GIF
* WEBP
* BMP

Depending on your WordPress configuration.

---

# What is NOT Supported

This plugin imports **direct media files only**.

Supported:

```
https://example.com/image.jpg
https://example.com/photo.png
https://example.com/picture.webp
```

Not Supported:

```
https://google.com
https://facebook.com
https://chat.deepseek.com/...
https://example.com/blog-post
```

These are HTML pages rather than direct media files.

---

# Error Handling

The plugin detects:

* Invalid URLs
* Duplicate URLs
* Unsupported content
* Download failures
* HTTP errors
* Missing files

---

# Admin Features

* Modern dashboard UI
* AJAX import
* Live progress updates
* Rating system
* Import history
* Thumbnail preview
* Delete logs
* Responsive design

---

# Plugin Structure

```
bulk-url-media-importer/

│
├── assets/
│   ├── css/
│   │   └── admin.css
│   └── js/
│       └── admin.js
│
├── bulk-url-media-importer.php
│
└── README.md

---

# Changelog

## Version 1.0

* Added manual URL importing
* Added CSV/TXT importing
* Added thumbnail support
* Added star ratings
* Added AJAX progress
* Improved validation
* Improved UI
* Improved logging
* Improved error handling

---

# License

GPL v2 or later

---

# Contributing

Contributions, issues, and feature requests are welcome.

Feel free to fork the repository and submit a Pull Request.

---

# Author

Muhammad Fawad Ali

---

If you like this plugin, please consider giving it a ⭐ on GitHub.
