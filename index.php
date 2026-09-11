<?php
/**
 * PillowPotion redirect service.
 *
 * pillowpotion.net/{slug}?any=params  →  validates visitor IP is US, shows a
 * branded "Redirecting…" interstitial, then forwards to the mapped
 * destination with all incoming query params merged in.
 * Non-US (or unresolvable) IPs are rejected — never redirected.
 *
 * The interstitial is a real loaded document, so the destination's Referer
 * becomes pillowpotion.net (not whatever the visitor's original referrer
 * was) — same behavior as MoneyWise's redirect.
 */

require_once __DIR__ . '/includes/db.php';

function client_ip() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($parts[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

// Sticky-session proxies often reuse the same exit IP across many clicks in a
// short window — cache geo results per IP for 5 min so those hits skip the
// slow external ip-api.com round-trip entirely.
define('GEO_CACHE_TTL', 300); // seconds

function geo_cache_get($ip) {
    $all = db_read('geo-cache.json', []);
    $hit = $all[$ip] ?? null;
    if (!$hit || $hit['expiresAt'] < time()) return null;
    return $hit['geo'];
}

function geo_cache_set($ip, $geo) {
    $all = db_read('geo-cache.json', []);
    $now = time();
    foreach ($all as $k => $v) { if ($v['expiresAt'] < $now) unset($all[$k]); }
    $all[$ip] = ['geo' => $geo, 'expiresAt' => $now + GEO_CACHE_TTL];
    db_write('geo-cache.json', $all);
}

function geo_lookup($ip) {
    $cached = geo_cache_get($ip);
    if ($cached !== null) return $cached;

    $url = 'http://ip-api.com/json/' . urlencode($ip) . '?fields=status,message,country,countryCode,regionName,city,zip,query';
    $ctx = stream_context_create(['http' => ['timeout' => 3]]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) return null;
    $j = json_decode($body, true);
    if (!$j || ($j['status'] ?? '') !== 'success') return null;
    geo_cache_set($ip, $j);
    return $j;
}

function append_click($record) {
    $clicks = db_read('clicks-log.json', []);
    $clicks[] = $record;
    // Keep the log from growing forever on shared hosting.
    if (count($clicks) > 5000) $clicks = array_slice($clicks, -5000);
    db_write('clicks-log.json', $clicks);
}

function esc($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }

function render_redirecting($destUrl, $city, $region) {
    $locationParts = array_filter([$city, $region]);
    $location = $locationParts ? esc(implode(', ', $locationParts)) : 'United States';
    $dest = esc($destUrl);
    echo <<<HTML
<!doctype html>
<html><head><meta charset="utf-8">
<title>PillowPotion — Redirecting…</title>
<meta http-equiv="refresh" content="1.2;url={$dest}">
<style>
  *{box-sizing:border-box;} body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;
    background:#0b1220;color:#e6ebf5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;}
  .card{text-align:center;padding:48px 40px;background:#121a2b;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.4);max-width:420px;}
  .brand{font-size:22px;font-weight:800;letter-spacing:.5px;color:#a855f7;margin-bottom:28px;}
  .spinner{width:40px;height:40px;border:3px solid rgba(255,255,255,.15);border-top-color:#a855f7;border-radius:50%;
    margin:0 auto 22px;animation:spin .8s linear infinite;}
  @keyframes spin{to{transform:rotate(360deg);}}
  h1{font-size:17px;font-weight:600;margin:0 0 8px;}
  p.sub{color:#94a3b8;font-size:13px;margin:0 0 22px;}
  .badge{display:inline-flex;align-items:center;gap:8px;background:#2a1245;border:1px solid #4c1d95;color:#c084fc;
    padding:8px 16px;border-radius:999px;font-size:12.5px;font-weight:600;}
</style></head>
<body>
  <div class="card">
    <div class="brand">PillowPotion</div>
    <div class="spinner"></div>
    <h1>Redirecting you to your offer…</h1>
    <p class="sub">Please wait, this only takes a moment.</p>
    <div class="badge">✓ Verified Location — United States, {$location}</div>
  </div>
</body></html>
HTML;
}

function render_rejected($country) {
    $where = esc($country ?: 'an unrecognized location');
    echo <<<HTML
<!doctype html>
<html><head><meta charset="utf-8">
<title>PillowPotion — Unable to Continue</title>
<style>
  *{box-sizing:border-box;} body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;
    background:#0b1220;color:#e6ebf5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;}
  .card{text-align:center;padding:48px 40px;background:#121a2b;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.4);max-width:440px;}
  .brand{font-size:22px;font-weight:800;letter-spacing:.5px;color:#f87171;margin-bottom:28px;}
  h1{font-size:17px;font-weight:600;margin:0 0 10px;}
  p{color:#94a3b8;font-size:13.5px;line-height:1.6;margin:0;}
</style></head>
<body>
  <div class="card">
    <div class="brand">PillowPotion</div>
    <h1>We couldn't verify your location</h1>
    <p>This link is only available to visitors in the United States. Your connection appears to originate from {$where}, so we're not able to continue — this protects both you and the offer from a potential IP mismatch.</p>
  </div>
</body></html>
HTML;
}

$slug = $_GET['src'] ?? '';

if ($slug === '') {
    header('Content-Type: text/plain');
    echo 'PillowPotion redirect service is running.';
    exit;
}

$map = db_read('redirects.json', []);
$dest = $map[$slug] ?? null;
$ip = client_ip();
$referer = $_SERVER['HTTP_REFERER'] ?? null;

if (!$dest) {
    append_click(['ts' => gmdate('c'), 'slug' => $slug, 'ip' => $ip, 'referer' => $referer, 'country' => null, 'region' => null, 'city' => null, 'zip' => null, 'valid' => false, 'redirected' => false, 'reason' => 'not-found']);
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'Redirect not found: ' . $slug;
    exit;
}

$geo = geo_lookup($ip);
$isUs = $geo && ($geo['countryCode'] ?? '') === 'US';

if (!$isUs) {
    append_click([
        'ts' => gmdate('c'), 'slug' => $slug, 'ip' => $ip, 'referer' => $referer,
        'country' => $geo['country'] ?? null, 'region' => $geo['regionName'] ?? null, 'city' => $geo['city'] ?? null, 'zip' => $geo['zip'] ?? null,
        'valid' => false, 'redirected' => false, 'reason' => $geo ? 'non-us-ip' : 'geo-lookup-failed',
    ]);
    header('Content-Type: text/html');
    render_rejected($geo['country'] ?? null);
    exit;
}

// Merge incoming query params into the destination URL (incoming wins on key clash).
$parts = parse_url($dest);
parse_str($parts['query'] ?? '', $destParams);
$incoming = $_GET;
unset($incoming['src']);
$merged = array_merge($destParams, $incoming);
$query = http_build_query($merged);
$scheme = $parts['scheme'] ?? 'https';
$host = $parts['host'] ?? '';
$path = $parts['path'] ?? '';
$finalUrl = $scheme . '://' . $host . $path . ($query ? '?' . $query : '');

append_click(['ts' => gmdate('c'), 'slug' => $slug, 'ip' => $ip, 'referer' => $referer, 'country' => $geo['country'], 'region' => $geo['regionName'], 'city' => $geo['city'], 'zip' => $geo['zip'] ?? null, 'valid' => true, 'redirected' => true, 'reason' => null]);

header('Content-Type: text/html');
render_redirecting($finalUrl, $geo['city'], $geo['regionName']);
