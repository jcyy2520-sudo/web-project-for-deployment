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
        if (!Schema::hasTable('payments')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            // Discount fields for PWD, Senior Citizen, Student
            $table->boolean('is_pwd')->default(false)->after('notes');
            $table->decimal('pwd_discount_amount', 10, 2)->default(0)->after('is_pwd');
            
            $table->boolean('is_senior_citizen')->default(false)->after('pwd_discount_amount');
            $table->decimal('senior_discount_amount', 10, 2)->default(0)->after('is_senior_citizen');
            
            $table->boolean('is_student')->default(false)->after('senior_discount_amount');
            $table->decimal('student_discount_amount', 10, 2)->default(0)->after('is_student');
            
            // Total discount tracking
            $table->decimal('total_discount_applied', 10, 2)->default(0)->after('student_discount_amount');
            
            // Final amounts after all discounts
            $table->decimal('amount_after_discount', 10, 2)->nullable()->after('total_discount_applied');
            
            // Audit tracking for edits
            $table->timestamp('last_edited_at')->nullable()->after('amount_after_discount');
            $table->foreignId('last_edited_by')->nullable()->constrained('users')->onDelete('set null')->after('last_edited_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'is_pwd',
                'pwd_discount_amount',
                'is_senior_citizen',
                'senior_discount_amount',
                'is_student',
                'student_discount_amount',
                'total_discount_applied',
                'amount_after_discount',
                'last_edited_at',
                'last_edited_by'
            ]);
        });
    }
};
