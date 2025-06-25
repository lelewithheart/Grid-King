<?php
/**
 * Export GridKing data as .gklm file (SQL INSERTs, optional AES-256-CBC encryption)
 * Usage: Run from CLI or browser. Set $encrypt = true and $encryption_key for encryption.
 */

require_once '../config/config.php';
requireAdmin(); // Only allow admins to export

$db = new Database();
$conn = $db->getConnection();

// --- CONFIG ---
$encrypt = true; // Set to true to encrypt output
$encryption_key = 'key'; // Change this if $encrypt is true

$tables = [
    'users',
    'drivers',
    'teams',
    'races',
    'race_results',
    'seasons',
    'settings'
];

// --- EXPORT FUNCTION ---
function exportTable($conn, $table) {
    $rows = $conn->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
    $sql = '';
    foreach ($rows as $row) {
        $columns = array_map(fn($col) => "`$col`", array_keys($row));
        $values = array_map(fn($val) => $val === null ? 'NULL' : $conn->quote($val), array_values($row));
        $sql .= "INSERT INTO `$table` (" . implode(',', $columns) . ") VALUES (" . implode(',', $values) . ");\n";
    }
    return $sql;
}

// --- BUILD EXPORT ---
$output = "-- GridKing Export\n-- Date: " . date('Y-m-d H:i:s') . "\n\n";
foreach ($tables as $table) {
    $output .= "-- Table: $table\n";
    $output .= exportTable($conn, $table) . "\n";
}

// --- ENCRYPT IF NEEDED ---
if ($encrypt) {
    $ivlen = openssl_cipher_iv_length('aes-256-cbc');
    $iv = openssl_random_pseudo_bytes($ivlen);
    $ciphertext = openssl_encrypt($output, 'aes-256-cbc', $encryption_key, 0, $iv);
    $output = base64_encode($iv . $ciphertext);
    $filename = 'simleague_export_' . date('Ymd_His') . '.gklm.enc';
    $contentType = 'application/octet-stream';
} else {
    $filename = 'simleague_export_' . date('Ymd_His') . '.gklm';
    $contentType = 'text/plain';
}

// --- OUTPUT FILE ---
header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo $output;