# CloudPanel Deployment Verification ✅

## Status: READY FOR DEPLOYMENT

Your Rotational Contribution App is fully prepared for CloudPanel deployment with PostgreSQL.

---

## ✅ What's Been Verified

### 1. Database Configuration
- ✅ All documentation uses PostgreSQL (not MySQL)
- ✅ `.env.cloudpanel` configured for PostgreSQL
- ✅ Connection: `pgsql` on port `5432`
- ✅ PHP extension: `php8.2-pgsql` specified
- ✅ PostgreSQL-specific setup guide created

### 2. Documentation Complete
- ✅ **CLOUDPANEL_DEPLOYMENT_GUIDE.md** - 14-part comprehensive guide
- ✅ **CLOUDPANEL_QUICK_START.md** - Step-by-step checklist
- ✅ **CLOUDPANEL_POSTGRESQL_SETUP.md** - PostgreSQL-specific instructions
- ✅ **DEPLOYMENT_READY_SUMMARY.md** - Overview and checklist

### 3. Configuration Files
- ✅ **backend/.env.cloudpanel** - Production environment template with PostgreSQL
- ✅ **deploy-to-cloudpanel.sh** - Automated deployment script
- ✅ **prepare-for-deployment.sh** - Package preparation script

### 4. Application Components
- ✅ Backend (Laravel 10) - Fully implemented
- ✅ Admin Dashboard - Complete with all features
- ✅ Mobile App (Flutter) - Ready (needs API URL update post-deployment)
- ✅ Database Migrations - All migrations ready
- ✅ Seeders - AdminUserSeeder configured
- ✅ Tests - Property-based and integration tests complete

---

## 📋 Pre-Deployment Checklist

### Server Requirements
- [ ] Ubuntu 22.04 LTS server with CloudPanel installed
- [ ] Minimum specs: 2 CPU cores, 4GB RAM, 20GB SSD
- [ ] Domain DNS pointed to server IP
- [ ] SSH access configured

### CloudPanel Setup
- [ ] PostgreSQL 14+ installed on server
- [ ] PHP 8.2 with `php8.2-pgsql` extension installed
- [ ] Redis installed and running
- [ ] Supervisor installed for queue workers

### Information to Gather
- [ ] **Domain name**: `yourdomain.com`
- [ ] **Database credentials** (from CloudPanel):
  - Database name: `rotational_app`
  - Database user: `rotational_user`
  - Database password: (save securely)
- [ ] **Paystack API keys** (live mode):
  - Public key: `pk_live_...`
  - Secret key: `sk_live_...`
- [ ] **SMTP credentials** for email:
  - Host, port, username, password
- [ ] **Admin credentials**:
  - Email: `admin@yourdomain.com`
  - Password: (choose strong password)

---

## 🚀 Deployment Steps (Quick Reference)

### Step 1: Server Preparation (10-15 min)
```bash
# Install PostgreSQL (if not installed)
sudo apt update
sudo apt install postgresql postgresql-contrib -y
sudo apt install php8.2-pgsql -y

# Install Redis
sudo apt install redis-server -y
sudo systemctl enable redis-server
sudo systemctl start redis-server

# Install Supervisor
sudo apt install supervisor -y
sudo systemctl enable supervisor
```

### Step 2: CloudPanel Configuration (5 min)
1. Log into CloudPanel
2. Create PostgreSQL database: `rotational_app`
3. Create database user: `rotational_user` with strong password
4. Create site: `yourdomain.com` with PHP 8.2 and Laravel template

### Step 3: Upload Code (5-10 min)
**Option A: Git Clone**
```bash
cd /home/{site-user}/htdocs/{yourdomain.com}
rm -rf *
git clone https://github.com/yourusername/repo.git .
```

**Option B: SFTP Upload**
- Upload `backend/` contents to `/home/{site-user}/htdocs/{yourdomain.com}/`
- Upload `admin-dashboard/` contents to `/home/{site-user}/htdocs/{yourdomain.com}/public/admin/`

### Step 4: Configure Environment (5 min)
```bash
cd /home/{site-user}/htdocs/{yourdomain.com}
cp .env.cloudpanel .env
nano .env

# Update these values:
# - APP_URL=https://yourdomain.com
# - DB_DATABASE=rotational_app
# - DB_USERNAME=rotational_user
# - DB_PASSWORD=your_database_password
# - PAYSTACK_PUBLIC_KEY=pk_live_...
# - PAYSTACK_SECRET_KEY=sk_live_...
# - ADMIN_EMAIL=admin@yourdomain.com
# - ADMIN_PASSWORD=secure_password
```

### Step 5: Install Dependencies (5 min)
```bash
composer install --optimize-autoloader --no-dev
php artisan key:generate
chmod -R 755 storage bootstrap/cache
chown -R {site-user}:clp storage bootstrap/cache
```

### Step 6: Setup Database (2 min)
```bash
# Test connection first
php artisan tinker
>>> DB::connection()->getPdo();
>>> exit

# Run migrations
php artisan migrate --force
php artisan db:seed --class=AdminUserSeeder --force
php artisan storage:link
```

### Step 7: Optimize Application (2 min)
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Step 8: Configure Queue Worker (5 min)
```bash
sudo nano /etc/supervisor/conf.d/rotational-worker.conf
```

Paste (update {site-user} and {yourdomain.com}):
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

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start rotational-worker:*
```

### Step 9: Setup Scheduler (2 min)
```bash
crontab -e

# Add this line:
* * * * * cd /home/{site-user}/htdocs/{yourdomain.com} && php artisan schedule:run >> /dev/null 2>&1
```

### Step 10: Enable SSL (5 min)
1. Go to CloudPanel → Your Site → SSL/TLS
2. Click "Let's Encrypt" → "Install"
3. Wait for certificate installation

### Step 11: Update Admin Dashboard (2 min)
```bash
nano /home/{site-user}/htdocs/{yourdomain.com}/public/admin/app.js

# Update API_BASE_URL:
const API_BASE_URL = 'https://yourdomain.com/api/v1';
```

---

## 🧪 Post-Deployment Testing

### 1. Test Backend API
```bash
# Health check
curl https://yourdomain.com/api/v1/health

# Expected: {"status":"ok","timestamp":"..."}
```

### 2. Test Admin Dashboard
1. Visit: `https://yourdomain.com/admin`
2. Login with admin credentials from `.env`
3. Verify dashboard loads
4. Test user management
5. Test KYC approval
6. Test analytics

### 3. Check Services
```bash
# Queue workers
sudo supervisorctl status rotational-worker:*

# Redis
redis-cli ping

# Database
php artisan tinker
>>> DB::table('users')->count();
>>> exit

# Logs
tail -f storage/logs/laravel.log
```

### 4. Test Key Features
- [ ] User registration
- [ ] User login
- [ ] Wallet funding
- [ ] Group creation
- [ ] Contribution recording
- [ ] KYC submission
- [ ] Admin approval workflows

---

## 📱 Mobile App Update

After backend deployment is complete:

### 1. Update API Configuration
Edit `mobile/lib/core/config/app_config.dart`:
```dart
class AppConfig {
  static const String apiBaseUrl = 'https://yourdomain.com/api/v1';
  static const String appName = 'Rotational Contribution';
  static const String appVersion = '1.0.0';
}
```

### 2. Rebuild Mobile App
```bash
cd mobile
flutter clean
flutter pub get
flutter build apk --release
```

### 3. Test Mobile App
- Install APK on physical device
- Test login
- Test wallet operations
- Test group features
- Test notifications

---

## 🔒 Security Checklist

Post-deployment security verification:

- [ ] SSL certificate installed and HTTPS enforced
- [ ] `APP_DEBUG=false` in production `.env`
- [ ] Strong database password (min 16 characters)
- [ ] Admin password changed from default
- [ ] File permissions: 755 for directories, 644 for files
- [ ] `.env` file not publicly accessible
- [ ] Firewall configured (UFW):
  ```bash
  sudo ufw allow 22/tcp
  sudo ufw allow 80/tcp
  sudo ufw allow 443/tcp
  sudo ufw enable
  ```
- [ ] Security headers in Nginx (CloudPanel handles this)
- [ ] Rate limiting enabled (Laravel default)
- [ ] Regular backups scheduled

---

## 📊 Monitoring Setup

### Daily Automated Backup
```bash
# Create backup directory
mkdir -p /home/{site-user}/backups

# Add to crontab
crontab -e

# Database backup (2 AM daily)
0 2 * * * pg_dump -U rotational_user -h 127.0.0.1 rotational_app | gzip > /home/{site-user}/backups/db_$(date +\%Y\%m\%d).sql.gz

# File backup (3 AM Sunday)
0 3 * * 0 tar -czf /home/{site-user}/backups/files_$(date +\%Y\%m\%d).tar.gz /home/{site-user}/htdocs/{yourdomain.com}

# Cleanup old backups (keep 7 days)
0 4 * * * find /home/{site-user}/backups -name "*.gz" -mtime +7 -delete
```

### Monitoring Commands
```bash
# Check application status
php artisan queue:work --once
php artisan schedule:list

# Check queue workers
sudo supervisorctl status rotational-worker:*

# View logs
tail -f storage/logs/laravel.log
sudo tail -f /var/log/nginx/{yourdomain.com}_error.log

# Check disk space
df -h

# Check database size
psql -U rotational_user -h 127.0.0.1 -d rotational_app -c "SELECT pg_size_pretty(pg_database_size('rotational_app'));"
```

---

## 🆘 Troubleshooting Guide

### Issue: 500 Internal Server Error
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

### Issue: Database Connection Error
```bash
# Verify credentials
cat .env | grep DB_

# Test connection
psql -U rotational_user -h 127.0.0.1 -d rotational_app

# Check PHP extension
php -m | grep pgsql

# Clear config cache
php artisan config:clear
```

### Issue: Queue Not Processing
```bash
# Check status
sudo supervisorctl status rotational-worker:*

# Restart workers
sudo supervisorctl restart rotational-worker:*

# Check logs
tail -f storage/logs/worker.log

# Test Redis
redis-cli ping
```

### Issue: File Upload Fails
```bash
# Check storage permissions
ls -la storage/app/public

# Recreate storage link
php artisan storage:link

# Check upload limits
php -i | grep upload_max_filesize
php -i | grep post_max_size

# Update if needed
sudo nano /etc/php/8.2/fpm/php.ini
# upload_max_filesize = 10M
# post_max_size = 10M
sudo systemctl restart php8.2-fpm
```

---

## 📈 Performance Optimization

### Enable OPcache
```bash
sudo nano /etc/php/8.2/fpm/php.ini

# Add/update:
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2

sudo systemctl restart php8.2-fpm
```

### Optimize PostgreSQL
```bash
sudo nano /etc/postgresql/14/main/postgresql.conf

# For 4GB RAM server:
shared_buffers = 1GB
effective_cache_size = 3GB
maintenance_work_mem = 256MB
checkpoint_completion_target = 0.9
wal_buffers = 16MB
default_statistics_target = 100
random_page_cost = 1.1
effective_io_concurrency = 200
work_mem = 5MB

sudo systemctl restart postgresql
```

### Optimize Redis
```bash
sudo nano /etc/redis/redis.conf

# Add/update:
maxmemory 256mb
maxmemory-policy allkeys-lru

sudo systemctl restart redis-server
```

---

## ✅ Deployment Success Criteria

Your deployment is successful when ALL of these are true:

- [ ] Backend API responds at `https://yourdomain.com/api/v1/health`
- [ ] Admin dashboard loads at `https://yourdomain.com/admin`
- [ ] Admin can login successfully
- [ ] SSL certificate is active (HTTPS works)
- [ ] Database migrations completed (check `migrations` table)
- [ ] Queue workers running (`sudo supervisorctl status`)
- [ ] Scheduler configured (`crontab -l`)
- [ ] No errors in logs (`tail -f storage/logs/laravel.log`)
- [ ] Mobile app connects successfully
- [ ] All API endpoints working (test with Postman/curl)
- [ ] File uploads working (profile pictures, KYC documents)
- [ ] Email notifications working (test with registration)
- [ ] Paystack integration working (test with wallet funding)

---

## 📞 Support Resources

### Documentation
- **Laravel**: https://laravel.com/docs/10.x
- **CloudPanel**: https://www.cloudpanel.io/docs
- **PostgreSQL**: https://www.postgresql.org/docs/
- **Paystack**: https://paystack.com/docs/api
- **Flutter**: https://flutter.dev/docs

### Your Deployment Guides
- `CLOUDPANEL_DEPLOYMENT_GUIDE.md` - Comprehensive 14-part guide
- `CLOUDPANEL_QUICK_START.md` - Quick setup checklist
- `CLOUDPANEL_POSTGRESQL_SETUP.md` - PostgreSQL-specific setup
- `DEPLOYMENT_READY_SUMMARY.md` - Overview and preparation

---

## 🎉 Ready to Deploy!

Everything is prepared and verified. You can now:

1. **Review** the deployment guides
2. **Prepare** your CloudPanel server
3. **Upload** your code
4. **Configure** environment variables
5. **Test** thoroughly
6. **Go live!**

**Estimated Total Time**: 45-60 minutes

**Good luck with your deployment! 🚀**

---

## 📝 Deployment Notes

### Current Configuration
- **Database**: PostgreSQL 14+ (configured)
- **Cache**: Redis (configured)
- **Queue**: Redis (configured)
- **Session**: Redis (configured)
- **File Storage**: Local disk (can upgrade to S3)
- **Email**: SMTP (needs configuration)
- **Payment**: Paystack (needs live keys)

### What's Working
- ✅ User authentication and authorization
- ✅ Wallet management (funding, withdrawal, balance)
- ✅ Group creation and management
- ✅ Contribution recording and tracking
- ✅ KYC submission and approval
- ✅ Admin dashboard with analytics
- ✅ Mobile app (needs API URL update)
- ✅ Payment gateway integration (Paystack)
- ✅ Notification system
- ✅ Fraud detection
- ✅ Automated payouts
- ✅ Scheduler for recurring tasks

### Post-Deployment Tasks
1. Configure Paystack with live API keys
2. Configure SMTP for email notifications
3. Test all features thoroughly
4. Update mobile app and rebuild
5. Deploy mobile app to Play Store
6. Set up monitoring and alerts
7. Configure backup strategy
8. Document admin procedures

---

**Last Updated**: March 13, 2026
**Status**: ✅ READY FOR PRODUCTION DEPLOYMENT
