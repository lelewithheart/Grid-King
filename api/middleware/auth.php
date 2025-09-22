<?php
/**
 * API Authentication Middleware
 */

function authenticateAPIRequest() {
    // Get API key from Authorization header
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
        return false;
    }
    
    $apiKey = substr($authHeader, 7); // Remove "Bearer " prefix
    
    if (empty($apiKey)) {
        return false;
    }
    
    // Verify API key in database
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("
        SELECT id, permissions, is_active, expires_at 
        FROM api_keys 
        WHERE key_hash = SHA2(:api_key, 256)
    ");
    $stmt->bindParam(':api_key', $apiKey);
    $stmt->execute();
    
    $keyData = $stmt->fetch();
    
    if (!$keyData) {
        return false;
    }
    
    // Check if key is active
    if (!$keyData['is_active']) {
        return false;
    }
    
    // Check if key has expired
    if ($keyData['expires_at'] && strtotime($keyData['expires_at']) < time()) {
        return false;
    }
    
    // Update last used timestamp
    $updateStmt = $conn->prepare("UPDATE api_keys SET last_used = NOW() WHERE id = :id");
    $updateStmt->bindParam(':id', $keyData['id']);
    $updateStmt->execute();
    
    // Store permissions for later use
    $GLOBALS['api_permissions'] = json_decode($keyData['permissions'], true) ?? [];
    
    return true;
}

function hasPermission($permission) {
    return in_array($permission, $GLOBALS['api_permissions'] ?? []);
}

function requirePermission($permission) {
    if (!hasPermission($permission)) {
        http_response_code(403);
        echo json_encode(['error' => 'Insufficient permissions']);
        exit();
    }
}
?>
