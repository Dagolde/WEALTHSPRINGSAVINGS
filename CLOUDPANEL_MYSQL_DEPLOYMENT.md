# CloudPanel MySQL Deployment Guide

## Overview

This guide shows you how to deploy the Rotational Contribution App using **MySQL** (CloudPanel's default database) instead of PostgreSQL. This way, you don't need to install PostgreSQL and won't affect other websites on your server.

---

## Why MySQL Instead of PostgreSQL?

✅ MySQL is CloudPanel's default database server  
✅ No additional installation required  
✅ Won't affect other websites on your server  
✅ Laravel works perfectly with MySQL  
✅ Easier to manage in CloudPanel interface  

---

## Part 1: Create MySQL Database in CloudPanel

### Step 1: Log into CloudPanel

Go to: `https://your-server-ip:8443`

### Step 2: Create Database

1. Click **Databases** in the left menu
2. Click **Add Database** button
3. Fill in the form:
   - **Database Type**: MySQL (should be selected by default)
   - **Database Name**: `rotational_app`
   - **Database User**: `rotational_user`
   - **Password**: Click "Generate" for a strong password
   - **Copy and save the password** - you'll need it for `.env` file

4. Click **Create**

### Step 3: Note Your Database Credentials

Write down these values:
- Database Name: `rotational_app`
- Database User: `rotational_user`  
- Database Password: (the one you generated)
- Database Host: `127.0.0.1`
- Database Port: `3306`

---

## Part 2: Deploy Application to CloudPanel

### Step 1: Create Site in CloudPanel

1. Go to **Sites** → **Add Site**
2. Configure:
   - **Domain Name**: `yourdomain.com`
   - **Site Type**: PHP
   - **PHP Version**: 8.2
   - **Vhost Template**: Laravel
   - **Site User**: (CloudPanel will create one, note it down)

3. Click **Create**

### Step 2: SSH into Your Server

```bash
ssh your-user@your-server-ip
```

### Step 3: Navigate to Site Directory

```bash
# Replace {site-user} and {yourdomain.com} with your actual values
cd /home/{site-user}/htdocs/{yourdomain.com}

# Example:
# cd /home/clpuser-abc123/htdocs/wealthspring.com
```

### Step 4: Clone Your Repository

```bash
# Remove default files
rm -rf *

# Clone your repository
git clone https://github.com/Dagolde/WEALTHSPRINGSAVINGS.git .

# Verify files are there
ls -la
```

### Step 5: Install Composer Dependencies

```bash
composer install --optimize-autoloader --no-dev
```

### Step 6: Configure Environment File

```bash
# Copy the CloudPanel environment template
cp .env.cloudpanel .env

# Edit the .env file
nano .env
```

Update these values in `.env`:

```env
APP_NAME="WealthSpring Savings"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://yourdomain.com

# Database Configuration - MySQL
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rotational_app
DB_USERNAME=rotational_user
DB_PASSWORD=your_actual_database_password_from_cloudpanel

# Cache & Session
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Paystack (Get from https://dashboard.paystack.com)
PAYSTACK_PUBLIC_KEY=pk_live_your_public_key
PAYSTACK_SECRET_KEY=sk_live_your_secret_key
PAYSTACK_PAYMENT_URL=https://api.paystack.co
PAYSTACK_MERCHANT_EMAIL=merchant@yourdomain.com

# Admin Credentials
ADMIN_EMAIL=admin@yourdomain.com
ADMIN_PASSWORD=your_secure_admin_password

# Mail Configuration (Update with your SMTP provider)
MAIL_MAILER=smtp
MAIL_HOST=smtp.yourdomain.com
MAIL_PORT=587
MAIL_USERNAME=noreply@yourdomain.com
MAIL_PASSWORD=your_email_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="WealthSpring Savings"
```

Save and exit: `Ctrl+X`, then `Y`, then `Enter`

### Step 7: Generate Application Key

```bash
php artisan key:generate
```

### Step 8: Run Database Migrations

```bash
# Run migrations
php artisan migrate --force

# Seed admin user
php artisan db:seed --class=AdminUserSeeder --force
```

### Step 9: Set Permissions

```bash
# Set correct permissions
chmod -R 755 storage bootstrap/cache
chown -R {site-user}:clp storage bootstrap/cache

# Example:
# chown -R clpuser-abc123:clp storage bootstrap/cache
```

### Step 10: Create Storage Link

```bash
php artisan storage:link
```

### Step 11: Cache Configuration

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## Part 3: Install Redis (Required for Caching & Queues)

### Check if Redis is Already Installed

```bash
redis-cli ping
```

If it returns `PONG`, Redis is already installed. Skip to Part 4.

### Install Redis (if not installed)

```bash
sudo apt update
sudo apt install redis-server -y
sudo systemctl enable redis-server
sudo systemctl start redis-server

# Test Redis
redis-cli ping
# Should return: PONG
```

---

## Part 4: Setup Queue Workers with Supervisor

### Step 1: Create Supervisor Configuration

```bash
sudo nano /etc/supervisor/conf.d/rotational-worker.conf
```

Add this configuration (replace `{site-user}` and `{yourdomain.com}`):

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

Save and exit: `Ctrl+X`, then `Y`, then `Enter`

### Step 2: Start Supervisor

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start rotational-worker:*

# Check status
sudo supervisorctl status rotational-worker:*
```

---

## Part 5: Setup Laravel Scheduler (Cron Job)

### Add Cron Job

```bash
# Edit crontab for your site user
sudo crontab -e -u {site-user}

# Example:
# sudo crontab -e -u clpuser-abc123
```

Add this line at the end:

```cron
* * * * * cd /home/{site-user}/htdocs/{yourdomain.com} && php artisan schedule:run >> /dev/null 2>&1
```

Save and exit.

---

## Part 6: Configure SSL Certificate

### Enable SSL in CloudPanel

1. Go to your site in CloudPanel
2. Click **SSL/TLS** tab
3. Click **Let's Encrypt**
4. Click **Install** button
5. Wait for certificate to be issued (usually 1-2 minutes)

### Update .env for HTTPS

```bash
nano .env
```

Update:
```env
APP_URL=https://yourdomain.com
```

Save and exit.

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

Find and update:
```javascript
const API_BASE_URL = 'https://yourdomain.com/api/v1';
```

Save and exit.

Access at: `https://yourdomain.com/admin`

---

## Part 8: Verify Deployment

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

### Test Database Connection

```bash
php artisan tinker
```

Then type:
```php
DB::connection()->getPdo();
exit
```

If no errors, database is connected!

### Check Logs

```bash
# Laravel logs
tail -f storage/logs/laravel.log

# Nginx logs
sudo tail -f /var/log/nginx/{yourdomain.com}_error.log

# Queue worker logs
tail -f storage/logs/worker.log
```

---

## Part 9: Test Admin Dashboard

1. Visit: `https://yourdomain.com/admin`
2. Login with credentials from `.env`:
   - Email: `admin@yourdomain.com`
   - Password: (the one you set in `.env`)
3. Verify all features work

---

## Part 10: Update Mobile App Configuration

### Update API URL in Mobile App

Edit `mobile/lib/core/config/app_config.dart`:

```dart
class AppConfig {
  static const String apiBaseUrl = 'https://yourdomain.com/api/v1';
  static const String appName = 'WealthSpring Savings';
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

## Part 11: GitHub Actions Deployment (Automatic Updates)

Once your site is deployed, configure GitHub Secrets for automatic deployment:

### Required GitHub Secrets

Go to: https://github.com/Dagolde/WEALTHSPRINGSAVINGS/settings/secrets/actions

Add these 4 secrets:

1. **SERVER_HOST**: Your server IP or domain
2. **SERVER_USER**: Your CloudPanel site user (e.g., `clpuser-abc123`)
3. **SSH_PRIVATE_KEY**: Your SSH private key (see `GITHUB_SECRETS_SETUP_GUIDE.md`)
4. **DEPLOY_PATH**: Full path to site (e.g., `/home/clpuser-abc123/htdocs/yourdomain.com`)

### Test Automatic Deployment

```bash
# Make a small change
git commit --allow-empty -m "Test automatic deployment"
git push origin main

# Watch deployment at:
# https://github.com/Dagolde/WEALTHSPRINGSAVINGS/actions
```

---

## MySQL vs PostgreSQL - What Changed?

| Configuration | PostgreSQL | MySQL |
|--------------|------------|-------|
| DB_CONNECTION | `pgsql` | `mysql` |
| DB_PORT | `5432` | `3306` |
| PHP Extension | `php8.2-pgsql` | `php8.2-mysql` (already installed) |
| Installation | Requires manual install | Already available in CloudPanel |

Everything else remains the same!

---

## Troubleshooting

### Error: "SQLSTATE[HY000] [2002] Connection refused"

**Cause**: MySQL not running or wrong credentials

**Fix**:
```bash
# Check MySQL status
sudo systemctl status mysql

# Restart MySQL
sudo systemctl restart mysql

# Verify credentials in .env match CloudPanel database
```

### Error: "Access denied for user"

**Cause**: Wrong database password

**Fix**:
1. Go to CloudPanel → Databases
2. Find your database
3. Reset password if needed
4. Update `.env` with correct password
5. Run: `php artisan config:clear`

### Error: "Base table or view not found"

**Cause**: Migrations not run

**Fix**:
```bash
php artisan migrate --force
```

### Error: "Queue not processing"

**Cause**: Supervisor not running

**Fix**:
```bash
sudo supervisorctl status rotational-worker:*
sudo supervisorctl restart rotational-worker:*
```

---

## Quick Reference Commands

### Deploy Updates

```bash
cd /home/{site-user}/htdocs/{yourdomain.com}
git pull origin main
composer install --optimize-autoloader --no-dev
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo supervisorctl restart rotational-worker:*
```

### Check Status

```bash
# Database connection
php artisan tinker
>>> DB::connection()->getPdo();
>>> exit

# Queue workers
sudo supervisorctl status rotational-worker:*

# Scheduler
php artisan schedule:list

# Redis
redis-cli ping
```

### View Logs

```bash
# Application logs
tail -f storage/logs/laravel.log

# Nginx error logs
sudo tail -f /var/log/nginx/{yourdomain.com}_error.log

# Worker logs
tail -f storage/logs/worker.log
```

### Clear Cache

```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

---

## Summary

✅ MySQL database created in CloudPanel  
✅ Application deployed to server  
✅ Environment configured for MySQL  
✅ Migrations run successfully  
✅ Queue workers configured with Supervisor  
✅ Laravel scheduler configured with cron  
✅ SSL certificate installed  
✅ Admin dashboard deployed  
✅ GitHub Actions configured for automatic deployment  

Your WealthSpring Savings app is now live at `https://yourdomain.com`!

---

**Created**: March 13, 2026  
**Database**: MySQL (CloudPanel Default)  
**Status**: Production Ready
