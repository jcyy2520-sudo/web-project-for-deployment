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
        // Add indexes to appointments table for common queries
        Schema::table('appointments', function (Blueprint $table) {
            // Check if service_id column exists before adding index
            if (Schema::hasColumn('appointments', 'service_id')) {
                $indexes = DB::select("SHOW INDEXES FROM appointments WHERE Column_name = 'service_id'");
                if (empty($indexes)) {
                    $table->index('service_id');
                }
            }
            
            $indexes = DB::select("SHOW INDEXES FROM appointments WHERE Column_name = 'created_at'");
            if (empty($indexes)) {
                $table->index('created_at');
            }
        });

        // Add indexes to users table for growth tracking
        Schema::table('users', function (Blueprint $table) {
            // Index for date range queries (user growth)
            $indexes = DB::select("SHOW INDEXES FROM users WHERE Column_name = 'created_at'");
            if (empty($indexes)) {
                $table->index('created_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndexIfExists(['service_id']);
            $table->dropIndexIfExists(['created_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndexIfExists(['created_at']);
        });
    }
};
