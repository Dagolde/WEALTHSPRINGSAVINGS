# MySQL Deployment - Quick Summary

## What Changed?

Your app now uses **MySQL** (CloudPanel's default database) instead of PostgreSQL. This means:

✅ No need to install PostgreSQL  
✅ Won't affect other websites on your server  
✅ Easier setup using CloudPanel's interface  
✅ Laravel works perfectly with MySQL  

---

## Quick Deployment Steps

### 1. Create MySQL Database in CloudPanel

1. Log into CloudPanel: `https://your-server-ip:8443`
2. Go to **Databases** → **Add Database**
3. Fill in:
   - Database Type: **MySQL** (default)
   - Database Name: `rotational_app`
   - Database User: `rotational_user`
   - Password: Generate and save it
4. Click **Create**

### 2. Create Site in CloudPanel

1. Go to **Sites** → **Add Site**
2. Fill in:
   - Domain: `yourdomain.com`
   - Site Type: **PHP**
   - PHP Version: **8.2**
   - Vhost Template: **Laravel**
3. Click **Create**
4. Note your site user (e.g., `clpuser-abc123`)

### 3. Deploy Code to Server

SSH into your server:

```bash
ssh your-user@your-server-ip

# Navigate to site directory
cd /home/{site-user}/htdocs/{yourdomain.com}

# Clone repository
rm -rf *
git clone https://github.com/Dagolde/WEALTHSPRINGSAVINGS.git .

# Install dependencies
composer install --optimize-autoloader --no-dev

# Configure environment
cp .env.cloudpanel .env
nano .env
```

### 4. Update .env File

Key settings to update:

```env
APP_URL=https://yourdomain.com

# MySQL Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rotational_app
DB_USERNAME=rotational_user
DB_PASSWORD=your_actual_password_from_cloudpanel

# Paystack
PAYSTACK_PUBLIC_KEY=pk_live_your_key
PAYSTACK_SECRET_KEY=sk_live_your_key

# Admin
ADMIN_EMAIL=admin@yourdomain.com
ADMIN_PASSWORD=your_secure_password
```

Save and exit: `Ctrl+X`, `Y`, `Enter`

### 5. Run Setup Commands

```bash
# Generate app key
php artisan key:generate

# Run migrations
php artisan migrate --force

# Seed admin user
php artisan db:seed --class=AdminUserSeeder --force

# Set permissions
chmod -R 755 storage bootstrap/cache
chown -R {site-user}:clp storage bootstrap/cache

# Create storage link
php artisan storage:link

# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 6. Setup Queue Workers

```bash
sudo nano /etc/supervisor/conf.d/rotational-worker.conf
```

Add (replace `{site-user}` and `{yourdomain.com}`):

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

Start workers:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start rotational-worker:*
```

### 7. Setup Cron Job

```bash
sudo crontab -e -u {site-user}
```

Add:

```cron
* * * * * cd /home/{site-user}/htdocs/{yourdomain.com} && php artisan schedule:run >> /dev/null 2>&1
```

### 8. Enable SSL

1. In CloudPanel, go to your site
2. Click **SSL/TLS** tab
3. Click **Let's Encrypt** → **Install**
4. Wait for certificate (1-2 minutes)

### 9. Deploy Admin Dashboard

```bash
mkdir -p public/admin
cp -r admin-dashboard/* public/admin/
nano public/admin/app.js
```

Update:
```javascript
const API_BASE_URL = 'https://yourdomain.com/api/v1';
```

### 10. Test Everything

```bash
# Test API
curl https://yourdomain.com/api/v1/health

# Test database
php artisan tinker
>>> DB::connection()->getPdo();
>>> exit

# Check logs
tail -f storage/logs/laravel.log
```

Visit:
- API: `https://yourdomain.com/api/v1`
- Admin: `https://yourdomain.com/admin`

---

## GitHub Actions Setup

For automatic deployment on every push:

1. Go to: https://github.com/Dagolde/WEALTHSPRINGSAVINGS/settings/secrets/actions

2. Add these 4 secrets:
   - **SERVER_HOST**: Your server IP
   - **SERVER_USER**: Your site user (e.g., `clpuser-abc123`)
   - **SSH_PRIVATE_KEY**: Your SSH private key
   - **DEPLOY_PATH**: `/home/{site-user}/htdocs/{yourdomain.com}`

3. Test deployment:
   ```bash
   git commit --allow-empty -m "Test deployment"
   git push origin main
   ```

4. Watch at: https://github.com/Dagolde/WEALTHSPRINGSAVINGS/actions

---

## What's Different from PostgreSQL?

| Item | PostgreSQL | MySQL |
|------|-----------|-------|
| DB_CONNECTION | `pgsql` | `mysql` |
| DB_PORT | `5432` | `3306` |
| Installation | Manual install required | Already in CloudPanel |
| PHP Extension | `php8.2-pgsql` | `php8.2-mysql` (pre-installed) |

Everything else is exactly the same!

---

## Complete Guides

- **CLOUDPANEL_MYSQL_DEPLOYMENT.md** - Full MySQL deployment guide
- **GITHUB_SECRETS_SETUP_GUIDE.md** - GitHub Actions setup
- **CLOUDPANEL_DEPLOYMENT_GUIDE.md** - General deployment guide

---

## Need Help?

### Common Issues

**"Connection refused"**
```bash
sudo systemctl status mysql
sudo systemctl restart mysql
```

**"Access denied"**
- Check password in `.env` matches CloudPanel database password
- Run: `php artisan config:clear`

**"Table not found"**
```bash
php artisan migrate --force
```

**"Queue not working"**
```bash
sudo supervisorctl restart rotational-worker:*
```

---

## Summary

✅ MySQL is now the default database  
✅ No PostgreSQL installation needed  
✅ Won't affect other websites  
✅ Easier CloudPanel setup  
✅ All guides updated  
✅ GitHub Actions ready  

Your app is ready to deploy with MySQL!

---

**Created**: March 13, 2026  
**Database**: MySQL (CloudPanel Default)  
**Status**: Ready to Deploy
