#!/bin/bash

# FastAPI Microservices Setup Script
# This script sets up the development environment

set -e

echo "=========================================="
echo "FastAPI Microservices Setup"
echo "=========================================="

# Check Python version
echo "Checking Python version..."
python_version=$(python3 --version 2>&1 | awk '{print $2}')
required_version="3.9"

if [ "$(printf '%s\n' "$required_version" "$python_version" | sort -V | head -n1)" != "$required_version" ]; then
    echo "Error: Python 3.9 or higher is required"
    echo "Current version: $python_version"
    exit 1
fi

echo "Python version: $python_version ✓"

# Create virtual environment
echo ""
echo "Creating virtual environment..."
if [ ! -d "venv" ]; then
    python3 -m venv venv
    echo "Virtual environment created ✓"
else
    echo "Virtual environment already exists ✓"
fi

# Activate virtual environment
echo ""
echo "Activating virtual environment..."
source venv/bin/activate

# Upgrade pip
echo ""
echo "Upgrading pip..."
pip install --upgrade pip

# Install dependencies
echo ""
echo "Installing dependencies..."
pip install -r requirements.txt

# Create .env file if it doesn't exist
echo ""
if [ ! -f ".env" ]; then
    echo "Creating .env file from template..."
    cp .env.example .env
    echo ".env file created ✓"
    echo "Please edit .env file with your configuration"
else
    echo ".env file already exists ✓"
fi

# Create logs directory
echo ""
echo "Creating logs directory..."
mkdir -p logs
echo "Logs directory created ✓"

echo ""
echo "=========================================="
echo "Setup completed successfully!"
echo "=========================================="
echo ""
echo "Next steps:"
echo "1. Edit .env file with your configuration"
echo "2. Ensure PostgreSQL and Redis are running"
echo "3. Activate virtual environment: source venv/bin/activate"
echo "4. Start FastAPI server: uvicorn app.main:app --reload --port 8001"
echo "5. Start Celery worker: celery -A app.celery_app worker --loglevel=info"
echo "6. Start Celery beat: celery -A app.celery_app beat --loglevel=info"
echo ""
