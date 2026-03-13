# MySQL Migration Fix

## Problem
The migration `2026_03_12_000002_add_indexes_for_performance.php` was using PostgreSQL-specific code (`pg_indexes` table) which doesn't work with MySQL.

## Solution Applied
Updated the `indexExists()` method in the migration file to automatically detect the database driver and use the appropriate syntax:
- **MySQL**: Uses `information_schema.statistics`
- **PostgreSQL**: Uses `pg_indexes`

## Status
✅ **Fix committed and pushed to GitHub** (commit: 6a545ad)

## Next Steps

The fix is now in the repository, but the migration has already been recorded as "run" on your server, so it won't automatically run again. You have two options:

### Option 1: Rollback and Re-run (Recommended)

SSH into your CloudPanel server and run:

```bash
cd /path/to/your/site
php artisan migrate:rollback --step=1 --force
php artisan migrate --force
```

This will:
1. Rollback the last migration (removing the failed indexes)
2. Re-run it with the MySQL-compatible code

### Option 2: Manual Database Cleanup

If rollback doesn't work, manually remove the migration record:

```bash
cd /path/to/your/site

# Remove the failed migration record
php artisan tinker
>>> DB::table('migrations')->where('migration', '2026_03_12_000002_add_indexes_for_performance')->delete();
>>> exit

# Run migrations again
php artisan migrate --force
```

### Option 3: Use the Reset Script

We've created helper scripts for you:

**PowerShell (Windows):**
```powershell
# Set your server details
$env:SERVER_HOST = "your-server-ip"
$env:SERVER_USER = "your-user"
$env:DEPLOY_PATH = "/path/to/site"

# Run the script
.\reset-migration-on-server.ps1
```

**Bash (Linux/Mac):**
```bash
# Set your server details
export SERVER_HOST="your-server-ip"
export SERVER_USER="your-user"
export DEPLOY_PATH="/path/to/site"

# Run the script
bash reset-migration-on-server.sh
```

## Verification

After running the fix, verify the indexes were created:

```bash
php artisan tinker
>>> DB::select("SHOW INDEX FROM users WHERE Key_name = 'users_status_index'");
```

You should see the index listed.

## What Changed in the Code

**Before (PostgreSQL only):**
```php
private function indexExists(string $table, string $index): bool
{
    $connection = Schema::getConnection();
    $schemaName = $connection->getConfig('schema') ?: 'public';
    
    $result = DB::select(
        "SELECT 1 FROM pg_indexes WHERE schemaname = ? AND tablename = ? AND indexname = ?",
        [$schemaName, $table, $index]
    );
    
    return count($result) > 0;
}
```

**After (MySQL + PostgreSQL):**
```php
private function indexExists(string $table, string $index): bool
{
    $connection = Schema::getConnection();
    $driver = $connection->getDriverName();
    
    if ($driver === 'mysql') {
        // MySQL: Use information_schema.statistics
        $database = $connection->getDatabaseName();
        $result = DB::select(
            "SELECT 1 FROM information_schema.statistics 
             WHERE table_schema = ? AND table_name = ? AND index_name = ?",
            [$database, $table, $index]
        );
    } else {
        // PostgreSQL: Use pg_indexes
        $schemaName = $connection->getConfig('schema') ?: 'public';
        $result = DB::select(
            "SELECT 1 FROM pg_indexes WHERE schemaname = ? AND tablename = ? AND indexname = ?",
            [$schemaName, $table, $index]
        );
    }
    
    return count($result) > 0;
}
```

## Future Deployments

All future deployments will automatically use the MySQL-compatible code. This was a one-time issue that has been permanently fixed.
