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
        // Create payment_methods table
        if (!Schema::hasTable('payment_methods')) {
            Schema::create('payment_methods', function (Blueprint $table) {
                $table->id();
                $table->string('name'); // cash, check, card, goods, discount, mixed, write-off
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        // Create payments table
        if (!Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('appointment_id')->constrained('appointments')->onDelete('cascade');
                $table->foreignId('recorded_by')->constrained('users')->onDelete('restrict'); // Attorney who recorded
                $table->decimal('service_price', 10, 2); // Reference price from service
                $table->decimal('amount_paid', 10, 2); // What client actually paid
                $table->decimal('discount_amount', 10, 2)->default(0); // Discount given
                $table->decimal('shortfall', 10, 2)->default(0); // service_price - amount_paid
                $table->foreignId('payment_method_id')->constrained('payment_methods')->onDelete('restrict');
                $table->string('payment_status')->default('unpaid'); // unpaid, partial, paid, overdue, disputed
                $table->text('notes')->nullable(); // Reason for partial payment, goods description, etc.
                $table->text('goods_description')->nullable(); // For barter payments
                $table->datetime('payment_date');
                $table->boolean('is_edited')->default(false);
                $table->text('edit_notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                // Indexes for performance
                $table->index('appointment_id');
                $table->index('recorded_by');
                $table->index('payment_status');
                $table->index('payment_date');
            });
        }

        // Create income_statements table for monthly/yearly summaries
        if (!Schema::hasTable('income_statements')) {
            Schema::create('income_statements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('attorney_id')->constrained('users')->onDelete('cascade');
                $table->integer('year');
                $table->integer('month');
                $table->integer('total_appointments_completed');
                $table->decimal('total_service_price', 10, 2); // Sum of service prices
                $table->decimal('total_amount_paid', 10, 2); // Sum of actual payments
                $table->decimal('total_shortfall', 10, 2); // Sum of shortfalls
                $table->decimal('total_discount_given', 10, 2)->default(0);
                $table->json('payment_methods_breakdown')->nullable(); // JSON breakdown by method
                $table->timestamps();

                // Unique constraint - one statement per attorney per month
                $table->unique(['attorney_id', 'year', 'month']);
            });
        }

        // Create completion_records table (optional, for enhanced tracking)
        if (!Schema::hasTable('completion_records')) {
            Schema::create('completion_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('appointment_id')->constrained('appointments')->onDelete('cascade');
                $table->foreignId('completed_by')->constrained('users')->onDelete('restrict'); // Attorney who completed
                $table->string('outcome_status'); // successful, partial, unsuccessful, rescheduled
                $table->integer('duration_minutes')->nullable(); // Actual time spent
                $table->text('work_done')->nullable(); // What was accomplished
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index('appointment_id');
                $table->index('completed_by');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('completion_records');
        Schema::dropIfExists('income_statements');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_methods');
    }
};
