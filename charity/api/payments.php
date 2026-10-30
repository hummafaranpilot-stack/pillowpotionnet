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

    // Allowed to go negative on purpose — you can pay a destination out of
    // pocket ahead of the charity balance catching up; the next income entry
    // brings it back toward zero automatically.
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
