#!/bin/bash

# Racing League Management System - Docker Deployment Script
# This script sets up and launches the complete racing league system

set -e

echo "🏁 Racing League Management System - Docker Deployment"
echo "======================================================="
echo ""

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo "❌ Docker is not installed. Please install Docker first."
    echo "   Visit: https://docs.docker.com/get-docker/"
    exit 1
fi

# Check if Docker Compose is installed
if ! command -v docker-compose &> /dev/null && ! docker compose version &> /dev/null; then
    echo "❌ Docker Compose is not installed. Please install Docker Compose first."
    echo "   Visit: https://docs.docker.com/compose/install/"
    exit 1
fi

echo "✅ Docker and Docker Compose are installed"
echo ""

# Create necessary directories
echo "📁 Creating necessary directories..."
mkdir -p uploads/liveries
mkdir -p docker
chmod -R 755 uploads

echo "✅ Directories created"
echo ""

# Check if docker-compose.yml exists
if [ ! -f "docker-compose.yml" ]; then
    echo "❌ docker-compose.yml not found. Please make sure you're in the correct directory."
    exit 1
fi

echo "🐳 Starting Docker containers..."
echo ""

# Stop any existing containers
echo "Stopping any existing containers..."
docker-compose down --remove-orphans 2>/dev/null || true

# Build and start containers
echo "Building and starting containers..."
if command -v docker-compose &> /dev/null; then
    docker-compose up -d --build
else
    docker compose up -d --build
fi

echo ""
echo "⏳ Waiting for services to start..."
sleep 10

# Check if containers are running
echo "🔍 Checking container status..."
if command -v docker-compose &> /dev/null; then
    docker-compose ps
else
    docker compose ps
fi

echo ""
echo "🎉 Racing League Management System is now running!"
echo ""
echo "📱 Access URLs:"
echo "   🏎️  Racing League System: http://localhost:8080"
echo "   📊 phpMyAdmin (Database):  http://localhost:8081"
echo ""
echo "🔑 Login Credentials:"
echo "   👨‍💼 Admin:        admin@racingleague.com / admin123"
echo "   🏁 Sample Driver: john_racer / admin123"
echo "   🏁 Sample Driver: sarah_speed / admin123"
echo "   👀 Spectator:    demo_spectator / admin123"
echo ""
echo "📋 Quick Test Workflow:"
echo "   1. Go to http://localhost:8080"
echo "   2. Login as admin"
echo "   3. Go to Admin → Race Results"
echo "   4. Add race results and see standings update live!"
echo ""
echo "🛠️  Management Commands:"
echo "   Stop:     docker-compose down"
echo "   Restart:  docker-compose restart"
echo "   Logs:     docker-compose logs -f"
echo "   Rebuild:  docker-compose up -d --build"
echo ""
echo "🗄️  Database Access:"
echo "   📊 Web Interface: http://localhost:8081"
echo "   🖥️  Command Line: docker exec -it racing_league_db mysql -u racing_user -p"
echo ""

# Check if services are responding
echo "🧪 Testing service health..."

# Test web service
if curl -s http://localhost:8080 > /dev/null 2>&1; then
    echo "✅ Web service is responding"
else
    echo "⚠️  Web service may still be starting. Please wait a moment and try accessing http://localhost:8080"
fi

# Test database service
if docker exec racing_league_db mysqladmin ping -h localhost -u racing_user -pracing_pass123 --silent > /dev/null 2>&1; then
    echo "✅ Database service is responding"
else
    echo "⚠️  Database service may still be starting. Please wait a moment."
fi

echo ""
echo "🎯 The Racing League Management System is ready!"
echo "   Visit http://localhost:8080 to start managing your racing championship!"
echo ""