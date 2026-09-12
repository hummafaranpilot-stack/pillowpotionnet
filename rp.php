<?php
// Tracking redirect: logs the click (best-effort) then 302s the visitor onward.
// No HTML output — only header() redirects, so this stays fast even under load.

require_once __DIR__ . '/config.php';

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

// --- Best-effort insert: a DB hiccup must never block the redirect ---
$db = get_db_connection();
if ($db !== null) {
    try {
        // ON DUPLICATE KEY UPDATE so a repeat click with the same fbclid doesn't
        // throw a duplicate-key error — it just no-ops (touches created_at) instead.
        $stmt = $db->prepare(
            'INSERT INTO clicks (click_id, fbclid, campaign_id, adset_id, ad_id, ip_address, user_agent, referrer)
             VALUES (:click_id, :fbclid, :campaign_id, :adset_id, :ad_id, :ip_address, :user_agent, :referrer)
             ON DUPLICATE KEY UPDATE created_at = created_at'
        );
        $stmt->execute([
            ':click_id' => $click_id,
            ':fbclid' => $fbclid !== '' ? $fbclid : null,
            ':campaign_id' => $campaign_id !== '' ? $campaign_id : null,
            ':adset_id' => $adset_id !== '' ? $adset_id : null,
            ':ad_id' => $ad_id !== '' ? $ad_id : null,
            ':ip_address' => $ip_address,
            ':user_agent' => $user_agent,
            ':referrer' => $referrer !== '' ? $referrer : null,
        ]);
    } catch (PDOException $e) {
        log_error('Click insert failed: ' . $e->getMessage());
        // Fall through — the visitor still gets redirected below.
    }
}

// --- Build the destination URL and redirect ---
$params = [
    'affid' => '275',
    'oid' => '185',
    'fn' => '',
    'ln' => '',
    'em' => '',
    'ph' => '',
    'creative_id' => '17',
    'click_id' => $click_id,
    'fbclid' => $fbclid,
    'sub1' => $campaign_id,
    'sub2' => $adset_id,
    'sub3' => $ad_id,
    'sub4' => 'utm_source_pillowpotion',
];

$destination = 'https://rushpermit.com/secure/app-carry10/?' . http_build_query($params);

header('Location: ' . $destination, true, 302);
exit;
