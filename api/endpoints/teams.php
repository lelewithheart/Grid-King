<?php
/**
 * Teams API Endpoint
 */

requirePermission('teams');

$db = new Database();
$conn = $db->getConnection();

if ($method === 'GET') {
    if (isset($segments[1]) && is_numeric($segments[1])) {
        // Get specific team details
        $teamId = intval($segments[1]);
        
        $query = "
            SELECT 
                t.*,
                u.username as created_by_username
            FROM teams t
            LEFT JOIN users u ON t.created_by = u.id
            WHERE t.id = :team_id
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':team_id', $teamId);
        $stmt->execute();
        
        $team = $stmt->fetch();
        
        if ($team) {
            // Get team drivers
            $driversQuery = "
                SELECT 
                    d.*,
                    u.username
                FROM drivers d
                LEFT JOIN users u ON d.user_id = u.id
                WHERE d.team_id = :team_id AND u.verified = 1
                ORDER BY u.username ASC
            ";
            
            $driversStmt = $conn->prepare($driversQuery);
            $driversStmt->bindParam(':team_id', $teamId);
            $driversStmt->execute();
            
            $team['drivers'] = $driversStmt->fetchAll();
            
            // Get team statistics
            $statsQuery = "
                SELECT 
                    COUNT(DISTINCT rr.race_id) as races_participated,
                    COUNT(CASE WHEN rr.position = 1 THEN 1 END) as wins,
                    COUNT(CASE WHEN rr.position <= 3 THEN 1 END) as podiums,
                    COUNT(CASE WHEN rr.pole_position = 1 THEN 1 END) as poles,
                    COUNT(CASE WHEN rr.fastest_lap = 1 THEN 1 END) as fastest_laps,
                    SUM(rr.points) as total_points
                FROM race_results rr
                LEFT JOIN drivers d ON rr.driver_id = d.id
                LEFT JOIN races r ON rr.race_id = r.id
                LEFT JOIN seasons s ON r.season_id = s.id
                WHERE d.team_id = :team_id AND s.is_active = 1
            ";
            
            $statsStmt = $conn->prepare($statsQuery);
            $statsStmt->bindParam(':team_id', $teamId);
            $statsStmt->execute();
            
            $stats = $statsStmt->fetch();
            $team['statistics'] = $stats;
            
            // Get recent results
            $recentQuery = "
                SELECT 
                    rr.*,
                    r.name as race_name,
                    r.track,
                    r.race_date,
                    u.username
                FROM race_results rr
                LEFT JOIN drivers d ON rr.driver_id = d.id
                LEFT JOIN users u ON d.user_id = u.id
                LEFT JOIN races r ON rr.race_id = r.id
                LEFT JOIN seasons s ON r.season_id = s.id
                WHERE d.team_id = :team_id AND s.is_active = 1
                ORDER BY r.race_date DESC, rr.position ASC
                LIMIT 10
            ";
            
            $recentStmt = $conn->prepare($recentQuery);
            $recentStmt->bindParam(':team_id', $teamId);
            $recentStmt->execute();
            
            $team['recent_results'] = $recentStmt->fetchAll();
            
            echo json_encode($team);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Team not found']);
        }
        
    } elseif (isset($segments[1]) && $segments[1] === 'search') {
        // Search teams by name
        $search = $_GET['q'] ?? '';
        
        if (empty($search)) {
            http_response_code(400);
            echo json_encode(['error' => 'Search query required']);
            exit();
        }
        
        $query = "
            SELECT 
                t.*,
                COUNT(d.id) as driver_count
            FROM teams t
            LEFT JOIN drivers d ON t.id = d.team_id
            LEFT JOIN users u ON d.user_id = u.id
            WHERE t.name LIKE :search AND (u.verified = 1 OR u.verified IS NULL)
            GROUP BY t.id, t.name, t.logo, t.created_by, t.created_at
            ORDER BY t.name ASC
            LIMIT 10
        ";
        
        $searchParam = '%' . $search . '%';
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':search', $searchParam);
        $stmt->execute();
        
        $teams = $stmt->fetchAll();
        
        echo json_encode($teams);
        
    } else {
        // Get all teams
        $query = "
            SELECT 
                t.*,
                COUNT(d.id) as driver_count,
                u.username as created_by_username
            FROM teams t
            LEFT JOIN drivers d ON t.id = d.team_id
            LEFT JOIN users du ON d.user_id = du.id AND du.verified = 1
            LEFT JOIN users u ON t.created_by = u.id
            GROUP BY t.id, t.name, t.logo, t.created_by, t.created_at, u.username
            ORDER BY t.name ASC
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        
        $teams = $stmt->fetchAll();
        
        // Add statistics for each team
        foreach ($teams as &$team) {
            $statsQuery = "
                SELECT 
                    COUNT(CASE WHEN rr.position = 1 THEN 1 END) as wins,
                    SUM(rr.points) as total_points
                FROM race_results rr
                LEFT JOIN drivers d ON rr.driver_id = d.id
                LEFT JOIN races r ON rr.race_id = r.id
                LEFT JOIN seasons s ON r.season_id = s.id
                WHERE d.team_id = :team_id AND s.is_active = 1
            ";
            
            $statsStmt = $conn->prepare($statsQuery);
            $statsStmt->bindParam(':team_id', $team['id']);
            $statsStmt->execute();
            
            $stats = $statsStmt->fetch();
            $team['statistics'] = $stats;
        }
        
        echo json_encode($teams);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
