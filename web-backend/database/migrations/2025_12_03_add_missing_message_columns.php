<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('messages', function (Blueprint $table) {
            // Add missing columns if they don't exist
            if (!Schema::hasColumn('messages', 'subject')) {
                $table->string('subject')->nullable()->after('message');
            }
            if (!Schema::hasColumn('messages', 'type')) {
                $table->string('type')->nullable()->after('subject');
            }
            if (!Schema::hasColumn('messages', 'reply_to_message_id')) {
                $table->foreignId('reply_to_message_id')->nullable()->constrained('messages')->onDelete('cascade')->after('type');
            }
            if (!Schema::hasColumn('messages', 'replies_count')) {
                $table->integer('replies_count')->default(0)->after('reply_to_message_id');
            }
        });
    }

    public function down()
    {
        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'replies_count')) {
                $table->dropColumn('replies_count');
            }
            if (Schema::hasColumn('messages', 'reply_to_message_id')) {
                $table->dropForeign(['reply_to_message_id']);
                $table->dropColumn('reply_to_message_id');
            }
            if (Schema::hasColumn('messages', 'type')) {
                $table->dropColumn('type');
            }
            if (Schema::hasColumn('messages', 'subject')) {
                $table->dropColumn('subject');
            }
        });
    }
};
