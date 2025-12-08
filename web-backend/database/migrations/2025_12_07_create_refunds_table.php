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
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->onDelete('cascade');
            $table->foreignId('requested_by')->constrained('users')->onDelete('restrict'); // Who requested the refund
            $table->decimal('refund_amount', 10, 2); // Amount to be refunded
            $table->decimal('original_amount', 10, 2); // Original payment amount
            $table->string('reason'); // Reason for refund
            $table->text('description')->nullable(); // Detailed description
            $table->string('status')->default('pending'); // pending, approved, rejected, completed
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null'); // Admin who approved
            $table->text('approval_notes')->nullable(); // Admin notes on approval/rejection
            $table->datetime('approved_at')->nullable();
            $table->datetime('completed_at')->nullable(); // When refund was actually processed
            $table->string('refund_method')->nullable(); // cash, card, check, bank_transfer, etc
            $table->string('transaction_id')->nullable(); // External transaction ID (for credit card, bank transfer)
            $table->string('payment_method_reversed')->nullable(); // Original payment method
            $table->text('rejection_reason')->nullable(); // Why refund was rejected
            $table->boolean('is_partial')->default(false); // Whether this is a partial refund
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index('requested_by');
            $table->index('status');
            $table->index('approved_by');
            $table->index(['status', 'created_at']);
            $table->index('appointment_id'); // For finding refunds per appointment
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
