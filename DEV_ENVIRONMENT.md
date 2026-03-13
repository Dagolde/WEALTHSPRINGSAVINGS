# Development Environment Guide

This guide provides comprehensive instructions for setting up and working with the Rotational Contribution App development environment.

## Table of Contents

- [Prerequisites](#prerequisites)
- [Quick Start](#quick-start)
- [Manual Setup](#manual-setup)
- [Docker Services](#docker-services)
- [Database Management](#database-management)
- [API Documentation](#api-documentation)
- [Git Hooks](#git-hooks)
- [Testing](#testing)
- [Troubleshooting](#troubleshooting)

## Prerequisites

Before setting up the development environment, ensure you have the following installed:

### Required Software

1. **Docker Desktop** (v20.10+)
   - [Download for Windows](https://www.docker.com/products/docker-desktop)
   - [Download for Mac](https://www.docker.com/products/docker-desktop)
   - [Install on Linux](https://docs.docker.com/engine/install/)

2. **Docker Compose** (v2.0+)
   - Included with Docker Desktop
   - Linux: `sudo apt-get install docker-compose-plugin`

3. **Git** (v2.30+)
   - [Download](https://git-scm.com/downloads)

### Optional (for local development without Docker)

- PHP 8.2+
- Composer 2.5+
- PostgreSQL 14+
- Redis 7+
- Python 3.9+
- Node.js 18+ (for frontend assets)

## Quick Start

### Automated Setup

The easiest way to set up the development environment is using the automated setup script:

**On Linux/Mac:**
```bash
chmod +x setup-dev-environment.sh
./setup-dev-environment.sh
```

**On Windows (PowerShell):**
```powershell
.\setup-dev-environment.ps1
```

This script will:
- Check prerequisites
- Configure Git hooks
- Create environment files
- Build Docker containers
- Start all services
- Run database migrations
- Optionally seed test data
- Generate API documentation

### Manual Quick Start

If you prefer manual setup:

```bash
# 1. Copy environment files
cp backend/.env.example backend/.env
cp microservices/.env.example microservices/.env

# 2. Build and start containers
docker-compose up -d

# 3. Install Laravel dependencies
docker-compose exec laravel composer install

# 4. Generate application key
docker-compose exec laravel php artisan key:generate

# 5. Run migrations
docker-compose exec laravel php artisan migrate

# 6. Seed database (optional)
docker-compose exec laravel php artisan db:seed --class=DevelopmentSeeder

# 7. Generate API docs
docker-compose exec laravel php artisan l5-swagger:generate
```

## Docker Services

The development environment consists of the following Docker services:

### Core Services

| Service | Port | Description |
|---------|------|-------------|
| **laravel** | 8000 | Laravel API backend |
| **laravel_queue** | - | Laravel queue worker |
| **fastapi** | 8001 | FastAPI microservices |
| **celery_worker** | - | Celery task worker |
| **celery_beat** | - | Celery scheduler |
| **postgres** | 5432 | PostgreSQL database |
| **redis** | 6379 | Redis cache & queue |
| **nginx** | 80, 443 | Reverse proxy & load balancer |

### Management Tools

| Service | Port | Description |
|---------|------|-------------|
| **adminer** | 8080 | Database management UI |
| **redis_commander** | 8081 | Redis management UI |

### Service URLs

- **Laravel API**: http://localhost:8000
- **FastAPI Services**: http://localhost:8001
- **API Documentation**: http://localhost:8000/api/documentation
- **Nginx Proxy**: http://localhost
- **Database Admin**: http://localhost:8080
- **Redis Commander**: http://localhost:8081

## Database Management

### Connection Details

```
Host:     localhost
Port:     5432
Database: rotational_contribution
Username: postgres
Password: password
```

### Using Adminer

Access Adminer at http://localhost:8080 to manage the database through a web interface.

### Command Line Access

```bash
# Connect to PostgreSQL
docker-compose exec postgres psql -U postgres -d rotational_contribution

# Run migrations
docker-compose exec laravel php artisan migrate

# Rollback migrations
docker-compose exec laravel php artisan migrate:rollback

# Fresh migration (drop all tables and re-migrate)
docker-compose exec laravel php artisan migrate:fresh

# Seed database
docker-compose exec laravel php artisan db:seed
```

### Database Factories

The project includes factory classes for generating test data:

```php
// Create a user
$user = User::factory()->create();

// Create a verified user with balance
$user = User::factory()
    ->verified()
    ->withBalance(50000.00)
    ->create();

// Create a group
$group = Group::factory()->create();

// Create an active group with 10 members
$group = Group::factory()
    ->active()
    ->withMembers(10)
    ->create();
```

### Seeders

```bash
# Seed development data (users, groups, etc.)
docker-compose exec laravel php artisan db:seed --class=DevelopmentSeeder

# Seed specific seeder
docker-compose exec laravel php artisan db:seed --class=UserSeeder
```

## API Documentation

The project uses Swagger/OpenAPI for API documentation.

### Accessing Documentation

Visit http://localhost:8000/api/documentation to view the interactive API documentation.

### Generating Documentation

```bash
# Generate/update API documentation
docker-compose exec laravel php artisan l5-swagger:generate
```

### Adding API Documentation

Use PHP attributes to document your API endpoints:

```php
use OpenApi\Attributes as OA;

#[OA\Get(
    path: '/api/v1/users/{id}',
    summary: 'Get user by ID',
    tags: ['Users'],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'integer')
        )
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'User found',
            content: new OA\JsonContent(ref: '#/components/schemas/User')
        ),
        new OA\Response(response: 404, description: 'User not found')
    ]
)]
public function show(int $id)
{
    // Implementation
}
```

## Git Hooks

The project includes Git hooks for code quality checks.

### Setup

Git hooks are automatically configured when you run the setup script. To manually configure:

```bash
# Configure Git to use custom hooks directory
git config core.hooksPath .githooks

# Make hooks executable (Linux/Mac)
chmod +x .githooks/pre-commit
chmod +x .githooks/pre-push
```

### Pre-Commit Hook

Runs before each commit and checks:
- PHP syntax errors
- PHP CS Fixer (code style)
- Psalm static analysis
- Python syntax errors
- Black formatter (Python)
- Flake8 linter (Python)
- Debug statements (dd, dump, var_dump)
- Direct commits to main/master branch

### Pre-Push Hook

Runs before pushing and checks:
- Laravel tests
- Python tests
- .env files not being pushed

### Bypassing Hooks

In rare cases where you need to bypass hooks:

```bash
# Skip pre-commit hook
git commit --no-verify -m "message"

# Skip pre-push hook
git push --no-verify
```

**Note:** Only bypass hooks when absolutely necessary and ensure code quality manually.

## Testing

### Laravel Tests

```bash
# Run all tests
docker-compose exec laravel php artisan test

# Run specific test file
docker-compose exec laravel php artisan test tests/Feature/UserTest.php

# Run with coverage
docker-compose exec laravel php artisan test --coverage

# Run parallel tests
docker-compose exec laravel php artisan test --parallel
```

### Python Tests

```bash
# Run all tests
docker-compose exec fastapi pytest

# Run specific test file
docker-compose exec fastapi pytest tests/test_payment_service.py

# Run with coverage
docker-compose exec fastapi pytest --cov=app --cov-report=html

# Run property-based tests
docker-compose exec fastapi pytest tests/property/
```

### Test Data

Use factories to create test data:

```php
// In Laravel tests
$user = User::factory()->create();
$group = Group::factory()->active()->create();
```

## Common Tasks

### Viewing Logs

```bash
# View all logs
docker-compose logs -f

# View specific service logs
docker-compose logs -f laravel
docker-compose logs -f fastapi
docker-compose logs -f celery_worker

# View last 100 lines
docker-compose logs --tail=100 laravel
```

### Restarting Services

```bash
# Restart all services
docker-compose restart

# Restart specific service
docker-compose restart laravel
docker-compose restart fastapi
```

### Clearing Caches

```bash
# Laravel cache
docker-compose exec laravel php artisan cache:clear
docker-compose exec laravel php artisan config:clear
docker-compose exec laravel php artisan route:clear
docker-compose exec laravel php artisan view:clear

# Redis cache
docker-compose exec redis redis-cli FLUSHALL
```

### Running Artisan Commands

```bash
# General format
docker-compose exec laravel php artisan <command>

# Examples
docker-compose exec laravel php artisan make:model Post
docker-compose exec laravel php artisan make:controller PostController
docker-compose exec laravel php artisan make:migration create_posts_table
```

### Running Composer Commands

```bash
# Install package
docker-compose exec laravel composer require package/name

# Update dependencies
docker-compose exec laravel composer update

# Dump autoload
docker-compose exec laravel composer dump-autoload
```

### Accessing Container Shell

```bash
# Laravel container
docker-compose exec laravel sh

# FastAPI container
docker-compose exec fastapi bash

# PostgreSQL container
docker-compose exec postgres sh
```

## Troubleshooting

### Port Already in Use

If you get "port already in use" errors:

```bash
# Check what's using the port (Linux/Mac)
lsof -i :8000

# Check what's using the port (Windows)
netstat -ano | findstr :8000

# Stop the conflicting service or change ports in docker-compose.yml
```

### Database Connection Issues

```bash
# Check if PostgreSQL is running
docker-compose ps postgres

# View PostgreSQL logs
docker-compose logs postgres

# Restart PostgreSQL
docker-compose restart postgres

# Check connection from Laravel
docker-compose exec laravel php artisan db:show
```

### Permission Issues (Linux/Mac)

```bash
# Fix storage permissions
sudo chown -R $USER:$USER backend/storage
sudo chmod -R 775 backend/storage

# Fix bootstrap cache permissions
sudo chown -R $USER:$USER backend/bootstrap/cache
sudo chmod -R 775 backend/bootstrap/cache
```

### Container Won't Start

```bash
# View container logs
docker-compose logs <service-name>

# Rebuild container
docker-compose build --no-cache <service-name>

# Remove and recreate container
docker-compose rm -f <service-name>
docker-compose up -d <service-name>
```

### Clear Everything and Start Fresh

```bash
# Stop and remove all containers
docker-compose down

# Remove volumes (WARNING: This deletes all data)
docker-compose down -v

# Remove images
docker-compose down --rmi all

# Rebuild and start
docker-compose build --no-cache
docker-compose up -d
```

## Environment Variables

### Laravel (.env)

Key environment variables for Laravel:

```env
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=rotational_contribution
DB_USERNAME=postgres
DB_PASSWORD=password

REDIS_HOST=redis
REDIS_PORT=6379

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
```

### FastAPI (.env)

Key environment variables for FastAPI:

```env
ENVIRONMENT=development
DATABASE_URL=postgresql+asyncpg://postgres:password@postgres:5432/rotational_contribution
REDIS_URL=redis://redis:6379/0
CELERY_BROKER_URL=redis://redis:6379/2
CELERY_RESULT_BACKEND=redis://redis:6379/3
LARAVEL_API_URL=http://laravel:8000
```

## Best Practices

1. **Always run tests before committing**
2. **Use factories for test data**
3. **Keep .env files secure and never commit them**
4. **Use meaningful commit messages**
5. **Create feature branches for new work**
6. **Run code quality checks regularly**
7. **Keep dependencies up to date**
8. **Document new API endpoints**
9. **Write tests for new features**
10. **Review logs regularly**

## Additional Resources

- [Laravel Documentation](https://laravel.com/docs)
- [FastAPI Documentation](https://fastapi.tiangolo.com/)
- [Docker Documentation](https://docs.docker.com/)
- [PostgreSQL Documentation](https://www.postgresql.org/docs/)
- [Redis Documentation](https://redis.io/documentation)

## Support

For issues or questions:
1. Check this documentation
2. Review the troubleshooting section
3. Check Docker logs: `docker-compose logs`
4. Contact the development team

---

**Last Updated:** 2024-01-15
