<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('title');
                $table->text('message');
                $table->string('type')->default('general');
                $table->json('data')->nullable();
                $table->boolean('is_read')->default(false);
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'is_read']);
                $table->index('type');
            });
        }

        if (!Schema::hasTable('announcements')) {
            Schema::create('announcements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
                $table->string('title');
                $table->text('message');
                $table->string('priority')->default('normal');
                $table->string('target_audience')->default('all_users');
                $table->boolean('is_active')->default(true);
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('notifications');
    }
};
