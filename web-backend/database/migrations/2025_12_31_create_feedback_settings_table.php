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
        Schema::create('feedback_settings', function (Blueprint $table) {
            $table->id();
            $table->integer('rate_limit')->default(2)->comment('Number of feedbacks allowed per user');
            $table->integer('cooldown_days')->default(7)->comment('Days before user can submit feedback again after reaching limit');
            $table->boolean('profanity_filter_enabled')->default(true);
            $table->boolean('duplicate_detection_enabled')->default(true);
            $table->timestamps();
        });

        // Insert default settings
        Schema::table('feedback_settings', function (Blueprint $table) {
            DB::table('feedback_settings')->insert([
                'rate_limit' => 2,
                'cooldown_days' => 7,
                'profanity_filter_enabled' => true,
                'duplicate_detection_enabled' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedback_settings');
    }
};
