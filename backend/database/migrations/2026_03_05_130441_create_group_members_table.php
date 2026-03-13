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
        Schema::create('group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->integer('position_number');
            $table->integer('payout_day');
            $table->boolean('has_received_payout')->default(false);
            $table->timestamp('payout_received_at')->nullable();
            $table->timestamp('joined_at')->useCurrent();
            $table->enum('status', ['active', 'removed', 'left'])->default('active');
            $table->timestamps();
            
            // Unique constraints
            $table->unique(['group_id', 'user_id'], 'unique_group_user');
            $table->unique(['group_id', 'position_number'], 'unique_group_position');
            
            // Indexes for performance
            $table->index('group_id');
            $table->index('user_id');
            $table->index(['group_id', 'payout_day']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_members');
    }
};
