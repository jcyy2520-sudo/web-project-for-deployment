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
            // Add completion tracking fields
            $table->datetime('completed_at')->nullable()->after('status');
            $table->text('completion_notes')->nullable()->after('completed_at');
            $table->foreignId('completed_by')->nullable()->constrained('users')->onDelete('set null')->after('completion_notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeignIdFor('completed_by', 'users');
            $table->dropColumn(['completed_at', 'completion_notes', 'completed_by']);
        });
    }
};
