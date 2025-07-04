<?php
session_start();

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

// Error reporting (disable in production)
error_reporting(0);
ini_set('display_errors', 0);

/**
 * Exchange authorization code for Discord access token
 * @param string $code OAuth authorization code
 * @return array|null Token data or null on failure
 */
function get_discord_token($code) {
    $data = [
        'client_id' => DISCORD_CLIENT_ID,
        'client_secret' => DISCORD_CLIENT_SECRET,
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => DISCORD_REDIRECT_URI,
        'scope' => 'identify'
    ];
    
    $options = [
        'http' => [
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data)
        ]
    ];
    
    $context = stream_context_create($options);
    $result = @file_get_contents('https://discord.com/api/oauth2/token', false, $context);
    
    return $result ? json_decode($result, true) : null;
}

/**
 * Get Discord user profile using access token
 * @param string $access_token OAuth access token
 * @return array|null User data or null on failure
 */
function get_discord_user($access_token) {
    $options = [
        'http' => [
            'header' => "Authorization: Bearer $access_token\r\n",
            'method' => 'GET'
        ]
    ];
    
    $context = stream_context_create($options);
    $result = @file_get_contents('https://discord.com/api/users/@me', false, $context);
    
    return $result ? json_decode($result, true) : null;
}

/**
 * Send standardized JSON error response
 * @param string $message Error message
 * @param int $statusCode HTTP status code
 */
function sendJsonError($message, $statusCode = 400) {
    header("HTTP/1.1 $statusCode");
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit();
}

// Handle file requests
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != "off" ? "https://" : "http://";
$domain = $_SERVER['HTTP_HOST'];
$fileurl = $protocol . $domain . '/' . UPLOAD_DIR;

if (isset($_GET['f'])) {
    $filename = basename($_GET['f']);
    $filepath = UPLOAD_DIR . $filename;

    if (file_exists($filepath)) {
        // Serve raw file without HTML preview
        if (isset($_GET['raw']) && $_GET['raw'] == '1') {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $filepath);
            finfo_close($finfo);
            header('Content-Type: '.$mime);
            header('Content-Length: ' . filesize($filepath));
            readfile($filepath);
            exit;
        }

        // Detect Discord bot
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $is_discord_bot = stripos($user_agent, 'Discordbot') !== false;

        if ($is_discord_bot) {
            // Return Open Graph metadata for Discord
            header('Content-Type: text/html');
            ?>
            <!DOCTYPE html>
            <html prefix="og: https://ogp.me/ns#">
            <head>
                <title><?= SITE_TITLE ?></title>
                <meta property="og:title" content="<?= SITE_TITLE ?>">
                <meta property="og:image" content="<?= htmlspecialchars($fileurl . $filename) ?>">
                <meta property="og:url" content="<?= htmlspecialchars($fileurl . $filename) ?>">
                <meta property="og:type" content="website">
                <meta name="twitter:card" content="summary_large_image">
                <meta name="theme-color" content="<?= THEME_COLOR ?>">
            </head>
            <body>
                <img src="<?= htmlspecialchars($fileurl . $filename) ?>" alt="<?= SITE_TITLE ?>">
            </body>
            </html>
            <?php
            exit;
        } else {
            // Beautiful preview for browsers
            header('Content-Type: text/html');
            ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Image Preview - <?= htmlspecialchars($filename) ?> | <?= SITE_TITLE ?></title>
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
                <style>
                    /* CSS styles for beautiful preview */
                    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
                    body {
                        background: #0e0e0e url('<?= BG_IMAGE ?>') no-repeat center center fixed;
                        background-size: cover;
                        color: #f0f0f0;
                        min-height: 100vh;
                        display: flex;
                        flex-direction: column;
                        padding: 20px;
                        position: relative;
                    }
                    body::before {
                        content: '';
                        position: absolute;
                        top: 0; left: 0; right: 0; bottom: 0;
                        background: rgba(10, 10, 10, 0.85);
                        z-index: -1;
                    }
                    .container {
                        max-width: 1200px;
                        margin: 0 auto;
                        flex: 1;
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        justify-content: center;
                        width: 100%;
                        z-index: 1;
                    }
                    header {
                        text-align: center;
                        padding: 20px 0;
                        margin-bottom: 20px;
                        width: 100%;
                    }
                    h1 {
                        font-size: 2.5rem;
                        margin-bottom: 10px;
                        color: #f0f0f0;
                        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
                    }
                    .logo {
                        font-size: 2.8rem;
                        margin-bottom: 15px;
                        color: #7289DA;
                    }
                    .image-container {
                        max-width: 90%;
                        margin: 20px 0;
                        text-align: center;
                        background: rgba(30, 30, 40, 0.7);
                        border-radius: 15px;
                        padding: 25px;
                        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
                        backdrop-filter: blur(10px);
                        border: 1px solid rgba(114, 137, 218, 0.3);
                    }
                    .image-preview {
                        max-width: 100%;
                        max-height: 70vh;
                        border-radius: 10px;
                        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
                        cursor: pointer;
                        transition: transform 0.3s;
                    }
                    .image-preview:hover { transform: scale(1.02); }
                    footer {
                        text-align: center;
                        padding: 25px;
                        margin-top: auto;
                        color: #aaa;
                        width: 100%;
                        font-size: 0.9rem;
                    }
                    .lightbox {
                        display: none;
                        position: fixed;
                        top: 0; left: 0;
                        width: 100%; height: 100%;
                        background: rgba(0, 0, 0, 0.95);
                        z-index: 1000;
                        text-align: center;
                    }
                    .lightbox img {
                        max-width: 90%; max-height: 90%;
                        margin: auto;
                        position: absolute;
                        top: 0; left: 0; bottom: 0; right: 0;
                        border-radius: 10px;
                        box-shadow: 0 0 40px rgba(114, 137, 218, 0.3);
                    }
                    .close-lightbox {
                        position: absolute;
                        top: 25px; right: 35px;
                        color: white; font-size: 50px;
                        cursor: pointer; z-index: 1001;
                        transition: color 0.3s;
                    }
                    .close-lightbox:hover { color: #7289DA; }
                    .watermark {
                        position: absolute;
                        bottom: 15px; right: 15px;
                        color: rgba(255, 255, 255, 0.3);
                        font-size: 0.8rem;
                    }
                    @media (max-width: 768px) {
                        .buttons { flex-direction: column; width: 100%; }
                        .btn { width: 100%; justify-content: center; }
                        .image-container { padding: 15px; }
                        h1 { font-size: 2rem; }
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <header>
                        <div class="logo">
                            <i class="fab fa-wolf-pack-battalion"></i>
                        </div>
                        <h1>Image Preview</h1>
                        <p>Image Hosting Service</p>
                    </header>

                    <div class="image-container">
                        <img src="<?= htmlspecialchars($fileurl . $filename) ?>" 
                             alt="Image preview" 
                             class="image-preview" 
                             id="previewImage">
                    </div>

                <footer>
                    <p>&copy; <?= date('Y') ?> Image Hosting Service. All rights reserved.</p>
                </footer>

                <div class="lightbox" id="lightbox">
                    <span class="close-lightbox" onclick="closeLightbox()">&times;</span>
                    <img src="" id="lightboxImage">
                </div>

                <div class="watermark">yourdomain.com</div>

                <script>
                    // Lightbox functionality
                    const previewImage = document.getElementById('previewImage');
                    const lightbox = document.getElementById('lightbox');
                    const lightboxImage = document.getElementById('lightboxImage');

                    previewImage.addEventListener('click', () => {
                        lightboxImage.src = previewImage.src;
                        lightbox.style.display = 'block';
                        document.body.style.overflow = 'hidden';
                    });

                    function closeLightbox() {
                        lightbox.style.display = 'none';
                        document.body.style.overflow = 'auto';
                    }

                    // Close lightbox when clicking outside image
                    lightbox.addEventListener('click', (e) => {
                        if (e.target === lightbox) closeLightbox();
                    });

                    // Close with ESC key
                    document.addEventListener('keydown', (e) => {
                        if (e.key === 'Escape' && lightbox.style.display === 'block') {
                            closeLightbox();
                        }
                    });
                </script>
            </body>
            </html>
            <?php
            exit;
        }
    } else {
        header("HTTP/1.0 404 Not Found");
        exit;
    }
}

// Handle actions: login, logout, delete
$action = $_GET['action'] ?? '';

// Login redirect
if ($action === 'login') {
    $auth_url = 'https://discord.com/api/oauth2/authorize?' . http_build_query([
        'client_id' => DISCORD_CLIENT_ID,
        'redirect_uri' => DISCORD_REDIRECT_URI,
        'response_type' => 'code',
        'scope' => 'identify'
    ]);
    header("Location: $auth_url");
    exit();
}

// Logout
if ($action === 'logout') {
    session_destroy();
    header('Location: index.php');
    exit();
}

// Delete image
if ($action === 'delete' && isset($_GET['file'])) {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] !== ALLOWED_USER_ID) {
        die("Unauthorized!");
    }
    
    $filename = basename($_GET['file']);
    $filepath = UPLOAD_DIR . $filename;
    
    if (file_exists($filepath) && unlink($filepath)) {
        $_SESSION['delete_success'] = "Image deleted successfully!";
    } else {
        $_SESSION['delete_error'] = "Error deleting image!";
    }
    
    header('Location: index.php');
    exit();
}

// Discord OAuth callback
if (isset($_GET['code'])) {
    $token = get_discord_token($_GET['code']);
    
    if (!$token || !isset($token['access_token'])) {
        die("Discord authorization error (missing token).");
    }
    
    $user = get_discord_user($token['access_token']);
    
    if (!$user || !isset($user['id'])) {
        die("Discord authorization error (missing user data).");
    }

    // Validate authorized user
    if ($user['id'] === ALLOWED_USER_ID) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['discriminator'] = $user['discriminator'];
        $_SESSION['avatar'] = $user['avatar'];
        header('Location: index.php');
        exit();
    } else {
        die("Access denied. Only owner can login.");
    }
}

// Handle file uploads
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_FILES['image']) || isset($_POST['paste_image']))) {
    // Authorization check
    if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] !== ALLOWED_USER_ID) {
        sendJsonError("Unauthorized!", 401);
    }

    // Handle paste operation
    if (isset($_POST['paste_image'])) {
        $base64 = $_POST['paste_image'];
        
        // Validate base64 image
        if (!preg_match('/^data:image\/(\w+);base64,/', $base64, $matches)) {
             sendJsonError("Invalid image format", 400);
        }
        
        $file_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64));
        
        if (!$file_data) {
            sendJsonError("Base64 processing error", 400);
        }
        
        // Check size
        $file_size = strlen($file_data);
        if ($file_size > MAX_FILE_SIZE) {
            sendJsonError("File too large (max " . (MAX_FILE_SIZE / 1024 / 1024) . "MB)", 413);
        }
        
        // Get MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_buffer($finfo, $file_data);
        finfo_close($finfo);

        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp'
        ];
        
        if (!isset($extensions[$mime_type])) {
            sendJsonError("Unsupported format: $mime_type", 415);
        }
        
        // Save file
        $ext = $extensions[$mime_type];
        $filename = uniqid() . '.' . $ext;
        $target = UPLOAD_DIR . $filename;

        if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true)) {
            sendJsonError("Upload directory error", 500);
        }

        if (file_put_contents($target, $file_data)) {
            $image_url = $protocol . $domain . '/?f=' . $filename;
            
            // AJAX response
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'success', 'url' => $image_url]);
                exit();
            } 
            
            // Browser response
            $_SESSION['last_upload'] = $image_url;
            header('Location: ?upload=success');
            exit();
        } else {
            sendJsonError("File save error", 500);
        }
    }
    
    // Handle file upload
    if (isset($_FILES['image'])) {
        $file = $_FILES['image'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            sendJsonError("Upload error: " . $file['error'], 400);
        }
        
        if ($file['size'] > MAX_FILE_SIZE) {
            sendJsonError("File too large (max " . (MAX_FILE_SIZE / 1024 / 1024) . "MB)", 413);
        }

        // Validate MIME type
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $file_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($file_type, $allowed_types)) {
            sendJsonError("Unsupported format: $file_type", 415);
        }

        // Save file
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $ext;
        $target = UPLOAD_DIR . $filename;

        if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true)) {
            sendJsonError("Upload directory error", 500);
        }

        if (move_uploaded_file($file['tmp_name'], $target)) {
            $image_url = $protocol . $domain . '/?f=' . $filename;
            $_SESSION['last_upload'] = $image_url;
            header('Location: ?upload=success');
            exit();
        } else {
            sendJsonError("File save error", 500);
        }
    }
}

// Get uploaded files
$uploaded_files = [];
if (is_dir(UPLOAD_DIR)) {
    $files = scandir(UPLOAD_DIR);
    foreach ($files as $file) {
        $filepath = UPLOAD_DIR . $file;
        if ($file !== '.' && $file !== '..' && !is_dir($filepath)) {
            $file_url = $protocol . $domain . '?f=' . $file;
            $raw_url = $file_url . '&raw=1';
            $uploaded_files[] = [
                'name' => $file,
                'url' => $file_url,
                'raw_url' => $raw_url,
                'size' => filesize($filepath),
                'date' => filemtime($filepath)
            ];
        }
    }
    
    // Sort by newest first
    usort($uploaded_files, function($a, $b) {
        return $b['date'] - $a['date'];
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_TITLE ?> - Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Modern dashboard CSS */
        :root {
            --discord-blurple: #5865F2;
            --discord-green: #57F287;
            --discord-red: #ED4245;
            --discord-dark: #2C2F33;
            --discord-darker: #23272A;
            --bg-color: #0e0e0e;
            --text-color: #f0f0f0;
            --border-color: #333;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background: var(--bg-color); color: var(--text-color); min-height: 100vh; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        header { text-align: center; padding: 20px 0; margin-bottom: 30px; border-bottom: 2px solid var(--discord-blurple); }
        h1 { font-size: 2.5rem; margin-bottom: 10px; color: var(--text-color); }
        .user-info { display: flex; align-items: center; justify-content: center; gap: 15px; margin-top: 15px; }
        .user-avatar { width: 50px; height: 50px; border-radius: 50%; border: 3px solid var(--discord-blurple); }
        .panel { display: grid; grid-template-columns: 1fr; gap: 30px; margin-bottom: 30px; }
        @media (min-width: 768px) { .panel { grid-template-columns: 1fr 1fr; } }
        .card { background: var(--discord-dark); border-radius: 10px; padding: 25px; box-shadow: 0 8px 16px rgba(0,0,0,0.3); border: 1px solid var(--border-color); }
        .card h2 { color: var(--discord-blurple); margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid var(--border-color); }
        .upload-form { display: flex; flex-direction: column; gap: 20px; }
        .file-input { position: relative; padding: 20px; border: 2px dashed var(--discord-blurple); border-radius: 8px; text-align: center; transition: all 0.3s; cursor: pointer; background: rgba(0,0,0,0.2); }
        .file-input:hover { background: rgba(88,101,242,0.1); }
        .file-input input { position: absolute; left: 0; top: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
        .file-input i { font-size: 2rem; margin-bottom: 10px; color: var(--discord-blurple); }
        .btn { padding: 12px 20px; background: var(--discord-blurple); color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 1rem; font-weight: 600; transition: all 0.2s; text-align: center; text-decoration: none; display: inline-block; }
        .btn:hover { background: #4752c4; transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.3); }
        .btn-logout { background: var(--discord-red); }
        .btn-logout:hover { background: #c0392b; }
        .btn-delete { background: var(--discord-red); padding: 6px 12px; font-size: 0.9rem; }
        .btn-delete:hover { background: #c0392b; }
        .btn-copy { background: #57F287; color: #000; }
        .btn-copy:hover { background: #46d177; }
        .result-box { background: rgba(0,0,0,0.3); padding: 15px; border-radius: 8px; margin-top: 20px; }
        .result-box h3 { margin-bottom: 10px; color: var(--discord-green); }
        .image-url { background: rgba(0,0,0,0.5); padding: 10px; border-radius: 5px; word-break: break-all; margin-bottom: 15px; }
        .url-input { width: 100%; padding: 10px; background: rgba(0,0,0,0.5); border: 1px solid var(--discord-blurple); border-radius: 5px; color: white; margin-bottom: 10px; }
        .file-list { max-height: 500px; overflow-y: auto; }
        .file-item { display: flex; align-items: center; padding: 12px; background: rgba(0,0,0,0.3); border-radius: 8px; margin-bottom: 10px; transition: all 0.2s; }
        .file-item:hover { background: rgba(0,0,0,0.5); }
        .file-thumb { width: 60px; height: 60px; border-radius: 5px; object-fit: cover; margin-right: 15px; border: 1px solid var(--border-color); }
        .file-info { flex: 1; }
        .file-name { font-weight: 600; margin-bottom: 5px; word-break: break-all; }
        .file-size { font-size: 0.85rem; color: #aaa; }
        .file-actions { display: flex; gap: 10px; }
        .alert { padding: 15px; border-radius: 8px; margin: 20px 0; text-align: center; }
        .alert-success { background: rgba(87,242,135,0.2); border: 1px solid var(--discord-green); }
        .alert-error { background: rgba(237,66,69,0.2); border: 1px solid var(--discord-red); }
        .login-container { max-width: 500px; margin: 100px auto; padding: 40px; background: var(--discord-dark); border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); text-align: center; border: 1px solid var(--discord-blurple); }
        .discord-logo { font-size: 4rem; margin-bottom: 20px; color: var(--discord-blurple); }
        .info-text { color: #aaa; font-size: 0.9rem; margin-top: 20px; }
        .copy-container { display: flex; gap: 10px; }
        .copy-container input { flex: 1; }
        .paste-info { padding: 12px; background: rgba(88,101,242,0.2); border-radius: 8px; text-align: center; margin-bottom: 15px; border: 1px dashed var(--discord-blurple); }
        .paste-info i { margin-right: 8px; }
        kbd { background: rgba(0,0,0,0.5); padding: 3px 6px; border-radius: 4px; border: 1px solid #555; font-family: monospace; }
        #pasteLoader { display: none; text-align: center; padding: 15px; background: rgba(0,0,0,0.3); border-radius: 8px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] === ALLOWED_USER_ID): ?>
            <header>
                <h1><?= SITE_TITLE ?> - Dashboard</h1>
                <div class="user-info">
                    <?php if (isset($_SESSION['avatar'])): ?>
                        <img src="https://cdn.discordapp.com/avatars/<?= $_SESSION['user_id'] ?>/<?= $_SESSION['avatar'] ?>.png" alt="Avatar" class="user-avatar">
                    <?php endif; ?>
                    <div>
                        <h2><?= htmlspecialchars($_SESSION['username']) ?>#<?= htmlspecialchars($_SESSION['discriminator']) ?></h2>
                        <a href="index.php?action=logout" class="btn btn-logout">Logout</a>
                    </div>
                </div>
            </header>

            <?php if (isset($_SESSION['delete_success'])): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($_SESSION['delete_success']) ?>
                </div>
                <?php unset($_SESSION['delete_success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['delete_error'])): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($_SESSION['delete_error']) ?>
                </div>
                <?php unset($_SESSION['delete_error']); ?>
            <?php endif; ?>

            <div class="panel">
                <div class="card">
                    <h2><i class="fas fa-cloud-upload-alt"></i> Upload New Image</h2>
                    <div class="paste-info">
                        <i class="fas fa-paste"></i> Use <kbd>Ctrl+V</kbd> to paste from clipboard
                    </div>
                    <form class="upload-form" method="POST" enctype="multipart/form-data" id="uploadForm">
                        <div class="file-input">
                            <i class="fas fa-file-image"></i>
                            <p>Drag & drop or click to select file</p>
                            <input type="file" name="image" accept="image/*" required id="fileInput">
                        </div>
                        <button type="submit" class="btn"><i class="fas fa-upload"></i> Upload Image</button>
                    </form>

                    <div id="pasteLoader">
                        <i class="fas fa-spinner fa-spin"></i> Uploading pasted image...
                    </div>

                    <?php if (isset($_GET['upload']) && $_GET['upload'] === 'success' && isset($_SESSION['last_upload'])): ?>
                        <div class="result-box">
                            <h3><i class="fas fa-check-circle"></i> Image uploaded successfully!</h3>
                            <div class="image-url">
                                <strong>Image URL:</strong><br>
                                <a href="<?= htmlspecialchars($_SESSION['last_upload']) ?>" target="_blank"><?= htmlspecialchars($_SESSION['last_upload']) ?></a>
                            </div>
                            <div>
                                <strong>Copy URL:</strong>
                                <div class="copy-container">
                                    <input type="text" class="url-input" value="<?= htmlspecialchars($_SESSION['last_upload']) ?>" readonly onclick="this.select()">
                                    <button class="btn btn-copy" onclick="copyToClipboard('<?= htmlspecialchars(addslashes($_SESSION['last_upload'])) ?>')"><i class="fas fa-copy"></i></button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <h2><i class="fas fa-images"></i> Your Images (<?= count($uploaded_files) ?>)</h2>
                    <div class="file-list">
                        <?php if (!empty($uploaded_files)): ?>
                            <?php foreach ($uploaded_files as $file): ?>
                                <div class="file-item">
                                    <img src="<?= htmlspecialchars($file['raw_url']) ?>" alt="<?= htmlspecialchars($file['name']) ?>" class="file-thumb">
                                    <div class="file-info">
                                        <div class="file-name"><?= htmlspecialchars($file['name']) ?></div>
                                        <div class="file-size">
                                            <?= round($file['size'] / 1024, 2) ?> KB | <?= date('Y-m-d H:i', $file['date']) ?>
                                        </div>
                                    </div>
                                    <div class="file-actions">
                                        <a href="<?= htmlspecialchars($file['url']) ?>" target="_blank" class="btn"><i class="fas fa-eye"></i></a>
                                        <a href="index.php?action=delete&file=<?= urlencode($file['name']) ?>" class="btn btn-delete"><i class="fas fa-trash"></i></a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No uploaded images.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <script>
                // Copy URL to clipboard
                function copyToClipboard(text) {
                    navigator.clipboard.writeText(text).then(() => {
                        alert('Link copied to clipboard!');
                    }).catch(err => {
                        console.error('Copy error: ', err);
                        alert('Failed to copy. Please copy manually.');
                    });
                }

                // Handle paste event
                document.addEventListener('paste', function(e) {
                    if (e.clipboardData && e.clipboardData.files.length > 0) {
                        const file = e.clipboardData.files[0];
                        
                        if (!file.type.match('image.*')) {
                            alert('Only images can be pasted!');
                            return;
                        }
                        
                        // Show loader
                        document.getElementById('pasteLoader').style.display = 'block';
                        
                        // Read file as DataURL
                        const reader = new FileReader();
                        reader.onload = function(event) {
                            // Prepare form data
                            const formData = new FormData();
                            formData.append('paste_image', event.target.result);
                            
                            // Send to server
                            fetch('', {
                                method: 'POST',
                                body: formData,
                                credentials: 'same-origin',
                                headers: { 'X-Requested-Width': 'XMLHttpRequest' }
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.status === 'success') {
                                    // Hide loader
                                    document.getElementById('pasteLoader').style.display = 'none';
                                    
                                    // Show result
                                    const resultHTML = `
                                        <div class="result-box">
                                            <h3><i class="fas fa-check-circle"></i> Image pasted successfully!</h3>
                                            <div class="image-url">
                                                <strong>Image URL:</strong><br>
                                                <a href="${data.url}" target="_blank">${data.url}</a>
                                            </div>
                                            <div>
                                                <strong>Copy URL:</strong>
                                                <div class="copy-container">
                                                    <input type="text" class="url-input" value="${data.url}" readonly>
                                                    <button class="btn btn-copy" onclick="copyToClipboard('${data.url}')"><i class="fas fa-copy"></i></button>
                                                </div>
                                            </div>
                                        </div>
                                    `;
                                    
                                    // Insert before loader
                                    document.getElementById('pasteLoader').insertAdjacentHTML('beforebegin', resultHTML);
                                } else {
                                    throw new Error(data.message || 'Unknown error');
                                }
                            })
                            .catch(error => {
                                document.getElementById('pasteLoader').style.display = 'none';
                                alert('Error: ' + error.message);
                            });
                        };
                        reader.readAsDataURL(file);
                    }
                });
            </script>
        <?php else: ?>
            <div class="login-container">
                <div class="discord-logo"><i class="fab fa-discord"></i></div>
                <h2>Image Hosting</h2>
                <p>Only owner can access the dashboard</p>
                <a href="index.php?action=login" class="btn"><i class="fab fa-discord"></i> Login with Discord</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
