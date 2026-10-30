<?php
require_once __DIR__ . '/db.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$db = getDb();

$totalCharity = (float)$db->query('SELECT COALESCE(SUM(charity_pkr),0) AS t FROM income_entries')->fetch_assoc()['t'];
$totalSent = (float)$db->query('SELECT COALESCE(SUM(amount_pkr),0) AS t FROM charity_payments')->fetch_assoc()['t'];

jsonResponse([
    'totalCharityPKR' => $totalCharity,
    'totalSentPKR' => $totalSent,
    'availablePKR' => $totalCharity - $totalSent,
]);
