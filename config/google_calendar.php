<?php
/**
 * Google Calendar Integration Class
 * Bidirectional synchronization between Grid King races and Google Calendar
 */

class GoogleCalendarIntegration {
    private $db;
    private $settings;
    private $accessToken;
    private $calendarId;

    const GOOGLE_CALENDAR_API_URL = 'https://www.googleapis.com/calendar/v3';
    const GOOGLE_OAUTH_URL = 'https://oauth2.googleapis.com/token';

    public function __construct($db) {
        $this->db = $db;
        $this->loadSettings();
        $this->accessToken = $this->settings['google_access_token'] ?? '';
        $this->calendarId = $this->settings['google_calendar_id'] ?? '';
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
     * Generate Google OAuth URL for calendar access
     */
    public function getAuthUrl($redirectUri) {
        $clientId = $this->settings['google_client_id'] ?? '';
        
        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => 'https://www.googleapis.com/auth/calendar',
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent'
        ];

        return 'https://accounts.google.com/o/oauth2/auth?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access token
     */
    public function exchangeAuthCode($code, $redirectUri) {
        $clientId = $this->settings['google_client_id'] ?? '';
        $clientSecret = $this->settings['google_client_secret'] ?? '';

        $postData = [
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code'
        ];

        $ch = curl_init(self::GOOGLE_OAUTH_URL);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $tokenData = json_decode($response, true);
            
            // Save tokens to settings
            $this->saveToken('google_access_token', $tokenData['access_token']);
            $this->saveToken('google_refresh_token', $tokenData['refresh_token'] ?? '');
            $this->saveToken('google_token_expires', time() + $tokenData['expires_in']);

            return true;
        }

        return false;
    }

    /**
     * Refresh access token using refresh token
     */
    public function refreshAccessToken() {
        $refreshToken = $this->settings['google_refresh_token'] ?? '';
        $clientId = $this->settings['google_client_id'] ?? '';
        $clientSecret = $this->settings['google_client_secret'] ?? '';

        if (empty($refreshToken)) {
            return false;
        }

        $postData = [
            'refresh_token' => $refreshToken,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type' => 'refresh_token'
        ];

        $ch = curl_init(self::GOOGLE_OAUTH_URL);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $tokenData = json_decode($response, true);
            
            $this->saveToken('google_access_token', $tokenData['access_token']);
            $this->saveToken('google_token_expires', time() + $tokenData['expires_in']);
            $this->accessToken = $tokenData['access_token'];

            return true;
        }

        return false;
    }

    /**
     * Create calendar event for a race
     */
    public function createRaceEvent($raceId) {
        if (!$this->isConfigured()) {
            return false;
        }

        $raceData = $this->getRaceData($raceId);
        if (!$raceData) {
            return false;
        }

        // Check if token needs refresh
        if ($this->needsTokenRefresh()) {
            if (!$this->refreshAccessToken()) {
                return false;
            }
        }

        $event = [
            'summary' => $raceData['name'],
            'description' => $this->buildRaceDescription($raceData),
            'start' => [
                'dateTime' => date('c', strtotime($raceData['race_date'])),
                'timeZone' => $this->settings['timezone'] ?? 'UTC'
            ],
            'end' => [
                'dateTime' => date('c', strtotime($raceData['race_date'] . ' +2 hours')),
                'timeZone' => $this->settings['timezone'] ?? 'UTC'
            ],
            'location' => $raceData['track'],
            'reminders' => [
                'useDefault' => false,
                'overrides' => [
                    ['method' => 'email', 'minutes' => 60],
                    ['method' => 'popup', 'minutes' => 15]
                ]
            ]
        ];

        $response = $this->makeCalendarRequest(
            'POST',
            "/calendars/{$this->calendarId}/events",
            $event
        );

        if ($response && isset($response['id'])) {
            // Save Google event ID to race record
            $this->saveEventId($raceId, $response['id']);
            return true;
        }

        return false;
    }

    /**
     * Update existing calendar event
     */
    public function updateRaceEvent($raceId) {
        if (!$this->isConfigured()) {
            return false;
        }

        $raceData = $this->getRaceData($raceId);
        $eventId = $this->getEventId($raceId);

        if (!$raceData || !$eventId) {
            return false;
        }

        // Check if token needs refresh
        if ($this->needsTokenRefresh()) {
            if (!$this->refreshAccessToken()) {
                return false;
            }
        }

        $event = [
            'summary' => $raceData['name'],
            'description' => $this->buildRaceDescription($raceData),
            'start' => [
                'dateTime' => date('c', strtotime($raceData['race_date'])),
                'timeZone' => $this->settings['timezone'] ?? 'UTC'
            ],
            'end' => [
                'dateTime' => date('c', strtotime($raceData['race_date'] . ' +2 hours')),
                'timeZone' => $this->settings['timezone'] ?? 'UTC'
            ],
            'location' => $raceData['track']
        ];

        $response = $this->makeCalendarRequest(
            'PUT',
            "/calendars/{$this->calendarId}/events/{$eventId}",
            $event
        );

        return $response !== false;
    }

    /**
     * Delete calendar event
     */
    public function deleteRaceEvent($raceId) {
        if (!$this->isConfigured()) {
            return false;
        }

        $eventId = $this->getEventId($raceId);
        if (!$eventId) {
            return false;
        }

        // Check if token needs refresh
        if ($this->needsTokenRefresh()) {
            if (!$this->refreshAccessToken()) {
                return false;
            }
        }

        $response = $this->makeCalendarRequest(
            'DELETE',
            "/calendars/{$this->calendarId}/events/{$eventId}"
        );

        if ($response !== false) {
            // Remove event ID from race record
            $this->removeEventId($raceId);
            return true;
        }

        return false;
    }

    /**
     * Sync all races to calendar
     */
    public function syncAllRaces() {
        if (!$this->isConfigured()) {
            return false;
        }

        $races = $this->getAllRaces();
        $synced = 0;
        $errors = [];

        foreach ($races as $race) {
            $eventId = $this->getEventId($race['id']);
            
            if ($eventId) {
                // Update existing event
                if ($this->updateRaceEvent($race['id'])) {
                    $synced++;
                } else {
                    $errors[] = "Failed to update race: " . $race['name'];
                }
            } else {
                // Create new event
                if ($this->createRaceEvent($race['id'])) {
                    $synced++;
                } else {
                    $errors[] = "Failed to create race: " . $race['name'];
                }
            }
        }

        return [
            'synced' => $synced,
            'errors' => $errors
        ];
    }

    /**
     * Get calendar events to sync back to Grid King
     */
    public function getCalendarEvents($timeMin = null, $timeMax = null) {
        if (!$this->isConfigured()) {
            return false;
        }

        // Check if token needs refresh
        if ($this->needsTokenRefresh()) {
            if (!$this->refreshAccessToken()) {
                return false;
            }
        }

        $params = [
            'orderBy' => 'startTime',
            'singleEvents' => 'true'
        ];

        if ($timeMin) {
            $params['timeMin'] = date('c', strtotime($timeMin));
        }
        if ($timeMax) {
            $params['timeMax'] = date('c', strtotime($timeMax));
        }

        $queryString = http_build_query($params);
        
        return $this->makeCalendarRequest(
            'GET',
            "/calendars/{$this->calendarId}/events?{$queryString}"
        );
    }

    /**
     * Test calendar connection
     */
    public function testConnection() {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'Calendar not configured'];
        }

        // Check if token needs refresh
        if ($this->needsTokenRefresh()) {
            if (!$this->refreshAccessToken()) {
                return ['success' => false, 'message' => 'Failed to refresh access token'];
            }
        }

        $response = $this->makeCalendarRequest('GET', "/calendars/{$this->calendarId}");
        
        if ($response && isset($response['summary'])) {
            return [
                'success' => true, 
                'message' => 'Connected to calendar: ' . $response['summary']
            ];
        }

        return ['success' => false, 'message' => 'Failed to connect to calendar'];
    }

    // Helper methods
    private function isConfigured() {
        return !empty($this->accessToken) && !empty($this->calendarId);
    }

    private function needsTokenRefresh() {
        $expiresAt = $this->settings['google_token_expires'] ?? 0;
        return time() >= ($expiresAt - 300); // Refresh 5 minutes before expiry
    }

    private function makeCalendarRequest($method, $endpoint, $data = null) {
        $url = self::GOOGLE_CALENDAR_API_URL . $endpoint;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return $response ? json_decode($response, true) : true;
        }

        return false;
    }

    private function buildRaceDescription($raceData) {
        $description = "🏁 " . $raceData['name'] . "\n\n";
        $description .= "📍 Track: " . $raceData['track'] . "\n";
        $description .= "🏁 Format: " . $raceData['format'] . "\n";
        $description .= "🔄 Laps: " . $raceData['laps'] . "\n\n";
        $description .= "League: " . ($this->settings['league_name'] ?? 'Grid King');
        
        return $description;
    }

    private function saveToken($key, $value) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("INSERT INTO settings (`key`, `value`) VALUES (:key, :value) ON DUPLICATE KEY UPDATE `value` = :value");
        $stmt->bindParam(':key', $key);
        $stmt->bindParam(':value', $value);
        $stmt->execute();
        $this->settings[$key] = $value;
    }

    private function getRaceData($raceId) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT * FROM races WHERE id = :race_id");
        $stmt->bindParam(':race_id', $raceId);
        $stmt->execute();
        return $stmt->fetch();
    }

    private function getAllRaces() {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT * FROM races ORDER BY race_date ASC");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function saveEventId($raceId, $eventId) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("UPDATE races SET google_event_id = :event_id WHERE id = :race_id");
        $stmt->bindParam(':event_id', $eventId);
        $stmt->bindParam(':race_id', $raceId);
        $stmt->execute();
    }

    private function getEventId($raceId) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT google_event_id FROM races WHERE id = :race_id");
        $stmt->bindParam(':race_id', $raceId);
        $stmt->execute();
        $result = $stmt->fetch();
        return $result ? $result['google_event_id'] : null;
    }

    private function removeEventId($raceId) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("UPDATE races SET google_event_id = NULL WHERE id = :race_id");
        $stmt->bindParam(':race_id', $raceId);
        $stmt->execute();
    }
}
?>
