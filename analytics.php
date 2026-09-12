<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/analytics-auth.php';
require_analytics_login();

// --- Date range filter (GET param, whitelisted against a fixed set of options) ---
$range = $_GET['range'] ?? '7d';
$allowed_ranges = ['24h', '7d', '30d', 'all'];
if (!in_array($range, $allowed_ranges, true)) {
    $range = '7d';
}
$range_sql = [
    '24h' => 'created_at >= (NOW() - INTERVAL 1 DAY)',
    '7d'  => 'created_at >= (NOW() - INTERVAL 7 DAY)',
    '30d' => 'created_at >= (NOW() - INTERVAL 30 DAY)',
    'all' => '1=1',
];
$where_clause = $range_sql[$range];

// --- Pagination ---
$per_page = 100;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

$rows = [];
$total_clicks = 0;
$total_conversions = 0;
$total_payout = 0.0;
$total_pages = 1;
$db_error = false;

$db = get_db_connection();
if ($db !== null) {
    try {
        // Summary counts over the filtered range.
        $stmt = $db->prepare(
            "SELECT
                COUNT(*) AS total_clicks,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS total_conversions,
                COALESCE(SUM(payout), 0) AS total_payout
             FROM clicks
             WHERE $where_clause"
        );
        $stmt->execute();
        $summary = $stmt->fetch();
        $total_clicks = (int)$summary['total_clicks'];
        $total_conversions = (int)$summary['total_conversions'];
        $total_payout = (float)$summary['total_payout'];
        $total_pages = max(1, (int)ceil($total_clicks / $per_page));

        // Page of rows, newest first.
        $stmt = $db->prepare(
            "SELECT click_id, fbclid, campaign_id, adset_id, ad_id, ip_address, status, payout, created_at, converted_at
             FROM clicks
             WHERE $where_clause
             ORDER BY created_at DESC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
    } catch (PDOException $e) {
        log_error('Analytics query failed: ' . $e->getMessage());
        $db_error = true;
    }
} else {
    $db_error = true;
}

$conversion_rate = $total_clicks > 0 ? ($total_conversions / $total_clicks) * 100 : 0;

function h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function status_class($status) {
    switch (strtolower((string)$status)) {
        case 'approved': return 'status-approved';
        case 'declined': return 'status-declined';
        default: return 'status-pending';
    }
}

/** Renders a long id shortened to $len chars + "…", with the full value in a hover tooltip. */
function short_id($value, $len = 14) {
    $value = (string)($value ?? '');
    if ($value === '') return '—';
    $display = strlen($value) > $len ? substr($value, 0, $len) . '…' : $value;
    return '<span title="' . h($value) . '">' . h($display) . '</span>';
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>PillowPotion — Analytics</title>
<style>
  * { box-sizing: border-box; }
  html, body { overflow-x: hidden; }
  body {
    margin: 0; min-height: 100vh; padding: 24px 16px;
    background: #0b1220; color: #e6ebf5;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  }
  .wrap { max-width: 1300px; margin: 0 auto; }

  .topbar {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 12px; margin-bottom: 24px;
  }
  .brand { font-size: 22px; font-weight: 800; color: #a855f7; }
  .logout { color: #94a3b8; text-decoration: none; font-size: 13.5px; }
  .logout:hover { color: #e6ebf5; }

  .controls {
    display: flex; align-items: center; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;
  }
  .controls label { font-size: 13px; color: #94a3b8; }
  .controls select {
    background: #121a2b; color: #e6ebf5; border: 1px solid #253046;
    border-radius: 8px; padding: 8px 12px; font-size: 13.5px;
  }

  .summary-grid {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 24px;
  }
  @media (max-width: 800px) { .summary-grid { grid-template-columns: repeat(2, 1fr); } }
  @media (max-width: 480px) { .summary-grid { grid-template-columns: 1fr; } }

  .stat-card {
    background: #121a2b; border: 1px solid #253046; border-radius: 12px; padding: 16px;
  }
  .stat-label { font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 6px; }
  .stat-value { font-size: 24px; font-weight: 800; color: #e6ebf5; }

  .table-scroll {
    overflow-x: auto; border: 1px solid #253046; border-radius: 12px; background: #121a2b;
  }
  table { width: 100%; border-collapse: collapse; font-size: 13px; white-space: nowrap; }
  th, td { padding: 10px 14px; text-align: left; border-bottom: 1px solid #1c2740; }
  th {
    color: #94a3b8; font-size: 11px; text-transform: uppercase; letter-spacing: .04em;
    background: #0f1626; position: sticky; top: 0;
  }
  tr:last-child td { border-bottom: none; }
  tr:hover td { background: #161f34; }
  td:nth-child(1) span[title], td:nth-child(2) span[title] {
    cursor: help; border-bottom: 1px dotted #3a4762;
  }

  .status-pill {
    display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 700;
  }
  .status-approved { background: rgba(34,197,94,.15); color: #4ade80; }
  .status-pending  { background: rgba(148,163,184,.15); color: #94a3b8; }
  .status-declined { background: rgba(248,113,113,.15); color: #f87171; }

  .empty, .error-msg { padding: 32px; text-align: center; color: #94a3b8; }
  .error-msg { color: #f87171; }

  .pagination {
    display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 18px; flex-wrap: wrap;
  }
  .pagination a, .pagination span {
    padding: 8px 14px; border-radius: 8px; font-size: 13px; text-decoration: none;
    background: #121a2b; border: 1px solid #253046; color: #e6ebf5;
  }
  .pagination .disabled { opacity: .4; pointer-events: none; }
  .pagination .current { background: #a855f7; border-color: #a855f7; color: #fff; font-weight: 700; }
</style>
</head>
<body>
<div class="wrap">
  <div class="topbar">
    <div class="brand">PillowPotion Analytics</div>
    <a class="logout" href="/analytics-logout">Log out</a>
  </div>

  <form class="controls" method="get">
    <label for="range">Date range:</label>
    <select id="range" name="range" onchange="this.form.submit()">
      <option value="24h" <?= $range === '24h' ? 'selected' : '' ?>>Last 24 hours</option>
      <option value="7d"  <?= $range === '7d'  ? 'selected' : '' ?>>Last 7 days</option>
      <option value="30d" <?= $range === '30d' ? 'selected' : '' ?>>Last 30 days</option>
      <option value="all" <?= $range === 'all' ? 'selected' : '' ?>>All time</option>
    </select>
  </form>

  <div class="summary-grid">
    <div class="stat-card">
      <div class="stat-label">Total Clicks</div>
      <div class="stat-value"><?= number_format($total_clicks) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Total Conversions</div>
      <div class="stat-value"><?= number_format($total_conversions) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Conversion Rate</div>
      <div class="stat-value"><?= number_format($conversion_rate, 2) ?>%</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Total Payout</div>
      <div class="stat-value">$<?= number_format($total_payout, 2) ?></div>
    </div>
  </div>

  <div class="table-scroll">
    <?php if ($db_error): ?>
      <div class="error-msg">Couldn't load analytics data right now. Please try again shortly.</div>
    <?php elseif (empty($rows)): ?>
      <div class="empty">No clicks in this date range.</div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Click ID</th>
          <th>FBCLID</th>
          <th>Campaign ID</th>
          <th>Adset ID</th>
          <th>Ad ID</th>
          <th>IP Address</th>
          <th>Status</th>
          <th>Payout</th>
          <th>Click Time</th>
          <th>Conversion Time</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
          <td><?= short_id($row['click_id']) ?></td>
          <td><?= short_id($row['fbclid']) ?></td>
          <td><?= h($row['campaign_id']) ?: '—' ?></td>
          <td><?= h($row['adset_id']) ?: '—' ?></td>
          <td><?= h($row['ad_id']) ?: '—' ?></td>
          <td><?= h($row['ip_address']) ?></td>
          <td><span class="status-pill <?= status_class($row['status']) ?>"><?= h($row['status']) ?></span></td>
          <td>$<?= number_format((float)$row['payout'], 2) ?></td>
          <td><?= h($row['created_at']) ?></td>
          <td><?= h($row['converted_at']) ?: '—' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <?php if (!$db_error && $total_pages > 1): ?>
  <div class="pagination">
    <?php
      $qs = ['range' => $range];
      $prev_url = '?' . http_build_query($qs + ['page' => max(1, $page - 1)]);
      $next_url = '?' . http_build_query($qs + ['page' => min($total_pages, $page + 1)]);
    ?>
    <a href="<?= h($prev_url) ?>" class="<?= $page <= 1 ? 'disabled' : '' ?>">&laquo; Prev</a>
    <span class="current">Page <?= $page ?> of <?= $total_pages ?></span>
    <a href="<?= h($next_url) ?>" class="<?= $page >= $total_pages ? 'disabled' : '' ?>">Next &raquo;</a>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
