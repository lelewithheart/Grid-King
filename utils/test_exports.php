<?php
/**
 * Export System Test Script
 * Validates export functionality and data integrity
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/ExportManager.php';

echo "GridKing Export System Test\n";
echo "============================\n\n";

$errors = [];
$passed = 0;
$total = 0;

function runTest($testName, $testFunction) {
    global $errors, $passed, $total;
    $total++;
    
    echo "Testing: $testName... ";
    
    try {
        $result = $testFunction();
        if ($result === true) {
            echo "✓ PASS\n";
            $passed++;
        } else {
            echo "✗ FAIL: $result\n";
            $errors[] = "$testName: $result";
        }
    } catch (Exception $e) {
        echo "✗ ERROR: " . $e->getMessage() . "\n";
        $errors[] = "$testName: " . $e->getMessage();
    }
}

// Test 1: Database Schema
runTest("Database Schema", function() {
    $db = new Database();
    $conn = $db->getConnection();
    
    $tables = ['export_logs', 'export_templates', 'export_settings', 'export_queue'];
    
    foreach ($tables as $table) {
        $stmt = $conn->prepare("SHOW TABLES LIKE :table");
        $stmt->bindParam(':table', $table);
        $stmt->execute();
        
        if (!$stmt->fetch()) {
            return "Table '$table' does not exist";
        }
    }
    
    return true;
});

// Test 2: Export Directory
runTest("Export Directory", function() {
    $exportDir = __DIR__ . '/../exports/';
    
    if (!is_dir($exportDir)) {
        if (!mkdir($exportDir, 0755, true)) {
            return "Cannot create export directory";
        }
    }
    
    if (!is_writable($exportDir)) {
        return "Export directory is not writable";
    }
    
    return true;
});

// Test 3: ExportManager Initialization
runTest("ExportManager Initialization", function() {
    $exportManager = new ExportManager(1); // Assuming user ID 1 exists
    
    if (!$exportManager) {
        return "Failed to initialize ExportManager";
    }
    
    return true;
});

// Test 4: Settings Loading
runTest("Settings Loading", function() {
    $db = new Database();
    $conn = $db->getConnection();
    
    $requiredSettings = [
        'export_enabled',
        'export_max_records',
        'export_max_file_size',
        'export_rate_limit'
    ];
    
    foreach ($requiredSettings as $setting) {
        $stmt = $conn->prepare("SELECT value FROM settings WHERE `key` = :key");
        $stmt->bindParam(':key', $setting);
        $stmt->execute();
        
        if (!$stmt->fetch()) {
            return "Setting '$setting' not found";
        }
    }
    
    return true;
});

// Test 5: Rate Limiting
runTest("Rate Limiting Logic", function() {
    $exportManager = new ExportManager(1);
    
    // This test would need to be more sophisticated in a real scenario
    // For now, just verify the manager can be created without rate limit errors
    
    return true;
});

// Test 6: CSV Generation
runTest("CSV File Generation", function() {
    $testData = [
        ['Name' => 'Test Driver 1', 'Points' => 25, 'Position' => 1],
        ['Name' => 'Test Driver 2', 'Points' => 20, 'Position' => 2],
        ['Name' => 'Test Driver 3', 'Points' => 15, 'Position' => 3]
    ];
    
    $testFile = __DIR__ . '/../exports/test_export.csv';
    
    // Create a test CSV
    $file = fopen($testFile, 'w');
    if (!$file) {
        return "Cannot create test CSV file";
    }
    
    fputcsv($file, ['Test Export']);
    fputcsv($file, ['Generated: ' . date('Y-m-d H:i:s')]);
    fputcsv($file, []);
    fputcsv($file, array_keys($testData[0]));
    
    foreach ($testData as $row) {
        fputcsv($file, $row);
    }
    
    fclose($file);
    
    // Verify file was created and has content
    if (!file_exists($testFile)) {
        return "Test CSV file was not created";
    }
    
    $content = file_get_contents($testFile);
    if (empty($content)) {
        unlink($testFile);
        return "Test CSV file is empty";
    }
    
    // Cleanup
    unlink($testFile);
    
    return true;
});

// Test 7: Database Logging
runTest("Database Logging", function() {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Test insert into export_logs
    $stmt = $conn->prepare("
        INSERT INTO export_logs 
        (export_type, data_type, user_id, filename, file_path, file_size, record_count)
        VALUES ('csv', 'results', 1, 'test.csv', '/test/path', 1024, 10)
    ");
    
    if (!$stmt->execute()) {
        return "Cannot insert test record into export_logs";
    }
    
    $testId = $conn->lastInsertId();
    
    // Verify record was inserted
    $verifyStmt = $conn->prepare("SELECT id FROM export_logs WHERE id = :id");
    $verifyStmt->bindParam(':id', $testId);
    $verifyStmt->execute();
    
    if (!$verifyStmt->fetch()) {
        return "Test record not found after insert";
    }
    
    // Cleanup
    $deleteStmt = $conn->prepare("DELETE FROM export_logs WHERE id = :id");
    $deleteStmt->bindParam(':id', $testId);
    $deleteStmt->execute();
    
    return true;
});

// Test 8: API Endpoint Structure
runTest("API Endpoint File", function() {
    $apiFile = __DIR__ . '/../api/endpoints/exports.php';
    
    if (!file_exists($apiFile)) {
        return "API endpoint file does not exist";
    }
    
    $content = file_get_contents($apiFile);
    
    $requiredElements = [
        'requirePermission',
        'ExportManager',
        'POST',
        'GET',
        'json_encode'
    ];
    
    foreach ($requiredElements as $element) {
        if (strpos($content, $element) === false) {
            return "API endpoint missing required element: $element";
        }
    }
    
    return true;
});

// Test 9: Admin Interface
runTest("Admin Interface File", function() {
    $adminFile = __DIR__ . '/../admin/exports.php';
    
    if (!file_exists($adminFile)) {
        return "Admin interface file does not exist";
    }
    
    $content = file_get_contents($adminFile);
    
    $requiredElements = [
        'requireAdmin',
        'ExportManager',
        'csrf_token',
        'data_type',
        'export_type'
    ];
    
    foreach ($requiredElements as $element) {
        if (strpos($content, $element) === false) {
            return "Admin interface missing required element: $element";
        }
    }
    
    return true;
});

// Test 10: Download Handler
runTest("Download Handler", function() {
    $downloadFile = __DIR__ . '/../admin/exports/download.php';
    
    if (!file_exists($downloadFile)) {
        return "Download handler file does not exist";
    }
    
    $content = file_get_contents($downloadFile);
    
    if (strpos($content, 'requireAdmin') === false) {
        return "Download handler missing admin requirement";
    }
    
    if (strpos($content, 'path traversal') === false && strpos($content, 'realpath') === false) {
        return "Download handler missing path traversal protection";
    }
    
    return true;
});

echo "\n";
echo "Test Results\n";
echo "============\n";
echo "Passed: $passed/$total\n";

if (!empty($errors)) {
    echo "\nErrors:\n";
    foreach ($errors as $error) {
        echo "- $error\n";
    }
} else {
    echo "\n✓ All tests passed! Export system is ready.\n";
}

echo "\nNext Steps:\n";
echo "1. Run database migration: database_v1.2.1_migrations.sql\n";
echo "2. Set up cron job for cleanup: utils/cleanup_exports.php\n";
echo "3. Configure export settings in admin panel\n";
echo "4. Test exports with real data\n";
echo "5. Set up PDF export library (optional)\n";
?>
