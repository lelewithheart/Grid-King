# Racing League Management System - Makefile
# Convenient commands for Docker deployment and management

.PHONY: help build up down restart logs clean backup restore test

# Default target
help: ## Show this help message
	@echo "Racing League Management System - Docker Commands"
	@echo "================================================="
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "\033[36m%-15s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

# Development commands
build: ## Build Docker images
	docker-compose build

up: ## Start all services
	docker-compose up -d
	@echo "🎉 Racing League is running at http://localhost:8080"

down: ## Stop all services
	docker-compose down

restart: ## Restart all services
	docker-compose restart

logs: ## Show logs from all services
	docker-compose logs -f

status: ## Show status of all containers
	docker-compose ps

# Production commands
prod-up: ## Start in production mode
	docker-compose -f docker-compose.yml -f docker-compose.prod.yml up -d
	@echo "🎉 Racing League (Production) is running"

prod-down: ## Stop production deployment
	docker-compose -f docker-compose.yml -f docker-compose.prod.yml down

# Database commands
db-backup: ## Backup database
	@echo "📦 Creating database backup..."
	@mkdir -p backups
	docker exec racing_league_db mysqldump -u racing_user -pracing_pass123 racing_league > backups/backup_$(shell date +%Y%m%d_%H%M%S).sql
	@echo "✅ Backup created in backups/ directory"

db-restore: ## Restore database from backup (Usage: make db-restore FILE=backup.sql)
	@if [ -z "$(FILE)" ]; then echo "❌ Please specify backup file: make db-restore FILE=backup.sql"; exit 1; fi
	@echo "🔄 Restoring database from $(FILE)..."
	docker exec -i racing_league_db mysql -u racing_user -pracing_pass123 racing_league < $(FILE)
	@echo "✅ Database restored successfully"

db-shell: ## Access database shell
	docker exec -it racing_league_db mysql -u racing_user -pracing_pass123 racing_league

# Maintenance commands
clean: ## Remove unused Docker resources
	docker system prune -f
	docker volume prune -f

clean-all: ## Remove all containers, images, and volumes (⚠️  DESTRUCTIVE)
	@echo "⚠️  This will remove ALL Racing League data!"
	@read -p "Are you sure? (y/N): " confirm && [ "$$confirm" = "y" ]
	docker-compose down -v --remove-orphans
	docker-compose -f docker-compose.yml -f docker-compose.prod.yml down -v --remove-orphans
	docker rmi $(shell docker images "racing*" -q) 2>/dev/null || true
	docker volume rm racing_league_db_data 2>/dev/null || true

# Testing commands
test: ## Run basic health checks
	@echo "🧪 Testing Racing League services..."
	@echo "Checking web service..."
	@curl -sf http://localhost:8080 > /dev/null && echo "✅ Web service OK" || echo "❌ Web service failed"
	@echo "Checking database service..."
	@docker exec racing_league_db mysqladmin ping -h localhost -u racing_user -pracing_pass123 --silent && echo "✅ Database service OK" || echo "❌ Database service failed"

reset-data: ## Reset to fresh sample data
	@echo "🔄 Resetting to fresh sample data..."
	@read -p "This will reset all race data. Continue? (y/N): " confirm && [ "$$confirm" = "y" ]
	docker exec -i racing_league_db mysql -u racing_user -pracing_pass123 racing_league < database_setup.sql
	docker exec -i racing_league_db mysql -u racing_user -pracing_pass123 racing_league < docker/init-sample-data.sql
	@echo "✅ Sample data restored"

# Quick start
quick-start: build up ## Quick start (build and run)
	@echo "⏳ Waiting for services to start..."
	@sleep 10
	@make test
	@echo ""
	@echo "🎉 Racing League Management System is ready!"
	@echo "   🏎️  Web Interface: http://localhost:8080"
	@echo "   📊 Database Admin: http://localhost:8081"
	@echo "   🔑 Admin Login: admin@racingleague.com / admin123"

# Setup development environment
dev-setup: ## Setup development environment
	@echo "🛠️  Setting up development environment..."
	cp .env.example .env
	@echo "📝 Please edit .env file with your settings"
	@echo "✅ Development environment ready"

# Update system
update: ## Update to latest version
	git pull
	docker-compose build --no-cache
	docker-compose up -d
	@echo "✅ System updated successfully"