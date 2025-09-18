#!/bin/bash

echo "Starting development environment..."

# Function to handle shutdown
cleanup() {
    echo "Shutting down development environment..."
    kill $(jobs -p) 2>/dev/null
    exit 0
}

# Set up signal handling
trap cleanup SIGTERM SIGINT

# Ensure proper permissions
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html

# Create build directory if it doesn't exist
mkdir -p /var/www/html/public/build
chown -R www-data:www-data /var/www/html/public/build

# Start Apache in background
echo "Starting Apache..."
apache2-foreground &
APACHE_PID=$!

# Wait a moment for Apache to start
sleep 2

# Start webpack in watch mode
echo "Starting Webpack in watch mode..."
cd /var/www/html
npm run watch &
WEBPACK_PID=$!

echo "Development environment ready!"
echo "- Apache PID: $APACHE_PID"
echo "- Webpack PID: $WEBPACK_PID"
echo "- Application available at: http://telemedi-zadanie.localhost"
echo "- File changes will be automatically detected"

# Wait for any process to exit
wait -n

# If we get here, one process has exited, so cleanup
cleanup
