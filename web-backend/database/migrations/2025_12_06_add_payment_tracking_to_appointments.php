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
            // Add payment tracking fields
            $table->enum('payment_status', ['unpaid', 'paid', 'partial'])->default('unpaid')->after('status');
            $table->decimal('payment_amount', 10, 2)->nullable()->after('payment_status');
            $table->decimal('discount_amount', 10, 2)->nullable()->after('payment_amount');
            $table->string('discount_type')->nullable()->after('discount_amount');
            $table->foreignId('processed_by')->nullable()->constrained('users')->onDelete('set null')->after('completed_by');
            $table->timestamp('payment_date')->nullable()->after('processed_by');
            $table->text('payment_notes')->nullable()->after('payment_date');
            
            // Add time slot fields for better tracking
            $table->time('start_time')->nullable()->after('appointment_time');
            $table->time('end_time')->nullable()->after('start_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['processed_by']);
            $table->dropColumn([
                'payment_status',
                'payment_amount',
                'discount_amount',
                'discount_type',
                'processed_by',
                'payment_date',
                'payment_notes',
                'start_time',
                'end_time'
            ]);
        });
    }
};
