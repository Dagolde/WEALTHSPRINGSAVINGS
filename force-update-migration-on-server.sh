#!/bin/bash

# Force Update Migration File on Server
# This script directly updates the migration file on the server with the MySQL fix

echo "=========================================="
echo "Force Update Migration on Server"
echo "=========================================="
echo ""

# The migration file content with MySQL support
read -r -d '' MIGRATION_CONTENT << 'EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add indexes to users table
        Schema::table('users', function (Blueprint $table) {
            if (!$this->indexExists('users', 'users_status_index')) {
                $table->index('status');
            }
            if (!$this->indexExists('users', 'users_role_index')) {
                $table->index('role');
            }
        });

        // Add indexes to groups table
        Schema::table('groups', function (Blueprint $table) {
            if (!$this->indexExists('groups', 'groups_status_index')) {
                $table->index('status');
            }
            if (!$this->indexExists('groups', 'groups_created_by_index')) {
                $table->index('created_by');
            }
        });

        // Add indexes to contributions table
        Schema::table('contributions', function (Blueprint $table) {
            if (!$this->indexExists('contributions', 'contributions_payment_status_index')) {
                $table->index('payment_status');
            }
        });

        // Add indexes to payouts table
        Schema::table('payouts', function (Blueprint $table) {
            if (!$this->indexExists('payouts', 'payouts_status_index')) {
                $table->index('status');
            }
        });

        // Add indexes to withdrawals table
        Schema::table('withdrawals', function (Blueprint $table) {
            if (!$this->indexExists('withdrawals', 'withdrawals_status_index')) {
                $table->index('status');
            }
            if (!$this->indexExists('withdrawals', 'withdrawals_admin_approval_status_index')) {
                $table->index('admin_approval_status');
            }
        });
    }

    /**
     * Check if an index exists.
     */
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if ($this->indexExists('users', 'users_status_index')) {
                $table->dropIndex(['status']);
            }
            if ($this->indexExists('users', 'users_role_index')) {
                $table->dropIndex(['role']);
            }
        });

        Schema::table('groups', function (Blueprint $table) {
            if ($this->indexExists('groups', 'groups_status_index')) {
                $table->dropIndex(['status']);
            }
            if ($this->indexExists('groups', 'groups_created_by_index')) {
                $table->dropIndex(['created_by']);
            }
        });

        Schema::table('contributions', function (Blueprint $table) {
            if ($this->indexExists('contributions', 'contributions_payment_status_index')) {
                $table->dropIndex(['payment_status']);
            }
        });

        Schema::table('payouts', function (Blueprint $table) {
            if ($this->indexExists('payouts', 'payouts_status_index')) {
                $table->dropIndex(['status']);
            }
        });

        Schema::table('withdrawals', function (Blueprint $table) {
            if ($this->indexExists('withdrawals', 'withdrawals_status_index')) {
                $table->dropIndex(['status']);
            }
            if ($this->indexExists('withdrawals', 'withdrawals_admin_approval_status_index')) {
                $table->dropIndex(['admin_approval_status']);
            }
        });
    }
};
EOF

echo "Creating temporary file with fixed migration..."
echo "$MIGRATION_CONTENT" > /tmp/migration_fix.php

echo ""
echo "Uploading to server..."
echo "Run this command:"
echo ""
echo "scp /tmp/migration_fix.php YOUR_USER@YOUR_SERVER:/path/to/site/database/migrations/2026_03_12_000002_add_indexes_for_performance.php"
echo ""
echo "Then SSH to server and run:"
echo "cd /path/to/site"
echo "php artisan migrate:rollback --step=1 --force"
echo "php artisan migrate --force"
