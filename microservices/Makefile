.PHONY: help setup install run test lint format clean docker-build docker-up docker-down

help:
	@echo "FastAPI Microservices - Available Commands"
	@echo "==========================================="
	@echo "setup          - Set up development environment"
	@echo "install        - Install dependencies"
	@echo "run            - Run FastAPI server"
	@echo "celery-worker  - Run Celery worker"
	@echo "celery-beat    - Run Celery beat scheduler"
	@echo "test           - Run tests"
	@echo "test-cov       - Run tests with coverage"
	@echo "lint           - Run linter (flake8)"
	@echo "format         - Format code (black)"
	@echo "type-check     - Run type checker (mypy)"
	@echo "clean          - Clean up generated files"
	@echo "docker-build   - Build Docker images"
	@echo "docker-up      - Start Docker containers"
	@echo "docker-down    - Stop Docker containers"

setup:
	@echo "Setting up development environment..."
	@bash setup.sh

install:
	@echo "Installing dependencies..."
	@pip install -r requirements.txt

run:
	@echo "Starting FastAPI server..."
	@uvicorn app.main:app --reload --port 8001

celery-worker:
	@echo "Starting Celery worker..."
	@celery -A app.celery_app worker --loglevel=info

celery-beat:
	@echo "Starting Celery beat..."
	@celery -A app.celery_app beat --loglevel=info

test:
	@echo "Running tests..."
	@pytest

test-cov:
	@echo "Running tests with coverage..."
	@pytest --cov=app --cov-report=html --cov-report=term

lint:
	@echo "Running linter..."
	@flake8 app/

format:
	@echo "Formatting code..."
	@black app/ tests/

type-check:
	@echo "Running type checker..."
	@mypy app/

clean:
	@echo "Cleaning up..."
	@find . -type d -name "__pycache__" -exec rm -rf {} +
	@find . -type f -name "*.pyc" -delete
	@find . -type f -name "*.pyo" -delete
	@find . -type d -name "*.egg-info" -exec rm -rf {} +
	@find . -type d -name ".pytest_cache" -exec rm -rf {} +
	@find . -type d -name ".hypothesis" -exec rm -rf {} +
	@find . -type d -name "htmlcov" -exec rm -rf {} +
	@find . -type f -name ".coverage" -delete

docker-build:
	@echo "Building Docker images..."
	@docker-compose build

docker-up:
	@echo "Starting Docker containers..."
	@docker-compose up -d

docker-down:
	@echo "Stopping Docker containers..."
	@docker-compose down

docker-logs:
	@echo "Showing Docker logs..."
	@docker-compose logs -f
