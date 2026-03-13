# L5-Swagger Composer Error Fix

## Issue

When running `composer install --no-dev` on production, you get this error:

```
In l5-swagger.php line 165:
Class "L5Swagger\Generator" not found

Script @php artisan package:discover --ansi handling the post-autoload-dump event returned with error code 1
```

## Root Cause

The `l5-swagger` package is in `require-dev` (development dependencies), but Laravel's package discovery tries to load it even when it's not installed in production.

## Solution

There are 3 ways to fix this:

---

### Option 1: Skip Package Discovery (Recommended for Production)

Run composer install with the `--no-scripts` flag, then manually run only the necessary commands:

```bash
# Install without running scripts
composer install --optimize-autoloader --no-dev --no-scripts

# Then manually run only what's needed
php artisan key:generate
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

This skips the package discovery that's causing the error.

---

### Option 2: Don't Discover L5-Swagger in Production

Update `backend/composer.json` to exclude l5-swagger from auto-discovery:

```json
"extra": {
    "laravel": {
        "dont-discover": [
            "darkaonline/l5-swagger"
        ]
    }
}
```

Then run:
```bash
composer install --optimize-autoloader --no-dev
```

---

### Option 3: Install L5-Swagger in Production (Not Recommended)

Move l5-swagger from `require-dev` to `require` in `composer.json`:

```json
"require": {
    "php": "^8.2",
    "darkaonline/l5-swagger": "^10.1",
    "laravel/framework": "^12.0",
    "laravel/sanctum": "^4.3",
    "laravel/tinker": "^2.10.1"
}
```

This installs Swagger in production (adds ~5MB to deployment).

---

## Quick Fix for Existing Deployment

If you're already on the server and facing this error:

```bash
cd /home/{site-user}/htdocs/{yourdomain.com}

# Clear composer cache
composer clear-cache

# Install without scripts
composer install --optimize-autoloader --no-dev --no-scripts

# Run necessary artisan commands
php artisan key:generate
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link

# Set permissions
chmod -R 755 storage bootstrap/cache
chown -R {site-user}:clp storage bootstrap/cache
```

---

## Recommended Approach

**Use Option 1** (skip scripts during install) because:
- ✅ No code changes needed
- ✅ Faster deployment
- ✅ Cleaner production environment
- ✅ No unnecessary packages in production

---

## Update Deployment Scripts

### Update `.github/workflows/deploy-to-cloudpanel.yml`

Change this line:
```yaml
composer install --optimize-autoloader --no-dev
```

To:
```yaml
composer install --optimize-autoloader --no-dev --no-scripts
```

### Update `deploy-to-cloudpanel.sh`

Change:
```bash
composer install --optimize-autoloader --no-dev
```

To:
```bash
composer install --optimize-autoloader --no-dev --no-scripts
```

### Update Deployment Guides

All deployment commands should use:
```bash
composer install --optimize-autoloader --no-dev --no-scripts
```

---

## Why This Happens

1. `l5-swagger` is a development tool for API documentation
2. It's in `require-dev` so it's not installed in production
3. Laravel's `package:discover` command runs after composer install
4. It tries to load all service providers, including l5-swagger's
5. Since l5-swagger isn't installed, the class doesn't exist
6. Error occurs

---

## Prevention

To prevent this in future packages:

1. Always test deployment with `--no-dev` flag locally first
2. Use `dont-discover` for dev-only packages
3. Or use `--no-scripts` flag for production installs

---

## Verification

After applying the fix, verify it works:

```bash
# Should complete without errors
composer install --optimize-autoloader --no-dev --no-scripts

# Verify autoload works
php artisan list

# Verify app works
php artisan tinker
>>> App\Models\User::count();
>>> exit
```

---

**Created**: March 13, 2026  
**Issue**: L5-Swagger class not found during composer install  
**Solution**: Use --no-scripts flag or exclude from discovery
