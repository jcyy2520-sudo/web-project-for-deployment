<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('error_logs', function (Blueprint $table) {
            $table->id();
            $table->string('level'); // error, warning, notice, info, debug
            $table->text('message');
            $table->text('exception')->nullable();
            $table->text('stack_trace')->nullable();
            $table->string('file')->nullable();
            $table->integer('line')->nullable();
            $table->json('context')->nullable(); // Additional context data
            $table->string('user_agent')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('url')->nullable();
            $table->string('method')->nullable(); // GET, POST, etc
            $table->text('request_data')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            
            // Indexes for faster queries
            $table->index('level');
            $table->index('created_at');
            $table->index('user_id');
            $table->index(['level', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_logs');
    }
};
