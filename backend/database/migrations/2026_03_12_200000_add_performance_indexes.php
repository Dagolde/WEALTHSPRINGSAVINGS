<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Skip - indexes already created in 2026_03_12_000002_add_indexes_for_performance
        // This migration is kept for reference but does nothing
    }

    private function indexExists(string $table, string $index): bool
    {
        return true; // Always return true to skip index creation
    }

    public function down(): void
    {
        // Drop indexes in reverse order
    }
};
