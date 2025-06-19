-- Additional sample data for Docker deployment
USE racing_league;

-- Update admin password for Docker deployment
UPDATE users SET password_hash = '$2y$10$EuRTC7Nxeok/TLFvjMh3Cul341eaRZUY7xoqPIoCBr6borLxqhnJy' WHERE username = 'admin';

-- Add more sample drivers for demonstration
INSERT INTO users (username, email, password_hash, role, verified) VALUES 
('alex_speed', 'alex@racingleague.com', '$2y$10$EuRTC7Nxeok/TLFvjMh3Cul341eaRZUY7xoqPIoCBr6borLxqhnJy', 'driver', TRUE),
('emma_racer', 'emma@racingleague.com', '$2y$10$EuRTC7Nxeok/TLFvjMh3Cul341eaRZUY7xoqPIoCBr6borLxqhnJy', 'driver', TRUE),
('demo_spectator', 'spectator@racingleague.com', '$2y$10$EuRTC7Nxeok/TLFvjMh3Cul341eaRZUY7xoqPIoCBr6borLxqhnJy', 'spectator', TRUE);

-- Add driver profiles for new users
INSERT INTO drivers (user_id, driver_number, platform, country, team_id) VALUES 
((SELECT id FROM users WHERE username = 'alex_speed'), 99, 'PC', 'AUS', 1),
((SELECT id FROM users WHERE username = 'emma_racer'), 22, 'PlayStation', 'FRA', 1);

-- Add sample news article
INSERT INTO news (title, content, author_id, published, featured) VALUES 
(
    'Welcome to Racing League Management System!',
    'Welcome to our professional racing league management platform! 

This system provides complete championship management including:

DRIVER FEATURES:
- Register as a driver with your racing number
- Track your championship position and points
- View detailed race history and statistics
- Upload your racing livery images
- Join or create racing teams

ADMIN FEATURES:
- Create and manage racing seasons
- Schedule races with track details and timing
- Input race results with automatic points calculation
- Apply penalties that affect championship standings
- Manage news and announcements
- Complete driver and team administration

CHAMPIONSHIP SYSTEM:
- F1-style points system (25, 18, 15, 12, 10, 8, 6, 4, 2, 1)
- Bonus points for pole position and fastest lap
- Real-time standings updates
- Team championships alongside driver standings
- Penalty system affecting points

RACE MANAGEMENT:
- Multiple race formats (Feature, Sprint, Endurance)
- Detailed race results with DNF tracking
- Penalty application and management
- Race calendar with countdown timers
- Track information and race statistics

This system is perfect for:
- Sim racing leagues and communities
- Racing clubs and organizations
- Educational projects and demonstrations
- Professional racing series management

Login as admin@racingleague.com (password: admin123) to explore the full admin interface, or register as a new driver to experience the driver features!

Happy Racing! 🏁',
    1,
    TRUE,
    TRUE
);

-- Add more race results for better demonstration
INSERT INTO race_results (race_id, driver_id, position, points, fastest_lap, pole_position, dnf) VALUES 
(1, 4, 4, 12, FALSE, FALSE, FALSE),
(1, 5, 5, 10, FALSE, FALSE, FALSE);

-- Create additional team
INSERT INTO teams (name, created_by) VALUES 
('Pro Racing Team', 1);

-- Update some drivers to new team
UPDATE drivers SET team_id = 2 WHERE id IN (2, 4);