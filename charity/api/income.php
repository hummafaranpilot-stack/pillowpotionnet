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
    $result = $db->query('SELECT * FROM income_entries ORDER BY entry_date DESC, id DESC');
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'id' => (int)$row['id'],
            'sourceName' => $row['source_name'],
            'date' => $row['entry_date'],
            'amountUSD' => (float)$row['amount_usd'],
            'exchangeRate' => (float)$row['exchange_rate'],
            'charityPercent' => (float)$row['charity_percent'],
            'charityUSD' => (float)$row['charity_usd'],
            'charityPKR' => (float)$row['charity_pkr'],
        ];
    }
    jsonResponse($rows);
}

if ($method === 'POST') {
    $data = readJsonBody();

    $sourceName = trim($data['sourceName'] ?? '');
    $date = $data['date'] ?? '';
    $amountUSD = (float)($data['amountUSD'] ?? 0);
    $exchangeRate = (float)($data['exchangeRate'] ?? 0);
    $charityPercent = (float)($data['charityPercent'] ?? 5);

    if ($sourceName === '' || $date === '' || $amountUSD <= 0 || $exchangeRate <= 0) {
        jsonError('Missing or invalid fields', 422);
    }
    if ($charityPercent <= 0 || $charityPercent > 100) {
        jsonError('Charity percent must be between 0 and 100', 422);
    }

    $charityUSD = round($amountUSD * ($charityPercent / 100), 2);
    $charityPKR = round($charityUSD * $exchangeRate, 2);

    $stmt = $db->prepare(
        'INSERT INTO income_entries (source_name, entry_date, amount_usd, exchange_rate, charity_percent, charity_usd, charity_pkr)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('ssddddd', $sourceName, $date, $amountUSD, $exchangeRate, $charityPercent, $charityUSD, $charityPKR);
    $stmt->execute();

    jsonResponse([
        'id' => $stmt->insert_id,
        'sourceName' => $sourceName,
        'date' => $date,
        'amountUSD' => $amountUSD,
        'exchangeRate' => $exchangeRate,
        'charityPercent' => $charityPercent,
        'charityUSD' => $charityUSD,
        'charityPKR' => $charityPKR,
    ], 201);
}

if ($method === 'DELETE') {
    parse_str($_SERVER['QUERY_STRING'] ?? '', $query);
    $id = (int)($query['id'] ?? 0);
    if ($id <= 0) {
        jsonError('Missing id', 422);
    }
    $stmt = $db->prepare('DELETE FROM income_entries WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    jsonResponse(['deleted' => $id]);
}

jsonError('Method not allowed', 405);
