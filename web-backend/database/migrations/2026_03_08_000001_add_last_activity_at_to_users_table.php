<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_activity_at')->nullable()->after('is_active');
            $table->index('last_activity_at');
        });

        // Initialize last_activity_at for existing users based on their most recent activity
        $func = \DB::getDriverName() === 'sqlite' ? 'MAX' : 'GREATEST';
        \DB::statement("
            UPDATE users 
            SET last_activity_at = {$func}(
                COALESCE(updated_at, created_at),
                COALESCE(
                    (SELECT MAX(a.created_at) FROM appointments a WHERE a.user_id = users.id),
                    created_at
                )
            )
            WHERE last_activity_at IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['last_activity_at']);
            $table->dropColumn('last_activity_at');
        });
    }
};
