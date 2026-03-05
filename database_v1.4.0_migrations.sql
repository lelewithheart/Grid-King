-- GridKing Racing League Management System
-- Database Migration v1.4.0 - Final Polish & LTS
-- Upgrade from v1.3.x to v1.4.0

USE racing_league;

-- ============================================================
-- 1.4.0 – Plugin Support
-- ============================================================

-- Plugins registry table
CREATE TABLE IF NOT EXISTS plugins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    plugin_id VARCHAR(100) UNIQUE NOT NULL,
    plugin_name VARCHAR(255),
    plugin_version VARCHAR(50) DEFAULT '1.0.0',
    author VARCHAR(255),
    description TEXT,
    is_enabled BOOLEAN DEFAULT TRUE,
    config JSON,
    installed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_plugin_id (plugin_id),
    INDEX idx_enabled (is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Plugin hooks/events log (for debugging)
CREATE TABLE IF NOT EXISTS plugin_events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    plugin_id VARCHAR(100) NOT NULL,
    hook_name VARCHAR(100) NOT NULL,
    event_data JSON,
    execution_time_ms INT,
    status ENUM('success', 'error', 'skipped') DEFAULT 'success',
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_plugin (plugin_id),
    INDEX idx_hook (hook_name),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add plugin-related feature toggles
INSERT INTO feature_toggles (feature_code, feature_name, description, is_enabled, category) VALUES
('plugins', 'Plugin System', 'Enable loading and execution of plugins from /plugins directory', TRUE, 'system'),
('lite_mode', 'Lite Mode', 'Disable plugins and optional features for improved performance on limited servers', FALSE, 'system')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- ============================================================
-- 1.4.1 – Admin Tools
-- ============================================================

-- Audit log table for admin actions
CREATE TABLE IF NOT EXISTS audit_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action_type VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    details JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_action (action_type),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_created (created_at),
    INDEX idx_ip (ip_address),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add archived_at column to seasons for archival support
ALTER TABLE seasons ADD COLUMN IF NOT EXISTS archived_at TIMESTAMP NULL;

-- Add debug mode setting
INSERT INTO settings (`key`, `value`, description, category, is_public) VALUES
('debug_mode', '0', 'Enable debug mode for verbose logging and error display', 'system', FALSE),
('audit_log_retention_days', '90', 'Number of days to retain audit log entries', 'system', FALSE),
('export_retention_days', '30', 'Number of days to retain export files', 'system', FALSE)
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- ============================================================
-- 1.4.2 – Final Migration Prep
-- ============================================================

-- Migration status tracking
CREATE TABLE IF NOT EXISTS migration_status (
    id INT PRIMARY KEY AUTO_INCREMENT,
    migration_type ENUM('export', 'import', 'sandbox_test') NOT NULL,
    status ENUM('pending', 'in_progress', 'completed', 'failed') DEFAULT 'pending',
    source_version VARCHAR(20),
    target_version VARCHAR(20),
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    records_processed INT DEFAULT 0,
    records_total INT DEFAULT 0,
    error_log TEXT,
    metadata JSON,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (migration_type),
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- LTS support marker
INSERT INTO settings (`key`, `value`, description, category, is_public) VALUES
('lts_version', '1.4', 'Long Term Support version series', 'system', TRUE),
('lts_support_until', '2026-12-31', 'LTS support end date', 'system', TRUE),
('migration_format_version', '1.4.0', 'Current export format version', 'migration', FALSE),
('v2_migration_url', 'https://gridking.io/migrate', 'URL for v2.0 hosted platform migration', 'migration', TRUE)
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- ============================================================
-- Version bump to 1.4.0
-- ============================================================

-- Update version in settings
UPDATE settings SET `value` = '1.4.0' WHERE `key` = 'db_version';
UPDATE settings SET `value` = '1.4.0' WHERE `key` = 'app_version';
UPDATE settings SET `value` = NOW() WHERE `key` = 'last_migration';

-- Insert migration completion record
INSERT INTO audit_log (user_id, action_type, details, ip_address, created_at)
VALUES (NULL, 'database_migration', '{"from_version": "1.3.x", "to_version": "1.4.0", "migration_type": "schema_update"}', 'system', NOW());

-- ============================================================
-- Migration Complete
-- ============================================================
-- GridKing Legacy 1.4.0 - Final Polish & LTS
-- This is the final feature release of the Legacy (self-hosted) version.
-- Future updates will be security patches and bug fixes only.
-- For new features, migrate to GridKing v2.0 (Hosted Platform).
