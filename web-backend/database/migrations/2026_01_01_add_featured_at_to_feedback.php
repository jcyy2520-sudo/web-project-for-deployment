<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration adds a featured_at timestamp to track when feedback was marked as testimonial.
     * This allows newly featured testimonials to appear first in the landing page.
     */
    public function up(): void
    {
        Schema::table('feedback', function (Blueprint $table) {
            if (!Schema::hasColumn('feedback', 'featured_at')) {
                $table->timestamp('featured_at')->nullable()->after('is_testimonial');
            }
        });

        // Update existing testimonials to have a featured_at based on updated_at
        \DB::statement('UPDATE feedback SET featured_at = updated_at WHERE is_testimonial = 1 AND featured_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feedback', function (Blueprint $table) {
            if (Schema::hasColumn('feedback', 'featured_at')) {
                $table->dropColumn('featured_at');
            }
        });
    }
};
