<?php
/**
 * Discord Integration Class
 * Enhanced webhook functionality with rich embeds and event handling
 */

class DiscordIntegration {
    private $webhookUrl;
    private $db;
    private $settings;

    public function __construct($db) {
        $this->db = $db;
        $this->loadSettings();
        $this->webhookUrl = $this->settings['discord_webhook'] ?? '';
    }

    private function loadSettings() {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("SELECT `key`, `value` FROM settings");
            $stmt->execute();
            $this->settings = [];
            foreach ($stmt->fetchAll() as $row) {
                $this->settings[$row['key']] = $row['value'];
            }
        } catch (Exception $e) {
            logError('Failed to load Discord settings', ['error' => $e->getMessage()]);
            $this->settings = [];
        }
    }

    /**
     * Send a Discord webhook with rich embed
     */
    public function sendWebhook($embed, $content = '') {
        if (empty($this->webhookUrl)) {
            return false;
        }

        $payload = [
            'content' => $content,
            'embeds' => [$embed]
        ];

        return $this->makeWebhookRequest($payload);
    }

    /**
     * Send race result notification
     */
    public function notifyRaceResult($raceId) {
        try {
            if (!$this->isNotificationEnabled('notify_race_result')) {
                return false;
            }

            $raceData = $this->getRaceData($raceId);
            $results = $this->getRaceResults($raceId);

            if (!$raceData || !$results) {
                logError('Discord: Race data not found', ['race_id' => $raceId]);
                return false;
            }

            $embed = [
                'title' => '🏁 Race Results: ' . ($raceData['name'] ?? 'Unknown Race'),
                'description' => 'Race completed at ' . ($raceData['track'] ?? 'Unknown Track'),
                'color' => 0x00FF00, // Green
                'fields' => [],
                'timestamp' => date('c'),
                'footer' => [
                    'text' => $this->settings['league_name'] ?? 'Grid King'
                ]
            ];

            // Add podium results
            $podium = array_slice($results, 0, 3);
            $podiumText = '';
            $medals = ['🥇', '🥈', '🥉'];
            
            foreach ($podium as $index => $result) {
                if (isset($result['username'])) {
                    $teamName = isset($result['team_name']) ? $result['team_name'] : 'Independent';
                    $podiumText .= $medals[$index] . ' ' . $result['username'] . ' (' . $teamName . ")\n";
                }
            }

            if (!empty($podiumText)) {
                $embed['fields'][] = [
                    'name' => 'Podium',
                    'value' => $podiumText,
                    'inline' => true
                ];
            }

            // Add fastest lap
            $fastestLap = $this->getFastestLap($raceId);
            if ($fastestLap && isset($fastestLap['username'])) {
                $lapTime = isset($fastestLap['fastest_lap_time']) ? ' - ' . $fastestLap['fastest_lap_time'] : '';
                $embed['fields'][] = [
                    'name' => '⚡ Fastest Lap',
                    'value' => $fastestLap['username'] . $lapTime,
                    'inline' => true
                ];
            }

            // Add DNFs if any
            $dnfs = array_filter($results, function($result) {
                return isset($result['dnf']) && $result['dnf'] == 1;
            });

            if (!empty($dnfs)) {
                $dnfText = '';
                foreach ($dnfs as $dnf) {
                    if (isset($dnf['username'])) {
                        $dnfText .= $dnf['username'] . "\n";
                    }
                }
                if (!empty($dnfText)) {
                    $embed['fields'][] = [
                        'name' => '❌ DNF',
                        'value' => $dnfText,
                        'inline' => true
                    ];
                }
            }

            return $this->sendWebhook($embed);
        } catch (Exception $e) {
            logError('Discord race result notification failed', ['race_id' => $raceId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Send driver registration notification
     */
    public function notifyDriverRegistration($userId) {
        try {
            if (!$this->isNotificationEnabled('notify_driver_register')) {
                return false;
            }

            $driverData = $this->getDriverData($userId);
            
            if (!$driverData || !isset($driverData['username'])) {
                logError('Discord: Driver data not found', ['user_id' => $userId]);
                return false;
            }

            $embed = [
                'title' => '🏎️ New Driver Joined',
                'description' => $driverData['username'] . ' has joined the league!',
                'color' => 0x0099FF, // Blue
                'fields' => [
                    [
                        'name' => 'Driver Number',
                        'value' => '#' . ($driverData['driver_number'] ?? 'TBD'),
                        'inline' => true
                    ],
                    [
                        'name' => 'Team',
                        'value' => $driverData['team_name'] ?? 'Independent',
                        'inline' => true
                    ],
                    [
                        'name' => 'Platform',
                        'value' => $driverData['platform'] ?? 'Unknown',
                        'inline' => true
                    ]
                ],
                'timestamp' => date('c'),
                'footer' => [
                    'text' => $this->settings['league_name'] ?? 'Grid King'
                ]
            ];

            return $this->sendWebhook($embed);
        } catch (Exception $e) {
            logError('Discord driver registration notification failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Send championship standings update
     */
    public function notifyStandingsUpdate($seasonId) {
        try {
            if (!$this->isNotificationEnabled('notify_standings_update')) {
                return false;
            }

            $standings = calculateStandings($seasonId);
            if (!$standings) {
                logError('Discord: Standings data not found', ['season_id' => $seasonId]);
                return false;
            }

            $topDrivers = array_slice($standings, 0, 5);

            $embed = [
                'title' => '🏆 Championship Standings Update',
                'description' => 'Current championship standings',
                'color' => 0xFFD700, // Gold
                'fields' => [],
                'timestamp' => date('c'),
                'footer' => [
                    'text' => $this->settings['league_name'] ?? 'Grid King'
                ]
            ];

            $standingsText = '';
            foreach ($topDrivers as $index => $driver) {
                $position = $index + 1;
                $points = $driver['total_points'] ?? 0;
                $username = $driver['username'] ?? 'Unknown';
                $standingsText .= $position . '. ' . $username . ' - ' . $points . " pts\n";
            }

            $embed['fields'][] = [
                'name' => 'Top 5 Drivers',
                'value' => $standingsText ?: 'No standings available',
                'inline' => false
            ];

            return $this->sendWebhook($embed);
        } catch (Exception $e) {
            logError('Discord standings update notification failed', ['season_id' => $seasonId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Send upcoming race notification
     */
    public function notifyUpcomingRace($raceId) {
        try {
            if (!$this->isNotificationEnabled('notify_upcoming_race')) {
                return false;
            }

            $raceData = $this->getRaceData($raceId);
            if (!$raceData) {
                logError('Discord: Race data not found for upcoming notification', ['race_id' => $raceId]);
                return false;
            }
            
            $embed = [
                'title' => '🏁 Upcoming Race',
                'description' => ($raceData['name'] ?? 'Unknown Race') . ' at ' . ($raceData['track'] ?? 'Unknown Track'),
                'color' => 0xFF6600, // Orange
                'fields' => [
                    [
                        'name' => '📅 Date',
                        'value' => isset($raceData['race_date']) ? date('F j, Y g:i A', strtotime($raceData['race_date'])) : 'TBD',
                        'inline' => true
                    ],
                    [
                        'name' => '🏎️ Format',
                        'value' => $raceData['format'] ?? 'Unknown',
                        'inline' => true
                    ],
                    [
                        'name' => '🔄 Laps',
                        'value' => $raceData['laps'] ?? 'TBD',
                        'inline' => true
                    ]
                ],
                'timestamp' => date('c'),
                'footer' => [
                    'text' => $this->settings['league_name'] ?? 'Grid King'
                ]
            ];

            return $this->sendWebhook($embed);
        } catch (Exception $e) {
            logError('Discord upcoming race notification failed', ['race_id' => $raceId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Test webhook connection
     */
    public function testWebhook() {
        try {
            $embed = [
                'title' => '✅ Webhook Test',
                'description' => 'This is a test message from your Grid King league!',
                'color' => 0x00FF00,
                'timestamp' => date('c'),
                'footer' => [
                    'text' => $this->settings['league_name'] ?? 'Grid King'
                ]
            ];

            return $this->sendWebhook($embed, 'Webhook test successful! 🎉');
        } catch (Exception $e) {
            logError('Discord webhook test failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    // Helper methods
    private function makeWebhookRequest($payload) {
        try {
            if (empty($this->webhookUrl) || !filter_var($this->webhookUrl, FILTER_VALIDATE_URL)) {
                return false;
            }

            $ch = curl_init($this->webhookUrl);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                logError('Discord webhook CURL error', ['error' => $error]);
                return false;
            }

            $success = $httpCode >= 200 && $httpCode < 300;
            if (!$success) {
                logError('Discord webhook HTTP error', ['code' => $httpCode, 'response' => $response]);
            }

            return $success;
        } catch (Exception $e) {
            logError('Discord webhook request failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function isNotificationEnabled($key) {
        return isset($this->settings[$key]) && $this->settings[$key] === '1';
    }

    private function getRaceData($raceId) {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("SELECT * FROM races WHERE id = :race_id");
            $stmt->bindParam(':race_id', $raceId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            logError('Failed to get race data', ['race_id' => $raceId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function getRaceResults($raceId) {
        try {
            $conn = $this->db->getConnection();
            $query = "
                SELECT 
                    rr.*,
                    u.username,
                    t.name as team_name
                FROM race_results rr
                LEFT JOIN drivers d ON rr.driver_id = d.id
                LEFT JOIN users u ON d.user_id = u.id
                LEFT JOIN teams t ON d.team_id = t.id
                WHERE rr.race_id = :race_id
                ORDER BY rr.position ASC
            ";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':race_id', $raceId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            logError('Failed to get race results', ['race_id' => $raceId, 'error' => $e->getMessage()]);
            return [];
        }
    }

    private function getFastestLap($raceId) {
        try {
            $conn = $this->db->getConnection();
            $query = "
                SELECT 
                    rr.fastest_lap_time,
                    u.username
                FROM race_results rr
                LEFT JOIN drivers d ON rr.driver_id = d.id
                LEFT JOIN users u ON d.user_id = u.id
                WHERE rr.race_id = :race_id AND rr.fastest_lap = 1
                LIMIT 1
            ";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':race_id', $raceId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            logError('Failed to get fastest lap', ['race_id' => $raceId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function getDriverData($userId) {
        try {
            $conn = $this->db->getConnection();
            $query = "
                SELECT 
                    u.username,
                    d.driver_number,
                    d.platform,
                    t.name as team_name
                FROM users u
                LEFT JOIN drivers d ON u.id = d.user_id
                LEFT JOIN teams t ON d.team_id = t.id
                WHERE u.id = :user_id
            ";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            logError('Failed to get driver data', ['user_id' => $userId, 'error' => $e->getMessage()]);
            return null;
        }
    }
}
?>
