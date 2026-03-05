<?php
/**
 * GridKing – Migration & Export (Legacy 1.3.2)
 *
 * Provides:
 *  - Full-league export as JSON, ZIP, or legacy GKLM
 *  - Secure export tokens (valid for 24 h by default)
 *  - Import preview before committing data
 *  - License & version metadata in all exports
 *
 * Access: admin only.
 */

require_once '../config/config.php';
requireAdmin();

$db   = new Database();
$conn = $db->getConnection();

$success = '';
$error   = '';
$preview = null;  // Import preview data

// Load settings
$stmt = $conn->prepare("SELECT `key`, `value` FROM settings");
$stmt->execute();
$settings = [];
foreach ($stmt->fetchAll() as $row) {
    $settings[$row['key']] = $row['value'];
}

// ----------------------------------------------------------------
// ACTION: Token-based download (GET ?download_token=...)
// ----------------------------------------------------------------
if (!empty($_GET['download_token'])) {
    $token = preg_replace('/[^a-f0-9]/', '', $_GET['download_token']);
    $stmt  = $conn->prepare(
        "SELECT * FROM migration_exports
         WHERE export_token = :t AND status != 'expired' AND expires_at > NOW()
         LIMIT 1"
    );
    $stmt->execute([':t' => $token]);
    $tok = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tok) {
        $error = 'Invalid or expired export token.';
    } else {
        // Update download counter
        $conn->prepare("UPDATE migration_exports SET download_count = download_count + 1,
                         last_downloaded_at = NOW(), status = 'completed'
                         WHERE export_token = :t")
             ->execute([':t' => $token]);

        $exportData = collectExportData($conn, $settings);
        $format     = $tok['export_format'];
        $filename   = 'gridking_export_' . date('Ymd_His');

        if ($format === 'json') {
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . $filename . '.json"');
            echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit();
        } elseif ($format === 'zip') {
            $tmpFile = tempnam(sys_get_temp_dir(), 'gk_export_');
            $zip     = new ZipArchive();
            $zip->open($tmpFile, ZipArchive::OVERWRITE);
            $zip->addFromString('data.json',   json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $zip->addFromString('inserts.sql', buildSqlInserts($conn, $exportData));
            $zip->close();
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $filename . '.zip"');
            header('Content-Length: ' . filesize($tmpFile));
            readfile($tmpFile);
            @unlink($tmpFile);
            exit();
        } else {
            $output = buildSqlInserts($conn, $exportData);
            header('Content-Type: text/plain');
            header('Content-Disposition: attachment; filename="' . $filename . '.gklm"');
            echo $output;
            exit();
        }
    }
}

$tokenTTL = (int)($settings['migration_export_token_ttl'] ?? 86400);

// ----------------------------------------------------------------
// Helper: generate a secure export token
// ----------------------------------------------------------------
function generateExportToken(): string
{
    return bin2hex(random_bytes(32));
}

// ----------------------------------------------------------------
// Helper: build the export metadata block
// ----------------------------------------------------------------
function buildMetadata(array $settings): array
{
    return [
        'exported_at'  => date('Y-m-d H:i:s'),
        'app_version'  => APP_VERSION,
        'db_version'   => $settings['db_version'] ?? 'unknown',
        'league_name'  => $settings['league_name'] ?? 'GridKing League',
        'license'      => 'GridKing Self-Hosted License – for personal/club use only.',
        'format'       => 'GridKing Migration Export v1.3',
    ];
}

// ----------------------------------------------------------------
// Helper: collect all exportable data from the database
// ----------------------------------------------------------------
function collectExportData(PDO $conn, array $settings): array
{
    $tables = [
        'users', 'drivers', 'teams', 'seasons', 'races',
        'race_results', 'penalties', 'settings', 'news',
    ];

    $data = ['_meta' => buildMetadata($settings)];

    foreach ($tables as $table) {
        try {
            $rows = $conn->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
            $data[$table] = $rows;
        } catch (PDOException $e) {
            $data[$table] = [];
        }
    }
    return $data;
}

// ----------------------------------------------------------------
// Helper: build SQL INSERT statements from export data
// ----------------------------------------------------------------
function buildSqlInserts(PDO $conn, array $data): string
{
    $sql = "-- GridKing Export\n-- Date: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- App Version: " . APP_VERSION . "\n";
    $sql .= "-- League: " . htmlspecialchars($data['_meta']['league_name'] ?? '') . "\n\n";

    foreach ($data as $table => $rows) {
        if ($table === '_meta' || empty($rows)) continue;
        $sql .= "-- Table: $table\n";
        foreach ($rows as $row) {
            $columns = array_map(fn($c) => "`$c`", array_keys($row));
            $values  = array_map(
                fn($v) => $v === null ? 'NULL' : $conn->quote((string)$v),
                array_values($row)
            );
            $sql .= "INSERT INTO `$table` (" . implode(',', $columns)
                 . ") VALUES (" . implode(',', $values) . ");\n";
        }
        $sql .= "\n";
    }
    return $sql;
}

// ----------------------------------------------------------------
// ACTION: Generate new export token
// ----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate_token') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $token  = generateExportToken();
        $format = in_array($_POST['export_format'] ?? '', ['json', 'zip', 'gklm'])
                    ? $_POST['export_format'] : 'json';
        $expiry = date('Y-m-d H:i:s', time() + $tokenTTL);

        $stmt = $conn->prepare(
            "INSERT INTO migration_exports
             (export_token, export_format, created_by, app_version, db_version, league_name, status, expires_at)
             VALUES (:token, :fmt, :uid, :av, :dv, :ln, 'pending', :exp)"
        );
        $stmt->execute([
            ':token' => $token,
            ':fmt'   => $format,
            ':uid'   => $_SESSION['user_id'],
            ':av'    => APP_VERSION,
            ':dv'    => $settings['db_version'] ?? 'unknown',
            ':ln'    => $settings['league_name'] ?? 'GridKing',
            ':exp'   => $expiry,
        ]);
        $success = 'Export token generated. Use the Download button below or share the token.';
    }
}

// ----------------------------------------------------------------
// ACTION: Perform export (download)
// ----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'export_download') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $format = in_array($_POST['export_format'] ?? '', ['json', 'zip', 'gklm'])
                    ? $_POST['export_format'] : 'json';
        $encrypt = isset($_POST['encrypt_export']);
        $encKey  = $settings['export_encryption_key'] ?? 'gridking-default-key';

        $exportData = collectExportData($conn, $settings);
        $filename   = 'gridking_export_' . date('Ymd_His');

        switch ($format) {
            case 'json':
                $output      = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                $filename   .= '.json';
                $contentType = 'application/json';
                break;

            case 'zip':
                // Build a ZIP in memory
                $tmpFile = tempnam(sys_get_temp_dir(), 'gk_export_');
                $zip     = new ZipArchive();
                if ($zip->open($tmpFile, ZipArchive::OVERWRITE) !== true) {
                    $error = 'Could not create ZIP archive.';
                    break;
                }
                $zip->addFromString('data.json',     json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $zip->addFromString('meta.json',     json_encode($exportData['_meta'], JSON_PRETTY_PRINT));
                $zip->addFromString('inserts.sql',   buildSqlInserts($conn, $exportData));
                $zip->addFromString('GRIDKING_EXPORT.txt',
                    "GridKing Migration Export\n" .
                    "Version : " . APP_VERSION . "\n" .
                    "Date    : " . date('Y-m-d H:i:s') . "\n" .
                    "League  : " . ($settings['league_name'] ?? 'GridKing') . "\n"
                );
                $zip->close();

                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $filename . '.zip"');
                header('Content-Length: ' . filesize($tmpFile));
                readfile($tmpFile);
                @unlink($tmpFile);
                exit();

            case 'gklm':
            default:
                $output      = buildSqlInserts($conn, $exportData);
                if ($encrypt) {
                    $ivlen      = openssl_cipher_iv_length('aes-256-cbc');
                    $iv         = openssl_random_pseudo_bytes($ivlen);
                    $cipher     = openssl_encrypt($output, 'aes-256-cbc', $encKey, 0, $iv);
                    $output     = base64_encode($iv . $cipher);
                    $filename  .= '.gklm.enc';
                    $contentType = 'application/octet-stream';
                } else {
                    $filename  .= '.gklm';
                    $contentType = 'text/plain';
                }
                break;
        }

        if (!$error) {
            header('Content-Type: ' . ($contentType ?? 'application/octet-stream'));
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo $output;
            exit();
        }
    }
}

// ----------------------------------------------------------------
// ACTION: Import preview
// ----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import_preview') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } elseif (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'No file uploaded or upload error.';
    } else {
        $filename = $_FILES['import_file']['name'];
        $tmpPath  = $_FILES['import_file']['tmp_name'];
        $content  = file_get_contents($tmpPath);

        // Handle ZIP files – extract data.json
        if (str_ends_with(strtolower($filename), '.zip')) {
            $zip = new ZipArchive();
            if ($zip->open($tmpPath) === true) {
                $jsonContent = $zip->getFromName('data.json');
                $zip->close();
                if ($jsonContent !== false) {
                    $content  = $jsonContent;
                    $filename = 'data.json'; // treat as JSON
                } else {
                    $error = 'ZIP file does not contain a data.json file.';
                }
            } else {
                $error = 'Could not open ZIP file.';
            }
        }

        // Auto-detect and decrypt .enc files
        if (str_ends_with(strtolower($filename), '.enc')) {
            $encKey  = $settings['export_encryption_key'] ?? 'gridking-default-key';
            $raw     = base64_decode($content);
            $ivlen   = openssl_cipher_iv_length('aes-256-cbc');
            $iv      = substr($raw, 0, $ivlen);
            $cipher  = substr($raw, $ivlen);
            $content = openssl_decrypt($cipher, 'aes-256-cbc', $encKey, 0, $iv);
            if ($content === false) {
                $error = 'Decryption failed. Wrong key?';
            }
        }

        if (!$error) {
            // Detect JSON vs SQL
            $trimmed = ltrim((string)$content);
            if (empty($trimmed)) {
                $error = 'The import file is empty.';
            } elseif ($trimmed[0] === '{' || $trimmed[0] === '[') {
                // JSON export
                $decoded = json_decode($content, true);
                if (!is_array($decoded)) {
                    $error = 'Invalid JSON export file.';
                } else {
                    $preview = [
                        'format'   => 'json',
                        'meta'     => $decoded['_meta'] ?? [],
                        'tables'   => [],
                        'raw'      => $content,
                    ];
                    foreach ($decoded as $k => $v) {
                        if ($k !== '_meta' && is_array($v)) {
                            $preview['tables'][$k] = count($v);
                        }
                    }
                }
            } else {
                // SQL / GKLM export – count statements
                $statements = array_filter(
                    array_map('trim', explode(";\n", $content))
                );
                $inserts = array_filter($statements, fn($s) => stripos($s, 'INSERT INTO') === 0);
                $preview = [
                    'format'     => 'sql',
                    'meta'       => [],
                    'tables'     => [],
                    'statements' => count($statements),
                    'inserts'    => count($inserts),
                    'raw'        => $content,
                ];
                // Parse table names from INSERT statements
                foreach ($inserts as $stmt) {
                    if (preg_match('/INSERT INTO `?(\w+)`?/i', $stmt, $m)) {
                        $preview['tables'][$m[1]] = ($preview['tables'][$m[1]] ?? 0) + 1;
                    }
                }
                // Try to extract metadata from SQL comments
                if (preg_match('/-- App Version: (.+)/i', $content, $m)) {
                    $preview['meta']['app_version'] = trim($m[1]);
                }
                if (preg_match('/-- Date: (.+)/i', $content, $m)) {
                    $preview['meta']['exported_at'] = trim($m[1]);
                }
            }
        }
    }
}

// ----------------------------------------------------------------
// ACTION: Confirm import
// ----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import_confirm') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $rawContent = $_POST['import_raw'] ?? '';
        if (empty($rawContent)) {
            $error = 'No import data found. Please start over.';
        } else {
            $trimmed = ltrim($rawContent);
            $ok = $fail = 0;

            if (empty($trimmed)) {
                $error = 'The import data is empty.';
            } elseif ($trimmed[0] === '{' || $trimmed[0] === '[') {
                // JSON import
                $decoded = json_decode($rawContent, true);
                if (!is_array($decoded)) {
                    $error = 'Invalid JSON data.';
                } else {
                    $tablesToClear = ['race_results', 'drivers', 'teams', 'users', 'races', 'seasons', 'penalties', 'news', 'settings'];
                    $conn->exec("SET FOREIGN_KEY_CHECKS=0");
                    foreach ($tablesToClear as $tbl) {
                        try { $conn->exec("TRUNCATE TABLE `$tbl`"); } catch (PDOException $e) {}
                    }
                    $conn->exec("SET FOREIGN_KEY_CHECKS=1");

                    foreach ($decoded as $table => $rows) {
                        if ($table === '_meta' || !is_array($rows)) continue;
                        foreach ($rows as $row) {
                            if (!is_array($row)) continue;
                            $cols = array_map(fn($c) => "`$c`", array_keys($row));
                            $phs  = array_map(fn($c) => ":$c", array_keys($row));
                            $sql  = "INSERT IGNORE INTO `$table` (" . implode(',', $cols)
                                  . ") VALUES (" . implode(',', $phs) . ")";
                            try {
                                $stmt = $conn->prepare($sql);
                                $stmt->execute($row);
                                $ok++;
                            } catch (PDOException $e) {
                                $fail++;
                            }
                        }
                    }
                }
            } else {
                // SQL import
                $tablesToClear = ['race_results', 'drivers', 'teams', 'users', 'races', 'seasons', 'penalties', 'news', 'settings'];
                $conn->exec("SET FOREIGN_KEY_CHECKS=0");
                foreach ($tablesToClear as $tbl) {
                    try { $conn->exec("TRUNCATE TABLE `$tbl`"); } catch (PDOException $e) {}
                }
                $conn->exec("SET FOREIGN_KEY_CHECKS=1");

                $statements = array_filter(array_map('trim', explode(";\n", $rawContent)));
                foreach ($statements as $sqlStmt) {
                    if (str_starts_with($sqlStmt, '--') || $sqlStmt === '') continue;
                    try {
                        $conn->exec($sqlStmt);
                        $ok++;
                    } catch (PDOException $e) {
                        $fail++;
                    }
                }
            }

            if (!$error) {
                $success = "Import complete. Success: $ok, Failed: $fail";
            }
        }
    }
}

// ----------------------------------------------------------------
// Load existing export tokens
// ----------------------------------------------------------------
$tokensStmt = $conn->prepare(
    "SELECT id, export_token, export_format, status, created_at, expires_at, download_count
     FROM migration_exports
     WHERE created_by = :uid AND expires_at > NOW()
     ORDER BY created_at DESC LIMIT 10"
);
$tokensStmt->execute([':uid' => $_SESSION['user_id']]);
$activeTokens = $tokensStmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Migration & Export';
include '../includes/header.php';
?>

<div class="container my-5">
    <div class="d-flex align-items-center mb-4 gap-3">
        <a href="settings.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h1 class="mb-0"><i class="bi bi-box-arrow-up me-2"></i>Migration &amp; Export</h1>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- ═══════════════════════════════════════════════════
             LEFT COLUMN – Export
             ═══════════════════════════════════════════════════ -->
        <div class="col-lg-6">

            <!-- Export card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-download me-2"></i>Export League Data</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        Export all league data including drivers, teams, races, results, and settings.
                        Each export includes version and license metadata.
                    </p>

                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="export_download">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Export Format</label>
                            <div class="d-flex gap-3 flex-wrap">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="export_format" id="fmt_json" value="json" checked>
                                    <label class="form-check-label" for="fmt_json">
                                        <i class="bi bi-filetype-json text-warning me-1"></i>JSON
                                        <small class="text-muted d-block">Structured, portable</small>
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="export_format" id="fmt_zip" value="zip">
                                    <label class="form-check-label" for="fmt_zip">
                                        <i class="bi bi-file-zip text-primary me-1"></i>ZIP Archive
                                        <small class="text-muted d-block">JSON + SQL + readme</small>
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="export_format" id="fmt_gklm" value="gklm">
                                    <label class="form-check-label" for="fmt_gklm">
                                        <i class="bi bi-file-earmark-code text-secondary me-1"></i>GKLM (Legacy SQL)
                                        <small class="text-muted d-block">Compatible with &lt;1.3</small>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3" id="encryptRow">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="encrypt_export" id="encryptExport">
                                <label class="form-check-label" for="encryptExport">
                                    Encrypt export (AES-256-CBC) — GKLM only
                                </label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-download me-1"></i>Download Export
                        </button>
                    </form>
                </div>
            </div>

            <!-- Secure token card -->
            <div class="card shadow-sm">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="bi bi-key me-2"></i>Secure Export Tokens</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        Generate a time-limited token (valid for <?php echo round($tokenTTL / 3600, 1); ?> hour<?php echo $tokenTTL !== 3600 ? 's' : ''; ?>).
                        Share it with a trusted admin to let them download the export without giving
                        full admin credentials.
                    </p>

                    <form method="post" class="mb-3">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="generate_token">
                        <div class="input-group">
                            <select name="export_format" class="form-select" style="max-width:150px;">
                                <option value="json">JSON</option>
                                <option value="zip">ZIP</option>
                                <option value="gklm">GKLM</option>
                            </select>
                            <button type="submit" class="btn btn-outline-secondary">
                                <i class="bi bi-plus-circle me-1"></i>Generate Token
                            </button>
                        </div>
                    </form>

                    <?php if (!empty($activeTokens)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Token (first 16)</th>
                                    <th>Format</th>
                                    <th>Expires</th>
                                    <th>Downloads</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activeTokens as $tok): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars(substr($tok['export_token'], 0, 16)); ?>…</code></td>
                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars(strtoupper($tok['export_format'])); ?></span></td>
                                    <td><small><?php echo htmlspecialchars($tok['expires_at']); ?></small></td>
                                    <td><?php echo (int)$tok['download_count']; ?></td>
                                    <td>
                                        <a href="?download_token=<?php echo urlencode($tok['export_token']); ?>"
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-download"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <p class="text-muted small mb-0">No active tokens.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════
             RIGHT COLUMN – Import
             ═══════════════════════════════════════════════════ -->
        <div class="col-lg-6">

            <?php if ($preview === null): ?>
            <!-- Step 1: upload file for preview -->
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-upload me-2"></i>Import League Data</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning mb-3">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        <strong>Warning:</strong> Importing will <strong>overwrite</strong> existing data
                        for the imported tables. This cannot be undone!
                    </div>
                    <p class="text-muted small mb-3">
                        Upload a <code>.json</code>, <code>.zip</code> (will extract <code>data.json</code>),
                        <code>.gklm</code>, or <code>.gklm.enc</code> file.
                        You will see a preview before committing.
                    </p>
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="import_preview">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Select export file</label>
                            <input type="file" name="import_file" class="form-control"
                                   accept=".json,.zip,.gklm,.enc" required>
                        </div>
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-eye me-1"></i>Preview Import
                        </button>
                    </form>
                </div>
            </div>

            <?php else: ?>
            <!-- Step 2: show preview & confirm -->
            <div class="card shadow-sm border-warning">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="bi bi-eye me-2"></i>Import Preview</h5>
                </div>
                <div class="card-body">
                    <h6 class="fw-semibold mb-2">Export Metadata</h6>
                    <?php if (!empty($preview['meta'])): ?>
                    <table class="table table-sm table-bordered mb-3">
                        <tbody>
                            <?php foreach ($preview['meta'] as $k => $v): ?>
                            <tr>
                                <th class="table-secondary w-35"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $k))); ?></th>
                                <td><?php echo htmlspecialchars((string)$v); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p class="text-muted small">No metadata found in this export file.</p>
                    <?php endif; ?>

                    <h6 class="fw-semibold mb-2">Tables to be imported</h6>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <?php foreach ($preview['tables'] as $tbl => $count): ?>
                            <span class="badge bg-info text-dark">
                                <?php echo htmlspecialchars($tbl); ?>
                                (<?php echo (int)$count; ?> rows)
                            </span>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($preview['format'] === 'sql'): ?>
                    <p class="text-muted small">
                        Format: SQL — <?php echo (int)$preview['inserts']; ?> INSERT statements,
                        <?php echo (int)$preview['statements']; ?> total statements.
                    </p>
                    <?php endif; ?>

                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        Clicking <strong>Confirm Import</strong> will <strong>truncate and replace</strong>
                        the listed tables with the imported data.
                    </div>

                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="import_confirm">
                        <input type="hidden" name="import_raw"
                               value="<?php echo htmlspecialchars($preview['raw'], ENT_QUOTES); ?>">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-check-circle me-1"></i>Confirm Import
                            </button>
                            <a href="migration.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle me-1"></i>Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Show/hide encrypt option based on selected format
document.querySelectorAll('input[name="export_format"]').forEach(function(el) {
    el.addEventListener('change', function() {
        document.getElementById('encryptRow').style.display =
            this.value === 'gklm' ? '' : 'none';
    });
});
// Initially hide encrypt for non-GKLM
(function() {
    const sel = document.querySelector('input[name="export_format"]:checked');
    if (sel && sel.value !== 'gklm') {
        document.getElementById('encryptRow').style.display = 'none';
    }
})();
</script>

<?php include '../includes/footer.php'; ?>
