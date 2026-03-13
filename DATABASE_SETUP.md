# Database Infrastructure Setup

This document describes the database infrastructure configuration for the Rotational Contribution App.

## Overview

The application uses:
- **PostgreSQL** as the primary database for ACID-compliant financial transactions
- **Redis** for caching, session storage, and queue management
- **Connection pooling** for optimal database performance
- **Read replica support** for future horizontal scaling

## PostgreSQL Configuration

### Development Environment

**Database Details:**
- Host: `127.0.0.1` (localhost)
- Port: `5432`
- Database: `rotational_contribution`
- Username: `postgres`
- Password: `password`

### Connection Pooling

Both Laravel and FastAPI are configured with connection pooling:

**Laravel (PHP PDO):**
- Minimum connections: 2
- Maximum connections: 20
- Connection timeout: 5 seconds
- Persistent connections: Disabled (for better resource management)

**FastAPI (SQLAlchemy + asyncpg):**
- Pool size: 20
- Max overflow: 10
- Pool pre-ping: Enabled (verifies connections before use)
- Pool recycle: 3600 seconds (1 hour)

### Read Replica Configuration

The system is prepared for read replica scaling. To enable:

1. **Backend (.env):**
```env
DB_READ_HOST=replica-host.example.com
DB_READ_PORT=5432
DB_READ_DATABASE=rotational_contribution
DB_READ_USERNAME=postgres
DB_READ_PASSWORD=password
```

2. **Laravel automatically routes:**
   - Write operations → Primary database
   - Read operations → Read replica

## Redis Configuration

### Database Allocation

Redis uses multiple databases for different purposes:

| Database | Purpose | Used By |
|----------|---------|---------|
| 0 | Default/General | Both |
| 1 | Cache | Both |
| 2 | Queue/Celery Broker | Both |
| 3 | Celery Results | FastAPI |

### Laravel Redis Configuration

**Connection Details:**
- Host: `127.0.0.1`
- Port: `6379`
- Password: `null` (no password in development)

**Usage:**
- Session storage
- Cache storage
- Queue management
- Broadcast driver

### FastAPI Redis Configuration

**Connection Details:**
- Base URL: `redis://localhost:6379/0`
- Cache DB: `1`
- Celery Broker DB: `2`
- Celery Backend DB: `3`

**Features:**
- Async Redis client for high-performance caching
- Sync Redis client for Celery compatibility
- Connection pooling with automatic reconnection
- Health check monitoring

## Setup Instructions

### 1. Install PostgreSQL

**Ubuntu/Debian:**
```bash
sudo apt update
sudo apt install postgresql postgresql-contrib
sudo systemctl start postgresql
sudo systemctl enable postgresql
```

**macOS:**
```bash
brew install postgresql@14
brew services start postgresql@14
```

**Windows:**
Download and install from [PostgreSQL official website](https://www.postgresql.org/download/windows/)

### 2. Create Database

```bash
# Connect to PostgreSQL
sudo -u postgres psql

# Create database
CREATE DATABASE rotational_contribution;

# Create user (if needed)
CREATE USER postgres WITH PASSWORD 'password';

# Grant privileges
GRANT ALL PRIVILEGES ON DATABASE rotational_contribution TO postgres;

# Exit
\q
```

### 3. Install Redis

**Ubuntu/Debian:**
```bash
sudo apt update
sudo apt install redis-server
sudo systemctl start redis-server
sudo systemctl enable redis-server
```

**macOS:**
```bash
brew install redis
brew services start redis
```

**Windows:**
Download from [Redis Windows port](https://github.com/microsoftarchive/redis/releases)

### 4. Verify Connections

**Test PostgreSQL:**
```bash
psql -h 127.0.0.1 -U postgres -d rotational_contribution
```

**Test Redis:**
```bash
redis-cli ping
# Should return: PONG
```

### 5. Configure Laravel

```bash
cd backend

# Copy environment file
cp .env.example .env

# Update database settings in .env
# DB_CONNECTION=pgsql
# DB_HOST=127.0.0.1
# DB_PORT=5432
# DB_DATABASE=rotational_contribution
# DB_USERNAME=postgres
# DB_PASSWORD=password

# Install PHP dependencies
composer install

# Generate application key
php artisan key:generate

# Run migrations (when ready)
php artisan migrate
```

### 6. Configure FastAPI Microservices

```bash
cd microservices

# Create virtual environment
python -m venv venv
source venv/bin/activate  # On Windows: venv\Scripts\activate

# Install dependencies
pip install -r requirements.txt

# Copy environment file
cp .env.example .env

# Update database settings in .env
# DATABASE_URL=postgresql+asyncpg://postgres:password@localhost:5432/rotational_contribution
# REDIS_URL=redis://localhost:6379/0

# Run the application
uvicorn app.main:app --reload
```

## Docker Setup (Alternative)

For containerized development:

```bash
cd microservices

# Start all services (PostgreSQL, Redis, FastAPI, Celery)
docker-compose up -d

# View logs
docker-compose logs -f

# Stop services
docker-compose down
```

## Connection Health Monitoring

### Laravel

Laravel automatically handles connection health through:
- PDO connection timeout settings
- Automatic reconnection on connection loss
- Queue worker connection refresh

### FastAPI

Health check endpoints:

```bash
# Check application health
curl http://localhost:8001/health

# Database and Redis health are checked on startup
# Check logs for connection status
```

## Performance Tuning

### PostgreSQL

**Recommended settings for production (`postgresql.conf`):**
```conf
max_connections = 100
shared_buffers = 256MB
effective_cache_size = 1GB
maintenance_work_mem = 64MB
checkpoint_completion_target = 0.9
wal_buffers = 16MB
default_statistics_target = 100
random_page_cost = 1.1
effective_io_concurrency = 200
work_mem = 4MB
min_wal_size = 1GB
max_wal_size = 4GB
```

### Redis

**Recommended settings for production (`redis.conf`):**
```conf
maxmemory 512mb
maxmemory-policy allkeys-lru
save 900 1
save 300 10
save 60 10000
```

## Backup Strategy

### PostgreSQL Backups

**Daily automated backup:**
```bash
#!/bin/bash
# backup.sh
BACKUP_DIR="/var/backups/postgresql"
DATE=$(date +%Y%m%d_%H%M%S)
pg_dump -U postgres rotational_contribution > "$BACKUP_DIR/backup_$DATE.sql"
```

### Redis Persistence

Redis is configured with RDB snapshots:
- Snapshot every 15 minutes if at least 1 key changed
- Snapshot every 5 minutes if at least 10 keys changed
- Snapshot every 60 seconds if at least 10000 keys changed

## Troubleshooting

### Connection Issues

**PostgreSQL connection refused:**
```bash
# Check if PostgreSQL is running
sudo systemctl status postgresql

# Check PostgreSQL logs
sudo tail -f /var/log/postgresql/postgresql-14-main.log
```

**Redis connection refused:**
```bash
# Check if Redis is running
sudo systemctl status redis-server

# Check Redis logs
sudo tail -f /var/log/redis/redis-server.log
```

### Performance Issues

**Check active connections:**
```sql
-- PostgreSQL
SELECT count(*) FROM pg_stat_activity;

-- View connection details
SELECT * FROM pg_stat_activity WHERE datname = 'rotational_contribution';
```

**Check Redis memory usage:**
```bash
redis-cli info memory
```

## Security Considerations

### Production Checklist

- [ ] Change default PostgreSQL password
- [ ] Enable SSL/TLS for PostgreSQL connections
- [ ] Set Redis password (`requirepass` in redis.conf)
- [ ] Configure firewall rules to restrict database access
- [ ] Enable PostgreSQL connection encryption
- [ ] Use separate database users with minimal privileges
- [ ] Enable audit logging for sensitive operations
- [ ] Regular security updates for PostgreSQL and Redis
- [ ] Implement database backup encryption
- [ ] Configure read replica with SSL

### Environment-Specific Settings

**Development:**
- Relaxed connection limits
- Verbose logging
- No SSL required

**Staging:**
- Production-like configuration
- Moderate logging
- SSL recommended

**Production:**
- Strict connection limits
- Error-level logging only
- SSL required
- Read replicas enabled
- Automated backups
- Monitoring and alerting

## Monitoring

### Key Metrics to Monitor

**PostgreSQL:**
- Active connections
- Query execution time
- Cache hit ratio
- Disk I/O
- Replication lag (if using replicas)

**Redis:**
- Memory usage
- Hit/miss ratio
- Connected clients
- Commands per second
- Evicted keys

### Recommended Tools

- **pgAdmin** - PostgreSQL management
- **Redis Commander** - Redis management
- **Prometheus + Grafana** - Metrics and monitoring
- **Sentry** - Error tracking
- **New Relic / DataDog** - APM and infrastructure monitoring

## Next Steps

After database infrastructure is set up:

1. Run database migrations (Task 2.x)
2. Seed initial data
3. Configure backup automation
4. Set up monitoring and alerting
5. Performance testing and optimization
