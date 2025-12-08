<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Alter the enum to include 'interpreter' and 'guest'
        DB::statement("ALTER TABLE chat_messages MODIFY COLUMN source ENUM('user', 'huggingface', 'interpreter', 'guest') DEFAULT 'user'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum values
        DB::statement("ALTER TABLE chat_messages MODIFY COLUMN source ENUM('user', 'huggingface') DEFAULT 'user'");
    }
};
