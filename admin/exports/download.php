<?php
/**
 * Export Download Handler
 * Secure file download with access control and logging
 */

require_once '../../config/config.php';

// Require admin access
requireAdmin();

if (!isset($_GET['file']) || empty($_GET['file'])) {
    http_response_code(400);
    die('Invalid file parameter');
}

$filename = basename($_GET['file']); // Sanitize filename
$exportDir = __DIR__ . '/../../exports/';
$filepath = $exportDir . $filename;

// Validate file exists and is within export directory
if (!file_exists($filepath) || !is_file($filepath)) {
    http_response_code(404);
    die('File not found');
}

// Check if file is actually in exports directory (prevent path traversal)
$realExportDir = realpath($exportDir);
$realFilePath = realpath($filepath);

if (!$realFilePath || strpos($realFilePath, $realExportDir) !== 0) {
    http_response_code(403);
    die('Access denied');
}

try {
    // Verify user has access to this export
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("
        SELECT id, export_type, data_type, user_id, file_size, created_at 
        FROM export_logs 
        WHERE filename = :filename 
        AND (user_id = :user_id OR :is_admin = 1)
        AND (expires_at IS NULL OR expires_at > NOW())
    ");
    
    $stmt->bindParam(':filename', $filename);
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->bindValue(':is_admin', isAdmin() ? 1 : 0, PDO::PARAM_INT);
    $stmt->execute();
    
    $exportRecord = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$exportRecord) {
        http_response_code(403);
        die('Access denied or file expired');
    }
    
    // Update download count
    $updateStmt = $conn->prepare("
        UPDATE export_logs 
        SET downloaded_count = downloaded_count + 1, last_downloaded_at = NOW()
        WHERE id = :export_id
    ");
    $updateStmt->bindParam(':export_id', $exportRecord['id'], PDO::PARAM_INT);
    $updateStmt->execute();
    
    // Determine content type
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    $contentType = 'application/octet-stream';
    
    switch (strtolower($extension)) {
        case 'csv':
            $contentType = 'text/csv';
            break;
        case 'pdf':
            $contentType = 'application/pdf';
            break;
        case 'json':
            $contentType = 'application/json';
            break;
    }
    
    // Set headers for file download
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    // Output file
    readfile($filepath);
    
    // Log download
    logError('Export downloaded', [
        'filename' => $filename,
        'user_id' => $_SESSION['user_id'],
        'export_type' => $exportRecord['export_type'],
        'data_type' => $exportRecord['data_type'],
        'file_size' => $exportRecord['file_size']
    ]);
    
} catch (Exception $e) {
    logError('Export download failed', [
        'filename' => $filename,
        'user_id' => $_SESSION['user_id'],
        'error' => $e->getMessage()
    ]);
    
    http_response_code(500);
    die('Download failed');
}
?>
