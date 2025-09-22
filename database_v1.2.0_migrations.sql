-- Database Schema Updates for v1.2.0 - Integrations
-- Run this after the base database_setup.sql

-- Add Google Calendar event ID to races table
ALTER TABLE races ADD COLUMN google_event_id VARCHAR(255) NULL AFTER laps;

-- Add new settings for integrations
INSERT INTO settings (`key`, `value`) VALUES 
-- Discord Integration Settings
('discord_webhook', ''),
('notify_driver_register', '1'),
('notify_race_result', '1'),
('notify_team_created', '1'),
('notify_upcoming_race', '1'),
('notify_standings_update', '0'),

-- Google Calendar Integration Settings
('google_client_id', ''),
('google_client_secret', ''),
('google_calendar_id', ''),
('google_access_token', ''),
('google_refresh_token', ''),
('google_token_expires', '0'),
('calendar_sync_enabled', '0'),
('timezone', 'UTC'),

-- Discord Bot Settings
('discord_bot_token', ''),
('discord_server_id', ''),
('discord_channel_results', ''),
('discord_channel_notifications', ''),
('discord_bot_enabled', '0'),
('api_key', ''),

-- General Integration Settings
('webhook_retry_attempts', '3'),
('webhook_timeout', '10'),
('integration_logs_enabled', '1')

ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

-- Create integration logs table for debugging
CREATE TABLE IF NOT EXISTS integration_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type ENUM('discord', 'calendar', 'api') NOT NULL,
    action VARCHAR(100) NOT NULL,
    status ENUM('success', 'error', 'retry') NOT NULL,
    message TEXT,
    data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type_created (type, created_at),
    INDEX idx_status_created (status, created_at)
);

-- Create API keys table for bot authentication
CREATE TABLE IF NOT EXISTS api_keys (
    id INT PRIMARY KEY AUTO_INCREMENT,
    key_hash VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    permissions JSON, -- ["standings", "races", "drivers", "teams"]
    is_active BOOLEAN DEFAULT TRUE,
    last_used TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    INDEX idx_key_hash (key_hash),
    INDEX idx_active_expires (is_active, expires_at)
);

-- Add webhook delivery attempts tracking
CREATE TABLE IF NOT EXISTS webhook_deliveries (
    id INT PRIMARY KEY AUTO_INCREMENT,
    webhook_url VARCHAR(500) NOT NULL,
    payload JSON NOT NULL,
    response_code INT,
    response_body TEXT,
    attempt_number INT DEFAULT 1,
    status ENUM('pending', 'delivered', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    delivered_at TIMESTAMP NULL,
    INDEX idx_status_created (status, created_at)
);

-- Add calendar sync status to races
ALTER TABLE races ADD COLUMN calendar_sync_status ENUM('not_synced', 'synced', 'error') DEFAULT 'not_synced' AFTER google_event_id;
ALTER TABLE races ADD COLUMN calendar_last_sync TIMESTAMP NULL AFTER calendar_sync_status;

-- Create notification queue for async processing
CREATE TABLE IF NOT EXISTS notification_queue (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type ENUM('discord', 'calendar', 'email') NOT NULL,
    event_type VARCHAR(100) NOT NULL, -- 'race_result', 'driver_register', etc.
    recipient VARCHAR(255), -- webhook URL, calendar ID, email
    payload JSON NOT NULL,
    priority INT DEFAULT 5, -- 1-10, lower = higher priority
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    scheduled_for TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status_priority (status, priority),
    INDEX idx_scheduled (scheduled_for),
    INDEX idx_type_event (type, event_type)
);

-- Create integration settings cache table for performance
CREATE TABLE IF NOT EXISTS integration_cache (
    cache_key VARCHAR(100) PRIMARY KEY,
    cache_value JSON NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    INDEX idx_expires (expires_at)
);

-- Add indexes for better performance on new columns
CREATE INDEX idx_races_google_event ON races(google_event_id);
CREATE INDEX idx_races_calendar_sync ON races(calendar_sync_status, calendar_last_sync);

-- Update existing default settings if they don't exist
INSERT IGNORE INTO settings (`key`, `value`) VALUES 
('league_name', 'Grid King League'),
('welcome_text', 'Welcome to our SimRacing League!'),
('theme_color', '#dc2626'),
('points_system', 'f1_2022');

-- Create a view for easy integration status monitoring
CREATE OR REPLACE VIEW integration_status AS
SELECT 
    'Discord Webhook' as integration_name,
    CASE 
        WHEN s1.value != '' AND s1.value IS NOT NULL THEN 'Configured'
        ELSE 'Not Configured'
    END as status,
    s1.value as config_value
FROM settings s1 WHERE s1.key = 'discord_webhook'
UNION ALL
SELECT 
    'Google Calendar' as integration_name,
    CASE 
        WHEN s2.value = '1' THEN 'Enabled'
        WHEN s2.value = '0' THEN 'Configured but Disabled'
        ELSE 'Not Configured'
    END as status,
    s2.value as config_value
FROM settings s2 WHERE s2.key = 'calendar_sync_enabled'
UNION ALL
SELECT 
    'Discord Bot' as integration_name,
    CASE 
        WHEN s3.value = '1' THEN 'Enabled'
        WHEN s3.value = '0' THEN 'Configured but Disabled'
        ELSE 'Not Configured'
    END as status,
    s3.value as config_value
FROM settings s3 WHERE s3.key = 'discord_bot_enabled';

-- Insert sample API key for testing (replace in production)
INSERT INTO api_keys (key_hash, name, permissions, is_active) VALUES 
(SHA2('test_api_key_change_in_production', 256), 'Default Bot Key', '["standings", "races", "drivers", "teams"]', TRUE);

-- Add triggers for automatic notification queue population
DELIMITER //

CREATE TRIGGER after_race_result_insert 
AFTER INSERT ON race_results
FOR EACH ROW
BEGIN
    -- Check if all drivers have results for this race
    DECLARE total_drivers INT;
    DECLARE drivers_with_results INT;
    
    SELECT COUNT(*) INTO total_drivers 
    FROM drivers d 
    JOIN users u ON d.user_id = u.id 
    WHERE u.verified = TRUE;
    
    SELECT COUNT(DISTINCT driver_id) INTO drivers_with_results 
    FROM race_results 
    WHERE race_id = NEW.race_id;
    
    -- If race is complete, queue notifications
    IF drivers_with_results >= total_drivers THEN
        INSERT INTO notification_queue (type, event_type, payload, priority) VALUES 
        ('discord', 'race_result', JSON_OBJECT('race_id', NEW.race_id), 3);
    END IF;
END//

CREATE TRIGGER after_driver_insert 
AFTER INSERT ON drivers
FOR EACH ROW
BEGIN
    INSERT INTO notification_queue (type, event_type, payload, priority) VALUES 
    ('discord', 'driver_register', JSON_OBJECT('user_id', NEW.user_id), 5);
END//

CREATE TRIGGER after_race_insert 
AFTER INSERT ON races
FOR EACH ROW
BEGIN
    -- Queue calendar sync
    INSERT INTO notification_queue (type, event_type, payload, priority) VALUES 
    ('calendar', 'race_created', JSON_OBJECT('race_id', NEW.id), 4);
    
    -- Queue upcoming race notification (schedule for 24 hours before race)
    INSERT INTO notification_queue (type, event_type, payload, priority, scheduled_for) VALUES 
    ('discord', 'upcoming_race', JSON_OBJECT('race_id', NEW.id), 2, DATE_SUB(NEW.race_date, INTERVAL 24 HOUR));
END//

CREATE TRIGGER after_race_update 
AFTER UPDATE ON races
FOR EACH ROW
BEGIN
    -- Queue calendar sync if date or details changed
    IF OLD.race_date != NEW.race_date OR OLD.name != NEW.name OR OLD.track != NEW.track THEN
        INSERT INTO notification_queue (type, event_type, payload, priority) VALUES 
        ('calendar', 'race_updated', JSON_OBJECT('race_id', NEW.id), 4);
    END IF;
END//

DELIMITER ;

-- Clean up old integration logs (keep last 30 days)
CREATE EVENT IF NOT EXISTS cleanup_integration_logs
ON SCHEDULE EVERY 1 DAY
DO
DELETE FROM integration_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Clean up old webhook deliveries (keep last 7 days)
CREATE EVENT IF NOT EXISTS cleanup_webhook_deliveries
ON SCHEDULE EVERY 1 DAY
DO
DELETE FROM webhook_deliveries WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);

-- Clean up expired cache entries
CREATE EVENT IF NOT EXISTS cleanup_integration_cache
ON SCHEDULE EVERY 1 HOUR
DO
DELETE FROM integration_cache WHERE expires_at < NOW();
