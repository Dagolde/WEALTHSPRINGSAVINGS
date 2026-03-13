<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->enum('payment_method', ['wallet', 'card', 'bank_transfer']);
            $table->string('payment_reference', 255)->unique();
            $table->enum('payment_status', ['pending', 'successful', 'failed'])->default('pending');
            $table->date('contribution_date');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            
            // Unique constraints
            $table->unique(['group_id', 'user_id', 'contribution_date'], 'unique_daily_contribution');
            
            // Indexes for performance
            $table->index('payment_reference');
            $table->index('contribution_date');
            $table->index(['group_id', 'contribution_date']);
            $table->index('payment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contributions');
    }
};
