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
        Schema::table('feedback_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('feedback_settings', 'profanity_list')) {
                $table->text('profanity_list')->nullable()->after('duplicate_detection_enabled');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feedback_settings', function (Blueprint $table) {
            if (Schema::hasColumn('feedback_settings', 'profanity_list')) {
                $table->dropColumn('profanity_list');
            }
        });
    }
};
