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
            // Make purpose column nullable permanently
            $table->text('purpose')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Don't revert this - purpose should remain nullable
        Schema::table('appointments', function (Blueprint $table) {
            $table->text('purpose')->nullable()->change();
        });
    }
};
