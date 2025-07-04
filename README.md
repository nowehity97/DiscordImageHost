
## Image Hosting Platform with Discord Authentication
Dashboard for managing uploaded images

Beautiful image preview with lightbox functionality

A secure image hosting platform with Discord OAuth authentication, beautiful previews, and Flameshot integration for easy screenshot sharing.

# Features ✨
🔒 Discord OAuth Authentication - Secure login using Discord accounts

📁 Multiple Upload Methods:

Drag & drop interface

Traditional file selection

Clipboard paste (Ctrl+V)

🌈 Beautiful Image Previews:

Responsive lightbox gallery

Discord-optimized metadata

Mobile-friendly design

🤖 Discord Bot Support - Automatic Open Graph metadata for rich embeds

⚡ API Access - Programmatic uploads with token authentication

🔗 One-Click Sharing - Copy direct links with a single click

🗑️ File Management - Delete images from dashboard

📸 Flameshot Integration - Direct screenshot uploads with notifications

Setup Instructions 🛠️
Server Requirements
PHP 7.4+

Web server (Apache/Nginx)

Discord Developer Application

Write permissions for images/ directory

## Configuration (index.php)

```php
// Configuration - replace placeholders with your actual values
define('DISCORD_CLIENT_ID', 'YOUR_DISCORD_CLIENT_ID');
define('DISCORD_CLIENT_SECRET', 'YOUR_DISCORD_CLIENT_SECRET');
define('DISCORD_REDIRECT_URI', 'YOUR_REDIRECT_URI');
define('ALLOWED_USER_ID', 'YOUR_DISCORD_USER_ID');
define('UPLOAD_DIR', 'images/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('SITE_TITLE', 'Image Hosting Service');
define('THEME_COLOR', '#7289DA'); // Discord blurple
define('BG_IMAGE', 'path/to/background.webp'); // Preview background
```

## Discord Developer Setup
1. Create application at Discord Developer Portal
2. Add redirect URI: https://yourdomain.com/index.php
3. Note Client ID and Client Secret
4. Add your Discord User ID to ALLOWED_USER_ID

## Flameshot Integration 📸

Linux Setup

upload.sh file

```bash
#!/bin/bash -e

# Configuration - replace with your API token and URL
auth="YOUR_API_TOKEN_HERE"
url="https://yourdomain.com/upload.php"

temp_file="/tmp/screenshot.png"
flameshot gui -r > "$temp_file"

# Check MIME type
mime_type=$(file --mime-type -b "$temp_file")
if [[ "$mime_type" != "image/png" ]]; then
    notify-send "Error" "Invalid file type: $mime_type" -a "Flameshot"
    rm "$temp_file"
    exit 1
fi

# Send request
response=$(curl -s -X POST \
  -H "key: $auth" \
  -F "file=@$temp_file" \
  "$url")

# Process response
if ! jq -e . >/dev/null 2>&1 <<< "$response"; then
    notify-send "Server Error" "Invalid response" -a "Flameshot"
    rm "$temp_file"
    exit 1
fi

status=$(jq -r '.status' <<< "$response")
if [[ "$status" != "success" ]]; then
    error=$(jq -r '.message // empty' <<< "$response")
    notify-send "Upload Error" "${error:-Unknown error}" -a "Flameshot"
    rm "$temp_file"
    exit 1
fi

image_url=$(jq -r '.url' <<< "$response")
echo -n "$image_url" | xclip -selection clipboard
notify-send "URL Copied" "$image_url" -a "Flameshot" -i "$temp_file"
rm "$temp_file"
```
##
to create a shortcut to the script we create shortcut.desktop
and paste the code below
and right click on the newly created shortcut and allow it to run

```bash
[Desktop Entry]
Name=Flameshot Upload
Comment=Upload screenshot to server
Exec=/home/user/Deskop/upload.sh
Icon=flameshot
Terminal=false
Type=Application
Categories=Utility;
Keywords=screenshot;upload;
StartupNotify=true
```
## Usage 🖥️

### Web Interface
1. Visit your domain
2. Click "Login with Discord"
3. Upload images via:
   - Drag & drop to the upload zone
   - Clicking and selecting files
   - Pasting with Ctrl+V
4. Manage files in the dashboard:
   - View all uploaded images
   - Copy direct links
   - Delete images

### Flameshot Integration
1. Press your shortcut key (Ctrl+Shift+P)
2. Select area with Flameshot
3. URL automatically copies to clipboard
4. Notification appears with preview

### API Upload
```bash
curl -X POST \
  -H "Key: YOUR_API_TOKEN" \
  -F "file=@image.jpg" \
  https://yourdomain.com/upload.php
```

**Example Response:**
```json
{
  "status": "success",
  "url": "https://yourdomain.com/?f=filename.jpg"
}
```

## Security 🔐

- Use HTTPS for all connections
- Keep API tokens confidential
- Regularly rotate API tokens
- Set proper permissions (755 for directories, 644 for files)
- Limit uploads to authenticated users
- Validate all file types on server side
- Consider adding rate limiting for API access

## Troubleshooting 🐞

**Flameshot not working:**
```bash
# Check dependencies
flameshot --version
jq --version
xclip -version
notify-send --version

# Test script manually
./upload.sh
```

**Upload errors:**
- Verify `images/` directory permissions (775)
- Check PHP settings:
  ```ini
  file_uploads = On
  upload_max_filesize = 10M
  post_max_size = 12M
  ```
- Ensure API token matches in config and script

**Discord login issues:**
- Verify redirect URI in Discord Developer Portal
- Ensure correct Discord user ID is whitelisted
- Check session cookies are enabled

## License 📄

MIT License - See [LICENSE](LICENSE) for full text

```
Copyright 2025 nowehity97

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

---

**Contribute:** Issues and pull requests are welcome!  
**Support:** For help, create an issue in GitHub repository.
