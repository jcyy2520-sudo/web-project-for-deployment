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
        Schema::table('messages', function (Blueprint $table) {
            // Add subject column if it doesn't exist
            if (!Schema::hasColumn('messages', 'subject')) {
                $table->string('subject')->nullable()->after('message');
            }
            
            // Add type column if it doesn't exist
            if (!Schema::hasColumn('messages', 'type')) {
                $table->string('type')->default('general')->after('subject');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'subject')) {
                $table->dropColumn('subject');
            }
            if (Schema::hasColumn('messages', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};
