<?php
/**
 * Main Configuration File for Racing League Management System
 */

// Start session management
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Application settings
define('APP_NAME', 'Grid King');
define('APP_VERSION', '1.2.1');
define('BASE_URL', 'http://localhost');

// Security settings
define('PASSWORD_HASH_ALGO', PASSWORD_DEFAULT);
define('SESSION_TIMEOUT', 3600); // 1 hour

// File upload settings
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_MIME_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// Include database configuration
require_once 'database.php';

// Utility functions
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}


// Neue rollenbasierte Permission-Checks
function hasRole($role_code) {
    if (!isLoggedIn() || !isset($_SESSION['user_roles'])) return false;
    return in_array($role_code, $_SESSION['user_roles']);
}

function isAdmin() {
    return hasRole('admin');
}

function isDriver() {
    return hasRole('driver');
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function requireAdmin() {
    if (!isAdmin()) {
        header('Location: index.php');
        exit();
    }
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function formatDate($date) {
    return date('M j, Y g:i A', strtotime($date));
}

function calculateStandings($seasonId) {
    $db = new Database();
    $conn = $db->getConnection();
    
    $query = "
        SELECT 
            d.id,
            u.username,
            d.driver_number,
            t.name as team_name,
            SUM(rr.points) as total_points,
            COUNT(CASE WHEN rr.position = 1 THEN 1 END) as wins,
            COUNT(CASE WHEN rr.pole_position = TRUE THEN 1 END) as poles,
            COUNT(CASE WHEN rr.fastest_lap = TRUE THEN 1 END) as fastest_laps,
            COUNT(CASE WHEN rr.dnf = TRUE THEN 1 END) as dnfs,
            AVG(CASE WHEN rr.position IS NOT NULL THEN rr.position END) as avg_position
        FROM drivers d
        LEFT JOIN users u ON d.user_id = u.id
        LEFT JOIN teams t ON d.team_id = t.id
        LEFT JOIN race_results rr ON d.id = rr.driver_id
        LEFT JOIN races r ON rr.race_id = r.id
        WHERE r.season_id = :season_id OR r.id IS NULL
        GROUP BY d.id, u.username, d.driver_number, t.name
        ORDER BY total_points DESC, wins DESC, avg_position ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':season_id', $seasonId);
    $stmt->execute();
    
    return $stmt->fetchAll();
}

function sendDiscordWebhook($webhookUrl, $message) {
    if (empty($webhookUrl) || !filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
        return false;
    }
    
    $data = json_encode(["content" => $message]);
    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode >= 200 && $httpCode < 300;
}

// Additional security functions
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function validateImageUpload($file) {
    // Check if file was uploaded
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'error' => 'File upload error'];
    }
    
    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['valid' => false, 'error' => 'File too large'];
    }
    
    // Check file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, ALLOWED_MIME_TYPES)) {
        return ['valid' => false, 'error' => 'Invalid file type'];
    }
    
    // Check file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_IMAGE_TYPES)) {
        return ['valid' => false, 'error' => 'Invalid file extension'];
    }
    
    return ['valid' => true];
}

function logError($message, $context = []) {
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $message,
        'context' => $context,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    error_log(json_encode($logEntry), 3, __DIR__ . '/../logs/app.log');
}