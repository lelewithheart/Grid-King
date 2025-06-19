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
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost');

// Security settings
define('PASSWORD_HASH_ALGO', PASSWORD_DEFAULT);
define('SESSION_TIMEOUT', 3600); // 1 hour

// File upload settings
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);

// Include database configuration
require_once 'database.php';

// Utility functions
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

function isAdmin() {
    return isLoggedIn() && $_SESSION['role'] === 'admin';
}

function isDriver() {
    return isLoggedIn() && $_SESSION['role'] === 'driver';
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