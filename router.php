<?php
// Router for the PHP built-in server: serve real files, send the rest to the app.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$file = __DIR__ . '/htdocs' . $path;
if ($path !== '/' && is_file($file)) {
    return false;
}
require __DIR__ . '/htdocs/index.php';
