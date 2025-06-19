# 🏁 Racing League Management System - Docker Deployment

A complete, professional racing league management system built with PHP 8.2, MariaDB, and Bootstrap 5. Perfect for sim racing leagues, racing clubs, and educational projects.

## 🚀 Quick Start

### Prerequisites
- [Docker](https://docs.docker.com/get-docker/) 20.10+
- [Docker Compose](https://docs.docker.com/compose/install/) 2.0+

### One-Command Launch
```bash
# Clone/download the Racing League files, then:
./deploy.sh
```

Or manually:
```bash
docker-compose up -d
```

### Access Your Racing League
- **🏎️ Racing League System:** http://localhost:8080
- **📊 Database Admin (phpMyAdmin):** http://localhost:8081

### Login Credentials
- **👨‍💼 Admin:** `admin@racingleague.com` / `admin123`
- **🏁 Driver:** `john_racer` / `admin123`
- **👀 Spectator:** `demo_spectator` / `admin123`

## 🎯 Core Features

### 🏆 Championship Management
- **Live Standings** - Real-time championship points calculation
- **Race Results Input** - Admin interface with automatic points calculation
- **F1-Style Points System** - 25,18,15,12,10,8,6,4,2,1 + fastest lap bonus
- **Penalty System** - Apply penalties that affect standings instantly

### 👥 User & Driver Management
- **Role-Based Access** - Admin, Driver, Spectator roles
- **Driver Registration** - Racing numbers, platforms, countries
- **Profile Management** - Edit info, upload racing liveries
- **Team System** - Team rosters and team championships

### 📅 Race Management
- **Race Calendar** - Upcoming races with countdown timers
- **Multiple Formats** - Feature, Sprint, Endurance races
- **Track Information** - Detailed race and track data
- **Race History** - Complete results archive

### 📰 Content System
- **News Management** - Articles with templates and featured posts
- **Announcements** - Important league communications
- **Driver Spotlights** - Featured driver profiles

## 🛠️ Development & Management

### Using Make Commands
```bash
# Quick commands (if you have make installed)
make help          # Show all available commands
make quick-start    # Build and start everything
make logs          # View real-time logs
make db-backup     # Backup database
make clean         # Clean up unused resources
```

### Manual Docker Commands
```bash
# Basic operations
docker-compose up -d        # Start services
docker-compose down         # Stop services
docker-compose restart      # Restart services
docker-compose ps           # Check status
docker-compose logs -f      # View logs

# Database operations
docker exec -it racing_league_db mysql -u racing_user -pracing_pass123
docker exec racing_league_db mysqldump -u racing_user -pracing_pass123 racing_league > backup.sql
```

## 🔧 Configuration

### Environment Variables
Copy `.env.example` to `.env` and customize:
```bash
cp .env.example .env
# Edit .env with your preferred settings
```

### Port Configuration
Edit `docker-compose.yml` to change ports:
```yaml
services:
  web:
    ports:
      - "8080:80"    # Change 8080 to your preferred port
  db:
    ports:
      - "3306:3306"  # Database port (optional)
  phpmyadmin:
    ports:
      - "8081:80"    # phpMyAdmin port
```

### File Upload Configuration
Create/modify `uploads` directory permissions:
```bash
mkdir -p uploads/liveries
chmod -R 755 uploads
```

## 🌐 Production Deployment

### Production Mode
```bash
# Use production configuration
docker-compose -f docker-compose.yml -f docker-compose.prod.yml up -d

# Or with make
make prod-up
```

### Security Considerations
1. **Change default passwords** in `.env`
2. **Remove phpMyAdmin** in production (comment out in docker-compose.yml)
3. **Use HTTPS** with reverse proxy (nginx configuration included)
4. **Regular backups** (automated with cron)
5. **Update regularly** for security patches

### SSL/HTTPS Setup
1. Obtain SSL certificates (Let's Encrypt recommended)
2. Place certificates in `docker/ssl/`
3. Enable nginx service in `docker-compose.prod.yml`
4. Configure your domain DNS

### Backup Strategy
```bash
# Automated daily backups
echo "0 2 * * * cd /path/to/racing-league && make db-backup" | crontab -

# Manual backup
make db-backup

# Restore from backup
make db-restore FILE=backup.sql
```

## 🎮 Using the System

### Admin Workflow
1. **Login** as admin at `/login.php`
2. **Create Races** at `Admin → Manage Races`
3. **Input Results** at `Admin → Race Results`
4. **Apply Penalties** at `Admin → Penalties`
5. **Manage News** at `Admin → News`

### Driver Experience
1. **Register** as driver at `/register.php`
2. **Update Profile** with racing info and livery
3. **View Dashboard** for personal stats
4. **Check Standings** for championship position
5. **Follow Calendar** for upcoming races

### Key Value Demonstration
The core value is the **Race Results → Live Standings** workflow:
1. Admin inputs race results with positions
2. System calculates points automatically (F1-style)
3. Standings update immediately with new positions
4. Penalties can be applied to adjust points
5. Changes are visible instantly to all users

## 🧪 Testing

### Health Checks
```bash
# Check all services
make test

# Manual checks
curl http://localhost:8080
docker exec racing_league_db mysqladmin ping -h localhost -u racing_user -pracing_pass123
```

### Sample Data
The system includes sample data:
- **3 Sample drivers** with race history
- **3 Races** in Championship 2025
- **Race results** showing live standings
- **Sample news articles**

### Reset to Fresh Data
```bash
make reset-data  # Resets to clean sample data
```

## 🔍 Troubleshooting

### Common Issues

**Port conflicts:**
```bash
# Change ports in docker-compose.yml
ports:
  - "8090:80"  # Instead of 8080:80
```

**Permission issues:**
```bash
# Fix upload permissions
docker exec racing_league_web chown -R www-data:www-data /var/www/html/uploads
chmod -R 755 uploads
```

**Database connection issues:**
```bash
# Check database status
docker logs racing_league_db
docker exec racing_league_db mysqladmin ping -h localhost -u racing_user -pracing_pass123

# Restart database
docker-compose restart db
```

**Memory issues:**
```bash
# Increase Docker memory limit (Docker Desktop: Settings → Resources)
# Or use production configuration with optimized database settings
make prod-up
```

### Viewing Logs
```bash
# All services
docker-compose logs -f

# Specific service
docker-compose logs -f web
docker-compose logs -f db

# Apache error log
docker exec racing_league_web tail -f /var/log/apache2/error.log
```

## 📚 Technical Details

### System Architecture
- **Frontend:** Bootstrap 5 + JavaScript + PHP templating
- **Backend:** PHP 8.2 with PDO for database access
- **Database:** MariaDB 10.11 with optimized configuration
- **Web Server:** Apache 2.4 with mod_rewrite
- **Container Orchestration:** Docker Compose

### File Structure
```
racing-league/
├── config/              # Database and app configuration
├── includes/           # Header, footer, common includes
├── admin/              # Admin interface pages
├── uploads/            # User uploaded files (liveries)
├── docker/             # Docker configuration files
├── database_setup.sql  # Database schema and initial data
├── docker-compose.yml  # Docker services configuration
└── deploy.sh          # Quick deployment script
```

### Database Schema
- **Users** - Authentication and roles
- **Drivers** - Racing profiles and info
- **Teams** - Team management
- **Seasons** - Championship seasons
- **Races** - Race events and details
- **Race Results** - Points and positions
- **Penalties** - Penalty tracking
- **News** - Content management

## 🤝 Support

### Getting Help
1. Check the logs for error details
2. Verify Docker and system requirements
3. Review configuration files
4. Test with sample data

### System Requirements
- **Docker:** 20.10+
- **Docker Compose:** 2.0+
- **RAM:** 2GB minimum, 4GB recommended
- **Disk:** 10GB for system + database growth
- **CPU:** Any modern processor
- **OS:** Linux, macOS, Windows with Docker Desktop

### Performance Tuning
- Database configuration in `docker-compose.prod.yml`
- Apache optimization in `docker/apache.conf`
- PHP configuration can be customized in Dockerfile
- Use nginx reverse proxy for high traffic

---

## 🏆 About

The Racing League Management System is a complete solution for managing racing championships and leagues. Built with modern web technologies and Docker for easy deployment, it provides everything needed to run a professional racing organization.

**Perfect for:**
- Sim racing leagues and communities
- Racing clubs and organizations
- Educational projects and demonstrations
- Professional racing series management

**Key Features:**
- Live championship standings with automatic point calculation
- Complete race and driver management
- Professional racing-themed interface
- Easy deployment with Docker
- Production-ready with security features
- Extensible and customizable

Ready to start your racing championship? `./deploy.sh` and you're racing! 🏁