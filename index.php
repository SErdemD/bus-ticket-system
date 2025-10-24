<?php
/**
 * index.php — Main front controller
 */

// --- Secure session configuration ---
ini_set('session.use_only_cookies', 1);

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();

// --- Required files ---
require_once __DIR__ . '/src/db/db.php';
require_once __DIR__ . '/src/scripts/auth_helper.php';

// --- Resolve request path once ---
$request_uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

// --- Serve uploaded files securely ---
if (str_starts_with($request_uri, 'uploads/')) {
    $upload_file = __DIR__ . '/src/' . $request_uri;
    $base_upload_path = realpath(__DIR__ . '/src/uploads');
    $real_upload_path = realpath($upload_file);

    if (
        $real_upload_path &&
        str_starts_with($real_upload_path, $base_upload_path) &&
        file_exists($real_upload_path)
    ) {
        $mime = mime_content_type($real_upload_path);
        header("Content-Type: $mime");
        header('Content-Length: ' . filesize($real_upload_path));
        header('Cache-Control: public, max-age=86400'); // Cache 1 day
        readfile($real_upload_path);
        exit;
    }

    http_response_code(404);
    echo "File not found.";
    exit;
}

// --- Handle API requests ---
if (str_starts_with($request_uri, 'api/')) {
    $api_file = __DIR__ . '/src/' . $request_uri;
    $base_api_path = realpath(__DIR__ . '/src/api');
    $real_api_path = realpath($api_file);

    if (
        $real_api_path &&
        str_starts_with($real_api_path, $base_api_path) &&
        file_exists($real_api_path)
    ) {
        require_once $real_api_path;
        exit;
    }

    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'API endpoint not found.']);
    exit;
}

// --- Page routing ---
ob_start();

$page = $request_uri ?: 'home';
$page_file = __DIR__ . '/src/pages/' . $page . '.php';
$base_page_path = realpath(__DIR__ . '/src/pages');
$real_page_path = realpath($page_file);

// Templates
require_once __DIR__ . '/src/shared/header.php';

if ($real_page_path && str_starts_with($real_page_path, $base_page_path)) {
    require_once $real_page_path;
} else {
    http_response_code(404);
    require_once __DIR__ . '/src/pages/404.php';
}

require_once __DIR__ . '/src/shared/footer.php';
ob_end_flush();
