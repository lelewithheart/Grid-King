<?php
/**
 * Standings API Endpoint
 * Enhanced with security and error handling
 */

requirePermission('standings');

try {
    $db = new Database();
    $conn = $db->getConnection();
} catch (Exception $e) {
    logError('Database connection failed in standings API', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
    exit();
}

// Get active season with validation
$seasonId = $_GET['season_id'] ?? null;
if ($seasonId !== null) {
    if (!is_numeric($seasonId) || intval($seasonId) <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid season ID']);
        exit();
    }
    $seasonId = intval($seasonId);
}

if (!$seasonId) {
    try {
        $stmt = $conn->prepare("SELECT id FROM seasons WHERE is_active = 1 LIMIT 1");
        $stmt->execute();
        $season = $stmt->fetch(PDO::FETCH_ASSOC);
        $seasonId = $season['id'] ?? 1;
    } catch (Exception $e) {
        logError('Error fetching active season', ['error' => $e->getMessage()]);
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error']);
        exit();
    }
}

if ($method === 'GET') {
    if (isset($segments[1]) && $segments[1] === 'driver' && isset($segments[2])) {
        // Get specific driver standings
        $driverId = intval($segments[2]);
        
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
                AVG(CASE WHEN rr.position IS NOT NULL THEN rr.position END) as avg_position,
                COUNT(rr.race_id) as races_participated
            FROM drivers d
            LEFT JOIN users u ON d.user_id = u.id
            LEFT JOIN teams t ON d.team_id = t.id
            LEFT JOIN race_results rr ON d.id = rr.driver_id
            LEFT JOIN races r ON rr.race_id = r.id
            WHERE d.id = :driver_id AND (r.season_id = :season_id OR r.id IS NULL)
            GROUP BY d.id, u.username, d.driver_number, t.name
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':driver_id', $driverId);
        $stmt->bindParam(':season_id', $seasonId);
        $stmt->execute();
        
        $driver = $stmt->fetch();
        
        if ($driver) {
            // Get position in championship
            $standings = calculateStandings($seasonId);
            $position = 1;
            foreach ($standings as $index => $standing) {
                if ($standing['id'] == $driverId) {
                    $position = $index + 1;
                    break;
                }
            }
            
            $driver['championship_position'] = $position;
            echo json_encode($driver);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Driver not found']);
        }
        
    } elseif (isset($segments[1]) && $segments[1] === 'team') {
        // Get team standings
        $teamQuery = "
            SELECT 
                t.id,
                t.name as team_name,
                SUM(rr.points) as total_points,
                COUNT(CASE WHEN rr.position = 1 THEN 1 END) as wins,
                COUNT(CASE WHEN rr.pole_position = TRUE THEN 1 END) as poles,
                COUNT(CASE WHEN rr.fastest_lap = TRUE THEN 1 END) as fastest_laps,
                COUNT(d.id) as driver_count
            FROM teams t
            LEFT JOIN drivers d ON t.id = d.team_id
            LEFT JOIN race_results rr ON d.id = rr.driver_id
            LEFT JOIN races r ON rr.race_id = r.id
            WHERE r.season_id = :season_id OR r.id IS NULL
            GROUP BY t.id, t.name
            HAVING driver_count > 0
            ORDER BY total_points DESC
        ";
        
        $stmt = $conn->prepare($teamQuery);
        $stmt->bindParam(':season_id', $seasonId);
        $stmt->execute();
        
        $teams = $stmt->fetchAll();
        
        echo json_encode([
            'season_id' => $seasonId,
            'teams' => $teams
        ]);
        
    } else {
        // Get full championship standings
        $standings = calculateStandings($seasonId);
        
        // Get season info
        $seasonStmt = $conn->prepare("SELECT name, year FROM seasons WHERE id = :season_id");
        $seasonStmt->bindParam(':season_id', $seasonId);
        $seasonStmt->execute();
        $seasonInfo = $seasonStmt->fetch();
        
        echo json_encode([
            'season' => $seasonInfo,
            'standings' => $standings
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
