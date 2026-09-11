<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_login_api();
no_cache_headers();

header('Content-Type: application/json');

const RESERVED = ['admin', 'login', 'redirects-api', 'index', 'data', 'includes'];

function slugify($s) {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9_-]+/', '-', $s);
    return trim($s, '-');
}

/** Adds https:// if the user pasted a bare domain like "rushpermit.com". */
function normalize_dest($url) {
    $url = trim($url);
    if ($url !== '' && !preg_match('~^https?://~i', $url)) {
        $url = 'https://' . $url;
    }
    return $url;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $map = db_read('redirects.json', []);
    $meta = db_read('redirects-meta.json', []);
    $out = [];
    foreach ($map as $slug => $dest) {
        $out[] = [
            'slug' => $slug,
            'destination' => $dest,
            'createdAt' => $meta[$slug]['createdAt'] ?? null,
            'updatedAt' => $meta[$slug]['updatedAt'] ?? null,
        ];
    }
    usort($out, fn($a, $b) => strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? ''));
    echo json_encode($out);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $body['_action'] ?? 'create';
$map = db_read('redirects.json', []);
$meta = db_read('redirects-meta.json', []);

if ($action === 'create') {
    $rawSlug = $body['slug'] ?? '';
    $destination = normalize_dest($body['destination'] ?? '');
    if (empty($rawSlug)) { http_response_code(400); echo json_encode(['error' => 'slug required']); exit; }
    if (empty($destination)) { http_response_code(400); echo json_encode(['error' => 'destination required']); exit; }
    $slug = slugify($rawSlug);
    if (in_array($slug, RESERVED, true)) { http_response_code(400); echo json_encode(['error' => 'that slug is reserved']); exit; }
    if (isset($map[$slug])) { http_response_code(409); echo json_encode(['error' => 'slug already exists']); exit; }
    $map[$slug] = $destination;
    $meta[$slug] = ['createdAt' => gmdate('c'), 'updatedAt' => gmdate('c')];
    db_write('redirects.json', $map);
    db_write('redirects-meta.json', $meta);
    echo json_encode(['slug' => $slug, 'destination' => $destination]);
    exit;
}

if ($action === 'update') {
    $slug = $body['slug'] ?? '';
    if (!isset($map[$slug])) { http_response_code(404); echo json_encode(['error' => 'not found']); exit; }
    $destination = normalize_dest($body['destination'] ?? '');
    if (empty($destination)) { http_response_code(400); echo json_encode(['error' => 'destination required']); exit; }
    $map[$slug] = $destination;
    $meta[$slug] = ['createdAt' => $meta[$slug]['createdAt'] ?? gmdate('c'), 'updatedAt' => gmdate('c')];
    db_write('redirects.json', $map);
    db_write('redirects-meta.json', $meta);
    echo json_encode(['slug' => $slug, 'destination' => $destination]);
    exit;
}

if ($action === 'delete') {
    $slug = $body['slug'] ?? '';
    unset($map[$slug]);
    unset($meta[$slug]);
    db_write('redirects.json', $map);
    db_write('redirects-meta.json', $meta);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'unknown action']);
