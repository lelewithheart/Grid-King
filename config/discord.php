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
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT `key`, `value` FROM settings");
        $stmt->execute();
        $this->settings = [];
        foreach ($stmt->fetchAll() as $row) {
            $this->settings[$row['key']] = $row['value'];
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
        if (!$this->isNotificationEnabled('notify_race_result')) {
            return false;
        }

        $raceData = $this->getRaceData($raceId);
        $results = $this->getRaceResults($raceId);

        $embed = [
            'title' => '🏁 Race Results: ' . $raceData['name'],
            'description' => 'Race completed at ' . $raceData['track'],
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
            $podiumText .= $medals[$index] . ' ' . $result['username'] . ' (' . $result['team_name'] . ")\n";
        }

        $embed['fields'][] = [
            'name' => 'Podium',
            'value' => $podiumText,
            'inline' => true
        ];

        // Add fastest lap
        $fastestLap = $this->getFastestLap($raceId);
        if ($fastestLap) {
            $embed['fields'][] = [
                'name' => '⚡ Fastest Lap',
                'value' => $fastestLap['username'] . ' - ' . $fastestLap['fastest_lap_time'],
                'inline' => true
            ];
        }

        // Add DNFs if any
        $dnfs = array_filter($results, function($result) {
            return $result['dnf'] == 1;
        });

        if (!empty($dnfs)) {
            $dnfText = '';
            foreach ($dnfs as $dnf) {
                $dnfText .= $dnf['username'] . "\n";
            }
            $embed['fields'][] = [
                'name' => '❌ DNF',
                'value' => $dnfText,
                'inline' => true
            ];
        }

        return $this->sendWebhook($embed);
    }

    /**
     * Send driver registration notification
     */
    public function notifyDriverRegistration($userId) {
        if (!$this->isNotificationEnabled('notify_driver_register')) {
            return false;
        }

        $driverData = $this->getDriverData($userId);

        $embed = [
            'title' => '🏎️ New Driver Joined',
            'description' => $driverData['username'] . ' has joined the league!',
            'color' => 0x0099FF, // Blue
            'fields' => [
                [
                    'name' => 'Driver Number',
                    'value' => '#' . $driverData['driver_number'],
                    'inline' => true
                ],
                [
                    'name' => 'Team',
                    'value' => $driverData['team_name'] ?? 'Independent',
                    'inline' => true
                ],
                [
                    'name' => 'Platform',
                    'value' => $driverData['platform'],
                    'inline' => true
                ]
            ],
            'timestamp' => date('c'),
            'footer' => [
                'text' => $this->settings['league_name'] ?? 'Grid King'
            ]
        ];

        return $this->sendWebhook($embed);
    }

    /**
     * Send championship standings update
     */
    public function notifyStandingsUpdate($seasonId) {
        $standings = calculateStandings($seasonId);
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
            $standingsText .= $position . '. ' . $driver['username'] . ' - ' . $driver['total_points'] . " pts\n";
        }

        $embed['fields'][] = [
            'name' => 'Top 5 Drivers',
            'value' => $standingsText,
            'inline' => false
        ];

        return $this->sendWebhook($embed);
    }

    /**
     * Send upcoming race notification
     */
    public function notifyUpcomingRace($raceId) {
        $raceData = $this->getRaceData($raceId);
        
        $embed = [
            'title' => '🏁 Upcoming Race',
            'description' => $raceData['name'] . ' at ' . $raceData['track'],
            'color' => 0xFF6600, // Orange
            'fields' => [
                [
                    'name' => '📅 Date',
                    'value' => date('F j, Y g:i A', strtotime($raceData['race_date'])),
                    'inline' => true
                ],
                [
                    'name' => '🏎️ Format',
                    'value' => $raceData['format'],
                    'inline' => true
                ],
                [
                    'name' => '🔄 Laps',
                    'value' => $raceData['laps'],
                    'inline' => true
                ]
            ],
            'timestamp' => date('c'),
            'footer' => [
                'text' => $this->settings['league_name'] ?? 'Grid King'
            ]
        ];

        return $this->sendWebhook($embed);
    }

    /**
     * Test webhook connection
     */
    public function testWebhook() {
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
    }

    // Helper methods
    private function makeWebhookRequest($payload) {
        $ch = curl_init($this->webhookUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode >= 200 && $httpCode < 300;
    }

    private function isNotificationEnabled($key) {
        return isset($this->settings[$key]) && $this->settings[$key] === '1';
    }

    private function getRaceData($raceId) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT * FROM races WHERE id = :race_id");
        $stmt->bindParam(':race_id', $raceId);
        $stmt->execute();
        return $stmt->fetch();
    }

    private function getRaceResults($raceId) {
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
        $stmt->bindParam(':race_id', $raceId);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function getFastestLap($raceId) {
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
        $stmt->bindParam(':race_id', $raceId);
        $stmt->execute();
        return $stmt->fetch();
    }

    private function getDriverData($userId) {
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
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        return $stmt->fetch();
    }
}
?>
