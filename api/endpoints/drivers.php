<?php
/**
 * Drivers API Endpoint
 * Enhanced with security and error handling
 */

requirePermission('drivers');

try {
    $db = new Database();
    $conn = $db->getConnection();
} catch (Exception $e) {
    logError('Database connection failed in drivers API', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
    exit();
}

if ($method === 'GET') {
    if (isset($segments[1]) && is_numeric($segments[1])) {
        // Get specific driver details
        $driverId = intval($segments[1]);
        
        // Validate driver ID
        if ($driverId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid driver ID']);
            exit();
        }
        try {
            $query = "
                SELECT 
                    d.*,
                    u.username,
                    u.email,
                    t.name as team_name,
                    t.logo as team_logo
                FROM drivers d
                LEFT JOIN users u ON d.user_id = u.id
                LEFT JOIN teams t ON d.team_id = t.id
                WHERE d.id = :driver_id AND u.verified = 1
            ";
            
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':driver_id', $driverId, PDO::PARAM_INT);
            $stmt->execute();
            
            $driver = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($driver) {
                // Get driver statistics
                $statsQuery = "
                    SELECT 
                        COUNT(*) as races_participated,
                        COUNT(CASE WHEN position = 1 THEN 1 END) as wins,
                        COUNT(CASE WHEN position <= 3 THEN 1 END) as podiums,
                        COUNT(CASE WHEN pole_position = 1 THEN 1 END) as poles,
                        COUNT(CASE WHEN fastest_lap = 1 THEN 1 END) as fastest_laps,
                        COUNT(CASE WHEN dnf = 1 THEN 1 END) as dnfs,
                        SUM(points) as total_points,
                        AVG(CASE WHEN position IS NOT NULL THEN position END) as avg_position,
                        MIN(CASE WHEN position IS NOT NULL THEN position END) as best_position
                    FROM race_results rr
                    LEFT JOIN races r ON rr.race_id = r.id
                    LEFT JOIN seasons s ON r.season_id = s.id
                    WHERE rr.driver_id = :driver_id AND s.is_active = 1
                ";
                
                $statsStmt = $conn->prepare($statsQuery);
                $statsStmt->bindParam(':driver_id', $driverId, PDO::PARAM_INT);
                $statsStmt->execute();
                
                $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
                $driver['statistics'] = $stats;
                
                // Get recent race results
                $recentQuery = "
                    SELECT 
                        rr.*,
                        r.name as race_name,
                        r.track,
                        r.race_date
                    FROM race_results rr
                    LEFT JOIN races r ON rr.race_id = r.id
                    LEFT JOIN seasons s ON r.season_id = s.id
                    WHERE rr.driver_id = :driver_id AND s.is_active = 1
                    ORDER BY r.race_date DESC
                    LIMIT 5
                ";
                
                $recentStmt = $conn->prepare($recentQuery);
                $recentStmt->bindParam(':driver_id', $driverId, PDO::PARAM_INT);
                $recentStmt->execute();
                
                $driver['recent_results'] = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode($driver);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Driver not found']);
            }
        } catch (Exception $e) {
            logError('Error fetching driver details', ['driver_id' => $driverId, 'error' => $e->getMessage()]);
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
        
    } elseif (isset($segments[1]) && $segments[1] === 'search') {
        // Search drivers by username or driver number
        $search = $_GET['q'] ?? '';
        
        if (empty($search) || strlen($search) < 2) {
            http_response_code(400);
            echo json_encode(['error' => 'Search query must be at least 2 characters']);
            exit();
        }
        
        // Sanitize search input
        $search = htmlspecialchars(trim($search), ENT_QUOTES, 'UTF-8');
        if (strlen($search) > 50) {
            http_response_code(400);
            echo json_encode(['error' => 'Search query too long']);
            exit();
        }
        
        try {
            $query = "
                SELECT 
                    d.*,
                    u.username,
                    t.name as team_name
                FROM drivers d
                LEFT JOIN users u ON d.user_id = u.id
                LEFT JOIN teams t ON d.team_id = t.id
                WHERE u.verified = 1 AND (
                    u.username LIKE :search 
                    OR d.driver_number = :exact_search
                )
                ORDER BY u.username ASC
                LIMIT 10
            ";
            
            $searchParam = '%' . $search . '%';
            $exactSearch = is_numeric($search) ? intval($search) : 0;
            
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
            $stmt->bindParam(':exact_search', $exactSearch, PDO::PARAM_INT);
            $stmt->execute();
            
            $drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode($drivers);
        } catch (Exception $e) {
            logError('Error searching drivers', ['search' => $search, 'error' => $e->getMessage()]);
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
        
    } else {
        // Get all drivers
        try {
            $query = "
                SELECT 
                    d.*,
                    u.username,
                    t.name as team_name,
                    t.logo as team_logo
                FROM drivers d
                LEFT JOIN users u ON d.user_id = u.id
                LEFT JOIN teams t ON d.team_id = t.id
                WHERE u.verified = 1
                ORDER BY u.username ASC
            ";
            
            $stmt = $conn->prepare($query);
            $stmt->execute();
            
            $drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Add basic statistics for each driver
            foreach ($drivers as &$driver) {
                $statsQuery = "
                    SELECT 
                        COUNT(*) as races_participated,
                        COUNT(CASE WHEN position = 1 THEN 1 END) as wins,
                        SUM(points) as total_points
                    FROM race_results rr
                    LEFT JOIN races r ON rr.race_id = r.id
                    LEFT JOIN seasons s ON r.season_id = s.id
                    WHERE rr.driver_id = :driver_id AND s.is_active = 1
                ";
                
                $statsStmt = $conn->prepare($statsQuery);
                $statsStmt->bindParam(':driver_id', $driver['id'], PDO::PARAM_INT);
                $statsStmt->execute();
                
                $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
                $driver['statistics'] = $stats;
            }
            
            echo json_encode($drivers);
        } catch (Exception $e) {
            logError('Error fetching all drivers', ['error' => $e->getMessage()]);
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
