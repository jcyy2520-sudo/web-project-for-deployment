<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('password_login_enabled')->default(true)->after('verification_method');
        });

        // Existing Google accounts should require explicit password setup.
        \DB::table('users')
            ->whereNotNull('google_id')
            ->update(['password_login_enabled' => false]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('password_login_enabled');
        });
    }
};
