# Racing League Management System - Docker Deployment

This directory contains Docker configuration files for easy deployment of the Racing League Management System.

## Quick Start

1. **Clone or extract the Racing League files**
2. **Run with Docker Compose:**
   ```bash
   docker-compose up -d
   ```

3. **Access the application:**
   - **Racing League System:** http://localhost:8080
   - **phpMyAdmin (Database Admin):** http://localhost:8081

4. **Login Credentials:**
   - **Admin:** admin@racingleague.com / admin123
   - **Sample Drivers:** john_racer, sarah_speed, alex_speed, emma_racer (all use password: admin123)

## What's Included

### Services:
- **web**: PHP 8.2 + Apache web server
- **db**: MariaDB 10.11 database
- **phpmyadmin**: Database management interface

### Features:
- Complete racing league management system
- Sample data pre-loaded (drivers, races, standings)
- File upload support for driver liveries
- Professional racing-themed UI
- Admin interface for race management
- Real-time standings calculation

## Customization

### Environment Variables
You can customize database settings by modifying the `docker-compose.yml` file:

```yaml
environment:
  MYSQL_ROOT_PASSWORD: your_root_password
  MYSQL_DATABASE: racing_league
  MYSQL_USER: your_user
  MYSQL_PASSWORD: your_password
```

### Port Configuration
- Change web port: Modify `"8080:80"` to `"YOUR_PORT:80"`
- Change database port: Modify `"3306:3306"` if needed
- Change phpMyAdmin port: Modify `"8081:80"`

### Volume Mounts
- **Uploads:** `./uploads:/var/www/html/uploads` (driver liveries)
- **Config:** `./config:/var/www/html/config` (database settings)
- **Database:** `db_data:/var/lib/mysql` (persistent data)

## Development Mode

For development with live code changes:

```yaml
# Add to web service in docker-compose.yml
volumes:
  - .:/var/www/html
  - ./uploads:/var/www/html/uploads
```

## Production Deployment

### Security Considerations:
1. **Change default passwords** in docker-compose.yml
2. **Remove phpMyAdmin** service in production
3. **Use environment files** for sensitive data
4. **Enable HTTPS** with reverse proxy (nginx/traefik)
5. **Regular database backups**

### Example Production Setup:
```bash
# Create environment file
echo "MYSQL_ROOT_PASSWORD=your_secure_password" > .env
echo "MYSQL_PASSWORD=your_secure_db_password" >> .env

# Run in production mode
docker-compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

## Database Management

### Backup Database:
```bash
docker exec racing_league_db mysqldump -u racing_user -p racing_league > backup.sql
```

### Restore Database:
```bash
docker exec -i racing_league_db mysql -u racing_user -p racing_league < backup.sql
```

### Access Database CLI:
```bash
docker exec -it racing_league_db mysql -u racing_user -p
```

## Troubleshooting

### Common Issues:

1. **Port already in use:**
   ```bash
   # Change ports in docker-compose.yml
   ports:
     - "8090:80"  # Instead of 8080:80
   ```

2. **Permission issues with uploads:**
   ```bash
   # Fix upload permissions
   docker exec racing_league_web chown -R www-data:www-data /var/www/html/uploads
   ```

3. **Database connection issues:**
   ```bash
   # Check database logs
   docker logs racing_league_db
   
   # Restart services
   docker-compose restart
   ```

### Logs:
```bash
# View web server logs
docker logs racing_league_web

# View database logs
docker logs racing_league_db

# Follow logs in real-time
docker-compose logs -f
```

## Updating

### Update application:
```bash
# Rebuild with latest changes
docker-compose down
docker-compose build --no-cache web
docker-compose up -d
```

### Update database schema:
```bash
# Apply database changes
docker exec -i racing_league_db mysql -u racing_user -p racing_league < updates.sql
```

## Support

For technical support or feature requests:
1. Check logs for error details
2. Verify database connectivity
3. Ensure proper file permissions
4. Review Docker and system requirements

**System Requirements:**
- Docker 20.10+
- Docker Compose 2.0+
- 2GB RAM minimum
- 10GB disk space