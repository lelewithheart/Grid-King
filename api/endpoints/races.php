<?php
/**
 * Races API Endpoint
 */

requirePermission('races');

$db = new Database();
$conn = $db->getConnection();

if ($method === 'GET') {
    if (isset($segments[1]) && $segments[1] === 'upcoming') {
        // Get upcoming races
        $query = "
            SELECT 
                r.*,
                s.name as season_name,
                s.year as season_year
            FROM races r
            LEFT JOIN seasons s ON r.season_id = s.id
            WHERE r.race_date > NOW()
            ORDER BY r.race_date ASC
            LIMIT 5
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        
        $races = $stmt->fetchAll();
        
        // Add time until race for each
        foreach ($races as &$race) {
            $raceTime = strtotime($race['race_date']);
            $now = time();
            $timeDiff = $raceTime - $now;
            
            if ($timeDiff > 0) {
                $days = floor($timeDiff / 86400);
                $hours = floor(($timeDiff % 86400) / 3600);
                $minutes = floor(($timeDiff % 3600) / 60);
                
                $race['time_until'] = [
                    'days' => $days,
                    'hours' => $hours,
                    'minutes' => $minutes,
                    'total_seconds' => $timeDiff
                ];
            }
        }
        
        echo json_encode($races);
        
    } elseif (isset($segments[1]) && $segments[1] === 'recent') {
        // Get recent race results
        $query = "
            SELECT 
                r.*,
                s.name as season_name,
                s.year as season_year
            FROM races r
            LEFT JOIN seasons s ON r.season_id = s.id
            WHERE r.race_date <= NOW()
            ORDER BY r.race_date DESC
            LIMIT 5
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        
        $races = $stmt->fetchAll();
        
        // Get results for each race
        foreach ($races as &$race) {
            $resultsQuery = "
                SELECT 
                    rr.*,
                    u.username,
                    d.driver_number,
                    t.name as team_name
                FROM race_results rr
                LEFT JOIN drivers d ON rr.driver_id = d.id
                LEFT JOIN users u ON d.user_id = u.id
                LEFT JOIN teams t ON d.team_id = t.id
                WHERE rr.race_id = :race_id
                ORDER BY rr.position ASC
            ";
            
            $resultsStmt = $conn->prepare($resultsQuery);
            $resultsStmt->bindParam(':race_id', $race['id']);
            $resultsStmt->execute();
            
            $race['results'] = $resultsStmt->fetchAll();
        }
        
        echo json_encode($races);
        
    } elseif (isset($segments[1]) && is_numeric($segments[1])) {
        // Get specific race details
        $raceId = intval($segments[1]);
        
        $query = "
            SELECT 
                r.*,
                s.name as season_name,
                s.year as season_year
            FROM races r
            LEFT JOIN seasons s ON r.season_id = s.id
            WHERE r.id = :race_id
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':race_id', $raceId);
        $stmt->execute();
        
        $race = $stmt->fetch();
        
        if ($race) {
            // Get race results
            $resultsQuery = "
                SELECT 
                    rr.*,
                    u.username,
                    d.driver_number,
                    t.name as team_name
                FROM race_results rr
                LEFT JOIN drivers d ON rr.driver_id = d.id
                LEFT JOIN users u ON d.user_id = u.id
                LEFT JOIN teams t ON d.team_id = t.id
                WHERE rr.race_id = :race_id
                ORDER BY rr.position ASC
            ";
            
            $resultsStmt = $conn->prepare($resultsQuery);
            $resultsStmt->bindParam(':race_id', $raceId);
            $resultsStmt->execute();
            
            $race['results'] = $resultsStmt->fetchAll();
            
            // Get race sessions
            $sessionsQuery = "
                SELECT 
                    rs.*,
                    st.name as session_name,
                    st.code as session_code
                FROM race_sessions rs
                LEFT JOIN session_types st ON rs.session_type_id = st.id
                WHERE rs.race_id = :race_id AND rs.enabled = 1
                ORDER BY rs.session_order ASC
            ";
            
            $sessionsStmt = $conn->prepare($sessionsQuery);
            $sessionsStmt->bindParam(':race_id', $raceId);
            $sessionsStmt->execute();
            
            $race['sessions'] = $sessionsStmt->fetchAll();
            
            echo json_encode($race);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Race not found']);
        }
        
    } else {
        // Get all races for current season
        $seasonId = $_GET['season_id'] ?? null;
        if (!$seasonId) {
            $stmt = $conn->prepare("SELECT id FROM seasons WHERE is_active = 1 LIMIT 1");
            $stmt->execute();
            $season = $stmt->fetch();
            $seasonId = $season['id'] ?? 1;
        }
        
        $query = "
            SELECT 
                r.*,
                s.name as season_name,
                s.year as season_year
            FROM races r
            LEFT JOIN seasons s ON r.season_id = s.id
            WHERE r.season_id = :season_id
            ORDER BY r.race_date ASC
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':season_id', $seasonId);
        $stmt->execute();
        
        $races = $stmt->fetchAll();
        
        echo json_encode([
            'season_id' => $seasonId,
            'races' => $races
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
