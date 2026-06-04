<?php

$publicPath = __DIR__ . DIRECTORY_SEPARATOR . 'public';
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');

// Build file path
$filePath = $publicPath . str_replace('/', DIRECTORY_SEPARATOR, $uri);

// Check if it's a static file
if ($uri !== '/' && file_exists($filePath) && is_file($filePath)) {
    // Determine MIME type
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mimeTypes = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
    ];
    
    $mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';
    header("Content-Type: {$mimeType}");
    header('Cache-Control: public, max-age=3600');
    readfile($filePath);
    exit;
}

// Route to Laravel
require $publicPath . DIRECTORY_SEPARATOR . 'index.php';