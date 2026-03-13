# CloudPanel Quick Start Checklist

## Pre-Deployment Checklist

### ☐ 1. Server Setup
- [ ] CloudPanel installed on Ubuntu 22.04 server
- [ ] Domain DNS pointed to server IP
- [ ] SSH access configured
- [ ] Firewall configured (ports 80, 443, 22)

### ☐ 2. CloudPanel Configuration
- [ ] PostgreSQL database created (`rotational_app`)
- [ ] Database user created with password saved
- [ ] Site created for your domain
- [ ] PHP 8.2 selected
- [ ] Laravel vhost template selected

### ☐ 3. Server Dependencies
```bash
# Install Redis
sudo apt update
sudo apt install redis-server -y
sudo systemctl enable redis-server
sudo systemctl start redis-server

# Install Supervisor
sudo apt install supervisor -y
sudo systemctl enable supervisor
sudo systemctl start supervisor

# Verify PHP extensions
php -m | grep -E 'pgsql|redis|mbstring|xml|bcmath|curl|zip|gd'
```

---

## Deployment Steps

### Step 1: Upload Code
```bash
# SSH into server as site user
ssh {site-user}@your-server-ip

# Navigate to site directory
cd /home/{site-user}/htdocs/{yourdomain.com}

# Remove default files
rm -rf *

# Upload your code (choose one method):

# Method A: Git Clone
git clone https://github.com/yourusername/rotational-contribution-app.git .
cd backend
mv * ../
mv .* ../ 2>/dev/null || true
cd ..
rm -rf backend

# Method B: SFTP Upload
# Use FileZilla or WinSCP to upload 'backend' folder contents to:
# /home/{site-user}/htdocs/{yourdomain.com}/
```

### Step 2: Configure Environment
```bash
# Copy environment file
cp .env.cloudpanel .env

# Edit with your values
nano .env

# Update these critical values:
# - APP_URL=https://yourdomain.com
# - DB_CONNECTION=pgsql
# - DB_DATABASE=rotational_app
# - DB_USERNAME=rotational_user
# - DB_PASSWORD=your_database_password
# - PAYSTACK_PUBLIC_KEY=pk_live_...
# - PAYSTACK_SECRET_KEY=sk_live_...
# - ADMIN_EMAIL=admin@yourdomain.com
# - ADMIN_PASSWORD=secure_password_here
```

### Step 3: Install Dependencies
```bash
# Install Composer dependencies
composer install --optimize-autoloader --no-dev

# Generate application key
php artisan key:generate

# Set permissions
chmod -R 755 storage bootstrap/cache
chown -R {site-user}:clp storage bootstrap/cache
```

### Step 4: Setup Database
```bash
# Run migrations
php artisan migrate --force

# Seed admin user
php artisan db:seed --class=AdminUserSeeder --force

# Create storage link
php artisan storage:link
```

### Step 5: Optimize Application
```bash
# Cache everything
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Step 6: Configure Queue Worker
```bash
# Create supervisor config
sudo nano /etc/supervisor/conf.d/rotational-worker.conf
```

Paste this (replace {site-user} and {yourdomain.com}):
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

Start worker:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start rotational-worker:*
```

### Step 7: Setup Scheduler
```bash
# Add cron job
crontab -e

# Add this line (replace {site-user} and {yourdomain.com}):
* * * * * cd /home/{site-user}/htdocs/{yourdomain.com} && php artisan schedule:run >> /dev/null 2>&1
```

### Step 8: Enable SSL
1. Go to CloudPanel → Your Site → SSL/TLS
2. Click "Let's Encrypt" → "Install"
3. Wait for certificate installation

### Step 9: Deploy Admin Dashboard
```bash
# Create admin directory
mkdir -p /home/{site-user}/htdocs/{yourdomain.com}/public/admin

# Upload admin-dashboard files to this directory
# Or copy if already uploaded:
cp -r admin-dashboard/* /home/{site-user}/htdocs/{yourdomain.com}/public/admin/

# Update API URL
nano /home/{site-user}/htdocs/{yourdomain.com}/public/admin/app.js

# Change API_BASE_URL to:
const API_BASE_URL = 'https://yourdomain.com/api/v1';
```

---

## Testing

### Test Backend API
```bash
curl https://yourdomain.com/api/v1/health
```

Expected response:
```json
{"status":"ok","timestamp":"..."}
```

### Test Admin Login
1. Visit: `https://yourdomain.com/admin`
2. Login with credentials from `.env`:
   - Email: Value of `ADMIN_EMAIL`
   - Password: Value of `ADMIN_PASSWORD`

### Check Logs
```bash
# Laravel logs
tail -f storage/logs/laravel.log

# Nginx error logs
sudo tail -f /var/log/nginx/{yourdomain.com}_error.log

# Queue worker logs
tail -f storage/logs/worker.log
```

### Verify Services
```bash
# Check queue workers
sudo supervisorctl status rotational-worker:*

# Check Redis
redis-cli ping
# Should return: PONG

# Check database connection
php artisan tinker
>>> DB::connection()->getPdo();
# Should return PDO object
```

---

## Post-Deployment

### ☐ Security Checklist
- [ ] SSL certificate installed and working
- [ ] APP_DEBUG=false in .env
- [ ] Strong database password set
- [ ] Admin password changed from default
- [ ] File permissions correct (755/644)
- [ ] .env file not publicly accessible

### ☐ Configure Services
- [ ] Paystack keys updated (live keys)
- [ ] SMTP email configured
- [ ] Backup strategy implemented
- [ ] Monitoring setup

### ☐ Mobile App Update
```dart
// Update mobile/lib/core/config/app_config.dart
static const String apiBaseUrl = 'https://yourdomain.com/api/v1';
```

Then rebuild:
```bash
cd mobile
flutter clean
flutter pub get
flutter build apk --release
```

---

## Maintenance Commands

### Deploy Updates
```bash
cd /home/{site-user}/htdocs/{yourdomain.com}
./deploy-to-cloudpanel.sh
```

### Clear Cache
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Restart Queue Workers
```bash
sudo supervisorctl restart rotational-worker:*
```

### View Logs
```bash
tail -f storage/logs/laravel.log
```

---

## Troubleshooting

### 500 Error
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

### Queue Not Working
```bash
# Check status
sudo supervisorctl status rotational-worker:*

# Restart
sudo supervisorctl restart rotational-worker:*

# Check logs
tail -f storage/logs/worker.log
```

### Database Connection Error
```bash
# Verify credentials
cat .env | grep DB_

# Test connection
php artisan tinker
>>> DB::connection()->getPdo();
```

---

## Support

If you encounter issues:
1. Check logs: `storage/logs/laravel.log`
2. Verify .env configuration
3. Check file permissions
4. Review CloudPanel documentation
5. Check Laravel documentation

---

## Quick Reference

**Site Directory**: `/home/{site-user}/htdocs/{yourdomain.com}`
**Admin Dashboard**: `https://yourdomain.com/admin`
**API Base URL**: `https://yourdomain.com/api/v1`
**Logs**: `storage/logs/laravel.log`

**Common Commands**:
```bash
# Deploy updates
./deploy-to-cloudpanel.sh

# Clear cache
php artisan cache:clear

# Restart workers
sudo supervisorctl restart rotational-worker:*

# View logs
tail -f storage/logs/laravel.log
```

---

## Success! 🎉

Your Rotational Contribution App is now live on CloudPanel!

**Next Steps**:
1. Test all features thoroughly
2. Configure Paystack with live API keys
3. Set up email notifications
4. Deploy mobile app
5. Monitor performance and logs
