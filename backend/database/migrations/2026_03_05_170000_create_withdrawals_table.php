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
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('bank_account_id')->constrained('bank_accounts');
            $table->decimal('amount', 15, 2);
            $table->enum('status', ['pending', 'approved', 'processing', 'successful', 'rejected', 'failed'])->default('pending');
            $table->enum('admin_approval_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('payment_reference', 255)->unique()->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index('status');
            $table->index('admin_approval_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
