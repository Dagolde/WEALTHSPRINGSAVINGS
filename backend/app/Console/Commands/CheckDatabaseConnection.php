<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class CheckDatabaseConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check database and Redis connectivity';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking database infrastructure...');
        $this->newLine();

        // Check PostgreSQL connection
        $this->checkPostgreSQL();
        $this->newLine();

        // Check Redis connection
        $this->checkRedis();
        $this->newLine();

        $this->info('Database infrastructure check complete!');
        return Command::SUCCESS;
    }

    /**
     * Check PostgreSQL database connection
     */
    private function checkPostgreSQL(): void
    {
        $this->info('PostgreSQL Connection:');
        
        try {
            // Test write connection
            DB::connection()->getPdo();
            $writeHost = config('database.connections.pgsql.write.host')[0] ?? config('database.connections.pgsql.host');
            $this->line("  ✓ Write connection successful: {$writeHost}");

            // Test read connection (if configured)
            $readHost = config('database.connections.pgsql.read.host')[0] ?? null;
            if ($readHost && $readHost !== $writeHost) {
                DB::connection()->getReadPdo();
                $this->line("  ✓ Read connection successful: {$readHost}");
            }

            // Get database info
            $dbName = DB::connection()->getDatabaseName();
            $this->line("  ✓ Database: {$dbName}");

            // Test query
            $result = DB::select('SELECT version()');
            $version = $result[0]->version ?? 'Unknown';
            $this->line("  ✓ PostgreSQL version: " . substr($version, 0, 50) . "...");

            // Check active connections
            $connections = DB::select("SELECT count(*) as count FROM pg_stat_activity WHERE datname = ?", [$dbName]);
            $count = $connections[0]->count ?? 0;
            $this->line("  ✓ Active connections: {$count}");

        } catch (\Exception $e) {
            $this->error("  ✗ PostgreSQL connection failed: " . $e->getMessage());
        }
    }

    /**
     * Check Redis connection
     */
    private function checkRedis(): void
    {
        $this->info('Redis Connection:');
        
        try {
            // Test default connection
            Redis::connection('default')->ping();
            $host = config('redis.default.host');
            $db = config('redis.default.database');
            $this->line("  ✓ Default connection successful: {$host} (DB: {$db})");

            // Test cache connection
            Redis::connection('cache')->ping();
            $cacheDb = config('redis.cache.database');
            $this->line("  ✓ Cache connection successful: {$host} (DB: {$cacheDb})");

            // Test queue connection
            Redis::connection('queue')->ping();
            $queueDb = config('redis.queue.database');
            $this->line("  ✓ Queue connection successful: {$host} (DB: {$queueDb})");

            // Get Redis info
            $info = Redis::connection('default')->info();
            $version = $info['redis_version'] ?? 'Unknown';
            $this->line("  ✓ Redis version: {$version}");

            $memory = $info['used_memory_human'] ?? 'Unknown';
            $this->line("  ✓ Memory usage: {$memory}");

        } catch (\Exception $e) {
            $this->error("  ✗ Redis connection failed: " . $e->getMessage());
        }
    }
}
