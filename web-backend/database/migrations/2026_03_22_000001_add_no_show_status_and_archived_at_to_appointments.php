<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Extend the status enum to include no_show
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE appointments MODIFY COLUMN status ENUM('pending','approved','completed','cancelled','declined','no_show') DEFAULT 'pending'");
        }

        // Add archived_at column (separate from soft-delete deleted_at)
        Schema::table('appointments', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('deleted_at');
            $table->index('archived_at');
        });
    }

    public function down(): void
    {
        // Revert no_show appointments to cancelled before removing the enum value
        DB::table('appointments')->where('status', 'no_show')->update(['status' => 'cancelled']);
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE appointments MODIFY COLUMN status ENUM('pending','approved','completed','cancelled','declined') DEFAULT 'pending'");
        }

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['archived_at']);
            $table->dropColumn('archived_at');
        });
    }
};
