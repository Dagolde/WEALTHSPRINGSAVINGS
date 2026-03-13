# Docker Development Guide

Complete guide for running the Rotational Contribution App in Docker.

## Quick Start

### Windows (PowerShell)
```powershell
.\start-docker-dev.ps1
```

### Linux/Mac (Bash)
```bash
chmod +x start-docker-dev.sh
./start-docker-dev.sh
```

## What Gets Started

The Docker environment includes:

1. **PostgreSQL** (port 5432) - Database
2. **Redis** (port 6379) - Cache & Queue
3. **Laravel** (port 8000) - Backend API
4. **Laravel Queue** - Background job processor
5. **FastAPI** (port 8001) - Microservices
6. **Celery Worker** - Distributed task processor
7. **Celery Beat** - Task scheduler
8. **Adminer** (port 8080) - Database management UI
9. **Redis Commander** (port 8081) - Redis management UI

## Service URLs

| Service | URL | Description |
|---------|-----|-------------|
| Laravel API | http://localhost:8000 | Main backend API |
| API Docs | http://localhost:8000/api/documentation | Swagger UI |
| FastAPI | http://localhost:8001 | Microservices |
| FastAPI Docs | http://localhost:8001/docs | Interactive API docs |
| Database Admin | http://localhost:8080 | Adminer UI |
| Redis Commander | http://localhost:8081 | Redis management |

## Database Connection

```
Host:     localhost
Port:     5432
Database: rotational_contribution
Username: postgres
Password: password
```

## Common Commands

### Start Services
```powershell
docker-compose -f docker-compose.dev.yml up -d
```

### Stop Services
```powershell
docker-compose -f docker-compose.dev.yml down
```

### View Logs
```powershell
# All services
docker-compose -f docker-compose.dev.yml logs -f

# Specific service
docker-compose -f docker-compose.dev.yml logs -f laravel
docker-compose -f docker-compose.dev.yml logs -f fastapi
```

### Check Status
```powershell
docker-compose -f docker-compose.dev.yml ps
```

### Restart Services
```powershell
# All services
docker-compose -f docker-compose.dev.yml restart

# Specific service
docker-compose -f docker-compose.dev.yml restart laravel
```

### Access Container Shell
```powershell
# Laravel
docker-compose -f docker-compose.dev.yml exec laravel sh

# FastAPI
docker-compose -f docker-compose.dev.yml exec fastapi bash

# PostgreSQL
docker-compose -f docker-compose.dev.yml exec postgres psql -U postgres -d rotational_contribution
```

## Laravel Commands

### Run Migrations
```powershell
docker-compose -f docker-compose.dev.yml exec laravel php artisan migrate
```

### Rollback Migrations
```powershell
docker-compose -f docker-compose.dev.yml exec laravel php artisan migrate:rollback
```

### Seed Database
```powershell
docker-compose -f docker-compose.dev.yml exec laravel php artisan db:seed
```

### Clear Cache
```powershell
docker-compose -f docker-compose.dev.yml exec laravel php artisan cache:clear
docker-compose -f docker-compose.dev.yml exec laravel php artisan config:clear
```

### Run Tests
```powershell
docker-compose -f docker-compose.dev.yml exec laravel php artisan test
```

### Generate API Documentation
```powershell
docker-compose -f docker-compose.dev.yml exec laravel php artisan l5-swagger:generate
```

## FastAPI Commands

### Run Tests
```powershell
docker-compose -f docker-compose.dev.yml exec fastapi pytest
```

### Check Infrastructure
```powershell
docker-compose -f docker-compose.dev.yml exec fastapi python check_infrastructure.py
```

## Development Workflow

### 1. Start Environment
```powershell
.\start-docker-dev.ps1
```

### 2. Make Code Changes
- Edit files in `backend/` or `microservices/`
- Changes are automatically reflected (hot reload enabled)

### 3. Run Migrations (if database changes)
```powershell
docker-compose -f docker-compose.dev.yml exec laravel php artisan migrate
```

### 4. Test Your Changes
- Visit http://localhost:8000 for Laravel
- Visit http://localhost:8001 for FastAPI
- Use Swagger UI for API testing

### 5. View Logs
```powershell
docker-compose -f docker-compose.dev.yml logs -f
```

## Troubleshooting

### Services Won't Start

**Check Docker is running:**
```powershell
docker info
```

**View build logs:**
```powershell
docker-compose -f docker-compose.dev.yml up --build
```

### Port Already in Use

**Find what's using the port:**
```powershell
netstat -ano | findstr :8000
```

**Change ports in docker-compose.dev.yml:**
```yaml
ports:
  - "8001:8000"  # Use port 8001 instead of 8000
```

### Database Connection Failed

**Check PostgreSQL is running:**
```powershell
docker-compose -f docker-compose.dev.yml ps postgres
```

**View PostgreSQL logs:**
```powershell
docker-compose -f docker-compose.dev.yml logs postgres
```

**Restart PostgreSQL:**
```powershell
docker-compose -f docker-compose.dev.yml restart postgres
```

### Laravel Errors

**Clear all caches:**
```powershell
docker-compose -f docker-compose.dev.yml exec laravel php artisan cache:clear
docker-compose -f docker-compose.dev.yml exec laravel php artisan config:clear
docker-compose -f docker-compose.dev.yml exec laravel php artisan route:clear
docker-compose -f docker-compose.dev.yml exec laravel php artisan view:clear
```

**Regenerate app key:**
```powershell
docker-compose -f docker-compose.dev.yml exec laravel php artisan key:generate
```

### Permission Issues

**Fix storage permissions:**
```powershell
docker-compose -f docker-compose.dev.yml exec laravel chmod -R 775 storage bootstrap/cache
docker-compose -f docker-compose.dev.yml exec laravel chown -R www-data:www-data storage bootstrap/cache
```

### Clean Start

**Remove everything and start fresh:**
```powershell
# Stop and remove containers
docker-compose -f docker-compose.dev.yml down

# Remove volumes (WARNING: deletes all data)
docker-compose -f docker-compose.dev.yml down -v

# Rebuild and start
docker-compose -f docker-compose.dev.yml up -d --build
```

## Testing Services

### Test All Services
```powershell
.\test-services.ps1
```

### Manual Testing

**Laravel:**
```powershell
curl http://localhost:8000
```

**FastAPI:**
```powershell
curl http://localhost:8001
```

**PostgreSQL:**
```powershell
docker-compose -f docker-compose.dev.yml exec postgres pg_isready -U postgres
```

**Redis:**
```powershell
docker-compose -f docker-compose.dev.yml exec redis redis-cli -a password ping
```

## Performance Tips

### View Resource Usage
```powershell
docker stats
```

### Limit Resources (if needed)
Edit `docker-compose.dev.yml` and add:
```yaml
services:
  laravel:
    deploy:
      resources:
        limits:
          cpus: '0.5'
          memory: 512M
```

### Prune Unused Resources
```powershell
# Remove unused images
docker image prune -a

# Remove unused volumes
docker volume prune

# Remove everything unused
docker system prune -a
```

## Development Best Practices

1. **Always check logs** when something doesn't work
2. **Use hot reload** - no need to restart containers for code changes
3. **Run migrations** after pulling database changes
4. **Clear caches** if you see unexpected behavior
5. **Use Adminer** for database inspection
6. **Use Swagger UI** for API testing
7. **Check container status** regularly with `docker-compose ps`

## Next Steps

After Docker is running:

1. **Access API Documentation**: http://localhost:8000/api/documentation
2. **Test API Endpoints**: Use Swagger UI
3. **Check Database**: Use Adminer at http://localhost:8080
4. **View Logs**: `docker-compose -f docker-compose.dev.yml logs -f`
5. **Continue Development**: Edit code and see changes live

## Support

For issues:
1. Check this guide
2. View logs: `docker-compose -f docker-compose.dev.yml logs`
3. Check container status: `docker-compose -f docker-compose.dev.yml ps`
4. Try clean restart: `docker-compose -f docker-compose.dev.yml down && docker-compose -f docker-compose.dev.yml up -d --build`

---

**Happy Coding! 🚀**
