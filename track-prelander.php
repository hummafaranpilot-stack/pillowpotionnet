<?php
// Called via fetch() from the rushpermit prelander as soon as it loads, so a click
// row exists (and a real click_id is known) before the visitor hits the CTA link.
// Same clicks table and click_id logic as rp.php, just JSON in/out instead of a redirect.

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input'), true) ?? [];

// --- Read + sanitize body params (all treated as plain strings, never used in raw SQL) ---
$fbclid = trim($body['fbclid'] ?? '');
$campaign_id = trim($body['campaign_id'] ?? '');
$adset_id = trim($body['adset_id'] ?? '');
$ad_id = trim($body['ad_id'] ?? '');
$referrer = trim($body['referrer'] ?? '');

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

// --- Best-effort insert: a DB hiccup must never stop click_id from reaching the page ---
$db = get_db_connection();
if ($db !== null) {
    try {
        // ON DUPLICATE KEY UPDATE so a repeat call with the same fbclid doesn't
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
        log_error('track-prelander insert failed: ' . $e->getMessage());
        // Fall through — still return the click_id below so the page isn't blocked.
    }
}

echo json_encode(['click_id' => $click_id]);
