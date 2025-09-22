<?php
/**
 * API Router for Grid King League Management
 * Handles REST API requests from Discord Bot and other integrations
 */

require_once '../config/config.php';
require_once 'middleware/auth.php';
require_once 'middleware/cors.php';

// Enable CORS for API requests
handleCORS();

// Set JSON content type
header('Content-Type: application/json');

// Parse the request
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/api', '', $path); // Remove /api prefix
$segments = explode('/', trim($path, '/'));

// Authenticate API request
if (!authenticateAPIRequest()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    // Route to appropriate endpoint
    switch ($segments[0]) {
        case 'standings':
            require_once 'endpoints/standings.php';
            break;
            
        case 'races':
            require_once 'endpoints/races.php';
            break;
            
        case 'drivers':
            require_once 'endpoints/drivers.php';
            break;
            
        case 'teams':
            require_once 'endpoints/teams.php';
            break;
            
        case 'stats':
            require_once 'endpoints/stats.php';
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error', 'message' => $e->getMessage()]);
}
?>
