# PostgreSQL Setup for CloudPanel

## Overview
CloudPanel supports both MySQL and PostgreSQL. This guide shows how to set up PostgreSQL for your Rotational Contribution App.

## Step 1: Install PostgreSQL on CloudPanel Server

### Check if PostgreSQL is Already Installed
```bash
sudo -u postgres psql --version
```

### Install PostgreSQL 14+ (if not installed)
```bash
# Update package list
sudo apt update

# Install PostgreSQL
sudo apt install postgresql postgresql-contrib -y

# Start and enable PostgreSQL
sudo systemctl start postgresql
sudo systemctl enable postgresql

# Verify installation
sudo systemctl status postgresql
```

## Step 2: Install PHP PostgreSQL Extension

```bash
# Install PHP 8.2 PostgreSQL extension
sudo apt install php8.2-pgsql -y

# Restart PHP-FPM
sudo systemctl restart php8.2-fpm

# Verify installation
php -m | grep pgsql
```

## Step 3: Create Database and User

### Option 1: Using CloudPanel UI (Recommended)
1. Log into CloudPanel
2. Go to **Databases** → **Add Database**
3. Select **PostgreSQL** as database type
4. Fill in:
   - **Database Name**: `rotational_app`
   - **Database User**: `rotational_user`
   - **Password**: Generate strong password
5. Click **Create**

### Option 2: Using Command Line
```bash
# Switch to postgres user
sudo -u postgres psql

# Create database
CREATE DATABASE rotational_app;

# Create user with password
CREATE USER rotational_user WITH ENCRYPTED PASSWORD 'your_secure_password_here';

# Grant privileges
GRANT ALL PRIVILEGES ON DATABASE rotational_app TO rotational_user;

# Grant schema privileges (PostgreSQL 15+)
\c rotational_app
GRANT ALL ON SCHEMA public TO rotational_user;
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO rotational_user;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO rotational_user;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO rotational_user;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO rotational_user;

# Exit
\q
```

## Step 4: Configure PostgreSQL for Laravel

### Update pg_hba.conf (if needed)
```bash
# Edit pg_hba.conf
sudo nano /etc/postgresql/14/main/pg_hba.conf

# Add this line (if not already present):
# local   all             all                                     md5
# host    all             all             127.0.0.1/32            md5

# Reload PostgreSQL
sudo systemctl reload postgresql
```

### Test Connection
```bash
# Test database connection
psql -U rotational_user -h 127.0.0.1 -d rotational_app

# If successful, you'll see:
# rotational_app=>

# Exit with:
\q
```

## Step 5: Update Laravel Configuration

### Update .env file
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=rotational_app
DB_USERNAME=rotational_user
DB_PASSWORD=your_secure_password_here
```

### Test Laravel Database Connection
```bash
cd /home/{site-user}/htdocs/{yourdomain.com}

# Test connection
php artisan tinker
>>> DB::connection()->getPdo();
# Should return PDO object

>>> DB::select('SELECT version()');
# Should return PostgreSQL version

>>> exit
```

## Step 6: Run Migrations

```bash
# Run migrations
php artisan migrate --force

# Seed admin user
php artisan db:seed --class=AdminUserSeeder --force

# Verify tables were created
php artisan tinker
>>> DB::table('users')->count();
# Should return 1 (admin user)
>>> exit
```

## PostgreSQL vs MySQL Differences

### Data Types
Laravel handles most differences automatically, but be aware:

| MySQL | PostgreSQL | Laravel Migration |
|-------|------------|-------------------|
| TINYINT | SMALLINT | `tinyInteger()` |
| BIGINT UNSIGNED | BIGINT | `unsignedBigInteger()` |
| DATETIME | TIMESTAMP | `timestamp()` |
| JSON | JSONB | `json()` |

### Case Sensitivity
- PostgreSQL is case-sensitive for table/column names
- Laravel migrations handle this automatically
- Use lowercase for all database identifiers

### Boolean Values
- PostgreSQL uses `true`/`false` (not 1/0)
- Laravel handles this automatically

## Backup & Restore

### Backup Database
```bash
# Backup to file
pg_dump -U rotational_user -h 127.0.0.1 rotational_app > backup.sql

# Backup with compression
pg_dump -U rotational_user -h 127.0.0.1 rotational_app | gzip > backup.sql.gz
```

### Restore Database
```bash
# Restore from file
psql -U rotational_user -h 127.0.0.1 rotational_app < backup.sql

# Restore from compressed file
gunzip -c backup.sql.gz | psql -U rotational_user -h 127.0.0.1 rotational_app
```

### Automated Daily Backup
```bash
# Create backup directory
mkdir -p /home/{site-user}/backups

# Add to crontab
crontab -e

# Add this line:
0 2 * * * pg_dump -U rotational_user -h 127.0.0.1 rotational_app | gzip > /home/{site-user}/backups/db_$(date +\%Y\%m\%d).sql.gz

# Keep only last 7 days of backups
0 3 * * * find /home/{site-user}/backups -name "db_*.sql.gz" -mtime +7 -delete
```

## Performance Optimization

### Update PostgreSQL Configuration
```bash
# Edit postgresql.conf
sudo nano /etc/postgresql/14/main/postgresql.conf

# Recommended settings for 4GB RAM server:
shared_buffers = 1GB
effective_cache_size = 3GB
maintenance_work_mem = 256MB
checkpoint_completion_target = 0.9
wal_buffers = 16MB
default_statistics_target = 100
random_page_cost = 1.1
effective_io_concurrency = 200
work_mem = 5MB
min_wal_size = 1GB
max_wal_size = 4GB

# Restart PostgreSQL
sudo systemctl restart postgresql
```

### Create Indexes (Already in Migrations)
Your migrations already include performance indexes:
- User email index
- Group status index
- Contribution group_id and user_id indexes
- Transaction indexes
- Timestamp indexes

## Monitoring

### Check Database Size
```bash
psql -U rotational_user -h 127.0.0.1 -d rotational_app -c "SELECT pg_size_pretty(pg_database_size('rotational_app'));"
```

### Check Table Sizes
```bash
psql -U rotational_user -h 127.0.0.1 -d rotational_app -c "SELECT schemaname, tablename, pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) AS size FROM pg_tables WHERE schemaname = 'public' ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC;"
```

### Check Active Connections
```bash
psql -U rotational_user -h 127.0.0.1 -d rotational_app -c "SELECT count(*) FROM pg_stat_activity WHERE datname = 'rotational_app';"
```

### View Slow Queries
```bash
# Enable slow query logging
sudo nano /etc/postgresql/14/main/postgresql.conf

# Add/update:
log_min_duration_statement = 1000  # Log queries taking > 1 second

# Restart PostgreSQL
sudo systemctl restart postgresql

# View logs
sudo tail -f /var/log/postgresql/postgresql-14-main.log
```

## Troubleshooting

### Connection Refused
```bash
# Check if PostgreSQL is running
sudo systemctl status postgresql

# Check if listening on correct port
sudo netstat -plnt | grep 5432

# Check pg_hba.conf
sudo nano /etc/postgresql/14/main/pg_hba.conf
```

### Permission Denied
```bash
# Grant all privileges again
sudo -u postgres psql -d rotational_app -c "GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO rotational_user;"
sudo -u postgres psql -d rotational_app -c "GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO rotational_user;"
```

### Laravel Can't Connect
```bash
# Verify .env settings
cat .env | grep DB_

# Test connection manually
psql -U rotational_user -h 127.0.0.1 -d rotational_app

# Check PHP extension
php -m | grep pgsql

# Clear Laravel config cache
php artisan config:clear
```

## Security Best Practices

1. **Strong Password**: Use a strong, unique password for database user
2. **Limit Connections**: Configure `pg_hba.conf` to only allow local connections
3. **Regular Backups**: Automate daily backups
4. **Update Regularly**: Keep PostgreSQL updated
5. **Monitor Logs**: Regularly check PostgreSQL logs for suspicious activity

## PostgreSQL Advantages for Your App

1. **Better JSON Support**: JSONB type for efficient JSON storage
2. **Advanced Indexing**: GIN, GiST indexes for complex queries
3. **Full-Text Search**: Built-in full-text search capabilities
4. **ACID Compliance**: Strong data integrity guarantees
5. **Concurrent Performance**: Better handling of concurrent writes
6. **Open Source**: No licensing costs, even for commercial use

## Migration from MySQL (If Needed)

If you ever need to migrate from MySQL to PostgreSQL:

```bash
# Install pgloader
sudo apt install pgloader -y

# Create migration script
cat > migrate.load << 'EOF'
LOAD DATABASE
     FROM mysql://mysql_user:password@localhost/mysql_db
     INTO postgresql://pg_user:password@localhost/pg_db
     WITH include drop, create tables, create indexes, reset sequences
     SET maintenance_work_mem to '128MB', work_mem to '12MB'
     CAST type datetime to timestamptz drop default drop not null using zero-dates-to-null;
EOF

# Run migration
pgloader migrate.load
```

## Summary

PostgreSQL is now configured and ready for your Rotational Contribution App:

- ✅ PostgreSQL 14+ installed
- ✅ PHP pgsql extension installed
- ✅ Database and user created
- ✅ Laravel configured
- ✅ Migrations ready to run
- ✅ Backups configured
- ✅ Performance optimized

Your app will work seamlessly with PostgreSQL!
