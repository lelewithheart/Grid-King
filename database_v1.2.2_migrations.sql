-- GridKing Racing League Management System
-- Database Migration v1.2.2 - Steward Logs & Race Management
-- Upgrade from v1.2.1 to v1.2.2

USE racing_league;

-- User roles table for role-based permissions
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

-- Final confirmation
SELECT 'GridKing v1.2.2 migration completed successfully!' as Status,
       'New features: Steward Logs, Incident Management, Role Permissions, Appeals System' as Features;
