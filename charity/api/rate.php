<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

function fetchRate(string $url, string $key): ?float {
    $ctx = stream_context_create(['http' => ['timeout' => 6]]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) return null;
    $json = json_decode($raw, true);
    if (!isset($json['rates']['PKR'])) return null;
    return (float)$json['rates']['PKR'];
}

$rate = fetchRate('https://open.er-api.com/v6/latest/USD', 'rates');
if ($rate === null) {
    $rate = fetchRate('https://api.frankfurter.app/latest?from=USD&to=PKR', 'rates');
}

if ($rate === null) {
    http_response_code(502);
    echo json_encode(['error' => 'Could not fetch live exchange rate']);
    exit;
}

echo json_encode(['rate' => $rate, 'pair' => 'USD_PKR']);
