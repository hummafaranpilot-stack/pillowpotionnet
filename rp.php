<?php
// Tracking redirect: logs the click (best-effort) then 302s the visitor onward to
// whichever offer's ?offer= key was requested (see offers.php for the registry).
// No HTML output — only header() redirects, so this stays fast even under load.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

$offers = require __DIR__ . '/offers.php';

// Sticky-session proxies often reuse the same exit IP across many clicks in a
// short window — cache geo results per IP for 5 min so those hits skip the
// slow external ip-api.com round-trip entirely (same pattern as index.php).
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

/** Best-effort country lookup for the click record — never blocks the redirect on failure. */
function geo_country($ip) {
    $cached = geo_cache_get($ip);
    if ($cached !== null) return $cached['country'] ?? null;

    $url = 'http://ip-api.com/json/' . urlencode($ip) . '?fields=status,country';
    $ctx = stream_context_create(['http' => ['timeout' => 3]]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) return null;
    $j = json_decode($body, true);
    if (!$j || ($j['status'] ?? '') !== 'success') return null;
    geo_cache_set($ip, $j);
    return $j['country'] ?? null;
}

// --- Which offer to redirect to (defaults to rushpermit for old links with no ?offer=) ---
$offer_key = trim($_GET['offer'] ?? 'rushpermit');
if (!isset($offers[$offer_key])) {
    log_error("Unknown offer requested: $offer_key");
    http_response_code(404);
    exit('Unknown offer');
}
$offer = $offers[$offer_key];

// --- Read + sanitize query params (all treated as plain strings, never used in raw SQL) ---
$fbclid = trim($_GET['fbclid'] ?? '');
$campaign_id = trim($_GET['sub1'] ?? '');
$adset_id = trim($_GET['sub2'] ?? '');
$ad_id = trim($_GET['sub3'] ?? '');
$referrer = trim($_GET['referrer'] ?? '');

// --- Click ID: reuse fbclid when present, otherwise generate our own fallback ID ---
if ($fbclid !== '') {
    $click_id = $fbclid;
} else {
    $click_id = 'pp_trkr_' . bin2hex(random_bytes(16));
}

// --- Visitor metadata ---
// Cloudflare's connecting-IP header is more accurate than REMOTE_ADDR when the site is behind Cloudflare.
$ip_address = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$country = $ip_address !== '' ? geo_country($ip_address) : null;

// --- Best-effort insert: a DB hiccup must never block the redirect ---
$db = get_db_connection();
if ($db !== null) {
    try {
        // ON DUPLICATE KEY UPDATE so a repeat click with the same fbclid doesn't
        // throw a duplicate-key error — it just no-ops (touches created_at) instead.
        $stmt = $db->prepare(
            'INSERT INTO clicks (click_id, offer, fbclid, campaign_id, adset_id, ad_id, ip_address, country, user_agent, referrer)
             VALUES (:click_id, :offer, :fbclid, :campaign_id, :adset_id, :ad_id, :ip_address, :country, :user_agent, :referrer)
             ON DUPLICATE KEY UPDATE created_at = created_at'
        );
        $stmt->execute([
            ':click_id' => $click_id,
            ':offer' => $offer_key,
            ':fbclid' => $fbclid !== '' ? $fbclid : null,
            ':campaign_id' => $campaign_id !== '' ? $campaign_id : null,
            ':adset_id' => $adset_id !== '' ? $adset_id : null,
            ':ad_id' => $ad_id !== '' ? $ad_id : null,
            ':ip_address' => $ip_address,
            ':country' => $country,
            ':user_agent' => $user_agent,
            ':referrer' => $referrer !== '' ? $referrer : null,
        ]);
    } catch (PDOException $e) {
        log_error('Click insert failed: ' . $e->getMessage());
        // Fall through — the visitor still gets redirected below.
    }
}

// --- Build the destination URL from the offer's param template and redirect ---
$replacements = [
    '{click_id}' => $click_id,
    '{fbclid}' => $fbclid,
    '{sub1}' => $campaign_id,
    '{sub2}' => $adset_id,
    '{sub3}' => $ad_id,
];
$params = [];
foreach ($offer['params'] as $key => $value) {
    $params[$key] = strtr($value, $replacements);
}

$destination = $offer['base_url'] . '?' . http_build_query($params);

header('Location: ' . $destination, true, 302);
exit;
