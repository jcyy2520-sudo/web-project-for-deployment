<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // 'error_count', 'error_level', 'response_time', 'disk_space', 'memory'
            $table->string('condition'); // '>', '<', '==', 'contains', etc
            $table->string('threshold'); // Value to compare against
            $table->string('channel')->default('slack'); // slack, email, sms
            $table->string('slack_webhook')->nullable(); // Slack webhook URL
            $table->string('email_recipients')->nullable(); // Comma-separated emails
            $table->boolean('enabled')->default(true);
            $table->integer('cooldown_minutes')->default(5); // Don't spam with alerts
            $table->timestamp('last_triggered_at')->nullable();
            $table->json('config')->nullable(); // Extra configuration
            $table->timestamps();
            
            $table->index('type');
            $table->index('enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_rules');
    }
};
