<?php
/**
 * Import GridKing .gklm or .gklm.enc file (SQL INSERTs, optional AES-256-CBC decryption)
 * Usage: Upload a .gklm or .gklm.enc file via the form below.
 * Only allow admins to import!
 */

require_once '../config/config.php';
requireAdmin(); // Only allow admins

$db = new Database();
$conn = $db->getConnection();

$import_result = '';
$encryption_key = 'key'; // Must match the export key

function decrypt_gklm($data, $key) {
    $raw = base64_decode($data);
    $ivlen = openssl_cipher_iv_length('aes-256-cbc');
    $iv = substr($raw, 0, $ivlen);
    $ciphertext = substr($raw, $ivlen);
    return openssl_decrypt($ciphertext, 'aes-256-cbc', $key, 0, $iv);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['gklm_file'])) {
    $file = $_FILES['gklm_file']['tmp_name'];
    $filename = $_FILES['gklm_file']['name'];
    $content = file_get_contents($file);

    // Decrypt if .enc
    if (str_ends_with($filename, '.enc')) {
        $content = decrypt_gklm($content, $encryption_key);
        if ($content === false) {
            $import_result = '<div class="alert alert-danger">Decryption failed. Wrong key?</div>';
        }
    }

    if ($content) {
        // Truncate tables before import (order matters for foreign keys)
        $tables = ['race_results', 'drivers', 'teams', 'users', 'races', 'seasons', 'settings'];
        foreach ($tables as $table) {
            $conn->exec("SET FOREIGN_KEY_CHECKS=0;");
            $conn->exec("TRUNCATE TABLE `$table`;");
            $conn->exec("SET FOREIGN_KEY_CHECKS=1;");
        }

        // Split into individual SQL statements
        $statements = array_filter(array_map('trim', explode(";\n", $content)));
        $success = 0; $fail = 0;
        foreach ($statements as $sql) {
            if (str_starts_with($sql, '--') || $sql === '') continue;
            try {
                $conn->exec($sql);
                $success++;
            } catch (PDOException $e) {
                $fail++;
            }
        }
        $import_result = "<div class='alert alert-success'>Import complete. Success: $success, Failed: $fail</div>";
    } else {
        $import_result = '<div class="alert alert-danger">Import failed. No content or decryption error.</div>';
    }
}

include '../includes/header.php';
?>
<div class="container my-5">
    <h1 class="mb-4"><i class="bi bi-upload me-2"></i>Import League Data (.gklm)</h1>
    <?php if ($import_result) echo $import_result; ?>
    <form method="post" enctype="multipart/form-data" class="card card-body shadow-sm">
        <div class="mb-3">
            <label for="gklm_file" class="form-label">Select .gklm or .gklm.enc file</label>
            <input type="file" name="gklm_file" id="gklm_file" class="form-control" accept=".gklm,.enc" required>
        </div>
        <button type="submit" class="btn btn-primary">Import</button>
    </form>
    <div class="alert alert-warning mt-3">
        <b>Warning:</b> Importing will overwrite existing data for the imported tables!
    </div>
</div>
<?php include '../includes/footer.php'; ?>