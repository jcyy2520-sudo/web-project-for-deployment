<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('database_backups', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('path'); // Full path to backup file
            $table->bigInteger('size'); // File size in bytes
            $table->string('database_name');
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->string('backup_type')->default('automatic'); // automatic, manual
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('created_by')->nullable(); // User who triggered backup
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_restored_at')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
            
            $table->index('status');
            $table->index('backup_type');
            $table->index('created_at');
            $table->index('completed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('database_backups');
    }
};
