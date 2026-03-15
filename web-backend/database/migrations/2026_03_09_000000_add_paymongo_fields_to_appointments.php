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
            $table->string('paymongo_checkout_id')->nullable()->after('payment_type');
            $table->string('paymongo_payment_id')->nullable()->after('paymongo_checkout_id');
            $table->string('paymongo_checkout_url')->nullable()->after('paymongo_payment_id');
            $table->string('paymongo_status')->nullable()->after('paymongo_checkout_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn([
                'paymongo_checkout_id',
                'paymongo_payment_id',
                'paymongo_checkout_url',
                'paymongo_status',
            ]);
        });
    }
};
