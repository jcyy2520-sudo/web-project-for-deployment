<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::table('messages', function (Blueprint $table) {
            // Fix the 'type' column to allow null values or have a default
            // Using raw statement to modify existing column
            DB::statement('ALTER TABLE messages MODIFY COLUMN type VARCHAR(255) DEFAULT "general"');
        });
    }

    public function down()
    {
        Schema::table('messages', function (Blueprint $table) {
            // Revert to NOT NULL
            DB::statement('ALTER TABLE messages MODIFY COLUMN type VARCHAR(255) NOT NULL');
        });
    }
};
