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
        Schema::table('feedback', function (Blueprint $table) {
            if (!Schema::hasColumn('feedback', 'feedback_type')) {
                $table->string('feedback_type')->default('general')->after('rating');
            }

            if (!Schema::hasColumn('feedback', 'is_testimonial')) {
                $table->boolean('is_testimonial')->default(false)->after('feedback_type');
            }

            if (!Schema::hasColumn('feedback', 'is_reported')) {
                $table->boolean('is_reported')->default(false)->after('is_testimonial');
            }

            if (!Schema::hasColumn('feedback', 'reported_reason')) {
                $table->string('reported_reason')->nullable()->after('is_reported');
            }

            if (!Schema::hasColumn('feedback', 'reported_explanation')) {
                $table->string('reported_explanation')->nullable()->after('reported_reason');
            }

            if (!Schema::hasColumn('feedback', 'reported_by_admin')) {
                $table->unsignedBigInteger('reported_by_admin')->nullable()->after('reported_explanation');
            }

            if (!Schema::hasColumn('feedback', 'is_blocked')) {
                $table->boolean('is_blocked')->default(false)->after('reported_by_admin');
            }

            if (!Schema::hasColumn('feedback', 'blocked_until')) {
                $table->timestamp('blocked_until')->nullable()->after('is_blocked');
            }

            if (!Schema::hasColumn('feedback', 'deleted_at')) {
                $table->softDeletes();
            }

            // Indexes
            $table->index(['is_reported']);
            $table->index(['is_blocked']);
            $table->index(['feedback_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feedback', function (Blueprint $table) {
            if (Schema::hasColumn('feedback', 'blocked_until')) {
                $table->dropColumn('blocked_until');
            }

            if (Schema::hasColumn('feedback', 'is_blocked')) {
                $table->dropColumn('is_blocked');
            }

            if (Schema::hasColumn('feedback', 'reported_by_admin')) {
                $table->dropColumn('reported_by_admin');
            }

            if (Schema::hasColumn('feedback', 'reported_explanation')) {
                $table->dropColumn('reported_explanation');
            }

            if (Schema::hasColumn('feedback', 'reported_reason')) {
                $table->dropColumn('reported_reason');
            }

            if (Schema::hasColumn('feedback', 'is_reported')) {
                $table->dropColumn('is_reported');
            }

            if (Schema::hasColumn('feedback', 'is_testimonial')) {
                $table->dropColumn('is_testimonial');
            }

            if (Schema::hasColumn('feedback', 'feedback_type')) {
                $table->dropColumn('feedback_type');
            }

            if (Schema::hasColumn('feedback', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
