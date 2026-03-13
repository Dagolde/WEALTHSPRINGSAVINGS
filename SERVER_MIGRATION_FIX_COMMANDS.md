# Server Migration Fix - Direct Commands

## Problem
The server still has the old PostgreSQL-specific migration file. The `git pull` didn't update it properly.

## Solution: Run These Commands on Your Server

SSH into your CloudPanel server and run these commands:

### Step 1: Navigate to your site directory
```bash
cd /path/to/your/site
```

### Step 2: Force pull the latest code
```bash
git fetch origin
git reset --hard origin/main
```

### Step 3: Remove the failed migration record
```bash
php artisan tinker
```

Then in tinker, run:
```php
DB::table('migrations')->where('migration', '2026_03_12_000002_add_indexes_for_performance')->delete();
exit
```

### Step 4: Run migrations again
```bash
php artisan migrate --force
```

## Alternative: Manual File Update

If git pull still doesn't work, manually update the file:

### Step 1: Edit the migration file
```bash
nano database/migrations/2026_03_12_000002_add_indexes_for_performance.php
```

### Step 2: Find the `indexExists` method (around line 62)

Replace this:
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

With this:
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

### Step 3: Save and exit
Press `Ctrl+X`, then `Y`, then `Enter`

### Step 4: Remove migration record and re-run
```bash
php artisan tinker
```

In tinker:
```php
DB::table('migrations')->where('migration', '2026_03_12_000002_add_indexes_for_performance')->delete();
exit
```

Then:
```bash
php artisan migrate --force
```

## Verification

After running the migration, verify the indexes were created:

```bash
php artisan tinker
```

```php
DB::select("SHOW INDEX FROM users WHERE Key_name = 'users_status_index'");
```

You should see the index listed.

## Why This Happened

The server's git repository might have local changes or conflicts preventing the pull. The `git reset --hard origin/main` command will force the server to match exactly what's in GitHub, discarding any local changes.
