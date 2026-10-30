<?php
// Database connection config for Hostinger MySQL.
// Real credentials live in config.local.php, which is NOT committed to git
// so the DB password never ends up in the repo's history. On the server,
// create config.local.php once (copy config.local.php.example) defining:
//   DB_HOST, DB_NAME, DB_USER, DB_PASS

require_once __DIR__ . '/config.local.php';

function getDb(): mysqli {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset('utf8mb4');
    return $conn;
}

function jsonResponse($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function jsonError(string $message, int $status = 400): void {
    jsonResponse(['error' => $message], $status);
}

function readJsonBody(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        jsonError('Invalid JSON body', 400);
    }
    return $data;
}
