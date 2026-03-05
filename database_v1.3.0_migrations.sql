-- GridKing Racing League Management System
-- Database Migration v1.3.0 - Settings & Migration
-- Upgrade from v1.2.2 to v1.3.0

USE racing_league;

-- ============================================================
-- 1.3.0 – Global Config & Setup
-- ============================================================

-- Feature toggles table for modular on/off switches
CREATE TABLE IF NOT EXISTS feature_toggles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    feature_code VARCHAR(50) UNIQUE NOT NULL,
    feature_name VARCHAR(100) NOT NULL,
    description TEXT,
    is_enabled BOOLEAN DEFAULT TRUE,
    category VARCHAR(50) DEFAULT 'general',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (feature_code),
    INDEX idx_enabled (is_enabled),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default feature toggles
INSERT INTO feature_toggles (feature_code, feature_name, description, is_enabled, category) VALUES
('qualifying', 'Qualifying Sessions', 'Enable qualifying session tracking and results', TRUE, 'race_format'),
('sprint_races', 'Sprint Races', 'Enable sprint race format and points', TRUE, 'race_format'),
('practice_sessions', 'Practice Sessions', 'Enable free practice session tracking', TRUE, 'race_format'),
('fantasy_mode', 'Fantasy Mode', 'Enable fantasy league functionality for drivers', FALSE, 'features'),
('driver_ratings', 'Driver Ratings', 'Enable community driver rating system', FALSE, 'features'),
('team_standings', 'Team Standings', 'Show constructor/team championship standings', TRUE, 'features'),
('driver_profiles', 'Public Driver Profiles', 'Allow public access to driver profile pages', TRUE, 'features'),
('appeals_system', 'Appeals System', 'Allow drivers to appeal penalties and steward decisions', TRUE, 'moderation'),
('user_registration', 'User Registration', 'Allow new users to register on the platform', TRUE, 'access'),
('spectator_access', 'Spectator Access', 'Allow non-registered users to view public pages', TRUE, 'access')
ON DUPLICATE KEY UPDATE feature_name = VALUES(feature_name);

-- ============================================================
-- 1.3.1 – Appearance & Themes
-- ============================================================

-- Language/translation files support
CREATE TABLE IF NOT EXISTS languages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(10) UNIQUE NOT NULL,
    name VARCHAR(50) NOT NULL,
    native_name VARCHAR(50) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    is_default BOOLEAN DEFAULT FALSE,
    flag_icon VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_default (is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO languages (code, name, native_name, is_active, is_default, flag_icon) VALUES
('en', 'English', 'English', TRUE, TRUE, 'gb'),
('de', 'German', 'Deutsch', TRUE, FALSE, 'de'),
('fr', 'French', 'Français', TRUE, FALSE, 'fr'),
('es', 'Spanish', 'Español', TRUE, FALSE, 'es'),
('it', 'Italian', 'Italiano', TRUE, FALSE, 'it'),
('pt', 'Portuguese', 'Português', TRUE, FALSE, 'pt'),
('nl', 'Dutch', 'Nederlands', TRUE, FALSE, 'nl'),
('pl', 'Polish', 'Polski', TRUE, FALSE, 'pl')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ============================================================
-- 1.3.2 – Migration System
-- ============================================================

-- Migration exports table – tracks full-league exports with tokens
CREATE TABLE IF NOT EXISTS migration_exports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    export_token VARCHAR(64) UNIQUE NOT NULL,
    export_format ENUM('zip', 'json', 'gklm') NOT NULL DEFAULT 'json',
    created_by INT NOT NULL,
    file_path VARCHAR(500),
    file_size BIGINT DEFAULT 0,
    app_version VARCHAR(20) NOT NULL,
    db_version VARCHAR(20) NOT NULL,
    league_name VARCHAR(255),
    metadata JSON,
    status ENUM('pending', 'processing', 'completed', 'failed', 'expired') DEFAULT 'pending',
    is_encrypted BOOLEAN DEFAULT FALSE,
    download_count INT DEFAULT 0,
    last_downloaded_at TIMESTAMP NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (export_token),
    INDEX idx_status (status),
    INDEX idx_created_by (created_by),
    INDEX idx_expires (expires_at),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- New settings for 1.3 features
-- ============================================================

INSERT INTO settings (`key`, `value`, description, category, is_public) VALUES
-- Theme & Appearance
('theme_mode', 'light', 'UI theme mode: light, dark, or auto', 'appearance', TRUE),
('custom_css', '', 'Custom CSS injected on every page', 'appearance', FALSE),
('announcement_bar_enabled', '0', 'Show announcement bar at top of every page', 'appearance', TRUE),
('announcement_bar_text', '', 'Text content of the announcement bar', 'appearance', TRUE),
('announcement_bar_color', '#0d6efd', 'Background color of the announcement bar', 'appearance', TRUE),
('announcement_bar_dismissible', '1', 'Allow users to dismiss the announcement bar', 'appearance', TRUE),

-- i18n
('default_language', 'en', 'Default site language code', 'localization', TRUE),
('available_languages', 'en', 'Comma-separated list of enabled language codes', 'localization', TRUE),

-- Setup wizard
('setup_completed', '0', 'Whether the initial setup wizard has been completed', 'system', FALSE),
('setup_completed_at', '', 'Timestamp when setup wizard was completed', 'system', FALSE),

-- Migration system
('migration_export_token_ttl', '86400', 'Export token time-to-live in seconds (default: 24h)', 'migration', FALSE),
('migration_allow_json_export', '1', 'Allow JSON format full-league exports', 'migration', FALSE),
('migration_allow_zip_export', '1', 'Allow ZIP format full-league exports', 'migration', FALSE)
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- ============================================================
-- Version bump to 1.3.0
-- ============================================================
UPDATE settings SET `value` = '1.3.0' WHERE `key` = 'db_version';
UPDATE settings SET `value` = '1.3.0' WHERE `key` = 'app_version';
UPDATE settings SET `value` = NOW()   WHERE `key` = 'last_migration';
