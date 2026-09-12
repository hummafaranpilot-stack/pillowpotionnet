<?php
// Database connection for the click-tracking system (table: clicks).
// Uses PDO with prepared statements everywhere it's used — never build queries with string concatenation.
//
// Actual credentials live in config.local.php, which is NOT committed to git
// (see .gitignore) so the DB password never ends up in the repo's history.
// On the server, create config.local.php once (see instructions.txt) defining:
//   DB_HOST, DB_NAME, DB_USER, DB_PASS

require_once __DIR__ . '/config.local.php';

define('ERROR_LOG_FILE', __DIR__ . '/logs/errors.log');

/** Appends a timestamped line to logs/errors.log instead of leaking details to the visitor. */
function log_error($message) {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    error_log($line, 3, ERROR_LOG_FILE);
}

/**
 * Returns a PDO connection, or null on failure.
 * Callers must handle a null return themselves (e.g. still redirect the visitor) —
 * this function never echoes/throws out to the browser, it only logs.
 */
function get_db_connection() {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    try {
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        log_error('DB connection failed: ' . $e->getMessage());
        return null;
    }
}
