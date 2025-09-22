#!/usr/bin/env php
<?php
/**
 * Export Cleanup Cron Job
 * Automatically cleans up expired export files
 * 
 * Usage: Run daily via cron
 * 0 2 * * * /usr/bin/php /path/to/GridKing/utils/cleanup_exports.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/ExportManager.php';

echo "Starting export cleanup at " . date('Y-m-d H:i:s') . "\n";

try {
    // Clean up expired exports
    ExportManager::cleanupExpiredExports();
    
    // Additional cleanup: Remove orphaned files in export directory
    $exportDir = __DIR__ . '/../exports/';
    $db = new Database();
    $conn = $db->getConnection();
    
    if (is_dir($exportDir)) {
        $files = glob($exportDir . '*');
        $deletedOrphans = 0;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $filename = basename($file);
                
                // Check if file exists in database
                $stmt = $conn->prepare("SELECT id FROM export_logs WHERE filename = :filename");
                $stmt->bindParam(':filename', $filename);
                $stmt->execute();
                
                if (!$stmt->fetch()) {
                    // File not in database, remove it
                    unlink($file);
                    $deletedOrphans++;
                    echo "Deleted orphaned file: $filename\n";
                }
            }
        }
        
        if ($deletedOrphans > 0) {
            echo "Deleted $deletedOrphans orphaned files\n";
        }
    }
    
    // Clean up old export queue entries
    $stmt = $conn->prepare("
        DELETE FROM export_queue 
        WHERE status IN ('completed', 'failed') 
        AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute();
    $deletedQueueEntries = $stmt->rowCount();
    
    if ($deletedQueueEntries > 0) {
        echo "Deleted $deletedQueueEntries old queue entries\n";
    }
    
    echo "Export cleanup completed successfully at " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    echo "Export cleanup failed: " . $e->getMessage() . "\n";
    logError('Export cleanup cron failed', ['error' => $e->getMessage()]);
    exit(1);
}
?>
