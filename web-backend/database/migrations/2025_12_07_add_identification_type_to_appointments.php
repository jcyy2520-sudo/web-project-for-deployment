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
        Schema::table('appointments', function (Blueprint $table) {
            // Add identification type if it doesn't exist
            if (!Schema::hasColumn('appointments', 'identification_type')) {
                $table->string('identification_type')->nullable()->default('Not specified')->after('documents');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'identification_type')) {
                $table->dropColumn('identification_type');
            }
        });
    }
};
