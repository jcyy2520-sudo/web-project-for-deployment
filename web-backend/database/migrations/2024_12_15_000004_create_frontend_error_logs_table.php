<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frontend_error_logs', function (Blueprint $table) {
            $table->id();
            $table->string('message');
            $table->string('error_type'); // TypeError, ReferenceError, SyntaxError, etc
            $table->text('stack_trace')->nullable();
            $table->string('file')->nullable();
            $table->integer('line')->nullable();
            $table->integer('column')->nullable();
            $table->string('url');
            $table->string('user_agent')->nullable();
            $table->string('ip_address')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->json('context')->nullable(); // Component name, route, etc
            $table->json('breadcrumbs')->nullable(); // User actions leading to error
            $table->text('device_info')->nullable(); // Browser, OS, screen size
            $table->string('severity')->default('error'); // error, warning, info
            $table->boolean('is_reported')->default(false);
            $table->integer('occurrence_count')->default(1);
            $table->timestamps();
            
            $table->index('error_type');
            $table->index('severity');
            $table->index('user_id');
            $table->index('created_at');
            $table->index(['error_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frontend_error_logs');
    }
};
