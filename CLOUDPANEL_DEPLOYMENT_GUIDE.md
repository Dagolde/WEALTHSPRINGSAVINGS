# CloudPanel Deployment Guide - Rotational Contribution App

## Overview
This guide will help you deploy the Rotational Contribution App (Laravel backend + Admin Dashboard) to CloudPanel on your domain.

## Prerequisites
- CloudPanel installed on your server
- Domain name pointed to your server
- SSH access to your server
- Git installed on server

## Architecture
- **Backend**: Laravel 10 (PHP 8.2+)
- **Admin Dashboard**: Static HTML/CSS/JS
- **Database**: MySQL 8.0+ (CloudPanel default) or PostgreSQL 14+
- **Cache**: Redis
- **Queue**: Laravel Queue Worker
- **Scheduler**: Laravel Scheduler (Cron)

---

## Part 1: Server Requirements

### Minimum Server Specs
- **CPU**: 2 cores
- **RAM**: 4GB
- **Storage**: 20GB SSD
- **OS**: Ubuntu 22.04 LTS (recommended for CloudPanel)

### Required PHP Extensions
CloudPanel usually includes these, but verify:
```
php8.2-cli
php8.2-fpm
php8.2-mysql (for MySQL) or php8.2-pgsql (for PostgreSQL)
php8.2-mbstring
php8.2-xml
php8.2-bcmath
php8.2-curl
php8.2-zip
php8.2-gd
php8.2-redis
php8.2-intl
```

**Note**: This app works with both MySQL and PostgreSQL. MySQL is CloudPanel's default and recommended for easier setup.

---

## Part 2: CloudPanel Setup

### Step 1: Create Database
1. Log into CloudPanel
2. Go to **Databases** → **Add Database**
3. Select database type:
   - **MySQL** (Recommended - CloudPanel default, no additional setup)
   - **PostgreSQL** (Advanced - requires separate installation)
4. Create database:
   - **Database Name**: `rotational_app`
   - **Database User**: `rotational_user`
   - **Password**: Generate strong password (save it!)

**For MySQL users**: See `CLOUDPANEL_MYSQL_DEPLOYMENT.md` for complete MySQL-specific guide.  
**For PostgreSQL users**: See `CLOUDPANEL_POSTGRESQL_SETUP.md` for PostgreSQL installation.

### Step 2: Create Site
1. Go to **Sites** → **Add Site**
2. Configure:
   - **Domain Name**: `yourdomain.com`
   - **Site Type**: PHP
   - **PHP Version**: 8.2
   - **Vhost Template**: Laravel
   - **Site User**: Create new user or use existing

### Step 3: Install Redis
SSH into your server:
```bash
sudo apt update
sudo apt install redis-server -y
sudo systemctl enable redis-server
sudo systemctl start redis-server
```

---

## Part 3: Deploy Backend

### Step 1: Upload Code via Git
SSH into your server as the site user:

```bash
# Navigate to site directory
cd /home/{site-user}/htdocs/{yourdomain.com}

# Remove default files
rm -rf *

# Clone your repository (or upload via SFTP)
git clone https://github.com/yourusername/rotational-contribution-app.git .

# Or if uploading manually, upload the 'backend' folder contents to this directory
```

### Step 2: Install Dependencies
```bash
cd /home/{site-user}/htdocs/{yourdomain.com}

# Install Composer dependencies
composer install --optimize-autoloader --no-dev

# Set permissions
chmod -R 755 storage bootstrap/cache
chown -R {site-user}:clp storage bootstrap/cache
```

### Step 3: Configure Environment
```bash
# Copy environment file
cp .env.production .env

# Edit .env file
nano .env
```

Update these values in `.env`:
```env
APP_NAME="Rotational Contribution App"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://yourdomain.com

# For MySQL (Recommended - CloudPanel default)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rotational_app
DB_USERNAME=rotational_user
DB_PASSWORD=your_database_password_here

# For PostgreSQL (Alternative)
# DB_CONNECTION=pgsql
# DB_HOST=127.0.0.1
# DB_PORT=5432
# DB_DATABASE=rotational_app
# DB_USERNAME=rotational_user
# DB_PASSWORD=your_database_password_here

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Paystack Configuration
PAYSTACK_PUBLIC_KEY=your_paystack_public_key
PAYSTACK_SECRET_KEY=your_paystack_secret_key
PAYSTACK_PAYMENT_URL=https://api.paystack.co

# Admin Credentials
ADMIN_EMAIL=admin@yourdomain.com
ADMIN_PASSWORD=change_this_secure_password

# Mail Configuration (use your SMTP provider)
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"
```

### Step 4: Generate Application Key
```bash
php artisan key:generate
```

### Step 5: Run Migrations
```bash
# Run migrations
php artisan migrate --force

# Seed admin user
php artisan db:seed --class=AdminUserSeeder --force
```

### Step 6: Optimize Application
```bash
# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Create storage link
php artisan storage:link
```

---

## Part 4: Configure Web Server (Nginx)

CloudPanel should auto-configure Nginx for Laravel, but verify:

### Check Nginx Configuration
```bash
sudo nano /etc/nginx/sites-enabled/{yourdomain.com}.conf
```

Ensure it has:
```nginx
server {
    listen 80;
    listen [::]:80;
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    
    server_name yourdomain.com www.yourdomain.com;
    
    root /home/{site-user}/htdocs/{yourdomain.com}/public;
    index index.php index.html;

    # SSL Configuration (CloudPanel handles this)
    ssl_certificate /path/to/cert;
    ssl_certificate_key /path/to/key;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Laravel specific
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Increase upload size for profile pictures and KYC documents
    client_max_body_size 10M;
}
```

Reload Nginx:
```bash
sudo nginx -t
sudo systemctl reload nginx
```

---

## Part 5: Setup Queue Worker

### Create Supervisor Configuration
```bash
sudo nano /etc/supervisor/conf.d/rotational-worker.conf
```

Add:
```ini
[program:rotational-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /home/{site-user}/htdocs/{yourdomain.com}/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user={site-user}
numprocs=2
redirect_stderr=true
stdout_logfile=/home/{site-user}/htdocs/{yourdomain.com}/storage/logs/worker.log
stopwaitsecs=3600
```

Start supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start rotational-worker:*
```

---

## Part 6: Setup Laravel Scheduler

### Add Cron Job
```bash
sudo crontab -e -u {site-user}
```

Add this line:
```cron
* * * * * cd /home/{site-user}/htdocs/{yourdomain.com} && php artisan schedule:run >> /dev/null 2>&1
```

---

## Part 7: Deploy Admin Dashboard

### Option 1: Same Domain (Subdirectory)
```bash
# Create admin directory
mkdir -p /home/{site-user}/htdocs/{yourdomain.com}/public/admin

# Copy admin dashboard files
cp -r admin-dashboard/* /home/{site-user}/htdocs/{yourdomain.com}/public/admin/

# Update API URL in admin dashboard
nano /home/{site-user}/htdocs/{yourdomain.com}/public/admin/app.js
```

Update `API_BASE_URL` in `app.js`:
```javascript
const API_BASE_URL = 'https://yourdomain.com/api/v1';
```

Access at: `https://yourdomain.com/admin`

### Option 2: Subdomain (Recommended)
1. In CloudPanel, create new site: `admin.yourdomain.com`
2. Upload admin dashboard files to the new site's public directory
3. Update `API_BASE_URL` to point to main domain

---

## Part 8: SSL Certificate

### Enable SSL via CloudPanel
1. Go to your site in CloudPanel
2. Click **SSL/TLS**
3. Click **Let's Encrypt** → **Install**
4. CloudPanel will automatically obtain and install SSL certificate

### Force HTTPS
Update `.env`:
```env
APP_URL=https://yourdomain.com
```

---

## Part 9: Post-Deployment Checks

### Test Backend API
```bash
curl https://yourdomain.com/api/v1/health
```

Should return:
```json
{
  "status": "ok",
  "timestamp": "2026-03-13T..."
}
```

### Test Admin Dashboard
1. Visit `https://yourdomain.com/admin`
2. Login with admin credentials from `.env`
3. Verify all features work

### Check Logs
```bash
# Laravel logs
tail -f /home/{site-user}/htdocs/{yourdomain.com}/storage/logs/laravel.log

# Nginx logs
sudo tail -f /var/log/nginx/{yourdomain.com}_access.log
sudo tail -f /var/log/nginx/{yourdomain.com}_error.log

# Queue worker logs
tail -f /home/{site-user}/htdocs/{yourdomain.com}/storage/logs/worker.log
```

---

## Part 10: Mobile App Configuration

After backend is deployed, update mobile app to point to your domain:

### Update Mobile App Config
Edit `mobile/lib/core/config/app_config.dart`:
```dart
class AppConfig {
  static const String apiBaseUrl = 'https://yourdomain.com/api/v1';
  static const String appName = 'Rotational Contribution';
  static const String appVersion = '1.0.0';
}
```

### Rebuild Mobile App
```bash
cd mobile
flutter clean
flutter pub get
flutter build apk --release
```

---

## Part 11: Maintenance & Monitoring

### Regular Maintenance Tasks

#### Daily
- Monitor error logs
- Check queue worker status
- Verify scheduler is running

#### Weekly
- Review application performance
- Check disk space
- Update dependencies if needed

#### Monthly
- Database backup
- Security updates
- Performance optimization

### Backup Strategy

#### Database Backup (Daily)
```bash
# Add to crontab
0 2 * * * pg_dump -U rotational_user -h 127.0.0.1 rotational_app > /home/{site-user}/backups/db_$(date +\%Y\%m\%d).sql
```

#### File Backup (Weekly)
```bash
# Add to crontab
0 3 * * 0 tar -czf /home/{site-user}/backups/files_$(date +\%Y\%m\%d).tar.gz /home/{site-user}/htdocs/{yourdomain.com}
```

### Monitoring Commands
```bash
# Check queue status
php artisan queue:work --once

# Check scheduler
php artisan schedule:list

# Check failed jobs
php artisan queue:failed

# Restart queue workers
sudo supervisorctl restart rotational-worker:*

# Clear cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

---

## Part 12: Troubleshooting

### Common Issues

#### 500 Internal Server Error
```bash
# Check permissions
chmod -R 755 storage bootstrap/cache
chown -R {site-user}:clp storage bootstrap/cache

# Check logs
tail -f storage/logs/laravel.log

# Clear cache
php artisan cache:clear
php artisan config:clear
```

#### Queue Not Processing
```bash
# Check supervisor status
sudo supervisorctl status rotational-worker:*

# Restart workers
sudo supervisorctl restart rotational-worker:*

# Check Redis
redis-cli ping
```

#### Database Connection Error
```bash
# Test database connection
php artisan tinker
>>> DB::connection()->getPdo();

# Check credentials in .env
cat .env | grep DB_
```

#### File Upload Issues
```bash
# Check storage permissions
ls -la storage/app/public

# Recreate storage link
php artisan storage:link

# Check upload size in php.ini
php -i | grep upload_max_filesize
php -i | grep post_max_size
```

---

## Part 13: Security Checklist

- [ ] SSL certificate installed and forced HTTPS
- [ ] Strong database password set
- [ ] APP_DEBUG=false in production
- [ ] APP_KEY generated
- [ ] Admin password changed from default
- [ ] File permissions set correctly (755 for directories, 644 for files)
- [ ] .env file not publicly accessible
- [ ] Firewall configured (UFW)
- [ ] Regular backups scheduled
- [ ] Security headers configured in Nginx
- [ ] Rate limiting enabled
- [ ] CORS configured properly

---

## Part 14: Performance Optimization

### Enable OPcache
```bash
sudo nano /etc/php/8.2/fpm/php.ini
```

Add/update:
```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
```

Restart PHP-FPM:
```bash
sudo systemctl restart php8.2-fpm
```

### Redis Configuration
```bash
sudo nano /etc/redis/redis.conf
```

Update:
```conf
maxmemory 256mb
maxmemory-policy allkeys-lru
```

Restart Redis:
```bash
sudo systemctl restart redis-server
```

---

## Support & Resources

- **Laravel Documentation**: https://laravel.com/docs
- **CloudPanel Documentation**: https://www.cloudpanel.io/docs
- **Paystack API**: https://paystack.com/docs/api

---

## Quick Reference Commands

```bash
# Deploy updates
cd /home/{site-user}/htdocs/{yourdomain.com}
git pull origin main
composer install --optimize-autoloader --no-dev
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo supervisorctl restart rotational-worker:*

# Check status
php artisan queue:work --once
sudo supervisorctl status rotational-worker:*
php artisan schedule:list

# View logs
tail -f storage/logs/laravel.log
sudo tail -f /var/log/nginx/{yourdomain.com}_error.log

# Clear cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

---

## Deployment Complete! 🎉

Your Rotational Contribution App is now live at `https://yourdomain.com`

- **API**: `https://yourdomain.com/api/v1`
- **Admin Dashboard**: `https://yourdomain.com/admin`
- **Mobile App**: Update config and rebuild

**Next Steps**:
1. Test all features thoroughly
2. Configure Paystack with live keys
3. Set up email service (SMTP)
4. Deploy mobile app to Play Store/App Store
5. Monitor logs and performance
