<?php
/**
 * Export Utilities Class
 * Comprehensive export system for CSV, PDF, and JSON formats
 * 
 * Features:
 * - Multi-format export (CSV, PDF, JSON)
 * - Template-based PDF generation
 * - Rate limiting and security
 * - Version tracking and audit trails
 * - Background processing for large exports
 */

require_once 'config/config.php';

class ExportManager {
    private $db;
    private $userId;
    private $settings;
    private $exportDir;
    private $maxRecords;
    private $maxFileSize;
    private $rateLimit;

    public function __construct($userId = null) {
        $this->db = new Database();
        $this->userId = $userId;
        $this->exportDir = __DIR__ . '/exports/';
        $this->loadSettings();
        
        // Create export directory if it doesn't exist
        if (!is_dir($this->exportDir)) {
            mkdir($this->exportDir, 0755, true);
        }
    }

    private function loadSettings() {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("
                SELECT `key`, `value` FROM settings 
                WHERE `key` IN ('export_max_records', 'export_max_file_size', 'export_rate_limit', 'export_enabled')
            ");
            $stmt->execute();
            $this->settings = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $this->settings[$row['key']] = $row['value'];
            }
            
            $this->maxRecords = intval($this->settings['export_max_records'] ?? 10000);
            $this->maxFileSize = intval($this->settings['export_max_file_size'] ?? 50) * 1024 * 1024; // Convert to bytes
            $this->rateLimit = intval($this->settings['export_rate_limit'] ?? 5);
            
        } catch (Exception $e) {
            logError('Failed to load export settings', ['error' => $e->getMessage()]);
            $this->settings = [];
            $this->maxRecords = 10000;
            $this->maxFileSize = 50 * 1024 * 1024;
            $this->rateLimit = 5;
        }
    }

    /**
     * Check if exports are enabled and user has permission
     */
    private function validateExportPermission() {
        if (!isset($this->settings['export_enabled']) || $this->settings['export_enabled'] !== '1') {
            throw new Exception('Export functionality is disabled');
        }

        if (!$this->userId) {
            throw new Exception('User authentication required for exports');
        }

        // Check rate limiting
        if (!$this->checkRateLimit()) {
            throw new Exception('Export rate limit exceeded. Please try again later.');
        }

        return true;
    }

    /**
     * Check if user has exceeded export rate limit
     */
    private function checkRateLimit() {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("
                SELECT COUNT(*) as export_count 
                FROM export_logs 
                WHERE user_id = :user_id 
                AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->bindParam(':user_id', $this->userId, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return ($result['export_count'] ?? 0) < $this->rateLimit;
        } catch (Exception $e) {
            logError('Rate limit check failed', ['user_id' => $this->userId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Export race results to CSV
     */
    public function exportRaceResults($raceId = null, $seasonId = null, $filters = []) {
        $this->validateExportPermission();
        
        try {
            $conn = $this->db->getConnection();
            
            // Build query with filters
            $whereConditions = ['1=1'];
            $params = [];
            
            if ($raceId) {
                $whereConditions[] = 'r.id = :race_id';
                $params[':race_id'] = $raceId;
            }
            
            if ($seasonId) {
                $whereConditions[] = 'r.season_id = :season_id';
                $params[':season_id'] = $seasonId;
            }
            
            if (!empty($filters['date_from'])) {
                $whereConditions[] = 'r.race_date >= :date_from';
                $params[':date_from'] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $whereConditions[] = 'r.race_date <= :date_to';
                $params[':date_to'] = $filters['date_to'];
            }

            $query = "
                SELECT 
                    s.name as season_name,
                    r.name as race_name,
                    r.track,
                    r.race_date,
                    u.username,
                    d.driver_number,
                    t.name as team_name,
                    rr.position,
                    rr.points,
                    rr.fastest_lap,
                    rr.fastest_lap_time,
                    rr.pole_position,
                    rr.dnf,
                    rr.dnf_reason,
                    rr.penalties_applied,
                    rr.created_at as result_recorded_at
                FROM race_results rr
                JOIN races r ON rr.race_id = r.id
                JOIN seasons s ON r.season_id = s.id
                JOIN drivers d ON rr.driver_id = d.id
                JOIN users u ON d.user_id = u.id
                LEFT JOIN teams t ON d.team_id = t.id
                WHERE " . implode(' AND ', $whereConditions) . "
                ORDER BY r.race_date DESC, rr.position ASC
                LIMIT :max_records
            ";
            
            $stmt = $conn->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':max_records', $this->maxRecords, PDO::PARAM_INT);
            $stmt->execute();
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($results)) {
                throw new Exception('No results found for the specified criteria');
            }
            
            // Generate CSV
            $filename = 'race_results_' . date('Y-m-d_H-i-s') . '.csv';
            $filepath = $this->exportDir . $filename;
            
            $this->generateCSV($results, $filepath, 'Race Results Export');
            
            // Log export
            $this->logExport('csv', 'results', $filename, $filepath, count($results), $filters, $seasonId, $raceId);
            
            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'record_count' => count($results),
                'download_url' => '/admin/exports/download.php?file=' . urlencode($filename)
            ];
            
        } catch (Exception $e) {
            logError('Race results export failed', [
                'user_id' => $this->userId,
                'race_id' => $raceId,
                'season_id' => $seasonId,
                'error' => $e->getMessage()
            ]);
            throw new Exception('Export failed: ' . $e->getMessage());
        }
    }

    /**
     * Export championship standings to CSV
     */
    public function exportStandings($seasonId = null, $filters = []) {
        $this->validateExportPermission();
        
        try {
            $conn = $this->db->getConnection();
            
            // Get active season if not specified
            if (!$seasonId) {
                $stmt = $conn->prepare("SELECT id FROM seasons WHERE is_active = 1 LIMIT 1");
                $stmt->execute();
                $season = $stmt->fetch(PDO::FETCH_ASSOC);
                $seasonId = $season['id'] ?? 1;
            }
            
            $query = "
                SELECT 
                    s.name as season_name,
                    u.username,
                    d.driver_number,
                    t.name as team_name,
                    SUM(rr.points) as total_points,
                    COUNT(rr.race_id) as races_participated,
                    COUNT(CASE WHEN rr.position = 1 THEN 1 END) as wins,
                    COUNT(CASE WHEN rr.position <= 3 THEN 1 END) as podiums,
                    COUNT(CASE WHEN rr.pole_position = 1 THEN 1 END) as poles,
                    COUNT(CASE WHEN rr.fastest_lap = 1 THEN 1 END) as fastest_laps,
                    COUNT(CASE WHEN rr.dnf = 1 THEN 1 END) as dnfs,
                    AVG(CASE WHEN rr.position IS NOT NULL THEN rr.position END) as avg_position,
                    MIN(CASE WHEN rr.position IS NOT NULL THEN rr.position END) as best_position,
                    MAX(r.race_date) as last_race_date
                FROM drivers d
                JOIN users u ON d.user_id = u.id
                LEFT JOIN teams t ON d.team_id = t.id
                LEFT JOIN race_results rr ON d.id = rr.driver_id
                LEFT JOIN races r ON rr.race_id = r.id
                JOIN seasons s ON r.season_id = s.id
                WHERE s.id = :season_id AND u.verified = 1
                GROUP BY d.id, u.username, d.driver_number, t.name, s.name
                ORDER BY total_points DESC, wins DESC, podiums DESC
                LIMIT :max_records
            ";
            
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':season_id', $seasonId, PDO::PARAM_INT);
            $stmt->bindValue(':max_records', $this->maxRecords, PDO::PARAM_INT);
            $stmt->execute();
            
            $standings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($standings)) {
                throw new Exception('No standings data found for the specified season');
            }
            
            // Add position numbers
            foreach ($standings as $index => &$driver) {
                $driver['championship_position'] = $index + 1;
            }
            
            // Generate CSV
            $filename = 'standings_season_' . $seasonId . '_' . date('Y-m-d_H-i-s') . '.csv';
            $filepath = $this->exportDir . $filename;
            
            $this->generateCSV($standings, $filepath, 'Championship Standings Export');
            
            // Log export
            $this->logExport('csv', 'standings', $filename, $filepath, count($standings), $filters, $seasonId);
            
            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'record_count' => count($standings),
                'download_url' => '/admin/exports/download.php?file=' . urlencode($filename)
            ];
            
        } catch (Exception $e) {
            logError('Standings export failed', [
                'user_id' => $this->userId,
                'season_id' => $seasonId,
                'error' => $e->getMessage()
            ]);
            throw new Exception('Export failed: ' . $e->getMessage());
        }
    }

    /**
     * Export penalties to CSV
     */
    public function exportPenalties($seasonId = null, $raceId = null, $filters = []) {
        $this->validateExportPermission();
        
        try {
            $conn = $this->db->getConnection();
            
            // Build query with filters
            $whereConditions = ['1=1'];
            $params = [];
            
            if ($raceId) {
                $whereConditions[] = 'r.id = :race_id';
                $params[':race_id'] = $raceId;
            }
            
            if ($seasonId) {
                $whereConditions[] = 'r.season_id = :season_id';
                $params[':season_id'] = $seasonId;
            }
            
            if (!empty($filters['severity'])) {
                $whereConditions[] = 'p.severity = :severity';
                $params[':severity'] = $filters['severity'];
            }

            $query = "
                SELECT 
                    s.name as season_name,
                    r.name as race_name,
                    r.track,
                    r.race_date,
                    u.username,
                    d.driver_number,
                    t.name as team_name,
                    p.incident_description,
                    p.penalty_type,
                    p.penalty_value,
                    p.severity,
                    p.points_deducted,
                    p.time_penalty,
                    p.grid_penalty,
                    p.steward_notes,
                    p.incident_lap,
                    p.incident_time,
                    p.created_at as penalty_issued_at,
                    admin_u.username as issued_by
                FROM penalties p
                JOIN races r ON p.race_id = r.id
                JOIN seasons s ON r.season_id = s.id
                JOIN drivers d ON p.driver_id = d.id
                JOIN users u ON d.user_id = u.id
                LEFT JOIN teams t ON d.team_id = t.id
                LEFT JOIN users admin_u ON p.issued_by = admin_u.id
                WHERE " . implode(' AND ', $whereConditions) . "
                ORDER BY p.created_at DESC
                LIMIT :max_records
            ";
            
            $stmt = $conn->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':max_records', $this->maxRecords, PDO::PARAM_INT);
            $stmt->execute();
            
            $penalties = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($penalties)) {
                throw new Exception('No penalties found for the specified criteria');
            }
            
            // Generate CSV
            $filename = 'penalties_' . date('Y-m-d_H-i-s') . '.csv';
            $filepath = $this->exportDir . $filename;
            
            $this->generateCSV($penalties, $filepath, 'Penalties Export');
            
            // Log export
            $this->logExport('csv', 'penalties', $filename, $filepath, count($penalties), $filters, $seasonId, $raceId);
            
            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'record_count' => count($penalties),
                'download_url' => '/admin/exports/download.php?file=' . urlencode($filename)
            ];
            
        } catch (Exception $e) {
            logError('Penalties export failed', [
                'user_id' => $this->userId,
                'race_id' => $raceId,
                'season_id' => $seasonId,
                'error' => $e->getMessage()
            ]);
            throw new Exception('Export failed: ' . $e->getMessage());
        }
    }

    /**
     * Generate CSV file from data array
     */
    private function generateCSV($data, $filepath, $title = '') {
        $file = fopen($filepath, 'w');
        
        if (!$file) {
            throw new Exception('Cannot create export file');
        }
        
        // Add BOM for UTF-8
        fwrite($file, "\xEF\xBB\xBF");
        
        // Add title and export info
        if ($title) {
            fputcsv($file, [$title]);
            fputcsv($file, ['Generated on: ' . date('Y-m-d H:i:s')]);
            fputcsv($file, ['Total records: ' . count($data)]);
            fputcsv($file, []);
        }
        
        if (!empty($data)) {
            // Write headers
            fputcsv($file, array_keys($data[0]));
            
            // Write data rows
            foreach ($data as $row) {
                fputcsv($file, $row);
            }
        }
        
        fclose($file);
        
        // Check file size
        $fileSize = filesize($filepath);
        if ($fileSize > $this->maxFileSize) {
            unlink($filepath);
            throw new Exception('Export file exceeds maximum size limit');
        }
    }

    /**
     * Log export operation
     */
    private function logExport($exportType, $dataType, $filename, $filepath, $recordCount, $filters = [], $seasonId = null, $raceId = null) {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("
                INSERT INTO export_logs 
                (export_type, data_type, user_id, season_id, race_id, filename, file_path, file_size, record_count, export_filters)
                VALUES (:export_type, :data_type, :user_id, :season_id, :race_id, :filename, :file_path, :file_size, :record_count, :export_filters)
            ");
            
            $stmt->bindParam(':export_type', $exportType);
            $stmt->bindParam(':data_type', $dataType);
            $stmt->bindParam(':user_id', $this->userId, PDO::PARAM_INT);
            $stmt->bindParam(':season_id', $seasonId, PDO::PARAM_INT);
            $stmt->bindParam(':race_id', $raceId, PDO::PARAM_INT);
            $stmt->bindParam(':filename', $filename);
            $stmt->bindParam(':file_path', $filepath);
            $stmt->bindValue(':file_size', filesize($filepath), PDO::PARAM_INT);
            $stmt->bindParam(':record_count', $recordCount, PDO::PARAM_INT);
            $stmt->bindValue(':export_filters', json_encode($filters));
            
            $stmt->execute();
        } catch (Exception $e) {
            logError('Failed to log export operation', [
                'export_type' => $exportType,
                'data_type' => $dataType,
                'user_id' => $this->userId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get user's export history
     */
    public function getExportHistory($limit = 50) {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("
                SELECT 
                    el.*,
                    s.name as season_name,
                    r.name as race_name
                FROM export_logs el
                LEFT JOIN seasons s ON el.season_id = s.id
                LEFT JOIN races r ON el.race_id = r.id
                WHERE el.user_id = :user_id
                ORDER BY el.created_at DESC
                LIMIT :limit
            ");
            
            $stmt->bindParam(':user_id', $this->userId, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            logError('Failed to retrieve export history', [
                'user_id' => $this->userId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Clean up expired export files
     */
    public static function cleanupExpiredExports() {
        try {
            $db = new Database();
            $conn = $db->getConnection();
            
            // Get expired exports
            $stmt = $conn->prepare("
                SELECT file_path 
                FROM export_logs 
                WHERE expires_at IS NOT NULL AND expires_at < NOW()
            ");
            $stmt->execute();
            
            $expiredFiles = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Delete files
            $deletedCount = 0;
            foreach ($expiredFiles as $filepath) {
                if (file_exists($filepath)) {
                    unlink($filepath);
                    $deletedCount++;
                }
            }
            
            // Remove expired records
            $stmt = $conn->prepare("
                DELETE FROM export_logs 
                WHERE expires_at IS NOT NULL AND expires_at < NOW()
            ");
            $stmt->execute();
            
            logError('Export cleanup completed', [
                'deleted_files' => $deletedCount,
                'deleted_records' => $stmt->rowCount()
            ]);
            
        } catch (Exception $e) {
            logError('Export cleanup failed', ['error' => $e->getMessage()]);
        }
    }
}
?>
