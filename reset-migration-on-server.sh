#!/bin/bash

# Reset Migration on CloudPanel Server
# This script resets the failed migration so it can run again with the MySQL fix

echo "=========================================="
echo "Reset Migration on CloudPanel Server"
echo "=========================================="
echo ""

# Check if required environment variables are set
if [ -z "$SERVER_HOST" ] || [ -z "$SERVER_USER" ] || [ -z "$DEPLOY_PATH" ]; then
    echo "ERROR: Required environment variables not set"
    echo ""
    echo "Please set the following variables:"
    echo "  export SERVER_HOST='your-server-ip-or-domain'"
    echo "  export SERVER_USER='your-cloudpanel-user'"
    echo "  export DEPLOY_PATH='/path/to/your/site'"
    echo ""
    exit 1
fi

echo "Server: $SERVER_HOST"
echo "User: $SERVER_USER"
echo "Path: $DEPLOY_PATH"
echo ""

# SSH into server and reset the migration
ssh ${SERVER_USER}@${SERVER_HOST} << 'ENDSSH'
cd ${DEPLOY_PATH}

echo "Removing failed migration from migrations table..."
php artisan db:table migrations --where="migration=2026_03_12_000002_add_indexes_for_performance" --delete

echo ""
echo "Running migration again with MySQL fix..."
php artisan migrate --force

echo ""
echo "Migration reset complete!"
ENDSSH

echo ""
echo "=========================================="
echo "Done!"
echo "=========================================="
