<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refund_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // 'request' for refund request reasons, 'decline' for decline/rejection reasons
            $table->string('key'); // machine-readable key like 'customer_request'
            $table->string('label'); // human-readable label like 'Customer Request'
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false); // system defaults can't be deleted
            $table->integer('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->unique(['type', 'key']); // key is unique per type
            $table->index(['type', 'is_active']);
        });

        // Seed default request reasons
        DB::table('refund_reasons')->insert([
            ['type' => 'request', 'key' => 'customer_request', 'label' => 'Customer Request', 'is_active' => true, 'is_default' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'request', 'key' => 'service_not_provided', 'label' => 'Service Not Provided', 'is_active' => true, 'is_default' => true, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'request', 'key' => 'duplicate_payment', 'label' => 'Duplicate Payment', 'is_active' => true, 'is_default' => true, 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'request', 'key' => 'service_cancellation', 'label' => 'Service Cancellation', 'is_active' => true, 'is_default' => true, 'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'request', 'key' => 'poor_service', 'label' => 'Poor Service', 'is_active' => true, 'is_default' => true, 'sort_order' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'request', 'key' => 'other', 'label' => 'Other', 'is_active' => true, 'is_default' => true, 'sort_order' => 99, 'created_at' => now(), 'updated_at' => now()],

            // Default decline reasons
            ['type' => 'decline', 'key' => 'duplicate_refund', 'label' => 'Duplicate Refund Request', 'is_active' => true, 'is_default' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'decline', 'key' => 'outside_policy', 'label' => 'Outside Refund Policy', 'is_active' => true, 'is_default' => true, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'decline', 'key' => 'no_service_issue', 'label' => 'No Service Issue Found', 'is_active' => true, 'is_default' => true, 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'decline', 'key' => 'insufficient_documentation', 'label' => 'Insufficient Documentation', 'is_active' => true, 'is_default' => true, 'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'decline', 'key' => 'request_too_late', 'label' => 'Request Too Late (30 days)', 'is_active' => true, 'is_default' => true, 'sort_order' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'decline', 'key' => 'service_completed', 'label' => 'Service Completed Successfully', 'is_active' => true, 'is_default' => true, 'sort_order' => 6, 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'decline', 'key' => 'user_fault', 'label' => 'Refund Not Due to Business Fault', 'is_active' => true, 'is_default' => true, 'sort_order' => 7, 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'decline', 'key' => 'other', 'label' => 'Other Reason', 'is_active' => true, 'is_default' => true, 'sort_order' => 99, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_reasons');
    }
};
