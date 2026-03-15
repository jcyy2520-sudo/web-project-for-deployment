<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('account_status', 20)->default('active')->after('is_active');
            $table->text('account_status_reason')->nullable()->after('account_status');
            $table->index('account_status');
        });

        // Sync existing data: inactive users without soft-delete are deactivated
        DB::table('users')
            ->where('is_active', false)
            ->whereNull('deleted_at')
            ->update(['account_status' => 'deactivated']);

        // Soft-deleted users are archived/deleted
        DB::table('users')
            ->whereNotNull('deleted_at')
            ->update(['account_status' => 'deleted']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['account_status']);
            $table->dropColumn(['account_status', 'account_status_reason']);
        });
    }
};
