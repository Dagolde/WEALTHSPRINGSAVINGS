.PHONY: help setup up down restart logs clean test lint format docs

# Default target
.DEFAULT_GOAL := help

# Colors for output
BLUE := \033[0;34m
GREEN := \033[0;32m
YELLOW := \033[1;33m
NC := \033[0m # No Color

help: ## Show this help message
	@echo "$(BLUE)Rotational Contribution App - Development Commands$(NC)"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  $(GREEN)%-20s$(NC) %s\n", $$1, $$2}'
	@echo ""

setup: ## Initial setup of development environment
	@echo "$(BLUE)Setting up development environment...$(NC)"
	@chmod +x setup-dev-environment.sh
	@./setup-dev-environment.sh

up: ## Start all Docker containers
	@echo "$(BLUE)Starting Docker containers...$(NC)"
	@docker-compose up -d
	@echo "$(GREEN)✓ Containers started$(NC)"

down: ## Stop all Docker containers
	@echo "$(BLUE)Stopping Docker containers...$(NC)"
	@docker-compose down
	@echo "$(GREEN)✓ Containers stopped$(NC)"

restart: ## Restart all Docker containers
	@echo "$(BLUE)Restarting Docker containers...$(NC)"
	@docker-compose restart
	@echo "$(GREEN)✓ Containers restarted$(NC)"

logs: ## View logs from all containers
	@docker-compose logs -f

logs-laravel: ## View Laravel logs
	@docker-compose logs -f laravel

logs-fastapi: ## View FastAPI logs
	@docker-compose logs -f fastapi

logs-celery: ## View Celery worker logs
	@docker-compose logs -f celery_worker

build: ## Build Docker containers
	@echo "$(BLUE)Building Docker containers...$(NC)"
	@docker-compose build
	@echo "$(GREEN)✓ Build complete$(NC)"

rebuild: ## Rebuild Docker containers without cache
	@echo "$(BLUE)Rebuilding Docker containers...$(NC)"
	@docker-compose build --no-cache
	@echo "$(GREEN)✓ Rebuild complete$(NC)"

clean: ## Remove all containers, volumes, and images
	@echo "$(YELLOW)WARNING: This will remove all containers, volumes, and data!$(NC)"
	@read -p "Are you sure? (y/N) " -n 1 -r; \
	echo; \
	if [[ $$REPLY =~ ^[Yy]$$ ]]; then \
		docker-compose down -v --rmi all; \
		echo "$(GREEN)✓ Cleanup complete$(NC)"; \
	fi

# Laravel commands
laravel-shell: ## Access Laravel container shell
	@docker-compose exec laravel sh

laravel-install: ## Install Laravel dependencies
	@docker-compose exec laravel composer install

laravel-update: ## Update Laravel dependencies
	@docker-compose exec laravel composer update

migrate: ## Run database migrations
	@echo "$(BLUE)Running migrations...$(NC)"
	@docker-compose exec laravel php artisan migrate
	@echo "$(GREEN)✓ Migrations complete$(NC)"

migrate-fresh: ## Fresh migration (drop all tables)
	@echo "$(YELLOW)WARNING: This will drop all tables!$(NC)"
	@read -p "Are you sure? (y/N) " -n 1 -r; \
	echo; \
	if [[ $$REPLY =~ ^[Yy]$$ ]]; then \
		docker-compose exec laravel php artisan migrate:fresh; \
		echo "$(GREEN)✓ Fresh migration complete$(NC)"; \
	fi

seed: ## Seed database with test data
	@echo "$(BLUE)Seeding database...$(NC)"
	@docker-compose exec laravel php artisan db:seed --class=DevelopmentSeeder
	@echo "$(GREEN)✓ Database seeded$(NC)"

cache-clear: ## Clear all Laravel caches
	@echo "$(BLUE)Clearing caches...$(NC)"
	@docker-compose exec laravel php artisan cache:clear
	@docker-compose exec laravel php artisan config:clear
	@docker-compose exec laravel php artisan route:clear
	@docker-compose exec laravel php artisan view:clear
	@echo "$(GREEN)✓ Caches cleared$(NC)"

# FastAPI commands
fastapi-shell: ## Access FastAPI container shell
	@docker-compose exec fastapi bash

fastapi-install: ## Install Python dependencies
	@docker-compose exec fastapi pip install -r requirements.txt

# Testing commands
test: ## Run all tests
	@echo "$(BLUE)Running Laravel tests...$(NC)"
	@docker-compose exec laravel php artisan test
	@echo ""
	@echo "$(BLUE)Running Python tests...$(NC)"
	@docker-compose exec fastapi pytest

test-laravel: ## Run Laravel tests
	@docker-compose exec laravel php artisan test

test-laravel-coverage: ## Run Laravel tests with coverage
	@docker-compose exec laravel php artisan test --coverage

test-python: ## Run Python tests
	@docker-compose exec fastapi pytest

test-python-coverage: ## Run Python tests with coverage
	@docker-compose exec fastapi pytest --cov=app --cov-report=html

# Code quality commands
lint: ## Run linters
	@echo "$(BLUE)Running PHP CS Fixer...$(NC)"
	@docker-compose exec laravel ./vendor/bin/php-cs-fixer fix --dry-run --diff
	@echo ""
	@echo "$(BLUE)Running Psalm...$(NC)"
	@docker-compose exec laravel ./vendor/bin/psalm
	@echo ""
	@echo "$(BLUE)Running Flake8...$(NC)"
	@docker-compose exec fastapi flake8 app/

format: ## Format code
	@echo "$(BLUE)Formatting PHP code...$(NC)"
	@docker-compose exec laravel ./vendor/bin/php-cs-fixer fix
	@echo ""
	@echo "$(BLUE)Formatting Python code...$(NC)"
	@docker-compose exec fastapi black app/
	@echo "$(GREEN)✓ Code formatted$(NC)"

psalm: ## Run Psalm static analysis
	@docker-compose exec laravel ./vendor/bin/psalm

# Documentation commands
docs: ## Generate API documentation
	@echo "$(BLUE)Generating API documentation...$(NC)"
	@docker-compose exec laravel php artisan l5-swagger:generate
	@echo "$(GREEN)✓ Documentation generated$(NC)"
	@echo "$(BLUE)View at: http://localhost:8000/api/documentation$(NC)"

# Database commands
db-shell: ## Access PostgreSQL shell
	@docker-compose exec postgres psql -U postgres -d rotational_contribution

db-backup: ## Backup database
	@echo "$(BLUE)Creating database backup...$(NC)"
	@docker-compose exec postgres pg_dump -U postgres rotational_contribution > backup_$$(date +%Y%m%d_%H%M%S).sql
	@echo "$(GREEN)✓ Backup created$(NC)"

db-restore: ## Restore database from backup (usage: make db-restore FILE=backup.sql)
	@if [ -z "$(FILE)" ]; then \
		echo "$(YELLOW)Usage: make db-restore FILE=backup.sql$(NC)"; \
		exit 1; \
	fi
	@echo "$(BLUE)Restoring database from $(FILE)...$(NC)"
	@docker-compose exec -T postgres psql -U postgres -d rotational_contribution < $(FILE)
	@echo "$(GREEN)✓ Database restored$(NC)"

# Redis commands
redis-shell: ## Access Redis CLI
	@docker-compose exec redis redis-cli

redis-flush: ## Flush all Redis data
	@echo "$(YELLOW)WARNING: This will delete all Redis data!$(NC)"
	@read -p "Are you sure? (y/N) " -n 1 -r; \
	echo; \
	if [[ $$REPLY =~ ^[Yy]$$ ]]; then \
		docker-compose exec redis redis-cli FLUSHALL; \
		echo "$(GREEN)✓ Redis flushed$(NC)"; \
	fi

# Status commands
status: ## Show status of all services
	@docker-compose ps

health: ## Check health of all services
	@echo "$(BLUE)Checking service health...$(NC)"
	@echo ""
	@echo "Laravel API:"
	@curl -s http://localhost:8000/health || echo "$(YELLOW)Not responding$(NC)"
	@echo ""
	@echo "FastAPI:"
	@curl -s http://localhost:8001/health || echo "$(YELLOW)Not responding$(NC)"
	@echo ""
	@echo "PostgreSQL:"
	@docker-compose exec postgres pg_isready -U postgres || echo "$(YELLOW)Not ready$(NC)"
	@echo ""
	@echo "Redis:"
	@docker-compose exec redis redis-cli ping || echo "$(YELLOW)Not responding$(NC)"

# Git commands
git-setup: ## Setup Git hooks and commit template
	@echo "$(BLUE)Setting up Git configuration...$(NC)"
	@git config core.hooksPath .githooks
	@git config commit.template .gitmessage
	@chmod +x .githooks/pre-commit
	@chmod +x .githooks/pre-push
	@echo "$(GREEN)✓ Git configuration complete$(NC)"
