<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_unavailabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->onDelete('cascade');
            $table->string('reason');
            $table->string('reason_category')->default('custom');
            $table->boolean('is_global')->default(true); // true = indefinite, false = scheduled
            $table->dateTime('unavailable_from')->nullable(); // null if global/indefinite
            $table->dateTime('unavailable_until')->nullable(); // null if global/indefinite
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['service_id', 'is_active']);
            $table->index(['unavailable_from', 'unavailable_until'], 'svc_unavail_date_range_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_unavailabilities');
    }
};
