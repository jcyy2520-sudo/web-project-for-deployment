<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('method'); // GET, POST, etc
            $table->string('path');
            $table->string('endpoint')->nullable(); // Named route
            $table->integer('status_code')->nullable(); // HTTP status code
            $table->integer('response_time_ms'); // milliseconds
            $table->integer('memory_usage')->nullable(); // bytes
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->boolean('is_error')->default(false);
            $table->string('error_type')->nullable(); // Exception class name
            $table->timestamps();
            
            // Indexes for faster queries
            $table->index('method');
            $table->index('path');
            $table->index('status_code');
            $table->index('created_at');
            $table->index(['path', 'method']);
            $table->index('is_error');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_metrics');
    }
};
