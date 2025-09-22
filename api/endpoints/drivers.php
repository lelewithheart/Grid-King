<?php
/**
 * Drivers API Endpoint
 */

requirePermission('drivers');

$db = new Database();
$conn = $db->getConnection();

if ($method === 'GET') {
    if (isset($segments[1]) && is_numeric($segments[1])) {
        // Get specific driver details
        $driverId = intval($segments[1]);
        
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
        $stmt->bindParam(':driver_id', $driverId);
        $stmt->execute();
        
        $driver = $stmt->fetch();
        
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
            $statsStmt->bindParam(':driver_id', $driverId);
            $statsStmt->execute();
            
            $stats = $statsStmt->fetch();
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
            $recentStmt->bindParam(':driver_id', $driverId);
            $recentStmt->execute();
            
            $driver['recent_results'] = $recentStmt->fetchAll();
            
            echo json_encode($driver);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Driver not found']);
        }
        
    } elseif (isset($segments[1]) && $segments[1] === 'search') {
        // Search drivers by username or driver number
        $search = $_GET['q'] ?? '';
        
        if (empty($search)) {
            http_response_code(400);
            echo json_encode(['error' => 'Search query required']);
            exit();
        }
        
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
        $exactSearch = intval($search);
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':search', $searchParam);
        $stmt->bindParam(':exact_search', $exactSearch);
        $stmt->execute();
        
        $drivers = $stmt->fetchAll();
        
        echo json_encode($drivers);
        
    } else {
        // Get all drivers
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
        
        $drivers = $stmt->fetchAll();
        
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
            $statsStmt->bindParam(':driver_id', $driver['id']);
            $statsStmt->execute();
            
            $stats = $statsStmt->fetch();
            $driver['statistics'] = $stats;
        }
        
        echo json_encode($drivers);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
