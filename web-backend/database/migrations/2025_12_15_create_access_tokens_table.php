<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_tokens', function (Blueprint $table) {
            $table->id();
            $table->uuid('token_uuid')->unique();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->uuid('user_uuid')->nullable();
            $table->string('token_hash')->unique();
            $table->string('purpose')->default('general');
            $table->text('metadata')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('used_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'purpose']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_tokens');
    }
};
