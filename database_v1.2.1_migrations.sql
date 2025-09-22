-- GridKing v1.2.1 Database Migration
-- Export & Reporting System

-- Create export logs table for tracking all export operations
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

-- Create export templates table for PDF customization
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

-- Insert default PDF templates
INSERT INTO export_templates (name, template_type, is_default, color_scheme, page_orientation) VALUES
('Default Race Results', 'results', TRUE, '{"primary": "#007bff", "secondary": "#6c757d", "success": "#28a745"}', 'landscape'),
('Default Standings', 'standings', TRUE, '{"primary": "#ffc107", "secondary": "#6c757d", "accent": "#dc3545"}', 'portrait'),
('Default Penalties', 'penalties', TRUE, '{"primary": "#dc3545", "secondary": "#6c757d", "warning": "#ffc107"}', 'portrait'),
('Default Summary', 'summary', TRUE, '{"primary": "#17a2b8", "secondary": "#6c757d", "info": "#007bff"}', 'portrait');

-- Create export settings table for user preferences
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

-- Create export queue table for background processing
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

-- Add export permissions to existing permissions system
INSERT IGNORE INTO settings (`key`, `value`, description) VALUES
('export_enabled', '1', 'Enable/disable export functionality'),
('export_max_records', '10000', 'Maximum number of records per export'),
('export_max_file_size', '50', 'Maximum export file size in MB'),
('export_retention_days', '30', 'Days to keep export files'),
('export_rate_limit', '5', 'Maximum exports per user per hour'),
('pdf_generation_enabled', '1', 'Enable/disable PDF export functionality'),
('csv_generation_enabled', '1', 'Enable/disable CSV export functionality');

-- Create indexes for better export query performance
CREATE INDEX IF NOT EXISTS idx_race_results_race_season ON race_results(race_id, driver_id);
CREATE INDEX IF NOT EXISTS idx_race_results_points ON race_results(points, position);
CREATE INDEX IF NOT EXISTS idx_penalties_race_driver ON penalties(race_id, driver_id, created_at);
CREATE INDEX IF NOT EXISTS idx_drivers_active ON drivers(user_id, created_at);

-- Add export tracking trigger for automatic cleanup
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

-- Create view for export statistics
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

-- Add version tracking
INSERT INTO settings (`key`, `value`, description) VALUES
('db_version', '1.2.1', 'Database schema version for export system')
ON DUPLICATE KEY UPDATE `value` = '1.2.1';
