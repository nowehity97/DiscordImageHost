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
