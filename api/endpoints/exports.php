<?php
/**
 * Export API Endpoint
 * Programmatic data export for external tools and integrations
 */

requirePermission('export');

try {
    // Load export manager
    require_once '../utils/ExportManager.php';
    $exportManager = new ExportManager($currentUserId);
    
    if ($method === 'POST') {
        // Create new export
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON input']);
            exit();
        }
        
        // Validate required parameters
        $dataType = $input['data_type'] ?? '';
        $exportType = $input['export_type'] ?? 'csv';
        
        if (!in_array($dataType, ['results', 'standings', 'penalties'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid data_type. Must be: results, standings, or penalties']);
            exit();
        }
        
        if (!in_array($exportType, ['csv', 'json'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid export_type. Must be: csv or json']);
            exit();
        }
        
        // Extract filters and parameters
        $seasonId = isset($input['season_id']) && is_numeric($input['season_id']) ? intval($input['season_id']) : null;
        $raceId = isset($input['race_id']) && is_numeric($input['race_id']) ? intval($input['race_id']) : null;
        
        $filters = [];
        if (!empty($input['filters']) && is_array($input['filters'])) {
            $allowedFilters = ['date_from', 'date_to', 'severity'];
            foreach ($allowedFilters as $filter) {
                if (isset($input['filters'][$filter])) {
                    $filters[$filter] = sanitizeInput($input['filters'][$filter]);
                }
            }
        }
        
        // Perform export
        try {
            switch ($dataType) {
                case 'results':
                    $result = $exportManager->exportRaceResults($raceId, $seasonId, $filters);
                    break;
                case 'standings':
                    $result = $exportManager->exportStandings($seasonId, $filters);
                    break;
                case 'penalties':
                    $result = $exportManager->exportPenalties($seasonId, $raceId, $filters);
                    break;
            }
            
            // Return export information
            echo json_encode([
                'success' => true,
                'export_id' => $result['filename'],
                'filename' => $result['filename'],
                'record_count' => $result['record_count'],
                'download_url' => $result['download_url'],
                'created_at' => date('c'),
                'expires_at' => date('c', strtotime('+30 days'))
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        
    } elseif ($method === 'GET') {
        // Get export history or download specific export
        if (isset($segments[1]) && $segments[1] === 'download' && isset($segments[2])) {
            // Download specific export file
            $filename = basename($segments[2]);
            
            try {
                $db = new Database();
                $conn = $db->getConnection();
                
                $stmt = $conn->prepare("
                    SELECT file_path, export_type, file_size 
                    FROM export_logs 
                    WHERE filename = :filename 
                    AND user_id = :user_id
                    AND (expires_at IS NULL OR expires_at > NOW())
                ");
                
                $stmt->bindParam(':filename', $filename);
                $stmt->bindParam(':user_id', $currentUserId, PDO::PARAM_INT);
                $stmt->execute();
                
                $exportRecord = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$exportRecord || !file_exists($exportRecord['file_path'])) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Export file not found or expired']);
                    exit();
                }
                
                // Update download count
                $updateStmt = $conn->prepare("
                    UPDATE export_logs 
                    SET downloaded_count = downloaded_count + 1, last_downloaded_at = NOW()
                    WHERE filename = :filename AND user_id = :user_id
                ");
                $updateStmt->bindParam(':filename', $filename);
                $updateStmt->bindParam(':user_id', $currentUserId, PDO::PARAM_INT);
                $updateStmt->execute();
                
                // Set appropriate headers
                $extension = pathinfo($filename, PATHINFO_EXTENSION);
                switch (strtolower($extension)) {
                    case 'csv':
                        header('Content-Type: text/csv');
                        break;
                    case 'json':
                        header('Content-Type: application/json');
                        break;
                    default:
                        header('Content-Type: application/octet-stream');
                }
                
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Length: ' . $exportRecord['file_size']);
                
                readfile($exportRecord['file_path']);
                exit();
                
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Download failed']);
            }
            
        } else {
            // Get export history
            $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? min(intval($_GET['limit']), 100) : 20;
            $offset = isset($_GET['offset']) && is_numeric($_GET['offset']) ? intval($_GET['offset']) : 0;
            
            try {
                $db = new Database();
                $conn = $db->getConnection();
                
                $stmt = $conn->prepare("
                    SELECT 
                        el.filename,
                        el.export_type,
                        el.data_type,
                        el.record_count,
                        el.file_size,
                        el.downloaded_count,
                        el.created_at,
                        el.expires_at,
                        s.name as season_name,
                        r.name as race_name
                    FROM export_logs el
                    LEFT JOIN seasons s ON el.season_id = s.id
                    LEFT JOIN races r ON el.race_id = r.id
                    WHERE el.user_id = :user_id
                    ORDER BY el.created_at DESC
                    LIMIT :limit OFFSET :offset
                ");
                
                $stmt->bindParam(':user_id', $currentUserId, PDO::PARAM_INT);
                $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
                
                $exports = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Get total count
                $countStmt = $conn->prepare("
                    SELECT COUNT(*) as total
                    FROM export_logs 
                    WHERE user_id = :user_id
                ");
                $countStmt->bindParam(':user_id', $currentUserId, PDO::PARAM_INT);
                $countStmt->execute();
                $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
                
                echo json_encode([
                    'exports' => $exports,
                    'pagination' => [
                        'total' => intval($totalCount),
                        'limit' => $limit,
                        'offset' => $offset,
                        'has_more' => ($offset + $limit) < $totalCount
                    ]
                ]);
                
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to retrieve export history']);
            }
        }
        
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    logError('Export API error', [
        'user_id' => $currentUserId ?? null,
        'method' => $method,
        'error' => $e->getMessage()
    ]);
    
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
