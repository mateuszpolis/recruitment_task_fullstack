#!/bin/bash

echo "🚀 Starting development environment..."

# Stop any running containers first
echo "Stopping any existing containers..."
docker compose -f docker-compose.yml down 2>/dev/null
docker compose -f docker-compose.dev.yml down 2>/dev/null

# Build and start development containers
echo "Building and starting development containers..."
docker compose -f docker-compose.dev.yml up --build -d

# Wait a moment for containers to start
sleep 3

# Show status
echo ""
echo "📊 Container status:"
docker compose -f docker-compose.dev.yml ps

echo ""
echo "✅ Development environment is ready!"
echo "🌐 Application: http://telemedi-zadanie.localhost"
echo "📁 Source files are mounted for live editing"
echo "🔄 Changes to PHP and frontend assets will auto-reload"
echo ""
echo "📝 Commands:"
echo "  View logs: docker compose -f docker-compose.dev.yml logs -f"
echo "  Stop:      docker compose -f docker-compose.dev.yml down"
echo "  Restart:   ./dev-up.sh"
