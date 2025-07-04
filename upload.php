<?php
// upload.php

// Disable sessions and HTML output
define('DISABLE_SESSION', true);
define('DISABLE_HTML', true);

// Configuration - replace with your API token
define('API_TOKEN', 'YOUR_SECURE_API_TOKEN');
define('UPLOAD_DIR', 'images/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB

// Authorization check
$authorized = false;
if (isset($_SERVER['HTTP_KEY']) && $_SERVER['HTTP_KEY'] === API_TOKEN) {
    $authorized = true;
}

if (!$authorized) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// Validate file existence
if (!isset($_FILES['file'])) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'No file provided']);
    exit;
}

$file = $_FILES['file'];

// Validate upload errors
if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Upload error: ' . $file['error']]);
    exit;
}

// Validate file size
if ($file['size'] > MAX_FILE_SIZE) {
    http_response_code(413);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'File too large']);
    exit;
}

// Validate MIME type
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime_type, $allowed_types)) {
    http_response_code(415);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unsupported file type: ' . $mime_type]);
    exit;
}

// Prepare upload directory
if (!is_dir(UPLOAD_DIR)) {
    if (!mkdir(UPLOAD_DIR, 0755, true)) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Directory creation failed']);
        exit;
    }
}

// Generate unique filename
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid() . '.' . $ext;
$target = UPLOAD_DIR . $filename;

// Save file
if (move_uploaded_file($file['tmp_name'], $target)) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != "off" ? "https://" : "http://";
    $domain = $_SERVER['HTTP_HOST'];
    $image_url = $protocol . $domain . '/?f=' . $filename;
    
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'url' => $image_url]);
    exit;
} else {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'File save failed']);
    exit;
}
