<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('message_settings', function (Blueprint $table) {
            $table->id();
            $table->integer('user_message_limit')->default(2)->comment('Max consecutive messages a user can send before admin replies');
            $table->unsignedBigInteger('last_updated_by')->nullable()->comment('Admin who last updated this setting');
            $table->timestamps();

            $table->foreign('last_updated_by')->references('id')->on('users')->onDelete('set null');
        });

        // Insert default settings
        DB::table('message_settings')->insert([
            'user_message_limit' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_settings');
    }
};
