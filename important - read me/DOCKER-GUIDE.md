# 🏁 Racing League Docker - Quick Deployment Guide

## 🚀 Deployment Options

### Option 1: One-Command Quick Start (Recommended)
```bash
./deploy.sh
```
This script handles everything automatically and provides status updates.

### Option 2: Manual Docker Compose
```bash
# Start all services
docker-compose up -d

# Check status
docker-compose ps

# View logs
docker-compose logs -f
```

### Option 3: Using Make (if available)
```bash
make quick-start    # Build and start everything
make logs          # View logs
make status        # Check container status
```

## 🌍 Common Deployment Scenarios

### Local Development
```bash
# Clone/download Racing League files
cd racing-league-management

# Quick start
./deploy.sh

# Access at http://localhost:8080
```

### Home Server / NAS
```bash
# SSH into your server
ssh user@your-server

# Transfer files and start
scp -r racing-league-management/ user@your-server:~/
cd racing-league-management
./deploy.sh

# Access at http://your-server-ip:8080
```

### VPS / Cloud Server
```bash
# On your VPS (Ubuntu/Debian example)
sudo apt update
sudo apt install docker.io docker-compose

# Clone and start
git clone [your-repo] racing-league
cd racing-league
./deploy.sh

# Configure firewall
sudo ufw allow 8080
sudo ufw allow 8081  # Optional: phpMyAdmin

# Access at http://your-vps-ip:8080
```

### Production Deployment
```bash
# Use production configuration
cp .env.example .env
nano .env  # Edit with secure passwords

# Start in production mode
docker-compose -f docker-compose.yml -f docker-compose.prod.yml up -d

# Setup automated backups
echo "0 2 * * * cd $(pwd) && make db-backup" | crontab -
```

## 🔧 Port Configurations

### Standard Setup (Default)
- Racing League: http://localhost:8080
- phpMyAdmin: http://localhost:8081
- Database: localhost:3306

### Custom Ports
Edit `docker-compose.yml`:
```yaml
services:
  web:
    ports:
      - "80:80"      # Use standard HTTP port
  phpmyadmin:
    ports:
      - "8000:80"    # Change phpMyAdmin port
```

### Behind Reverse Proxy (nginx/Apache)
```yaml
services:
  web:
    ports:
      - "127.0.0.1:8080:80"  # Only localhost access
    # Let reverse proxy handle external access
```

## 🔐 Security Configurations

### Basic Security
```bash
# Change default passwords
cp .env.example .env
nano .env

# Disable phpMyAdmin in production
# Comment out phpMyAdmin service in docker-compose.yml
```

### Advanced Security (Production)
```bash
# Use production config with nginx
make prod-up

# Setup SSL certificates
mkdir -p docker/ssl
# Add your SSL certificates to docker/ssl/

# Enable firewall
sudo ufw enable
sudo ufw allow ssh
sudo ufw allow 80
sudo ufw allow 443
```

## 💾 Backup Strategies

### Manual Backup
```bash
# Database backup
make db-backup
# Creates backup in backups/ directory

# Full system backup
tar -czf racing-league-backup.tar.gz . --exclude=backups
```

### Automated Backup
```bash
# Daily database backup (2 AM)
echo "0 2 * * * cd $(pwd) && make db-backup" | crontab -

# Weekly full backup
echo "0 3 * * 0 cd $(pwd) && tar -czf backup-\$(date +%Y%m%d).tar.gz . --exclude=backups" | crontab -
```

### Restore from Backup
```bash
# Restore database
make db-restore FILE=backup.sql

# Restore full system
tar -xzf racing-league-backup.tar.gz
```

## 📊 Monitoring & Maintenance

### Health Checks
```bash
# Quick health check
make test

# Detailed status
docker-compose ps
docker stats

# View resource usage
docker exec racing_league_web top
```

### Log Management
```bash
# View live logs
docker-compose logs -f

# Specific service logs
docker-compose logs -f web
docker-compose logs -f db

# Rotate logs (if needed)
docker-compose down && docker-compose up -d
```

### Updates
```bash
# Update system
git pull  # If using git
docker-compose build --no-cache
docker-compose up -d

# Or with make
make update
```

## 🔄 Migration & Scaling

### Move to New Server
```bash
# On old server
make db-backup
tar -czf racing-league-full.tar.gz .

# On new server
tar -xzf racing-league-full.tar.gz
./deploy.sh
make db-restore FILE=backup.sql
```

### Scaling for High Traffic
```bash
# Use production config with nginx
make prod-up

# Add multiple web containers (edit docker-compose.yml)
docker-compose up -d --scale web=3
```

## 🐛 Troubleshooting

### Container Won't Start
```bash
# Check logs
docker-compose logs db
docker-compose logs web

# Check ports
netstat -tulpn | grep :8080
sudo lsof -i :8080

# Restart everything
docker-compose down
docker-compose up -d
```

### Database Connection Issues
```bash
# Test database connectivity
docker exec racing_league_db mysqladmin ping -h localhost -u racing_user -pracing_pass123

# Reset database
docker-compose down
docker volume rm racing_league_db_data
docker-compose up -d
```

### Permission Issues
```bash
# Fix file permissions
docker exec racing_league_web chown -R www-data:www-data /var/www/html
chmod -R 755 uploads

# Fix SELinux (if applicable)
sudo setsebool -P httpd_can_network_connect 1
```

## 📋 Pre-Deployment Checklist

### Development
- [ ] Docker and Docker Compose installed
- [ ] Ports 8080 and 8081 available
- [ ] 2GB+ RAM available
- [ ] Internet connection for downloading images

### Production
- [ ] Secure passwords in .env file
- [ ] SSL certificates configured
- [ ] Firewall rules configured
- [ ] Backup strategy implemented
- [ ] Monitoring setup
- [ ] Domain name pointed to server
- [ ] Regular update schedule planned

---

## 🎯 Quick Reference

**Start System:** `./deploy.sh` or `docker-compose up -d`
**Stop System:** `docker-compose down`
**View Logs:** `docker-compose logs -f`
**Backup Database:** `make db-backup`
**Access System:** http://localhost:8080
**Admin Login:** admin@racingleague.com / admin123

**Need Help?** Check the main README.md for detailed documentation!