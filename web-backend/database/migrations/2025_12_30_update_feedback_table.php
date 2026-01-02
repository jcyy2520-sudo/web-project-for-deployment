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
            // Add new columns for enhanced feedback system
            $table->string('feedback_type')->default('general')->comment('Type: service_quality, speed, support, system_experience, bug_report, suggestion, other');
            $table->boolean('is_reported')->default(false)->comment('Whether feedback has been reported by admin');
            $table->string('reported_reason')->nullable()->comment('Reason for report: harassment, hate_speech, spam, threats, false_information, other');
            $table->text('reported_explanation')->nullable()->comment('Custom explanation for report');
            $table->unsignedBigInteger('reported_by_admin')->nullable()->comment('Admin ID who reported the feedback');
            $table->boolean('is_blocked')->default(false)->comment('Whether user is blocked from submitting feedback');
            $table->timestamp('blocked_until')->nullable()->comment('When the block expires');
            $table->softDeletes()->comment('Soft delete timestamp');
            
            // Add foreign key for reported_by_admin
            $table->foreign('reported_by_admin')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feedback', function (Blueprint $table) {
            $table->dropForeign(['reported_by_admin']);
            $table->dropColumn([
                'feedback_type',
                'is_reported',
                'reported_reason',
                'reported_explanation',
                'reported_by_admin',
                'is_blocked',
                'blocked_until',
                'deleted_at'
            ]);
        });
    }
};
