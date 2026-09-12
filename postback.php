<?php
// Server-to-server postback from Everflow: fired when a click converts (sale confirmed).
// Everflow calls this directly (no browser involved), so responses are plain text,
// never HTML/JSON — it only cares about the HTTP status + a short confirmation body.

require_once __DIR__ . '/config.php';

// IP allowlisting: once Everflow's fixed postback IP ranges are known, restrict this
// endpoint to them here (or in .htaccess) so third parties can't forge conversions.
// Example: if (!in_array($_SERVER['REMOTE_ADDR'], EVERFLOW_IPS, true)) { http_response_code(403); exit; }

define('POSTBACK_LOG_FILE', __DIR__ . '/logs/postback_received.log');
define('POSTBACK_UNMATCHED_LOG_FILE', __DIR__ . '/logs/postback_unmatched.log');

/** Appends a timestamped line to the given log file (mirrors log_error() in config.php). */
function log_line($file, $message) {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    error_log($line, 3, $file);
}

function respond($http_code, $body) {
    http_response_code($http_code);
    header('Content-Type: text/plain');
    echo $body;
    exit;
}

// --- Read + sanitize query params (all treated as plain strings, never used in raw SQL) ---
$click_id = trim($_GET['cid'] ?? '');
$payout = trim($_GET['payout'] ?? '');
$status = trim($_GET['status'] ?? '');
$ip_address = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';

// Every attempt gets logged, matched or not, so postback traffic is always traceable.
log_line(POSTBACK_LOG_FILE, "cid=$click_id payout=$payout status=$status ip=$ip_address");

if ($click_id === '') {
    log_error('Postback rejected: missing cid (ip=' . $ip_address . ')');
    respond(400, 'Missing cid');
}

$db = get_db_connection();
if ($db === null) {
    respond(500, 'Database unavailable');
}

try {
    $stmt = $db->prepare(
        'UPDATE clicks SET status = :status, payout = :payout, converted_at = NOW()
         WHERE click_id = :click_id'
    );
    $stmt->execute([
        ':status' => $status !== '' ? $status : 'approved',
        ':payout' => $payout !== '' ? $payout : 0,
        ':click_id' => $click_id,
    ]);

    if ($stmt->rowCount() === 0) {
        // cid didn't match any row we tracked — log separately so unmatched volume is easy to audit.
        log_line(POSTBACK_UNMATCHED_LOG_FILE, "cid=$click_id payout=$payout status=$status");
    }

    respond(200, 'OK');
} catch (PDOException $e) {
    log_error('Postback update failed: ' . $e->getMessage());
    respond(500, 'Database error');
}
