<?php
define('DATA_DIR', __DIR__ . '/../data');

/** Tells browsers AND any server/CDN-level cache (e.g. Hostinger LiteSpeed) to
 * never cache this response — these pages show live, frequently-changing data. */
function no_cache_headers() {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
    header('Pragma: no-cache');
    header('Expires: 0');
}

function db_read($file, $default = []) {
    $path = DATA_DIR . '/' . $file;
    if (!file_exists($path)) return $default;
    $json = file_get_contents($path);
    $data = json_decode($json, true);
    return $data === null ? $default : $data;
}

function db_write($file, $data) {
    $path = DATA_DIR . '/' . $file;
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}
