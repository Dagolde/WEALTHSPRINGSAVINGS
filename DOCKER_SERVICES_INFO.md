# Docker Development Environment - Service Information

## ✅ Status: All Services Running

The Docker development environment is successfully running with all services healthy.

## 🌐 Service URLs (Updated Ports)

| Service | URL | Description |
|---------|-----|-------------|
| **Laravel API** | http://localhost:8002 | Main backend API server |
| **API Documentation** | http://localhost:8002/api/documentation | Swagger/OpenAPI docs |
| **FastAPI Microservices** | http://localhost:8003 | Payment, scheduler, notification, fraud services |
| **FastAPI Docs** | http://localhost:8003/docs | Interactive API documentation |
| **Adminer (Database UI)** | http://localhost:8082 | PostgreSQL management interface |
| **Redis Commander** | http://localhost:8083 | Redis management interface |

## 🗄️ Database Connection

| Parameter | Value |
|-----------|-------|
| **Host** | localhost |
| **Port** | 5433 (mapped from internal 5432) |
| **Database** | rotational_contribution |
| **Username** | postgres |
| **Password** | password |

## 📦 Redis Connection

| Parameter | Value |
|-----------|-------|
| **Host** | localhost |
| **Port** | 6380 (mapped from internal 6379) |
| **Password** | password |
| **Databases** | 0: General, 1: Cache, 2: Queue, 3: Celery Results |

## ✅ Completed Migrations

The following database migrations have been successfully applied:

1. ✅ `0001_01_01_000000_create_users_table` - Users table with KYC fields
2. ✅ `0001_01_01_000001_create_cache_table` - Cache table
3. ✅ `0001_01_01_000002_create_jobs_table` - Queue jobs table
4. ✅ `2026_03_05_113407_create_personal_access_tokens_table` - Sanctum tokens
5. ✅ `2026_03_05_123734_create_bank_accounts_table` - Bank accounts table
6. ✅ `2026_03_05_130000_create_groups_table` - Groups table (Task 2.4 completed!)

## 🐳 Docker Commands

```bash
# View logs for all services
docker-compose -f docker-compose.dev.yml logs -f

# View logs for specific service
docker-compose -f docker-compose.dev.yml logs -f laravel
docker-compose -f docker-compose.dev.yml logs -f fastapi

# Stop all services
docker-compose -f docker-compose.dev.yml down

# Restart all services
docker-compose -f docker-compose.dev.yml restart

# Access Laravel container shell
docker-compose -f docker-compose.dev.yml exec laravel sh

# Run Laravel commands
docker-compose -f docker-compose.dev.yml exec laravel php artisan migrate
docker-compose -f docker-compose.dev.yml exec laravel php artisan tinker
docker-compose -f docker-compose.dev.yml exec laravel php artisan route:list

# Access FastAPI container shell
docker-compose -f docker-compose.dev.yml exec fastapi sh

# Check service status
docker-compose -f docker-compose.dev.yml ps
```

## 🧪 Testing the Services

### Test Laravel API
```bash
curl http://localhost:8002/api/health
```

### Test FastAPI
```bash
curl http://localhost:8003/health
```

### Test Database Connection
Visit http://localhost:8082 and login with:
- System: PostgreSQL
- Server: postgres
- Username: postgres
- Password: password
- Database: rotational_contribution

### Test Redis Connection
Visit http://localhost:8083 (no login required)

## 📝 Notes

- **Port Changes**: Ports were updated to avoid conflicts with existing services on your system
  - PostgreSQL: 5432 → 5433
  - Redis: 6379 → 6380
  - Laravel: 8000 → 8002
  - FastAPI: 8001 → 8003
  - Adminer: 8080 → 8082
  - Redis Commander: 8081 → 8083

- **Auto-start**: The startup script automatically:
  - Generates Laravel app key
  - Runs all pending migrations
  - Starts all services with health checks

- **Data Persistence**: All data is persisted in Docker volumes:
  - `postgres_data` - Database data
  - `redis_data` - Redis data
  - `laravel_storage` - Laravel storage files

## 🎯 Next Steps

Now that Docker is running, you can:

1. ✅ Test the services using the URLs above
2. ✅ Continue with remaining database migrations (Tasks 2.5-2.10)
3. ✅ Implement backend services (Tasks 3-7)
4. ✅ Test as you develop using the live Docker environment

The environment is ready for development! 🚀
