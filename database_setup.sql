-- Racing League Management System Database Schema
-- Created for PHP 8.2 + MariaDB 10.11

DROP DATABASE IF EXISTS racing_league;
CREATE DATABASE racing_league;
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
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Teams table
CREATE TABLE IF NOT EXISTS teams (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,
    logo VARCHAR(255),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

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
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL
);

-- Seasons table  
CREATE TABLE IF NOT EXISTS seasons (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    year INT NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    points_system JSON, -- Store points configuration as JSON
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Races table
CREATE TABLE IF NOT EXISTS races (
    id INT PRIMARY KEY AUTO_INCREMENT,
    season_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    track VARCHAR(100) NOT NULL,
    track_image VARCHAR(255),
    race_date DATETIME NOT NULL,
    format ENUM('Sprint', 'Feature', 'Endurance') DEFAULT 'Feature',
    laps INT,
    status ENUM('Scheduled', 'In Progress', 'Completed', 'Cancelled') DEFAULT 'Scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE
);

-- Session Types table (for different session formats)
CREATE TABLE IF NOT EXISTS session_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    code VARCHAR(32) NOT NULL UNIQUE, -- e.g. 'fp1', 'q1', 'feature'
    is_default BOOLEAN DEFAULT 0
);

-- Race Sessions table (to manage different sessions within a Race) 
CREATE TABLE IF NOT EXISTS race_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    race_id INT NOT NULL,
    session_type_id INT NOT NULL,
    session_order INT DEFAULT 0,
    enabled BOOLEAN DEFAULT 1,
    FOREIGN KEY (race_id) REFERENCES races(id) ON DELETE CASCADE,
    FOREIGN KEY (session_type_id) REFERENCES session_types(id) ON DELETE CASCADE
);

-- Race Results table (the heart of standings calculation)
CREATE TABLE IF NOT EXISTS race_results (
    id INT PRIMARY KEY AUTO_INCREMENT,
    race_id INT NOT NULL,
    driver_id INT NOT NULL,
    race_session_id INT DEFAULT NULL,
    attendance ENUM('Present','Absent','Excused') DEFAULT 'Present',
    position INT, -- NULL for DNF/DNS
    points INT DEFAULT 0,
    fastest_lap BOOLEAN DEFAULT FALSE,
    pole_position BOOLEAN DEFAULT FALSE,
    dnf BOOLEAN DEFAULT FALSE,
    dnf_reason VARCHAR(255),
    time_penalty INT DEFAULT 0, -- seconds
    points_penalty INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (race_id) REFERENCES races(id) ON DELETE CASCADE,
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE,
    FOREIGN KEY (race_session_id) REFERENCES race_sessions(id) ON DELETE SET NULL,
    UNIQUE KEY unique_race_driver_session (race_id, driver_id, race_session_id)
);

-- Race Registrations table (for drivers to register/attend races)
CREATE TABLE IF NOT EXISTS race_registrations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    race_id INT NOT NULL,
    driver_id INT NOT NULL,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_race_driver (race_id, driver_id),
    FOREIGN KEY (race_id) REFERENCES races(id) ON DELETE CASCADE,
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE
);

-- Penalties table (separate from race results for tracking)
CREATE TABLE IF NOT EXISTS penalties (
    id INT PRIMARY KEY AUTO_INCREMENT,
    driver_id INT NOT NULL,
    race_id INT,
    type ENUM('Time Penalty', 'Points Deduction', 'Grid Drop', 'Warning', 'Disqualification') NOT NULL,
    value INT, -- seconds for time penalty, points for deduction, etc.
    reason TEXT NOT NULL,
    applied_by INT, -- admin user who applied penalty
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE,
    FOREIGN KEY (race_id) REFERENCES races(id) ON DELETE CASCADE,
    FOREIGN KEY (applied_by) REFERENCES users(id) ON DELETE SET NULL
);

-- News articles table
CREATE TABLE IF NOT EXISTS news (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    image VARCHAR(255),
    author_id INT,
    published BOOLEAN DEFAULT FALSE,
    featured BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Announcements table
CREATE TABLE IF NOT EXISTS announcements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    type ENUM('General', 'Race', 'Penalty', 'Championship') DEFAULT 'General',
    priority ENUM('Low', 'Medium', 'High') DEFAULT 'Medium',
    author_id INT,
    expires_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Settings table (key-value pairs for league configuration)
CREATE TABLE IF NOT EXISTS settings (
    `key` VARCHAR(64) PRIMARY KEY,
    `value` TEXT NOT NULL
);

-- Create indexes for better performance
CREATE INDEX idx_drivers_user_id ON drivers(user_id);
CREATE INDEX idx_drivers_team_id ON drivers(team_id);
CREATE INDEX idx_race_results_race_id ON race_results(race_id);
CREATE INDEX idx_race_results_driver_id ON race_results(driver_id);
CREATE INDEX idx_races_season_id ON races(season_id);
CREATE INDEX idx_races_date ON races(race_date);
CREATE INDEX idx_penalties_driver_id ON penalties(driver_id);
CREATE INDEX idx_penalties_race_id ON penalties(race_id);

-- Insert default data
INSERT INTO users (username, email, password_hash, role, verified) VALUES 
('admin', 'admin@racingleague.com', '$2y$10$jfLZ3yshsJRZPcnvYv4rVuXa3zPmuqy0gl/adiYK4RO9.ksFsj0z6', 'admin', TRUE);
-- Sample drivers (linked to users and teams)
INSERT INTO users (username, email, password_hash, role, verified) VALUES
('driver1', 'driver1@racingleague.com', '$2y$10$jfLZ3yshsJRZPcnvYv4rVuXa3zPmuqy0gl/adiYK4RO9.ksFsj0z6', 'driver', TRUE),
('driver2', 'driver2@racingleague.com', '$2y$10$jfLZ3yshsJRZPcnvYv4rVuXa3zPmuqy0gl/adiYK4RO9.ksFsj0z6', 'driver', TRUE);

-- Default season with standard F1-style points system
INSERT INTO seasons (name, year, points_system, is_active) VALUES 
('Championship 2025', 2025, '{"1":25,"2":18,"3":15,"4":12,"5":10,"6":8,"7":6,"8":4,"9":2,"10":1,"fastest_lap":1}', TRUE);

-- Sample team
INSERT INTO teams (name, created_by) VALUES 
('Independent Drivers', 1);

-- Insert drivers (user_id, team_id, driver_number, platform, country, livery_image, bio)
INSERT INTO drivers (user_id, team_id, driver_number, platform, country, livery_image, bio) VALUES
(2, 1, 44, 'PC', 'DE', NULL, 'Sample bio for driver 1'),
(3, 1, 77, 'Xbox', 'GB', NULL, 'Sample bio for driver 2');

-- Sample track data
INSERT INTO races (season_id, name, track, race_date, format, laps) VALUES 
(1, 'Season Opener', 'Silverstone', '2025-04-15 15:00:00', 'Feature', 52),
(1, 'Sprint Challenge', 'Monza', '2025-04-22 15:00:00', 'Sprint', 30),
(1, 'Championship Decider', 'Spa-Francorchamps', '2025-12-15 15:00:00', 'Feature', 44);

-- Default session types
INSERT INTO session_types (name, code, is_default) VALUES
('FP1', 'fp1', 1),
('FP2', 'fp2', 1),
('FP3', 'fp3', 1),
('Q1', 'q1', 1),
('Q2', 'q2', 1),
('Q3', 'q3', 1),
('Sprint Qualy', 'sprint_quali', 1),
('Sprint', 'sprint', 1),
('Feature Race', 'feature', 1);

-- Add sessions for "Season Opener" (race_id = 1)
INSERT INTO race_sessions (race_id, session_type_id, session_order, enabled) VALUES
(1, 1, 1, 1),  -- FP1
(1, 2, 2, 1),  -- FP2
(1, 3, 3, 1),  -- FP3
(1, 4, 4, 1),  -- Q1
(1, 5, 5, 1),  -- Q2
(1, 6, 6, 1),  -- Q3
(1, 9, 7, 1);  -- Feature Race

-- Add sessions for "Sprint Challenge" (race_id = 2)
INSERT INTO race_sessions (race_id, session_type_id, session_order, enabled) VALUES
(2, 1, 1, 1),  -- FP1
(2, 2, 2, 1),  -- FP2
(2, 7, 3, 1),  -- Sprint Qualy
(2, 8, 4, 1),  -- Sprint
(2, 9, 5, 1);  -- Feature Race

-- Add sessions for "Championship Decider" (race_id = 3)
INSERT INTO race_sessions (race_id, session_type_id, session_order, enabled) VALUES
(3, 1, 1, 1),  -- FP1
(3, 2, 2, 1),  -- FP2
(3, 3, 3, 1),  -- FP3
(3, 4, 4, 1),  -- Q1
(3, 5, 5, 1),  -- Q2
(3, 6, 6, 1),  -- Q3
(3, 9, 7, 1);  -- Feature Race