-- Racing League Management System Database Schema
-- Created for PHP 8.2 + MariaDB 10.11

USE racing_league;

-- Users table (authentication and roles)
CREATE TABLE users (
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
CREATE TABLE teams (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,
    logo VARCHAR(255),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Drivers table (extended user profile for racing)
CREATE TABLE drivers (
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
CREATE TABLE seasons (
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
CREATE TABLE races (
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

-- Race Results table (the heart of standings calculation)
CREATE TABLE race_results (
    id INT PRIMARY KEY AUTO_INCREMENT,
    race_id INT NOT NULL,
    driver_id INT NOT NULL,
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
    UNIQUE KEY unique_race_driver (race_id, driver_id)
);

-- Penalties table (separate from race results for tracking)
CREATE TABLE penalties (
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
CREATE TABLE news (
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
CREATE TABLE announcements (
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
('admin', 'admin@racingleague.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', TRUE);

-- Default season with standard F1-style points system
INSERT INTO seasons (name, year, points_system, is_active) VALUES 
('Championship 2025', 2025, '{"1":25,"2":18,"3":15,"4":12,"5":10,"6":8,"7":6,"8":4,"9":2,"10":1,"fastest_lap":1}', TRUE);

-- Sample team
INSERT INTO teams (name, created_by) VALUES 
('Independent Drivers', 1);

-- Sample track data
INSERT INTO races (season_id, name, track, race_date, format, laps) VALUES 
(1, 'Season Opener', 'Silverstone', '2025-04-15 15:00:00', 'Feature', 52),
(1, 'Sprint Challenge', 'Monza', '2025-04-22 15:00:00', 'Sprint', 30),
(1, 'Championship Decider', 'Spa-Francorchamps', '2025-12-15 15:00:00', 'Feature', 44);