<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Check if column already exists
        if (Schema::hasColumn('users', 'uuid')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
        });

        // Generate UUIDs for all users
        $users = \DB::table('users')->get();
        foreach ($users as $user) {
            \DB::table('users')
                ->where('id', $user->id)
                ->update(['uuid' => (string) Str::uuid()]);
        }

        // Update column to not nullable
        if (\DB::getDriverName() !== 'sqlite') {
            \DB::statement('ALTER TABLE users MODIFY uuid CHAR(36) NOT NULL');
            // Add unique constraint
            \DB::statement('ALTER TABLE users ADD UNIQUE KEY users_uuid_unique (uuid)');
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_uuid_unique');
            $table->dropColumn('uuid');
        });
    }
};
