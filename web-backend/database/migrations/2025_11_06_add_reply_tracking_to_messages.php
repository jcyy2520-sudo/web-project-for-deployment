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
        Schema::table('messages', function (Blueprint $table) {
            // Track which admin message this reply is for (null if it's from admin)
            $table->unsignedBigInteger('reply_to_message_id')->nullable()->after('message');
            $table->foreign('reply_to_message_id')->references('id')->on('messages')->onDelete('cascade');
            
            // Track the number of replies this message has received
            $table->unsignedInteger('replies_count')->default(0)->after('reply_to_message_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['reply_to_message_id']);
            $table->dropColumn('reply_to_message_id');
            $table->dropColumn('replies_count');
        });
    }
};
