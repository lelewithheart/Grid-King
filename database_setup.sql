-- GridKing Racing League Management System Database Schema
-- Version 1.2.1 - Complete Setup with Integrations & Export System
-- Created for PHP 8.2 + MariaDB 10.11

DROP DATABASE IF EXISTS racing_league;
CREATE DATABASE racing_league CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE racing_league;

-- Users table (authentication and roles)
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'driver', 'spectator') DEFAULT 'spectator',
    verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Teams table
CREATE TABLE IF NOT EXISTS teams (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,
    logo VARCHAR(255),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Drivers table (extended user profile for racing)
CREATE TABLE IF NOT EXISTS drivers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    team_id INT,
    driver_number INT UNIQUE,
    platform ENUM('PC', 'Xbox', 'PlayStation') NOT NULL,
    country VARCHAR(3), -- ISO country code
    livery_image VARCHAR(255),
    bio TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_team_id (team_id),
    INDEX idx_driver_number (driver_number),
    INDEX idx_platform (platform),
    INDEX idx_drivers_active (user_id, created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seasons table  
CREATE TABLE IF NOT EXISTS seasons (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    year INT NOT NULL,
    is_active BOOLEAN DEFAULT FALSE,
    start_date DATE,
    end_date DATE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_year (year),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Races table
CREATE TABLE IF NOT EXISTS races (
    id INT PRIMARY KEY AUTO_INCREMENT,
    season_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    track VARCHAR(255) NOT NULL,
    race_date DATETIME NOT NULL,
    format ENUM('Feature', 'Sprint', 'Qualifying', 'Practice') DEFAULT 'Feature',
    laps INT,
    status ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
    track_image VARCHAR(255),
    weather_conditions VARCHAR(100),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_season_id (season_id),
    INDEX idx_race_date (race_date),
    INDEX idx_status (status),
    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Session types for qualifying, practice, etc.
CREATE TABLE IF NOT EXISTS session_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    display_order INT DEFAULT 0,
    INDEX idx_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Race sessions (practice, qualifying, sprint, main race)
CREATE TABLE IF NOT EXISTS race_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    race_id INT NOT NULL,
    session_type_id INT NOT NULL,
    session_order INT DEFAULT 1,
    enabled BOOLEAN DEFAULT TRUE,
    scheduled_start DATETIME,
    actual_start DATETIME,
    actual_end DATETIME,
    INDEX idx_race_id (race_id),
    INDEX idx_session_type (session_type_id),
    FOREIGN KEY (race_id) REFERENCES races(id) ON DELETE CASCADE,
    FOREIGN KEY (session_type_id) REFERENCES session_types(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Race results
CREATE TABLE IF NOT EXISTS race_results (
    id INT PRIMARY KEY AUTO_INCREMENT,
    race_id INT NOT NULL,
    driver_id INT NOT NULL,
    position INT,
    points DECIMAL(5,2) DEFAULT 0.00,
    fastest_lap BOOLEAN DEFAULT FALSE,
    fastest_lap_time TIME,
    pole_position BOOLEAN DEFAULT FALSE,
    dnf BOOLEAN DEFAULT FALSE,
    dnf_reason TEXT,
    penalties_applied DECIMAL(5,2) DEFAULT 0.00,
    grid_position INT,
    status ENUM('finished', 'dnf', 'dsq', 'dns') DEFAULT 'finished',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_race_driver (race_id, driver_id),
    INDEX idx_position (position),
    INDEX idx_points (points),
    INDEX idx_race_results_race_season (race_id, driver_id),
    INDEX idx_race_results_points (points, position),
    UNIQUE KEY unique_race_driver (race_id, driver_id),
    FOREIGN KEY (race_id) REFERENCES races(id) ON DELETE CASCADE,
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Race registrations table (for drivers to register for races)
CREATE TABLE IF NOT EXISTS race_registrations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    race_id INT NOT NULL,
    driver_id INT NOT NULL,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_race_driver (race_id, driver_id),
    FOREIGN KEY (race_id) REFERENCES races(id) ON DELETE CASCADE,
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Penalties table
CREATE TABLE IF NOT EXISTS penalties (
    id INT PRIMARY KEY AUTO_INCREMENT,
    race_id INT NOT NULL,
    driver_id INT NOT NULL,
    incident_description TEXT NOT NULL,
    penalty_type ENUM('time', 'grid', 'points', 'warning', 'dsq') NOT NULL,
    penalty_value INT DEFAULT 0, -- seconds for time, positions for grid, points for points
    severity ENUM('warning', 'minor', 'major', 'severe') DEFAULT 'minor',
    points_deducted DECIMAL(5,2) DEFAULT 0.00,
    time_penalty INT DEFAULT 0, -- in seconds
    grid_penalty INT DEFAULT 0, -- grid positions
    steward_notes TEXT,
    incident_lap INT,
    incident_time TIME,
    issued_by INT, -- admin user who issued penalty
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_race_id (race_id),
    INDEX idx_driver_id (driver_id),
    INDEX idx_penalty_type (penalty_type),
    INDEX idx_severity (severity),
    INDEX idx_penalties_race_driver (race_id, driver_id, created_at),
    FOREIGN KEY (race_id) REFERENCES races(id) ON DELETE CASCADE,
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE,
    FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- News/Announcements table
CREATE TABLE IF NOT EXISTS news (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    author_id INT NOT NULL,
    published BOOLEAN DEFAULT FALSE,
    featured BOOLEAN DEFAULT FALSE,
    image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_published (published),
    INDEX idx_featured (featured),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settings table for system configuration
CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    `key` VARCHAR(100) UNIQUE NOT NULL,
    `value` TEXT,
    description TEXT,
    category VARCHAR(50) DEFAULT 'general',
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (`key`),
    INDEX idx_category (category),
    INDEX idx_public (is_public)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- API Keys table for integrations (v1.2.0)
CREATE TABLE IF NOT EXISTS api_keys (
    id INT PRIMARY KEY AUTO_INCREMENT,
    key_name VARCHAR(100) NOT NULL,
    api_key VARCHAR(255) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    permissions JSON NOT NULL DEFAULT '[]',
    last_used TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_api_key (api_key),
    INDEX idx_user_id (user_id),
    INDEX idx_active (is_active),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Integration logs (v1.2.0)
CREATE TABLE IF NOT EXISTS integration_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    integration_type ENUM('discord', 'google_calendar', 'webhook') NOT NULL,
    event_type ENUM('race_result', 'driver_registration', 'standings_update', 'upcoming_race', 'penalty_issued', 'calendar_sync') NOT NULL,
    status ENUM('success', 'failed', 'pending') NOT NULL,
    request_data JSON NULL,
    response_data JSON NULL,
    error_message TEXT NULL,
    processing_time_ms INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_integration_type (integration_type),
    INDEX idx_event_type (event_type),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Export logs table for tracking all export operations (v1.2.1)
CREATE TABLE IF NOT EXISTS export_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    export_type ENUM('csv', 'pdf', 'json') NOT NULL,
    data_type ENUM('results', 'standings', 'penalties', 'drivers', 'teams', 'races') NOT NULL,
    user_id INT NOT NULL,
    season_id INT NULL,
    race_id INT NULL,
    filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL DEFAULT 0,
    record_count INT NOT NULL DEFAULT 0,
    export_filters JSON NULL COMMENT 'Stores filter criteria used for export',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL COMMENT 'When file should be automatically deleted',
    downloaded_count INT DEFAULT 0,
    last_downloaded_at TIMESTAMP NULL,
    INDEX idx_export_type (export_type),
    INDEX idx_data_type (data_type),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
    FOREIGN KEY (race_id) REFERENCES races(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Export templates table for PDF customization (v1.2.1)
CREATE TABLE IF NOT EXISTS export_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    template_type ENUM('results', 'standings', 'penalties', 'summary') NOT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    header_content TEXT NULL COMMENT 'HTML/CSS for header customization',
    footer_content TEXT NULL COMMENT 'HTML/CSS for footer customization',
    logo_position ENUM('left', 'center', 'right') DEFAULT 'left',
    color_scheme JSON NULL COMMENT 'Primary colors for PDF styling',
    include_logos BOOLEAN DEFAULT TRUE,
    include_timestamps BOOLEAN DEFAULT TRUE,
    include_signatures BOOLEAN DEFAULT FALSE,
    page_orientation ENUM('portrait', 'landscape') DEFAULT 'portrait',
    font_family VARCHAR(50) DEFAULT 'Arial',
    font_size INT DEFAULT 10,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_default_per_type (template_type, is_default),
    INDEX idx_template_type (template_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Export settings table for user preferences (v1.2.1)
CREATE TABLE IF NOT EXISTS export_settings (
    user_id INT PRIMARY KEY,
    default_format ENUM('csv', 'pdf') DEFAULT 'csv',
    auto_cleanup_days INT DEFAULT 30 COMMENT 'Days after which exports are auto-deleted',
    include_personal_data BOOLEAN DEFAULT TRUE COMMENT 'Include email addresses, etc in exports',
    preferred_template_id INT NULL,
    csv_delimiter CHAR(1) DEFAULT ',',
    csv_encoding VARCHAR(20) DEFAULT 'UTF-8',
    pdf_watermark TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (preferred_template_id) REFERENCES export_templates(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Export queue table for background processing (v1.2.1)
CREATE TABLE IF NOT EXISTS export_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    export_type ENUM('csv', 'pdf', 'json') NOT NULL,
    data_type ENUM('results', 'standings', 'penalties', 'drivers', 'teams', 'races') NOT NULL,
    parameters JSON NOT NULL COMMENT 'Export parameters and filters',
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    progress_percentage TINYINT DEFAULT 0,
    error_message TEXT NULL,
    result_file_path VARCHAR(500) NULL,
    estimated_completion TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    INDEX idx_status (status),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default system settings
INSERT INTO settings (`key`, `value`, description, category, is_public) VALUES
-- General settings
('site_name', 'GridKing Racing League', 'Name of the racing league', 'general', TRUE),
('site_description', 'Professional SimRacing League Management', 'Description of the site', 'general', TRUE),
('league_logo', '', 'Path to league logo', 'branding', TRUE),
('primary_color', '#dc3545', 'Primary theme color', 'branding', TRUE),
('secondary_color', '#6c757d', 'Secondary theme color', 'branding', TRUE),
('timezone', 'Europe/Berlin', 'Default timezone for events', 'general', TRUE),

-- Points system
('points_system', 'f1', 'Default points system (f1, motogp, custom)', 'points', FALSE),
('pole_position_points', '0', 'Points awarded for pole position', 'points', FALSE),
('fastest_lap_points', '1', 'Points awarded for fastest lap', 'points', FALSE),
('points_for_sprint', '1', 'Enable points for sprint races', 'points', FALSE),

-- Registration settings
('registration_open', '1', 'Allow new user registration', 'registration', FALSE),
('require_approval', '0', 'Require admin approval for new drivers', 'registration', FALSE),
('max_drivers_per_season', '30', 'Maximum drivers per season', 'registration', FALSE),

-- Discord Integration (v1.2.0)
('discord_webhook', '', 'Discord webhook URL for notifications', 'integrations', FALSE),
('discord_bot_token', '', 'Discord bot token for advanced features', 'integrations', FALSE),
('notify_race_result', '1', 'Send Discord notifications for race results', 'integrations', FALSE),
('notify_driver_register', '1', 'Send Discord notifications for new drivers', 'integrations', FALSE),
('notify_standings_update', '1', 'Send Discord notifications for standings updates', 'integrations', FALSE),
('notify_upcoming_race', '1', 'Send Discord notifications for upcoming races', 'integrations', FALSE),
('notify_penalty_issued', '1', 'Send Discord notifications for penalties', 'integrations', FALSE),

-- Google Calendar Integration (v1.2.0)
('google_client_id', '', 'Google OAuth2 Client ID', 'integrations', FALSE),
('google_client_secret', '', 'Google OAuth2 Client Secret', 'integrations', FALSE),
('google_calendar_id', '', 'Google Calendar ID for race events', 'integrations', FALSE),
('google_access_token', '', 'Google OAuth2 Access Token', 'integrations', FALSE),
('google_refresh_token', '', 'Google OAuth2 Refresh Token', 'integrations', FALSE),
('calendar_sync_enabled', '0', 'Enable automatic calendar synchronization', 'integrations', FALSE),

-- Export System (v1.2.1)
('export_enabled', '1', 'Enable/disable export functionality', 'export', FALSE),
('export_max_records', '10000', 'Maximum number of records per export', 'export', FALSE),
('export_max_file_size', '50', 'Maximum export file size in MB', 'export', FALSE),
('export_retention_days', '30', 'Days to keep export files', 'export', FALSE),
('export_rate_limit', '5', 'Maximum exports per user per hour', 'export', FALSE),
('pdf_generation_enabled', '1', 'Enable/disable PDF export functionality', 'export', FALSE),
('csv_generation_enabled', '1', 'Enable/disable CSV export functionality', 'export', FALSE),

-- Security settings
('csrf_protection', '1', 'Enable CSRF protection', 'security', FALSE),
('session_timeout', '3600', 'Session timeout in seconds', 'security', FALSE),
('max_login_attempts', '5', 'Maximum login attempts before lockout', 'security', FALSE),
('lockout_duration', '900', 'Account lockout duration in seconds', 'security', FALSE),

-- Version tracking
('db_version', '1.2.1', 'Database schema version', 'system', FALSE),
('app_version', '1.2.1', 'Application version', 'system', TRUE),
('last_migration', NOW(), 'Timestamp of last database migration', 'system', FALSE);

-- Insert default export templates (v1.2.1)
INSERT INTO export_templates (name, template_type, is_default, color_scheme, page_orientation) VALUES
('Default Race Results', 'results', TRUE, '{"primary": "#007bff", "secondary": "#6c757d", "success": "#28a745"}', 'landscape'),
('Default Standings', 'standings', TRUE, '{"primary": "#ffc107", "secondary": "#6c757d", "accent": "#dc3545"}', 'portrait'),
('Default Penalties', 'penalties', TRUE, '{"primary": "#dc3545", "secondary": "#6c757d", "warning": "#ffc107"}', 'portrait'),
('Default Summary', 'summary', TRUE, '{"primary": "#17a2b8", "secondary": "#6c757d", "info": "#007bff"}', 'portrait');

-- Insert default session types
INSERT INTO session_types (name, code, is_default, display_order) VALUES
('Free Practice 1', 'fp1', TRUE, 1),
('Free Practice 2', 'fp2', TRUE, 2),
('Free Practice 3', 'fp3', TRUE, 3),
('Qualifying 1', 'q1', TRUE, 4),
('Qualifying 2', 'q2', TRUE, 5),
('Qualifying 3', 'q3', TRUE, 6),
('Sprint Qualifying', 'sprint_quali', TRUE, 7),
('Sprint Race', 'sprint', TRUE, 8),
('Feature Race', 'feature', TRUE, 9);

-- Insert sample data
-- Sample admin user (password: admin123)
INSERT INTO users (username, email, password_hash, role, verified) VALUES
('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', TRUE);

-- Sample season
INSERT INTO seasons (name, year, is_active, start_date, end_date, description) VALUES
('2025 Championship Season', 2025, TRUE, '2025-04-01', '2025-12-31', 'The inaugural season of our SimRacing league');

-- Sample teams
INSERT INTO teams (name, logo, created_by) VALUES
('Red Bull Racing', NULL, 1),
('Mercedes AMG F1', NULL, 1),
('Scuderia Ferrari', NULL, 1);

-- Sample drivers (you'll need to create user accounts first)
INSERT INTO users (username, email, password_hash, role, verified) VALUES
('driver1', 'driver1@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'driver', TRUE),
('driver2', 'driver2@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'driver', TRUE),
('driver3', 'driver3@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'driver', TRUE);

-- Insert sample drivers
INSERT INTO drivers (user_id, team_id, driver_number, platform, country, bio) VALUES
(2, 1, 33, 'PC', 'NLD', 'Experienced simracer with 5+ years in competitive leagues'),
(3, 2, 44, 'PC', 'GBR', 'Former real-world karting champion turned simracer'),
(4, 3, 16, 'Xbox', 'ITA', 'Rising talent in the simracing community');

-- Sample races
INSERT INTO races (season_id, name, track, race_date, format, laps, status, description) VALUES 
(1, 'Bahrain Grand Prix', 'Bahrain International Circuit', '2025-04-15 15:00:00', 'Feature', 52, 'scheduled', 'Season opener in the desert'),
(1, 'Spanish Grand Prix', 'Circuit de Barcelona-Catalunya', '2025-04-29 15:00:00', 'Feature', 66, 'scheduled', 'Technical challenge in Barcelona'),
(1, 'Monaco Grand Prix', 'Circuit de Monaco', '2025-05-13 15:00:00', 'Feature', 78, 'scheduled', 'The jewel in the crown of motorsport');

-- Add race sessions for Bahrain GP (race_id = 1)
INSERT INTO race_sessions (race_id, session_type_id, session_order, enabled) VALUES
(1, 1, 1, TRUE),  -- FP1
(1, 2, 2, TRUE),  -- FP2
(1, 3, 3, TRUE),  -- FP3
(1, 4, 4, TRUE),  -- Q1
(1, 5, 5, TRUE),  -- Q2
(1, 6, 6, TRUE),  -- Q3
(1, 9, 7, TRUE);  -- Feature Race

-- Add race sessions for Spanish GP (race_id = 2)
INSERT INTO race_sessions (race_id, session_type_id, session_order, enabled) VALUES
(2, 1, 1, TRUE),  -- FP1
(2, 2, 2, TRUE),  -- FP2
(2, 7, 3, TRUE),  -- Sprint Qualifying
(2, 8, 4, TRUE),  -- Sprint Race
(2, 9, 5, TRUE);  -- Feature Race

-- Add race sessions for Monaco GP (race_id = 3)
INSERT INTO race_sessions (race_id, session_type_id, session_order, enabled) VALUES
(3, 1, 1, TRUE),  -- FP1
(3, 2, 2, TRUE),  -- FP2
(3, 3, 3, TRUE),  -- FP3
(3, 4, 4, TRUE),  -- Q1
(3, 5, 5, TRUE),  -- Q2
(3, 6, 6, TRUE),  -- Q3
(3, 9, 7, TRUE);  -- Feature Race

-- Sample news article
INSERT INTO news (title, content, author_id, published, featured) VALUES
('Welcome to GridKing Racing League!', 
'We are excited to announce the launch of our new SimRacing league management system. With advanced features like Discord integration, export capabilities, and comprehensive race management, we are ready for an amazing 2025 season!', 
1, TRUE, TRUE);

-- Create triggers for export cleanup (v1.2.1)
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS export_cleanup_trigger 
AFTER INSERT ON export_logs
FOR EACH ROW
BEGIN
    -- Set expiration date based on settings
    UPDATE export_logs 
    SET expires_at = DATE_ADD(NEW.created_at, INTERVAL (
        SELECT COALESCE(
            (SELECT auto_cleanup_days FROM export_settings WHERE user_id = NEW.user_id),
            (SELECT CAST(value AS UNSIGNED) FROM settings WHERE `key` = 'export_retention_days'),
            30
        ) DAY
    ))
    WHERE id = NEW.id AND expires_at IS NULL;
END$$
DELIMITER ;

-- Create view for export statistics (v1.2.1)
CREATE OR REPLACE VIEW export_statistics AS
SELECT 
    u.username,
    el.export_type,
    el.data_type,
    COUNT(*) as total_exports,
    SUM(el.file_size) as total_size_bytes,
    SUM(el.record_count) as total_records,
    AVG(el.file_size) as avg_file_size,
    MIN(el.created_at) as first_export,
    MAX(el.created_at) as last_export,
    SUM(el.downloaded_count) as total_downloads
FROM export_logs el
JOIN users u ON el.user_id = u.id
WHERE el.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
GROUP BY u.username, el.export_type, el.data_type;

-- Final setup message
SELECT 'GridKing Racing League Database v1.2.1 setup completed successfully!' as Status,
       'Features: Core System + Discord Integration + Google Calendar + Export System' as Features,
       'Ready for production use!' as Message;