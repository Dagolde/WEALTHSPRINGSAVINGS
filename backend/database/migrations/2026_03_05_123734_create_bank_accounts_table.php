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
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('account_name');
            $table->string('account_number', 20);
            $table->string('bank_name');
            $table->string('bank_code', 10);
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            
            // Unique constraint to prevent duplicate bank accounts for the same user
            $table->unique(['user_id', 'account_number', 'bank_code'], 'unique_user_bank_account');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
