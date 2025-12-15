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
        Schema::create('two_factor_auth', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('secret')->nullable(); // Encrypted TOTP secret
            $table->json('recovery_codes')->nullable(); // Encrypted recovery codes
            $table->boolean('enabled')->default(false);
            $table->timestamp('enabled_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('preferred_method')->default('totp'); // totp, sms, email
            $table->string('phone_number')->nullable(); // For SMS verification
            $table->integer('failed_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamps();

            $table->unique('user_id');
            $table->index('enabled');
        });

        // Track 2FA verification attempts for security monitoring
        Schema::create('two_factor_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('method'); // totp, recovery_code, sms, email
            $table->boolean('successful')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['ip_address', 'created_at']);
        });

        // Add 2FA columns to users table
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('two_factor_enabled')->default(false)->after('remember_token');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_enabled', 'two_factor_confirmed_at']);
        });

        Schema::dropIfExists('two_factor_attempts');
        Schema::dropIfExists('two_factor_auth');
    }
};
