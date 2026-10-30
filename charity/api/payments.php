<?php
require_once __DIR__ . '/db.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$db = getDb();
$method = $_SERVER['REQUEST_METHOD'];

function getAvailablePKR(mysqli $db): float {
    $totalCharity = $db->query('SELECT COALESCE(SUM(charity_pkr),0) AS t FROM income_entries')->fetch_assoc()['t'];
    $totalSent = $db->query('SELECT COALESCE(SUM(amount_pkr),0) AS t FROM charity_payments')->fetch_assoc()['t'];
    return (float)$totalCharity - (float)$totalSent;
}

if ($method === 'GET') {
    $result = $db->query('SELECT * FROM charity_payments ORDER BY payment_date DESC, id DESC');
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'id' => (int)$row['id'],
            'destinationName' => $row['destination_name'],
            'date' => $row['payment_date'],
            'amountPKR' => (float)$row['amount_pkr'],
        ];
    }
    jsonResponse($rows);
}

if ($method === 'POST') {
    $data = readJsonBody();

    $destinationName = trim($data['destinationName'] ?? '');
    $date = $data['date'] ?? '';
    $amountPKR = (float)($data['amountPKR'] ?? 0);

    if ($destinationName === '' || $date === '' || $amountPKR <= 0) {
        jsonError('Missing or invalid fields', 422);
    }

    $available = getAvailablePKR($db);
    if ($amountPKR > $available + 0.01) {
        jsonError('Amount exceeds available charity balance (Rs ' . number_format($available, 2) . ')', 422);
    }

    $stmt = $db->prepare(
        'INSERT INTO charity_payments (destination_name, payment_date, amount_pkr) VALUES (?, ?, ?)'
    );
    $stmt->bind_param('ssd', $destinationName, $date, $amountPKR);
    $stmt->execute();

    jsonResponse([
        'id' => $stmt->insert_id,
        'destinationName' => $destinationName,
        'date' => $date,
        'amountPKR' => $amountPKR,
    ], 201);
}

if ($method === 'DELETE') {
    parse_str($_SERVER['QUERY_STRING'] ?? '', $query);
    $id = (int)($query['id'] ?? 0);
    if ($id <= 0) {
        jsonError('Missing id', 422);
    }
    $stmt = $db->prepare('DELETE FROM charity_payments WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    jsonResponse(['deleted' => $id]);
}

jsonError('Method not allowed', 405);
