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
        Schema::table('chat_messages', function (Blueprint $table) {
            // Make user_id nullable so guest messages can be stored
            $table->unsignedBigInteger('user_id')->nullable()->change();
            
            // Add session_id column for tracking guest conversations
            if (!Schema::hasColumn('chat_messages', 'session_id')) {
                $table->string('session_id')->nullable()->after('user_id');
                $table->index('session_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            if (Schema::hasColumn('chat_messages', 'session_id')) {
                $table->dropIndex(['session_id']);
                $table->dropColumn('session_id');
            }
        });
    }
};
