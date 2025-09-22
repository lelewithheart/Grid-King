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

CREATE TABLE IF NOT EXISTS user_roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role_name VARCHAR(50) UNIQUE NOT NULL,
    role_code VARCHAR(20) UNIQUE NOT NULL,
    description TEXT,
    permissions JSON NOT NULL DEFAULT '[]',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_role_code (role_code),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User role assignments table
CREATE TABLE IF NOT EXISTS user_role_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    season_id INT NULL,
    assigned_by INT,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    INDEX idx_user_id (user_id),
    INDEX idx_role_id (role_id),
    INDEX idx_season_id (season_id),
    INDEX idx_active (is_active),
    UNIQUE KEY unique_user_role_season (user_id, role_id, season_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES user_roles(id) ON DELETE CASCADE,
    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Race incidents table for detailed incident tracking
CREATE TABLE IF NOT EXISTS race_incidents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    race_id INT NOT NULL,
    incident_type ENUM('collision', 'track_limits', 'unsafe_driving', 'blocking', 'false_start', 'technical', 'other') NOT NULL,
    incident_title VARCHAR(255) NOT NULL,
    incident_description TEXT NOT NULL,
    incident_lap INT,
    incident_time TIME,
    incident_sector TINYINT, -- 1, 2, 3 for track sectors
    incident_turn VARCHAR(50), -- Turn name or number
    drivers_involved JSON NOT NULL DEFAULT '[]', -- Array of driver IDs
    reported_by INT, -- User who reported the incident
    steward_assigned INT, -- Steward assigned to investigate
    status ENUM('reported', 'investigating', 'under_review', 'penalty_issued', 'no_action', 'dismissed') DEFAULT 'reported',
    severity ENUM('minor', 'major', 'severe', 'dangerous') DEFAULT 'minor',
    evidence_files JSON DEFAULT '[]', -- Array of file paths for photos/videos
    weather_conditions VARCHAR(100),
    track_conditions ENUM('dry', 'wet', 'mixed') DEFAULT 'dry',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    INDEX idx_race_id (race_id),
    INDEX idx_incident_type (incident_type),
    INDEX idx_status (status),
    INDEX idx_severity (severity),
    INDEX idx_steward_assigned (steward_assigned),
    INDEX idx_reported_by (reported_by),
    FOREIGN KEY (race_id) REFERENCES races(id) ON DELETE CASCADE,
    FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (steward_assigned) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Steward decisions table for tracking all steward actions
CREATE TABLE IF NOT EXISTS steward_decisions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    incident_id INT NOT NULL,
    steward_id INT NOT NULL,
    decision_type ENUM('no_action', 'warning', 'time_penalty', 'grid_penalty', 'points_deduction', 'disqualification', 'investigation_continues') NOT NULL,
    decision_summary VARCHAR(500) NOT NULL,
    decision_reasoning TEXT NOT NULL,
    penalty_value INT DEFAULT 0, -- seconds, grid positions, or championship points
    penalty_target JSON DEFAULT '[]', -- Array of driver IDs affected
    precedent_reference VARCHAR(255), -- Reference to similar past decisions
    appeal_deadline TIMESTAMP NULL,
    is_final BOOLEAN DEFAULT FALSE,
    decision_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT, -- Senior steward/race director who reviewed
    review_date TIMESTAMP NULL,
    INDEX idx_incident_id (incident_id),
    INDEX idx_steward_id (steward_id),
    INDEX idx_decision_type (decision_type),
    INDEX idx_decision_date (decision_date),
    INDEX idx_is_final (is_final),
    FOREIGN KEY (incident_id) REFERENCES race_incidents(id) ON DELETE CASCADE,
    FOREIGN KEY (steward_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Appeals table for driver appeals against steward decisions
CREATE TABLE IF NOT EXISTS penalty_appeals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    decision_id INT NOT NULL,
    appealing_driver_id INT NOT NULL,
    appeal_reason TEXT NOT NULL,
    supporting_evidence JSON DEFAULT '[]', -- File paths for additional evidence
    appeal_status ENUM('submitted', 'under_review', 'hearing_scheduled', 'upheld', 'overturned', 'dismissed') DEFAULT 'submitted',
    appeal_fee_paid BOOLEAN DEFAULT FALSE,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    review_deadline TIMESTAMP NULL,
    hearing_date TIMESTAMP NULL,
    appeal_committee JSON DEFAULT '[]', -- Array of steward user IDs
    committee_decision TEXT NULL,
    committee_reasoning TEXT NULL,
    final_decision_date TIMESTAMP NULL,
    processed_by INT, -- Appeal coordinator
    INDEX idx_decision_id (decision_id),
    INDEX idx_appealing_driver (appealing_driver_id),
    INDEX idx_appeal_status (appeal_status),
    INDEX idx_submitted_at (submitted_at),
    FOREIGN KEY (decision_id) REFERENCES steward_decisions(id) ON DELETE CASCADE,
    FOREIGN KEY (appealing_driver_id) REFERENCES drivers(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Steward notes table for general race observations
CREATE TABLE IF NOT EXISTS steward_notes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    race_id INT NOT NULL,
    steward_id INT NOT NULL,
    note_type ENUM('general', 'driver_warning', 'track_condition', 'safety_concern', 'rule_clarification') DEFAULT 'general',
    note_title VARCHAR(255) NOT NULL,
    note_content TEXT NOT NULL,
    related_lap INT NULL,
    related_driver_id INT NULL,
    visibility ENUM('public', 'stewards_only', 'race_director_only') DEFAULT 'stewards_only',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_race_id (race_id),
    INDEX idx_steward_id (steward_id),
    INDEX idx_note_type (note_type),
    INDEX idx_priority (priority),
    INDEX idx_visibility (visibility),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (race_id) REFERENCES races(id) ON DELETE CASCADE,
    FOREIGN KEY (steward_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (related_driver_id) REFERENCES drivers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Committee votes table for appeal decisions
CREATE TABLE IF NOT EXISTS committee_votes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    appeal_id INT NOT NULL,
    steward_id INT NOT NULL,
    vote ENUM('uphold', 'overturn', 'abstain') NOT NULL,
    vote_reasoning TEXT,
    cast_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_appeal_id (appeal_id),
    INDEX idx_steward_id (steward_id),
    UNIQUE KEY unique_appeal_steward (appeal_id, steward_id),
    FOREIGN KEY (appeal_id) REFERENCES penalty_appeals(id) ON DELETE CASCADE,
    FOREIGN KEY (steward_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Race control sessions table for live race management
CREATE TABLE IF NOT EXISTS race_control_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    race_id INT NOT NULL,
    session_type_id INT NOT NULL,
    race_director_id INT NOT NULL,
    session_start TIMESTAMP NULL,
    session_end TIMESTAMP NULL,
    status ENUM('scheduled', 'active', 'red_flag', 'safety_car', 'completed', 'abandoned') DEFAULT 'scheduled',
    current_lap INT DEFAULT 0,
    total_laps INT,
    safety_car_active BOOLEAN DEFAULT FALSE,
    red_flag_active BOOLEAN DEFAULT FALSE,
    weather_update VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_race_id (race_id),
    INDEX idx_session_type_id (session_type_id),
    INDEX idx_race_director_id (race_director_id),
    INDEX idx_status (status),
    FOREIGN KEY (race_id) REFERENCES races(id) ON DELETE CASCADE,
    FOREIGN KEY (session_type_id) REFERENCES session_types(id) ON DELETE CASCADE,
    FOREIGN KEY (race_director_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Evidence files table for incident documentation
CREATE TABLE IF NOT EXISTS incident_evidence (
    id INT PRIMARY KEY AUTO_INCREMENT,
    incident_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type ENUM('image', 'video', 'document', 'audio') NOT NULL,
    file_size INT NOT NULL,
    description TEXT,
    uploaded_by INT NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_primary BOOLEAN DEFAULT FALSE,
    visibility ENUM('stewards_only', 'public', 'parties_involved') DEFAULT 'stewards_only',
    INDEX idx_incident_id (incident_id),
    INDEX idx_uploaded_by (uploaded_by),
    INDEX idx_file_type (file_type),
    INDEX idx_uploaded_at (uploaded_at),
    FOREIGN KEY (incident_id) REFERENCES race_incidents(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default user roles
INSERT INTO user_roles (role_name, role_code, description, permissions) VALUES
('Administrator', 'admin', 'Full system administration access', '["all"]'),
('Race Director', 'race_director', 'Race event management and oversight', '["manage_races", "assign_stewards", "final_decisions", "view_all_incidents", "manage_sessions"]'),
('Chief Steward', 'chief_steward', 'Senior steward with review authority', '["investigate_incidents", "issue_penalties", "review_decisions", "manage_appeals", "assign_stewards"]'),
('Steward', 'steward', 'Race incident investigation and penalties', '["investigate_incidents", "issue_penalties", "create_notes", "view_incidents"]'),
('Steward Trainee', 'steward_trainee', 'Steward in training with limited access', '["view_incidents", "create_notes", "assist_investigations"]'),
('Driver Representative', 'driver_rep', 'Driver liaison for appeals and protests', '["submit_appeals", "view_public_decisions", "driver_advocacy"]');

-- Add steward-related settings
INSERT INTO settings (`key`, `value`, description, category, is_public) VALUES
('steward_system_enabled', '1', 'Enable/disable steward incident management system', 'steward', FALSE),
('incident_evidence_max_size', '50', 'Maximum file size for incident evidence in MB', 'steward', FALSE),
('incident_evidence_types', 'jpg,jpeg,png,gif,mp4,mov,avi,pdf,doc,docx', 'Allowed file types for incident evidence', 'steward', FALSE),
('appeal_deadline_hours', '72', 'Hours after penalty to submit appeal', 'steward', FALSE),
('appeal_fee_amount', '0', 'Appeal fee in league currency (0 = no fee)', 'steward', FALSE),
('steward_notification_discord', '1', 'Send Discord notifications for steward actions', 'steward', FALSE),
('incident_auto_assign', '1', 'Automatically assign incidents to available stewards', 'steward', FALSE),
('race_director_required', '1', 'Require race director assignment for sessions', 'steward', FALSE);

-- Add steward report templates to export templates
INSERT INTO export_templates (name, template_type, is_default, color_scheme, page_orientation) VALUES
('Steward Incident Report', 'penalties', FALSE, '{"primary": "#dc3545", "secondary": "#6c757d", "warning": "#ffc107", "info": "#17a2b8"}', 'portrait'),
('Race Control Summary', 'summary', FALSE, '{"primary": "#007bff", "secondary": "#6c757d", "success": "#28a745", "warning": "#ffc107"}', 'landscape');

-- Create triggers for steward notifications
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS incident_notification_trigger 
AFTER INSERT ON race_incidents
FOR EACH ROW
BEGIN
    -- Log the incident creation for Discord notification
    INSERT INTO integration_logs (integration_type, event_type, status, request_data, created_at)
    VALUES ('discord', 'incident_reported', 'pending', 
            JSON_OBJECT('incident_id', NEW.id, 'race_id', NEW.race_id, 'type', NEW.incident_type), 
            NOW());
END$$

CREATE TRIGGER IF NOT EXISTS penalty_decision_trigger 
AFTER INSERT ON steward_decisions
FOR EACH ROW
BEGIN
    -- Log the penalty decision for Discord notification
    INSERT INTO integration_logs (integration_type, event_type, status, request_data, created_at)
    VALUES ('discord', 'penalty_decision', 'pending', 
            JSON_OBJECT('decision_id', NEW.id, 'incident_id', NEW.incident_id, 'decision_type', NEW.decision_type), 
            NOW());
END$$
DELIMITER ;

-- Create views for steward statistics
CREATE OR REPLACE VIEW steward_statistics AS
SELECT 
    u.id as steward_id,
    u.username as steward_name,
    COUNT(DISTINCT ri.id) as incidents_handled,
    COUNT(DISTINCT sd.id) as decisions_made,
    COUNT(DISTINCT CASE WHEN sd.decision_type != 'no_action' THEN sd.id END) as penalties_issued,
    COUNT(DISTINCT pa.id) as appeals_received,
    COUNT(DISTINCT CASE WHEN pa.appeal_status = 'overturned' THEN pa.id END) as appeals_overturned,
    AVG(TIMESTAMPDIFF(HOUR, ri.created_at, ri.resolved_at)) as avg_resolution_hours,
    MIN(ri.created_at) as first_case_date,
    MAX(ri.resolved_at) as last_case_date
FROM users u
LEFT JOIN race_incidents ri ON ri.steward_assigned = u.id
LEFT JOIN steward_decisions sd ON sd.steward_id = u.id
LEFT JOIN penalty_appeals pa ON pa.decision_id = sd.id
WHERE u.id IN (SELECT DISTINCT user_id FROM user_role_assignments WHERE role_id IN (
    SELECT id FROM user_roles WHERE role_code IN ('steward', 'chief_steward', 'race_director')
))
GROUP BY u.id, u.username;

-- Create view for incident summary
CREATE OR REPLACE VIEW incident_summary AS
SELECT 
    ri.id,
    ri.race_id,
    r.name as race_name,
    ri.incident_type,
    ri.incident_title,
    ri.status,
    ri.severity,
    u_reported.username as reported_by_name,
    u_steward.username as steward_name,
    sd.decision_type,
    sd.is_final,
    COUNT(pa.id) as appeal_count,
    ri.created_at,
    ri.resolved_at
FROM race_incidents ri
LEFT JOIN races r ON ri.race_id = r.id
LEFT JOIN users u_reported ON ri.reported_by = u_reported.id
LEFT JOIN users u_steward ON ri.steward_assigned = u_steward.id
LEFT JOIN steward_decisions sd ON ri.id = sd.incident_id
LEFT JOIN penalty_appeals pa ON sd.id = pa.decision_id
GROUP BY ri.id, ri.race_id, r.name, ri.incident_type, ri.incident_title, ri.status, ri.severity, 
         u_reported.username, u_steward.username, sd.decision_type, sd.is_final, ri.created_at, ri.resolved_at;

-- Update database version
UPDATE settings SET `value` = '1.2.2' WHERE `key` = 'db_version';
UPDATE settings SET `value` = '1.2.2' WHERE `key` = 'app_version';
UPDATE settings SET `value` = NOW() WHERE `key` = 'last_migration';

-- Final setup message
SELECT 'GridKing Racing League Database v1.2.2 setup completed successfully!' as Status,
       'Features: Core System + Discord Integration + Google Calendar + Export System' as Features,
       'Ready for production use!' as Message;