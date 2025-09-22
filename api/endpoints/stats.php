<?php
/**
 * Statistics API Endpoint
 */

requirePermission('standings');

$db = new Database();
$conn = $db->getConnection();

if ($method === 'GET') {
    $statType = $segments[1] ?? '';
    
    // Get active season
    $seasonId = $_GET['season_id'] ?? null;
    if (!$seasonId) {
        $stmt = $conn->prepare("SELECT id FROM seasons WHERE is_active = 1 LIMIT 1");
        $stmt->execute();
        $season = $stmt->fetch();
        $seasonId = $season['id'] ?? 1;
    }
    
    switch ($statType) {
        case 'wins':
            $query = "
                SELECT 
                    u.username,
                    d.driver_number,
                    t.name as team_name,
                    COUNT(CASE WHEN rr.position = 1 THEN 1 END) as wins,
                    SUM(rr.points) as total_points
                FROM drivers d
                LEFT JOIN users u ON d.user_id = u.id
                LEFT JOIN teams t ON d.team_id = t.id
                LEFT JOIN race_results rr ON d.id = rr.driver_id
                LEFT JOIN races r ON rr.race_id = r.id
                WHERE r.season_id = :season_id AND u.verified = 1
                GROUP BY d.id, u.username, d.driver_number, t.name
                HAVING wins > 0
                ORDER BY wins DESC, total_points DESC
                LIMIT 10
            ";
            break;
            
        case 'poles':
            $query = "
                SELECT 
                    u.username,
                    d.driver_number,
                    t.name as team_name,
                    COUNT(CASE WHEN rr.pole_position = 1 THEN 1 END) as poles,
                    SUM(rr.points) as total_points
                FROM drivers d
                LEFT JOIN users u ON d.user_id = u.id
                LEFT JOIN teams t ON d.team_id = t.id
                LEFT JOIN race_results rr ON d.id = rr.driver_id
                LEFT JOIN races r ON rr.race_id = r.id
                WHERE r.season_id = :season_id AND u.verified = 1
                GROUP BY d.id, u.username, d.driver_number, t.name
                HAVING poles > 0
                ORDER BY poles DESC, total_points DESC
                LIMIT 10
            ";
            break;
            
        case 'fastest_laps':
            $query = "
                SELECT 
                    u.username,
                    d.driver_number,
                    t.name as team_name,
                    COUNT(CASE WHEN rr.fastest_lap = 1 THEN 1 END) as fastest_laps,
                    SUM(rr.points) as total_points
                FROM drivers d
                LEFT JOIN users u ON d.user_id = u.id
                LEFT JOIN teams t ON d.team_id = t.id
                LEFT JOIN race_results rr ON d.id = rr.driver_id
                LEFT JOIN races r ON rr.race_id = r.id
                WHERE r.season_id = :season_id AND u.verified = 1
                GROUP BY d.id, u.username, d.driver_number, t.name
                HAVING fastest_laps > 0
                ORDER BY fastest_laps DESC, total_points DESC
                LIMIT 10
            ";
            break;
            
        case 'dnf':
            $query = "
                SELECT 
                    u.username,
                    d.driver_number,
                    t.name as team_name,
                    COUNT(CASE WHEN rr.dnf = 1 THEN 1 END) as dnfs,
                    COUNT(rr.race_id) as total_races,
                    ROUND((COUNT(CASE WHEN rr.dnf = 1 THEN 1 END) / COUNT(rr.race_id)) * 100, 1) as dnf_percentage
                FROM drivers d
                LEFT JOIN users u ON d.user_id = u.id
                LEFT JOIN teams t ON d.team_id = t.id
                LEFT JOIN race_results rr ON d.id = rr.driver_id
                LEFT JOIN races r ON rr.race_id = r.id
                WHERE r.season_id = :season_id AND u.verified = 1
                GROUP BY d.id, u.username, d.driver_number, t.name
                HAVING dnfs > 0
                ORDER BY dnfs DESC, dnf_percentage DESC
                LIMIT 10
            ";
            break;
            
        case 'podiums':
            $query = "
                SELECT 
                    u.username,
                    d.driver_number,
                    t.name as team_name,
                    COUNT(CASE WHEN rr.position <= 3 AND rr.position IS NOT NULL THEN 1 END) as podiums,
                    COUNT(CASE WHEN rr.position = 1 THEN 1 END) as wins,
                    SUM(rr.points) as total_points
                FROM drivers d
                LEFT JOIN users u ON d.user_id = u.id
                LEFT JOIN teams t ON d.team_id = t.id
                LEFT JOIN race_results rr ON d.id = rr.driver_id
                LEFT JOIN races r ON rr.race_id = r.id
                WHERE r.season_id = :season_id AND u.verified = 1
                GROUP BY d.id, u.username, d.driver_number, t.name
                HAVING podiums > 0
                ORDER BY podiums DESC, wins DESC, total_points DESC
                LIMIT 10
            ";
            break;
            
        case 'points':
            $query = "
                SELECT 
                    u.username,
                    d.driver_number,
                    t.name as team_name,
                    SUM(rr.points) as total_points,
                    COUNT(rr.race_id) as races_participated,
                    ROUND(AVG(rr.points), 2) as avg_points_per_race
                FROM drivers d
                LEFT JOIN users u ON d.user_id = u.id
                LEFT JOIN teams t ON d.team_id = t.id
                LEFT JOIN race_results rr ON d.id = rr.driver_id
                LEFT JOIN races r ON rr.race_id = r.id
                WHERE r.season_id = :season_id AND u.verified = 1
                GROUP BY d.id, u.username, d.driver_number, t.name
                HAVING total_points > 0
                ORDER BY total_points DESC
                LIMIT 10
            ";
            break;
            
        case 'overview':
            // Season overview statistics
            $overviewQuery = "
                SELECT 
                    COUNT(DISTINCT r.id) as total_races,
                    COUNT(DISTINCT d.id) as total_drivers,
                    COUNT(DISTINCT t.id) as total_teams,
                    COUNT(rr.id) as total_results,
                    SUM(rr.points) as total_points_awarded
                FROM races r
                LEFT JOIN race_results rr ON r.id = rr.race_id
                LEFT JOIN drivers d ON rr.driver_id = d.id
                LEFT JOIN users u ON d.user_id = u.id
                LEFT JOIN teams t ON d.team_id = t.id
                WHERE r.season_id = :season_id AND u.verified = 1
            ";
            
            $stmt = $conn->prepare($overviewQuery);
            $stmt->bindParam(':season_id', $seasonId);
            $stmt->execute();
            
            $overview = $stmt->fetch();
            
            // Get most successful driver
            $topDriverQuery = "
                SELECT 
                    u.username,
                    SUM(rr.points) as total_points
                FROM drivers d
                LEFT JOIN users u ON d.user_id = u.id
                LEFT JOIN race_results rr ON d.id = rr.driver_id
                LEFT JOIN races r ON rr.race_id = r.id
                WHERE r.season_id = :season_id AND u.verified = 1
                GROUP BY d.id, u.username
                ORDER BY total_points DESC
                LIMIT 1
            ";
            
            $topDriverStmt = $conn->prepare($topDriverQuery);
            $topDriverStmt->bindParam(':season_id', $seasonId);
            $topDriverStmt->execute();
            
            $topDriver = $topDriverStmt->fetch();
            $overview['leading_driver'] = $topDriver;
            
            echo json_encode($overview);
            return;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid statistics type']);
            return;
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':season_id', $seasonId);
    $stmt->execute();
    
    $results = $stmt->fetchAll();
    
    echo json_encode([
        'type' => $statType,
        'season_id' => $seasonId,
        'data' => $results
    ]);
    
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
