# Setup Verification Checklist

This document helps verify that the Laravel project has been properly initialized.

## ✅ Completed Setup Tasks

### 1. Laravel Installation
- [x] Laravel 12+ installed
- [x] PHP 8.2+ verified
- [x] Composer dependencies installed
- [x] Application key generated

### 2. Core Dependencies
- [x] Laravel Sanctum installed and published
- [x] Queue system configured (Redis)
- [x] Events system available (built-in)
- [x] Notifications system available (built-in)

### 3. Code Quality Tools
- [x] PHP CS Fixer installed
- [x] Psalm installed with Laravel plugin
- [x] Configuration files created (.php-cs-fixer.php, psalm.xml)
- [x] Composer scripts added for code quality checks

### 4. Environment Configuration
- [x] .env.development created
- [x] .env.staging created
- [x] .env.production created
- [x] Database configuration updated (PostgreSQL with read replicas)
- [x] Redis configuration updated (separate DBs for cache, queue)

### 5. Project Structure
- [x] Services directory created (app/Services)
- [x] Service interfaces directory created (app/Services/Interfaces)
- [x] Documentation files created (README.md, PROJECT_STRUCTURE.md)

## Verification Commands

Run these commands to verify the setup:

### Check Laravel Version
```bash
php artisan --version
# Expected: Laravel Framework 12.x.x
```

### Check Installed Packages
```bash
composer show laravel/sanctum
composer show friendsofphp/php-cs-fixer
composer show vimeo/psalm
```

### Verify Sanctum Routes
```bash
php artisan route:list --path=sanctum
# Expected: sanctum/csrf-cookie route
```

### Run Code Style Check
```bash
composer cs-check
# Expected: No violations (or list of issues to fix)
```

### Run Static Analysis
```bash
composer psalm
# Expected: No errors (or list of issues to fix)
```

### Test Database Connection
```bash
# After configuring .env with database credentials
php artisan migrate:status
```

### Test Redis Connection
```bash
# After configuring Redis in .env
php artisan tinker
# Then run: Redis::ping()
# Expected: "PONG"
```

## Next Steps

1. **Configure Database**
   - Set up PostgreSQL database
   - Update .env with database credentials
   - Run migrations: `php artisan migrate`

2. **Configure Redis**
   - Install and start Redis server
   - Update .env with Redis credentials
   - Test connection

3. **Set Up Payment Gateway**
   - Obtain Paystack/Flutterwave API keys
   - Add keys to .env files

4. **Configure External Services**
   - SMS Gateway (Termii/Africa's Talking)
   - Email Service (SendGrid/AWS SES)
   - Push Notifications (Firebase FCM)

5. **Start Development**
   - Begin implementing database migrations (Task 2.x)
   - Create models and relationships
   - Implement service layer
   - Build API endpoints

## Environment Variables to Configure

Before running the application, ensure these variables are set in your .env file:

### Required for Development
- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD` (if applicable)

### Required for Production
- All development variables
- `PAYSTACK_SECRET_KEY`, `PAYSTACK_PUBLIC_KEY`, `PAYSTACK_WEBHOOK_SECRET`
- `TERMII_API_KEY`, `TERMII_SENDER_ID`
- `FCM_SERVER_KEY`, `FCM_SENDER_ID`
- `MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD`
- `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_BUCKET` (if using S3)

## Troubleshooting

### Issue: "Class not found" errors
**Solution:** Run `composer dump-autoload`

### Issue: "No application encryption key has been specified"
**Solution:** Run `php artisan key:generate`

### Issue: Permission denied on storage/logs
**Solution:** 
```bash
chmod -R 775 storage bootstrap/cache
```

### Issue: Psalm errors about missing Laravel classes
**Solution:** Run `composer dump-autoload` and ensure psalm/plugin-laravel is installed

## Success Criteria

The setup is complete when:
- ✅ `php artisan --version` shows Laravel 12+
- ✅ `composer cs-check` runs without fatal errors
- ✅ `composer psalm` runs without fatal errors
- ✅ All environment files exist and are properly configured
- ✅ Project structure matches PROJECT_STRUCTURE.md
- ✅ README.md provides clear setup instructions
